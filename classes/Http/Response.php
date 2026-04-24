<?php

namespace AIJOH\Http;

class Response {

    /**
     * JSON形式でデータ出力する。
     * @param array $data
     * @return void
     */
    public static function json( array $data ) : void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }


    /**
     * JSON 形式の標準レスポンスを出力して終了する。
     * 不要なキー（null や空配列・空文字）は省略する。
     *
     * レスポンス形式:
     *   { "status": true|false,
     *     "message": "...",            // 全体メッセージ（任意）
     *     "errors":  { "field": "..." },// フィールド単位エラー（任意）
     *     "redirect": "URL" }           // リダイレクト先（任意）
     *
     * @param bool        $status   true=成功 / false=失敗
     * @param string|null $message  全体メッセージ（Verify エラー、送信完了など）
     * @param array|null  $errors   フィールド単位のエラー連想配列
     * @param string|null $redirect リダイレクト先 URL
     */
    public static function jsonResults(
        bool $status,
        ?string $message = null,
        ?array $errors = null,
        ?string $redirect = null,
    ) : never {
        $response = [ 'status' => $status ];
        if ( $message !== null && $message !== '' ) {
            $response['message'] = $message;
        }
        if ( $errors !== null && $errors !== [] ) {
            $response['errors'] = $errors;
        }
        if ( $redirect !== null && $redirect !== '' ) {
            $response['redirect'] = $redirect;
        }
        self::json($response);
        exit;
    }

}
