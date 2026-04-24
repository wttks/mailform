<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

class ValidateString extends ValidateBase {
    
    public function getErrorMessage() : string {
        return ":titleは文字列を指定してください。";
    }
    
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        return is_string($value);
    }
}