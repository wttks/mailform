<?php

namespace AIJOH\Test\Form;

use AIJOH\Form\FormSession;
use AIJOH\Http\Session;
use AIJOH\Http\UploadFile;
use AIJOH\Results\FormData;
use PHPUnit\Framework\TestCase;

/**
 * FormSession の添付ファイル一時保存・復元のテスト。
 *
 * Session::getInstance() は $_SESSION を直接触るので、テストでは $_SESSION 配列を
 * セットアップして利用する。
 */
class FormSessionUploadTest extends TestCase {

    private string $tmpUploadBase;

    protected function setUp() : void {
        $_SESSION = [];
        Session::reset();
        $this->tmpUploadBase = sys_get_temp_dir() . '/mailform_uploads';
    }

    protected function tearDown() : void {
        $_SESSION = [];
        Session::reset();
        // テスト残骸の掃除（このテストで作ったディレクトリ）
        if ( is_dir($this->tmpUploadBase) ) {
            foreach ( glob($this->tmpUploadBase . '/*', GLOB_ONLYDIR) ?: [] as $dir ) {
                foreach ( glob($dir . '/*') ?: [] as $f ) {
                    @unlink($f);
                }
                @rmdir($dir);
            }
        }
    }


    /**
     * テスト用に $_FILES に偽のアップロードを差し込み、UploadFile を作る代わりに
     * fromPersisted で「移動済みっぽい」UploadFile を構築する。
     * → serializeData は move() を呼ぶが isPersisted=true なので rename される。
     */
    private function buildFakeUpload( string $key, string $name, string $contents, string $mime ) : UploadFile {
        $tmp = tempnam(sys_get_temp_dir(), 'mailformtest_');
        file_put_contents($tmp, $contents);
        return UploadFile::fromPersisted($tmp, $name, $mime, strlen($contents));
    }


    public function test_mkdir失敗時_errorlogに原因が記録される(): void {
        // 公開可能なテスト用サブクラスで getOrCreateUploadDir に到達する
        $session = new class extends FormSession {
            public function callGetOrCreateUploadDir() : string {
                return $this->getOrCreateUploadDir();
            }
        };

        // Session 初期化後に token を固定 ( FormSession コンストラクタ後にセットしないと
        // 上書きされる可能性がある )
        $token = 'collision-token-' . bin2hex(random_bytes(4));
        $_SESSION['_form_upload_token'] = $token;

        // 同名のファイルを事前作成して mkdir を失敗させる
        if ( ! is_dir($this->tmpUploadBase) ) {
            mkdir($this->tmpUploadBase, 0700, true);
        }
        $conflictPath = $this->tmpUploadBase . '/' . $token;
        @unlink($conflictPath);
        file_put_contents($conflictPath, 'I am a file, not a dir');

        // error_log を一時ファイルに切替
        $logFile = tempnam(sys_get_temp_dir(), 'mfsess_');
        $originalLog = ini_get('error_log');
        ini_set('error_log', $logFile);

        try {
            $result = $session->callGetOrCreateUploadDir();
            // ディレクトリは作れていない ( ファイルが居座っている )
            $this->assertFalse(is_dir($conflictPath));
            // パス文字列自体は返る ( 後続の move() で失敗するという既存挙動 )
            $this->assertSame($conflictPath, $result);

            $log = (string) file_get_contents($logFile);
            $this->assertStringContainsString(
                'failed to create upload dir',
                $log,
                'mkdir 失敗時に error_log で原因が出ること'
            );
        } finally {
            ini_set('error_log', $originalLog);
            @unlink($logFile);
            @unlink($conflictPath);
        }
    }


    public function test_save_と_restore_の往復で_UploadFile_が復元される(): void {
        $upload = $this->buildFakeUpload('document', 'sample.pdf', 'PDF DATA', 'application/pdf');
        $formData = new FormData();
        $formData->setData([
            'name'     => '山田太郎',
            'document' => $upload,
        ]);

        $session = new FormSession();
        $session->save($formData);

        // セッションには永続化メタが入っているはず
        $stored = $_SESSION['_form_data'];
        $this->assertSame('upload_file', $stored['document']['__type']);
        $this->assertSame('sample.pdf', $stored['document']['name']);
        $this->assertSame(8, $stored['document']['size']);
        $this->assertFileExists($stored['document']['persisted_path']);

        // restore で UploadFile に戻る
        $restored = $session->restore();
        $this->assertNotNull($restored);
        $data = $restored->getData();
        $this->assertSame('山田太郎', $data['name']);
        $this->assertInstanceOf(UploadFile::class, $data['document']);
        $this->assertSame('sample.pdf', $data['document']->getName());
        $this->assertSame(8, $data['document']->getSize());
        $this->assertSame('application/pdf', $data['document']->getMimeType());
        $this->assertTrue($data['document']->isPersisted());
        // 中身も保持されている
        $this->assertSame('PDF DATA', file_get_contents($data['document']->getTmpName()));
    }


