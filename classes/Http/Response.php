<?php

namespace AIJOH\Http;

class Response {
    
    /**
     * JSON形式でデータ出力する。
     * @param array $data
     * @return void
     */
    public static function json(array $data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }
    
    public static function jsonResults(bool $result, string $message, $data = []) {
        self::json([
            'status'  => $result,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }
    
}