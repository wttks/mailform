<?php

namespace AIJOH\Form\Draft;

/**
 * draft 機能の同意管理判定。
 *
 * 3 モード：
 *   - builtin  : mailform が同梱 UI と同意 Cookie で管理。PHP 側で実際に判定する。
 *   - callback : 設置者の CMP に判定を委譲。PHP 側は通す（信頼ベース）。
 *   - disabled : mailform は管理しない。PHP 側は通す（設置者全責任、API のみ提供）。
 *
 * builtin での挙動：
 *   - opt-in  : 同意 Cookie が 'granted' のときだけ許可
 *   - opt-out : 同意 Cookie が 'revoked' でなければ許可（デフォルト許可）
 *   - policy_version 設定時、Cookie のバージョンと不一致なら同意リセット扱い
 *     （opt-in は再拒否、opt-out はデフォルトに戻る）
 *
 * 同意 Cookie 値フォーマット:
 *   - policy_version 無し: 'granted' または 'revoked'
 *   - policy_version 有り: 'granted:2026-04-28' のように `:` 区切りでバージョン付与
 */
class DraftConsent {

    public const MODE_BUILTIN  = 'builtin';
    public const MODE_CALLBACK = 'callback';
    public const MODE_DISABLED = 'disabled';

    public const BEHAVIOR_OPT_IN  = 'opt-in';
    public const BEHAVIOR_OPT_OUT = 'opt-out';

    public const STATUS_GRANTED = 'granted';
    public const STATUS_REVOKED = 'revoked';

    private string $mode;
    private string $behavior;
    private ?string $policyVersion;


    public function __construct( array $config ) {
        $this->mode = $config['mode'] ?? self::MODE_DISABLED;
        $this->behavior = $config['behavior'] ?? self::BEHAVIOR_OPT_IN;
        $this->policyVersion = $config['policy_version'] ?? null;
    }


    /**
     * mailform が同意管理を実際に行うか（builtin モードのみ true）。
     * builtin の場合、設置者は HTML スニペットを設置するだけで、
     * mailform が同意 Cookie の発行・読み取り・判定を担う。
     */
    public function isManagedByMailform() : bool {
        return $this->mode === self::MODE_BUILTIN;
    }


    /**
     * 現在の同意 Cookie 値で draft 保存・復元が許可されているか判定する。
     *
     * @param string|null $consentCookieValue 同意 Cookie の値（無ければ null）
     */
    public function isAllowed( ?string $consentCookieValue ) : bool {
        if ( ! $this->isManagedByMailform() ) {
            // callback / disabled では PHP 側で判定しない（信頼ベース）
            return true;
        }

        // builtin モード
        $parsed = $this->parseConsentCookie($consentCookieValue);

        // policy_version 不一致なら過去の同意/拒否を無効化（未表明状態に戻す）
        if ( $this->policyVersion !== null && $parsed['version'] !== $this->policyVersion ) {
            $parsed['status'] = null;
        }

        if ( $this->behavior === self::BEHAVIOR_OPT_IN ) {
            return $parsed['status'] === self::STATUS_GRANTED;
        }

        // opt-out: 明示的に revoked でない限り許可
        return $parsed['status'] !== self::STATUS_REVOKED;
    }


    /**
     * 同意 Cookie の値を生成する（Set-Cookie 用）。
     *
     * @param string $status 'granted' または 'revoked'
     */
    public function makeConsentCookieValue( string $status ) : string {
        if ( ! in_array($status, [ self::STATUS_GRANTED, self::STATUS_REVOKED ], true) ) {
            throw new \InvalidArgumentException("Invalid consent status: {$status}");
        }
        if ( $this->policyVersion === null ) {
            return $status;
        }
        return $status . ':' . $this->policyVersion;
    }


    public function getMode() : string {
        return $this->mode;
    }


    public function getBehavior() : string {
        return $this->behavior;
    }


    public function getPolicyVersion() : ?string {
        return $this->policyVersion;
    }


    /**
     * @return array{status: ?string, version: ?string}
     */
    private function parseConsentCookie( ?string $value ) : array {
        if ( $value === null || $value === '' ) {
            return [ 'status' => null, 'version' => null ];
        }
        $parts = explode(':', $value, 2);
        $status = $parts[0];
        if ( ! in_array($status, [ self::STATUS_GRANTED, self::STATUS_REVOKED ], true) ) {
            return [ 'status' => null, 'version' => null ];
        }
        return [ 'status' => $status, 'version' => $parts[1] ?? null ];
    }
}
