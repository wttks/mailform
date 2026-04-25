<?php

namespace AIJOH\Test\Results\Formatter;

use AIJOH\Output\Mailer\MailAddress;
use AIJOH\Results\FormData;
use AIJOH\Results\Formatter\Formatter;
use PHPUnit\Framework\TestCase;

/**
 * Formatter::formatAll の動的解決機能（Closure / map）のテスト。
 */
class FormatterTest extends TestCase {

    private function makeFormatter( array $data ) : Formatter {
        $formData = new FormData();
        $formData->setData($data);
        return new Formatter($formData);
    }

    // ---- 既存挙動 ----

    public function test_文字列の_placeholder_展開(): void {
        $f = $this->makeFormatter(['name' => '山田', 'email' => 'a@x']);
        $result = $f->formatAll([
            'subject' => '{name}様より',
            'to'      => '{email}',
        ]);
        $this->assertSame(['subject' => '山田様より', 'to' => 'a@x'], $result);
    }

    public function test_配列はそのまま再帰展開(): void {
        $f = $this->makeFormatter(['email' => 'a@x', 'name' => '山田']);
        $result = $f->formatAll([
            'replyTo' => [['{email}', '{name}様']],
        ]);
        $this->assertSame([
            'replyTo' => [['a@x', '山田様']],
        ], $result);
    }

    // ---- Closure ----

    public function test_Closure_は_form_data_を受け取って結果を返す(): void {
        $f = $this->makeFormatter(['type' => 'urgent']);
        $result = $f->formatAll([
            'to' => fn(array $data) => $data['type'] === 'urgent' ? 'urgent@x' : 'info@x',
        ]);
        $this->assertSame(['to' => 'urgent@x'], $result);
    }

    public function test_Closure_の戻り値が文字列なら_placeholder_展開も走る(): void {
        $f = $this->makeFormatter(['type' => 'a', 'email' => 'a@x']);
        $result = $f->formatAll([
            'to' => fn(array $data) => '{email}',
        ]);
        $this->assertSame(['to' => 'a@x'], $result);
    }

    public function test_Closure_は_MailAddress_を返せる(): void {
        $f = $this->makeFormatter(['type' => 'a']);
        $addr = new MailAddress('foo@example.com', '名前');
        $result = $f->formatAll([
            'to' => fn() => $addr,
        ]);
        $this->assertSame($addr, $result['to']);
    }

    public function test_Closure_は_MailAddress_配列を返せる(): void {
        $f = $this->makeFormatter([]);
        $a = new MailAddress('a@example.com');
        $b = new MailAddress('b@example.com');
        $result = $f->formatAll([
            'to' => fn() => [$a, $b],
        ]);
        $this->assertSame([$a, $b], $result['to']);
    }

    public function test_Closure_は_subject_でも使える(): void {
        $f = $this->makeFormatter(['name' => '山田']);
        $result = $f->formatAll([
            'subject' => fn(array $d) => '【件名】' . $d['name'],
        ]);
        $this->assertSame('【件名】山田', $result['subject']);
    }

    // ---- map ----

    public function test_map_指定で_field_の値に応じて宛先切替(): void {
        $f = $this->makeFormatter(['department' => 'sales']);
        $result = $f->formatAll([
            'to' => [
                'field' => 'department',
                'map' => [
                    'sales'   => 'sales@x',
                    'recruit' => 'recruit@x',
                ],
                'default' => 'info@x',
            ],
        ]);
        $this->assertSame(['to' => 'sales@x'], $result);
    }

    public function test_map_未マッチは_default_が使われる(): void {
        $f = $this->makeFormatter(['department' => 'unknown']);
        $result = $f->formatAll([
            'to' => [
                'field'   => 'department',
                'map'     => ['sales' => 'sales@x'],
                'default' => 'info@x',
            ],
        ]);
        $this->assertSame(['to' => 'info@x'], $result);
    }

    public function test_map_field_が未定義のときは_default(): void {
        $f = $this->makeFormatter([]);
        $result = $f->formatAll([
            'to' => [
                'field'   => 'department',
                'map'     => ['sales' => 'sales@x'],
                'default' => 'info@x',
            ],
        ]);
        $this->assertSame(['to' => 'info@x'], $result);
    }

    public function test_map_の値が配列タプル_名前付き(): void {
        $f = $this->makeFormatter(['type' => 'recruit']);
        $result = $f->formatAll([
            'to' => [
                'field' => 'type',
                'map' => [
                    'recruit' => [['recruit@x', '採用'], ['admin@x', '管理者']],
                ],
                'default' => 'info@x',
            ],
        ]);
        $this->assertSame([
            'to' => [['recruit@x', '採用'], ['admin@x', '管理者']],
        ], $result);
    }

    public function test_map_の値が_Closure(): void {
        $f = $this->makeFormatter(['type' => 'special', 'email' => 'a@x']);
        $result = $f->formatAll([
            'to' => [
                'field' => 'type',
                'map' => [
                    'special' => fn(array $d) => 'callback@' . substr($d['email'], strpos($d['email'], '@') + 1),
                ],
                'default' => 'info@x',
            ],
        ]);
        $this->assertSame(['to' => 'callback@x'], $result);
    }

    public function test_map_の値が_placeholder_文字列(): void {
        $f = $this->makeFormatter(['type' => 'a', 'email' => 'a@x']);
        $result = $f->formatAll([
            'to' => [
                'field' => 'type',
                'map' => ['a' => '{email}'],
                'default' => 'info@x',
            ],
        ]);
        $this->assertSame(['to' => 'a@x'], $result);
    }

    public function test_map_は_field_map_default_3キー全部揃わないと普通の配列扱い(): void {
        $f = $this->makeFormatter(['x' => '1']);
        // default 欠落
        $result = $f->formatAll([
            'list' => [
                'field' => 'x',
                'map'   => ['1' => 'a@x'],
                // default 無し
            ],
        ]);
        // 普通の配列として扱われる（再帰される）
        $this->assertSame([
            'list' => [
                'field' => 'x',
                'map'   => ['1' => 'a@x'],
            ],
        ], $result);
    }

    // ---- ネスト ----

    public function test_ネストされた配列の中の_Closure_も解決(): void {
        $f = $this->makeFormatter(['email' => 'a@x']);
        $result = $f->formatAll([
            '管理者向けメール' => [
                'to'      => fn() => 'admin@x',
                'replyTo' => [['{email}', '管理者']],
            ],
        ]);
        $this->assertSame([
            '管理者向けメール' => [
                'to'      => 'admin@x',
                'replyTo' => [['a@x', '管理者']],
            ],
        ], $result);
    }

}
