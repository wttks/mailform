<?php

namespace AIJOH\Lang;

/**
 * 多言語対応の翻訳マネージャ。
 *
 * 翻訳キーは「日本語デフォルトメッセージ」をそのまま使う gettext 方式。
 * 各 Validate クラスのデフォルト文（例: ':titleは必須項目です。'）を
 * lang/{locale}.php でマップした翻訳に差し替える。
 *
 * - locale 未指定 / 'ja' / 翻訳未定義 の場合はデフォルトの日本語をそのまま返す
 * - 翻訳ファイル: lib/mailform/lang/{locale}.php
 * - 利用側で setMessages() を使えば独自言語ファイルの追加も可能
 */
class Translator {

    public const DEFAULT_LOCALE = 'ja';

    private static string $locale = self::DEFAULT_LOCALE;

    /** @var array<string, array<string, string>> [locale => [jaMessage => translated]] */
    private static array $messages = [];

    /** @var array<string, bool> [locale => loaded] */
    private static array $loaded = [];


    /**
     * 現在の locale を設定する。
     */
    public static function setLocale( string $locale ) : void {
        self::$locale = $locale;
    }


    public static function getLocale() : string {
        return self::$locale;
    }


    /**
     * 任意 locale のメッセージマップを追加する（既存とマージ）。
     * @param array<string, string> $messages [jaMessage => translated]
     */
    public static function setMessages( string $locale, array $messages ) : void {
        self::ensureLoaded($locale);
        self::$messages[ $locale ] = array_merge(self::$messages[ $locale ] ?? [], $messages);
    }


    /**
     * 日本語デフォルトメッセージを現在の locale に翻訳する。
     * 翻訳が無い場合は元の日本語をそのまま返す。
     */
    public static function translate( string $defaultMessage ) : string {
        if ( self::$locale === self::DEFAULT_LOCALE ) {
            return $defaultMessage;
        }
        self::ensureLoaded(self::$locale);
        return self::$messages[ self::$locale ][ $defaultMessage ] ?? $defaultMessage;
    }


    /**
     * 状態をリセット（テスト用）。
     */
    public static function reset() : void {
        self::$locale = self::DEFAULT_LOCALE;
        self::$messages = [];
        self::$loaded = [];
    }


    /**
     * 初回アクセス時に同梱の lang/{locale}.php を読み込む。
     */
    private static function ensureLoaded( string $locale ) : void {
        if ( ! empty(self::$loaded[ $locale ]) ) {
            return;
        }
        self::$loaded[ $locale ] = true;
        $file = __DIR__ . '/../../lang/' . $locale . '.php';
        if ( is_file($file) ) {
            $loaded = require $file;
            if ( is_array($loaded) ) {
                self::$messages[ $locale ] = array_merge($loaded, self::$messages[ $locale ] ?? []);
            }
        }
    }
}
