# gdaxbot
WIP: Gdax trading bot (DISCLAIMER: use at your own risc)

Great thanks to https://medium.com/@joeldg/an-advanced-tutorial-a-new-crypto-currency-trading-bot-boilerplate-framework-e777733607ae (https://github.com/joeldg/bowhead),

https://github.com/benfranke/gdax-php

Initial trading bot.

## Setup

The bot has 3 working parts

## Ticker
- The ticker to get the price data (every 5 seconds)

## Buy bot
- The buy bot that will buy 0.001 BTC (can be set in .env)  when we get a buy signal from a stragegy

## Positions bot
- The position bot that will keep track of the open positions and the currentprice. It wil run every 2 seconds in case it needs to sell
when price drops under stoploss threshold. The bot is a trailing stoploss, which means it will follow the price up and set a new limit where
when the price goes below, it will trigger a sell. Please test what works for you, coz if the price goes down, it will trigger a sell as damage control.


## Requirements && Install

The bot uses php 7.1, ext-trader (TA lib), redis and mysql database.

## Step 1:

composer install

## Step 2: Create mysql database and dump 

mysql -uroot -p whatever < database/create_tables.sql

## Step 3:

Copy .env.example to .env and fill in some credentials you got from gdax

 GDAX_PASSWORD=
 
 GDAX_API_SECRET=
 
 GDAX_API_KEY=

In config/bot.yml you can put the bot in testmode by setting sandbox to true. Please create a test apikey for gdax else
it will not work.

The rest of the settings is in the setting table.

## Step 4:

To run the buy bots

php bin/console bot:run:ticker

php bin/console bot:run:positions

php bin/console bot:run:buys

If you are daring, you can put this in a supervisorctl.


#~$ more /etc/supervisor/conf.d/crypt.conf

[program:gdaxbuys]
command=/usr/bin/php bin/console bot:run:buys
directory=/home/user/gdaxbot
startretries=20
stopwaitsecs=10
autostart=true

[program:gdaxpositions]
command=/usr/bin/php bin/console bot:run:positions
directory=/home/user/gdaxbot
startretries=20
stopwaitsecs=10
autostart=true


[program:gdaxticker]
command=/usr/bin/php bin/console bot:run:ticker
directory=/home/user/gdaxbot
startretries=20
stopwaitsecs=10
autostart=true

##Settings

To control the bot, edit the settings table

`stoploss`  percentage for trshold when to take the loss/profit (trailing)

`max_orders` how many positions may be open at a time 

`bottom`, `top` what range should the bot operate (limit the range when to buy, not to sell)

`size` How many BTC should we buy, for now conservative to test it 0.001 BTC

`botactive` is the bot active (can it buy and sell) 

