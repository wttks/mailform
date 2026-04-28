<?php

namespace AIJOH\Validation\Validator;

use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Parser\TitleManager;

class ValidationErrors {
    private array $errors = [];
    
    public function __construct(protected TitleManager $titleManager) {
    }
    
    public function add( string $key, string $message ) {
        $this->errors[ $key ] = $this->formatMessage($key,$message);
    }
    
    
    protected function formatMessage($key,$message) : string {
        return str_replace( ':title', $this->titleManager->getTitle($key), $message);
    }
    
    
    /**
     * バリデーションエラーが発生した場合に例外を投げる
     * @return void
     * @throws ValidationException
     */
    public function exception() : void {
        if(count($this->errors) > 0 ){
            throw new ValidationException($this->errors,"バリデーションエラーが発生しました。",255);
        }
    }
    

}