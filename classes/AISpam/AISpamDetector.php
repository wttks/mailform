<?php

namespace AIJOH\AISpam;

use AIJOH\AI\AIClient;
use AIJOH\AI\AIClientException;
use AIJOH\AI\AIClientFactory;
use AIJOH\AI\AIMessage;
use AIJOH\AI\AIRequest;
use AIJOH\AI\AIResponse;

/**
 * AI を使ってフォーム送信内容のスパム判定を行う。
 *
 * 設定:
 *   ai (provider など)、ai_spam (enabled, fields, threshold, cache, ...)
 *
 * 判定タイミング:
 *   form.php 送信時のみ（バリデーション後・メール送信前）
 *
 * 失敗時:
 *   AIClientException → Fail Open（送信を通す） + error_log
 */
class AISpamDetector {

    private static ?AIClient $client = null;
    /** @var array{provider?:string, api_key?:string, model?:string, timeout?:int} */
    private static array $aiConfig = [];
    /** @var array{enabled?:bool, fields?:array, threshold?:float, cache?:bool, cache_dir?:string, cache_ttl?:int, block_message?:string} */
    private static array $spamConfig = [];
    private static bool $disabled = false;


    public static function configure( array $aiConfig, array $spamConfig ) : void {
        self::$aiConfig   = $aiConfig;
        self::$spamConfig = $spamConfig;
        self::$client     = null;  // 設定変更時は再生成
    }


    /**
     * 全判定をスキップする（テスト用）。
     */
    public static function disable() : void {
        self::$disabled = true;
    }


    /**
     * 全状態を初期化する（テスト用）。
     */
    public static function reset() : void {
        self::$aiConfig   = [];
        self::$spamConfig = [];
        self::$client     = null;
        self::$disabled   = false;
    }


    /**
     * AIClient を直接差し込む（テスト用）。
     */
    public static function setClientForTest( AIClient $client ) : void {
        self::$client = $client;
    }


    /**
     * フォームデータを評価して結果を返す。
     */
    public static function judge( array $data ) : SpamJudgement {
        if ( self::$disabled || empty(self::$spamConfig['enabled']) ) {
            return SpamJudgement::clean('disabled');
        }

        $text = self::extractFields($data);
        if ( $text === '' ) {
            return SpamJudgement::clean('no text');
        }

        // キャッシュ確認
        $cached = self::loadCache($text);
        if ( $cached !== null ) {
            return $cached;
        }

        $client = self::getClient();
        if ( $client === null ) {
            return SpamJudgement::clean('no client');
        }

        try {
            $request   = self::buildRequest($text);
            $response  = $client->send($request);
            $judgement = self::parseResponse($response);
            self::saveCache($text, $judgement);
            return $judgement;
        } catch ( AIClientException $e ) {
            // Fail Open: API 失敗時は通す
            error_log("[AISpamDetector] AI client failed: " . $e->getMessage());
            return SpamJudgement::clean('api error: ' . $e->getMessage());
        } catch ( \Throwable $e ) {
            error_log("[AISpamDetector] unexpected error: " . $e->getMessage());
            return SpamJudgement::clean('error: ' . $e->getMessage());
        }
    }


    private static function getClient() : ?AIClient {
        if ( self::$client !== null ) {
            return self::$client;
        }
        if ( empty(self::$aiConfig['provider']) ) {
            return null;
        }
        try {
            self::$client = AIClientFactory::create(self::$aiConfig);
        } catch ( \InvalidArgumentException $e ) {
            error_log("[AISpamDetector] AI client setup failed: " . $e->getMessage());
            return null;
        }
        return self::$client;
    }


    /**
     * 設定で指定されたフィールドだけを連結する。
     * 'fields' 未指定なら全フィールド対象（POST 由来は文字列のみ拾う）。
     */
    private static function extractFields( array $data ) : string {
        $fields = self::$spamConfig['fields'] ?? null;
        $parts = [];
        if ( is_array($fields) && ! empty($fields) ) {
            foreach ( $fields as $key ) {
                $value = $data[$key] ?? null;
                if ( is_scalar($value) && (string)$value !== '' ) {
                    $parts[] = "[{$key}]\n" . (string)$value;
                }
            }
        } else {
            foreach ( $data as $key => $value ) {
                if ( is_scalar($value) && (string)$value !== '' ) {
                    $parts[] = "[{$key}]\n" . (string)$value;
                }
            }
        }
        return implode("\n\n", $parts);
    }


    private static function buildRequest( string $text ) : AIRequest {
        $system = "あなたは日本語のお問い合わせフォームのスパム判定者です。"
            . "営業メール・宣伝・不審なリンク・コピーペースト感の強い文面はスパム判定してください。"
            . "通常のお問い合わせ（質問・相談・予約・苦情・採用応募など）は非スパムです。"
            . "判定結果を JSON で返してください。";
        $user = "以下のフォーム送信内容を判定してください:\n\n```\n{$text}\n```\n\n"
            . '出力フォーマット: {"is_spam": bool, "score": 0.0-1.0, "reason": "理由を簡潔に"}';
        return new AIRequest(
            system: $system,
            messages: [AIMessage::user($user)],
            maxTokens: 256,
            jsonMode: true,
        );
    }


    private static function parseResponse( AIResponse $response ) : SpamJudgement {
        $threshold = (float) (self::$spamConfig['threshold'] ?? 0.7);
        $json = $response->jsonData;
        if ( ! is_array($json) ) {
            // jsonMode で JSON 取れなかった → 通す（fail open）
            return SpamJudgement::clean('json parse failed: ' . substr($response->text, 0, 100));
        }
        $score  = (float) ($json['score'] ?? 0.0);
        $reason = (string) ($json['reason'] ?? '');
        $explicit = (bool) ($json['is_spam'] ?? false);
        // is_spam フラグ または score >= threshold でスパム判定
        $isSpam = $explicit || $score >= $threshold;
        return $isSpam
            ? SpamJudgement::spam($score, $reason)
            : SpamJudgement::clean($reason);
    }


    private static function loadCache( string $text ) : ?SpamJudgement {
        if ( empty(self::$spamConfig['cache']) ) return null;
        $path = self::cachePath($text);
        if ( $path === null || ! is_file($path) ) return null;
        $ttl = (int) (self::$spamConfig['cache_ttl'] ?? 0);
        if ( $ttl > 0 && filemtime($path) + $ttl < time() ) {
            @unlink($path);
            return null;
        }
        $contents = @file_get_contents($path);
        if ( $contents === false ) return null;
        $data = json_decode($contents, true);
        if ( ! is_array($data) ) return null;
        return new SpamJudgement(
            (bool)   ($data['is_spam'] ?? false),
            (float)  ($data['score']   ?? 0.0),
            (string) ($data['reason']  ?? ''),
        );
    }


    private static function saveCache( string $text, SpamJudgement $j ) : void {
        if ( empty(self::$spamConfig['cache']) ) return;
        $path = self::cachePath($text);
        if ( $path === null ) return;
        $dir = dirname($path);
        if ( ! is_dir($dir) ) {
            @mkdir($dir, 0700, true);
        }
        $payload = json_encode([
            'is_spam' => $j->isSpam,
            'score'   => $j->score,
            'reason'  => $j->reason,
        ], JSON_UNESCAPED_UNICODE);
        @file_put_contents($path, $payload);
    }


    private static function cachePath( string $text ) : ?string {
        $dir = self::$spamConfig['cache_dir'] ?? null;
        if ( ! is_string($dir) || $dir === '' ) return null;
        return $dir . '/' . sha1($text) . '.json';
    }

}
