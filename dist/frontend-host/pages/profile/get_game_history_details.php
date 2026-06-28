<?php
include 'database.php';

if (!isset($_GET['history_id']) || (string) $_GET['history_id'] === '') {
    echo '<div class="alert alert-danger">Geçersiz istek</div>';
    exit();
}

$historyId = trim((string) $_GET['history_id']);

if (empty($_SESSION['loggedin']) || empty($_SESSION['member_jwt'])) {
    echo '<div class="alert alert-warning">Oturum gerekli</div>';
    exit();
}

$row = ApiGameHistory::findTransactionById((string) $_SESSION['member_jwt'], $historyId);

if ($row === null || $row === []) {
    echo '<div class="alert alert-warning">Oyun işlemi bulunamadı</div>';
    exit();
}

$gName = (string) ($row['gameName'] ?? '');
$pName = (string) ($row['providerName'] ?? '');
$betAmount = (float) ($row['betAmount'] ?? 0);
$winAmount = (float) ($row['winAmount'] ?? 0);
$txnType = (string) ($row['txnType'] ?? '');
$status = (string) ($row['status'] ?? '');
$createdAt = (string) ($row['createdAt'] ?? '');
$balanceAfter = $row['balanceAfter'] ?? null;
$source = (string) ($row['source'] ?? '');
$wallet = (string) ($row['wallet'] ?? '');
$roundId = (string) ($row['roundId'] ?? '');
$provTxn = (string) ($row['providerTxnId'] ?? '');
$sessionTok = $row['sessionToken'] ?? null;
$gameId = (string) ($row['gameId'] ?? '');
$provCode = (string) ($row['providerCode'] ?? '');
$net = $winAmount - $betAmount;
?>

<div class="game-history-details">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <h6 class="text-muted">Oyun bilgileri</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-1"><strong>Oyun:</strong> <?= htmlspecialchars($gName !== '' ? $gName : '—') ?></p>
                        <p class="mb-1"><strong>Sağlayıcı:</strong> <?= htmlspecialchars($pName !== '' ? $pName : '—') ?></p>
                        <p class="mb-1"><strong>Game ID:</strong> <?= htmlspecialchars($gameId !== '' ? $gameId : '—') ?></p>
                        <p class="mb-1"><strong>Sağlayıcı kodu:</strong> <?= htmlspecialchars($provCode !== '' ? $provCode : '—') ?></p>
                        <p class="mb-1"><strong>Round:</strong> <?= htmlspecialchars($roundId !== '' ? $roundId : '—') ?></p>
                        <p class="mb-1"><strong>Kaynak:</strong> <?= htmlspecialchars($source !== '' ? $source : '—') ?></p>
                        <p class="mb-1"><strong>Cüzdan:</strong> <?= htmlspecialchars($wallet !== '' ? $wallet : '—') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="mb-3">
                <h6 class="text-muted">İşlem</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-1"><strong>İşlem türü:</strong> <?= htmlspecialchars($txnType !== '' ? $txnType : '—') ?></p>
                        <p class="mb-1"><strong>Durum:</strong> <?= htmlspecialchars($status !== '' ? $status : '—') ?></p>
                        <p class="mb-1"><strong>Bahis:</strong> <span class="text-danger">-<?= number_format($betAmount, 2) ?> ₺</span></p>
                        <p class="mb-1"><strong>Kazanç:</strong> <span class="text-success">+<?= number_format($winAmount, 2) ?> ₺</span></p>
                        <p class="mb-1"><strong>Net:</strong>
                            <span class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $net >= 0 ? '+' : '' ?><?= number_format($net, 2) ?> ₺
                            </span>
                        </p>
                        <?php if ($balanceAfter !== null && $balanceAfter !== ''): ?>
                        <p class="mb-1"><strong>İşlem sonrası bakiye:</strong> <?= number_format((float) $balanceAfter, 2) ?> ₺</p>
                        <?php endif; ?>
                        <p class="mb-1"><strong>Sağlayıcı işlem no:</strong> <small><?= htmlspecialchars($provTxn !== '' ? $provTxn : '—') ?></small></p>
                        <?php if ($sessionTok !== null && (string) $sessionTok !== ''): ?>
                        <p class="mb-1"><strong>Oturum:</strong> <small><?= htmlspecialchars((string) $sessionTok) ?></small></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                <h6 class="text-muted">Zaman</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-0"><strong>Oluşturulma:</strong>
                            <?= $createdAt !== '' ? htmlspecialchars(date('d.m.Y H:i:s', strtotime($createdAt))) : '—' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
