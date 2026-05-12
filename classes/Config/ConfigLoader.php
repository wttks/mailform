<?php

namespace AIJOH\Config;

/**
 * フォーム設定をマルチフォーム構成でロードするヘルパー。
 *
 * 想定ディレクトリ構成:
 *   public/
 *   ├── common/                ← サイト共通設定
 *   │   ├── verify.php
 *   │   ├── rate_limit.php
 *   │   ├── ai.php
 *   │   ├── ai_spam.php
 *   │   └── local.php          ← 任意。環境別上書き（gitignore）
 *   └── contact/
 *       └── form/
 *           ├── config.php     ← buildFormConfig() を呼ぶだけ
 *           ├── validation.php
 *           └── sender.php
 *
 * config.php は ConfigLoader::buildFormConfig(__DIR__, __DIR__ . '/../../common')
 * の 1 行で済む。
 *
 * マージ順:
 *   1. common/{verify, rate_limit, ai, ai_spam}.php  （存在するもの）
 *   2. form/{validation, sender}.php                 （存在するもの）
 *   3. $overrides 引数                                 （任意の上書き）
 *   4. common/local.php                              （存在すれば、環境別上書き）
 */
class ConfigLoader {

    /** common ディレクトリで読み込む共通セクション */
    private const COMMON_SECTIONS = ['verify', 'rate_limit', 'ai', 'ai_spam', 'dev_bypass'];

    /** form ディレクトリで読み込むフォーム個別セクション */
    private const FORM_SECTIONS = ['validation', 'sender'];


    /**
     * フォーム設定を構築する。
     *
     * @param string $formDir  フォーム個別ファイルが置かれるディレクトリ（通常 __DIR__）
     * @param string $commonDir 共通設定ディレクトリ（例: __DIR__ . '/../../common'）
     * @param array  $overrides フォーム個別の追加上書き（任意）
     * @return array
     */
    public static function buildFormConfig( string $formDir, string $commonDir, array $overrides = [] ) : array {
        $sources = [];

        // 1. common 共通セクション
        foreach ( self::COMMON_SECTIONS as $section ) {
            $path = $commonDir . '/' . $section . '.php';
            if ( is_file($path) ) {
                $sources[] = [ $section => include $path ];
            }
        }

        // 2. form 個別セクション
        foreach ( self::FORM_SECTIONS as $section ) {
            $path = $formDir . '/' . $section . '.php';
            if ( is_file($path) ) {
                $sources[] = [ $section => include $path ];
            }
        }

        // 3. フォーム個別の上書き
        if ( $overrides !== [] ) {
            $sources[] = $overrides;
        }

        // 4. 環境別上書き（common/local.php）
        $localPath = $commonDir . '/local.php';
        if ( is_file($localPath) ) {
            $sources[] = include $localPath;
        }

        return ConfigMerger::merge(...$sources);
    }

}
