<?php

namespace AIJOH\Util;

class DataSize {
    
    
    /**
     * @var array|string[] 単位の一覧
     */
    private static array $unitList = [ 'K', 'M', 'G', 'T', 'P', 'E' ];
    
    /**
     * MB GB　などの単位をバイトに変換する。
     * @param string $size
     * @return int
     * @throws \InvalidArgumentException
     */
    public static function toByte( string $size ) : int {
        $size = strtoupper($size);
        if ( StrUtil::endsWith($size, 'B') ) {
            $size = substr($size, 0, -1);
        }
        $unit = substr($size, -1);
        $value = substr($size, 0, -1);
        
        $idx = array_search($unit, self::$unitList);
        if ( $idx === false ) {
            if ( ! is_numeric($size) ) {
                throw new \InvalidArgumentException("サイズの指定が不正です。");
            }
            return (float)$size;
        }
        return round((float)$value * pow(1000, $idx + 1));
    }
}