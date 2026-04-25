<?php

namespace AIJOH\Test\AISpam;

use AIJOH\AISpam\SpamJudgement;
use PHPUnit\Framework\TestCase;

class SpamJudgementTest extends TestCase {

    public function test_clean_は_isSpam_false_score_0_を返す() : void {
        $j = SpamJudgement::clean('reason');
        $this->assertFalse($j->isSpam);
        $this->assertSame(0.0, $j->score);
        $this->assertSame('reason', $j->reason);
    }

    public function test_spam_は_isSpam_true_を返す() : void {
        $j = SpamJudgement::spam(0.95, '営業メール');
        $this->assertTrue($j->isSpam);
        $this->assertSame(0.95, $j->score);
        $this->assertSame('営業メール', $j->reason);
    }

}
