<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 02-01-18
 * Time: 13:04
 */

namespace App\Util;

/**
 * Class Cache
 *
 * @package App\Util
 */
class Cache
{
    protected        $cache;
    protected static $instance = null;


    public function __contruct()
    {
        if (is_null(self::$instance)) {
            self::$instance = $this;
        }
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function setCache($cache)
    {
        $instance = self::getInstance();
        $instance->cache = $cache;
    }


    public static function put($name, $value, $lifetime = 180)
    {
        self::getInstance()->putItem($name, $value, $lifetime);
    }


    public function putItem($name, $value, $lifetime = 180)
    {
        $item = $this->cache->getItem($name);
        $item->expiresAfter($lifetime);
        $item->set($value);
        $this->cache->save($item);
    }

    public static function get($name, $default = null)
    {
        return self::getInstance()->getItem($name, $default);
    }

    public function getItem($name, $default = null)
    {
        $item = $this->cache->getItem($name);
        if ($item->isHit()) {
            return $item->get();
        } else {
            return $default;
        }
    }

    public static function clear($name)
    {
        self::getInstance()->clearItem($name);
    }

    public function clearItem($name)
    {
        $this->cache->deleteItem($name);
    }
}