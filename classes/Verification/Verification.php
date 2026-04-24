<?php

namespace AIJOH\Verification;


use AIJOH\Verification\Verify\VerifyBase;

/**
 * フォームの入力者が正しいかチェックを行うためのクラス
 */
class Verification {
    
    /**
     * 検証リスト
     * @var array
     */
    private array $verifyList = [];
    
    
    /**
     * コンストラクタ
     * @param $config
     */
    public function __construct( $config ) {
        $this->init($config);
    }
    
    /**
     * 初期化を行う
     * @param $config
     * @return void
     */
    private function init( $config ) : void {
        foreach ( $config as $key => $value ) {
            if ( is_int($key) ) {
                $name = $value;
                $param = [];
            } else {
                $name = $key;
                $param = $value;
            }
            $this->verifyList[] = $this->loadVerify($name, $param);
        }
    }
    
    /**
     * @param string $name
     * @param array $config
     * @return object|VerifyBase
     */
    private function loadVerify( string $name, array $config = [] ) : object {
        $className = $this->getVerifyClass($name);
        return new $className($config);
    }
    
    
    private function getVerifyClass( string $name ) : string {
        $name = ucfirst($name);
        return "AIJOH\Verification\Verify\\Verify{$name}";
    }
    
    
    public function getHeaderTag() : string {
        $html = [];
        foreach ( $this->verifyList as $verify ) {
            $html[] = $verify->header();
        }
        return implode(PHP_EOL, $html);
    }
    
    
    /**
     * Form内に設定するタグを取得する。
     * @return string
     */
    public function getFormTag() : string {
        $html = [];
        foreach ( $this->verifyList as $verify ) {
            $html[] = $verify->form();
        }
        return implode(PHP_EOL, $html);
    }
    
    
    public function getFooterTag() : string {
        $html = [];
        foreach ( $this->verifyList as $verify ) {
            $html[] = $verify->footer();
        }
        return implode(PHP_EOL, $html);
    }
    
    
    /**
     * 入力値のチェックを行う
     * @return string
     */
    public function verify() : true|string {
        if( empty($this->verifyList)){
            return true;
        }
        
        foreach ( $this->verifyList as $verify ) {
            if ( ! $verify->verify() ) {
                return $verify->getErrorMessage();
            }
        }
        return true;
    }
    
    
}