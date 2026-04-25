<?php

namespace AIJOH\AI\Client;

use AIJOH\AI\AIClientException;
use AIJOH\AI\AIRequest;
use AIJOH\AI\AIResponse;

/**
 * OpenAI Chat Completions API クライアント。
 * https://platform.openai.com/docs/api-reference/chat/create
 *
 * 設定キー:
 *   - api_key: 必須
 *   - model:   モデル ID（例: 'gpt-5-nano'）
 *   - timeout
 *   - api_url: 上書き用
 */
class OpenAiApiClient extends HttpAIClient {

    private const DEFAULT_URL = 'https://api.openai.com/v1/chat/completions';


    public function send( AIRequest $request ) : AIResponse {
        $apiKey = (string) ($this->config['api_key'] ?? '');
        if ( $apiKey === '' ) {
            throw new AIClientException('api_key が未設定です');
        }
        $model = (string) ($this->config['model'] ?? 'gpt-5-nano');
        $url   = (string) ($this->config['api_url'] ?? self::DEFAULT_URL);

        // OpenAI は system も messages の先頭に入れる形
        $messages = [];
        if ( $request->system !== '' ) {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }
        foreach ( $request->messages as $m ) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        $body = [
            'model'      => $model,
            'max_tokens' => $request->maxTokens,
            'messages'   => $messages,
        ];
        if ( $request->jsonMode ) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ];

        $decoded = $this->postJson($url, $headers, $body);

        $text = $decoded['choices'][0]['message']['content'] ?? '';
        if ( ! is_string($text) ) {
            throw new AIClientException('レスポンスから content を抽出できませんでした');
        }
        return $this->buildResponse($text, $request->jsonMode);
    }

}
