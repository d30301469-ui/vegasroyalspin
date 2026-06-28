<?php

require_once dirname(__DIR__) . '/services/ProfileApiHelper.php';

/**
 * Oturum ve kullanıcı bilgisi – MAIN backend /users/profile ile senkron.
 * $conn parametresi geriye uyumluluk için yok sayılır.
 */
class SessionHelper
{
    /**
     * @param mixed $conn (kullanılmıyor)
     * @return array{isLoggedIn: bool, username: string, initial: string}
     */
    public static function checkUserSession($conn = null)
    {
        $isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
        $username   = $isLoggedIn ? $_SESSION['username'] : '';
        if (!$isLoggedIn) {
            header('Location: /');
            exit();
        }
        $initial = strtoupper(substr($username, 0, 1));
        if ($username !== '') {
            $prof = ProfileApiHelper::profileByUsername($username);
            if ($prof !== []) {
                $_SESSION['user_id']    = $prof['id'] ?? $_SESSION['user_id'] ?? null;
                $_SESSION['ana_bakiye'] = $prof['ana_bakiye'] ?? $_SESSION['ana_bakiye'] ?? 0;
                $_SESSION['first_name'] = $prof['first_name'] ?? '';
                $_SESSION['surname']    = $prof['surname'] ?? '';
            }
        }
        return ['isLoggedIn' => $isLoggedIn, 'username' => $username, 'initial' => $initial];
    }
}
