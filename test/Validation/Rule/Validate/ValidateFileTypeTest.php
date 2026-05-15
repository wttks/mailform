<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Rule\Validate\ValidateFileType;
use PHPUnit\Framework\TestCase;

class ValidateFileTypeTest extends TestCase {

    private ValidateFileType $rule;
    /** @var string[] tearDown で消す一時ファイル */
    private array $tmpFiles = [];

    protected function setUp() : void {
        $this->rule = new ValidateFileType();
    }

    protected function tearDown() : void {
        foreach ( $this->tmpFiles as $f ) { @unlink($f); }
        $this->tmpFiles = [];
    }

    /**
     * MIMEタイプと拡張子を持つ UploadFile モックを作成する。
     * exists() は true を返すようにしてバリデーションがスキップされないようにする。
     * tmp ファイル無しのモックは magic bytes 検証をスキップして拡張子 + MIME で判断。
     */
    private function makeFile( string $mimeType, string $extension ) : UploadFile {
        $mock = $this->createStub(UploadFile::class);
        $mock->method('exists')->willReturn(true);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getExtension')->willReturn($extension);
        $mock->method('getTmpName')->willReturn('');
        return $mock;
    }


    /**
     * 実ファイル付きの UploadFile モックを作成する ( 3 段検証用 )
     */
    private function makeRealFile( string $content, string $mimeType, string $extension ) : UploadFile {
        $tmpPath = tempnam(sys_get_temp_dir(), 'mfu_ft_');
        file_put_contents($tmpPath, $content);
        $this->tmpFiles[] = $tmpPath;

        $mock = $this->createStub(UploadFile::class);
        $mock->method('exists')->willReturn(true);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getExtension')->willReturn($extension);
        $mock->method('getTmpName')->willReturn($tmpPath);
        return $mock;
    }

    // ==============================
    // UploadFile 以外の値
    // ==============================

    public function test_文字列はfalseを返す() : void {
        $this->assertFalse($this->rule->validate('not_a_file', ['pdf']));
    }

    public function test_数値はfalseを返す() : void {
        $this->assertFalse($this->rule->validate(123, ['pdf']));
    }

    public function test_nullは未入力扱いでtrueを返す() : void {
        // 基底クラスの仕様: 非必須フィールドの null は空値スキップでtrue
        $this->assertTrue($this->rule->validate(null, ['pdf']));
    }

    // ==============================
    // エイリアス未指定・未定義
    // ==============================

    public function test_エイリアス未指定は例外を投げる() : void {
        $this->expectException(ValidationRuleException::class);
        $file = $this->makeFile('application/pdf', 'pdf');
        $this->rule->validate($file, []);
    }

    public function test_未定義エイリアスは例外を投げる() : void {
        $this->expectException(ValidationRuleException::class);
        $file = $this->makeFile('application/pdf', 'pdf');
        $this->rule->validate($file, ['unknown_alias']);
    }

    // ==============================
    // pdf
    // ==============================

    public function test_pdfファイルはtrueを返す() : void {
        $file = $this->makeFile('application/pdf', 'pdf');
        $this->assertTrue($this->rule->validate($file, ['pdf']));
    }

    public function test_MIMEがpdfでも拡張子が違えばfalseを返す() : void {
        $file = $this->makeFile('application/pdf', 'txt');
        $this->assertFalse($this->rule->validate($file, ['pdf']));
    }

    public function test_拡張子がpdfでもMIMEが違えばfalseを返す() : void {
        $file = $this->makeFile('text/plain', 'pdf');
        $this->assertFalse($this->rule->validate($file, ['pdf']));
    }

    // ==============================
    // word
    // ==============================

    public function test_docファイルはtrueを返す() : void {
        $file = $this->makeFile('application/msword', 'doc');
        $this->assertTrue($this->rule->validate($file, ['word']));
    }

    public function test_docxファイルはtrueを返す() : void {
        $file = $this->makeFile('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx');
        $this->assertTrue($this->rule->validate($file, ['word']));
    }

    // ==============================
    // excel
    // ==============================

    public function test_xlsファイルはtrueを返す() : void {
        $file = $this->makeFile('application/vnd.ms-excel', 'xls');
        $this->assertTrue($this->rule->validate($file, ['excel']));
    }

    public function test_xlsxファイルはtrueを返す() : void {
        $file = $this->makeFile('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx');
        $this->assertTrue($this->rule->validate($file, ['excel']));
    }

    // ==============================
    // image（ワイルドカード）
    // ==============================

    public function test_jpegファイルはtrueを返す() : void {
        $file = $this->makeFile('image/jpeg', 'jpg');
        $this->assertTrue($this->rule->validate($file, ['image']));
    }

