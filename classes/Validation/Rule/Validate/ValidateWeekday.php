<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\DateUtil;
use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

class ValidateWeekday extends ValidateBase {
    
    public function getErrorMessage() : string {
        return ":titleは文字列を指定してください。";
    }
    
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        return $this->checkWeekday($value);
    }
    
    
    private function checkWeekday( mixed $value ) : bool {
        if ( is_string($value) ) {
            return DateUtil::isWeekday($value);
        }
        if ( is_array($value) ) {
            foreach ( $value as $val ) {
                if ( ! DateUtil::isWeekday($val) ) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
}