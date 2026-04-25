<?php

namespace AIJOH\Test\Config;

use AIJOH\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

class ConfigLoaderTest extends TestCase {

    private string $tmpDir;
    private string $commonDir;
    private string $formDir;

    protected function setUp(): void {
        $this->tmpDir = sys_get_temp_dir() . '/mailform_configloader_' . uniqid();
        $this->commonDir = $this->tmpDir . '/common';
        $this->formDir   = $this->tmpDir . '/contact/form';
        mkdir($this->commonDir, 0700, true);
        mkdir($this->formDir,   0700, true);
    }

    protected function tearDown(): void {
        $this->cleanupDir($this->tmpDir);
    }

    private function cleanupDir( string $dir ) : void {
        if ( ! is_dir($dir) ) return;
        foreach ( scandir($dir) as $f ) {
            if ( $f === '.' || $f === '..' ) continue;
            $path = $dir . '/' . $f;
            is_dir($path) ? $this->cleanupDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function writeReturn( string $path, string $code ) : void {
        file_put_contents($path, "<?php\nreturn {$code};\n");
    }

    public function test_common_と_form_を_マージして返す(): void {
        $this->writeReturn($this->commonDir . '/verify.php',     "['csrfToken' => true]");
        $this->writeReturn($this->commonDir . '/rate_limit.php', "['enabled' => true]");
        $this->writeReturn($this->formDir . '/validation.php',   "['name' => ['rule' => 'required']]");
        $this->writeReturn($this->formDir . '/sender.php',       "['admin' => ['to' => 'a@b.c']]");

        $config = ConfigLoader::buildFormConfig($this->formDir, $this->commonDir);

        $this->assertSame(['csrfToken' => true], $config['verify']);
        $this->assertSame(['enabled' => true], $config['rate_limit']);
        $this->assertSame(['name' => ['rule' => 'required']], $config['validation']);
        $this->assertSame(['admin' => ['to' => 'a@b.c']], $config['sender']);
    }

    public function test_overrides_引数で_form_個別に_共通設定を上書き(): void {
        $this->writeReturn($this->commonDir . '/rate_limit.php', "['enabled' => true, 'whitelist_ips' => ['127.0.0.1']]");
        $this->writeReturn($this->formDir . '/validation.php', "[]");

        $config = ConfigLoader::buildFormConfig(
            $this->formDir,
            $this->commonDir,
            ['rate_limit' => ['whitelist_ips' => []]],   // フォーム個別で whitelist 空に
        );

        $this->assertTrue($config['rate_limit']['enabled']);
        $this->assertSame([], $config['rate_limit']['whitelist_ips']);
    }

    public function test_common_local_php_は_最後にマージされる_本番上書き(): void {
        $this->writeReturn($this->commonDir . '/ai.php',   "['provider' => 'claude_cli']");
        $this->writeReturn($this->commonDir . '/local.php', "['ai' => ['provider' => 'claude_api', 'api_key' => 'sk-prod']]");
        $this->writeReturn($this->formDir . '/validation.php', "[]");

        $config = ConfigLoader::buildFormConfig($this->formDir, $this->commonDir);

        $this->assertSame('claude_api', $config['ai']['provider']);
        $this->assertSame('sk-prod',    $config['ai']['api_key']);
    }

    public function test_common_local_php_は_overrides_よりも_優先される(): void {
        $this->writeReturn($this->commonDir . '/ai.php',   "['provider' => 'claude_cli']");
        $this->writeReturn($this->commonDir . '/local.php', "['ai' => ['provider' => 'gemini_api']]");
        $this->writeReturn($this->formDir . '/validation.php', "[]");

        // overrides で provider を openai_api に指定しても local.php が勝つ
        $config = ConfigLoader::buildFormConfig(
            $this->formDir,
            $this->commonDir,
            ['ai' => ['provider' => 'openai_api']],
        );

        $this->assertSame('gemini_api', $config['ai']['provider']);
    }

    public function test_存在しないファイルは無視される(): void {
        // common にも form にも何もない
        $config = ConfigLoader::buildFormConfig($this->formDir, $this->commonDir);
        $this->assertSame([], $config);
    }

}
