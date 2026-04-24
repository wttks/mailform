<?php

namespace AIJOH\Validation\Rule\Format;

class FormatHourMin extends FormatBase {
    
    /**
     * データのフォーマットを行う。
     * @param mixed $value
     * @param array|null $args
     * @return mixed
     */
    public function format( mixed $value, ?array $args = null ) : mixed {
        if( preg_match('/\A(\d{1,2}):(\d{1,2})\z/u',$value,$matches) ) {
            return sprintf('%02d:%02d',$matches[1],$matches[2]);
        }
        return $value;
    }
    
    
    
}