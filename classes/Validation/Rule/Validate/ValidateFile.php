<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

use AIJOH\Http\UploadFile;

class ValidateFile extends ValidateBase {
    
    public function __construct( ) {
        parent::__construct(false);
    }
    
    
    public function getErrorMessage() : string {
        return ':titleはファイルをアップロードしてください。';
    }
    
    /**
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( $value instanceof UploadFile ) {
            return $value->exists();
        }
        return false;
    }
}