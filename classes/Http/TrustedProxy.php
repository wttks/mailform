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
 * デフォルトは `trust_forwarded_for=false`（安全側）。プロキシ越し配置のときだけ
 * 明示的に true にし、trusted_proxies にプロキシ IP / CIDR を設定する必要がある。
 *
 * 解析ロジック:
 * - trust_forwarded_for=false → 常に REMOTE_ADDR
 * - trust_forwarded_for=true + trusted_proxies 空 → WARN ログを出し、安全側で REMOTE_ADDR
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
     * trust_forwarded_for=true で trusted_proxies が空のときは起動時 1 回 WARN を出し、
     * 実行時は安全側で X-Forwarded-* を信用しない ( REMOTE_ADDR を使う )。
     */
    public static function configure( array $config ) : void {
        self::$config = $config;
        self::$configured = true;

        $trust = (bool) ( $config['trust_forwarded_for'] ?? false );
        $proxies = (array) ( $config['trusted_proxies'] ?? [] );
        if ( $trust && empty($proxies) ) {
            error_log(
                "[mailform] WARN: http.trust_forwarded_for=true ですが http.trusted_proxies が空です。"
                . " 空の場合は X-Forwarded-* を無視し REMOTE_ADDR を使います。"
                . " プロキシ越し配置の場合は trusted_proxies にプロキシ IP / CIDR を設定してください。"
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
     * デフォルト ( configure 未呼出 / trust_forwarded_for=false ) は REMOTE_ADDR。
     * X-Forwarded-* を信用するには trust_forwarded_for=true と trusted_proxies の
     * 設定の両方が必要 ( どちらが欠けても REMOTE_ADDR )。
     */
    public static function getClientIp() : string {
        $remote = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

        if ( ! self::trustForwarded() ) {
            return $remote;
        }

        $proxies = self::trustedProxies();
        if ( empty($proxies) ) {
            // 安全側: trusted_proxies 未設定なら XFF を信用しない
            // ( configure() で WARN ログ済 )
            return $remote;
        }

        $forwarded = self::parseForwardedFor();
        if ( empty($forwarded) ) {
            return $remote;
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
     * X-Forwarded-Proto を信頼するには trust_forwarded_for=true かつ
     * trusted_proxies が設定済みかつ REMOTE_ADDR が trusted である必要がある。
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
            // 安全側: trusted_proxies 未設定なら XFP を信用しない
            return false;
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
     * 各要素は inet_pton で IP 形式検証し、不正な値 ( ホスト名 / 制御文字混入 /
     * `unknown` 等 ) はリストから除外する。これがないと、edge proxy が incoming
     * XFF を strip/overwrite せず append する構成で、攻撃者が任意の文字列を
     * 先頭 XFF に注入してレート制限カウンタキーを分散させられる。
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
            if ( $p === '' ) {
                continue;
            }
            // 不正な値 ( IP 形式でない / 制御文字含む / unknown 等 ) は無視
            if ( @inet_pton($p) === false ) {
                continue;
            }
            $result[] = $p;
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
            // configure 未呼び出しなら安全側 ( 信用しない )
            return false;
        }
        return (bool) ( self::$config['trust_forwarded_for'] ?? false );
    }


    /**
     * @return string[]
     */
    private static function trustedProxies() : array {
        $list = (array) ( self::$config['trusted_proxies'] ?? [] );
        return array_values(array_filter($list, 'is_string'));
    }
}
