<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\DateUtil;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

/**
 * DateUtil のテスト
 */
class DateUtilTest extends TestCase {

    // ---- isWeekDay() ----

    public function test_isWeekDay_数値0は日曜日でtrue(): void {
        $this->assertTrue(DateUtil::isWeekDay(0));
    }

    public function test_isWeekDay_数値6は土曜日でtrue(): void {
        $this->assertTrue(DateUtil::isWeekDay(6));
    }

    public function test_isWeekDay_数値7はfalse(): void {
        $this->assertFalse(DateUtil::isWeekDay(7));
    }

    public function test_isWeekDay_数値マイナス1はfalse(): void {
        $this->assertFalse(DateUtil::isWeekDay(-1));
    }

    public function test_isWeekDay_日本語曜日でtrue(): void {
        $this->assertTrue(DateUtil::isWeekDay('月'));
    }

    public function test_isWeekDay_日本語曜日フルでtrue(): void {
        $this->assertTrue(DateUtil::isWeekDay('月曜日'));
    }

    public function test_isWeekDay_英語短縮でtrue(): void {
        $this->assertTrue(DateUtil::isWeekDay('mon'));
    }

    public function test_isWeekDay_英語大文字でもtrue(): void {
        $this->assertTrue(DateUtil::isWeekDay('MON'));
    }

    public function test_isWeekDay_英語フルでtrue(): void {
        $this->assertTrue(DateUtil::isWeekDay('monday'));
    }

    public function test_isWeekDay_無効な文字列はfalse(): void {
        $this->assertFalse(DateUtil::isWeekDay('invalid'));
    }

    // ---- getWeekDayNumber() ----

    public function test_getWeekDayNumber_数値0はそのまま0(): void {
        $this->assertSame(0, DateUtil::getWeekDayNumber(0));
    }

    public function test_getWeekDayNumber_数値6はそのまま6(): void {
        $this->assertSame(6, DateUtil::getWeekDayNumber(6));
    }

    public function test_getWeekDayNumber_数値7はfalse(): void {
        $this->assertFalse(DateUtil::getWeekDayNumber(7));
    }

    public function test_getWeekDayNumber_日本語日は0(): void {
        $this->assertSame(0, DateUtil::getWeekDayNumber('日'));
    }

    public function test_getWeekDayNumber_日本語土は6(): void {
        $this->assertSame(6, DateUtil::getWeekDayNumber('土'));
    }

    public function test_getWeekDayNumber_英語sunは0(): void {
        $this->assertSame(0, DateUtil::getWeekDayNumber('sun'));
    }

    public function test_getWeekDayNumber_英語satは6(): void {
        $this->assertSame(6, DateUtil::getWeekDayNumber('sat'));
    }

    public function test_getWeekDayNumber_英語sundayは0(): void {
        $this->assertSame(0, DateUtil::getWeekDayNumber('sunday'));
    }

    public function test_getWeekDayNumber_無効な文字列はfalse(): void {
        $this->assertFalse(DateUtil::getWeekDayNumber('invalid'));
    }

    // ---- formatDate() ----

    public function test_formatDate_Ym_dフォーマットで日付を取得できる(): void {
        $result = DateUtil::formatDate(['Y-m-d'], '2024-01-15');
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-01-15', $result->format('Y-m-d'));
    }

    public function test_formatDate_複数フォーマットで最初に合致したものを返す(): void {
        $result = DateUtil::formatDate(['Y/m/d', 'Y-m-d'], '2024/01/15');
        $this->assertSame('2024-01-15', $result->format('Y-m-d'));
    }

    public function test_formatDate_合致しない場合はnullを返す(): void {
        $result = DateUtil::formatDate(['Y-m-d'], 'not-a-date');
        $this->assertNull($result);
    }

    public function test_formatDate_うるう年2024年2月29日はOK(): void {
        $result = DateUtil::formatDate(['Y-m-d'], '2024-02-29');
        $this->assertNotNull($result);
        $this->assertSame('2024-02-29', $result->format('Y-m-d'));
    }

    public function test_formatDate_非うるう年2023年2月29日はNG(): void {
        $result = DateUtil::formatDate(['Y-m-d'], '2023-02-29');
        $this->assertNull($result);
    }

    // ---- checkDateRange() / parseDateRange() ----

    public function test_checkDateRange_単一日付はtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('2024-01-15'));
    }

    public function test_checkDateRange_スラッシュ区切りはtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('2024/01/15'));
    }

    public function test_checkDateRange_YYYYMMDD形式はtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('20240115'));
    }

    public function test_checkDateRange_範囲指定ハイフンはtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('2024/01/01-2024/01/31'));
    }

    public function test_checkDateRange_範囲指定波ダッシュはtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('2024/01/01～2024/01/31'));
    }

    public function test_checkDateRange_範囲指定全角波ダッシュはtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('2024/01/01〜2024/01/31'));
    }

    public function test_checkDateRange_開始のみはtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('2024/01/01-'));
    }

    public function test_checkDateRange_終了のみはtrue(): void {
        $this->assertTrue(DateUtil::checkDateRange('-2024/01/31'));
    }

    public function test_checkDateRange_無効な文字列はfalse(): void {
        $this->assertFalse(DateUtil::checkDateRange('not-a-date'));
    }

    // ---- createDateList() ----

    public function test_createDateList_2日間の配列が生成される(): void {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-01-03');
        $list = DateUtil::createDateList($start, $end);
        $this->assertCount(3, $list);
        $this->assertSame('2024-01-01', $list[0]->format('Y-m-d'));
        $this->assertSame('2024-01-03', $list[2]->format('Y-m-d'));
    }

    public function test_createDateList_同じ日付は1要素(): void {
        $date = new DateTimeImmutable('2024-05-01');
        $list = DateUtil::createDateList($date, $date);
        $this->assertCount(1, $list);
    }

    // ---- toDateTimeImmutable() ----

    public function test_toDateTimeImmutable_DateTimeImmutableはそのまま返す(): void {
        $dt = new DateTimeImmutable('2024-01-01');
        $result = DateUtil::toDateTimeImmutable($dt);
        $this->assertSame($dt, $result);
    }

    public function test_toDateTimeImmutable_DateTimeはDateTimeImmutableに変換(): void {
        $dt = new \DateTime('2024-01-01');
        $result = DateUtil::toDateTimeImmutable($dt);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-01-01', $result->format('Y-m-d'));
    }

    public function test_toDateTimeImmutable_文字列をDateTimeImmutableに変換(): void {
        $result = DateUtil::toDateTimeImmutable('2024-06-15');
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    public function test_toDateTimeImmutable_UNIXタイムスタンプを変換(): void {
        $result = DateUtil::toDateTimeImmutable(0);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    public function test_toDateTimeImmutable_無効な値は例外を投げる(): void {
        $this->expectException(\InvalidArgumentException::class);
        DateUtil::toDateTimeImmutable(3.14);
    }

    // ---- getYearOptionList() ----

    public function test_getYearOptionList_範囲が正しい(): void {
        $list = DateUtil::getYearOptionList(20, 60);
        $nowYear = (int)date('Y');
        $this->assertContains($nowYear - 20, $list);
        $this->assertContains($nowYear - 60, $list);
    }

    // ---- getMonthOptionList() / getDayOptionList() ----

    public function test_getMonthOptionList_1から12の配列(): void {
        $list = DateUtil::getMonthOptionList();
        $this->assertSame(range(1, 12), $list);
    }

    public function test_getDayOptionList_1から31の配列(): void {
        $list = DateUtil::getDayOptionList();
        $this->assertSame(range(1, 31), $list);
    }
}
