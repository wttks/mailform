<?php

namespace AIJOH\Validation;

use AIJOH\Results\FormData;
use AIJOH\Validation\Compose\ComposeFactory;
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
     * 設定情報（compose を読み取るため保持）
     * @var array
     */
    private array $config;

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
        $this->config    = $config;
        $this->formatter = new Formatter($config);
        $this->validator = new Validator($config);
    }


    /**
     * 複合フィールド (compose) を適用する。
     * 各フィールド設定に 'compose' があれば、元フィールドの値を結合して
     * 結合後フィールドの値として data に追加する。
     * 元フィールドのいずれかが空の場合は結合をスキップする
     * （元フィールドの required バリデーションでエラーになるため）。
     *
     * @param array $data
     * @return array
     */
    private function applyCompose( array $data ) : array {
        foreach ( $this->config as $key => $setting ) {
            if ( ! isset($setting['compose']) || ! is_array($setting['compose']) ) continue;
            $composer = ComposeFactory::create($setting['compose']);
            $value = $composer->apply($data);
            if ( $value !== null ) {
                $data[ $key ] = $value;
            }
        }
        return $data;
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
        $data = $this->applyCompose($data);
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