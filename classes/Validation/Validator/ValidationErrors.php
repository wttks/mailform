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
     * 現在のエラー一覧を返す。
     * @return array<string,string>
     */
    public function toArray() : array {
        return $this->errors;
    }


    /**
     * エラー一覧をまるごと置き換える（hook 経由での加工結果反映用）。
     * formatMessage は走らない（呼び出し側で整形済み前提）。
     * @param array<string,string> $errors
     */
    public function replace( array $errors ) : void {
        $this->errors = $errors;
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