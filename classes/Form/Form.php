<?php

namespace AIJOH\Form;

use AIJOH\AISpam\AISpamDetector;
use AIJOH\AISpam\SpamSession;
use AIJOH\Form\Draft\DraftManager;
use AIJOH\Http\Request;
use AIJOH\Lang\Translator;
use AIJOH\Http\Response;
use AIJOH\Results\Formatter\Formatter;
use AIJOH\Sender\Sender;
use AIJOH\Verification\Verification;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Sender\SendException;

class Form implements FormBase {

    /** @var Verification */
    private Verification $verification;

    /** @var array */
    private array $validationConfig;

    /** @var array */
    private array $sendConfig;

    /**
     * フォームフロー: 'direct'（入力→完了）または 'confirm'（入力→確認→完了）
     */
    private string $flow;

    /** @var string 確認ページの URL（confirm フロー時） */
    private string $confirmUrl;

    /** @var string 完了ページの URL */
    private string $completeUrl;

    /** @var FormSession */
    private FormSession $formSession;

    /** @var callable|null バリデーション前のデータ加工関数 */
    private $beforeFormat;

    /** @var string AI スパム判定で弾かれた際にユーザーに表示するメッセージ */
    private string $spamBlockMessage;

    /** @var DraftManager|null draft 機能（'draft' 設定がある場合のみ初期化） */
    private ?DraftManager $draftManager = null;


    public function __construct( array $config ) {
        if ( isset($config['lang']) && is_string($config['lang']) && $config['lang'] !== '' ) {
            Translator::setLocale($config['lang']);
        }
        $this->verification     = new Verification($config['verify'] ?? []);
        $this->validationConfig = $config['validation'] ?? [];
        $this->sendConfig       = $config['sender'] ?? [];
        $this->flow             = $config['flow'] ?? 'direct';
        $this->confirmUrl       = $config['confirm_url'] ?? '';
        $this->completeUrl      = $config['complete_url'] ?? '';
        $this->beforeFormat     = $config['beforeFormat'] ?? null;
        $this->formSession      = new FormSession();
        $this->spamBlockMessage = $config['ai_spam']['block_message']
            ?? '送信内容に問題が検出されました。お手数ですがお電話でお問い合わせください。';
        if ( isset($config['draft']) && is_array($config['draft']) ) {
            $this->draftManager = new DraftManager($config['draft']);
        }
    }


    public function getHeaderTag() : string {
        return $this->verification->getHeaderTag();
    }


    public function getFormTag() : string {
        return $this->verification->getFormTag();
    }


    public function getFooterTag() : string {
        return $this->verification->getFooterTag();
    }


    public function receive() : void {
        $request = Request::getInstance();
        if ( ! $request->isPost() ) {
            return;
        }

        // 既にスパム判定済セッションは即拒否
        SpamSession::abortIfBlocked($this->spamBlockMessage);

        $verifyMessage = $this->verification->verify();
        if ( $verifyMessage !== true ) {
            Response::jsonResults(false, $verifyMessage);
            return;
        }

        // _action 分岐
        // HPP 対策: 配列を渡されても string として扱う（getString は配列なら default を返す）
        $action = $request->post()->getString('_action', '');
        if ( $action === 'validate' ) {
            $this->receiveValidateOnly($request);
            return;
        }
        if ( $action === 'draft_save' ) {
            $this->receiveDraftSave($request);
            return;
        }
        if ( $action === 'draft_restore' ) {
            $this->receiveDraftRestore();
            return;
        }
        if ( $action === 'draft_consent' ) {
            $this->receiveDraftConsent($request);
            return;
        }

        if ( $this->flow === 'confirm' ) {
            $this->receiveConfirmFlow($request);
        } else {
            $this->receiveDirectFlow($request);
        }
    }


    /**
     * バリデーションだけ実行してエラー一覧を返す（リアルタイム検証用）。
     * メール送信は行わない。
     */
    private function receiveValidateOnly( Request $request ) : void {
        try {
            $request->validateForm($this->validationConfig, $this->beforeFormat);
            Response::jsonResults(true);
        } catch ( ValidationException $e ) {
            Response::jsonResults(false, null, $e->getErrors());
        }
    }


    /**
     * AI スパム判定を実行し、スパムなら拒否レスポンスで終了する。
     */
    private function checkSpamOrAbort( array $data ) : void {
        $judgement = AISpamDetector::judge($data);
        if ( $judgement->isSpam ) {
            SpamSession::block($judgement->reason);
            Response::jsonResults(false, $this->spamBlockMessage);
        }
    }


