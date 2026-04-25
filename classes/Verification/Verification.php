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
     * 初期化を行う。
     *
     * 設定形式:
     *   ['csrfToken' => true]                 // 設定なしで有効化
     *   ['honeypot'  => ['name' => 'website']] // 設定あり
     *   ['csrfToken' => false]                // 明示的に無効化
     *   ['csrfToken']                         // ← 旧形式（数値キー）も互換維持
     *
     * @param array $config
     */
    private function init( array $config ) : void {
        foreach ( $config as $key => $value ) {
            if ( is_int($key) ) {
                // 旧形式: ['csrfToken'] のような文字列リスト → 互換維持
                $name = (string) $value;
                $param = [];
            } else {
                // 新形式: 'name' => true / false / array
                if ( $value === false ) {
                    continue;  // false は明示無効化
                }
                $name = (string) $key;
                $param = is_array($value) ? $value : [];
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