<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\ArrayUtil;
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
        foreach($acceptMimeList as $acceptMime){
            if( fnmatch($acceptMime, $mimeType) ){
                return true;
            }
        }
        return false;
    }
}