    /**
     * 直接送信フロー: バリデーション → メール送信 → 完了
     */
    private function receiveDirectFlow( Request $request ) : void {
        try {
            $formData = $request->validateForm($this->validationConfig, $this->beforeFormat);
            // バリデーション後・送信前に AI スパム判定（スパム判定なら exit）
            $this->checkSpamOrAbort($formData->getData());
            $formatter  = new Formatter($formData);
            $sendConfig = $formatter->formatAll($this->sendConfig);
            $sender     = new Sender($sendConfig);
            $sender->sendAll($formatter);
            $this->draftManager?->clear();
            Response::jsonResults(true, '送信が完了しました。', null, $this->completeUrl);
        } catch ( SendException $se ) {
            Response::jsonResults(false, $se->getMessage());
        } catch ( ValidationException $e ) {
            Response::jsonResults(false, 'フォームのチェックに失敗しました。', $e->getErrors());
        }
    }


    /**
     * 確認フロー: _step に応じて処理を分岐する。
     * _step=input   → バリデーション → セッション保存 → 確認ページへ
     * _step=confirm → セッションから復元 → メール送信 → 完了ページへ
     */
    private function receiveConfirmFlow( Request $request ) : void {
        // HPP 対策: 配列を渡されても string として扱う
        $step = $request->post()->getString('_step', 'input');

        if ( $step === 'confirm' ) {
            $this->receiveConfirmStep();
        } else {
            $this->receiveInputStep($request);
        }
    }


    /**
     * 入力ステップ: バリデーションしてセッションに保存する。
     */
    private function receiveInputStep( Request $request ) : void {
        try {
            $formData = $request->validateForm($this->validationConfig, $this->beforeFormat);
            // 確認画面表示前にも AI スパム判定（早期に弾く）
            $this->checkSpamOrAbort($formData->getData());
            $this->formSession->save($formData);
            Response::jsonResults(true, 'バリデーションが完了しました。', null, $this->confirmUrl);
        } catch ( ValidationException $e ) {
            Response::jsonResults(false, 'フォームのチェックに失敗しました。', $e->getErrors());
        }
    }


    /**
     * 確認ステップ: セッションのデータを使ってメールを送信する。
     */
    private function receiveConfirmStep() : void {
        $formData = $this->formSession->restore();
        if ( $formData === null ) {
            Response::jsonResults(false, 'セッションが切れました。最初からやり直してください。');
            return;
        }

        // 添付ファイルが GC で消えていた場合は明確なエラーで返す
        // （以前は黙って null になり、メール送信時に添付なしで送られていた）
        if ( $this->formSession->hasMissingUploads() ) {
            $this->formSession->clear();
            Response::jsonResults(false, '添付ファイルの一時保存期間が過ぎました。最初からやり直してください。');
            return;
        }

        // 念のため確認 → 送信ステップでも判定する（キャッシュにヒットするので追加コストは無い）
        $this->checkSpamOrAbort($formData->getData());

        try {
            $formatter  = new Formatter($formData);
            $sendConfig = $formatter->formatAll($this->sendConfig);
            $sender     = new Sender($sendConfig);
            $sender->sendAll($formatter);
            $this->formSession->clear();
            $this->draftManager?->clear();
            Response::jsonResults(true, '送信が完了しました。', null, $this->completeUrl);
        } catch ( SendException $se ) {
            Response::jsonResults(false, $se->getMessage());
        }
    }


    /**
     * 確認ページ表示用: セッションのデータを返す。
     * 確認ページの PHP から呼び出す。
     * @return array<string, array{title: string, value: mixed}>
     */
    public function getConfirmData() : array {
        return $this->formSession->getConfirmItems();
    }


    // ====================================================================
    //  draft 機能（パターン B: PHP テンプレ向け公開 API）
    // ====================================================================


    /**
     * draft 機能が有効か。
     */
    public function isDraftEnabled() : bool {
        return $this->draftManager !== null;
    }


    /**
     * draft Cookie から特定フィールドの保存値を取得する（パターン B）。
     * PHP テンプレで `<input value="<?= $form->getDraftValue('name') ?>">` のように使う。
     *
     * @param string $field フィールド名
     * @param mixed $default 未保存時のデフォルト値
     */
    public function getDraftValue( string $field, mixed $default = '' ) : mixed {
        if ( $this->draftManager === null ) {
            return $default;
        }
        $values = $this->draftManager->restore();
        return $values[ $field ] ?? $default;
    }


    /**
     * draft Cookie から全保存値を取得する（パターン B）。
     */
    public function getDraftValues() : array {
        if ( $this->draftManager === null ) {
            return [];
        }
        return $this->draftManager->restore();
    }


    /**
     * JS 用初期データを返す。`<form data-mailform-draft-config="...">` の data 属性 や
     * `<script type="application/json">` で埋め込んで JS 側に渡す想定。
     *
     * @return array{enabled: bool, consent: array, debounce_ms: int}
     */
    public function getDraftClientConfig() : array {
        if ( $this->draftManager === null ) {
            return [ 'enabled' => false ];
        }
        $consent = $this->draftManager->getConsent();
        return [
            'enabled' => true,
            'consent' => [
                'mode'      => $consent->getMode(),
                'behavior'  => $consent->getBehavior(),
                'isAllowed' => $this->draftManager->isAllowed(),
                'managed'   => $consent->isManagedByMailform(),
            ],
            'debounce_ms' => 800,
        ];
    }


