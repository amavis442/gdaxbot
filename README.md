# gdaxbot
WIP: Gdax trading bot (DISCLAIMER: use at your own risc)

Great thanks to https://medium.com/@joeldg/an-advanced-tutorial-a-new-crypto-currency-trading-bot-boilerplate-framework-e777733607ae (https://github.com/joeldg/bowhead),

https://github.com/benfranke/gdax-php

Initial trading bot.

## Step 1:

composer update

## Step 2: Create mysql database and dump 

mysql -uroot -p whatever < database/create_tables.sql

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

php bin/console bot:run

If you are daring, you can put this in a cronjob.


Run th eticker

#~$ more /etc/supervisor/conf.d/crypt.conf

[program:wsgdax]
command=/usr/bin/php bin/console bot:run
directory=/home/ubuntu/gdax
startretries=3
stopwaitsecs=10
autostart=true
