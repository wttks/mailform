<?php

namespace AIJOH\Results;

use AIJOH\Http\UploadFile;
use AIJOH\Util\ArrayUtil;
use AIJOH\Validation\Parser\TitleManager;

class FormData {
    
    /**
     * タイトルとキーを管理するクラス
     * @var TitleManager|null
     */
    protected ?TitleManager $titleManager = null;
    
    /**
     * 入力データ
     * @var array
     */
    protected array $data;
    
    /**
     * データの一覧をテキストに変換したもの
     */
    private ?string $toString = null;
    
    public function __construct() {
    }
    
    /**
     * タイトルとキーを管理するクラスを設定する。
     * @param TitleManager $titleManager
     * @return void
     */
    public final function setTitleManager( TitleManager $titleManager ) : void {
        $this->titleManager = $titleManager;
    }
    
    /**
     * データを設定する。
     * @param array $data
     * @return void
     */
    public final function setData( array $data ) : void {
        $this->data = $data;
    }
    
    /**
     * データを取得する。
     * @return array
     */
    public function getData() : array {
        return $this->data;
    }
    
    /**
     * 指定したキーに対応する値を返す。
     * @param string $title
     * @return mixed
     */
    public function get(string $title) : mixed {
        return ArrayUtil::get($this->data, $title);
    }
    
    /**
     * キーに対応する値を取得する。
     * @param string $key
     * @return mixed
     */
    public function getKeyValues(string $key) : array {
        return ArrayUtil::getKeyValueList($this->data, $key);
    }
    
    
 
    
    /**
     * タイトルマネージャーを取得する。
     * @return TitleManager|null
     */
    public function getTitleManager() : ?TitleManager {
        return $this->titleManager;
    }
    

    
    /**
     * 添付ファイルの一覧を取得する。
     * @return array|UploadFile[]
     */
    public function getAttachmentList() : array {
        $attachments = [];
        array_walk_recursive($this->data, function( $value, $key ) use ( &$attachments ) {
            if ( $value instanceof UploadFile && $value->exists() ){
                $attachments[ $key ] = $value;
            }
        });
        return $attachments;
    }
}