<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\ArrayUtil;
use AIJOH\Util\FileUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;

class ValidateMime extends ValidateBase {
    
    
    public function __construct( bool $isRequiredCheck = false ) {
        parent::__construct($isRequiredCheck);
    }
    
    
    public function getErrorMessage() : string {
        return ':titleは:typesのファイルを指定してください。';
    }

    #[\Override] public function formatMessageArgs( ?array $args ) : array {
        $flat = ArrayUtil::flatten($args);
        return ['types' => implode(',', $flat)];
    }
    
    /**
     * データが指定したMIMEタイプかチェックを行う。
     * 例：image/png, image/jpeg
     * * はワイルドカードとして使用できる。
     *
     * finfo MIME と magic bytes の 2 段検証を行う:
     * - finfo MIME が許可リストにマッチしない → false
     * - 許可 MIME がワイルドカードを含まない確定 MIME なら、その magic bytes も確認
     *   ( polyglot / 拡張偽装に対する追加防衛 )
     *
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException チェックするMIMEタイプが指定されていない場合に発生する。
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {

        if( ! $value instanceof UploadFile ){
            return false;
        }
        $acceptMimeList = ArrayUtil::flatten($args);
        if( empty($acceptMimeList) ){
            throw new ValidationRuleException('アップロードを許可するMIMEタイプを指定してください。');
        }

        $mimeType = $value->getMimeType();
        $matchedMime = null;
        foreach($acceptMimeList as $acceptMime){
            if( fnmatch($acceptMime, $mimeType) ){
                $matchedMime = (string) $acceptMime;
                break;
            }
        }
        if ( $matchedMime === null ) {
            return false;
        }

        // 確定 MIME ( ワイルドカードなし ) の場合のみ magic bytes も検証
        // ワイルドカード ( image/* など ) は対象 MIME が複数なので、検出された
        // 実 MIME ( $mimeType ) で magic bytes をチェックする
        $mimeForSignature = str_contains($matchedMime, '*') ? $mimeType : $matchedMime;
        $tmpPath = $value->getTmpName();
        if ( $tmpPath === '' || ! is_file($tmpPath) ) {
            // 確認画面復元等で tmp ファイルが既に無いケースは finfo MIME だけで判断
            return true;
        }
        return FileUtil::matchesMagicBytes($tmpPath, $mimeForSignature);
    }
}