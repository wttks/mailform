<?php
namespace AIJOH\Util;


class FileUtil {

    /**
     * 指定したファイルのパスが画像ファイルかどうかチェックを行う。
     * @param string $path チェックを行うファイルのパス
     * @return bool ファイルが画像ファイルの場合はtrueを返す。
     */
    public static function isImageFile(string $path) : bool {
        if( ! file_exists($path) ) {
            return false;
        }
        $mime = mime_content_type($path);
        return $mime !== false && StrUtil::startsWith($mime, 'image/');
    }
    
    
    /**
     * 指定したファイルのパスがPDFファイルかどうかチェックを行う。
     * @param string $path
     * @return bool
     */
    public static function isPDFFile(string $path) : bool {
        if( ! file_exists($path) ) {
            return false;
        }
        return mime_content_type($path) === "application/pdf";
    }
    
    /**
     * 指定したファイルのパスがExcelファイルかどうかチェックを行う。
     * @param string $path
     * @return bool
     */
    public static function isExcelFile(string $path) : bool {
        if( ! file_exists($path) ) {
            return false;
        }
        return mime_content_type($path) === "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
    }

}