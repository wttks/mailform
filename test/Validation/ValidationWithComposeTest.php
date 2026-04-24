<?php

namespace AIJOH\Test\Validation;

use AIJOH\Validation\Validation;
use AIJOH\Validation\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Validation と compose の統合テスト
 */
class ValidationWithComposeTest extends TestCase {

    /**
     * 生年月日3項目 + 結合後 birthday の標準的な設定
     */
    private function birthdayConfig() : array {
        return [
            'y' => ['title' => '年', 'rule' => 'required|int', 'output' => false],
            'm' => ['title' => '月', 'rule' => 'required|int', 'output' => false],
            'd' => ['title' => '日', 'rule' => 'required|int', 'output' => false],
            'birthday' => [
                'title'   => '生年月日',
                'compose' => ['join' => ['y', 'm', 'd'], 'separator' => '-', 'pad' => [4, 2, 2]],
                'rule'    => 'required|date',
            ],
        ];
    }

    public function test_compose_適用後の結合値で_date_バリデーションが通る(): void {
        $validation = new Validation($this->birthdayConfig());
        $result = $validation->validated([
            'y' => '1990',
            'm' => '1',
            'd' => '5',
        ]);
        $this->assertSame('1990-01-05', $result['birthday']);
    }

    public function test_compose_元フィールドが空なら_birthday_は未生成_required_でエラー(): void {
        $validation = new Validation($this->birthdayConfig());
        $this->expectException(ValidationException::class);
        try {
            $validation->validated([
                'y' => '1990',
                'm' => '',
                'd' => '5',
            ]);
        } catch ( ValidationException $e ) {
            $errors = $e->getErrors();
            // 元フィールド m の required エラーが出る
            $this->assertArrayHasKey('m', $errors);
            // birthday は compose スキップされたので date エラーは出ず、required で出る
            $this->assertArrayHasKey('birthday', $errors);
            throw $e;
        }
    }

    public function test_compose_は_beforeFormat_の後に適用される(): void {
        // Validation を継承して beforeFormat をオーバーライドし、
        // beforeFormat → compose の順序を確認する
        $validation = new class($this->birthdayConfig()) extends Validation {
            public function beforeFormat( array $data ) : array {
                // 生年月日の値を beforeFormat で書き換える
                $data['y'] = '2000';
                return $data;
            }
        };
        $result = $validation->validated([
            'y' => '1990',
            'm' => '1',
            'd' => '5',
        ]);
        // beforeFormat で 2000 に書き換えられた値が compose に反映されている
        $this->assertSame('2000-01-05', $result['birthday']);
    }

    public function test_compose_氏名_スペース区切り(): void {
        $config = [
            'last_name'  => ['title' => '姓', 'rule' => 'required|string', 'output' => false],
            'first_name' => ['title' => '名', 'rule' => 'required|string', 'output' => false],
            'name' => [
                'title'   => 'お名前',
                'compose' => ['join' => ['last_name', 'first_name'], 'separator' => ' '],
                'rule'    => 'required|string|max:100',
            ],
        ];
        $validation = new Validation($config);
        $result = $validation->validated([
            'last_name'  => '山田',
            'first_name' => '太郎',
        ]);
        $this->assertSame('山田 太郎', $result['name']);
    }

    public function test_compose_電話番号_ハイフン区切り(): void {
        $config = [
            'tel1' => ['title' => '電話1', 'rule' => 'required', 'output' => false],
            'tel2' => ['title' => '電話2', 'rule' => 'required', 'output' => false],
            'tel3' => ['title' => '電話3', 'rule' => 'required', 'output' => false],
            'tel' => [
                'title'   => '電話番号',
                'compose' => ['join' => ['tel1', 'tel2', 'tel3'], 'separator' => '-'],
                'rule'    => 'required|telephone',
            ],
        ];
        $validation = new Validation($config);
        $result = $validation->validated([
            'tel1' => '090',
            'tel2' => '1234',
            'tel3' => '5678',
        ]);
        $this->assertSame('090-1234-5678', $result['tel']);
    }

    public function test_compose_設定がないフィールドは_dataはそのまま(): void {
        $config = [
            'name' => ['title' => '名前', 'rule' => 'required|string'],
        ];
        $validation = new Validation($config);
        $result = $validation->validated(['name' => '山田']);
        $this->assertSame('山田', $result['name']);
    }

}
