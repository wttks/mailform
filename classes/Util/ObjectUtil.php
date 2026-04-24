<?php

namespace AIJOH\Util;

class ObjectUtil {
    
    public static function isEmpty(mixed $value) : bool {
        if( is_null($value) ) {
            return true;
        }
        if( is_string($value) ) {
            return $value === '';
        }
        if( is_array($value) ) {
            return ArrayUtil::isEmpty($value);
        }
        if( is_object($value) ) {
            return count((array)$value) === 0;
        }
        return false;
    }
}