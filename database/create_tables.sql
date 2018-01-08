-- Create mysql orders table
CREATE TABLE `orders` (
    `id` INTEGER PRIMARY KEY AUTO_INCREMENT, 
    `parent_id` integer unsigned, 
    `position_id` integer unsigned,
    `side` varchar(10), 
    `size` varchar(20), 
    `amount` decimal(15,9),
    `status` varchar(40), 
    `order_id` varchar(40),
    `strategy` varchar(40),
    `take_profit` decimal(10,2),
    `signalpos` int(11) DEFAULT NULL,
    `signalneg` int(11) DEFAULT NULL,
    `position` enum('pending','open','closed') default 'pending',
    `close_reason` varchar(20) DEFAULT NULL,
    `created_at` datetime, 
    `updated_at` timestamp
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create mysql orders table
CREATE TABLE `positions` (
    `id` INTEGER PRIMARY KEY AUTO_INCREMENT, 
    `order_id` varchar(40), 
    `size` varchar(20), 
    `amount` decimal(15,9),
    `position` enum('open','pending','closed') default 'open',
    `close_reason` varchar(20) DEFAULT NULL,
    `created_at` datetime, 
    `updated_at` timestamp
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create the settings table for the bot
CREATE TABLE `settings` (
    `id` INTEGER PRIMARY KEY AUTO_INCREMENT, 
    `spread` decimal(8,2),
    `sellspread` decimal(8,2), 
    `stoploss` decimal(8,2),
    `max_orders` int, 
    `bottom` decimal(10,2),
    `top` decimal(10,2) ,
    `size` varchar(10),
    `lifetime` int, 
    `botactive` tinyint(1), 
    `created_at` datetime, 
    `updated_at` timestamp
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `settings` SET `spread`=0.01, `max_orders`=3, `top`=12000, `bottom` = 11000, `size`='0.001',`lifetime` = 90,`botactive` = 0, `created_at` = '2017-12-29 11:00:00';

-- Create the open, high, low, closed table
CREATE TABLE `ohlc` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `product_id` varchar(10) DEFAULT NULL,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timeid` bigint(28) DEFAULT NULL,
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` int(18) DEFAULT NULL,
  UNIQUE KEY `product_id` (`product_id`,`timeid`),
  KEY `ctime` (`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Same as above but for 1 minute intervals
CREATE TABLE `ohlc_1m` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `product_id` varchar(10) DEFAULT NULL,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timeid` bigint(28) DEFAULT NULL,
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` int(18) DEFAULT NULL,
  UNIQUE KEY `product_id` (`product_id`,`timeid`),
  KEY `ctime` (`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Same as above but for 5 minute intervals
CREATE TABLE `ohlc_5m` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `product_id` varchar(10) DEFAULT NULL,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timeid` bigint(28) DEFAULT NULL,
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` int(18) DEFAULT NULL,
  UNIQUE KEY `product_id` (`product_id`,`timeid`),
  KEY `ctime` (`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Same as above but for 15 minute intervals
CREATE TABLE `ohlc_15m` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `product_id` varchar(10) DEFAULT NULL,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timeid` bigint(28) DEFAULT NULL,
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` int(18) DEFAULT NULL,
  UNIQUE KEY `product_id` (`product_id`,`timeid`),
  KEY `ctime` (`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Same as above but for 30 minute intervals
CREATE TABLE `ohlc_30m` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `product_id` varchar(10) DEFAULT NULL,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timeid` bigint(28) DEFAULT NULL,
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` int(18) DEFAULT NULL,
  UNIQUE KEY `product_id` (`product_id`,`timeid`),
  KEY `ctime` (`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Same as above but for 60 minute intervals
CREATE TABLE `ohlc_1h` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `product_id` varchar(10) DEFAULT NULL,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timeid` bigint(28) DEFAULT NULL,
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` int(18) DEFAULT NULL,
   UNIQUE KEY `product_id` (`product_id`,`timeid`),
  KEY `ctime` (`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'ohlc_tick'
CREATE TABLE `ohlc_tick` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `product_id` varchar(10) DEFAULT NULL,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timeid` bigint(28) DEFAULT NULL,
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` int(18) DEFAULT NULL,
   UNIQUE KEY `product_id` (`product_id`,`timeid`),
   KEY `ctime` (`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'bowhead_strategy'
CREATE TABLE `strategy` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ctime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `position_id` varchar(68) DEFAULT NULL,
  `pair` varchar(25) DEFAULT NULL,
  `direction` varchar(5) DEFAULT NULL,
  `profit` bigint(22) DEFAULT NULL,
  `strategy` varchar(30) DEFAULT NULL,
  `signalpos` int(11) DEFAULT NULL,
  `signalneg` int(11) DEFAULT NULL,
  `close_reason` varchar(20) DEFAULT NULL,
  `state` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'historical'
CREATE TABLE `historical` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pair` varchar(7) DEFAULT NULL,
  `buckettime` datetime DEFAULT NULL,
  `low` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `open` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  `volume` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pair_2` (`pair`,`buckettime`),
  KEY `pair` (`pair`),
  KEY `buckettime` (`buckettime`),
  KEY `volume` (`volume`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'symbols'
CREATE TABLE `symbols` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `symbol` varchar(10) DEFAULT NULL,
  `category` varchar(20) DEFAULT NULL,
  `maximum_leverage` int(11) DEFAULT NULL,
  `maximum_amount` int(11) DEFAULT NULL,
  `overnight_charge_long_percent` float DEFAULT NULL,
  `overnight_charge_short_percent` float DEFAULT NULL,
  `decimals` int(11) DEFAULT NULL,
  `timezone` varchar(80) DEFAULT NULL,
  `timezone_offset` varchar(10) DEFAULT NULL,
  `open_day` varchar(80) DEFAULT NULL,
  `open_time` time DEFAULT NULL,
  `close_day` varchar(80) DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `daily_break_start` time DEFAULT NULL,
  `daily_break_stop` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

