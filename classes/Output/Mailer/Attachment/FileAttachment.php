<?php

namespace AIJOH\Output\Mailer\Attachment;

use AIJOH\Output\Mailer\Attachment\MailAttachment;

/**
 * ファイルを添付するクラス
 */
class FileAttachment extends MailAttachment {
    
    /**
     * @param string $path
     * @param string $name
     */
    public function __construct( protected string $path, string $name = "" ) {
        if ( $name === "" ) {
            $name = basename($path);
        }
        parent::__construct($name);
    }
    
    public function getPath() : string {
        return $this->path;
    }
    
    
    
}