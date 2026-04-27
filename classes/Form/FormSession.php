<?php

namespace AIJOH\Form;

use AIJOH\Http\Session;
use AIJOH\Results\FormData;
use AIJOH\Validation\Parser\TitleManager;

/**
 * 確認フロー用のセッション管理クラス
 */
class FormSession {

    private const KEY_DATA          = '_form_data';
    private const KEY_TITLES        = '_form_titles';
    private const KEY_CONFIRMED     = '_form_confirmed';
    private const KEY_UPLOAD_TOKEN  = '_form_upload_token';
    private const UPLOAD_DIR_PREFIX = 'mailform_uploads';

    private Session $session;

    public function __construct() {
        $this->session = Session::getInstance();
    }

    /**
     * バリデーション済みデータとタイトル情報をセッションに保存する。
     */
    public function save( FormData $formData ) : void {
        // セッション固定化対策: 確認フローへ遷移する直前に ID を再発行する
        $this->session->regenerate();

        // 同一セッションで再入力された場合の古い一時ファイルを削除
        $this->cleanupUploadDir();
        $this->session->remove(self::KEY_UPLOAD_TOKEN);

        $data = $this->serializeData($formData->getData());
        $titles = $this->serializeTitles($formData->getTitleManager());

        $this->session->set(self::KEY_DATA, $data);
        $this->session->set(self::KEY_TITLES, $titles);
        $this->session->set(self::KEY_CONFIRMED, false);
    }

    /**
     * 確認済みフラグを立てる。
     */
    public function confirm() : void {
        $this->session->set(self::KEY_CONFIRMED, true);
    }

    /**
     * 確認済みかどうかを返す。
     */
    public function isConfirmed() : bool {
        return $this->session->get(self::KEY_CONFIRMED, false) === true;
    }

    /**
     * セッションに保存されたデータを FormData として復元する。
     * @return FormData|null データがない場合は null
     */
    public function restore() : ?FormData {
        $data = $this->session->get(self::KEY_DATA);
        if ( $data === null ) {
            return null;
        }

        $data = $this->unserializeData($data);
        $formData = new FormData();
        $formData->setData($data);

        $titles = $this->session->get(self::KEY_TITLES);
        if ( $titles !== null ) {
            $titleManager = $this->restoreTitleManager($titles);
            $formData->setTitleManager($titleManager);
        }

        return $formData;
    }

    /**
     * 確認ページ表示用に、出力可能なタイトルとデータのペアを返す。
     * @return array<string, array{title: string, value: mixed}>
     */
    public function getConfirmItems() : array {
        $titles = $this->session->get(self::KEY_TITLES, []);
        $data   = $this->session->get(self::KEY_DATA, []);

        $items = [];
        foreach ( $titles as $key => $titleData ) {
            $items[ $key ] = [
                'title' => $titleData['title'],
                'value' => $data[ $key ] ?? null,
            ];
        }
        return $items;
    }

    /**
     * セッションのフォームデータをクリアする。
     * 一時保存された添付ファイルも合わせて削除する。
     */
    public function clear() : void {
        $this->cleanupUploadDir();
        $this->session->remove(self::KEY_DATA);
        $this->session->remove(self::KEY_TITLES);
        $this->session->remove(self::KEY_CONFIRMED);
        $this->session->remove(self::KEY_UPLOAD_TOKEN);
    }


    /**
     * 古い一時アップロードディレクトリを削除する（cron 等から定期実行）。
     * @param int $olderThanSeconds これより古い mtime のディレクトリを対象とする
     * @return int 削除したファイル数
     */
    public static function gc( int $olderThanSeconds = 86400 ) : int {
        $base = sys_get_temp_dir() . '/' . self::UPLOAD_DIR_PREFIX;
        if ( ! is_dir($base) ) {
            return 0;
        }
        $threshold = time() - $olderThanSeconds;
        $deleted = 0;
        foreach ( glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir ) {
            if ( filemtime($dir) >= $threshold ) {
                continue;
            }
            foreach ( glob($dir . '/*') ?: [] as $f ) {
                if ( @unlink($f) ) {
                    $deleted++;
                }
            }
            @rmdir($dir);
        }
        return $deleted;
    }


