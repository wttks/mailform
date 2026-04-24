<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\ObjectUtil;

trait Required {
    
    /**
     * データの入力が存在するかどうかの判定を行う。
     * @param mixed $value
     * @return bool
     */
    public function isRequired(mixed $value) : bool {
        return ! ObjectUtil::isEmpty($value);
    }
    
    
}