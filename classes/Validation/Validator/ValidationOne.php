<?php

namespace AIJOH\Validation\Validator;

use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Parser\RuleConfig;
use AIJOH\Validation\Parser\RuleOne;
use AIJOH\Validation\Parser\ValidationBase;
use AIJOH\Validation\Rule\Validate\ValidateBase;

class ValidationOne extends RuleOne {
    
    

    
    /**
     * @return array|ValidationRuleConfig[]
     */
    public function getRules() : array {
        return parent::getRules();
    }
    
    /**
     * createRuleConfig ルールの設定を作成する。
     * @param object|null $instance
     * @param array|null $args
     * @param string|null $key
     * @return ValidationRuleConfig
     * @throws ValidationRuleException
     */
    protected function createRuleConfig( ?object $instance, ?array $args = null, ?string $key = null ) : ValidationRuleConfig {
        return new ValidationRuleConfig($instance, $args, $key);
    }
    
    
    /**
     * setMessage エラーメッセージをまとめて設定する。
     * @param array $messages
     * @return void
     */
    public function setMessage( array $messages ) : void {
        foreach ( $messages as $key => $message ) {
            $instance = $this->getRule($key);
            if ( $instance instanceof ValidationRuleConfig ) {
                $instance->setErrorMessage($message);
            }
        }
    }
    
    
    /**
     * バリデーションを実施する。
     * @param mixed $value
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return array [bool,string|null] バリデーションの結果とエラーメッセージ
     * @throws ValidationRuleException
     */
    public function validate( mixed $value, string $name = "", array $data = [], ?Validator $validator = null ) : array {
        $validators = $this->getRules();
        foreach ( $validators as $key => $validate ) {
            if ( ! $validate instanceof ValidationRuleConfig ) {
                throw new ValidationRuleException('バリデーションクラスが正しくありません。');
            }
            [ $results, $message ] = $validate->validate($value, $name, $data, $validator);
            if ( ! $results ) {
                return [$results,$message];
            }
        }
        return [true,null];
    }
    
    
}