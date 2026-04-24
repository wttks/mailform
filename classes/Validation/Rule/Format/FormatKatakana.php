<?php

namespace AIJOH\Validation\Rule\Format;

use AIJOH\Util\StrUtil;

class FormatKatakana extends FormatBase {
    
    public function __construct() {
    
    }
    
    /**
     * データのフォーマットを行う。
     * @param mixed $value
     * @param array|null $args
     * @return mixed
     */
    public function format( mixed $value, ?array $args = [] ): mixed {
        if( is_string($value) || is_array($value)){
            return StrUtil::toKatakana($value);
        }
        return $value;
    }
}