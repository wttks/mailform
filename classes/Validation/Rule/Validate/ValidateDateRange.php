<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\DateUtil;
use AIJOH\Validation\Validator\Validator;

class ValidateDateRange  extends ValidateBase{
    
    public function getErrorMessage() : string {
        return ':titleは日付の範囲指定形式で入力してください。';
    }
    
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        return $this->checkDateRange($value);
    }
 
    private function checkDateRange(mixed $value) {
        if( is_string($value) ){
            return DateUtil::checkDateRange($value);
        }
        if( is_array($value) ){
            foreach( $value as $v ){
                if( !$this->checkDateRange($v) ){
                    return false;
                }
            }
            return true;
        }
        return false;
    }
    
}