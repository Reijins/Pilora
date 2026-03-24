<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Nouveau client</h2>
            <p class="muted">Création rapide d’une fiche client (squelette).</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <form method="POST" action="<?= htmlspecialchars($basePath . '/clients/create', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:760px;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <label class="label" for="client_type">Type de compte</label>
                    <select class="input" id="client_type" name="client_type">
                        <option value="entreprise" selected>Entreprise</option>
                        <option value="particulier">Particulier</option>
                    </select>

                    <label class="label" id="siret_label" for="siret">SIRET</label>
                    <input class="input" id="siret" name="siret" type="text" maxlength="14" placeholder="14 chiffres">

                    <label class="label" id="first_name_label" for="first_name" style="display:none;">Prénom</label>
                    <input class="input" id="first_name" name="first_name" type="text" style="display:none;">

                    <label class="label" for="name">Nom</label>
                    <input class="input" id="name" name="name" type="text" required>

                    <label class="label" for="phone">Téléphone</label>
                    <input class="input" id="phone" name="phone" type="text" autocomplete="tel">

                    <label class="label" for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" autocomplete="email">

                    <label class="label" for="address">Adresse</label>
                    <input class="input" id="address" name="address" type="text">

                    <label class="label" for="notes">Notes</label>
                    <input class="input" id="notes" name="notes" type="text">

                    <label class="label" for="accounting_customer_account">Compte client (comptabilité)</label>
                    <input class="input" id="accounting_customer_account" name="accounting_customer_account" type="text" maxlength="32" placeholder="411xxx — laisser vide pour utiliser le défaut société">
                    <p class="muted" style="margin:4px 0 0;font-size:13px;">Optionnel. Sert pour l’export des écritures (grand-livre / tiers client).</p>

                    <label class="checkbox-item" id="create_contact_wrapper" style="padding:0; margin-top:4px; display:none;">
                        <input type="checkbox" name="create_contact_with_client" value="1">
                        <span>Créer en tant que contact</span>
                    </label>

                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <button class="btn btn-primary" type="submit">Créer</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                    </div>
                </form>
                <script>
                    (function () {
                        var type = document.getElementById('client_type');
                        var siretLabel = document.getElementById('siret_label');
                        var siretInput = document.getElementById('siret');
                        var firstNameLabel = document.getElementById('first_name_label');
                        var firstNameInput = document.getElementById('first_name');
                        var createContact = document.getElementById('create_contact_wrapper');
                        if (!type || !siretLabel || !siretInput || !firstNameLabel || !firstNameInput || !createContact) return;
                        function sync() {
                            var isIndividual = type.value === 'particulier';
                            siretLabel.style.display = isIndividual ? 'none' : 'block';
                            siretInput.style.display = isIndividual ? 'none' : 'block';
                            firstNameLabel.style.display = isIndividual ? 'block' : 'none';
                            firstNameInput.style.display = isIndividual ? 'block' : 'none';
                            createContact.style.display = isIndividual ? 'inline-flex' : 'none';
                        }
                        type.addEventListener('change', sync);
                        sync();
                    })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</section>

