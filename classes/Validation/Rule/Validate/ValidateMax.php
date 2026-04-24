<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\DataSize;
use AIJOH\Util\DateUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;


class ValidateMax extends ValidateBaseSizeCompare {
    
    
    public function getArgNames() : array {
        return ['max'];
    }

    protected function getErrorMessageNumber() : string {
        return ":titleは:max以下の数値を指定してください。";
    }

    protected function getErrorMessageString() : string {
        return ":titleは:max文字以下で入力してください。";
    }

    protected function getErrorMessageDate() : string {
        return ":titleは:maxより前の日付を指定してください。";
    }

    protected function getErrorMessageArray() : string {
        return ":titleは:max個以下で入力してください。";
    }

    protected function getErrorMessageFile() : string {
        return ":titleは:max以下のサイズのファイルを指定してください。";
    }
    
    
    /**
     * 引数のチェックを行う。
     * @param array|null $args
     * @throws ValidationRuleException
     */
    private function checkArgs(?array $args = []) : void {
        if( empty($args) ){
            throw new ValidationRuleException("引数を取得してください。");
        }
        if( count($args) !== 1 ){
            throw new ValidationRuleException("引数は1つのみ設定可能です。");
        }
    }
    
    protected function judgeResults( $result ) : bool {
        return $result >= 0;
    }
    
    #[\Override] protected function getErrorMessageDatetime() : string {
        return ":titleは:maxより前の日時を指定してください。";
    }
}