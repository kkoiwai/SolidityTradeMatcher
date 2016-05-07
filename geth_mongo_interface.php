<?php
define("COTRACT_ADDR", "0xcafebabedeadbeafcafebabedeadbeafcafebabedeadbeaf");// your contract
$mongo_manager = new MongoDB\Driver\Manager("mongodb://localhost:3001");
define("MONGO_COLLECTION", "meteor.trades");
define("GETH_RPC_URL", "http://localhost:8545");
define("GETH_GAS_LIMIT", "0xffffff"); // default gas limit is too small

$MY_ACCT = json_decode(call_method("eth_accounts"), true, 512, JSON_BIGINT_AS_STRING)['result'][0];
$JSON_ID_COUNTER = 1;

function call_method($method, $params="[]", $id=1){
    $defaults = array( 
        CURLOPT_POST => 1, 
        CURLOPT_HEADER => 0, 
        CURLOPT_URL => GETH_RPC_URL, 
        CURLOPT_FRESH_CONNECT => 1, 
        CURLOPT_RETURNTRANSFER => 1, 
        CURLOPT_FORBID_REUSE => 1, 
        CURLOPT_TIMEOUT => 4, 

        CURLOPT_POSTFIELDS => '{"jsonrpc":"2.0","method":"' . $method . '","params":' . $params . ',"id":' . $id . '}'

    ); 

    $ch = curl_init(); 
    curl_setopt_array($ch, ($defaults)); 
    if( ! $result = curl_exec($ch)) 
    { 
        trigger_error(curl_error($ch)); 
    } 
    curl_close($ch); 
  //echo 'call_method  : '.'{"jsonrpc":"2.0","method":"' . $method . '","params":' . $params . ',"id":' . $id . '}'."\n";
  //echo $result."\n";
  return $result;
}

function eth_call($data="[]",$id=1){
  return call_method("eth_call",'[{"to": "' .COTRACT_ADDR. '", "data": "'.$data.'"}]',$id);
}

function getTrade($tradeID){
 // echo str_pad(dechex($input), 64, "0", STR_PAD_LEFT); 
  $retval = eth_call('0x2db25e05'.str_pad(dechex($tradeID), 64, "0", STR_PAD_LEFT),1);
  return parse_trade($retval);
}

function parse_trade($retStr){
  $retArray = json_decode($retStr, true, 512, JSON_BIGINT_AS_STRING);
  //echo "parse_trade: ".$retStr."\n";
  //var_dump($retArray);
  $result = $retArray["result"];
  //$result = $retStr;
  return  array(
    "sender"       => substr(substr($result,2,64),-40,40),
    "senderid"     => trim(hex2bin(substr($result,2+64*1,64))),
    "seller"       => substr(substr($result,2+64*2,64),-40,40),
    "buyer"        => substr(substr($result,2+64*3,64),-40,40),
    "seccode"      => trim(hex2bin(substr($result,2+64*4,64))),
    "tradedate"    => hexdec(substr($result,2+64*5,64)),
    "deliverydate" => hexdec(substr($result,2+64*6,64)),
    "quantity"     => hexdec(substr($result,2+64*7,64)),
    "price"        => hexdec(substr($result,2+64*8,64)),
    "deliveryamount" => hexdec(substr($result,2+64*9,64)),
    "matchedtrade" => hexdec(substr($result,2+64*10,64))
    );
}

function updateMongoByTradeID($tradeID){
  global $mongo_manager,$MY_ACCT;
  $trade = getTrade($tradeID);
  echo "updateMongoByTradeID( ".$tradeID." )\n";
  var_dump($trade);
  
  if (intval($trade["sender"])==0){
    echo "no trade found\n";
    return false;
  }
  
  // set status by matchiedtrade
  if($trade["matchedtrade"]>0){
    $trade["status"]="matched";
    $trade["matchedkey"]= min(intval($tradeID),intval($trade["matchedtrade"]));
  }else{
    $trade["status"]="unmatch";
  }
  // set tradeID
  $trade["tradeID"] = $tradeID;
  
  $bulk = new MongoDB\Driver\BulkWrite;
  if (strcasecmp($trade['sender'],substr($MY_ACCT,-40,40))==0){
    // MY Trade
    $bulk->update(['_id' => $trade['senderid'],'sender' => $trade['sender']], ['$set' => $trade], ['multi' => false, 'upsert' => true]);
  }else{
    // OTHER's Trade
    //$bulk->insert($trade);
    $bulk->update(['tradeID' => $trade['tradeID']], ['$set' => $trade], ['multi' => false, 'upsert' => true]);
  }
  
  $result = $mongo_manager->executeBulkWrite(MONGO_COLLECTION, $bulk);
  
  $retval = true;
  /* If the WriteConcern could not be fulfilled */
  if ($writeConcernError = $result->getWriteConcernError()) {
      printf("%s (%d): %s\n", $writeConcernError->getMessage(), $writeConcernError->getCode(), var_export($writeConcernError->getInfo(), true));
      $retval = false;
  }

  /* If a write could not happen at all */
  foreach ($result->getWriteErrors() as $writeError) {
      printf("Operation#%d: %s (%d)\n", $writeError->getIndex(), $writeError->getMessage(), $writeError->getCode());
      $retval = false;
  }
  return $retval;
}

