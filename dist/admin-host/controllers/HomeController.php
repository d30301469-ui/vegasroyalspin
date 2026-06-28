<?php

require_once SERVICE_PATH . '/MemberLoginService.php';

class HomeController extends Controller
{
    public function index(): void
    {
        $this->handleLogin();
        $showJackpotWinnersRow = true;
        $this->view('pages/home', compact('showJackpotWinnersRow'));
    }

    private function handleLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['username'], $_POST['password'])) {
            return;
        }

        $username = trim((string) $_POST['username']);
        $password = (string) $_POST['password'];

        if ($username === '' && $password === '') {
            return;
        }

        $res = MemberLoginService::login($username, $password);

        if (MemberLoginService::succeeded($res)) {
            MemberLoginService::applySession($res, $username);
            $this->redirect('/');
            return;
        }

        $_SESSION['login_error'] = MemberLoginService::failureMessage($res);
    }
}
