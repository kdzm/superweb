<?php
/**
 * Created by PhpStorm.
 * User: lychee
 * Date: 2018/12/10
 * Time: 14:32
 */

namespace console\collectors\profile\interfaces;


Interface searchByName
{
    /**
     * @param $name string
     * @param $options array
     * @return mixed
     */
    static function searchByName($name, $options);
}