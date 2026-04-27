<?php

namespace AIJOH\Test\Lang;

use AIJOH\Lang\Translator;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Validation;
use PHPUnit\Framework\TestCase;

/**
 * Translator と Validation の統合テスト。
 * locale を切り替えるとバリデーションエラー文が翻訳されることを確認する。
 */
class TranslatorIntegrationTest extends TestCase {

    protected function setUp() : void {
        Translator::reset();
    }

    protected function tearDown() : void {
        Translator::reset();
    }


    public function test_locale_ja_では日本語のエラーメッセージ(): void {
        $validation = new Validation([
            'name' => ['title' => 'Name', 'rule' => 'required'],
        ]);
        try {
            $validation->validated([]);
            $this->fail('ValidationException が投げられるべき');
        } catch ( ValidationException $e ) {
            $errors = $e->getErrors();
            $this->assertSame('Nameは必須項目です。', $errors['name']);
        }
    }


    public function test_locale_en_では英語に翻訳される(): void {
        Translator::setLocale('en');
        $validation = new Validation([
            'name' => ['title' => 'Name', 'rule' => 'required'],
        ]);
        try {
            $validation->validated([]);
            $this->fail('ValidationException が投げられるべき');
        } catch ( ValidationException $e ) {
            $errors = $e->getErrors();
            $this->assertSame('Name is required.', $errors['name']);
        }
    }


    public function test_カスタムメッセージは翻訳されず優先される(): void {
        Translator::setLocale('en');
        $validation = new Validation([
            'name' => [
                'title'   => 'Name',
                'rule'    => 'required',
                'message' => ['required' => 'カスタムメッセージ'],
            ],
        ]);
        try {
            $validation->validated([]);
            $this->fail('ValidationException が投げられるべき');
        } catch ( ValidationException $e ) {
            $errors = $e->getErrors();
            $this->assertSame('カスタムメッセージ', $errors['name']);
        }
    }
}
