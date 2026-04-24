<?php

namespace AIJOH\Output\Mailer\Attachment;

class MailAttachment {
    
    public function __construct( protected string $name ) {
    
    }
    
    public function getName() : string {
        return $this->name;
    }
}