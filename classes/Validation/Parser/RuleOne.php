<?php

namespace AIJOH\Validation\Parser;

use AIJOH\Validation\Exception\ValidationRuleException;

/**
 * 1つの項目に対するルールを管理するクラス
 */
abstract class RuleOne {
    
    use RuleValueParser;
    
    /**
     * @var RuleConfig[]
     */
    private array $rules = [];
    
    /**
     * コンストラクタ
     * @param string $name キーとなる名前
     * @param array|string $values 設定値
     * @throws ValidationRuleException
     */
    public function __construct( private readonly string $name, array|string $values ) {
        $this->rules = $this->parseValue($values);
    }
    
    /**
     * ルールが存在するかどうかを判別す津。
     * @return bool ルールが存在する場合はtrue、存在しない場合はfalse
     */
    public function exists() : bool {
        return count($this->rules) > 0;
    }
    
    
    /**
     * パラメータを適用するキーの名前
     * @return string
     */
    public function getName() : string {
        return $this->name;
    }
    
    
    /**
     * ルールの中に指定したキーが存在するかチェックを行う。
     * @param string $key キー
     * @return bool
     */
    public function hasRule( string $key ) : bool {
        return array_key_exists($key, $this->rules);
    }
    
    /**
     * キーに対応するRuleConfigクラスを取得する。
     * @param string $key キー
     * @return ?RuleConfig 対応するルール
     */
    public function getRule( string $key ) : ?RuleConfig {
        return $this->rules[ $key ] ?? null;
    }
    
    /**
     * 設定されている全てのルールを配列形式で返す。
     * @return array|RuleConfig[]
     */
    public function getRules() : array {
        return $this->rules;
    }
    
    
    /**
     * キーで指定したルールのうち、最初に定義したルールのデータを返す。
     * @param array $keys
     * @return RuleConfig|null
     */
    public function findFirstRule(array $keys) : ?RuleConfig {
        foreach($this->rules as $ruleKey => $rule) {
            if(in_array($ruleKey, $keys)) {
                return $rule;
            }
        }
        return null;
    }
    
    
    protected abstract function createRuleConfig( ?object $instance, ?array $args = null,?string $key = null ) : RuleConfig;
    
}