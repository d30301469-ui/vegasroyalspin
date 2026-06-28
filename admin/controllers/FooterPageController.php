<?php

class FooterPageController extends Controller
{
    public function show(string $slug = ''): void
    {
        require_once API_PATH . '/bootstrap.php';

        $page = ApiFooterPages::findBySlug($slug);
        if ($page === null) {
            http_response_code(404);
            $page = [
                'title' => 'Sayfa bulunamadı',
                'content' => '<p>Aradığınız footer sayfası bulunamadı.</p>',
                'meta_title' => 'Sayfa bulunamadı',
                'meta_description' => '',
            ];
        }

        $this->view('pages/footer-page', ['footerPage' => $page]);
    }
}
