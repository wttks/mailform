<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

class ValidateInJapanese extends ValidateBase {
    
    public function getErrorMessage() : string {
        return ":titleは日本語を含めて入力してください。";
    }
    
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( ! is_string($value) ) {
            return false;
        }
        return StrUtil::inJapanese($value);
    }
}