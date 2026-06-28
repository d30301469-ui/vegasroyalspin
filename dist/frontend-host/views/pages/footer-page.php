<?php
$footerPage = is_array($footerPage ?? null) ? $footerPage : [];
$pageTitle = (string) ($footerPage['title'] ?? 'Footer Sayfası');
$pageContent = (string) ($footerPage['content'] ?? '');
?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<div class="layout-content-holder-bc footerPageContent">
    <main class="footerPageContainer">
        <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="footerPageBody">
            <?= $pageContent ?>
        </div>
    </main>
</div>

<?php include VIEW_PATH . '/partials/footer.php'; ?>
