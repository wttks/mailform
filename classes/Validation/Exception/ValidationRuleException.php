<?php

namespace AIJOH\Validation\Exception;

use AIJOH\Validation\Throwable;

/**
 * バリデーションやフォーマットを実行する際のルールの例外
 */
class ValidationRuleException extends \Exception{
    public function __construct( string $message = "", int $code = 0, ?Throwable $previous = null ) {
        parent::__construct($message, $code, $previous);
    }
}