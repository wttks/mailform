<?php

namespace AIJOH\Validation\Rule\Format;

class FormatDate extends FormatBase {
    
    /**
     * データのフォーマットを行う。
     * @param mixed $value
     * @param array|null $args
     * @return mixed
     */
    public function format( mixed $value, ?array $args = null ) : mixed {
        if( is_string($value) ) {
            return str_replace('/', '-', $value);
        }
        return $value;
    }
    
    
    
}