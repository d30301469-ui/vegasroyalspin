<?php
/**
 * Bonus Detay Modal — koyu mor temalı bonus detay popup
 * İçerik JS ile (BonusDetailModal.open(data)) doldurulur.
 */
?>
<div id="bonus-detail-modal-overlay" class="bonus-modal-overlay" aria-hidden="true">
    <div id="bonus-detail-modal" class="bonus-modal" role="dialog" aria-modal="true" aria-labelledby="bonus-modal-title" aria-hidden="true" tabindex="-1">
        <button type="button" class="bonus-modal-close" aria-label="Modalı kapat"><span aria-hidden="true">&times;</span></button>
        <div class="bonus-modal-header">
            <button type="button" class="bonus-modal-back" aria-label="Geri">
                <svg class="bonus-modal-back-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>
            </button>
            <h2 id="bonus-modal-title" class="bonus-modal-title"></h2>
        </div>
        <div class="bonus-modal-body">
            <div class="bonus-modal-left">
                <div class="bonus-image-wrap">
                    <img id="bonus-modal-image" src="" alt="">
                </div>
            </div>
            <div class="bonus-modal-right">
                <div class="bonus-accordion-list" role="list"></div>
                <div class="bonus-modal-claim" id="bonus-modal-claim" hidden>
                    <div class="bonus-modal-claim-actions">
                        <a class="bonus-modal-claim-login bonus-modal-link" id="bonus-modal-link" href="#" hidden>Promosyona git</a>
                    </div>
                    <div class="bonus-modal-claim-actions">
                        <a class="bonus-modal-claim-login" id="bonus-modal-claim-login" href="/login">Giriş yap</a>
                        <button type="button" class="bonus-modal-claim-submit" id="bonus-modal-claim-submit">Bonus talep et</button>
                    </div>
                    <p class="bonus-modal-claim-status" id="bonus-modal-claim-status" role="status" aria-live="polite"></p>
                </div>
            </div>
        </div>
    </div>
</div>
