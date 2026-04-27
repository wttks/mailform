<?php

namespace AIJOH\Validation\Validator;

use AIJOH\Lang\Translator;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Parser\RuleConfig;
use AIJOH\Validation\Rule\Validate\ValidateBase;

class ValidationRuleConfig extends RuleConfig {
    
    private string $message = "";
    
    
    public function getInstance() : ValidateBase {
        $instance = parent::getInstance();
        if ( $instance instanceof ValidateBase ) {
            return $instance;
        }
        throw new ValidationRuleException('バリデーションクラスが正しくありません。');
    }
    
    /**
     * バリデーション用のクラスであるかチェックを行う。
     * @param object|null $instance
     * @return bool
     */
    protected function isInstance( ?object $instance ) : bool {
        if ( $instance === null ) {
            return false;
        }
        return $instance instanceof ValidateBase;
    }
    
    
    protected function getInstanceNameSpace() : string {
        return 'AIJOH\\Validation\\Rule\\Validate\\';
    }
    
    protected function getClassNamePrefix() : string {
        return 'Validate';
    }
    
    /**
     * エラーメッセージを設定する。
     * @param string $message
     * @return void
     */
    public function setErrorMessage( string $message ) : void {
        $this->message = $message;
    }
    
    /**
     * エラーメッセージを設定する。
     * @return string
     * @throws ValidationRuleException
     */
    private function getDefaultErrorMessage() : string {
        if ( $this->message !== "" ) {
            return $this->message;
        }
        return Translator::translate($this->getInstance()->getErrorMessage());
    }
    
    /**
     * エラーメッセージを返す。
     * 引数は内部で保持している getArgs() を使用する。
     * @return string
     * @throws ValidationRuleException
     */
    public function getErrorMessage() : string {
        $msg = $this->getDefaultErrorMessage();
        $namedArgs = $this->getInstance()->formatMessageArgs($this->getArgs());
        $replaceKeys = array_map(fn( $key ) => ':' . $key, array_keys($namedArgs));
        return str_replace($replaceKeys, array_values($namedArgs), $msg);
    }


    /**
     * データのチェックを行う
     * @param mixed $value
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return array [bool, string] バリデーションに成功したら true 失敗したらfalseとエラーメッセージを返す。
     * @throws ValidationRuleException
     */
    public function validate( mixed $value, string $name = "", array $data = [], ?Validator $validator = null ) : array {
        $instance = $this->getInstance();
        if ( $instance->validate($value, $this->getArgs(), $name, $data, $validator) ) {
            return [ true, "" ];
        } else {
            return [ false, $this->getErrorMessage() ];
        }
    }
    
}