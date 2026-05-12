<?php

namespace AIJOH\RateLimit;

use AIJOH\Http\DevBypass;
use AIJOH\Http\Response;
use AIJOH\Http\Session;

/**
 * レート制限の入口。
 *
 * 各エンドポイントの先頭で RateLimit::checkOrAbort($endpointName) を呼ぶと
 * 設定に従ってリクエストを評価し、超過時は 429 相当のレスポンスで終了する。
 *
 * 設定例（config.php）:
 *   'rate_limit' => [
 *       'enabled'     => true,
 *       'storage_dir' => sys_get_temp_dir() . '/mailform_ratelimit',
 *       'endpoints' => [
 *           'submit' => [
 *               ['key' => 'ip',      'limit' => 5,  'window' => 60],
 *               ['key' => 'session', 'limit' => 3,  'window' => 60],
 *           ],
 *       ],
 *   ]
 *
 * 開発者が連投テストしたい場合は IP ホワイトリストではなく `dev_bypass` を使う:
 *   'dev_bypass' => [
 *       'enabled' => true,
 *       'bypass'  => ['rate_limit'],
 *       'match'   => ['email' => ['qa@example.com']],
 *   ]
 * （X-Forwarded-For 偽装で IP ホワイトリストを迂回される問題を回避するため）
 */
class RateLimit {

    private static ?array $config = null;
    private static bool $disabled = false;
    private static ?RateLimitStore $store = null;


    /**
     * 設定を登録する。
     */
    public static function configure( array $config ) : void {
        self::$config = $config;
        self::$store = null;  // store も再構築させる
    }


    /**
     * 全チェックをスキップする（テスト用）。
     */
    public static function disable() : void {
        self::$disabled = true;
    }


    /**
     * 設定とフラグをクリアする（テスト用）。
     */
    public static function reset() : void {
        self::$config = null;
        self::$disabled = false;
        self::$store = null;
    }


    /**
     * Store を直接差し込む（テスト用）。
     */
    public static function setStoreForTest( RateLimitStore $store ) : void {
        self::$store = $store;
    }


    /**
     * エンドポイントの上限を評価する。上限超過なら false、OK なら現在のリクエストを記録して true を返す。
     * テスト用にも使える純粋ロジック。
     *
     * @param string|RateLimitEndpoint $endpoint エンドポイント識別子（enum または文字列）
     * @param array|null $data dev_bypass 判定に使う POST データ。null なら $_POST を読む。
     */
    public static function check( string|RateLimitEndpoint $endpoint, ?array $data = null ) : bool {
        $endpointName = $endpoint instanceof RateLimitEndpoint ? $endpoint->value : $endpoint;
        if ( self::$disabled || self::$config === null || empty(self::$config['enabled']) ) {
            return true;
        }

        // dev_bypass: 特定の入力値が一致したらレート制限を通す（IP ホワイトリスト代替）
        if ( DevBypass::shouldBypass('rate_limit', $data ?? $_POST) ) {
            return true;
        }

        $ip = self::getClientIp();

        $rules = self::$config['endpoints'][ $endpointName ] ?? [];
        if ( empty($rules) ) {
            return true;
        }

        $store = self::getStore();
        if ( $store === null ) {
            return true;
        }

        $sessionId = self::getSessionId();
        $keys = [];
        foreach ( $rules as $ruleConfig ) {
            $rule = RateLimitRule::fromConfig($ruleConfig);
            $key = self::buildKey($endpointName, $rule->keyType, $ip, $sessionId);
            if ( $key === null ) {
                continue;
            }
            $count = $store->countWithin($key, $rule->windowSec);
            if ( $count >= $rule->limit ) {
                return false;  // 超過
            }
            $keys[] = $key;
        }

        // すべてのルールを通過したら現在のリクエストを記録する
        foreach ( array_unique($keys) as $key ) {
            $store->record($key);
        }
        return true;
    }


    /**
     * エンドポイントの上限を評価し、超過時は 429 相当のレスポンスで exit する。
     *
     * @param string|RateLimitEndpoint $endpoint エンドポイント識別子
     * @param array|null $data dev_bypass 判定に使う POST データ。null なら $_POST を読む。
     */
    public static function checkOrAbort( string|RateLimitEndpoint $endpoint, ?array $data = null ) : void {
        if ( ! self::check($endpoint, $data) ) {
            Response::jsonResults(false, '送信が制限されています。しばらくしてから再度お試しください。');
        }
    }


    /**
     * 不要ファイルを掃除する（cron 等から呼び出す）。
     */
    public static function gc( int $maxAgeSec = 86400 ) : int {
        $store = self::getStore();
        if ( $store instanceof FileStore ) {
            return $store->gc($maxAgeSec);
        }
        return 0;
    }


    /**
     * カウント記録キーを生成する（エンドポイント・ルール種別・値の組み合わせ）。
     */
    private static function buildKey( string $endpointName, string $keyType, string $ip, ?string $sessionId ) : ?string {
        $value = match ( $keyType ) {
            'ip'      => $ip,
            'session' => $sessionId,
            default   => null,
        };
        if ( $value === null || $value === '' ) {
            return null;
        }
        return $endpointName . ':' . $keyType . ':' . $value;
    }


    private static function getStore() : ?RateLimitStore {
        if ( self::$store !== null ) {
            return self::$store;
        }
        $dir = self::$config['storage_dir'] ?? null;
        if ( ! is_string($dir) || $dir === '' ) {
            return null;
        }
        self::$store = new FileStore($dir);
        return self::$store;
    }


    /**
     * クライアント IP を返す。リバースプロキシ越しは X-Forwarded-For の先頭を採用する。
     */
    private static function getClientIp() : string {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ( $forwarded !== '' ) {
            $first = trim(explode(',', $forwarded)[0]);
            if ( $first !== '' ) {
                return $first;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }


    private static function getSessionId() : ?string {
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            return session_id() ?: null;
        }
        // Session::getInstance() は session_start を行うがヘッダ送信後は無効
        // ここで初期化してしまうと副作用が強いので諦めて null を返す
        return null;
    }


}
