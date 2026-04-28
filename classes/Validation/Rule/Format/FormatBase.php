<?php

namespace AIJOH\Validation\Rule\Format;

abstract class FormatBase {
    
    
    /**
     * データのフォーマットを行う。
     * @param mixed $value
     * @param array|null $args
     * @return mixed
     */
    public abstract function format( mixed $value, ?array $args = null ): mixed;
    
}