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
 *
 * セキュリティ:
 *   locale 値は `isValidLocaleSyntax()` で構文検証する（path traversal 阻止）。
 *   さらに「同梱ファイル」または「setMessages() で追加された locale」しか
 *   setLocale() で受け付けない（動的ホワイトリスト方式）。未知 locale は
 *   error_log に WARN を出してデフォルトのまま据え置く。
 */
class Translator {

    public const DEFAULT_LOCALE = 'ja';

    private static string $locale = self::DEFAULT_LOCALE;

    /** @var array<string, array<string, string>> [locale => [jaMessage => translated]] */
    private static array $messages = [];

    /** @var array<string, bool> [locale => loaded] */
    private static array $loaded = [];

    /**
     * 既知の locale 集合（動的ホワイトリスト）。
     * 同梱ファイル発見時 / setMessages() 呼出時に true をセットする。
     * デフォルト locale は常に許可。
     * @var array<string, bool>
     */
    private static array $known = [ self::DEFAULT_LOCALE => true ];


    /**
     * 現在の locale を設定する。
     *
     * 未知 locale や不正構文は WARN ログを出してデフォルトのまま据え置く
     * （例外を投げると Form 初期化が壊れるため、防御的に fail-soft）。
     */
    public static function setLocale( string $locale ) : void {
        if ( ! self::isValidLocaleSyntax($locale) ) {
            error_log("[mailform] WARN: 不正な locale 形式: '{$locale}'。デフォルト '" . self::DEFAULT_LOCALE . "' を維持します。");
            return;
        }
        // 同梱ファイルがあれば known に登録される
        self::ensureLoaded($locale);
        if ( empty(self::$known[ $locale ]) ) {
            error_log("[mailform] WARN: locale '{$locale}' は同梱されておらず setMessages でも追加されていません。デフォルト '" . self::DEFAULT_LOCALE . "' を維持します。");
            return;
        }
        self::$locale = $locale;
    }


    public static function getLocale() : string {
        return self::$locale;
    }


    /**
     * 任意 locale のメッセージマップを追加する（既存とマージ）。
     * 追加時点で動的ホワイトリストにも登録される。
     * @param array<string, string> $messages [jaMessage => translated]
     */
    public static function setMessages( string $locale, array $messages ) : void {
        if ( ! self::isValidLocaleSyntax($locale) ) {
            error_log("[mailform] WARN: 不正な locale 形式: '{$locale}'。setMessages の呼び出しを無視します。");
            return;
        }
        self::ensureLoaded($locale);
        self::$known[ $locale ] = true;
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
        self::$known = [ self::DEFAULT_LOCALE => true ];
    }


    /**
     * 初回アクセス時に同梱の lang/{locale}.php を読み込む。
     * 同梱ファイルが見つかった locale は known に自動登録する。
     */
    private static function ensureLoaded( string $locale ) : void {
        if ( ! empty(self::$loaded[ $locale ]) ) {
            return;
        }
        self::$loaded[ $locale ] = true;
        $file = __DIR__ . '/../../lang/' . $locale . '.php';
        if ( is_file($file) ) {
            self::$known[ $locale ] = true;
            $loaded = require $file;
            if ( is_array($loaded) ) {
                self::$messages[ $locale ] = array_merge($loaded, self::$messages[ $locale ] ?? []);
            }
        }
    }


    /**
     * locale 文字列の構文を検証する（path traversal 阻止）。
     * 許容: 英数字・アンダースコア・ハイフン、1〜16 文字。
     * 主目的は `../` や `/` 経由の外部パス参照の完全阻止。
     */
    private static function isValidLocaleSyntax( string $locale ) : bool {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{1,16}$/', $locale);
    }
}
