<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;

class ValidateDateFormat extends ValidateBase {
    public function __construct() {
        parent::__construct(false);
    }
    
    public function getArgNames() : array {
        return ['format'];
    }

    public function getErrorMessage() : string {
        return ':titleは:formatの形式で入力してください。';
    }
    
    /**
     * 日付のチェックを行う。
     * @param mixed $value
     * @param array|null $args 日付のフォーマット
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException
     */
    public function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( ! is_string($value) ) {
            return false;
        }
        if( count($args) === 0 ){
            throw new ValidationRuleException("日付のフォーマットを指定してください。");
        }
        
        foreach ( $args as $format ) {
            if ( \DateTime::createFromFormat($format, $value) !== false ) {
                // PHP 8.2以降、エラーなしの場合 getLastErrors() は false を返す
                $lastErrors = \DateTime::getLastErrors();
                if ( $lastErrors === false || ($lastErrors['warning_count'] === 0 && $lastErrors['error_count'] === 0) ) {
                    return true;
                }
            }
        }
        return false;
    }
}