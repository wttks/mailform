<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Validation;
use AIJOH\Validation\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * ValidateSame の統合テスト（Validation 経由）
 */
class ValidateSameTest extends TestCase {

    private function config() : array {
        return [
            'email' => [
                'title' => 'メールアドレス',
                'rule'  => 'required|email',
            ],
            'email_confirm' => [
                'title'  => 'メールアドレス（確認用）',
                'rule'   => 'required|email|same:email',
                'message' => ['same' => 'メールアドレスが一致しません。'],
            ],
        ];
    }

    public function test_一致するときは_OK(): void {
        $v = new Validation($this->config());
        $result = $v->validated([
            'email'         => 'a@example.com',
            'email_confirm' => 'a@example.com',
        ]);
        $this->assertSame('a@example.com', $result['email_confirm']);
    }

    public function test_一致しないときは例外(): void {
        $v = new Validation($this->config());
        try {
            $v->validated([
                'email'         => 'a@example.com',
                'email_confirm' => 'b@example.com',
            ]);
            $this->fail('例外が投げられるべき');
        } catch ( ValidationException $e ) {
            $this->assertSame('メールアドレスが一致しません。', $e->getErrors()['email_confirm']);
        }
    }

    public function test_両方空なら_same_チェックは通る_required_側でエラーになる(): void {
        $v = new Validation($this->config());
        try {
            $v->validated(['email' => '', 'email_confirm' => '']);
            $this->fail('required で例外');
        } catch ( ValidationException $e ) {
            // same のエラーではなく required のエラーが出る
            $this->assertStringContainsString('必須', $e->getErrors()['email_confirm']);
        }
    }

    public function test_デフォルトメッセージ(): void {
        $config = $this->config();
        unset($config['email_confirm']['message']);
        $v = new Validation($config);
        try {
            $v->validated([
                'email'         => 'a@example.com',
                'email_confirm' => 'b@example.com',
            ]);
            $this->fail();
        } catch ( ValidationException $e ) {
            $msg = $e->getErrors()['email_confirm'];
            $this->assertStringContainsString('メールアドレス（確認用）', $msg);
            $this->assertStringContainsString('email', $msg);
            $this->assertStringContainsString('一致しません', $msg);
        }
    }

}
