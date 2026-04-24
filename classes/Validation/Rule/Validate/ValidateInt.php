<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

class ValidateInt extends ValidateBase {
    /**
     * デフォルトのエラーメッセージを取得する。
     * @return string
     */
    public function getErrorMessage() : string {
        return ":titleは整数で入力してください。";
    }
    
    /**
     * データが整数かどうかをチェックする。
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if( is_int($value) ) {
            return true;
        }
        if( is_string($value) ){
            return preg_match('/\A-?[0-9]+\z/', $value);
        }
        return false;
    }
    
}