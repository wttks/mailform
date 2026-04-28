<?php

namespace AIJOH\Form\Draft;

use AIJOH\Form\Draft\Exception\DraftDecryptException;

/**
 * draft 機能のメイン窓口。
 * DraftSerializer + DraftConsent + CookieIO を組み合わせ、
 * 「保存／復元／削除／同意状態の判定」を一手に提供する。
 */
class DraftManager {

    private const DEFAULT_PREFIX     = 'mailform_draft';
    private const DEFAULT_TTL        = 86400;
    private const DEFAULT_COMPRESS   = 512;
    private const DEFAULT_MAX_BYTES  = 7000;
    private const DEFAULT_SPLIT      = 5;
    private const DEFAULT_BLOCKED    = [
        'password', 'pass', 'pw',
        'credit_card', 'cc_number', 'card_number',
        'cvv', 'cvc', 'pin',
    ];

    private array $config;
    private string $key;
    private DraftSerializer $serializer;
    private DraftConsent $consent;
    private CookieIO $cookieIO;


    /**
     * @param array $draftConfig 'draft' セクションの設定
     * @throws \InvalidArgumentException encryption_key が不正な長さ
     */
    public function __construct(
        array $draftConfig,
        ?DraftSerializer $serializer = null,
        ?DraftConsent $consent = null,
        ?CookieIO $cookieIO = null,
    ) {
        $this->config = $draftConfig;
        $this->key = (string) ( $draftConfig['encryption_key'] ?? '' );
        if ( strlen($this->key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
            throw new \InvalidArgumentException(
                'draft.encryption_key must be ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes',
            );
        }
        $this->serializer = $serializer ?? new DraftSerializer();
        $this->consent = $consent ?? new DraftConsent($draftConfig['consent'] ?? []);
        $this->cookieIO = $cookieIO ?? new PhpCookieIO();
    }


    /**
     * 現在のリクエストで draft 保存・復元が許可されているか。
     */
    public function isAllowed() : bool {
        return $this->consent->isAllowed($this->cookieIO->get($this->getConsentCookieName()));
    }


    /**
     * mailform が同意管理を行うか（builtin モードのみ true）。
     */
    public function isManagedConsent() : bool {
        return $this->consent->isManagedByMailform();
    }


    /**
     * POST データを draft Cookie として保存する。
     * - 同意未取得（builtin + opt-in 等）なら何もしない
     * - 既存 draft Cookie はクリアしてから新たにセット（古い分割の残骸防止）
     * - filter したら空になった場合は draft Cookie ごと削除
     */
    public function save( array $postedData ) : void {
        if ( ! $this->isAllowed() ) {
            return;
        }

        $filtered = $this->filterFields($postedData);

        // まず既存 draft Cookie をクリア
        $this->clearDraftCookies();

        if ( empty($filtered) ) {
            return;
        }

        $cookies = $this->serializer->serialize(
            $filtered,
            $this->key,
            (int) ( $this->config['compress'] ?? self::DEFAULT_COMPRESS ),
            (int) ( $this->config['max_bytes'] ?? self::DEFAULT_MAX_BYTES ),
            (int) ( $this->config['split'] ?? self::DEFAULT_SPLIT ),
        );

        $options = $this->cookieOptions();
        foreach ( $cookies as $i => $value ) {
            $this->cookieIO->set($this->draftCookieName($i), $value, $options);
        }
    }


    /**
     * draft Cookie から保存済みデータを復元する。
     * 同意未取得・未保存・復号失敗の場合は空配列。
     * 復号失敗時は壊れた Cookie を自動削除する。
     */
    public function restore() : array {
        if ( ! $this->isAllowed() ) {
            return [];
        }

        $cookies = $this->collectDraftCookies();
        if ( empty($cookies) ) {
            return [];
        }

        try {
            return $this->serializer->unserialize($cookies, $this->key);
        } catch ( DraftDecryptException $e ) {
            $this->clearDraftCookies();
            return [];
        }
    }


    /**
     * draft Cookie を全削除（送信成功時 / 破棄ボタン用）。
     * 同意 Cookie は削除しない（同意状態は維持する）。
     */
    public function clear() : void {
        $this->clearDraftCookies();
    }


    /**
     * 同意状態を Cookie に書き込む（builtin モード用）。
     * @param string $status 'granted' または 'revoked'
     */
    public function setConsent( string $status ) : void {
        if ( ! $this->isManagedConsent() ) {
            return;
        }
        $this->cookieIO->set(
            $this->getConsentCookieName(),
            $this->consent->makeConsentCookieValue($status),
            $this->cookieOptions(),
        );
    }


    public function getConsent() : DraftConsent {
        return $this->consent;
    }


    /**
     * ホワイトリストに合致し、危険フィールド名でないものだけ抽出する。
     */
    private function filterFields( array $postedData ) : array {
        $whitelist = $this->config['fields'] ?? [];
        $filtered = [];
        foreach ( $whitelist as $field ) {
            $field = (string) $field;
            if ( $this->isDangerousFieldName($field) ) {
                continue;
            }
            if ( array_key_exists($field, $postedData) ) {
                $filtered[ $field ] = $postedData[ $field ];
            }
        }
        return $filtered;
    }


    /**
     * パスワード・カード番号系のフィールド名を強制除外する。
     * 設置者が config で blocked_fields を追加可能。
     */
    private function isDangerousFieldName( string $field ) : bool {
        $custom = $this->config['blocked_fields'] ?? [];
        $blocked = array_merge(self::DEFAULT_BLOCKED, $custom);
        $lower = strtolower($field);
        foreach ( $blocked as $name ) {
            if ( $name === '' ) {
                continue;
            }
            if ( str_contains($lower, strtolower((string) $name)) ) {
                return true;
            }
        }
        return false;
    }


    /**
     * draft データ Cookie を収集する（同意 Cookie は除外）。
     * @return array<int, string> 添字 = 分割番号、値 = Cookie 値
     */
    private function collectDraftCookies() : array {
        $prefix = $this->getCookiePrefix() . '_';
        $consentName = $this->getConsentCookieName();
        $cookies = [];
        foreach ( $this->cookieIO->getAll() as $name => $value ) {
            if ( $name === $consentName ) {
                continue;
            }
            if ( ! str_starts_with($name, $prefix) ) {
                continue;
            }
            $suffix = substr($name, strlen($prefix));
            if ( ! ctype_digit($suffix) ) {
                continue;
            }
            $cookies[ (int) $suffix ] = $value;
        }
        return $cookies;
    }


    private function clearDraftCookies() : void {
        $prefix = $this->getCookiePrefix() . '_';
        $consentName = $this->getConsentCookieName();
        $options = $this->cookieOptions();
        foreach ( $this->cookieIO->getAll() as $name => $value ) {
            if ( $name === $consentName ) {
                continue;
            }
            if ( ! str_starts_with($name, $prefix) ) {
                continue;
            }
            $suffix = substr($name, strlen($prefix));
            if ( ! ctype_digit($suffix) ) {
                continue;
            }
            $this->cookieIO->delete($name, $options);
        }
    }


    private function draftCookieName( int $index ) : string {
        return $this->getCookiePrefix() . '_' . $index;
    }


    private function getConsentCookieName() : string {
        return $this->getCookiePrefix() . '_consent';
    }


    private function getCookiePrefix() : string {
        return (string) ( $this->config['cookie']['name_prefix'] ?? self::DEFAULT_PREFIX );
    }


    /**
     * @return array{expires:int, path:string, secure:bool, httponly:bool, samesite:string}
     */
    private function cookieOptions() : array {
        $ttl = (int) ( $this->config['ttl'] ?? self::DEFAULT_TTL );
        return [
            'expires'  => time() + $ttl,
            'path'     => (string) ( $this->config['cookie']['path'] ?? '/' ),
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }
}
