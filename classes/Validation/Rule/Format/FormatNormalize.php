<?php

namespace AIJOH\Validation\Rule\Format;

use AIJOH\Util\StrUtil;

class FormatNormalize extends FormatBase {
    
        public function __construct() {
        
        }
        
        /**
        * データのフォーマットを行う。
        * @param mixed $value
        * @param array|null $args
        * @return mixed
        */
        public function format( mixed $value, ?array $args = [] ): mixed {
            if( is_array($value) || is_string($value) ) {
                return StrUtil::toNormalize($value);
            }
            return $value;
        }
    
}