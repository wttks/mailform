<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Validation;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Exception\ValidationRuleException;
use PHPUnit\Framework\TestCase;

/**
 * ValidateInList の統合テスト
 */
class ValidateInListTest extends TestCase {

    private function radioConfig() : array {
        return [
            'gender' => [
                'title' => '性別',
                'rule'  => 'required|in_list:male,female,other',
            ],
        ];
    }

    private function checkboxConfig() : array {
        return [
            'interests' => [
                'title' => '興味のある分野',
                'rule'  => 'array|in_list:web,mobile,ai,security',
            ],
        ];
    }


    // ---- 単一値（ラジオ・セレクト） ----

    public function test_許可リストに含まれる単一値は_OK(): void {
        $v = new Validation($this->radioConfig());
        $result = $v->validated(['gender' => 'female']);
        $this->assertSame('female', $result['gender']);
    }

    public function test_許可リストに含まれない単一値は_NG(): void {
        $v = new Validation($this->radioConfig());
        $this->expectException(ValidationException::class);
        $v->validated(['gender' => 'unknown']);
    }


    // ---- 配列値（チェックボックス） ----

    public function test_全要素が許可リストに含まれる配列は_OK(): void {
        $v = new Validation($this->checkboxConfig());
        $result = $v->validated(['interests' => ['web', 'ai']]);
        $this->assertSame(['web', 'ai'], $result['interests']);
    }

    public function test_配列の一部に許可外があれば_NG(): void {
        $v = new Validation($this->checkboxConfig());
        $this->expectException(ValidationException::class);
        $v->validated(['interests' => ['web', 'invalid']]);
    }

    public function test_空配列は_OK_要素ゼロのため(): void {
        $v = new Validation($this->checkboxConfig());
        $result = $v->validated(['interests' => []]);
        $this->assertSame([], $result['interests']);
    }


    // ---- 空値 ----

    public function test_空文字は_required_なしならスキップされる(): void {
        $v = new Validation([
            'gender' => ['title' => '性別', 'rule' => 'in_list:male,female'],
        ]);
        $result = $v->validated(['gender' => '']);
        // nullable 相当: 空値は in_list の判定をスキップ
        $this->assertSame('', $result['gender']);
    }


    // ---- 引数省略 ----

    public function test_引数なしは_例外(): void {
        $v = new Validation([
            'gender' => ['title' => '性別', 'rule' => 'required|in_list'],
        ]);
        $this->expectException(ValidationRuleException::class);
        $v->validated(['gender' => 'male']);
    }


    // ---- エラーメッセージ ----

    public function test_エラーメッセージに_title_が展開される(): void {
        $v = new Validation($this->radioConfig());
        try {
            $v->validated(['gender' => 'unknown']);
            $this->fail('ValidationException が投げられるべき');
        } catch ( ValidationException $e ) {
            $this->assertSame('性別は指定された値の中から選択してください。', $e->getErrors()['gender']);
        }
    }

    public function test_カスタムメッセージで_allowed_を表示できる(): void {
        $v = new Validation([
            'gender' => [
                'title'   => '性別',
                'rule'    => 'required|in_list:male,female,other',
                'message' => ['in_list' => ':titleは :allowed のいずれかを選択してください。'],
            ],
        ]);
        try {
            $v->validated(['gender' => 'unknown']);
            $this->fail('ValidationException が投げられるべき');
        } catch ( ValidationException $e ) {
            $this->assertSame('性別は male,female,other のいずれかを選択してください。', $e->getErrors()['gender']);
        }
    }
}
