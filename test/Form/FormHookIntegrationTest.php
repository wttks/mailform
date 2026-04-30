<?php

namespace AIJOH\Test\Form;

use AIJOH\Form\Form;
use AIJOH\Hook\HookRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Form クラスと HookRegistry の統合テスト。
 *
 * 各 hook 発火タイミングのフルフロー検証は実際の send / DB が絡むので難しいが、
 * config / on() / plugins/ の 3 経路で同じ HookRegistry に集約されることを確認する。
 */
class FormHookIntegrationTest extends TestCase {

    private function baseConfig() : array {
        return [
            'validation' => [],
            'sender'     => [],
        ];
    }


    public function test_config_hooks_は_HookRegistry_に登録される() : void {
        $form = new Form($this->baseConfig() + [
            'hooks' => [
                'after_send' => fn() => null,
            ],
        ]);
        $this->assertSame(1, $form->getHookRegistry()->count('after_send'));
    }


    public function test_config_hooks_配列形式で複数登録() : void {
        $form = new Form($this->baseConfig() + [
            'hooks' => [
                'before_validate' => [
                    fn( $data ) => $data,
                    fn( $data ) => $data,
                    fn( $data ) => $data,
                ],
            ],
        ]);
        $this->assertSame(3, $form->getHookRegistry()->count('before_validate'));
    }


    public function test_config_hooks_callable_でない値はスキップ() : void {
        $form = new Form($this->baseConfig() + [
            'hooks' => [
                'after_send' => 'not a callable',
            ],
        ]);
        $this->assertSame(0, $form->getHookRegistry()->count('after_send'));
    }


    public function test_form_on_で動的に追加できる() : void {
        $form = new Form($this->baseConfig());
        $form->on('after_send', fn() => null);
        $form->on('after_send', fn() => null);
        $this->assertSame(2, $form->getHookRegistry()->count('after_send'));
    }


    public function test_form_on_はチェイン可能() : void {
        $form = new Form($this->baseConfig());
        $result = $form
            ->on('a', fn() => null)
            ->on('b', fn() => null);
        $this->assertSame($form, $result);
    }


    public function test_config_と_on_は同じ_HookRegistry_に集約される() : void {
        $form = new Form($this->baseConfig() + [
            'hooks' => [
                'after_send' => fn() => 'from config',
            ],
        ]);
        $form->on('after_send', fn() => 'from on()');

        // 同じ event に 2 つ（config 1 + on() 1）登録されている
        $this->assertSame(2, $form->getHookRegistry()->count('after_send'));
    }


    public function test_plugin_dirs_でプラグインも読み込まれる() : void {
        // 一時ディレクトリに plugin を置いて読み込む
        $tmpDir = sys_get_temp_dir() . '/mailform_form_plugin_test_' . uniqid();
        mkdir($tmpDir, 0777, true);
        file_put_contents($tmpDir . '/test_plugin.php', <<<'PHP'
<?php
return new class implements \AIJOH\Hook\MailformPlugin {
    public function register( \AIJOH\Hook\HookRegistry $hooks ) : void {
        $hooks->on('from_plugin', fn() => 'fired');
    }
};
PHP
        );

        try {
            $form = new Form($this->baseConfig() + [
                'plugin_dirs' => [ $tmpDir ],
            ]);
            $this->assertSame(1, $form->getHookRegistry()->count('from_plugin'));
        } finally {
            @unlink($tmpDir . '/test_plugin.php');
            @rmdir($tmpDir);
        }
    }
}
