<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Util;

/**
 * Description of Transform
 *
 * @author patrick
 */
class Transform
{
   public static function toArray($result)
    {
        if (is_array($result)) {
            $rows = [];
            foreach ($result as $row) {
                $rows[] = (array) $row;
            }
            return $rows;
        } else {
            return [];
        }
    }
}
