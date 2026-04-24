<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Validation\Rule\Validate\ValidateImageMax;
use PHPUnit\Framework\TestCase;

/**
 * ValidateImageMax のテスト
 *
 * 注意: ValidateImageMax の check() の実装は ValidateImage と同一で、
 * 画像サイズ（幅・高さ）の最大値チェックを行っていない。
 * クラス名や getErrorMessage からは「画像最大サイズのチェック」であることが示唆されるが、
 * 実際には getMimeType() が "image/" で始まるかだけを確認する実装になっている。
 * これはおそらく実装が未完成のバグ。テストは現在の実装に沿って記載し、
 * 本来の仕様との乖離をコメントで記録する。
 */
class ValidateImageMaxTest extends TestCase {

    private ValidateImageMax $rule;

    protected function setUp(): void {
        $this->rule = new ValidateImageMax();
    }

    private function createUploadFileMock(string $mimeType, int $size = 1000): UploadFile {
        $mock = $this->createMock(UploadFile::class);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getSize')->willReturn($size);
        $mock->method('exists')->willReturn($size > 0);
        return $mock;
    }

    // ---- 現在の実装（ValidateImage と同一）に基づくテスト ----

    public function test_image_pngファイルはtrueを返す(): void {
        $file = $this->createUploadFileMock('image/png');
        $this->assertTrue($this->rule->validate($file));
    }

    public function test_image_jpegファイルはtrueを返す(): void {
        $file = $this->createUploadFileMock('image/jpeg');
        $this->assertTrue($this->rule->validate($file));
    }

    /**
     * 現在の実装では image/ で始まらない MIME タイプは false を返す。
     * 本来は画像サイズ（幅・高さ）の上限チェックを行うはずだが、
     * 実装が未完成のため MIME タイプチェックのみ行われている。
     */
    public function test_PDFファイルはfalseを返す(): void {
        $file = $this->createUploadFileMock('application/pdf');
        $this->assertFalse($this->rule->validate($file));
    }

    public function test_UploadFile以外はtrueを返す(): void {
        // check() で UploadFile でなければ true を返す（ValidateImage と同実装）
        $this->assertTrue($this->rule->validate('not-a-file'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_UploadFileが存在しない場合はスキップでtrueを返す(): void {
        $file = $this->createUploadFileMock('', 0);
        $this->assertTrue($this->rule->validate($file));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}
