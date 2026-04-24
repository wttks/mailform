<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

class ValidateEmail extends ValidateBase {
    
    
    /**
     * デフォルトのエラーメッセージを取得する。
     * @return string
     */
    public function getErrorMessage() : string {
        return ':titleはメールアドレスの形式で入力してください。';
    }
    
    /**
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    public function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if( is_string($value) ){

            return StrUtil::isEmail($value);
        }
        return false;
    }

}