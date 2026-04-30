<?php

namespace AIJOH\Hook;

/**
 * plugins/*.php ディレクトリから MailformPlugin を発見・登録するローダー。
 *
 * ファイル規約:
 * - `*.php` を昇順でロード（`01_xxx.php`, `02_yyy.php` で順序制御）
 * - ファイル名 `_disabled_*.php` はスキップ
 * - 各ファイルは `return new class implements MailformPlugin { ... };` を返す
 * - 戻り値が MailformPlugin でない場合は WARN ログ + スキップ
 *
 * Form::__construct から呼ばれ、HookRegistry に Hook を登録していく。
 */
final class PluginLoader {

    /** @var string mailform 同梱のデフォルト plugin ディレクトリ */
    public const DEFAULT_DIR = __DIR__ . '/../../config/plugins';


    /**
     * 指定ディレクトリ群を走査して plugin を HookRegistry にロードする。
     *
     * @param HookRegistry $registry ロード先のレジストリ
     * @param string[] $extraDirs 追加で探索するディレクトリ（設置者の plugin_dirs 設定）
     * @return int ロードされた plugin 数
     */
    public static function loadInto( HookRegistry $registry, array $extraDirs = [] ) : int {
        $dirs = array_merge([ self::DEFAULT_DIR ], $extraDirs);
        $loaded = 0;
        foreach ( $dirs as $dir ) {
            if ( ! is_string($dir) || $dir === '' || ! is_dir($dir) ) {
                continue;
            }
            $loaded += self::loadFromDir($registry, $dir);
        }
        return $loaded;
    }


    /**
     * 1 つのディレクトリから plugin をロードする。
     */
    private static function loadFromDir( HookRegistry $registry, string $dir ) : int {
        $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
        sort($files);   // 昇順でロード（01_xxx.php → 02_yyy.php の順序）

        $loaded = 0;
        foreach ( $files as $file ) {
            $basename = basename($file);
            if ( str_starts_with($basename, '_disabled_') ) {
                continue;
            }
            try {
                $plugin = require $file;
            } catch ( \Throwable $e ) {
                error_log("[PluginLoader] failed to require '{$file}': " . $e->getMessage());
                continue;
            }
            if ( ! ( $plugin instanceof MailformPlugin ) ) {
                error_log("[PluginLoader] '{$file}' did not return a MailformPlugin instance");
                continue;
            }
            try {
                $plugin->register($registry);
                $loaded++;
            } catch ( \Throwable $e ) {
                error_log("[PluginLoader] '{$file}' register() failed: " . $e->getMessage());
            }
        }
        return $loaded;
    }
}
