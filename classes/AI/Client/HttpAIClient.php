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
     * 受け付けるのは以下のいずれかの形式のみ:
     *   1. 全体が「```json ... ```」または「``` ... ```」コードブロック ( 前後空白のみ可 )
     *   2. 全体がプレーン JSON ( 前後空白のみ可 )
     *
     * 「説明文 + 中に { ... }」「説明文 + 中に code fence」のような混在応答は
     * 採用しない ( プロンプトインジェクション対策 )。jsonMode のとき、モデルに
     * 「JSON のみ返す」よう指示する前提で、契約違反の応答は decode 失敗扱い ( null )。
     */
    private function extractJsonObject( string $text ) : ?array {
        // 全体が ```json ... ``` ( 前後空白のみ可 )
        if ( preg_match('/\A\s*```(?:json)?\s*(\{.*?\})\s*```\s*\z/s', $text, $m) ) {
            $decoded = json_decode($m[1], true);
            if ( is_array($decoded) ) return $decoded;
        }
        // 全体がプレーン JSON
        $trimmed = trim($text);
        $decoded = json_decode($trimmed, true);
        if ( is_array($decoded) ) return $decoded;

        return null;
    }

}
