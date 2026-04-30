<?php

namespace AIJOH\Test\Http;

use AIJOH\Http\Post;
use AIJOH\SecurityPayloads;
use AIJOH\Validation\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * POST データの UTF-8 妥当性検証テスト。
 *
 * Post::__construct で _ プレフィックス以外のフィールドを再帰検証し、
 * 不正バイト列を検出したら ValidationException を投げる。
 */
class PostEncodingTest extends TestCase {

    protected function setUp() : void {
        Post::reset();
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown() : void {
        Post::reset();
        $_POST = [];
        $_FILES = [];
    }


    // ---- 正常系 ----

    public function test_有効な_UTF8_は受理される() : void {
        $_POST = [
            'name'  => '田中太郎',
            'email' => 'tanaka@example.com',
            'memo'  => "改行\nタブ\tも OK",
        ];
        // 例外を投げないことだけ確認
        $post = new Post();
        $this->assertSame('田中太郎', $post->get('name'));
    }


    public function test_テスト用に_data_を直接渡した場合_検証スキップ() : void {
        // setForTest 経由は検証スキップ
        Post::setForTest([ 'name' => "\xC0\xBC" ]);
        $this->assertSame("\xC0\xBC", Post::getInstance()->get('name'));
    }


    // ---- 不正 UTF-8 検出 ----

    public function test_不正_UTF8_overlong_で例外() : void {
        $this->expectException(ValidationException::class);
        $_POST = [ 'name' => SecurityPayloads::ENCODING_ATTACK['invalid_utf8_2'] ];
        new Post();
    }


    public function test_不正_UTF8_3byte_overlong_で例外() : void {
        $this->expectException(ValidationException::class);
        $_POST = [ 'name' => SecurityPayloads::ENCODING_ATTACK['invalid_utf8_3'] ];
        new Post();
    }


    public function test_lone_high_surrogate_で例外() : void {
        $this->expectException(ValidationException::class);
        $_POST = [ 'name' => SecurityPayloads::ENCODING_ATTACK['lone_high_surr'] ];
        new Post();
    }


    public function test_truncated_multibyte_で例外() : void {
        $this->expectException(ValidationException::class);
        $_POST = [ 'name' => SecurityPayloads::ENCODING_ATTACK['truncated_seq'] ];
        new Post();
    }


    public function test_例外には_不正フィールド名と_メッセージが含まれる() : void {
        $_POST = [
            'name'  => 'OK',
            'memo'  => "\xC0\xBC",
            'email' => 'ok@example.com',
        ];
        try {
            new Post();
            $this->fail('ValidationException should be thrown');
        } catch ( ValidationException $e ) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('memo', $errors);
            $this->assertArrayNotHasKey('name', $errors);
            $this->assertArrayNotHasKey('email', $errors);
        }
    }


    // ---- _ プレフィックスはスキップ ----

    public function test__csrf_token_の不正_UTF8_は検証スキップ() : void {
        // CSRF token 等の内部フィールドは検証対象外
        $_POST = [
            '_csrf_token' => "\xC0\xBC",   // 不正でもスキップ
            'name'        => '田中',
        ];
        $post = new Post();
        $this->assertSame('田中', $post->get('name'));
    }


    public function test__action_と__step_もスキップ() : void {
        $_POST = [
            '_action' => "\xFF\xFE",       // 不正でもスキップ
            '_step'   => "\xC0\xBC",
            'name'    => '田中',
        ];
        $post = new Post();
        $this->assertSame('田中', $post->get('name'));
    }


    // ---- ネスト配列の再帰検証 ----

    public function test_ネスト配列の不正_UTF8_も検出される() : void {
        $this->expectException(ValidationException::class);
        $_POST = [
            'address' => [
                'zip'    => '100-0001',
                'street' => "\xC0\xBC",   // ネストの中に不正バイト
            ],
        ];
        new Post();
    }


    public function test_ネスト配列が正常なら受理される() : void {
        $_POST = [
            'address' => [ 'zip' => '100-0001', 'street' => '千代田1-1' ],
        ];
        $post = new Post();
        $this->assertIsArray($post->get('address'));
    }
}
