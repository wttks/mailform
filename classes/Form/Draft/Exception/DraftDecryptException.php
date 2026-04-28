<?php

namespace AIJOH\Form\Draft\Exception;

/**
 * draft Cookie の復号・パース失敗時にスローされる。
 * 不正な暗号化キー、欠損した分割 Cookie、改ざんされた値、不正フォーマットなど。
 */
class DraftDecryptException extends \RuntimeException {}
