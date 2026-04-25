<?php

namespace AIJOH\AI;

/**
 * AI への汎用リクエスト。
 *
 * jsonMode = true の場合、各 Provider が JSON 出力を強制する仕組み
 * （Anthropic はプロンプト指示、OpenAI は response_format、
 *   Gemini は responseMimeType）を有効にする。
 */
final class AIRequest {

    public function __construct(
        /** システムプロンプト */
        public readonly string $system,
        /** @var AIMessage[] */
        public readonly array $messages,
        public readonly int $maxTokens = 1024,
        public readonly bool $jsonMode = false,
    ) {}

}
