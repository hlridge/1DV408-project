<?php

namespace BoostMyAllowanceApp\View;

use BoostMyAllowanceApp\Model\Model;

require_once("partials/head.php");
require_once("partials/footer.php");
require_once("partials/navigation.php");

abstract class View extends ViewKeys {

    private $title;
    protected $model;

    public function __construct(Model $model, $viewTitle = "") {
        $this->model = $model;
        $this->title = $model::APP_NAME . " " . $viewTitle;
    }

    protected function getSurroundingHtml($bodyHtml) {
        $html = $this->getFirstPartOfHtml();
        $html .= $bodyHtml;
        $html .= $this->getSecondPartOfHtml();

        return $html;
    }

    private function getFirstPartOfHtml() {
        $head = new Head($this->title);
        $navigation = new Navigation($this->model, get_class($this));

        $html = '<!DOCTYPE html>' . PHP_EOL;
        $html .= $head->getHtml();
        $html .= '<body>' . PHP_EOL;
        $html .= $navigation->getHtml();
        $html .= ($this->model->hasMessage() ? '<div class="alert alert-' . $this->getAlertCssClass($this->model->getMessage()->getMessageType()) . '" role="alert"><a href="#" class="close" data-dismiss="alert">&times;</a>
        ' . $this->model->getMessage()->getMessageText() . '</div>' : '');
        
        return $html;
    }

    private function getSecondPartOfHtml() {
        $footer = new Footer();

        $html = $footer->getHtml();
        $html .= '<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>';
        $html .= '<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>';
        $html .= '</body>' . PHP_EOL;

        return $html;
    }

    public function unsetCookies() {
        unset($_COOKIE[self::$cookieEncryptedPasswordKey]);
        setcookie(self::$cookieEncryptedPasswordKey, null, -1, '/');
        unset($_COOKIE[self::$cookieUsernameKey]);
        setcookie(self::$cookieUsernameKey, null, -1, '/');
    }

    public function getUsernameFromCookie() {
        return (isset($_COOKIE[self::$cookieUsernameKey]) ? $_COOKIE[self::$cookieUsernameKey] : "");
    }

    public function getEncryptedPasswordFromCookie() {
        return (isset($_COOKIE[self::$cookieEncryptedPasswordKey]) ? $_COOKIE[self::$cookieEncryptedPasswordKey] : "");
    }

    public function setEncryptedPasswordToCookie($encryptedCookiePassword) {
        $_COOKIE[self::$cookieEncryptedPasswordKey] = $encryptedCookiePassword;
    }
    public function setCookiesIfAutoLogin() {
        if ($this->autoLogin) {
            $encryptedCookiePassword = $this->model->encryptCookiePassword($_POST[$this->postPasswordKey]);
            setcookie($this->cookieUsernameKey, $_POST[$this->postUsernameKey], time()+2592000, '/'); //expire in 30 days
            setcookie($this->cookieEncryptedPasswordKey, $encryptedCookiePassword, time()+2592000, '/');
        }
    }
    public function wasLoginButtonClicked() {
        return isset($_POST[self::$postLoginButtonNameKey]);
    }

    public function getUsername() {
        return $this->username;
    }

    public function getPassword() {
        return $this->password;
    }

    public function wasAutoLoginChecked() {
        return $this->autoLogin;
    }

    public static function getClassName() {
        return get_called_class();
    }

    public static function getPageName() {
        preg_match("/\\\\(\w*)View$/", get_called_class(), $matches);
        return lcfirst($matches[1]);
    }

    public function getAlertCssClass($messageType) {
        $alertCssClass = "";

        switch ($messageType) {
            case 0:
                $alertCssClass = "info";
                break;
            case 1:
                $alertCssClass = "success";
                break;
            case 2:
                $alertCssClass = "danger";
                break;
            case 3:
                $alertCssClass = "warning";
                break;
            default:
                $alertCssClass = "info";
        }

        return $alertCssClass;
    }
}