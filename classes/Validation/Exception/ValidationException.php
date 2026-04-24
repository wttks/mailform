<?php

namespace AIJOH\Validation\Exception;

use AIJOH\Validation\Throwable;

class ValidationException extends \Exception {
    
    
    protected array $errors = [];
    
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(  $errors, string $message = "", int $code = 0, ?Throwable $previous = null ) {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }
    
    public function getErrors() : array {
        return $this->errors;
    }

}