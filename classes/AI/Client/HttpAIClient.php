<?php

namespace AIJOH\AI\Client;

use AIJOH\AI\AIClient;
use AIJOH\AI\AIClientException;

/**
 * HTTP 経由の AI クライアントの共通実装。
 * curl_init を使って POST する。
 */
abstract class HttpAIClient extends AIClient {

    /** @var array<string, mixed> 設定 */
    protected array $config;

    /** @var int タイムアウト秒 */
    protected int $timeout;


    public function __construct( array $config ) {
        $this->config  = $config;
        $this->timeout = (int) ($config['timeout'] ?? 10);
    }


    /**
     * JSON ボディの POST を実行し、デコード済み連想配列を返す。
     *
     * @param string $url
     * @param array<string, string> $headers ヘッダ連想配列
     * @param array $body リクエスト本体（JSON エンコード対象）
     * @return array
     * @throws AIClientException
     */
    protected function postJson( string $url, array $headers, array $body ) : array {
        $ch = curl_init($url);
        if ( $ch === false ) {
            throw new AIClientException('curl_init に失敗しました');
        }
        $headerLines = [];
        foreach ( $headers as $name => $value ) {
            $headerLines[] = $name . ': ' . $value;
        }
        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ( $errno !== 0 || $response === false ) {
            throw new AIClientException("curl エラー ({$errno}): {$error}");
        }
        if ( $status < 200 || $status >= 300 ) {
            throw new AIClientException("HTTP {$status}: " . substr((string)$response, 0, 200));
        }

        $decoded = json_decode((string)$response, true);
        if ( ! is_array($decoded) ) {
            throw new AIClientException('レスポンスが JSON として解析できませんでした');
        }
        return $decoded;
    }


    /**
     * AIResponse を text + 任意の jsonData で組み立てる。
     */
    protected function buildResponse( string $text, bool $jsonMode ) : \AIJOH\AI\AIResponse {
        $jsonData = null;
        if ( $jsonMode ) {
            $extracted = $this->extractJsonObject($text);
            if ( $extracted !== null ) {
                $jsonData = $extracted;
            }
        }
        return new \AIJOH\AI\AIResponse($text, $jsonData);
    }


    /**
     * 文字列から JSON オブジェクト部分を抽出して連想配列で返す。
     * 「```json ... ```」コードブロック表記やプレーンの両方に対応。
     */
    private function extractJsonObject( string $text ) : ?array {
        // ```json ... ``` を抜き出す
        if ( preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m) ) {
            $decoded = json_decode($m[1], true);
            if ( is_array($decoded) ) return $decoded;
        }
        // プレーン JSON
        $trimmed = trim($text);
        $decoded = json_decode($trimmed, true);
        if ( is_array($decoded) ) return $decoded;

        // 最初の { から最後の } を切り出して試す
        $start = strpos($trimmed, '{');
        $end   = strrpos($trimmed, '}');
        if ( $start !== false && $end !== false && $end > $start ) {
            $sub = substr($trimmed, $start, $end - $start + 1);
            $decoded = json_decode($sub, true);
            if ( is_array($decoded) ) return $decoded;
        }
        return null;
    }

}
