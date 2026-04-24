<?php
namespace AIJOH\Util;

class Directory {
    
    /**
     * ディレクトリを再帰的に作成する。
     * @param string $path
     * @return bool
     */
    public static function makeRecursive(string $path): bool {
        if (is_dir($path)) {
            return true;
        }
        mkdir($path, 0777, true);
        return is_dir($path);
    }
    
    
    
    


}