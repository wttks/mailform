<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

class ValidateAlphaNumeric extends ValidateBase{
    
    
    public function getErrorMessage() : string {
        return ":titleは半角英数字で入力してください。";
    }
    
    /**
     * 値が半角英数字かどうかをチェックする。
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( ! is_string($value) ) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }
}