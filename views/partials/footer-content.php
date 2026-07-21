<?php include __DIR__ . '/footer-site-chrome.php'; ?>

<?php
$ayar = isset($ayar) && is_array($ayar) ? $ayar : [];
$siteBranding = isset($siteBranding) && is_array($siteBranding) ? $siteBranding : [];
?>

  <app-footer _ngcontent-tqr-c15=""
><!----><!----><!----><!----><!----><!----><!----><!----><!----><app-g6-footer
>
                <div class="footer-scroll">
                    <footer class="footerRow">
                        <div class="container-fluid">
                                <div class="row justify-content-center">
                                <!-- 4 sütunlu grid: tüm bölüm başlıkları aynı hizada -->
                                <div class="footer-links-grid">
                                    <div class="footer-link-section">
                                        <h6>HAKKIMIZDA</h6>
                                        <ul>
                                            <li><a class="footLink border-0" href="javascript:void(0)" title="Hakkında &amp; Bize Ulaşın">Hakkında &amp; Bize Ulaşın</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Ortaklık Programı Ve Reklam">Ortaklık Programı Ve Reklam</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="İletişim">İletişim</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Gizlilik Politikası" onclick="openPrivacyPolicyModal()">Gizlilik Politikası</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Privacy &amp; Management Of Personal Data">Privacy &amp; Management Of Personal Data</a></li>
                                        </ul>
                                    </div>
                                    <div class="footer-link-section">
                                        <h6>BAHİS &amp; OYUN KURALLARI</h6>
                                        <ul>
                                            <li><a class="footLink border-0" href="javascript:void(0)" title="Genel Kurallar Ve Şartlar">Genel Kurallar Ve Şartlar</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Spor Bahis Kuralları">Spor Bahis Kuralları</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Bahislerin Kabulü">Bahislerin Kabulü</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Fairness &amp; RNG">Fairness &amp; RNG</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Dispute Resolution">Dispute Resolution</a></li>
                                        </ul>
                                    </div>
                                    <div class="footer-link-section">
                                        <h6>YARDIM</h6>
                                        <ul>
                                            <li><a class="footLink border-0" href="javascript:void(0)" title="Para Yatırma / Çekim / Bonus İşlemleri">Para Yatırma / Çekim / Bonus İşlemleri</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Sıkça Sorulan Sorular">Sıkça Sorulan Sorular</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Self-Exclusion">Self-Exclusion</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Responsible Gaming">Responsible Gaming</a></li>
                                        </ul>
                                    </div>
                                    <div class="footer-link-section">
                                        <h6>HIZLI ERİŞİM</h6>
                                        <ul>
                                            <li><a class="footLink border-0" href="javascript:void(0)" title="Bonus Talep">Bonus Talep</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Canli Yayin">Canli Yayin</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Canlı Karşılaşmalar">Canlı Karşılaşmalar</a></li>
                                            <li><a class="footLink" href="/sportbook" title="Spor Bahisleri">Spor Bahisleri</a></li>
                                            <li><a class="footLink" href="/slot" title="Slot Oyunları">Slot Oyunları</a></li>
                                            <li><a class="footLink" href="/livecasino" title="Canlı Casino">Canlı Casino</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="KYC Policies">KYC Policies</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Anti-Money Laundering">Anti-Money Laundering</a></li>
                                            <li><a class="footLink" href="javascript:void(0)" title="Accounts, Payouts &amp; Bonuses">Accounts, Payouts &amp; Bonuses</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <?php
                                $footerContentBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
                                $footerContentSiteName = (string) ($footerContentBranding['site_name'] ?? $ayar['site_adi'] ?? 'VegasRoyalSpin');
                                // footer_url varsa onu kullan, yoksa ana logo
                                $footerContentLogoUrl = (string) ($footerContentBranding['logo_footer_url'] ?? $footerContentBranding['logo_url'] ?? $ayar['logo_footer_url'] ?? $ayar['logo_url'] ?? '');
                                if (class_exists('ApiMediaUrl', false)) {
                                    $footerContentLogoUrl = ApiMediaUrl::resolve($footerContentLogoUrl);
                                }
                                ?>
                                <div class="col-12 mt-4 d-flex flex-column align-items-center">
                                    <div class="mb-3">
                                        <?php if ($footerContentLogoUrl !== ''): ?>
                                        <img loading="lazy" data-site-logo-link src="<?= htmlspecialchars($footerContentLogoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                             alt="<?= htmlspecialchars($footerContentSiteName, ENT_QUOTES, 'UTF-8') ?>" width="308" height="102"
                                             style="max-width: 308px; height: auto; display: block;">
                                        <?php endif; ?>
                                    </div>
                                    <a href="/android.apk" class="footer-android-link" aria-label="Android Uygulama İndir">
                                        <img loading="lazy" src="/assets/images/androiduygulamaindir.svg" alt="Android Uygulama İndir" class="footer-android-img" width="200" height="60">
                                    </a>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div                                        class="footerProvider d-flex justify-content-center align-items-center">
                                        <div                                            class="position-relative provider-style-div border-left-0 border-right-0">
                                            <h3>Canlı Casino</h3>
                                            <ul class="d-flex  flex-wrap"><!---->
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/sagaming/logo.png"
                                                            alt="undefined" id="img-0"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/amigogaming/logo.png"
                                                            alt="undefined" id="img-1"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/evolution/logo.png"
                                                            alt="undefined" id="img-2"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/betgames/logo.png"
                                                            alt="undefined" id="img-3"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/vivogaming/logo.png"
                                                            alt="undefined" id="img-4"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/lottoinstantwin/logo.png"
                                                            alt="undefined" id="img-5"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/mascot/logo.png"
                                                            alt="undefined" id="img-6"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/redtiger/logo.png"
                                                            alt="undefined" id="img-7"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/superspadegames/logo.png"
                                                            alt="undefined" id="img-8"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/iconic21/logo.png"
                                                            alt="undefined" id="img-9"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/pragmaticplaylive/logo.png"
                                                            alt="undefined" id="img-10"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/xprogaming/logo.png"
                                                            alt="undefined" id="img-11"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/pgsoft/logo.png"
                                                            alt="undefined" id="img-12"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/ezugi/logo.png"
                                                            alt="undefined" id="img-13"></a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div                                        class="footerProvider casinoProviderFtr d-flex justify-content-center align-items-center">
                                        <div                                            class="position-relative provider-style-div border-left-0 border-right-0">
                                            <h3>Casino</h3>
                                            <ul class="d-flex  flex-wrap"><!---->
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/pragmaticplay/logo.png"
                                                            alt="undefined" id="img-0"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/349420.png"
                                                            alt="undefined" id="img-1"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/103729.png"
                                                            alt="undefined" id="img-2"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/spribe/logo.png"
                                                            alt="undefined" id="img-3"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/aviatrix/logo.png"
                                                            alt="undefined" id="img-4"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/blueprint/logo.png"
                                                            alt="undefined" id="img-5"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/revolvergaming/logo.png"
                                                            alt="undefined" id="img-6"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/bgaming/logo.png"
                                                            alt="undefined" id="img-7"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/729161.png"
                                                            alt="undefined" id="img-8"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/apparat/logo.png"
                                                            alt="undefined" id="img-9"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/triplecherry/logo.png"
                                                            alt="undefined" id="img-10"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/gamebeat/logo.png"
                                                            alt="undefined" id="img-12"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/682736.png"
                                                            alt="undefined" id="img-13"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/842539.png"
                                                            alt="undefined" id="img-14"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/640474.png"
                                                            alt="undefined" id="img-15"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/87676.png"
                                                            alt="undefined" id="img-16"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/zeusplay/logo.png"
                                                            alt="undefined" id="img-17"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/netent/logo.png"
                                                            alt="undefined" id="img-18"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/23632.png"
                                                            alt="undefined" id="img-19"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/onetouch/logo.png"
                                                            alt="undefined" id="img-20"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/elbet/logo.png"
                                                            alt="undefined" id="img-21"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/755954.png"
                                                            alt="undefined" id="img-22"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/539140.png"
                                                            alt="undefined" id="img-23"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/quickspin/logo.png"
                                                            alt="undefined" id="img-24"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/234813.png"
                                                            alt="undefined" id="img-25"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/prod/v2-dashboard-pi/casino-provider/20230822/711467.png"
                                                            alt="undefined" id="img-26"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/betsoft/logo.png"
                                                            alt="undefined" id="img-27"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/belatragames/logo.png"
                                                            alt="undefined" id="img-28"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/threeoaks/logo.png"
                                                            alt="undefined" id="img-29"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/igrosoft/logo.png"
                                                            alt="undefined" id="img-30"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/spadegaming/logo.png"
                                                            alt="undefined" id="img-31"></a></li>
                                                <li><a
 href="javascript:void(0)"><img
 loading="lazy"
                                                            src="https://s3.ap-south-1.amazonaws.com/assets.iceexchange.com/pi-casino-df-img/slg/turbogames/logo.png"
                                                            alt="undefined" id="img-32"></a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="footer-begambleaware text-center mb-3">
                                        <img loading="lazy" src="/assets/images/begambleaware.png"
                                             alt="BeGambleAware - Sorumlu Oyun" width="120" height="38"
                                             style="height: 38px; width: auto; display: inline-block;">
                                    </div>
                                    <div class="copytext">
                                        <p>VegasRoyalSpin ile buyuk hayal et, buyuk kazan! 2000'den
                                            fazla casino oyunu, spor etkinlikleri ve bahis seçenekleri ile -
                                            becerilerin, sansin ve bilginle buyuk kazanclar kazanmak icin bahis yap.
                                            Sansini dene!</p>
                                    </div>
                                    <div class="payment-center">
                                        <div>
                                            <h6 class="payText">ÖDEME YÖNTEMLERİ</h6>
                                            <div class="filterTab">
                                                <ul class="paymentLogos"
                                                    id="footer-payment-modes">
                                                    <!-- Anında işlemler + ödeme sağlayıcıları (hepsi assets/images/payments/) -->
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/anında-banka.png" alt="Anında Banka" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/anında-havale.png" alt="Anında Havale" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/anında-kripto.png" alt="Anında Kripto" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/anında-mefete.png" alt="Anında Mefete" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/anında-papara.png" alt="Anında Papara" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/cmt.png" alt="CMT" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/payco.png" alt="Payco" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/fulgur-pay.png" alt="Fulgur Pay" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/maxpara-fast.png" alt="Maxpara Fast" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/maxpara-papara.png" alt="Maxpara Papara" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/parola.png" alt="Parola" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/paybol.png" alt="Paybol" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/paypay.png" alt="PayPay" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/popy.png" alt="Popy" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/hızlı-havale.png" alt="Hızlı Havale" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/kredi-kartı.png" alt="Kredi Kartı" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/kripto.png" alt="Kripto" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/papara.png" alt="Papara" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/paratim.png" alt="Paratim" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/pep.png" alt="Pep" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/valepays/havale.png" alt="ValePays Havale" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/valepays/kredikarti.png" alt="ValePays Kredi Kartı" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/valepays/kripto.png" alt="ValePays Kripto" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/xpay/hizli-havale.png" alt="XPay Hızlı Havale" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/xpay/kkarti.png" alt="XPay Kredi Kartı" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/xpay/kripto.png" alt="XPay Kripto" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/xpay/papel.png" alt="XPay Papel">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/havale.png" alt="Havale" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/kredikarti.png" alt="Kredi Kartı" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/crypto.png" alt="Kripto">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/parazula.png" alt="Parazula" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/mefete.png" alt="Mefete">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/payfix.png" alt="Payfix" width="64" height="40">
                                                    </li>
                                                    <li class="ftpayLogo">
                                                        <img loading="lazy" src="assets/images/payments/pep.png" alt="Pep" width="64" height="40">
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row pt-4 border-top copyRight">
                                <div class="col-12 col-lg-8">
                                    <div class="reslogoRow text-center text-lg-left">
                                        <span class="resLogo ml-0">
                                            <img                                                 alt="18+"
                                                 height="45"
                                                 loading="lazy"
                                                 src="assets/images/18plus.png"
                                                 width="46">
                                        </span>
                                        <span class="resLogo">
                                            <img                                                 alt="EGF"
                                                 height="45"
                                                 loading="lazy"
                                                 src="assets/images/egf-logo.png"
                                                 width="230">
                                        </span>
                                        <span class="resLogo">
                                            <img                                                 alt="DigiCert"
                                                 height="45"
                                                 loading="lazy"
                                                 src="assets/images/digicert-logo.png"
                                                 width="124">
                                        </span>
                                        <span class="resLogo">
                                            <img                                                 alt="GC"
                                                 height="45"
                                                 loading="lazy"
                                                 src="assets/images/gc-logo.png"
                                                 width="137">
                                        </span>
                                    </div>
                                </div>
                                <div class="col-lg-4 mt-0 text-center text-lg-right">
                                    <p style="margin-bottom: 2rem;">Copyright © 2016 - 2026. All rights reserved.</p>
                                </div>
                            </div>
                        </div>
                    </footer>
                </div>
