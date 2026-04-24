<?php

namespace AIJOH\Output\Mailer\Attachment;

/**
 * 文字で入力した内容を元に添付ファイルを生成するクラス
 */
class StringAttachment extends MailAttachment {
    
    public function __construct( protected string $content,string $name) {
        parent::__construct($name);
    }
    
    public function getContent() : string {
        return $this->content;
    }
}