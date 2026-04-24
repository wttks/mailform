<?php

namespace AIJOH\Validation\Formatter;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Parser\RuleConfig;
use AIJOH\Validation\Rule\Format\FormatBase;

class FormatRuleConfig extends RuleConfig {
    
    private static string $formatBasePath = 'AIJOH\\Validation\\Rule\\Format\\';
    
    private static string $formatClassNamePrefix = 'Format';
    
    
    /**
     * Format用のクラスであるかチェックを行う。
     * @param object $instance
     * @return bool
     */
    protected function isInstance( ?object $instance ) : bool {
        if ( $instance === null ) {
            return false;
        }
        return $instance instanceof FormatBase;
    }
    
    
    /**
     * インスタンス名からキー名を取得する。
     * @param object $instance
     * @return string
     */
    protected function createKey( object $instance ) : string {
        $name = get_class($instance);
        if ( StrUtil::startsWith($name, self::$formatBasePath . self::$formatClassNamePrefix) ) {
            $name = str_replace(self::$formatBasePath . self::$formatClassNamePrefix, '', $name);
            return StrUtil::toSnakeCase($name);
        }
        return $name;
    }
    
    /**
     * データのフォーマットを実施
     * @param mixed $value フォーマット前のデータ
     * @return mixed フォーマット後のデータ
     * @throws ValidationRuleException
     */
    public function format( mixed $value ) : mixed {
        $instance = $this->getInstance();
        if( $instance instanceof FormatBase ) {
            return $instance->format($value, $this->getArgs());
        }
        throw new ValidationRuleException('フォーマットクラスが正しくありません。');
    }
    
    protected function getInstanceNameSpace() : string {
        return 'AIJOH\\Validation\\Rule\\Format\\';
    }
    
    protected function getClassNamePrefix() : string {
        return 'Format';
    }

}