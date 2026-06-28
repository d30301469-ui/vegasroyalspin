<?php
/** Footer ust ozel icerik — casinomilyon591 (BC expandableContent HTML) */
$boxStyle = 'border:1px solid #987bb1;border-radius:10px;box-shadow:0 0 7px 2px #850f83;padding:16px 18px;background:transparent;';
$h3Style = 'text-align:center;text-transform:uppercase;font-size:14px;line-height:14px;font-weight:700;letter-spacing:.75px;margin:0 0 16px;color:rgba(255,255,255,.85);';
$footerAbout = is_array($footerAbout ?? null) ? $footerAbout : [];
$historyTitle = (string) ($footerAbout['history_title'] ?? 'TARİHİMİZ');
$historyText = (string) ($footerAbout['history_text'] ?? '');
$futureTitle = (string) ($footerAbout['future_title'] ?? 'GELECEĞİMİZ');
$futureText = (string) ($footerAbout['future_text'] ?? '');
$awardsTitle = (string) ($footerAbout['awards_title'] ?? 'ÖDÜLLERİMİZ');
?>
<div class="expandableContentWrapper">
    <div class="expandableContentData custom-content-section not-expandable">
        <div class="container">
            <div class="footerAboutCards col-2 m-t-10">
                <div class="column">
                    <h3 style="<?= $h3Style ?>"><?= htmlspecialchars($historyTitle) ?></h3>
                    <div class="footerAboutCard" style="<?= $boxStyle ?>">
                        <p><?= htmlspecialchars($historyText) ?></p>
                    </div>
                </div>
                <div class="column">
                    <h3 style="<?= $h3Style ?>"><?= htmlspecialchars($futureTitle) ?></h3>
                    <div class="footerAboutCard" style="<?= $boxStyle ?>">
                        <p><?= htmlspecialchars($futureText) ?></p>
                    </div>
                </div>
            </div>

            <div class="footerAwardsBlock m-t-15">
                <h3 style="<?= $h3Style ?>margin-bottom:14px;"><?= htmlspecialchars($awardsTitle) ?></h3>
                <div class="footerAwardsPanel" style="<?= $boxStyle ?>padding:18px 14px;">
                    <div class="footerAwardsGrid col-3">
                        <?php foreach ($footerAwards as $award): ?>
                            <?php
                            if (!is_array($award)) {
                                continue;
                            }
                            $awardSrc = (string) ($award['src'] ?? '');
                            if ($awardSrc === '') {
                                continue;
                            }
                            $awardPath = (string) parse_url($awardSrc, PHP_URL_PATH);
                            $awardFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . $awardPath;
                            $awardVersion = is_file($awardFile) ? ('?v=' . filemtime($awardFile)) : '';
                            ?>
                            <div class="column">
                                <div class="footerAwardCard">
                                    <img src="<?= htmlspecialchars($awardSrc . $awardVersion) ?>"
                                         alt="<?= htmlspecialchars((string) ($award['alt'] ?? '')) ?>"
                                         loading="lazy"
                                         class="footerAwardLogo">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
