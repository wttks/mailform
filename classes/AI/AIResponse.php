<?php

namespace AIJOH\AI;

/**
 * AI からのレスポンス。
 *
 * jsonMode で送ったときは jsonData にパース済みの連想配列が入る。
 * jsonMode 無し / パース失敗時は jsonData は null、text に raw 文字列。
 */
final class AIResponse {

    public function __construct(
        public readonly string $text,
        public readonly ?array $jsonData = null,
    ) {}

}
