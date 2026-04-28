<?php

namespace AIJOH\AI\Client;

use AIJOH\AI\AIClientException;
use AIJOH\AI\AIRequest;
use AIJOH\AI\AIResponse;

/**
 * Google Gemini API クライアント (generateContent)。
 * https://ai.google.dev/api/generate-content
 *
 * 設定キー:
 *   - api_key: 必須（クエリ ?key= で渡す）
 *   - model:   モデル ID（例: 'gemini-2.5-flash'）
 *   - timeout
 *   - api_url_base: 上書き用（{model} を model 名に置換）
 */
class GeminiApiClient extends HttpAIClient {

    private const DEFAULT_URL_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent';


    public function send( AIRequest $request ) : AIResponse {
        $apiKey = (string) ($this->config['api_key'] ?? '');
        if ( $apiKey === '' ) {
            throw new AIClientException('api_key が未設定です');
        }
        $model = (string) ($this->config['model'] ?? 'gemini-2.5-flash');
        $base  = (string) ($this->config['api_url_base'] ?? self::DEFAULT_URL_BASE);
        $url   = str_replace('{model}', $model, $base) . '?key=' . urlencode($apiKey);

        // Gemini はメッセージを contents に role + parts.text の形で入れる
        $contents = [];
        foreach ( $request->messages as $m ) {
            // Gemini は 'user' / 'model' という role 名（assistant は model）
            $role = $m->role === 'assistant' ? 'model' : $m->role;
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => $m->content]],
            ];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $request->maxTokens,
            ],
        ];
        if ( $request->system !== '' ) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $request->system]],
            ];
        }
        if ( $request->jsonMode ) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $decoded = $this->postJson($url, $headers, $body);

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ( ! is_string($text) ) {
            throw new AIClientException('レスポンスから text を抽出できませんでした');
        }
        return $this->buildResponse($text, $request->jsonMode);
    }

}