    public function test_pngファイルはtrueを返す() : void {
        $file = $this->makeFile('image/png', 'png');
        $this->assertTrue($this->rule->validate($file, ['image']));
    }

    public function test_webpファイルはtrueを返す() : void {
        $file = $this->makeFile('image/webp', 'webp');
        $this->assertTrue($this->rule->validate($file, ['image']));
    }

    public function test_拡張子が大文字でもtrueを返す() : void {
        $file = $this->makeFile('image/jpeg', 'JPG');
        $this->assertTrue($this->rule->validate($file, ['image']));
    }

    public function test_image_エイリアスに_svg_は含まれない_XSS対策() : void {
        $file = $this->makeFile('image/svg+xml', 'svg');
        $this->assertFalse($this->rule->validate($file, ['image']));
    }

    public function test_svg_エイリアスを明示指定すれば_svg_は許可される() : void {
        $file = $this->makeFile('image/svg+xml', 'svg');
        $this->assertTrue($this->rule->validate($file, ['svg']));
    }

    // ==============================
    // 複数エイリアス指定
    // ==============================

    public function test_複数エイリアスのいずれかに一致すればtrueを返す() : void {
        $file = $this->makeFile('application/pdf', 'pdf');
        $this->assertTrue($this->rule->validate($file, ['pdf', 'word']));
    }

    public function test_複数エイリアス全てに一致しない場合falseを返す() : void {
        $file = $this->makeFile('text/plain', 'txt');
        $this->assertFalse($this->rule->validate($file, ['pdf', 'word']));
    }

    // ==============================
    // エイリアスの追加・上書き
    // ==============================

    public function test_addAliasで新しいエイリアスを追加できる() : void {
        ValidateFileType::addAlias('custom', ['application/x-custom'], ['cst']);
        $file = $this->makeFile('application/x-custom', 'cst');
        $this->assertTrue($this->rule->validate($file, ['custom']));
    }

    public function test_addAliasesで複数エイリアスをまとめて追加できる() : void {
        ValidateFileType::addAliases([
            'csv' => ['mime' => ['text/csv'], 'ext' => ['csv']],
        ]);
        $file = $this->makeFile('text/csv', 'csv');
        $this->assertTrue($this->rule->validate($file, ['csv']));
    }

    public function test_addAliasで既存エイリアスを上書きできる() : void {
        // pdf の拡張子を 'pdf2' に上書き
        ValidateFileType::addAlias('pdf_custom', ['application/pdf'], ['pdf2']);
        $file = $this->makeFile('application/pdf', 'pdf2');
        $this->assertTrue($this->rule->validate($file, ['pdf_custom']));
    }

    // ==============================
    // エラーメッセージ
    // ==============================

    public function test_getErrorMessageが正しい形式を返す() : void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
        $this->assertStringContainsString(':types', $this->rule->getErrorMessage());
    }

    public function test_formatMessageArgsがエイリアス名を返す() : void {
        $args = $this->rule->formatMessageArgs(['pdf', 'word']);
        $this->assertArrayHasKey('types', $args);
        $this->assertStringContainsString('pdf', $args['types']);
        $this->assertStringContainsString('word', $args['types']);
    }


    // ==============================
    // 3 段検証 ( magic bytes ) - polyglot 対策
    // ==============================

    public function test_実ファイル_PNG_は3段すべて通過() : void {
        $pngContent = "\x89PNG\r\n\x1A\n" . str_repeat("\x00", 100);
        $file = $this->makeRealFile($pngContent, 'image/png', 'png');
        $this->assertTrue($this->rule->validate($file, ['image']));
    }


    public function test_polyglot_拡張子pngでfinfo_image_png_だが実体PDFは3段目で弾く() : void {
        // 攻撃シナリオ: 拡張子 .png + finfo の戻りも image/png に擬装したが、
        // 実体は PDF ヘッダで始まるファイル
        $pdfContent = "%PDF-1.4\n" . str_repeat("x", 100);
        $file = $this->makeRealFile($pdfContent, 'image/png', 'png');
        $this->assertFalse($this->rule->validate($file, ['image']),
            'polyglot は 3 段目の magic bytes 検証で弾かれること');
    }


    public function test_polyglot_拡張子pdfでfinfo_application_pdf_だが実体PNGも弾く() : void {
        $pngContent = "\x89PNG\r\n\x1A\n" . str_repeat("\x00", 100);
        $file = $this->makeRealFile($pngContent, 'application/pdf', 'pdf');
        $this->assertFalse($this->rule->validate($file, ['pdf']));
    }


    public function test_tmpファイル無しは_拡張子_finfo_MIMEのみで判断() : void {
        // 確認画面復元等で tmp が存在しないケースは従来通り 2 段検証
        $file = $this->makeFile('image/png', 'png');
        $this->assertTrue($this->rule->validate($file, ['image']));
    }
}
