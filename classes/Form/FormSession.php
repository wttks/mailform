<?php

namespace AIJOH\Form;

use AIJOH\Http\Session;
use AIJOH\Results\FormData;
use AIJOH\Validation\Parser\TitleManager;

/**
 * 確認フロー用のセッション管理クラス
 */
class FormSession {

    private const KEY_DATA         = '_form_data';
    private const KEY_TITLES       = '_form_titles';
    private const KEY_CONFIRMED    = '_form_confirmed';

    private Session $session;

    public function __construct() {
        $this->session = Session::getInstance();
    }

    /**
     * バリデーション済みデータとタイトル情報をセッションに保存する。
     */
    public function save( FormData $formData ) : void {
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
     */
    public function clear() : void {
        $this->session->remove(self::KEY_DATA);
        $this->session->remove(self::KEY_TITLES);
        $this->session->remove(self::KEY_CONFIRMED);
    }

    /**
     * UploadFile はセッションに保存できないため、ファイル情報を配列に変換する。
     */
    private function serializeData( array $data ) : array {
        array_walk_recursive($data, function( &$value ) {
            if ( $value instanceof \AIJOH\Http\UploadFile ) {
                // ファイルは確認フローでは表示のみ（再送信時はセッションから除外）
                $value = $value->exists() ? [
                    '__type'    => 'upload_file',
                    'name'      => $value->getName(),
                    'mime_type' => $value->getMimeType(),
                    'size'      => $value->getSize(),
                ] : null;
            }
        });
        return $data;
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