    public function test_clear_で永続化ファイルとディレクトリも削除される(): void {
        $upload = $this->buildFakeUpload('document', 'a.txt', 'X', 'text/plain');
        $formData = new FormData();
        $formData->setData(['document' => $upload]);

        $session = new FormSession();
        $session->save($formData);

        $persistedPath = $_SESSION['_form_data']['document']['persisted_path'];
        $persistedDir  = dirname($persistedPath);
        $this->assertFileExists($persistedPath);
        $this->assertDirectoryExists($persistedDir);

        $session->clear();

        $this->assertFileDoesNotExist($persistedPath);
        $this->assertDirectoryDoesNotExist($persistedDir);
        $this->assertArrayNotHasKey('_form_data', $_SESSION);
        $this->assertArrayNotHasKey('_form_upload_token', $_SESSION);
    }


    public function test_restore_時に_永続化ファイルが消えていれば_null_扱い(): void {
        $upload = $this->buildFakeUpload('document', 'b.txt', 'Y', 'text/plain');
        $formData = new FormData();
        $formData->setData(['document' => $upload]);

        $session = new FormSession();
        $session->save($formData);

        // 外部要因で永続化ファイルが消えたケースをシミュレート
        unlink($_SESSION['_form_data']['document']['persisted_path']);

        $restored = $session->restore();
        $this->assertNull($restored->getData()['document']);
    }


    public function test_restore_で添付ファイル消失時_hasMissingUploads_が_true(): void {
        $upload = $this->buildFakeUpload('document', 'c.txt', 'Z', 'text/plain');
        $formData = new FormData();
        $formData->setData([ 'name' => '田中', 'document' => $upload ]);

        $session = new FormSession();
        $session->save($formData);
        unlink($_SESSION['_form_data']['document']['persisted_path']);

        $session->restore();
        $this->assertTrue($session->hasMissingUploads());
    }


    public function test_restore_で添付ファイルが残っていれば_hasMissingUploads_は_false(): void {
        $upload = $this->buildFakeUpload('document', 'd.txt', 'W', 'text/plain');
        $formData = new FormData();
        $formData->setData([ 'name' => '田中', 'document' => $upload ]);

        $session = new FormSession();
        $session->save($formData);
        $session->restore();
        $this->assertFalse($session->hasMissingUploads());
    }


    public function test_hasMissingUploads_は_restore_毎にリセットされる(): void {
        // 1 回目の restore で missing 検出
        $upload1 = $this->buildFakeUpload('document', 'e.txt', 'A', 'text/plain');
        $fd1 = new FormData();
        $fd1->setData([ 'document' => $upload1 ]);

        $session = new FormSession();
        $session->save($fd1);
        unlink($_SESSION['_form_data']['document']['persisted_path']);
        $session->restore();
        $this->assertTrue($session->hasMissingUploads());

        // 2 回目の restore で別のセッション（添付なし）
        $session->clear();
        $fd2 = new FormData();
        $fd2->setData([ 'name' => '田中' ]);
        $session->save($fd2);
        $session->restore();
        $this->assertFalse($session->hasMissingUploads());
    }


    public function test_save_を_2回呼ぶと_前回の一時ファイルは破棄される(): void {
        $u1 = $this->buildFakeUpload('document', 'first.txt', 'first', 'text/plain');
        $fd1 = new FormData();
        $fd1->setData(['document' => $u1]);

        $session = new FormSession();
        $session->save($fd1);
        $firstPath = $_SESSION['_form_data']['document']['persisted_path'];

        $u2 = $this->buildFakeUpload('document', 'second.txt', 'second', 'text/plain');
        $fd2 = new FormData();
        $fd2->setData(['document' => $u2]);
        $session->save($fd2);
        $secondPath = $_SESSION['_form_data']['document']['persisted_path'];

        $this->assertFileDoesNotExist($firstPath);
        $this->assertFileExists($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
    }


    public function test_gc_は古いディレクトリだけ削除する(): void {
        // 新しい upload を 1 件作る
        $upload = $this->buildFakeUpload('document', 'new.txt', 'new', 'text/plain');
        $formData = new FormData();
        $formData->setData(['document' => $upload]);
        $session = new FormSession();
        $session->save($formData);
        $freshDir = dirname($_SESSION['_form_data']['document']['persisted_path']);

        // 古いディレクトリ（mtime を 2 日前に）を別途作る
        $oldDir = $this->tmpUploadBase . '/old_token_xxxx';
        mkdir($oldDir, 0700, true);
        $oldFile = $oldDir . '/x';
        file_put_contents($oldFile, 'old');
        touch($oldDir, time() - 86400 * 2);
        touch($oldFile, time() - 86400 * 2);

        $deleted = FormSession::gc(86400);

        $this->assertSame(1, $deleted);
        $this->assertFileDoesNotExist($oldFile);
        $this->assertDirectoryDoesNotExist($oldDir);
        $this->assertDirectoryExists($freshDir);
    }
}
