<?php

namespace AIJOH\Validation\Parser;

class TitleManager {

    private array $titles = [];

    public function __construct() {
    }

    /**
     * キーとタイトルを設定する。
     * @param string $key
     * @param string $title
     * @param bool|string $output
     * @return void
     */
    public function set( string $key, string $title, bool|string $output = true ) : void {
        $this->titles[ $key ]['title'] = $title;
        $this->titles[ $key ]['output'] = $output;
    }


    /**
     * 出力可能な全てのタイトル情報を取得する。
     * @return array
     */
    public function getAllTitle() : array {
        return array_filter($this->titles, fn( $value ) => $value['output'] !== false);
    }


    /**
     * キーに対応するタイトルを取得する。
     * output フラグに関わらずタイトルを返す。
     * @param string $key
     * @return string タイトル（未登録の場合はキーをそのまま返す）
     */
    public function getTitle( string $key ) : string {
        return $this->findTitle($key, respectOutput: false) ?? $key;
    }


    /**
     * キーに対応する出力可能なタイトルを取得する。
     * output が false の場合は空文字を返す。
     * @param string $key
     * @return string タイトル（未登録または非出力の場合はキーをそのまま返す）
     */
    public function getOutputableTitle( string $key ) : string {
        return $this->findTitle($key, respectOutput: true) ?? $key;
    }


    /**
     * キーに対応するタイトルを検索する共通ロジック。
     * @param string $key
     * @param bool $respectOutput true の場合、output=false のタイトルは空文字を返す
     * @return string|null タイトル文字列、未登録の場合は null
     */
    private function findTitle( string $key, bool $respectOutput ) : ?string {
        // 完全一致
        if ( array_key_exists($key, $this->titles) ) {
            $entry = $this->titles[ $key ];
            if ( $respectOutput && ! $entry['output'] ) {
                return "";
            }
            return $entry['title'];
        }

        // ワイルドカード一致（fnmatch）
        foreach ( $this->titles as $pattern => $entry ) {
            if ( fnmatch($pattern, $key) ) {
                if ( $respectOutput && ! $entry['output'] ) {
                    return "";
                }
                return $entry['title'];
            }
        }

        return null;
    }
}