function addTrade($senderid, $seller, $buyer, $seccode, $tradedate,  $deliverydate, $quantity, $price, $deliveryamount){
  // function addTrade(bytes32 senderid, address seller, address buyer,  bytes12 seccode, uint32 tradedate, uint32 deliverydate, uint quantity, uint32 price, int deliveryamount)
  global $JSON_ID_COUNTER;
  $retval = eth_sendTransaction('0x582ec91b'
                  .str_pad(bin2hex(substr($senderid,0,32)), 64, "0", STR_PAD_RIGHT)
                  .str_pad(substr($seller,-40,40), 64, "0", STR_PAD_LEFT)
                  .str_pad(substr($buyer,-40,40) , 64, "0", STR_PAD_LEFT)
                  .str_pad(bin2hex(substr($seccode,0,12)), 64, "0", STR_PAD_RIGHT)
                  .str_pad(dechex(intval($tradedate)), 64, "0", STR_PAD_LEFT)
                  .str_pad(dechex(intval($deliverydate)), 64, "0", STR_PAD_LEFT)
                  .str_pad(dechex(intval($quantity)), 64, "0", STR_PAD_LEFT)
                  .str_pad(dechex(intval($price)), 64, "0", STR_PAD_LEFT)
                  .str_pad(dechex(intval($deliveryamount)), 64, "0", STR_PAD_LEFT)
                  ,$JSON_ID_COUNTER++);

  return parse_transaction_hash($retval);
}
                             
function parse_transaction_hash($retStr){
  $retArray = json_decode($retStr, true, 512, JSON_BIGINT_AS_STRING);
  if( isset($retArray["error"])){
    echo "parse_transaction_hash error\n";
    var_dump($retArray);
    return false;
  }
  $result = $retArray["result"];
  //echo "parse_transaction_hash\n";
  //var_dump($result);
  return  substr($result,2,64);
}

function eth_sendTransaction($data="[]",$id=1){
  global $MY_ACCT;
  return call_method("eth_sendTransaction",'[{"from":"'.$MY_ACCT.'","to":"' .COTRACT_ADDR. '","data":"'.$data.'","gas":"'.GETH_GAS_LIMIT.'","value":"0x0"}]',$id); 
}
                             
function addQueuedTrades(){
  global $mongo_manager;
  $filter = ['status' => 'queued'];

  $query = new MongoDB\Driver\Query($filter);
  $cursor = $mongo_manager->executeQuery(MONGO_COLLECTION, $query);
  
  foreach ($cursor as $document) {
    //var_dump($document);
    $tranhash = addTradeFromDocument($document);
    if($tranhash){
      updateMongoTranHash($document->{"_id"},$tranhash);
    }
  } 
}

function addTradeFromDocument($document){
  // addTrade($senderid, $seller,  $buyer,   $seccode,  $tradedate,  $deliverydate,  $quantity,  $price,  $deliveryamount)
  
  return addTrade($document->{"_id"}, $document->{"seller"}, $document->{"buyer"}, $document->{"seccode"}, $document->{"tradedate"}, $document->{"deliverydate"}, $document->{"quantity"}, $document->{"price"}, $document->{"deliveryamount"});
}

function updateMongoTranHash($id,$tranhash){
  global $mongo_manager,$MY_ACCT;
  $bulk = new MongoDB\Driver\BulkWrite;
  $bulk->update(['_id' => $id], ['$set' => ['status' => 'pending', 'transactionhash' => $tranhash, 'sender' => substr($MY_ACCT,-40,40)]], ['multi' => false, 'upsert' => false]);
  $result = $mongo_manager->executeBulkWrite(MONGO_COLLECTION, $bulk);
  
  $retval = true;
  /* If the WriteConcern could not be fulfilled */
  if ($writeConcernError = $result->getWriteConcernError()) {
      printf("%s (%d): %s\n", $writeConcernError->getMessage(), $writeConcernError->getCode(), var_export($writeConcernError->getInfo(), true));
    
      $retval = false;
  }

  /* If a write could not happen at all */
  foreach ($result->getWriteErrors() as $writeError) {
      printf("Operation#%d: %s (%d)\n", $writeError->getIndex(), $writeError->getMessage(), $writreError->getCode());
    
      $retval = false;
  }
  return $retval;
}



while(false){
  // get queued trades from mongo and send to the network (addTrades) and change the status to pending
  addQueuedTrades();
  
  // traverse all trades in the network and update mongo (until the filter function is implemented)
  $i=1;
  while( updateMongoByTradeID($i++));
  
  sleep(5);
  // 
}
?>