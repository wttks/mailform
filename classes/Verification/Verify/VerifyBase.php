<?php

namespace AIJOH\Verification\Verify;

abstract class VerifyBase {
    
    /**
     * ヘッダー部分のHTMLタグを返す。
     * @return string
     */
    public function header() : string {
        return "";
    }
    
    /**
     * エラーメッセージを取得する。
     * @return string
     */
    public abstract function getErrorMessage() : string;
    
    /**
     * フォーム部分のHTMLタグを返す。
     * @return string
     */
    public function form() : string {
        return "";
    }
    
    /**
     * フッター部分のHTMLタグを返す。
     * @return string
     */
    public function footer() : string {
        return "";
    }
    
    
    /**
     * 入力の検証を行う
     * @return bool
     */
    public abstract function verify() : bool;
    
    
}