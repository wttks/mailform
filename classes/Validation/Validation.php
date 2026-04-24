<?php

namespace AIJOH\Validation;

use AIJOH\Results\FormData;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Formatter\Formatter;
use AIJOH\Validation\Validator\Validator;
use AIJOH\Validation\Exception\ValidationRuleException;

class Validation {
    
    /**
     * データをフォーマットするクラス
     * @var Formatter
     */
    private Formatter $formatter;
    
    /**
     * データをバリデーションするクラス
     * @var Validator
     */
    private Validator $validator;
    
    
    /**
     * @var string
     */
    private ?string $formDataClass = null;
    
    /**
     * コンストラクタ
     * フォーマットとバリデーションの設定情報を元にインスタンスを生成する。
     *
     * name => [
     *     'format' => [
     *      ]
     * ]
     *
     *
     * @param array $config 設定情報
     * @throws ValidationRuleException バリデーションの設定が不正な場合の例外
     */
    public function __construct( array $config ) {
        $this->formatter = new Formatter($config);
        $this->validator = new Validator($config);
    }
    
    
    public function setFormDataClass( string $formDataClass ) : void {
        $this->formDataClass = $formDataClass;
    }
    
    
    /**
     * フォームデータのインスタンスを生成する。
     * @return FormData
     * @throws ValidationRuleException
     */
    protected function createFormData() : FormData {
        if ( $this->formDataClass === null || $this->formDataClass === "" ) {
            $instance = new FormData();
        } else {
            $instance = new $this->formDataClass();
        }
        if ( ! ( $instance instanceof FormData ) ) {
            throw new ValidationRuleException("formDataClass は FormData またはその子クラスを指定してください。");
        }
        
        $instance->setTitleManager($this->validator->getTitleManager());
        
        return $instance;
    }
    
    
    public function beforeFormat( array $data ) : array {
        return $data;
    }
    
    
    public final function format( array $data ) : array {
        return $this->formatter->format($data);
    }
    
    
    public function beforeValidate( array $data ) : array {
        return $data;
    }
    
    /**
     * データのバリデーションを行う。
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function dataValidate( array $data ) : array {
        return $this->validator->validate($data);
    }
    
    
    /**
     * バリデーションした後のデータを配列で返す。
     * @param array $data
     * @return array
     * @throws ValidationException バリデーション例外
     */
    public function validated( array $data ) : array {
        $data = $this->beforeFormat($data);
        $data = $this->format($data);
        $data = $this->beforeValidate($data);
        
        return $this->dataValidate($data);
    }
    
    
    /**
     * データのバリデーションを行う。
     * @param array $data 入力データ
     * @return FormData バリデーション後のデータ
     * @throws ValidationException バリデーション例外
     * @throws ValidationRuleException バリデーションの設定が不正な場合の例外
     */
    public function validateFormData( array $data ) : FormData {
        $data = $this->validated($data);
        $formData = $this->createFormData();
        $formData->setData($data);
        return $formData;
    }
    
}