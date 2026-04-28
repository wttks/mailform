<?php

namespace AIJOH\Validation\Parser;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Exception\ValidationRuleException;

/**
 * バリデーション、フォーマットのルールを管理するクラス
 */
abstract class RuleConfig {
    
    private object $instance;
    
    private ?array $args;
    
    private string $key;
    
    /**
     * コンストラクタ。インスタンスかキー名のいずれかを指定する。
     *
     * - インスタンス指定: new RuleConfig($instance, $args, $key)
     * - キー名指定:       new RuleConfig(null, $args, $key)
     *
     * @param object|null $instance ルールクラスのインスタンス（指定する場合は isInstance() を満たすこと）
     * @param array|null $args ルールに渡す引数
     * @param string|null $key ルール名（インスタンス未指定時はクラスを生成するキーとして使用）
     * @throws ValidationRuleException インスタンスもキー名も指定されていない場合
     */
    public function __construct( ?object $instance = null, ?array $args = null, ?string $key = null ) {
        if ( $instance !== null ) {
            $this->initByInstance($instance, $args, $key);
        } elseif ( $key !== null && strlen($key) > 0 ) {
            $this->initByKey($key, $args);
        } else {
            throw new ValidationRuleException('クラスかキー名を指定してください。');
        }
    }
    
    /**
     * 生成済みのインスタンスを元に初期化を行う。
     * @param object $instance
     * @param array|null $args
     * @param string|null $key
     * @return void
     * @throws ValidationRuleException
     */
    protected function initByInstance( object $instance, ?array $args = null, ?string $key = "" ) : void {
        if ( ! $this->isInstance($instance) ) {
            throw new ValidationRuleException(get_class($instance) . 'は正しいインスタンスではありません。');
        }
        $this->instance = $instance;
        $this->args = $args;
        $this->key = $key ?? $this->createKey($instance);
    }
    
    /**
     * キー名を元に初期化を行う。
     * @param string $key
     * @param array|null $args
     * @return void
     * @throws ValidationRuleException キー名を元にルールのインスタンスが生成できなかった場合
     */
    protected function initByKey(string $key,?array $args = null) : void {
        $this->instance = $this->buildInstance($key);
        if(! $this->isInstance($this->instance) ){
            throw new ValidationRuleException($key . 'は正しいインスタンスではありません。');
        }
        
        $this->args = $args;
        $this->key = $key;
    }
    
    /**
     * インスタンスが正しいインスタンスかチェックを行う。
     * @param object|null $instance
     * @return bool
     */
    protected abstract function isInstance( ?object $instance ) : bool;
    
    
    /**
     * 文字列を元にパスを追加したクラス名を返す。
     * @param string $key 元となるキー値
     * @return object
     * @throws ValidationRuleException クラス名の解析に失敗した場合
     */
    protected function buildInstance( string $key ) : object {
        $baseName = $this->getClassNamePrefix() . ucfirst(StrUtil::toCamelCase($key));
        $className = $this->getInstanceNameSpace() . $baseName;
        if( class_exists($className) ){
            return new $className();
        }
        if( class_exists($baseName) ){
            return new $baseName();
        }
        throw new ValidationRuleException($key . 'が見つかりません。');
    }
    
    /**
     * インスタンスの名前空間を取得する。
     * @return string
     */
    protected abstract function getInstanceNameSpace() : string;
    
    /**
     * クラス名のプレフィックスを取得する。
     * @return string
     */
    protected abstract function getClassNamePrefix() : string;
    
    /**
     * 実行インスタンスを取得する。
     * @return object
     */
    public function getInstance() : object {
        return $this->instance;
    }
    
    /**
     * 実行インスタンスの引数を取得する。
     * @return array
     */
    public function getArgs() : array {
        return $this->args ?? [];
    }
    
    /**
     * パラメータ名を取得する。
     * @return string
     */
    public function getKey() : string {
        return $this->key ?? $this->createKey($this->instance);
    }
    
    /**
     * キーを生成する。
     * @return string
     */
    protected function createKey(object $instance) : string {
        $replaceName = $this->getInstanceNameSpace() . $this->getClassNamePrefix();
        $name = str_replace($replaceName, '', get_class($instance));
        return StrUtil::toSnakeCase($name);
    }
    
}