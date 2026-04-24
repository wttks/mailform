<?php

namespace AIJOH\Validation\Validator;


use AIJOH\Util\ArrayUtil;
use AIJOH\Validation\Parser\TitleManager;
use AIJOH\Validation\Validator\ValidationOne;
use AIJOH\Validation\Exception\ValidationRuleException;

class Validator {
    
    private TitleManager $titleManager;
    
    
    /**
     * @var array<ValidationOne> バリデーションのインスタンス
     */
    private array $validators = [];
    
    
    private string $validateKey = 'rule';
    
    
    
    /**
     * @param array $config
     * @throws ValidationRuleException バリデーションの設定が不正な場合の例外
     */
    public function __construct( array $config ) {
        $this->init($config);
    }
    
    
    /**
     * データのバリデーションを実施する。
     * @param array $config
     * @return void
     * @throws ValidationRuleException バリデーションの設定が不正な場合の例外
     */
    private function init( array $config ) : void {
        $this->titleManager = new TitleManager();
        foreach ( $config as $key => $param ) {
            $validator = new ValidationOne($key, $param[$this->validateKey] ?? []);
            if ( $validator->exists() ) {
                $this->validators[ $key ] = $validator;
            }
            
            // メッセージを設定する。
            $messages = $param['message'] ?? [];
            $validator->setMessage($messages);
            
            // タイトルを設定する。
            $title = $param['title'] ?? '';
            $output = $param['output'] ?? true;
            if ( is_string($title) && strlen($title) > 0 ) {
                $this->titleManager->set($key, $title,$output);
            }
        }
    }
    
    
    /**
     * 指定したキーに指定したルールが存在しているかチェックを行う。
     * @param string $key
     * @param string $rule
     * @return bool
     */
    public function hasRule( string $key, string $rule ) : bool {
        $validatorOne = $this->getValidatorOne($key);
        if ( $validatorOne === null ) {
            return false;
        }
        return $validatorOne->hasRule($rule);
    }
    
    /**
     * 一つの項目に対してバリデーションを実施する。
     * @param string $key
     * @return \AIJOH\Validation\Validator\ValidationOne|null
     */
    public function getValidatorOne( string $key ) : ?ValidationOne {
        foreach ( $this->validators as $pattern => $validator ) {
            if ( ArrayUtil::matchKey($pattern, $key) ) {
                return $validator;
            }
        }
        return null;
    }
    
    
    /**
     * タイトルとキーを管理するクラスを返す。
     * @return TitleManager タイトルとキーを管理するクラス
     */
    public function getTitleManager() : TitleManager {
        return $this->titleManager;
    }
    
    
    /**
     * データのバリデーションを行う。
     * @param array $data
     * @return array
     * @throws \AIJOH\Validation\Exception\ValidationException バリデーション失敗時の例外
     */
    public function validate( array $data ) : array {
        $results = [];
        $errors = new ValidationErrors($this->titleManager);
        
        
        foreach ( $this->validators as $key => $validator ) {
            $keyValues = ArrayUtil::getKeyValueList($data, $key);
            if( empty($keyValues) ){
                $keyValues = [ $key => null ];
            }
            
            foreach ( $keyValues as $itemKey => $itemValue ) {
                [ $validated, $message ] = $validator->validate($itemValue, $itemKey, $data, $this);
                if ( $validated ) {
                    ArrayUtil::set($results, $itemKey, $itemValue);
                } else {
                    $errors->add($itemKey, $message);
                }
            }
        }
        
        $errors->exception();
        return $results;
    }
}
