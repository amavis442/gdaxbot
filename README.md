# gdaxbot
WIP: Gdax trading bot (DISCLAIMER: use at your own risc)

Initial trading bot.

## Step 1:

composer update

## Step 2: sqllite database

php migrate.php

## Step 3:

Copy .env.example to .env and fill in some credentials you got from gdax

 GDAX_PASSWORD=
 
 GDAX_API_SECRET=
 
 GDAX_API_KEY=

spread=0.05 // interval for next buy order

order_size=0.01 // size of buy order in this case 0.01 litecoin

max_orders=5 // Number of orders (buy + sell)

max_orders_per_run=5

waitingtime=20

lifetime=45 // Time a buy order can live when it is still pending

BUYINGTRHESHOLD=233.0 // When should the buying stop

SELLINGTRESHOLD=229.0 // When shoud the selling stop

## Step 4:

php gdaxbot.sh bot:run

If you are daring, you can put this in a cronjob.
