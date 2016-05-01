contract TradeMatching{
    
        Trade t;

    // Trades to be matched
    struct Trade{
        address sender;
        address seller;
        address buyer;
        bytes12 seccode;
        uint32 tradedate; //YYYYMMDD
        uint32 deliverydate; //YYYYMMDD
        uint quantity;
        uint32 price;
        int deliveryamount;
        uint matchedtrade;
    }
    
    // The storage to save all trades
    uint numTrades = 1; //start with 1 to make clear 0=null
    mapping(uint => Trade) trademap;
    
    // data structure to link trades by Date
    mapping(uint32 => mapping(uint => uint)) tradesByDate;
    mapping(uint32 => uint) tradesByDateCounter;
    
    // The function
    function addTrade(address seller, address buyer,  bytes12 seccode, uint32 tradedate, uint32 deliverydate, uint quantity, uint32 price, int deliveryamount) returns (uint tradeID){
        tradeID = numTrades++;
        trademap[tradeID] = Trade(msg.sender,seller,buyer,seccode,tradedate,deliverydate, quantity,price, deliveryamount,0);
        
        // store tradeID to tradesByDate
        uint count = tradesByDateCounter[tradedate];
        tradesByDate[tradedate][count] = tradeID;
        tradesByDateCounter[tradedate] = count + 1;
        
        /* here starts "matching" part! */
        
        // for the rest of the trade date's trades,
        uint i;
        while(count > 0){
            i = tradesByDate[tradedate][count];
            t = trademap[i];
            count--;
            if(t.sender == msg.sender || t.matchedtrade != 0) continue;
            if(t.seller == seller && t.buyer == buyer && t.seccode == seccode && t.tradedate == tradedate && t.deliverydate == deliverydate && t.quantity == quantity && t.price == price && t.deliveryamount == deliveryamount){
                t.matchedtrade = tradeID;
                trademap[tradeID].matchedtrade = i;
                break;
            }
        }
    }
    
    // getter
    function getTrade(uint id) constant returns (address sender, address seller, address buyer,  bytes12 seccode, uint32 tradedate, uint32 deliverydate, uint quantity, uint32 price, int deliveryamount, uint matchedtrade){
        t=trademap[id];
        sender = t.sender;
        seller = t.seller;
        buyer = t.buyer;
        seccode = t.seccode;
        tradedate = t.tradedate;
        deliverydate = t.deliverydate;
        quantity = t.quantity;
        price = t.price;
        deliveryamount = t.deliveryamount;
        matchedtrade = t.matchedtrade;
    }
    
    
    function getTradeIdByTradedate(uint32 tradedate,uint count) constant returns (uint tradeID){
        tradeID = tradesByDate[tradedate][count];
    }
}

