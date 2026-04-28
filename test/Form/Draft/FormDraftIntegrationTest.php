<?php

namespace AIJOH\Test\Form\Draft;

use AIJOH\Form\Form;
use AIJOH\Http\Session;
use PHPUnit\Framework\TestCase;

/**
 * Form クラスの draft 機能連携のテスト。
 *
 * Cookie 操作（実際の保存・復元）は DraftManager のテストで網羅済み。
 * ここでは Form の API（公開ラッパ）が DraftManager に正しく委譲しているかを確認する。
 */
class FormDraftIntegrationTest extends TestCase {

    private string $key;

    protected function setUp() : void {
        $_SESSION = [];
        $_COOKIE = [];
        Session::reset();
        $this->key = str_repeat("\x00", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }


    protected function tearDown() : void {
        $_SESSION = [];
        $_COOKIE = [];
        Session::reset();
    }


    private function baseConfig() : array {
        return [
            'validation' => [],
            'sender'     => [],
        ];
    }


    // ---- draft 無効時 ----

    public function test_draft設定無し_isDraftEnabled_false() {
        $form = new Form($this->baseConfig());
        $this->assertFalse($form->isDraftEnabled());
    }


    public function test_draft設定無し_getDraftValue_デフォルト返す() {
        $form = new Form($this->baseConfig());
        $this->assertSame('', $form->getDraftValue('name'));
        $this->assertSame('default', $form->getDraftValue('name', 'default'));
    }


    public function test_draft設定無し_getDraftValues_空配列() {
        $form = new Form($this->baseConfig());
        $this->assertSame([], $form->getDraftValues());
    }


    public function test_draft設定無し_getDraftClientConfig_enabled_false() {
        $form = new Form($this->baseConfig());
        $this->assertSame([ 'enabled' => false ], $form->getDraftClientConfig());
    }


    // ---- draft 有効時 ----

    private function draftConfig( array $overrides = [] ) : array {
        return $this->baseConfig() + [
            'draft' => array_merge([
                'fields'         => [ 'name', 'email', 'message' ],
                'encryption_key' => $this->key,
            ], $overrides),
        ];
    }


    public function test_draft設定あり_isDraftEnabled_true() {
        $form = new Form($this->draftConfig());
        $this->assertTrue($form->isDraftEnabled());
    }


    public function test_draft設定あり_未保存_getDraftValue_デフォルト返す() {
        $form = new Form($this->draftConfig());
        $this->assertSame('', $form->getDraftValue('name'));
        $this->assertSame('fallback', $form->getDraftValue('name', 'fallback'));
    }


    public function test_draft設定あり_未保存_getDraftValues_空配列() {
        $form = new Form($this->draftConfig());
        $this->assertSame([], $form->getDraftValues());
    }


    public function test_draft設定あり_getDraftClientConfig_enabled_true_consent情報付き() {
        $form = new Form($this->draftConfig());
        $config = $form->getDraftClientConfig();
        $this->assertTrue($config['enabled']);
        $this->assertArrayHasKey('consent', $config);
        $this->assertSame('disabled', $config['consent']['mode']);   // デフォルトは disabled 相当
        $this->assertSame('opt-in', $config['consent']['behavior']);
        $this->assertTrue($config['consent']['isAllowed']);          // disabled は常に許可
        $this->assertFalse($config['consent']['managed']);
        $this->assertSame(800, $config['debounce_ms']);
    }


    public function test_builtin_optin_未同意_isAllowed_false_managed_true() {
        $form = new Form($this->draftConfig([
            'consent' => [ 'mode' => 'builtin', 'behavior' => 'opt-in' ],
        ]));
        $config = $form->getDraftClientConfig();
        $this->assertFalse($config['consent']['isAllowed']);
        $this->assertTrue($config['consent']['managed']);
    }


    public function test_builtin_optin_同意済み_isAllowed_true() {
        $_COOKIE['mailform_draft_consent'] = 'granted';
        $form = new Form($this->draftConfig([
            'consent' => [ 'mode' => 'builtin', 'behavior' => 'opt-in' ],
        ]));
        $config = $form->getDraftClientConfig();
        $this->assertTrue($config['consent']['isAllowed']);
    }


    public function test_callbackモード_常にisAllowed_true() {
        $form = new Form($this->draftConfig([
            'consent' => [
                'mode'     => 'callback',
                'check_js' => 'true',
            ],
        ]));
        $config = $form->getDraftClientConfig();
        $this->assertTrue($config['consent']['isAllowed']);
        $this->assertFalse($config['consent']['managed']);
    }


    // ---- draft 暗号化キー欠落で例外 ----

    public function test_draft設定_encryption_key欠落で例外() {
        $this->expectException(\InvalidArgumentException::class);
        new Form($this->baseConfig() + [
            'draft' => [ 'fields' => [ 'name' ] ],
        ]);
    }
}
