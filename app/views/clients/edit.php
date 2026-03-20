<?php
declare(strict_types=1);
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Modifier le compte client</h2>
        </div>
        <div class="card-body">
            <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
            <?php if (!empty($flashError)): ?><div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if (!empty($flashMessage)): ?><div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <form method="POST" action="<?= htmlspecialchars($basePath . '/clients/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:760px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">
                <?php $meta = is_array($clientMeta ?? null) ? $clientMeta : ['clientType' => 'entreprise', 'siret' => '', 'firstName' => '', 'createContactWithClient' => false]; ?>
                <label class="label" for="client_type">Type de compte</label>
                <select class="input" id="client_type" name="client_type">
                    <option value="entreprise" <?= ($meta['clientType'] ?? 'entreprise') === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                    <option value="particulier" <?= ($meta['clientType'] ?? '') === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                </select>
                <div id="company_fields">
                    <label class="label" for="siret">SIRET</label>
                    <input class="input" id="siret" name="siret" type="text" maxlength="14" value="<?= htmlspecialchars((string) ($meta['siret'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div id="individual_fields" style="display:none;">
                    <label class="label" for="first_name">Prénom</label>
                    <input class="input" id="first_name" name="first_name" type="text" value="<?= htmlspecialchars((string) ($meta['firstName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <label class="checkbox-item" style="padding:0; margin-top:8px;">
                        <input type="checkbox" name="create_contact_with_client" value="1" <?= !empty($meta['createContactWithClient']) ? 'checked' : '' ?>>
                        <span>Créer aussi le contact</span>
                    </label>
                </div>
                <label class="label" for="name">Nom</label>
                <input class="input" id="name" name="name" type="text" value="<?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                <label class="label" for="phone">Téléphone</label>
                <input class="input" id="phone" name="phone" type="text" value="<?= htmlspecialchars((string) ($client['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <label class="label" for="email">Email</label>
                <input class="input" id="email" name="email" type="email" value="<?= htmlspecialchars((string) ($client['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <label class="label" for="address">Adresse</label>
                <input class="input" id="address" name="address" type="text" value="<?= htmlspecialchars((string) ($client['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <label class="label" for="notes">Notes</label>
                <textarea class="input" id="notes" name="notes" style="min-height:120px;resize:vertical;"><?= htmlspecialchars((string) ($client['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="inline-actions">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients/show?clientId=' . (int) ($client['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Retour</a>
                </div>
            </form>
            <script>
                (function () {
                    var type = document.getElementById('client_type');
                    var company = document.getElementById('company_fields');
                    var individual = document.getElementById('individual_fields');
                    if (!type || !company || !individual) return;
                    function sync() {
                        var isIndividual = type.value === 'particulier';
                        company.style.display = isIndividual ? 'none' : 'block';
                        individual.style.display = isIndividual ? 'block' : 'none';
                    }
                    type.addEventListener('change', sync);
                    sync();
                })();
            </script>
        </div>
    </div>
</section>

