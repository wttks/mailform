<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Rule\Validate\ValidateMime;
use PHPUnit\Framework\TestCase;

/**
 * ValidateMime のテスト
 * UploadFile::getMimeType() を使用してMIMEタイプをチェック
 * fnmatch を使用するためワイルドカード (* ) も使用可能
 */
class ValidateMimeTest extends TestCase {

    private ValidateMime $rule;
    /** @var string[] tearDown で消す一時ファイル */
    private array $tmpFiles = [];

    protected function setUp(): void {
        $this->rule = new ValidateMime();
    }

    protected function tearDown(): void {
        foreach ( $this->tmpFiles as $f ) { @unlink($f); }
        $this->tmpFiles = [];
    }

    /**
     * UploadFile のモックを作成する
     * UploadFile は $_FILES から読み込むため、モックを使用する
     */
    private function createUploadFileMock(string $mimeType, int $size = 1000): UploadFile {
        $mock = $this->createStub(UploadFile::class);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getSize')->willReturn($size);
        $mock->method('exists')->willReturn($size > 0);
        $mock->method('getTmpName')->willReturn('');  // magic bytes 検証はスキップされる
        return $mock;
    }

    /**
     * 実ファイル付きの UploadFile モックを作成する ( magic bytes 検証用 )
     */
    private function createRealUploadFileMock(string $content, string $mimeType): UploadFile {
        $tmpPath = tempnam(sys_get_temp_dir(), 'mfu_');
        file_put_contents($tmpPath, $content);
        $this->tmpFiles[] = $tmpPath;

        $mock = $this->createStub(UploadFile::class);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getSize')->willReturn(strlen($content));
        $mock->method('exists')->willReturn(strlen($content) > 0);
        $mock->method('getTmpName')->willReturn($tmpPath);
        return $mock;
    }

    // ---- 正常系 ----

    public function test_MIMEタイプが一致する場合はtrueを返す(): void {
        $file = $this->createUploadFileMock('image/png');
        $this->assertTrue($this->rule->validate($file, ['image/png']));
    }

    public function test_MIMEタイプが複数指定の場合にいずれかに一致すればtrueを返す(): void {
        $file = $this->createUploadFileMock('image/jpeg');
        $this->assertTrue($this->rule->validate($file, ['image/png', 'image/jpeg']));
    }

    public function test_ワイルドカードでimage_全般が一致する場合はtrueを返す(): void {
        $file = $this->createUploadFileMock('image/gif');
        $this->assertTrue($this->rule->validate($file, ['image/*']));
    }

    public function test_PDFのMIMEタイプチェック(): void {
        $file = $this->createUploadFileMock('application/pdf');
        $this->assertTrue($this->rule->validate($file, ['application/pdf']));
    }

    // ---- 空値スキップ（UploadFile が存在しない場合）----

    public function test_UploadFileが存在しない場合はスキップでtrueを返す(): void {
        // isRequiredCheck=false かつ UploadFile::exists() が false の場合スキップ
        $file = $this->createUploadFileMock('', 0);
        $this->assertTrue($this->rule->validate($file, ['image/png']));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null, ['image/png']));
    }

    // ---- 異常系 ----

    public function test_MIMEタイプが不一致の場合はfalseを返す(): void {
        $file = $this->createUploadFileMock('application/pdf');
        $this->assertFalse($this->rule->validate($file, ['image/png']));
    }

    public function test_argsが空の場合は例外を投げる(): void {
        $file = $this->createUploadFileMock('image/png');
        $this->expectException(ValidationRuleException::class);
        $this->rule->validate($file, []);
    }

    public function test_UploadFileでない値はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('image/png', ['image/png']));
    }

    // ---- magic bytes 検証 ----

    public function test_確定MIME指定で_magic_bytesが一致すればtrue(): void {
        $pngContent = "\x89PNG\r\n\x1A\n" . str_repeat("\x00", 100);
        $file = $this->createRealUploadFileMock($pngContent, 'image/png');
        $this->assertTrue($this->rule->validate($file, ['image/png']));
    }

    public function test_確定MIME指定で_magic_bytes不一致ならfalse_polyglot対策(): void {
        // finfo MIME は image/png だが、実体は PDF ヘッダ
        $pdfContent = "%PDF-1.4\n" . str_repeat("x", 100);
        $file = $this->createRealUploadFileMock($pdfContent, 'image/png');
        $this->assertFalse($this->rule->validate($file, ['image/png']),
            'finfo MIME 偽装/polyglot は magic bytes で弾かれること');
    }

    public function test_ワイルドカードMIME指定では_実MIMEで_magic_bytesを確認(): void {
        $pngContent = "\x89PNG\r\n\x1A\n" . str_repeat("\x00", 100);
        $file = $this->createRealUploadFileMock($pngContent, 'image/png');
        $this->assertTrue($this->rule->validate($file, ['image/*']));
    }

    public function test_ワイルドカードMIME_実MIMEで_magic_bytes不一致ならfalse(): void {
        // finfo MIME は image/png と返るが、実体は PNG 以外のバイト
        $invalidContent = "INVALID_HEADER" . str_repeat("\x00", 100);
        $file = $this->createRealUploadFileMock($invalidContent, 'image/png');
        $this->assertFalse($this->rule->validate($file, ['image/*']));
    }

    public function test_signatureテーブル外のMIMEは_magic_bytes検証スキップ(): void {
        // text/plain は signature テーブルで空配列 ( = 検証スキップ )
        $file = $this->createRealUploadFileMock("hello world", 'text/plain');
        $this->assertTrue($this->rule->validate($file, ['text/plain']));
    }


    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':title', $msg);
        $this->assertStringContainsString(':types', $msg);
    }
}
