<?php
/**
 * Verify（送信元検証）の設定。
 *
 * 各キーが Verify クラス名 (Verify{Name}) に対応する。
 * - true            : 設定なしで有効化
 * - false           : 明示的に無効化
 * - 連想配列        : コンストラクタに渡す設定
 *
 * honeypot の name は bot が入力しがちな現実的な値にしておくと引っかかりやすい。
 * フォームごとに変えるとさらに学習されにくい。
 */
return [
    'csrfToken' => true,
    'honeypot'  => ['name' => 'website'],
];
