<?php
namespace AIJOH\Util;


class FileUtil {

    /**
     * 主要 MIME の magic bytes (signature) テーブル。
     * 各 MIME ごとに、その MIME を示す先頭バイト列のリスト。
     *
     * polyglot 攻撃 (PNG 拡張子で実体は PHP / ZIP 等) を検出するため、
     * 拡張子 / finfo MIME / magic bytes の 3 段検証で使う。
     *
     * 参考: https://en.wikipedia.org/wiki/List_of_file_signatures
     */
    public const MIME_SIGNATURES = [
        'image/jpeg'      => [ "\xFF\xD8\xFF" ],
        'image/png'       => [ "\x89PNG\r\n\x1A\n" ],
        'image/gif'       => [ "GIF87a", "GIF89a" ],
        'image/bmp'       => [ "BM" ],
        'image/webp'      => [ "RIFF" ],   // 後ろに WEBP も検証推奨
        'image/svg+xml'   => [ "<?xml", "<svg" ],
        'application/pdf' => [ "%PDF-" ],
        // Office 系（zip ベース）
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [ "PK\x03\x04" ],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [ "PK\x03\x04" ],
        // 古い Office 形式
        'application/msword' => [ "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ],
        'application/vnd.ms-excel' => [ "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ],
        // テキスト系
        'text/plain'      => [],   // signature なし、検証スキップ
        'text/csv'        => [],
    ];


    /**
     * ファイルの先頭バイトを読んで MIME signature と一致するかチェックする。
     *
     * @param string $path ファイルパス
     * @param string $expectedMime 期待される MIME タイプ
     * @return bool 一致すれば true、テーブル外の MIME なら true（スキップ扱い）
     */
    public static function matchesMagicBytes( string $path, string $expectedMime ) : bool {
        if ( ! is_file($path) ) {
            return false;
        }
        $signatures = self::MIME_SIGNATURES[ $expectedMime ] ?? null;
        if ( $signatures === null ) {
            // テーブル外の MIME は magic bytes 検証スキップ
            error_log("[FileUtil] no signature for MIME: {$expectedMime}");
            return true;
        }
        if ( empty($signatures) ) {
            // 空配列は「signature 検証なし」扱い（text/plain 等）
            return true;
        }
        $header = @file_get_contents($path, false, null, 0, 16);
        if ( $header === false ) {
            return false;
        }
        foreach ( $signatures as $sig ) {
            if ( str_starts_with($header, $sig) ) {
                return true;
            }
        }
        return false;
    }


    /**
     * ファイルの実 MIME を finfo で取得する（mime_content_type の代替）。
     * @return string MIME 文字列、取得失敗時は空文字列
     */
    public static function getMimeType( string $path ) : string {
        if ( ! is_file($path) ) {
            return '';
        }
        // PHP 8.5+ では finfo オブジェクトは自動解放される。finfo_close は不要。
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ( $finfo === false ) {
            // finfo 利用不可の環境では mime_content_type にフォールバック
            $type = @mime_content_type($path);
            return is_string($type) ? $type : '';
        }
        $type = @finfo_file($finfo, $path);
        return is_string($type) ? $type : '';
    }


    /**
     * 拡張子・finfo MIME・magic bytes の 3 段検証で安全な MIME であるか判定する。
     *
     * @param string $path ファイルパス
     * @param string $expectedExtension 期待される拡張子（'pdf' / 'png' 等。ドット不要）
     * @param string $expectedMime 期待される MIME タイプ
     * @return bool 3 段すべて一致すれば true
     */
    public static function isSafeFile( string $path, string $expectedExtension, string $expectedMime ) : bool {
        if ( ! is_file($path) ) {
            return false;
        }
        // 1. 拡張子チェック
        $actualExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ( $actualExt !== strtolower($expectedExtension) ) {
            return false;
        }
        // 2. finfo MIME チェック
        $actualMime = self::getMimeType($path);
        if ( $actualMime !== $expectedMime ) {
            // SVG 等で text/xml が返ることがあるので、関連 MIME も許容
            if ( ! self::isMimeAlias($actualMime, $expectedMime) ) {
                return false;
            }
        }
        // 3. magic bytes チェック
        return self::matchesMagicBytes($path, $expectedMime);
    }


    /**
     * MIME タイプ間のエイリアス判定（finfo の戻り値が期待値と微妙に違う場合の救済）。
     */
    private static function isMimeAlias( string $actual, string $expected ) : bool {
        $aliases = [
            'image/svg+xml' => [ 'text/xml', 'application/xml', 'text/html' ],
            'text/csv'      => [ 'text/plain' ],
        ];
        return in_array($actual, $aliases[ $expected ] ?? [], true);
    }


    /**
     * 指定したファイルのパスが画像ファイルかどうかチェックを行う（finfo ベース）。
     * @param string $path チェックを行うファイルのパス
     * @return bool ファイルが画像ファイルの場合はtrueを返す。
     */
    public static function isImageFile( string $path ) : bool {
        $mime = self::getMimeType($path);
        return $mime !== '' && str_starts_with($mime, 'image/');
    }


    /**
     * 指定したファイルのパスがPDFファイルかどうかチェックを行う（finfo + magic bytes）。
     */
    public static function isPDFFile( string $path ) : bool {
        if ( self::getMimeType($path) !== 'application/pdf' ) {
            return false;
        }
        return self::matchesMagicBytes($path, 'application/pdf');
    }


    /**
     * 指定したファイルのパスがExcelファイル (.xlsx) かどうかチェックを行う。
     */
    public static function isExcelFile( string $path ) : bool {
        return self::getMimeType($path)
            === "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
    }

}
