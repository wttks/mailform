<?php

namespace AIJOH\Sender;

class SendException extends \Exception{
    
    private string $className;
    
    public function __construct($className,$message, $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->className = $className;
    }
    
}