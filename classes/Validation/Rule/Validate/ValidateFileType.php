<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\ArrayUtil;
use AIJOH\Util\FileUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;

class ValidateFileType extends ValidateBase {

    /**
     * デフォルトのエイリアス定義。
     * キー: エイリアス名、値: ['mime' => MIMEパターンの配列, 'ext' => 拡張子の配列]
     */
    private static array $aliases = [
        'pdf'        => [
            'mime' => ['application/pdf'],
            'ext'  => ['pdf'],
        ],
        'word'       => [
            'mime' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'ext'  => ['doc', 'docx'],
        ],
        'excel'      => [
            'mime' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ext'  => ['xls', 'xlsx'],
        ],
        'powerpoint' => [
            'mime' => ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'ext'  => ['ppt', 'pptx'],
        ],
        'image'      => [
            // image/* は許可するが SVG は XSS リスクのため除外する。
            // SVG を許可したい場合は明示的に file_type:svg を指定する。
            'mime' => ['image/*'],
            'ext'  => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'],
        ],
        'svg'        => [
            'mime' => ['image/svg+xml'],
            'ext'  => ['svg'],
        ],
        'video'      => [
            'mime' => ['video/*'],
            'ext'  => ['mp4', 'mpeg', 'avi', 'mov', 'wmv', 'flv', 'webm'],
        ],
        'audio'      => [
            'mime' => ['audio/*'],
            'ext'  => ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a'],
        ],
        'text'       => [
            'mime' => ['text/*'],
            'ext'  => ['txt', 'csv', 'tsv', 'html', 'htm', 'xml', 'json'],
        ],
        'zip'        => [
            'mime' => ['application/zip', 'application/x-zip-compressed'],
            'ext'  => ['zip'],
        ],
    ];


    public function __construct( bool $isRequiredCheck = false ) {
        parent::__construct($isRequiredCheck);
    }


    /**
     * エイリアスを追加・上書きする。
     * @param string $alias エイリアス名
     * @param array $mime 許可するMIMEタイプの配列
     * @param array $ext 許可する拡張子の配列
     * @return void
     */
    public static function addAlias( string $alias, array $mime, array $ext ) : void {
        self::$aliases[ $alias ] = ['mime' => $mime, 'ext' => $ext];
    }


    /**
     * 複数のエイリアスをまとめて追加・上書きする。
     * @param array $aliases ['alias' => ['mime' => [...], 'ext' => [...]]] 形式
     * @return void
     */
    public static function addAliases( array $aliases ) : void {
        foreach ( $aliases as $alias => $definition ) {
            self::$aliases[ $alias ] = $definition;
        }
    }


    public function getErrorMessage() : string {
        return ':titleは:typesのファイルを指定してください。';
    }


    #[\Override] public function formatMessageArgs( ?array $args ) : array {
        $flat = ArrayUtil::flatten($args);
        return ['types' => implode(',', $flat)];
    }


    /**
     * アップロードファイルの拡張子・finfo MIME・magic bytes の 3 段検証を行う。
     *
     * 1. 実拡張子が許可リストにあるか ( 大小文字無視 )
     * 2. 実 finfo MIME が許可リストの fnmatch パターンにマッチするか
     * 3. 実ファイルの先頭バイトが実 MIME の signature と一致するか
     *    ( tmp ファイルが存在し、signature テーブルに該当 MIME が定義されているとき )
     *
     * polyglot ( 例: 拡張子 .png で finfo MIME も image/png だが、実体は PHP / PDF )
     * を 3 段目で検出する。
     *
     * @param mixed $value
     * @param array|null $args エイリアス名の配列
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException エイリアスが未指定または未定義の場合
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( ! $value instanceof UploadFile ) {
            return false;
        }

        $aliasList = ArrayUtil::flatten($args);
        if ( empty($aliasList) ) {
            throw new ValidationRuleException('file_type にはエイリアスを1つ以上指定してください。');
        }

        // 指定エイリアスから許可 MIME・拡張子リストを収集
        $acceptMimeList = [];
        $acceptExtList  = [];
        foreach ( $aliasList as $alias ) {
            if ( ! array_key_exists($alias, self::$aliases) ) {
                throw new ValidationRuleException("file_type のエイリアス '{$alias}' は定義されていません。");
            }
            $acceptMimeList = array_merge($acceptMimeList, self::$aliases[ $alias ]['mime']);
            $acceptExtList  = array_merge($acceptExtList, self::$aliases[ $alias ]['ext']);
        }

        // 1. 拡張子チェック ( 大小文字無視 )
        $ext = strtolower($value->getExtension());
        if ( ! in_array($ext, $acceptExtList, true) ) {
            return false;
        }

        // 2. finfo MIME チェック ( ワイルドカード対応 )
        $mimeType = $value->getMimeType();
        $mimeMatch = false;
        foreach ( $acceptMimeList as $acceptMime ) {
            if ( fnmatch($acceptMime, $mimeType) ) {
                $mimeMatch = true;
                break;
            }
        }
        if ( ! $mimeMatch ) {
            return false;
        }

        // 3. magic bytes チェック ( 実 MIME に対する signature )
        // tmp ファイルが既に無いケース ( 確認画面復元等 ) はスキップして拡張子 + MIME のみで判断
        $tmpPath = $value->getTmpName();
        if ( $tmpPath === '' || ! is_file($tmpPath) ) {
            return true;
        }
        return FileUtil::matchesMagicBytes($tmpPath, $mimeType);
    }
}
