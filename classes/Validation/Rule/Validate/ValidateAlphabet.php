<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

class ValidateAlphabet extends ValidateBase {
    
    public function getErrorMessage() : string {
        return ":titleは半角英字で入力してください。";
    }
    
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( ! is_string($value) ) {
            return false;
        }
        return preg_match('/\A[a-zA-Z]+\z/', $value) === 1;
    }
}