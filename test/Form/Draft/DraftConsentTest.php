<?php

namespace AIJOH\Form\Draft;

use PHPUnit\Framework\TestCase;

class DraftConsentTest extends TestCase {

    // ---- isManagedByMailform ----

    public function test_モード省略時はdisabled扱い_mailform管理外() {
        $consent = new DraftConsent([]);
        $this->assertFalse($consent->isManagedByMailform());
    }


    public function test_builtinのみmailform管理() {
        $this->assertTrue((new DraftConsent([ 'mode' => 'builtin' ]))->isManagedByMailform());
        $this->assertFalse((new DraftConsent([ 'mode' => 'callback' ]))->isManagedByMailform());
        $this->assertFalse((new DraftConsent([ 'mode' => 'disabled' ]))->isManagedByMailform());
    }


    // ---- isAllowed: builtin 以外は常に許可 ----

    public function test_disabledモードは常に許可() {
        $consent = new DraftConsent([ 'mode' => 'disabled' ]);
        $this->assertTrue($consent->isAllowed(null));
        $this->assertTrue($consent->isAllowed(''));
        $this->assertTrue($consent->isAllowed('anything'));
    }


    public function test_callbackモードは常に許可() {
        $consent = new DraftConsent([ 'mode' => 'callback' ]);
        $this->assertTrue($consent->isAllowed(null));
        $this->assertTrue($consent->isAllowed(''));
    }


    public function test_モード未指定は常に許可_disabled相当() {
        $consent = new DraftConsent([]);
        $this->assertTrue($consent->isAllowed(null));
    }


    // ---- builtin + opt-in ----

    public function test_builtin_optin_grantedで許可() {
        $consent = new DraftConsent([ 'mode' => 'builtin', 'behavior' => 'opt-in' ]);
        $this->assertTrue($consent->isAllowed('granted'));
    }


    public function test_builtin_optin_未表明とrevokedとnullは拒否() {
        $consent = new DraftConsent([ 'mode' => 'builtin', 'behavior' => 'opt-in' ]);
        $this->assertFalse($consent->isAllowed(null));
        $this->assertFalse($consent->isAllowed(''));
        $this->assertFalse($consent->isAllowed('revoked'));
    }


    // ---- builtin + opt-out ----

    public function test_builtin_optout_デフォルト許可() {
        $consent = new DraftConsent([ 'mode' => 'builtin', 'behavior' => 'opt-out' ]);
        $this->assertTrue($consent->isAllowed(null));
        $this->assertTrue($consent->isAllowed(''));
    }


    public function test_builtin_optout_grantedも許可() {
        $consent = new DraftConsent([ 'mode' => 'builtin', 'behavior' => 'opt-out' ]);
        $this->assertTrue($consent->isAllowed('granted'));
    }


    public function test_builtin_optout_revokedで拒否() {
        $consent = new DraftConsent([ 'mode' => 'builtin', 'behavior' => 'opt-out' ]);
        $this->assertFalse($consent->isAllowed('revoked'));
    }


    // ---- policy_version ----

    public function test_policy_version一致なら同意有効() {
        $consent = new DraftConsent([
            'mode'           => 'builtin',
            'behavior'       => 'opt-in',
            'policy_version' => '2026-04-28',
        ]);
        $this->assertTrue($consent->isAllowed('granted:2026-04-28'));
    }


    public function test_policy_version不一致は同意リセット_optinは拒否() {
        $consent = new DraftConsent([
            'mode'           => 'builtin',
            'behavior'       => 'opt-in',
            'policy_version' => '2026-04-28',
        ]);
        $this->assertFalse($consent->isAllowed('granted:2025-01-01'));
        $this->assertFalse($consent->isAllowed('granted'));   // バージョン無し
    }


    public function test_policy_version不一致は同意リセット_optoutは過去revokedをリセットしてデフォルト許可() {
        $consent = new DraftConsent([
            'mode'           => 'builtin',
            'behavior'       => 'opt-out',
            'policy_version' => '2026-04-28',
        ]);
        $this->assertTrue($consent->isAllowed('revoked:2025-01-01'));
        $this->assertTrue($consent->isAllowed('revoked'));   // バージョン無し
    }


    // ---- 不正な値 ----

    public function test_不正なstatusのCookie値は同意なし扱い() {
        $optin = new DraftConsent([ 'mode' => 'builtin', 'behavior' => 'opt-in' ]);
        $this->assertFalse($optin->isAllowed('garbage'));

        $optout = new DraftConsent([ 'mode' => 'builtin', 'behavior' => 'opt-out' ]);
        $this->assertTrue($optout->isAllowed('garbage'));   // opt-out は revoked でない限り許可
    }


    // ---- makeConsentCookieValue ----

    public function test_makeConsentCookieValue_バージョン無し() {
        $consent = new DraftConsent([ 'mode' => 'builtin' ]);
        $this->assertSame('granted', $consent->makeConsentCookieValue('granted'));
        $this->assertSame('revoked', $consent->makeConsentCookieValue('revoked'));
    }


    public function test_makeConsentCookieValue_バージョン有り() {
        $consent = new DraftConsent([
            'mode'           => 'builtin',
            'policy_version' => '2026-04-28',
        ]);
        $this->assertSame('granted:2026-04-28', $consent->makeConsentCookieValue('granted'));
        $this->assertSame('revoked:2026-04-28', $consent->makeConsentCookieValue('revoked'));
    }


    public function test_makeConsentCookieValue_不正なstatusは例外() {
        $consent = new DraftConsent([ 'mode' => 'builtin' ]);
        $this->expectException(\InvalidArgumentException::class);
        $consent->makeConsentCookieValue('invalid');
    }


    // ---- ゲッター ----

    public function test_getter_default値() {
        $consent = new DraftConsent([]);
        $this->assertSame('disabled', $consent->getMode());
        $this->assertSame('opt-in', $consent->getBehavior());
        $this->assertNull($consent->getPolicyVersion());
    }


    public function test_getter_明示値() {
        $consent = new DraftConsent([
            'mode'           => 'callback',
            'behavior'       => 'opt-out',
            'policy_version' => '2026-04-28',
        ]);
        $this->assertSame('callback', $consent->getMode());
        $this->assertSame('opt-out', $consent->getBehavior());
        $this->assertSame('2026-04-28', $consent->getPolicyVersion());
    }
}
