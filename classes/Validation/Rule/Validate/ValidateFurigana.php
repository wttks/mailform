<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

class ValidateFurigana extends ValidateBase {
    
    public function getArgNames() : array {
        return ['type'];
    }

    public function getErrorMessage() : string {
        return ":titleは:typeで入力してください。";
    }
    
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        $type = $args[0] ?? 'katakana';
        switch ( $type ) {
            case 'ひらがな':
            case 'hiragana':
                $type = 'hiragana';
                break;
            default:
                $type = 'katakana';
                break;
        }

        if ( is_array($value) ) {
            foreach ( $value as $item ) {
                if ( ! is_string($item) || ! $this->checkString($item, $type) ) {
                    return false;
                }
            }
            return true;
        }

        if ( ! is_string($value) ) {
            return false;
        }
        return $this->checkString($value, $type);
    }

    private function checkString( string $value, string $type ) : bool {
        return StrUtil::isFurigana($value, $type);
    }
}