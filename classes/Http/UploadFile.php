<?php

namespace AIJOH\Http;

/**
 * アップロードされたファイルに関するクラス
 */
class UploadFile {
    
    /**
     * ファイルデータ
     * @var array|mixed|null
     */
    private ?array $file;
    
    /**
     * コンストラクタ
     * @param string $name キーの名前
     */
    public function __construct( string $name ) {
        $this->file = $_FILES[ $name ] ?? null;
    }
    
    /**
     * アップロードされたファイルが存在するかどうかを判別する
     * @return bool
     */
    public function exists() : bool {
        return $this->getSize() > 0;
    }
    
    
    /**
     * アップロードされたファイルの拡張子を取得する。
     * @return string 拡張子
     */
    public function getExtension() : string {
        return pathinfo($this->getName(), PATHINFO_EXTENSION);
    }
    
    
    /**
     * アップロードされたファイル名を取得する。
     * @return string ファイル名
     */
    public function getName() : string {
        return $this->file['name'] ?? '';
    }
    
    
    /**
     * 一時的にファイルを保存するディレクトリのパスを取得する。
     * @return string 一時保存するファイルのパス
     */
    public function getTmpName() : string {
        return $this->file['tmp_name'] ?? '';
    }
    
    
    /**
     * アップロードされたファイルのMimeTypeを取得する。
     * @return string マイムタイプ
     */
    public function getMimeType() : string {
        $tmpFile = $this->getTmpName();
        if( empty($tmpFile) ){
            return '';
        }
        
        $type = mime_content_type($tmpFile);
        return $type !== false ? $type : $this->file['type'] ?? '';
    }
    
    
    /**
     * 画像ファイルの形式が画像であることを判別する。
     * @return bool
     */
    public function isImage() : bool {
        return str_starts_with($this->getMimeType(), 'image/');
    }
    
    
    /**
     * アップロードしたファイルのサイズを取得する。
     * @return int
     */
    public function getSize() : int {
        return $this->file['size'] ?? 0;
    }
    
    
    /**
     * アップロードが成功したかどうかを判別する。
     * @return bool
     */
    public function isUploadSuccess() : bool {
        return $this->getError() === UPLOAD_ERR_OK;
    }
    
    
    /**
     * エラーコードを取得する。
     * @return int
     */
    public function getError() : int {
        return $this->file['error'] ?? 0;
    }
    
    /**
     * ファイルを移動する。
     * @param string $path
     * @return bool
     */
    public function move( string $path ) : bool {
        if ( ! $this->exists() ) {
            return false;
        }
        return move_uploaded_file($this->getTmpName(), $path);
    }
    
    
    /**
     * 一時的に保存されたファイルを削除する。
     * @param string $path
     * @return bool
     */
    public function removeTempFile(string $path) : bool {
        if ( ! $this->exists() ) {
            return false;
        }
        unlink($path);
        clearstatcache();
        return ! file_exists($path);
    }
    

}