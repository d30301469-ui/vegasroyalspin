<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oyun Başlatılamadı</title>
    <style>
        body { background: #000; color: #fff; font-family: system-ui, sans-serif; padding: 20px; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-box { background: #111; border: 1px solid #856A00; padding: 30px 20px; max-width: 500px; width: 90%; margin: 0 auto; border-radius: 8px; }
        h2 { color: #856A00; margin-top: 0; text-align: center; }
        .game-info { background: #222; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-item { margin: 8px 0; display: flex; flex-wrap: wrap; }
        .label { color: #888; width: 100px; display: inline-block; font-weight: bold; }
        .value { color: #fff; flex: 1; }
        .device-info { background: #1a1a1a; padding: 10px; margin: 10px 0; border-left: 3px solid #856A00; font-size: 13px; }
        .button-group { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
        button { background: #856A00; color: #000; border: none; padding: 12px 25px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: all 0.2s; flex: 1; min-width: 120px; }
        button:hover { background: #9E7F00; transform: translateY(-2px); }
        @media (max-width: 480px) { .error-box { padding: 20px 15px; } .button-group { flex-direction: column; } button { width: 100%; } }
    </style>
</head>
<body>
<div class="error-box">
    <h2>❌ Oyun Başlatılamadı</h2>
    <div class="device-info"><strong>Cihaz:</strong> <?= htmlspecialchars($device) ?> (<?= htmlspecialchars($channel) ?>)</div>
    <div class="game-info">
        <div class="info-item"><span class="label">Oyun:</span><span class="value"><?= htmlspecialchars($game_name) ?></span></div>
        <div class="info-item"><span class="label">Sağlayıcı:</span><span class="value"><?= htmlspecialchars($vendor_name) ?></span></div>
        <div class="info-item"><span class="label">Hata:</span><span class="value" style="color: #856A00;"><?= htmlspecialchars($display_message) ?></span></div>
        <?php if ($error_code): ?>
        <div class="info-item"><span class="label">Hata Kodu:</span><span class="value"><?= (int) $error_code ?></span></div>
        <?php endif; ?>
    </div>
    <div class="button-group">
        <button type="button" onclick="history.back()">← Geri Dön</button>
        <button type="button" onclick="window.location.href='/casino'">🎮 Casino Sayfası</button>
    </div>
</div>
</body>
</html>
