<?php

namespace AIJOH\Test\Form;

use AIJOH\Form\Form;
use PHPUnit\Framework\TestCase;

/**
 * Form::flattenErrors() のテスト
 * abortWithErrors() の中身の検証ロジック（exit するため直接テストしづらい）。
 */
class FormFlattenErrorsTest extends TestCase {

    public function test_文字列値はそのまま(): void {
        $result = Form::flattenErrors([
            'name'  => 'お名前は必須です。',
            'email' => 'メールアドレスを入力してください。',
        ]);
        $this->assertSame([
            'name'  => 'お名前は必須です。',
            'email' => 'メールアドレスを入力してください。',
        ], $result);
    }

    public function test_配列値は改行連結(): void {
        $result = Form::flattenErrors([
            'datetime' => ['営業時間外です。', '前日 17:00 までの予約が必要です。'],
        ]);
        $this->assertSame([
            'datetime' => "営業時間外です。\n前日 17:00 までの予約が必要です。",
        ], $result);
    }

    public function test_文字列と配列が混在しても変換できる(): void {
        $result = Form::flattenErrors([
            'name'     => '必須です。',
            'datetime' => ['営業時間外です。', '休業日です。'],
        ]);
        $this->assertSame([
            'name'     => '必須です。',
            'datetime' => "営業時間外です。\n休業日です。",
        ], $result);
    }

    public function test_空配列は空文字に変換される(): void {
        $result = Form::flattenErrors([
            'foo' => [],
        ]);
        $this->assertSame(['foo' => ''], $result);
    }

    public function test_数値値も文字列化される(): void {
        $result = Form::flattenErrors([
            'count' => [1, 2, 3],
        ]);
        $this->assertSame(['count' => "1\n2\n3"], $result);
    }
}
