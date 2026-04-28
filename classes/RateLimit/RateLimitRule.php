<?php

namespace AIJOH\RateLimit;

/**
 * 1 件のレート制限ルールを表すデータクラス。
 *
 * 例: ['key' => 'ip', 'limit' => 5, 'window' => 60]
 *     → 同一 IP からの直近60秒間のリクエストを5件までに制限
 */
final class RateLimitRule {

    public function __construct(
        /** カウントするキーの種別: 'ip' | 'session' など */
        public readonly string $keyType,
        /** 上限件数 */
        public readonly int $limit,
        /** 集計時間窓（秒） */
        public readonly int $windowSec,
    ) {}


    /**
     * 設定配列からインスタンスを生成。
     *
     * @param array{key: string, limit: int, window: int} $config
     */
    public static function fromConfig( array $config ) : self {
        return new self(
            $config['key'] ?? 'ip',
            (int) ($config['limit'] ?? 0),
            (int) ($config['window'] ?? 0),
        );
    }

}
