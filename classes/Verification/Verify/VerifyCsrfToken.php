<?php

namespace AIJOH\Verification\Verify;

use AIJOH\Http\Post;
use AIJOH\Http\Session;
use AIJOH\Util\HtmlUtil;

class VerifyCsrfToken extends VerifyBase{
    private Session $session;

    private string $key = "_csrf_token";

    /**
     * セッションに保存するトークンのキー
     * @var string
     */
    private string $sessionKey = "form_csrf_token";

    /**
     * CSPRNG ( random_bytes ) 失敗時に弱い乱数 ( sha1(uniqid) ) でトークンを生成する
     * 後方互換モード。デフォルトは false ( fail-closed ) で、CSPRNG 失敗時は例外。
     * true にすると CSRF 対策の強度が大幅に落ちるので原則オフのまま運用する。
     */
    private bool $allowWeakFallback = false;


    /**
     * コンストラクタ
     * @param $config
     */
    public function __construct( $config = [] ) {
        $this->session = Session::getInstance();
        if ( ! empty($config['key']) ) {
            $this->key = $config['key'];
        }
        $this->allowWeakFallback = (bool) ( $config['allow_weak_fallback'] ?? false );
    }
    
    /**
     * 現在設定されているトークンを取得する。
     * @return string
     */
    private function getToken() : string {
        $token = $this->session->get($this->sessionKey);
        if ( empty($token) ) {
            $token = $this->generate();
            $this->session->set($this->sessionKey, $token);
        }
        return $token;
    }
    
    
    /**
     * トークンを生成する。
     *
     * デフォルトは fail-closed: CSPRNG ( random_bytes ) が失敗した場合は
     * error_log に原因を出した上で RuntimeException を投げる。CSRF 対策が
     * 機能しない状態で通信を成立させない ( silent failure 防止 )。
     *
     * verify.csrf.allow_weak_fallback=true を明示すると、後方互換として
     * sha1(uniqid) で代替する。CSRF トークンの予測可能性が大幅に上がるため
     * 原則オフのまま運用すること。
     *
     * @throws \RuntimeException CSPRNG 失敗かつ allow_weak_fallback=false のとき
     */
    private function generate() : string {
        try {
            return bin2hex($this->tryRandomBytes(32));
        } catch ( \Throwable $e ) {
            // CSPRNG 失敗は OS / 環境の異常事態。運用者が気付けるよう必ずログを出す
            error_log(
                "[mailform] CSPRNG ( random_bytes ) failure during CSRF token generation: "
                . $e->getMessage()
            );
            if ( ! $this->allowWeakFallback ) {
                throw new \RuntimeException(
                    'CSPRNG unavailable; CSRF token cannot be generated securely. '
                    . 'Investigate /dev/urandom or platform CSPRNG. '
                    . 'To use weak fallback ( NOT recommended ), set verify.csrf.allow_weak_fallback=true.',
                    0,
                    $e,
                );
            }
            error_log(
                "[mailform] WARN: falling back to sha1(uniqid) CSRF token "
                . "( verify.csrf.allow_weak_fallback=true ). CSRF strength is significantly reduced."
            );
            return sha1(uniqid('', true));
        }
    }
    
    /**
     * フォーム部分のHTMLを返す。
     * @return string
     */
    public function form() : string {
        $name = HtmlUtil::escape($this->key);
        $token = $this->getToken();
        return "<input type='hidden' name='{$name}' value='{$token}'>";
    }
    
    /**
     * 入力の検証を行う
     * @return bool
     */
    public function verify() : bool {
        $token = $this->session->get($this->sessionKey);
        if ( empty($token) ) {
            return false;
        }

        $input = Post::getInstance()->get($this->key, '');
        if ( ! is_string($input) ) {
            return false;
        }
        // タイミング攻撃対策のため hash_equals で比較する
        return hash_equals($token, $input);
    }
    
    
    public function getErrorMessage() : string {
        return "トークンが一致しません。再度お試しください。";
    }


    /**
     * CSPRNG 経由でランダムバイトを返す ( PHP の random_bytes そのまま )。
     * テストで CSPRNG 失敗を再現するためのフック ( サブクラスで override 可能 )。
     *
     * @throws \Exception random_bytes が CSPRNG にアクセスできなかったとき
     */
    protected function tryRandomBytes( int $length ) : string {
        return random_bytes($length);
    }
}