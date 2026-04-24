<?php

namespace AIJOH\Validation\Rule\Format;

class FormatBoolean extends FormatBase {

    private static $trueValues = [ true, 1, 'true', '1', 'yes', 'on', 'y', 't' ];
    private static $falseValues = [ false, 0, 'false', '0', 'no', 'off', 'n', 'f' ];

    public function format( mixed $value, ?array $args = null ) : mixed {
        return $this->formatValue($value);
    }


    private function formatValue( mixed $value ) : mixed {
        if ( is_array($value) ) {
            $results = [];
            foreach ( $value as $key => $val ) {
                $results[$key] = $this->formatValue($val);
            }
            return $results;
        }
        if ( in_array($value, self::$trueValues, true) ) {
            return true;
        }
        if ( in_array($value, self::$falseValues, true) ) {
            return false;
        }
        // true/false どちらにもマッチしない場合は元の値をそのまま返す
        return $value;
    }
}
