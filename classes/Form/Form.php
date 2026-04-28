<?php

namespace AIJOH\Form;

use AIJOH\AISpam\AISpamDetector;
use AIJOH\AISpam\SpamSession;
use AIJOH\Http\Request;
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


    public function __construct( array $config ) {
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

        // _action=validate ならバリデーションのみ実行（リアルタイム検証用）
        if ( $request->post()->get('_action', '') === 'validate' ) {
            $this->receiveValidateOnly($request);
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
        $step = $request->post()->get('_step', 'input');

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

        // 念のため確認 → 送信ステップでも判定する（キャッシュにヒットするので追加コストは無い）
        $this->checkSpamOrAbort($formData->getData());

        try {
            $formatter  = new Formatter($formData);
            $sendConfig = $formatter->formatAll($this->sendConfig);
            $sender     = new Sender($sendConfig);
            $sender->sendAll($formatter);
            $this->formSession->clear();
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
}
