<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\DataSize;
use AIJOH\Util\DateUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;


class ValidateMin extends ValidateBaseSizeCompare {
    
    public function getArgNames() : array {
        return ['min'];
    }

    protected function getErrorMessageNumber() : string {
        return ":titleは:min以上を指定してください。";
    }

    protected function getErrorMessageString() : string {
        return ":titleは:min文字以上で入力してください。";
    }

    protected function getErrorMessageDate() : string {
        return ":titleは:minより後の日付を指定してください。";
    }

    protected function getErrorMessageArray() : string {
        return ":titleは:min個以上で入力してください。";
    }

    protected function getErrorMessageFile() : string {
        return ":titleは:min以上のサイズのファイルを指定してください。";
    }


    protected function getErrorMessageDatetime() : string {
        return ":titleは:minより後の日時を指定してください。";
    }
    
    
    /**
     * 判定結果を返す。
     * @param $result
     * @return bool
     */
    #[\Override] protected function judgeResults( $result ) : bool {
        return $result <= 0;
    }
}