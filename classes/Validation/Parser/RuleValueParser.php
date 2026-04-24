<?php

namespace AIJOH\Validation\Parser;

use AIJOH\Util\ObjectUtil;
use AIJOH\Util\StrUtil;
use AIJOH\Validation\Exception\ValidationRuleException;

trait RuleValueParser {
    
    
    /**
     * 下記の様なテキストをクラスと引数に分割する。
     * [ 'required|between:1,20' , 'japanese', new Hoge()]
     * 1. 配列の場合は最初の値を取り出す。
     * 2. | で分割する。
     * 3. |で分割した文字列をそれぞれ: で分割して、:の前をクラス名、:の後を引数として取り出す。:がない場合はクラス名のみとする。
     * 4. 引数の中に,があれば,で分割する。
     *
     * @param array|string|object $config
     * @return array RuleConfigの配列
     * @throws ValidationRuleException
     */
    protected function parseValue( array|string|object $config ) : array {
        if ( is_string($config) || is_object($config) ) {
            $config = [ $config ];
        }

        $results = [];
        foreach ( $config as $value ) {
            if( $value === null || ObjectUtil::isEmpty($value) ){
                continue;
            }
            
            foreach($this->parseValues($value) as $val) {
                $key = $val->getKey();
                $results[$key] = $val;
            }
        }
        return $results;
    }
    
    /**
     * パラメータをパースする。
     * @param string|object $value
     * @return void
     * @throws ValidationRuleException
     */
    protected function parseValues( string|object|array $value ) {
        if ( is_object($value) ) {
            yield $this->createRuleConfig($value);
            return;
        }
        
        if( is_array($value) ){
            $firstKey = array_keys($value)[0];
            if( is_string($firstKey) ){
                yield $this->createRuleConfig(null,$value[$firstKey], $firstKey);
            }
            if( is_int($firstKey) ) {
                $name = array_shift($value);
                yield $this->createRuleConfig(null,$value,$name);
            }
            return;
        }
        
        
        
        $params = preg_split('/\|/', $value);
        foreach ( $params as $param ) {
            $param = trim($param);
            if( $param === null || strlen($param) === 0 ){
                continue;
            }
            
            if ( ! StrUtil::contains($param, ':') ) {
                $key = $param;
                $args = null;
            } else {
                [ $key, $args ] = $this->parseParam($param);
            }
            yield $this->createRuleConfig(null, $args, $key);
        }
        
    }
    
    /**
     * ルールの設定クラスを生成する。
     * @param object|null $instance
     * @param array|null $args
     * @param string|null $key
     * @return RuleConfig ルールの設定クラス
     * @throws ValidationRuleException
     */
    protected abstract function createRuleConfig( ?object $instance, ?array $args = null,?string $key = null ) : RuleConfig;
    
    
    /**
     * パラメータをパースする。
     * 文字列の中の最初の:までがクラス名、:以降が引数となる。
     * 引数は,で区切られる。
     * @param string $param
     * @return array
     */
    protected function parseParam( string $param ) : array {
        $pos = mb_strpos($param, ':');
        if ( $pos === false ) {
            return [ $param, null ];
        }
        $className = mb_substr($param, 0, $pos);
        $args = mb_substr($param, $pos + 1);
        return [ $className, mb_split(',', $args) ];
        
    }

}