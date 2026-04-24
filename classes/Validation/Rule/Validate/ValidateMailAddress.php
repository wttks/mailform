<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Output\Mailer\MailAddressParser;
use AIJOH\Output\Mailer\SendMailException;
use AIJOH\Validation\Validator\Validator;

class ValidateMailAddress extends ValidateBase {
    
    public function __construct() {
        parent::__construct(false);
    }
    
    public function getErrorMessage() : string {
        return ":titleはメールアドレスの一覧を入力してください。";
    }
    
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        try{
            $list = MailAddressParser::parse($value);
            
            return true;
        }catch(SendMailException $se){
            return false;
        }
    }
}