<?php

namespace AIJOH\Validation;


use AIJOH\Validation\Exception\ValidationRuleException;

/**
 * バリデーションやフォーマットを実行するた
 */
trait Executor {
    /**
     * 初期化
     * @param string|object $key インスタンスの元となる値
     * @return object インスタンス
     * @throws ValidationRuleException 設定の例外
     */
    public function parseInstance( string|object $key ) : object {
        $instance = $key;
        if ( is_string($key) ) {
            $className = $this->getClassName($key);
            if ( $className === false ) {
                throw new ValidationRuleException($key . 'に対応するクラスが存在しません。');
            }
            $instance = new $className();
        }
        
        if ( ! $this->isInstance($instance) ) {
            throw new ValidationRuleException(get_class($instance) . 'は正しいインスタンスではありません。');
        }
        return $instance;
    }
    
    
    /**
     * 文字列に対応するクラスが存在するかチェックを行う。
     * @param string $key キー
     * @return string|false 対応するクラスが存在する場合はそのクラス名、存在しない場合はfalse
     */
    private function getClassName( string $key ) : string|false {
        if ( class_exists($key) ) {
            return $key;
        }
        return $this->parseClassName($key);
    }
    
    /**
     * 文字列に対応するクラス名を返す。
     * @param string $key 元となるキー値
     * @return string|false 対応するクラス名が存在する場合はそのクラス名、存在しない場合はfalse
     */
    protected abstract function parseClassName( string $key ) : string|false;
    
    /**
     * インスタンスが正しいかどうかを判別する。
     * @param object $instance
     * @return bool
     */
    protected abstract function isInstance( object $instance ) : bool;
    
    
    
}