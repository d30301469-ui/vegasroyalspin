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
        global $ayar, $loggedIn, $siteMeta, $siteBranding, $siteContactLinks, $siteSettingsPayload;
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
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
        } else {
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
            echo '<p><a href="' . $safeUrl . '">Devam etmek için tıklayın</a></p>';
        }
        exit;
    }
}
