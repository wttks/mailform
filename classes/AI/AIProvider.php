<?php

namespace AIJOH\AI;

/**
 * AI プロバイダの識別子。
 *
 * 設定 (config.php の ai.provider) で使う。
 */
enum AIProvider : string {

    /** Anthropic Claude API */
    case ClaudeApi = 'claude_api';

    /** ローカルの claude コマンド経由（API キー不要） */
    case ClaudeCli = 'claude_cli';

    /** OpenAI Chat Completions API */
    case OpenAiApi = 'openai_api';

    /** Google Gemini API */
    case GeminiApi = 'gemini_api';


    /**
     * このプロバイダが API キーを必要とするか。
     */
    public function requiresApiKey() : bool {
        return $this !== self::ClaudeCli;
    }

}
