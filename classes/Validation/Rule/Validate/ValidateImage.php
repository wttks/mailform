<?php

namespace AIJOH\Validation\Rule\Validate;



use AIJOH\Http\UploadFile;
use AIJOH\Util\StrUtil;
use AIJOH\Validation\Validator\Validator;

/**
 * 画像ファイルかどうかをチェックするバリデーションクラス
 */
class ValidateImage extends ValidateBase {
    
    public function getErrorMessage() : string {
        return ":titleは画像ファイルを指定してください。";
    }
    
    /**
     * データが画像ファイルかどうかをチェックする。
     *
     * 空値・未アップロードの UploadFile は ValidateBase::validate() 側で
     * 吸収されるため、ここに来るのは「実体のある値」のみ。UploadFile 以外の
     * 値（文字列など）は不正としてエラーにする。
     *
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if( ! $value instanceof UploadFile ){
            return false;
        }
        $mimeType = $value->getMimeType();
        return StrUtil::startsWith($mimeType, "image/");
    }
}

