<?php

namespace AIJOH\Test\Sender;

use AIJOH\Sender\Send\AbstractSendBase;
use PHPUnit\Framework\TestCase;

/**
 * AbstractSendBase::resolveStaticAttachments() の動作テスト。
 *
 * Sender 全体の統合テストではなく、attachments の正規化ロジック単体を確認する。
 * テスト用に AbstractSendBase の subclass を作って resolveStaticAttachments を露出させる。
 */
class StaticAttachmentResolverTest extends TestCase {

    /**
     * テスト用に protected メソッドを公開し、format/data を注入できる Send クラス。
     */
    private function makeSend( array $config, array $formData = [] ) : object {
        $send = new class extends AbstractSendBase {
            public function send() : bool { return true; }
            protected function parseConfig( array $config ) : array { return $config; }
            public array $injectedData = [];
            public function callResolve() : array {
                // format が null でも config['attachments'] が Closure なら format を介さない経路もある
                // ここでは format を mock する代わりに、Closure 経由のデータ注入を testdata で実現
                $cfg = $this->config['attachments'] ?? [];
                if ( is_callable($cfg) ) {
                    $this->config['attachments'] = $cfg($this->injectedData);
                }
                return $this->resolveStaticAttachments();
            }
        };
        $send->setConfig($config);
        $send->injectedData = $formData;
        return $send;
    }


    // ---- 正常系 ----

    public function test_attachments_未設定なら空配列() : void {
        $send = $this->makeSend([ 'foo' => 'bar' ]);
        $this->assertSame([], $send->callResolve());
    }


    public function test_attachments_パス文字列_配列() : void {
        $send = $this->makeSend([
            'attachments' => [ '/var/www/a.pdf', '/var/www/b.pdf' ],
        ]);
        $this->assertSame([
            [ 'path' => '/var/www/a.pdf', 'name' => '' ],
            [ 'path' => '/var/www/b.pdf', 'name' => '' ],
        ], $send->callResolve());
    }


    public function test_attachments_path_name_配列() : void {
        $send = $this->makeSend([
            'attachments' => [
                [ 'path' => '/var/www/a.pdf', 'name' => 'ご案内.pdf' ],
            ],
        ]);
        $this->assertSame([
            [ 'path' => '/var/www/a.pdf', 'name' => 'ご案内.pdf' ],
        ], $send->callResolve());
    }


    public function test_attachments_配列で_name_省略は空文字() : void {
        $send = $this->makeSend([
            'attachments' => [
                [ 'path' => '/var/www/a.pdf' ],
            ],
        ]);
        $this->assertSame([
            [ 'path' => '/var/www/a.pdf', 'name' => '' ],
        ], $send->callResolve());
    }


    public function test_attachments_文字列と配列の混在() : void {
        $send = $this->makeSend([
            'attachments' => [
                '/var/www/a.pdf',
                [ 'path' => '/var/www/b.pdf', 'name' => '案内.pdf' ],
            ],
        ]);
        $this->assertSame([
            [ 'path' => '/var/www/a.pdf', 'name' => '' ],
            [ 'path' => '/var/www/b.pdf', 'name' => '案内.pdf' ],
        ], $send->callResolve());
    }


    // ---- Closure（動的添付）----

    public function test_attachments_Closureで動的決定() : void {
        $send = $this->makeSend(
            [
                'attachments' => fn( array $data ) => $data['plan'] === 'pro'
                    ? [ '/var/www/pro.pdf' ]
                    : [ '/var/www/free.pdf' ],
            ],
            [ 'plan' => 'pro' ],
        );
        $this->assertSame([
            [ 'path' => '/var/www/pro.pdf', 'name' => '' ],
        ], $send->callResolve());
    }


    public function test_attachments_Closure戻り値が空配列なら空() : void {
        $send = $this->makeSend(
            [ 'attachments' => fn() => [] ],
        );
        $this->assertSame([], $send->callResolve());
    }


    public function test_attachments_Closure戻り値が配列以外なら空() : void {
        $send = $this->makeSend(
            [ 'attachments' => fn() => 'not-array' ],
        );
        $this->assertSame([], $send->callResolve());
    }


    // ---- 不正形式はスキップ ----

    public function test_不正な要素はスキップされる() : void {
        $send = $this->makeSend([
            'attachments' => [
                '/var/www/a.pdf',
                123,                     // 不正: 数値
                [ 'no_path' => 'x' ],   // 不正: path キー無し
                null,                    // 不正: null
                [ 'path' => '/var/www/b.pdf' ],
            ],
        ]);
        $this->assertSame([
            [ 'path' => '/var/www/a.pdf', 'name' => '' ],
            [ 'path' => '/var/www/b.pdf', 'name' => '' ],
        ], $send->callResolve());
    }


    public function test_attachments_文字列値は空配列扱い() : void {
        // 'attachments' => 'not-array' のような誤設定
        $send = $this->makeSend([ 'attachments' => 'not-array' ]);
        $this->assertSame([], $send->callResolve());
    }
}
