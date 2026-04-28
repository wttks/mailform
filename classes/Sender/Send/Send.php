<?php

namespace AIJOH\Sender\Send;

use AIJOH\Results\Formatter\FormatBase;

interface Send {
    
    /**
     * 設定ファイルを設定する。
     * @param array $config
     * @return void
     */
    public function setConfig( array $config ) : void;
    
    /**
     * フォーマットしたデータを設定する。
     * @param FormatBase $format
     * @return void
     */
    public function setFormat(FormatBase $format) : void;
    
    
    /**
     * データの送信を行う。
     * @return bool
     */
    public function send() : bool;
    
}