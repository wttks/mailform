<?php

namespace AIJOH\Validation\Rule\Format;

use AIJOH\Util\StrUtil;

class FormatSort extends FormatBase {
    
    public function __construct() {
    
    }
    
    /**
     * データのフォーマットを行う。
     * @param mixed $value
     * @param array|null $args
     * @return mixed
     */
    public function format( mixed $value, ?array $args = [] ): mixed {
        if( is_array($value) ){
            $flag = $args[0] ?? SORT_REGULAR;
            sort($value,$flag);
            return $value;
        }
        return $value;
    }
}