<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

class ValidateNullable extends ValidateBase {
    public function __construct( ) {
        parent::__construct(true);
    }
    
    
    /**
     * デフォルトのエラーメッセージを取得する。
     * @return string
     */
    public function getErrorMessage() : string {
        return 'このメッセージは表示されません。';
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
        return true;
    }

}