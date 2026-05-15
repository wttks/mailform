<?php

namespace AIJOH\Http;

/**
 * 信頼プロキシ判定と X-Forwarded-* ヘッダの安全な解析を行う。
 *
 * 設定例:
 *   'http' => [
 *       'trust_forwarded_for' => true,            // X-Forwarded-* を信頼するか
 *       'trusted_proxies' => [                    // 信頼するプロキシの IP / CIDR
 *           '127.0.0.1',
 *           '172.20.0.0/16',
 *           '::1',
 *           'fd00::/8',
 *       ],
 *   ]
 *
 * デフォルトは `trust_forwarded_for=true` / `trusted_proxies=[]`（後方互換）。
 * 空のとき configure() 時点で WARN ログを出して、設置者に「ヘッダ偽装で
 * IP 単位のレート制限カウンタが分散される」リスクを通知する。
 *
 * 解析ロジック:
 * - trust_forwarded_for=false → 常に REMOTE_ADDR
 * - trust_forwarded_for=true + trusted_proxies 空 → 後方互換で X-Forwarded-For 先頭採用
 * - trust_forwarded_for=true + trusted_proxies 設定済み →
 *     REMOTE_ADDR が trusted なときだけ、X-Forwarded-For を後ろから辿り
 *     最初の「非 trusted」IP（= 真クライアント IP）を返す
 *     （Symfony / Laravel / Django と同等のロジック）
 */
class TrustedProxy {

    private static array $config = [];
    private static bool $configured = false;


    /**
     * 'http' セクションの設定を登録する。
     * trust_forwarded_for=true で trusted_proxies が空のときは起動時 1 回 WARN を出す。
     */
    public static function configure( array $config ) : void {
        self::$config = $config;
        self::$configured = true;

        $trust = (bool) ( $config['trust_forwarded_for'] ?? true );
        $proxies = (array) ( $config['trusted_proxies'] ?? [] );
        if ( $trust && empty($proxies) ) {
            error_log(
                "[mailform] WARN: http.trust_forwarded_for=true ですが http.trusted_proxies が空です。"
                . " X-Forwarded-For ヘッダを偽装されると IP 単位のレート制限カウンタが分散され、"
                . " 事実上の迂回が可能になります。プロキシ越し配置の場合は trusted_proxies に"
                . " プロキシ IP / CIDR を設定してください。直公開（プロキシなし）の場合は"
                . " trust_forwarded_for=false にしてください。"
            );
        }
    }


    /**
     * 状態をリセット（テスト用）。
     */
    public static function reset() : void {
        self::$config = [];
        self::$configured = false;
    }


    /**
     * クライアント IP を取得する。
     * 設定が未登録の場合は後方互換挙動（X-Forwarded-For 先頭採用 / 無ければ REMOTE_ADDR）。
     */
    public static function getClientIp() : string {
        $remote = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

        if ( ! self::trustForwarded() ) {
            return $remote;
        }

        $forwarded = self::parseForwardedFor();
        if ( empty($forwarded) ) {
            return $remote;
        }

        $proxies = self::trustedProxies();
        if ( empty($proxies) ) {
            // 後方互換: trusted_proxies 未設定なら従来通り先頭採用
            return $forwarded[0];
        }

        // REMOTE_ADDR が trusted でなければ X-Forwarded-* は信用しない
        if ( ! self::isTrustedIp($remote, $proxies) ) {
            return $remote;
        }

        // 後ろから辿り、最初の「非 trusted」IP = 真クライアント IP
        // XFF: "client, edge_proxy, internal_lb" の並びで来る想定
        for ( $i = count($forwarded) - 1; $i >= 0; $i-- ) {
            if ( ! self::isTrustedIp($forwarded[ $i ], $proxies) ) {
                return $forwarded[ $i ];
            }
        }

        // 全部 trusted なら XFF の先頭を返す（実質的に LB 自身が起点）
        return $forwarded[0];
    }


