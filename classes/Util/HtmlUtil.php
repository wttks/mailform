<?php

namespace AIJOH\Util;

class HtmlUtil {
    
    public static function escape(string $str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
    
}