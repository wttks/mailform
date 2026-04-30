<?php

namespace AIJOH\Validation\Rule\Format;

use AIJOH\Util\StrUtil;

/**
 * Unicode Format カテゴリ（\p{Cf}）の文字を全部削除するフォーマッタ。
 * ZWSP / ZWNJ / ZWJ / RTL Override / BOM 等を入力から除去する。
 *
 * normalize_trim は前後の Cc/Cf/Z 削除のみで中間の Cf は残るため、
 * このフォーマッタを併用することで「中間に潜伏した不可視文字」も除去できる。
 *
 * 使用例: 'rule' => 'normalize_trim|normalize_zwsp|required'
 */
class FormatNormalizeZwsp extends FormatBase {

    public function __construct() {
    }


    public function format( mixed $value, ?array $args = [] ) : mixed {
        if ( is_string($value) || is_array($value) ) {
            return StrUtil::stripFormatChars($value);
        }
        return $value;
    }
}
