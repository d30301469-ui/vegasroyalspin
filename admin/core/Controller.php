<?php

class Controller
{
    public function __construct()
    {
    }

    /**
     * View dosyasını yükler ve veriyi extract eder.
     * $path: views/ altındaki dosya yolu (uzantısız), ör. 'pages/home'
     */
    protected function view(string $path, array $data = []): void
    {
        global $ayar, $loggedIn;
        extract($data);
        if (defined('SURFACE') && SURFACE === 'mobile' && defined('MOBILE_PATH')) {
            $mobileFile = MOBILE_PATH . '/views/' . $path . '.php';
            if (file_exists($mobileFile)) {
                require $mobileFile;
                return;
            }
        }
        require VIEW_PATH . '/' . $path . '.php';
    }

    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }
}
