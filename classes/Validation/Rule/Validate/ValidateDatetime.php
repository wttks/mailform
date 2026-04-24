<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

class ValidateDatetime extends ValidateBase {
    
    public function __construct() {
        parent::__construct(false);
    }
    
    public function getErrorMessage() : string {
        return ':titleは日時の形式で入力してください。';
    }
    
    /**
     * 日付のチェックを行う。
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( is_string($value) ) {
            return strtotime($value) !== false;
        }
        if ( $value instanceof \DateTime || $value instanceof \DateTimeImmutable ) {
            return true;
        }
        return false;
    }
}