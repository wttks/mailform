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
     * コンストラクタ
     * @param $config
     */
    public function __construct( $config = [] ) {
        $this->session = Session::getInstance();
        if ( ! empty($config['key']) ) {
            $this->key = $config['key'];
        }
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
     * トークンを生成する
     * @return string
     */
    private function generate() : string {
        try {
            return bin2hex(random_bytes(32));
        } catch ( \Exception $e ) {
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
}