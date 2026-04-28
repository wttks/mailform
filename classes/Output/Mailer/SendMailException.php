<?php

namespace AIJOH\Output\Mailer;

/**
 * メール送信時の例外
 */
class SendMailException extends \Exception{
    public function __construct( string $message = "", int $code = 0, ?\Throwable $previous = null ) {
        parent::__construct($message, $code, $previous);
    }
}