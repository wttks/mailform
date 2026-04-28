<?php

namespace AIJOH\Validation\Formatter;

use AIJOH\Validation\Parser\RuleConfig;
use AIJOH\Validation\Parser\RuleOne;

class FormatOne extends RuleOne {
    
    public function __construct( string $name, array|string $values ) {
        parent::__construct($name, $values);
    }
    
    /**
     * @param object|null $instance
     * @param array|null $args
     * @param string|null $key
     * @return FormatRuleConfig
     * @throws \AIJOH\Validation\Exception\ValidationRuleException
     */
    protected function createRuleConfig( ?object $instance, ?array $args = null, ?string $key = null ) : FormatRuleConfig {
        return new FormatRuleConfig($instance, $args, $key);
    }
    
    
    /**
     * パラメータのフォーマットを行う。
     * @param mixed $value
     * @return mixed
     * @throws \AIJOH\Validation\Exception\ValidationRuleException
     */
    public function format( mixed $value ) : mixed {
        foreach( $this->getRules() as  $rule ){
            $value = $rule->format($value);
        }
        return $value;
    }
}