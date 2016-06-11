# SolidityTradeMatcher
An experimental code to implement Security Trade Matching over Ethereum

ほふりが行っている、決済照合システムの中の、「約定照合」または「取引照合」の部分を、非常にシンプルにした形で、Etherium上で実装することができるかの実験です。
こちらに経緯、仕様等をまとめています。
https://github.com/kkoiwai/SolidityTradeMatcher/blob/master/TradeMatchingOnEthereum.pdf

## How to use まずは試してみる

    docker run -p 3331:3000 --name ethereum1 kocko/soliditytradematcher
    # １０分ほど待つ（１号機内で自動的にコントラクトのコンパイル、登録が走るので）
    # wait for approc. 10 mins while ethereum1 node compilies the solidity code
    docker run -p 3332:3000 --name ethereum2 --link ethereum1:ethereum1 kocko/soliditytradematcher 2
    docker run -p 3333:3000 --name ethereum3 --link ethereum1:ethereum1 --link ethereum2:ethereum2 kocko/soliditytradematcher 3

ここまで起動したら、`http://localhost:3331` にアクセスして、いろいろ遊んでみてください。