    /**
     * 現在のリクエストが HTTPS か判定する。
     * trust_forwarded_for=true かつ REMOTE_ADDR が trusted なら X-Forwarded-Proto を信頼。
     * trusted_proxies 未設定なら後方互換で X-Forwarded-Proto を信頼。
     */
    public static function isHttps() : bool {
        if ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ) {
            return true;
        }
        if ( ($_SERVER['SERVER_PORT'] ?? '') === '443' ) {
            return true;
        }

        $forwardedProto = (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' );
        if ( $forwardedProto === '' ) {
            return false;
        }

        if ( ! self::trustForwarded() ) {
            return false;
        }

        $proxies = self::trustedProxies();
        if ( empty($proxies) ) {
            // 後方互換
            return strtolower($forwardedProto) === 'https';
        }

        $remote = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
        if ( ! self::isTrustedIp($remote, $proxies) ) {
            return false;
        }
        return strtolower($forwardedProto) === 'https';
    }


    /**
     * IP が trusted_proxies リストの単一 IP / CIDR にマッチするか。
     *
     * @param string[] $proxies
     */
    public static function isTrustedIp( string $ip, array $proxies ) : bool {
        if ( $ip === '' ) {
            return false;
        }
        foreach ( $proxies as $pattern ) {
            if ( ! is_string($pattern) || $pattern === '' ) {
                continue;
            }
            if ( str_contains($pattern, '/') ) {
                if ( self::ipInCidr($ip, $pattern) ) {
                    return true;
                }
            } else {
                if ( self::normalizeIp($ip) === self::normalizeIp($pattern) ) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * IP が CIDR 表記の範囲内か判定する（IPv4 / IPv6 両対応、inet_pton ベース）。
     */
    public static function ipInCidr( string $ip, string $cidr ) : bool {
        if ( ! str_contains($cidr, '/') ) {
            return false;
        }
        [ $subnet, $bitsStr ] = explode('/', $cidr, 2);
        if ( ! ctype_digit($bitsStr) ) {
            return false;
        }
        $bits = (int) $bitsStr;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ( $ipBin === false || $subnetBin === false ) {
            return false;
        }
        // IPv4 と IPv6 を混在比較しない
        if ( strlen($ipBin) !== strlen($subnetBin) ) {
            return false;
        }

        $byteCount = strlen($ipBin);
        $maxBits = $byteCount * 8;
        if ( $bits < 0 || $bits > $maxBits ) {
            return false;
        }
        if ( $bits === 0 ) {
            return true;  // 0.0.0.0/0 や ::/0 は全部マッチ
        }

        // バイト単位の比較
        $fullBytes = intdiv($bits, 8);
        $remainBits = $bits % 8;

        if ( $fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes) ) {
            return false;
        }
        if ( $remainBits === 0 ) {
            return true;
        }
        $mask = chr(0xFF << (8 - $remainBits) & 0xFF);
        return ( $ipBin[ $fullBytes ] & $mask ) === ( $subnetBin[ $fullBytes ] & $mask );
    }


    /**
     * X-Forwarded-For を IP のリストに分解する。
     * "client, proxy1, proxy2" を ["client", "proxy1", "proxy2"] に。
     *
     * @return string[]
     */
    private static function parseForwardedFor() : array {
        $forwarded = (string) ( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' );
        if ( $forwarded === '' ) {
            return [];
        }
        $parts = explode(',', $forwarded);
        $result = [];
        foreach ( $parts as $p ) {
            $p = trim($p);
            if ( $p !== '' ) {
                $result[] = $p;
            }
        }
        return $result;
    }


    /**
     * IPv4 mapped IPv6 などの差異を吸収するため inet_pton で正規化する。
     */
    private static function normalizeIp( string $ip ) : string {
        $bin = @inet_pton($ip);
        return $bin === false ? $ip : $bin;
    }


    private static function trustForwarded() : bool {
        if ( ! self::$configured ) {
            // configure 未呼び出しなら従来挙動（信頼する）
            return true;
        }
        return (bool) ( self::$config['trust_forwarded_for'] ?? true );
    }


    /**
     * @return string[]
     */
    private static function trustedProxies() : array {
        $list = (array) ( self::$config['trusted_proxies'] ?? [] );
        return array_values(array_filter($list, 'is_string'));
    }
}
