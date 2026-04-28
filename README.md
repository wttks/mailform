# wttks/mailform

PHP メールフォームライブラリ。**多段防御セキュリティ**（CSRF / Honeypot / レート制限 / AI スパム判定）と**入力途中データの一時保存**（HttpOnly Cookie + 暗号化）を内蔵。

> このリポジトリは Composer 配布用です。開発リポジトリは `wttks/mailform-dev`（モノレポ）から
> CI で自動的に subtree split されます。**Issue / PR は開発リポジトリにお願いします。**

## インストール

```bash
composer require wttks/mailform
```

## 動作環境

- PHP 8.2 / 8.3 / 8.4 / 8.5（PHPUnit 全テスト OK）
- PHP 8.0 / 8.1 は未対応（独立型ヒント `true|string` 等が parse error）
- 必須拡張: ext-mbstring / ext-fileinfo / ext-curl / ext-sodium / ext-zlib

## 主な機能

- **バリデーション**: 必須・型・正規表現・郵便番号・電話番号・フリガナ・日本語含有判定 ほか
- **動的宛先**: フォーム値で送信先を切り替え（`map` 形式 / Closure）
- **確認画面フロー**: 入力 → 確認 → 送信 のセッション保持（添付ファイル持ち越し対応）
- **マルチフォーム**: 複数フォームで共通設定を共有
- **多段セキュリティ**: CSRF / Honeypot / IP・セッションベースレート制限 / AI スパム判定
- **i18n**: 日本語デフォルト + 英語翻訳マップ同梱
- **draft 機能**: HttpOnly Cookie + sodium 暗号化で入力途中データを安全に一時保存

## クイックスタート

```php
require_once 'vendor/autoload.php';

$form = new \AIJOH\Form\Form([
    'verify' => [
        'csrfToken' => true,
        'honeypot' => ['name' => 'website'],
    ],
    'validation' => [
        'name'    => ['title' => 'お名前', 'rule' => 'required|string|max:100'],
        'email'   => ['title' => 'メールアドレス', 'rule' => 'required|email'],
        'message' => ['title' => 'お問い合わせ内容', 'rule' => 'required|string|max:2000'],
    ],
    'sender' => [
        '管理者通知' => [
            'from' => 'no-reply@example.com',
            'to'   => 'admin@example.com',
            'subject' => '【お問い合わせ】{name}様より',
            'body' => "お名前: {name}\nメール: {email}\n\n{message}",
        ],
    ],
]);

$form->receive();   // POST なら処理、GET なら何もしない
```

## ドキュメント

開発リポジトリ `wttks/mailform-dev` の `docs/` 配下:

- `README.md` — プロジェクト概要・クイックスタート
- `docs/CONFIG.md` — 設定リファレンス（draft 機能含む）
- `docs/SECURITY.md` — セキュリティ機能の詳細
- `docs/DEPLOY.md` — 本番デプロイガイド
- `docs/MULTIFORM.md` — マルチフォーム構成
- `docs/CUSTOM_VALIDATION.md` — カスタム業務検証
- `docs/PRIVACY_DRAFT.md` — draft 機能のプライバシーポリシー文面サンプル
- `docs/snippets/` — HTML スニペット集

## サンプル

開発リポジトリ `wttks/mailform-dev` の `public/` 配下に 4 サンプル同梱:

- `contact/` — 直接送信フォーム
- `confirm-sample/` — 確認画面付きフォーム
- `html-sample/` — 純 HTML + JS 注入型
- `draft-sample/` — draft 機能を有効化したフォーム

## 開発・コントリビューション

開発・Issue・PR は開発リポジトリへ:

https://github.com/wttks/mailform-dev

## ライセンス

MIT License — `LICENSE` 参照
