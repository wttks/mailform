<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Validation\Rule\Validate\ValidateImage;
use PHPUnit\Framework\TestCase;

/**
 * ValidateImage のテスト
 * UploadFile 以外の値は常に true（check でそのように実装されている）
 * UploadFile の場合は getMimeType() が "image/" で始まる場合のみ true
 */
class ValidateImageTest extends TestCase {

    private ValidateImage $rule;

    protected function setUp(): void {
        $this->rule = new ValidateImage();
    }

    private function createUploadFileMock(string $mimeType, int $size = 1000): UploadFile {
        $mock = $this->createMock(UploadFile::class);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getSize')->willReturn($size);
        $mock->method('exists')->willReturn($size > 0);
        return $mock;
    }

    // ---- UploadFile 正常系 ----

    public function test_image_pngのファイルはtrueを返す(): void {
        $file = $this->createUploadFileMock('image/png');
        $this->assertTrue($this->rule->validate($file));
    }

    public function test_image_jpegのファイルはtrueを返す(): void {
        $file = $this->createUploadFileMock('image/jpeg');
        $this->assertTrue($this->rule->validate($file));
    }

    public function test_image_gifのファイルはtrueを返す(): void {
        $file = $this->createUploadFileMock('image/gif');
        $this->assertTrue($this->rule->validate($file));
    }

    // ---- UploadFile 異常系 ----

    public function test_PDFファイルはfalseを返す(): void {
        $file = $this->createUploadFileMock('application/pdf');
        $this->assertFalse($this->rule->validate($file));
    }

    public function test_textファイルはfalseを返す(): void {
        $file = $this->createUploadFileMock('text/plain');
        $this->assertFalse($this->rule->validate($file));
    }

    // ---- UploadFile 以外は常に true ----

    public function test_文字列はtrueを返す(): void {
        // check() で UploadFile でなければ true を返す
        $this->assertTrue($this->rule->validate('some_value'));
    }

    public function test_整数はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(123));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- ファイルが存在しない場合はスキップ ----

    public function test_UploadFileが存在しない場合はスキップでtrueを返す(): void {
        $file = $this->createUploadFileMock('', 0);
        $this->assertTrue($this->rule->validate($file));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}
