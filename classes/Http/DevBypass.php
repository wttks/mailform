<?php

namespace AIJOH\Http;

/**
 * 開発者用バイパス機能。
 *
 * 特定の入力値が一致したときに、レート制限・AI スパム判定などの防御層を
 * 個別にバイパスする。IP ベースのホワイトリストの代替で、X-Forwarded-For
 * 偽装の影響を受けないのが利点。
 *
 * 設定例:
 *   'dev_bypass' => [
 *       'enabled' => true,
 *       'bypass'  => ['rate_limit', 'ai_spam'],
 *       'match'   => [
 *           'email' => ['qa-test@example.com'],
 *       ],
 *       'expires_at' => '2026-12-31',  // 任意、過去日付なら自動無効化
 *   ]
 *
 * Closure 形式（より柔軟な判定が必要な場合）:
 *   'match' => fn(array $post) => hash_equals(env('MAILFORM_DEV_TOKEN'), $post['email'] ?? ''),
 *
 * 設置者の責任:
 * - 設定値は必ず local.php / env 経由で読み込み、公開リポジトリにコミットしない
 * - ConfigValidator が enabled=true 時に WARN ログを出すので運用ログを必ず確認する
 */
class DevBypass {

    private static array $config = [];
    private static bool $disabled = false;
    /** expires_at 警告ログを 1 リクエスト 1 回に絞るためのフラグ */
    private static bool $expiredWarned = false;


    /**
     * 設定を登録する。
     */
    public static function configure( array $config ) : void {
        self::$config = $config;
        self::$expiredWarned = false;
    }


    /**
     * バイパスを完全に無効化する（テスト用）。
     */
    public static function disable() : void {
        self::$disabled = true;
    }


    /**
     * 設定とフラグを初期化する（テスト用）。
     */
    public static function reset() : void {
        self::$config = [];
        self::$disabled = false;
        self::$expiredWarned = false;
    }


    /**
     * 指定の防御層がバイパスされるべきかを判定する。
     *
     * @param string $layer 'rate_limit' | 'ai_spam'
     * @param array $data POST データ or フォームデータ
     */
    public static function shouldBypass( string $layer, array $data ) : bool {
        if ( self::$disabled ) {
            return false;
        }
        if ( empty(self::$config['enabled']) ) {
            return false;
        }

        // expires_at チェック
        if ( ! self::isWithinExpiry() ) {
            return false;
        }

        // bypass 対象の層に含まれているか
        $bypassLayers = (array) ( self::$config['bypass'] ?? [] );
        if ( ! in_array($layer, $bypassLayers, true) ) {
            return false;
        }

        // match 評価
        return self::matchEvaluate($layer, $data);
    }


    /**
     * expires_at を満たしているか（未指定 or 未来日付なら true）。
     */
    private static function isWithinExpiry() : bool {
        $expiresAt = self::$config['expires_at'] ?? null;
        if ( ! is_string($expiresAt) || $expiresAt === '' ) {
            return true;
        }
        // 日付文字列の終端 (23:59:59) まで有効とする
        $expires = strtotime($expiresAt . ' 23:59:59');
        if ( $expires === false ) {
            if ( ! self::$expiredWarned ) {
                error_log("[mailform] dev_bypass.expires_at が解析できません: '{$expiresAt}'");
                self::$expiredWarned = true;
            }
            return false;
        }
        if ( $expires < time() ) {
            if ( ! self::$expiredWarned ) {
                error_log("[mailform] dev_bypass.expires_at が過去日付です ({$expiresAt})。バイパスは自動的に無効化されます。");
                self::$expiredWarned = true;
            }
            return false;
        }
        return true;
    }


    /**
     * match 設定を評価して、一致すれば true を返す。
     * Closure 優先、無ければリスト走査（hash_equals でタイミング攻撃対策）。
     */
    private static function matchEvaluate( string $layer, array $data ) : bool {
        $match = self::$config['match'] ?? null;

        if ( $match instanceof \Closure ) {
            $result = (bool) $match($data);
            if ( $result ) {
                error_log("[mailform] dev_bypass triggered: layer={$layer}, match=closure");
            }
            return $result;
        }

        if ( is_array($match) ) {
            foreach ( $match as $field => $expectedValues ) {
                if ( ! is_string($field) ) {
                    continue;
                }
                $actual = $data[ $field ] ?? null;
                if ( ! is_string($actual) ) {
                    // HPP 対策: 配列値は一致しないものとして扱う
                    continue;
                }
                $values = is_array($expectedValues) ? $expectedValues : [ $expectedValues ];
                foreach ( $values as $expected ) {
                    if ( ! is_string($expected) || $expected === '' ) {
                        // 空文字列の expected を許容すると未入力フィールドが一致してしまうのでスキップ
                        continue;
                    }
                    if ( hash_equals($expected, $actual) ) {
                        error_log("[mailform] dev_bypass triggered: layer={$layer}, matched_field={$field}");
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
