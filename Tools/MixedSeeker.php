<?php

namespace Ow\Bundle\OwEzFetchAndListBundle\Tools;

class MixedSeeker
{

    /**
     * @param $mixed
     * @param $keySearch
     * @param  bool $returnVal
     * @return bool|object|array
     */
    public static function findKey($mixed, $keySearch, $returnVal = false)
    {
        $search = explode('.', $keySearch);
        if (is_array($mixed)) {
            if (array_key_exists($search[0], $mixed)) {
                if (isset($search[1]) && !empty($search[1])) {
                    if ($mixed[$search[0]] == $search[1]) {
                        if ($returnVal) {
                            return $mixed;
                        } else {
                            return true;
                        }
                    }
                } else {
                    return true;
                }
            }
            foreach ($mixed as $key => $val) {
                $res = self::findKey($val, $keySearch, $returnVal);
                if (is_bool($res) && $res) {
                    return true;
                } else if (!is_bool($res) && $returnVal) {
                    return $res;
                }
            }
        } elseif (is_object($mixed)) {
            if (isset($mixed->{$search[0]})) {
                if (isset($search[1]) && !empty($search[1])) {
                    if ($mixed->{$search[0]} == $search[1]) {
                        if ($returnVal) {
                            return $mixed;
                        } else {
                            return true;
                        }
                    }
                } else {
                    return true;
                }
            }
        } else {
            return false;
        }

        return false;
    }

    /**
     * @param $mixed
     * @param $keySearch
     * @return bool|object|array
     */
    public static function getKey($mixed, $keySearch)
    {
        return self::findKey($mixed, $keySearch, true);
    }
}