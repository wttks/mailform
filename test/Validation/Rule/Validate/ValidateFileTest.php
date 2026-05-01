<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Validation\Rule\Validate\ValidateFile;
use PHPUnit\Framework\TestCase;

/**
 * ValidateFile のテスト
 * UploadFile::exists() が true であれば OK
 * isRequiredCheck=false のため空値（UploadFile::exists()=false）はスキップ
 */
class ValidateFileTest extends TestCase {

    private ValidateFile $rule;

    protected function setUp(): void {
        $this->rule = new ValidateFile();
    }

    private function createUploadFileMock(bool $exists, int $size = 1000): UploadFile {
        $mock = $this->createStub(UploadFile::class);
        $mock->method('exists')->willReturn($exists);
        $mock->method('getSize')->willReturn($exists ? $size : 0);
        return $mock;
    }

    // ---- 正常系 ----

    public function test_ファイルが存在する場合はtrueを返す(): void {
        $file = $this->createUploadFileMock(true);
        $this->assertTrue($this->rule->validate($file));
    }

    // ---- ファイルが存在しない場合はスキップ（isRequiredCheck=false）----

    public function test_ファイルが存在しない場合はスキップでtrueを返す(): void {
        // ValidateBase の validate() で UploadFile::exists()=false の場合はスキップ
        $file = $this->createUploadFileMock(false);
        $this->assertTrue($this->rule->validate($file));
    }

    // ---- 異常系 ----

    public function test_UploadFileでない文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('some_file.txt'));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['file']));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}
