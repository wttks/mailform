<?php

namespace AIJOH\Form\Draft;

use PHPUnit\Framework\TestCase;

class DraftManagerTest extends TestCase {

    private string $key;

    protected function setUp() : void {
        $this->key = str_repeat("\x00", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }


    private function makeManager( array $config = [], ?ArrayCookieIO $cookieIO = null ) : DraftManager {
        $defaults = [
            'fields'         => [ 'name', 'email', 'message' ],
            'encryption_key' => $this->key,
        ];
        $config = array_merge($defaults, $config);
        return new DraftManager($config, null, null, $cookieIO ?? new ArrayCookieIO());
    }


    // ---- コンストラクタ ----

    public function test_encryption_key不正で例外() {
        $this->expectException(\InvalidArgumentException::class);
        new DraftManager([ 'encryption_key' => 'short' ], null, null, new ArrayCookieIO());
    }


    public function test_encryption_key欠落で例外() {
        $this->expectException(\InvalidArgumentException::class);
        new DraftManager([], null, null, new ArrayCookieIO());
    }


    // ---- 同意（builtin + opt-in、デフォルト挙動） ----

    public function test_disabledモードは常に許可_save_restoreが動く() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([], $io);  // consent 無し → disabled 相当
        $manager->save([ 'name' => '田中', 'email' => 'a@b.com', 'message' => 'hello' ]);
        $this->assertNotEmpty($io->sets);

        $restored = $manager->restore();
        $this->assertSame([ 'name' => '田中', 'email' => 'a@b.com', 'message' => 'hello' ], $restored);
    }


