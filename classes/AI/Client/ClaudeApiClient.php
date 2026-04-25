<?php

namespace AIJOH\AI\Client;

use AIJOH\AI\AIClientException;
use AIJOH\AI\AIRequest;
use AIJOH\AI\AIResponse;

/**
 * Anthropic Claude Messages API クライアント。
 * https://docs.claude.com/en/api/messages
 *
 * 設定キー:
 *   - api_key: 必須
 *   - model:   モデル ID（例: 'claude-haiku-4-5'）
 *   - timeout: タイムアウト秒
 *   - api_url: 上書き用（デフォルト Anthropic 公式）
 */
class ClaudeApiClient extends HttpAIClient {

    private const DEFAULT_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';


    public function send( AIRequest $request ) : AIResponse {
        $apiKey = (string) ($this->config['api_key'] ?? '');
        if ( $apiKey === '' ) {
            throw new AIClientException('api_key が未設定です');
        }
        $model = (string) ($this->config['model'] ?? 'claude-haiku-4-5');
        $url   = (string) ($this->config['api_url'] ?? self::DEFAULT_URL);

        $messages = [];
        foreach ( $request->messages as $m ) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        $body = [
            'model'      => $model,
            'max_tokens' => $request->maxTokens,
            'system'     => $request->system,
            'messages'   => $messages,
        ];

        $headers = [
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type'      => 'application/json',
        ];

        $decoded = $this->postJson($url, $headers, $body);

        // content[0].text を取り出す
        $text = $decoded['content'][0]['text'] ?? '';
        if ( ! is_string($text) ) {
            throw new AIClientException('レスポンスから text を抽出できませんでした');
        }
        return $this->buildResponse($text, $request->jsonMode);
    }

}
