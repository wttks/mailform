<?php

namespace AIJOH\Form\Draft\Exception;

/**
 * draft データが Cookie 容量上限（max_bytes / split）を超えた時にスローされる。
 */
class DraftOverflowException extends \RuntimeException {}
