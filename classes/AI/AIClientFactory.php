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
     * @param array $config 'provider' キーで Provider を選択する（文字列または AIProvider enum）
     * @throws \InvalidArgumentException 不明な Provider
     */
    public static function create( array $config ) : AIClient {
        $providerRaw = $config['provider'] ?? '';
        $provider = $providerRaw instanceof AIProvider
            ? $providerRaw
            : AIProvider::tryFrom((string)$providerRaw);
        if ( $provider === null ) {
            throw new \InvalidArgumentException("不明な AI プロバイダ: {$providerRaw}");
        }
        return match ( $provider ) {
            AIProvider::ClaudeApi => new ClaudeApiClient($config),
            AIProvider::ClaudeCli => new ClaudeCliClient($config),
            AIProvider::OpenAiApi => new OpenAiApiClient($config),
            AIProvider::GeminiApi => new GeminiApiClient($config),
        };
    }

}
