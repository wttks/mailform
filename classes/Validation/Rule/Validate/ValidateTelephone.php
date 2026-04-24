<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

class ValidateTelephone extends ValidateBase {
    
    
    public function getErrorMessage() : string {
        return ':titleは電話番号の形式で入力してください。';
    }
    
    /**
     * データのチェックを行う
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( is_array($value) ) {
            foreach ( $value as $item ) {
                if ( ! is_string($item) || ! StrUtil::isTelephone($item) ) {
                    return false;
                }
            }
            return true;
        }
        if ( is_string($value) ) {
            return StrUtil::isTelephone($value);
        }
        return false;
    }
}