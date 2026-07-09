<?php

$promotions = is_array($promotions ?? null) ? $promotions : [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 25);
$totalPages = (int) ($totalPages ?? 1);
$search = (string) ($search ?? '');
$flash = trim((string) ($flash ?? ''));

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => '₺' . number_format((float) $v, 2, ',', '.');
$badgeClass = static function (string $v): string {
    return match (strtolower($v)) {
        'active' => 'success dot',
        'inactive', 'draft' => 'warning dot',
        default => 'primary',
    };
};
$modalFieldValue = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Marketing</span>
        <h1 class="hero-title">Promosyonlar <span class="accent">yönetimi</span></h1>
        <p class="hero-sub">Aktif promosyonları yönetin, yeni promosyon ekleyin, kullanıcılara bonus atayın.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= $text(AdminAuth::url('/promotion/create')) ?>">Promosyon ekle</a>
    </div>
</div>

<?php if ($flash !== ''): ?>
    <div class="alert alert--<?= str_contains($flash, 'başarısız') || str_contains($flash, 'Geçersiz') ? 'danger' : 'success' ?>"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Liste</span>
            <h2 class="card-title">Tüm promosyonlar <span class="badge primary"><?= $total ?></span></h2>
        </div>
        <form method="get" action="<?= $text(AdminAuth::url('/promotions')) ?>" style="display:flex;gap:8px;align-items:center">
            <input class="input" type="search" name="search" value="<?= $text($search) ?>" placeholder="Başlık, tip veya kategori..." style="min-width:240px">
            <button class="btn btn--ghost" type="submit">Ara</button>
            <?php if ($search !== ''): ?><a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">Temizle</a><?php endif; ?>
        </form>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Tip</th>
                    <th>Kategori</th>
                    <th>Durum</th>
                    <th>Bonus</th>
                    <th>Çevrim</th>
                    <th>Sıra</th>
                    <th style="width:18%">İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($promotions === []): ?>
                <tr><td colspan="9">Promosyon bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($promotions as $promo): ?>
                    <tr>
                        <td><?= $text($promo['id'] ?? '') ?></td>
                        <td>
                            <?php $img = trim((string) ($promo['image_url'] ?? '')); ?>
                            <div style="display:flex;align-items:center;gap:8px">
                                <?php if ($img !== ''): ?><img src="<?= $text($img) ?>" alt="" style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid var(--border)"><?php endif; ?>
                                <span style="font-weight:700"><?= $text($promo['title'] ?? '') ?></span>
                            </div>
                        </td>
                        <td><?= $text($promo['type'] ?? '-') ?></td>
                        <td><?= $text($promo['category'] ?? '-') ?></td>
                        <td><span class="badge <?= $text($badgeClass((string) ($promo['status'] ?? ''))) ?>"><?= $text($promo['status'] ?? '') ?></span></td>
                        <td><span class="data-cell-mono"><?= $text($money($promo['bonus_amount'] ?? 0)) ?></span></td>
                        <td><?= $text($promo['wagering_multiplier'] ?? 0) ?>x</td>
                        <td><?= $text($promo['sort_order'] ?? 0) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <button
                                    type="button"
                                    class="btn btn--ghost js-open-promo-edit"
                                    style="font-size:11px;padding:4px 8px"
                                    data-id="<?= $modalFieldValue($promo['id'] ?? '') ?>"
                                    data-title="<?= $modalFieldValue($promo['title'] ?? '') ?>"
                                    data-description="<?= $modalFieldValue($promo['description'] ?? '') ?>"
                                    data-type="<?= $modalFieldValue($promo['type'] ?? '') ?>"
                                    data-category="<?= $modalFieldValue($promo['category'] ?? '') ?>"
                                    data-status="<?= $modalFieldValue($promo['status'] ?? 'active') ?>"
                                    data-sort-order="<?= $modalFieldValue($promo['sort_order'] ?? 0) ?>"
                                    data-bonus-amount="<?= $modalFieldValue($promo['bonus_amount'] ?? 0) ?>"
                                    data-wagering-multiplier="<?= $modalFieldValue($promo['wagering_multiplier'] ?? 0) ?>"
                                    data-image-url="<?= $modalFieldValue($promo['image_url'] ?? '') ?>"
                                >Düzenle</button>
                                <a class="btn btn--ghost" style="font-size:11px;padding:4px 8px" href="<?= $text(AdminAuth::url('/promotion/claims?id=' . rawurlencode((string) ($promo['id'] ?? '')))) ?>">Talepler</a>
                                <form method="post" action="<?= $text(AdminAuth::url('/promotion/delete')) ?>" onsubmit="return confirm('Promosyonu silmek istediğinizden emin misiniz?')" style="display:inline">
                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= $text($promo['id'] ?? '') ?>">
                                    <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px;color:var(--danger)" type="submit">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;font-size:12px;color:var(--t-light)">
        <span><?= number_format($total) ?> kayıt</span>
        <div style="display:flex;gap:4px">
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                <a href="<?= $text(AdminAuth::url('/promotions?page=' . $p . ($search !== '' ? '&search=' . rawurlencode($search) : ''))) ?>"
                   style="padding:4px 8px;border-radius:6px;<?= $p === $page ? 'background:var(--accent);color:#fff;font-weight:700' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<div id="promoEditModal" style="display:none;position:fixed;inset:0;background:rgba(9,11,20,.58);z-index:3000;align-items:center;justify-content:center;padding:18px">
    <div style="width:min(760px,96vw);max-height:92vh;overflow:auto;background:#fff;border-radius:14px;border:1px solid #dbe2ea;box-shadow:0 20px 40px rgba(8,14,30,.25)">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e9eef3">
            <h3 style="margin:0;font-size:18px;font-weight:800;color:#101828">Promosyon Düzenle</h3>
            <button type="button" id="promoEditClose" class="btn btn--ghost" style="font-size:12px;padding:6px 10px">Kapat</button>
        </div>

        <form method="post" action="<?= $text(AdminAuth::url('/promotion/update')) ?>" style="padding:16px">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" id="promoEditId" name="id" value="">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="grid-column:1/-1">
                    <label class="field-label" for="promoEditTitle">Başlık</label>
                    <input class="input" id="promoEditTitle" type="text" name="title" required maxlength="255">
                </div>

                <div style="grid-column:1/-1">
                    <label class="field-label" for="promoEditImageUrl">Image URL</label>
                    <input class="input" id="promoEditImageUrl" type="text" name="image_url" maxlength="700" placeholder="https://icons... veya /uploads/...">
                    <small style="display:block;margin-top:6px;color:#667085">Kaydet sırasında image URL otomatik normalize edilir.</small>
                </div>

                <div style="grid-column:1/-1">
                    <img id="promoEditImagePreview" src="" alt="" style="max-height:140px;max-width:100%;border:1px solid #e2e8f0;border-radius:8px;object-fit:contain;background:#f8fafc;padding:8px;display:none">
                </div>

                <div style="grid-column:1/-1">
                    <label class="field-label" for="promoEditDescription">Açıklama</label>
                    <textarea class="input" id="promoEditDescription" name="description" rows="4" style="resize:vertical;height:100px"></textarea>
                </div>

                <div>
                    <label class="field-label" for="promoEditType">Tip</label>
                    <input class="input" id="promoEditType" type="text" name="type" maxlength="60">
                </div>

                <div>
                    <label class="field-label" for="promoEditCategory">Kategori</label>
                    <input class="input" id="promoEditCategory" type="text" name="category" maxlength="60">
                </div>

                <div>
                    <label class="field-label" for="promoEditStatus">Durum</label>
                    <select id="promoEditStatus" class="select" name="status">
                        <option value="active">Aktif</option>
                        <option value="inactive">İnaktif</option>
                        <option value="draft">Taslak</option>
                    </select>
                </div>

                <div>
                    <label class="field-label" for="promoEditSortOrder">Sıra</label>
                    <input class="input" id="promoEditSortOrder" type="number" name="sort_order" min="0">
                </div>

                <div>
                    <label class="field-label" for="promoEditBonusAmount">Bonus (₺)</label>
                    <input class="input" id="promoEditBonusAmount" type="number" name="bonus_amount" min="0" step="0.01">
                </div>

                <div>
                    <label class="field-label" for="promoEditWageringMultiplier">Çevrim (x)</label>
                    <input class="input" id="promoEditWageringMultiplier" type="number" name="wagering_multiplier" min="0" step="0.1">
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
                <button type="button" id="promoEditCancel" class="btn btn--ghost">İptal</button>
                <button type="submit" class="btn btn--primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('promoEditModal');
    if (!modal) return;

    var closeBtn = document.getElementById('promoEditClose');
    var cancelBtn = document.getElementById('promoEditCancel');
    var preview = document.getElementById('promoEditImagePreview');
    var imageInput = document.getElementById('promoEditImageUrl');

    var fields = {
        id: document.getElementById('promoEditId'),
        title: document.getElementById('promoEditTitle'),
        description: document.getElementById('promoEditDescription'),
        type: document.getElementById('promoEditType'),
        category: document.getElementById('promoEditCategory'),
        status: document.getElementById('promoEditStatus'),
        sortOrder: document.getElementById('promoEditSortOrder'),
        bonusAmount: document.getElementById('promoEditBonusAmount'),
        wageringMultiplier: document.getElementById('promoEditWageringMultiplier'),
        imageUrl: imageInput
    };

    function toggleModal(show) {
        modal.style.display = show ? 'flex' : 'none';
        document.body.style.overflow = show ? 'hidden' : '';
    }

    function updateImagePreview() {
        var src = (imageInput && imageInput.value ? imageInput.value : '').trim();
        if (!src) {
            preview.style.display = 'none';
            preview.removeAttribute('src');
            return;
        }
        preview.style.display = 'block';
        preview.src = src;
    }

    function fillFromButton(btn) {
        fields.id.value = btn.getAttribute('data-id') || '';
        fields.title.value = btn.getAttribute('data-title') || '';
        fields.description.value = btn.getAttribute('data-description') || '';
        fields.type.value = btn.getAttribute('data-type') || '';
        fields.category.value = btn.getAttribute('data-category') || '';
        fields.status.value = btn.getAttribute('data-status') || 'active';
        fields.sortOrder.value = btn.getAttribute('data-sort-order') || '0';
        fields.bonusAmount.value = btn.getAttribute('data-bonus-amount') || '0';
        fields.wageringMultiplier.value = btn.getAttribute('data-wagering-multiplier') || '0';
        fields.imageUrl.value = btn.getAttribute('data-image-url') || '';
        updateImagePreview();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-open-promo-edit');
        if (!btn) return;
        e.preventDefault();
        fillFromButton(btn);
        toggleModal(true);
    });

    function closeModal() { toggleModal(false); }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (imageInput) imageInput.addEventListener('input', updateImagePreview);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
})();
</script>
</section>
