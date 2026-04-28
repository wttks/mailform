<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

class ValidateRequired extends ValidateBase {
    use Required;
    
    public function __construct() {
        parent::__construct(true);
    }
    
    /**
     * デフォルトのエラーメッセージを取得する。
     * @return string
     */
    public function getErrorMessage() : string {
        return ':titleは必須項目です。';
    }
    
    /**
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        return $this->isRequired($value);
    }
}