<?php

namespace AIJOH\AI;

use AIJOH\AI\Client\ClaudeApiClient;
use AIJOH\AI\Client\ClaudeCliClient;
use AIJOH\AI\Client\GeminiApiClient;
use AIJOH\AI\Client\OpenAiApiClient;

/**
 * 設定からプロバイダ別 AIClient を生成する。
 */
class AIClientFactory {

    /**
     * @param array $config 'provider' キーで Provider を選択する
     * @throws \InvalidArgumentException 不明な Provider
     */
    public static function create( array $config ) : AIClient {
        $provider = (string) ($config['provider'] ?? '');
        return match ( $provider ) {
            'claude_api' => new ClaudeApiClient($config),
            'claude_cli' => new ClaudeCliClient($config),
            'openai_api' => new OpenAiApiClient($config),
            'gemini_api' => new GeminiApiClient($config),
            default      => throw new \InvalidArgumentException("不明な AI プロバイダ: {$provider}"),
        };
    }

}
