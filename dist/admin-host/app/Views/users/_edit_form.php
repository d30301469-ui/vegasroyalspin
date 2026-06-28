<?php

$user = is_array($user ?? null) ? $user : [];
$isModal = !empty($isModal);
$mode = (string) ($mode ?? 'edit');
$isCreate = $mode === 'create';
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$checked = static fn (mixed $value): string => ((string) $value === '1' || $value === 1 || $value === true) ? ' checked' : '';
$selected = static fn (mixed $value, string $expected): string => (string) $value === $expected ? ' selected' : '';
$userId = (string) ($user['id'] ?? '');
$formAction = $isCreate ? '/user/store' : '/user/update';
?>

<?php if (!$isModal): ?>
<style>
    .user-edit-form { display:flex; flex-direction:column; gap:18px; }
    .user-edit-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:18px 22px; }
    .user-edit-grid .span-2 { grid-column:span 2; }
    .user-edit-switches { display:flex; flex-wrap:wrap; gap:14px; }
    @media (max-width:720px) {
        .user-edit-grid { grid-template-columns:1fr; }
        .user-edit-grid .span-2 { grid-column:span 1; }
    }
</style>
<?php endif; ?>

<form id="userEditForm" class="user-edit-form" method="post" action="<?= htmlspecialchars(AdminAuth::url($formAction), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!$isCreate): ?>
        <input type="hidden" name="user_id" value="<?= $text($userId) ?>">
    <?php endif; ?>

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Profil</span>
                <h2 class="card-title">Kimlik ve iletişim bilgileri</h2>
            </div>
            <span class="badge primary"><?= $isCreate ? 'Yeni oyuncu' : 'ID: ' . $text($userId) ?></span>
        </div>

        <div class="user-edit-grid">
            <div class="field">
                <label class="field-label" for="userEditName">Ad <span class="req">*</span></label>
                <input id="userEditName" class="input" name="name" value="<?= $text($user['name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="userEditSurname">Soyad <span class="req">*</span></label>
                <input id="userEditSurname" class="input" name="surname" value="<?= $text($user['surname'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="userEditUsername">Kullanıcı adı <span class="req">*</span></label>
                <input id="userEditUsername" class="input" name="username" value="<?= $text($user['username'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="userEditEmail">Email <span class="req">*</span></label>
                <input id="userEditEmail" class="input" type="email" name="email" value="<?= $text($user['email'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="userEditIdentity">T.C. / Kimlik No<?= $isCreate ? ' <span class="req">*</span>' : '' ?></label>
                <input id="userEditIdentity" class="input" name="identity_number" value="<?= $text($user['identity_number'] ?? '') ?>"<?= $isCreate ? ' required' : '' ?>>
            </div>
            <div class="field">
                <label class="field-label" for="userEditPhone">Telefon <span class="req">*</span></label>
                <input id="userEditPhone" class="input" name="phone" value="<?= $text($user['phone'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="userEditGender">Cinsiyet <span class="req">*</span></label>
                <select id="userEditGender" class="select" name="gender" required>
                    <option value="">Seçiniz</option>
                    <option value="Erkek"<?= $selected($user['gender'] ?? '', 'Erkek') ?>>Erkek</option>
                    <option value="Kadın"<?= $selected($user['gender'] ?? '', 'Kadın') ?>>Kadın</option>
                    <option value="Diğer"<?= $selected($user['gender'] ?? '', 'Diğer') ?>>Diğer</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="userEditDob">Doğum tarihi <span class="req">*</span></label>
                <input id="userEditDob" class="input" type="date" name="dob" value="<?= $text($user['dob'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="userEditCountry">Ülke</label>
                <input id="userEditCountry" class="input" name="country" value="<?= $text($user['country'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="userEditCity">Şehir</label>
                <input id="userEditCity" class="input" name="city" value="<?= $text($user['city'] ?? '') ?>">
            </div>
            <div class="field span-2">
                <label class="field-label" for="userEditAddress">Adres</label>
                <textarea id="userEditAddress" class="textarea" name="address" rows="3"><?= $text($user['address'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Güvenlik</span>
                <h2 class="card-title">Şifre ve durum</h2>
            </div>
        </div>

        <div class="user-edit-grid">
            <div class="field">
                <label class="field-label" for="userEditPassword"><?= $isCreate ? 'Şifre' : 'Yeni şifre' ?><?= $isCreate ? ' <span class="req">*</span>' : '' ?></label>
                <input id="userEditPassword" class="input" type="password" name="password" autocomplete="new-password" minlength="6"<?= $isCreate ? ' required' : '' ?>>
                <div class="field-help"><?= $isCreate ? 'En az 6 karakter girin.' : 'Boş bırakırsanız mevcut şifre değişmez.' ?></div>
            </div>
            <div class="field">
                <label class="field-label" for="userEditPasswordConfirmation"><?= $isCreate ? 'Şifre tekrar' : 'Yeni şifre tekrar' ?><?= $isCreate ? ' <span class="req">*</span>' : '' ?></label>
                <input id="userEditPasswordConfirmation" class="input" type="password" name="password_confirmation" autocomplete="new-password" minlength="6"<?= $isCreate ? ' required' : '' ?>>
            </div>
            <div class="field span-2">
                <div class="user-edit-switches">
                    <label class="switch">
                        <input type="checkbox" name="is_verified" value="1"<?= $checked($user['is_verified'] ?? 0) ?>>
                        <span class="track"></span>
                        Doğrulanmış kullanıcı
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="banned" value="1"<?= $checked($user['banned'] ?? 0) ?>>
                        <span class="track"></span>
                        Banlı kullanıcı
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="is_test" value="1"<?= $checked($user['is_test'] ?? 0) ?>>
                        <span class="track"></span>
                        Test hesabı
                    </label>
                </div>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <span class="badge dot warning">Bakiye değişiklikleri detay ekranındaki bakiye işlemi panelinden yapılır.</span>
        <span class="spacer"></span>
        <?php if ($isModal): ?>
            <button class="btn btn--ghost" type="button" data-admin-modal-close>Vazgeç</button>
        <?php elseif ($isCreate): ?>
            <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/module?key=users'), ENT_QUOTES, 'UTF-8') ?>">Vazgeç</a>
        <?php else: ?>
            <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . rawurlencode($userId)), ENT_QUOTES, 'UTF-8') ?>">Vazgeç</a>
        <?php endif; ?>
        <button class="btn btn--primary" type="submit"><?= $isCreate ? 'Oyuncu Ekle' : 'Kullanıcıyı Güncelle' ?></button>
    </div>
</form>
