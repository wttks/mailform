<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\ArrayUtil;
use AIJOH\Util\ObjectUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;

class ValidateRequiredIf extends ValidateBase {
    use Required;
    
    public function __construct() {
        parent::__construct(true);
    }
    
    
    public function getArgNames() : array {
        return ['field', 'value'];
    }

    public function getErrorMessage() : string {
        return ':fieldを設定している場合:titleは必須です。';
    }
    
    /**
     * データのチェックを行う
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException
     */
    protected function check( $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        $otherKey = $args[0] ?? '';
        $val = $args[1] ?? '';
        if ( $otherKey === "" ) {
            throw new ValidationRuleException("required_if の引数には他の項目名を指定してください。");
        }
        
        $ans =  ArrayUtil::get($data, $otherKey, null);
        if ( $this->isRequiredCheck($ans,$val) ){
            return $this->isRequired($value);
        }
        return true;
    }
    
    
    private function isRequiredCheck($otherValue,$val) : bool {
        if( is_null($otherValue) ){
            return false;
        }
        
        if( is_bool($otherValue) ){
            return $otherValue === filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }
        return $otherValue === $val;
    }
    
}