    public function test_builtin_optin_未同意ではsaveしない() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([
            'consent' => [ 'mode' => 'builtin', 'behavior' => 'opt-in' ],
        ], $io);
        $manager->save([ 'name' => '田中', 'email' => 'a@b.com', 'message' => 'hello' ]);
        // 同意 Cookie が無いので何もセットされない
        $this->assertEmpty($io->sets);
    }


    public function test_builtin_optin_同意済みでsaveする() {
        $io = new ArrayCookieIO([ 'mailform_draft_consent' => 'granted' ]);
        $manager = $this->makeManager([
            'consent' => [ 'mode' => 'builtin', 'behavior' => 'opt-in' ],
        ], $io);
        $manager->save([ 'name' => '田中' ]);
        $this->assertNotEmpty($io->sets);
    }


    public function test_builtin_optin_未同意ではrestoreが空() {
        // Cookie 自体は存在するが、同意なしなら復元しない
        $io = new ArrayCookieIO();
        $writeManager = $this->makeManager([], $io);
        $writeManager->save([ 'name' => '田中' ]);
        $this->assertNotEmpty($io->sets);

        // 別 Manager を opt-in で作る
        $io2 = new ArrayCookieIO($io->cookies);
        $manager = $this->makeManager([
            'consent' => [ 'mode' => 'builtin', 'behavior' => 'opt-in' ],
        ], $io2);
        $this->assertSame([], $manager->restore());
    }


    // ---- フィールドホワイトリスト ----

    public function test_ホワイトリスト外のフィールドは保存されない() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([ 'fields' => [ 'name' ] ], $io);
        $manager->save([
            'name'    => '田中',
            'email'   => 'a@b.com',   // ← whitelist 外
            'message' => 'hello',     // ← whitelist 外
        ]);
        $restored = $manager->restore();
        $this->assertSame([ 'name' => '田中' ], $restored);
    }


    public function test_全フィールドがwhitelist外ならsaveしない_既存はclear() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([ 'fields' => [ 'name' ] ], $io);
        // 一度 name を保存
        $manager->save([ 'name' => '田中' ]);
        $this->assertNotEmpty($io->cookies);

        // whitelist にないフィールドだけで save → 既存の draft Cookie がクリアされる
        $manager->save([ 'unrelated' => 'xyz' ]);
        $remainingDraftCookies = array_filter(
            array_keys($io->cookies),
            fn( $name ) => str_starts_with($name, 'mailform_draft_') && ctype_digit(substr($name, strlen('mailform_draft_'))),
        );
        $this->assertEmpty($remainingDraftCookies);
    }


    // ---- 危険フィールドの強制除外 ----

    public function test_password系フィールドは強制除外() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([
            'fields' => [ 'name', 'password', 'pw_confirm', 'user_pass' ],
        ], $io);
        $manager->save([
            'name'        => '田中',
            'password'    => 'secret',
            'pw_confirm'  => 'secret',
            'user_pass'   => 'secret',
        ]);
        $restored = $manager->restore();
        $this->assertSame([ 'name' => '田中' ], $restored);
    }


    public function test_credit_card系フィールドは強制除外() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([
            'fields' => [ 'name', 'credit_card', 'cc_number', 'card_number', 'cvv', 'pin' ],
        ], $io);
        $manager->save([
            'name'        => '田中',
            'credit_card' => '4111-1111-1111-1111',
            'cc_number'   => '4111111111111111',
            'card_number' => '4111111111111111',
            'cvv'         => '123',
            'pin'         => '0000',
        ]);
        $restored = $manager->restore();
        $this->assertSame([ 'name' => '田中' ], $restored);
    }


    public function test_設置者がblocked_fieldsを追加できる() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([
            'fields'         => [ 'name', 'mynumber', 'secret_q' ],
            'blocked_fields' => [ 'mynumber', 'secret' ],
        ], $io);
        $manager->save([
            'name'     => '田中',
            'mynumber' => '123456789012',
            'secret_q' => '答え',
        ]);
        $this->assertSame([ 'name' => '田中' ], $manager->restore());
    }


    // ---- restore: 異常系 ----

    public function test_復号失敗時は壊れたCookieを削除して空配列を返す() {
        $io = new ArrayCookieIO([
            'mailform_draft_0' => 'v1.-.0.1.garbage',  // 不正な base64
        ]);
        $manager = $this->makeManager([], $io);
        $this->assertSame([], $manager->restore());
        $this->assertNotEmpty($io->deletes);   // 削除操作が走った
    }


    public function test_未保存ならrestoreで空配列() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([], $io);
        $this->assertSame([], $manager->restore());
    }


    // ---- clear ----

    public function test_clearでdraftCookieのみ削除_同意Cookieは残る() {
        $io = new ArrayCookieIO([ 'mailform_draft_consent' => 'granted' ]);
        $manager = $this->makeManager([
            'consent' => [ 'mode' => 'builtin', 'behavior' => 'opt-in' ],
        ], $io);
        $manager->save([ 'name' => '田中', 'email' => 'a@b.com' ]);
        $this->assertNotEmpty($io->cookies);

        $manager->clear();
        // 同意 Cookie は残る
        $this->assertSame('granted', $io->cookies['mailform_draft_consent'] ?? null);
        // draft データ Cookie は消える
        foreach ( array_keys($io->cookies) as $name ) {
            if ( str_starts_with($name, 'mailform_draft_') ) {
                $suffix = substr($name, strlen('mailform_draft_'));
                $this->assertFalse(ctype_digit($suffix), "draft data cookie '{$name}' should be cleared");
            }
        }
    }


    // ---- 古い分割の残骸防止 ----

    public function test_save時に既存の古い分割Cookieが上書きされる() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([
            'fields'    => [ 'message' ],
            'compress'  => 0,                  // 圧縮無効で確実に分割させる
            'max_bytes' => 14000,
            'split'     => 5,
        ], $io);

        // 長文で 3 分割
        $manager->save([ 'message' => bin2hex(random_bytes(3000)) ]);
        $countBefore = count(array_filter(
            array_keys($io->cookies),
            fn( $n ) => str_starts_with($n, 'mailform_draft_') && ctype_digit(substr($n, strlen('mailform_draft_'))),
        ));
        $this->assertGreaterThan(1, $countBefore);

        // 短文で 1 分割に → 古い 2,3,... 番が残らないこと
        $manager->save([ 'message' => '短い' ]);
        $countAfter = count(array_filter(
            array_keys($io->cookies),
            fn( $n ) => str_starts_with($n, 'mailform_draft_') && ctype_digit(substr($n, strlen('mailform_draft_'))),
        ));
        $this->assertSame(1, $countAfter);
    }


    // ---- 同意管理 ----

    public function test_setConsent_builtinモードでセットされる() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([
            'consent' => [ 'mode' => 'builtin' ],
        ], $io);
        $manager->setConsent('granted');
        $this->assertSame('granted', $io->cookies['mailform_draft_consent']);
    }


    public function test_setConsent_disabledモードでは何もしない() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([], $io);   // disabled 相当
        $manager->setConsent('granted');
        $this->assertEmpty($io->sets);
    }


    public function test_isManagedConsent_builtinのみtrue() {
        $this->assertFalse($this->makeManager()->isManagedConsent());
        $this->assertTrue($this->makeManager([
            'consent' => [ 'mode' => 'builtin' ],
        ])->isManagedConsent());
    }


    // ---- マルチフォーム対応（prefix 切り替え） ----

    public function test_prefix変更で別フォームと混線しない() {
        $io = new ArrayCookieIO();
        $managerA = $this->makeManager([
            'cookie' => [ 'name_prefix' => 'mailform_draft_contact' ],
        ], $io);
        $managerB = $this->makeManager([
            'cookie' => [ 'name_prefix' => 'mailform_draft_quote' ],
        ], $io);

        $managerA->save([ 'name' => 'A 太郎', 'email' => 'a@a', 'message' => 'A' ]);
        $managerB->save([ 'name' => 'B 次郎', 'email' => 'b@b', 'message' => 'B' ]);

        $this->assertSame([ 'name' => 'A 太郎', 'email' => 'a@a', 'message' => 'A' ], $managerA->restore());
        $this->assertSame([ 'name' => 'B 次郎', 'email' => 'b@b', 'message' => 'B' ], $managerB->restore());
    }


    // ---- Cookie オプション ----

    public function test_保存時のCookieオプションがHttpOnly_Secure_SameSiteStrict() {
        $io = new ArrayCookieIO();
        $manager = $this->makeManager([
            'cookie' => [ 'path' => '/contact/' ],
            'ttl'    => 3600,
        ], $io);
        $manager->save([ 'name' => '田中' ]);

        $this->assertNotEmpty($io->sets);
        $opts = $io->sets[0]['options'];
        $this->assertTrue($opts['httponly']);
        $this->assertTrue($opts['secure']);
        $this->assertSame('Strict', $opts['samesite']);
        $this->assertSame('/contact/', $opts['path']);
        // expires は時刻なので妥当範囲確認
        $this->assertGreaterThan(time() + 3500, $opts['expires']);
        $this->assertLessThan(time() + 3700, $opts['expires']);
    }
}