    /**
     * UploadFile を一時ディレクトリに退避し、メタ情報＋永続化パスに変換する。
     * 確認フローでは送信ステップで unserializeData により UploadFile として復元される。
     */
    private function serializeData( array $data ) : array {
        array_walk_recursive($data, function( &$value ) {
            if ( ! ( $value instanceof \AIJOH\Http\UploadFile ) ) {
                return;
            }
            if ( ! $value->exists() ) {
                $value = null;
                return;
            }
            // 移動前にメタ情報を取得（移動後は tmp_name が無効になるため）
            $meta = [
                '__type'    => 'upload_file',
                'name'      => $value->getName(),
                'mime_type' => $value->getMimeType(),
                'size'      => $value->getSize(),
            ];
            $persistedPath = $this->getOrCreateUploadDir() . '/' . bin2hex(random_bytes(8));
            if ( $value->move($persistedPath) ) {
                $meta['persisted_path'] = $persistedPath;
            }
            // 移動失敗時は表示用メタのみ（送信ステップでは添付なし扱い）
            $value = $meta;
        });
        return $data;
    }


    /**
     * セッションから取り出したデータの upload_file メタを UploadFile に復元する。
     */
    private function unserializeData( array $data ) : array {
        // array_walk_recursive は連想配列の中まで降りてしまい dict 単位で扱えないため、
        // upload_file メタ判定 → 復元の自前ウォークを行う。
        foreach ( $data as $key => $value ) {
            $data[ $key ] = $this->unserializeValue($value);
        }
        return $data;
    }


    private function unserializeValue( mixed $value ) : mixed {
        if ( ! is_array($value) ) {
            return $value;
        }
        if ( ( $value['__type'] ?? '' ) === 'upload_file' ) {
            $path = $value['persisted_path'] ?? '';
            if ( $path === '' || ! is_file($path) ) {
                return null;
            }
            return \AIJOH\Http\UploadFile::fromPersisted(
                $path,
                $value['name'] ?? '',
                $value['mime_type'] ?? '',
                (int) ( $value['size'] ?? 0 ),
            );
        }
        // 通常の配列はネストして再帰
        foreach ( $value as $k => $v ) {
            $value[ $k ] = $this->unserializeValue($v);
        }
        return $value;
    }


    /**
     * このセッション専用のアップロード一時ディレクトリを取得（無ければ作成）。
     */
    private function getOrCreateUploadDir() : string {
        $token = $this->session->get(self::KEY_UPLOAD_TOKEN);
        if ( ! is_string($token) || $token === '' ) {
            $token = bin2hex(random_bytes(16));
            $this->session->set(self::KEY_UPLOAD_TOKEN, $token);
        }
        $dir = sys_get_temp_dir() . '/' . self::UPLOAD_DIR_PREFIX . '/' . $token;
        if ( ! is_dir($dir) ) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }


    /**
     * このセッションの一時アップロードディレクトリを削除する。
     */
    private function cleanupUploadDir() : void {
        $token = $this->session->get(self::KEY_UPLOAD_TOKEN);
        if ( ! is_string($token) || $token === '' ) {
            return;
        }
        $dir = sys_get_temp_dir() . '/' . self::UPLOAD_DIR_PREFIX . '/' . $token;
        if ( ! is_dir($dir) ) {
            return;
        }
        foreach ( glob($dir . '/*') ?: [] as $f ) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    /**
     * TitleManager の内容を配列に変換する。
     */
    private function serializeTitles( ?TitleManager $titleManager ) : array {
        if ( $titleManager === null ) {
            return [];
        }
        return $titleManager->getAllTitle();
    }

    /**
     * 配列から TitleManager を復元する。
     */
    private function restoreTitleManager( array $titles ) : TitleManager {
        $titleManager = new TitleManager();
        foreach ( $titles as $key => $titleData ) {
            $titleManager->set($key, $titleData['title'], $titleData['output'] ?? true);
        }
        return $titleManager;
    }
}
