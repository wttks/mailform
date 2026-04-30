<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\FileUtil;
use PHPUnit\Framework\TestCase;

/**
 * FileUtil のテスト
 * 実際のファイルを一時ディレクトリに作成してテストする
 */
class FileUtilTest extends TestCase {

    /** @var string テスト用一時ディレクトリ */
    private string $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/aijoh_fileutil_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void {
        // テスト後に一時ディレクトリを削除する
        $files = glob($this->tempDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    /**
     * 1x1ピクセルのPNG画像（最小バイナリ）を生成する
     */
    private function createPngFile(string $filename): string {
        $path = $this->tempDir . '/' . $filename;
        // 1x1 白ピクセルのPNG
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQI12P4z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg=='
        );
        file_put_contents($path, $png);
        return $path;
    }

    /**
     * テキストファイルを作成する
     */
    private function createTextFile(string $filename): string {
        $path = $this->tempDir . '/' . $filename;
        file_put_contents($path, 'hello world');
        return $path;
    }

    // ---- isImageFile() ----

    public function test_isImageFile_PNG画像はtrue(): void {
        $path = $this->createPngFile('test.png');
        $this->assertTrue(FileUtil::isImageFile($path));
    }

    public function test_isImageFile_テキストファイルはfalse(): void {
        $path = $this->createTextFile('test.txt');
        $this->assertFalse(FileUtil::isImageFile($path));
    }

    public function test_isImageFile_存在しないファイルはfalse(): void {
        $this->assertFalse(FileUtil::isImageFile('/nonexistent/path/file.png'));
    }

    // ---- isPDFFile() ----

    public function test_isPDFFile_存在しないファイルはfalse(): void {
        $this->assertFalse(FileUtil::isPDFFile('/nonexistent/path/file.pdf'));
    }

    public function test_isPDFFile_テキストファイルはfalse(): void {
        $path = $this->createTextFile('test.txt');
        $this->assertFalse(FileUtil::isPDFFile($path));
    }

    public function test_isPDFFile_PDFファイルはtrue(): void {
        // 最小限のPDFバイナリヘッダを持つファイルを作成する
        $path = $this->tempDir . '/test.pdf';
        // PDFマジックバイトで始まるファイル
        file_put_contents($path, "%PDF-1.0\n1 0 obj<</Type /Catalog/Pages 2 0 R>>endobj\n");
        $this->assertTrue(FileUtil::isPDFFile($path));
    }

    // ---- isExcelFile() ----

    public function test_isExcelFile_存在しないファイルはfalse(): void {
        $this->assertFalse(FileUtil::isExcelFile('/nonexistent/path/file.xlsx'));
    }

    public function test_isExcelFile_テキストファイルはfalse(): void {
        $path = $this->createTextFile('test.txt');
        $this->assertFalse(FileUtil::isExcelFile($path));
    }


    // ---- getMimeType (finfo ベース) ----

    public function test_getMimeType_PNG画像_image_png(): void {
        $path = $this->createPngFile('test.png');
        $this->assertSame('image/png', FileUtil::getMimeType($path));
    }


    public function test_getMimeType_テキスト_text_plain(): void {
        $path = $this->createTextFile('test.txt');
        $this->assertStringStartsWith('text/', FileUtil::getMimeType($path));
    }


    public function test_getMimeType_存在しないファイル_空文字(): void {
        $this->assertSame('', FileUtil::getMimeType('/nonexistent/path'));
    }


    // ---- matchesMagicBytes ----

    public function test_matchesMagicBytes_PNG画像_PNG_signature_に一致(): void {
        $path = $this->createPngFile('test.png');
        $this->assertTrue(FileUtil::matchesMagicBytes($path, 'image/png'));
    }


    public function test_matchesMagicBytes_PNG画像_JPEG_signature_には不一致(): void {
        $path = $this->createPngFile('test.png');
        $this->assertFalse(FileUtil::matchesMagicBytes($path, 'image/jpeg'));
    }


    public function test_matchesMagicBytes_PDF_先頭_PDF_signature_に一致(): void {
        $path = $this->tempDir . '/test.pdf';
        file_put_contents($path, "%PDF-1.0\nbody");
        $this->assertTrue(FileUtil::matchesMagicBytes($path, 'application/pdf'));
    }


    public function test_matchesMagicBytes_テーブル外の_MIME_は通過扱い(): void {
        $path = $this->createTextFile('test.txt');
        $this->assertTrue(FileUtil::matchesMagicBytes($path, 'application/x-unknown'));
    }


    public function test_matchesMagicBytes_存在しないファイルはfalse(): void {
        $this->assertFalse(FileUtil::matchesMagicBytes('/nonexistent', 'image/png'));
    }


    // ---- isSafeFile (3 段検証) ----

    public function test_isSafeFile_PNG_拡張子_MIME_signature_すべて一致なら_true(): void {
        $path = $this->createPngFile('test.png');
        $this->assertTrue(FileUtil::isSafeFile($path, 'png', 'image/png'));
    }


    public function test_isSafeFile_拡張子不一致は_false(): void {
        $path = $this->createPngFile('test.png');
        $this->assertFalse(FileUtil::isSafeFile($path, 'jpg', 'image/jpeg'));
    }


    public function test_isSafeFile_polyglot_PNG_拡張子だが_中身は_PHP_は_false(): void {
        // 攻撃シナリオ: 拡張子は .png だが実体は <?php
        $path = $this->tempDir . '/evil.png';
        file_put_contents($path, "<?php phpinfo(); ?>");
        // finfo は text/x-php 等を返すはずで MIME 不一致 → false
        $this->assertFalse(FileUtil::isSafeFile($path, 'png', 'image/png'));
    }


    public function test_isSafeFile_polyglot_PNG_拡張子に_ZIP_signature_は_false(): void {
        // 攻撃シナリオ: PNG 拡張子 + ZIP マジック
        $path = $this->tempDir . '/evil.png';
        file_put_contents($path, "PK\x03\x04rest_of_zip");
        // finfo は application/zip 等を返すはずで MIME 不一致 → false
        $this->assertFalse(FileUtil::isSafeFile($path, 'png', 'image/png'));
    }
}