    // ====================================================================
    //  draft 機能（ajax 受け口、Form::receive() から呼ばれる）
    // ====================================================================


    /**
     * `_action=draft_save`: POST データを draft Cookie に保存する。
     */
    private function receiveDraftSave( Request $request ) : void {
        if ( $this->draftManager === null ) {
            Response::jsonResults(false, 'draft 機能は有効化されていません。');
            return;
        }
        if ( ! $this->draftManager->isAllowed() ) {
            Response::jsonResults(false, '同意がないため保存できません。');
            return;
        }
        $posted = $request->post()->getAll();
        // _csrf_token / _action / _step 等の内部フィールドは除外
        $filtered = array_filter(
            $posted,
            fn( $k ) => ! str_starts_with((string) $k, '_'),
            ARRAY_FILTER_USE_KEY,
        );
        $this->draftManager->save($filtered);
        Response::jsonResults(true);
    }


    /**
     * `_action=draft_restore`: draft Cookie の値を JSON で返す（パターン A fallback）。
     * 純 HTML フォームで PHP テンプレが使えない場合に JS 側から呼ぶ。
     * レスポンス形式: { "status": true, "values": { "field" => value, ... } }
     */
    private function receiveDraftRestore() : void {
        if ( $this->draftManager === null ) {
            Response::jsonResults(false, 'draft 機能は有効化されていません。');
            return;
        }
        if ( ! $this->draftManager->isAllowed() ) {
            Response::json([ 'status' => true, 'values' => new \stdClass() ]);
            exit;
        }
        $values = $this->draftManager->restore();
        Response::json([ 'status' => true, 'values' => $values ?: new \stdClass() ]);
        exit;
    }


    /**
     * `_action=draft_consent`: 同意状態を Cookie に書き込む（builtin モード用）。
     * POST `consent` パラメータで 'granted' または 'revoked' を受け取る。
     * 同時に POST `discard=1` が来ていれば draft データも削除する（拒否＝過去の保存も消したい）。
     */
    private function receiveDraftConsent( Request $request ) : void {
        if ( $this->draftManager === null ) {
            Response::jsonResults(false, 'draft 機能は有効化されていません。');
            return;
        }
        if ( ! $this->draftManager->isManagedConsent() ) {
            Response::jsonResults(false, 'mailform は同意管理を行わない設定です。');
            return;
        }
        $status = $request->post()->get('consent', '');
        if ( ! is_string($status) || ! in_array($status, [ 'granted', 'revoked' ], true) ) {
            Response::jsonResults(false, 'consent は granted または revoked を指定してください。');
            return;
        }
        $this->draftManager->setConsent($status);
        if ( $status === 'revoked' || $request->post()->get('discard') === '1' ) {
            $this->draftManager->clear();
        }
        Response::jsonResults(true);
    }


    /**
     * カスタム業務ロジックの検証結果をフォームと同じ JSON 形式で返して終了する。
     *
     * Form::receive() の前に独自の検証（営業時間・在庫・予約枠など mailform 標準
     * ルールでは表現できないもの）を行うときの正解の返し方。
     *
     * 配列形式は標準バリデーションと揃えてあるため、フロントの form.js が
     * そのままフィールド単位エラーとして表示できる。
     *
     * 使用例:
     *   $errors = [];
     *   if ( ! StoreSchedule::isOpen($postedDatetime) ) {
     *       $errors['datetime'] = '営業時間外です。';
     *   }
     *   if ( $errors ) {
     *       \AIJOH\Form\Form::abortWithErrors($errors);
     *   }
     *   $form = new \AIJOH\Form\Form(include __DIR__ . '/config.php');
     *   $form->receive();
     *
     * @param array<string, string|string[]> $errors フィールド名 => メッセージ（または配列）
     * @param string $message 全体メッセージ
     * @return never
     */
    public static function abortWithErrors(
        array $errors,
        string $message = 'フォームのチェックに失敗しました。',
    ) : never {
        Response::jsonResults(false, $message, self::flattenErrors($errors));
    }


    /**
     * フィールド単位エラー配列をフロント送信用のフラット形式（field => string）に変換する。
     * 値が配列なら改行で連結する。abortWithErrors() のテスト容易化のため公開している。
     *
     * @param array<string, string|string[]> $errors
     * @return array<string, string>
     */
    public static function flattenErrors( array $errors ) : array {
        $flat = [];
        foreach ( $errors as $field => $messages ) {
            $flat[ $field ] = is_array($messages)
                ? implode("\n", array_map('strval', $messages))
                : (string) $messages;
        }
        return $flat;
    }
}
