<?php

namespace AIJOH\Test\Validation;

use AIJOH\Hook\HookRegistry;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Validation;
use PHPUnit\Framework\TestCase;

/**
 * 動的バリデーション hook (validate_rules / validate.{field} / validate) の統合テスト。
 *
 * - validate_rules : 検証直前にルール定義を $data に応じて加工
 * - validate.{f}   : per-field の追加チェック（既存ルールの直後）
 * - validate       : 全エラー配列の総合加工
 */
class ValidationWithDynamicHooksTest extends TestCase {

    private function baseConfig() : array {
        return [
            'name'  => [ 'title' => '名前',     'rule' => 'required|string|max:100' ],
            'email' => [ 'title' => 'メール', 'rule' => 'required|email' ],
        ];
    }

    // ============================================================
    // B: validate_rules — ルール定義そのものを動的に変える
    // ============================================================

    public function test_validate_rules_でルールを追加して既存通過データを弾ける() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate_rules', function ( array $rules, array $data ) : array {
            // 仮想的な「会員区分=corporate なら company が必須」
            if ( ( $data['plan'] ?? '' ) === 'corporate' ) {
                $rules['company'] = [ 'title' => '会社名', 'rule' => 'required|string' ];
            }
            return $rules;
        });

        $validation = new Validation($this->baseConfig());
        $this->expectException(ValidationException::class);
        try {
            $validation->validated([
                'name'  => 'Yamada',
                'email' => 'a@example.com',
                'plan'  => 'corporate',
                // company を意図的に与えない
            ], $hooks);
        } catch ( ValidationException $e ) {
            $this->assertArrayHasKey('company', $e->getErrors());
            throw $e;
        }
    }


    public function test_validate_rules_でルールを削除すれば検証スキップ() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate_rules', function ( array $rules, array $data ) : array {
            unset($rules['email']); // email チェック自体を消す
            return $rules;
        });

        $validation = new Validation($this->baseConfig());
        $result = $validation->validated([
            'name'  => 'Yamada',
            'email' => 'not-an-email', // 通常は email ルールで弾かれる
        ], $hooks);

        $this->assertSame('Yamada', $result['name']);
    }


    public function test_validate_rules_を渡さなければ動的ルールは効かない() : void {
        $hooks = new HookRegistry(); // 空のレジストリ

        $validation = new Validation($this->baseConfig());
        $result = $validation->validated([
            'name'  => 'Yamada',
            'email' => 'a@example.com',
        ], $hooks);
        $this->assertSame('Yamada', $result['name']);
    }


    // ============================================================
    // C: validate.{field} — 1 フィールドの追加チェック
    // ============================================================

    public function test_validate_field_filter_で追加エラーを足せる() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate.email', function ( ?string $error, mixed $value, array $data ) : ?string {
            if ( $error !== null ) return $error;
            if ( str_contains((string)$value, 'blocked.com') ) {
                return 'このドメインは利用できません。';
            }
            return null;
        });

        $validation = new Validation($this->baseConfig());
        $this->expectException(ValidationException::class);
        try {
            $validation->validated([
                'name'  => 'Yamada',
                'email' => 'spam@blocked.com',
            ], $hooks);
        } catch ( ValidationException $e ) {
            $this->assertSame('このドメインは利用できません。', $e->getErrors()['email']);
            throw $e;
        }
    }


    public function test_validate_field_filter_で既存エラーを差し替えられる() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate.email', function ( ?string $error, mixed $value, array $data ) : ?string {
            return $error === null ? null : 'メールアドレスを正しく入力してください。';
        });

        $validation = new Validation($this->baseConfig());
        try {
            $validation->validated([
                'name'  => 'Yamada',
                'email' => 'not-an-email',
            ], $hooks);
            $this->fail('ValidationException が発生するはず');
        } catch ( ValidationException $e ) {
            $this->assertSame('メールアドレスを正しく入力してください。', $e->getErrors()['email']);
        }
    }


    public function test_validate_field_filter_でnullを返せば成功化できる() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate.email', fn( ?string $error ): ?string => null);

        $validation = new Validation($this->baseConfig());
        // 通常は email ルールで弾かれるが、hook が null を返したので成功扱い
        $result = $validation->validated([
            'name'  => 'Yamada',
            'email' => 'not-an-email',
        ], $hooks);
        $this->assertSame('not-an-email', $result['email']);
    }


    // ============================================================
    // A: validate — 全エラー配列を総合加工
    // ============================================================

    public function test_validate_filter_で横断エラーを追加できる() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate', function ( array $errors, array $data ) : array {
            // 仮想的な「name と email のドメインが一致していなければ NG」
            if ( str_starts_with((string)( $data['email'] ?? '' ), 'admin@') ) {
                $errors['email'] = 'admin で始まるメールは登録できません。';
            }
            return $errors;
        });

        $validation = new Validation($this->baseConfig());
        try {
            $validation->validated([
                'name'  => 'Yamada',
                'email' => 'admin@example.com', // 本来は email ルール OK
            ], $hooks);
            $this->fail('ValidationException が発生するはず');
        } catch ( ValidationException $e ) {
            $this->assertSame('admin で始まるメールは登録できません。', $e->getErrors()['email']);
        }
    }


    public function test_validate_filter_で空配列を返せば成功化できる() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate', fn( array $errors, array $data ): array => []);

        $validation = new Validation($this->baseConfig());
        // 通常は name / email 両方欠けているので失敗するが、hook で消すので成功
        $result = $validation->validated([], $hooks);
        $this->assertIsArray($result);
    }


    // ============================================================
    // 3 hook 併用 — 順序検証
    // ============================================================

    public function test_3hook併用時の発火順序_rules_field_all() : void {
        $log = [];

        $hooks = new HookRegistry();
        $hooks->on('validate_rules', function ( array $rules, array $data ) use ( &$log ) : array {
            $log[] = 'rules';
            return $rules;
        });
        $hooks->on('validate.name', function ( ?string $error, mixed $value, array $data ) use ( &$log ) : ?string {
            $log[] = 'field:name';
            return $error;
        });
        $hooks->on('validate.email', function ( ?string $error, mixed $value, array $data ) use ( &$log ) : ?string {
            $log[] = 'field:email';
            return $error;
        });
        $hooks->on('validate', function ( array $errors, array $data ) use ( &$log ) : array {
            $log[] = 'all';
            return $errors;
        });

        $validation = new Validation($this->baseConfig());
        $validation->validated([
            'name'  => 'Yamada',
            'email' => 'a@example.com',
        ], $hooks);

        $this->assertSame([ 'rules', 'field:name', 'field:email', 'all' ], $log);
    }


    public function test_validate_rules_で追加したフィールドにも_field_filter_が効く() : void {
        $hooks = new HookRegistry();
        $hooks->on('validate_rules', function ( array $rules, array $data ) : array {
            $rules['extra'] = [ 'title' => '追加', 'rule' => 'required|string' ];
            return $rules;
        });
        $hooks->on('validate.extra', function ( ?string $error, mixed $value, array $data ) : ?string {
            // 既存エラー（required で空）が出ているはず
            return $error === null ? null : '追加項目を入れてください。';
        });

        $validation = new Validation($this->baseConfig());
        try {
            $validation->validated([
                'name'  => 'Yamada',
                'email' => 'a@example.com',
            ], $hooks);
            $this->fail('ValidationException が発生するはず');
        } catch ( ValidationException $e ) {
            $this->assertSame('追加項目を入れてください。', $e->getErrors()['extra']);
        }
    }
}
