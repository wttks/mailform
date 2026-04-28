<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

class ValidatePostalCode extends ValidateBase {

    
    /**
     * デフォルトのエラーメッセージを取得する。
     * @return string
     */
    public function getErrorMessage() : string {
        return ':titleには郵便番号を入力してください';
    }
    
    /**
     * データのチェックを行う
     *
     * @param mixed $value
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    public function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if( is_string($value) ){
            return preg_match('/\A\d{3}-?\d{4}\z/', $value) === 1;
        }
        return false;
    }

}