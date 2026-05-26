<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Nouveau client</h2>
            <p class="muted">Création rapide d'une fiche client.</p>
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

                    <!-- Champs entreprise -->
                    <div id="section-entreprise">
                        <label class="label" for="siret">SIRET</label>
                        <input class="input" id="siret" name="siret" type="text" maxlength="14" placeholder="14 chiffres">

                        <label class="label" for="name">Nom de l'entreprise</label>
                        <input class="input" id="name" name="name" type="text" required>
                    </div>

                    <!-- Champs particulier -->
                    <div id="section-particulier" style="display:none;">
                        <label class="label" for="first_name">Prénom</label>
                        <input class="input" id="first_name" name="first_name" type="text">

                        <label class="label" for="last_name_particulier">Nom</label>
                        <input class="input" id="last_name_particulier" name="last_name_particulier" type="text">

                        <label class="label" for="particulier_phone">Téléphone</label>
                        <input class="input" id="particulier_phone" name="particulier_phone" type="text" autocomplete="tel">

                        <label class="label" for="particulier_email">Email</label>
                        <input class="input" id="particulier_email" name="particulier_email" type="email" autocomplete="email">
                    </div>

                    <label class="label" for="address">Adresse</label>
                    <input class="input" id="address" name="address" type="text">

                    <label class="label" for="notes">Notes</label>
                    <input class="input" id="notes" name="notes" type="text">

                    <label class="label" for="accounting_customer_account">Compte client (comptabilité)</label>
                    <input class="input" id="accounting_customer_account" name="accounting_customer_account" type="text" maxlength="32" placeholder="411xxx — laisser vide pour utiliser le défaut société">
                    <p class="muted" style="margin:4px 0 0;font-size:13px;">Optionnel. Sert pour l'export des écritures (grand-livre / tiers client).</p>

                    <!-- Section Contact (entreprise uniquement) -->
                    <div id="section-contact" style="margin-top:20px;">
                        <h3 style="font-size:0.95rem; font-weight:600; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">Contact principal</h3>

                        <label class="label" for="contact_first_name">Prénom du contact</label>
                        <input class="input" id="contact_first_name" name="contact_first_name" type="text">

                        <label class="label" for="contact_last_name">Nom du contact</label>
                        <input class="input" id="contact_last_name" name="contact_last_name" type="text">

                        <label class="label" for="contact_function">Fonction</label>
                        <input class="input" id="contact_function" name="contact_function" type="text" placeholder="Ex: Gérant, Conducteur de travaux">

                        <label class="label" for="contact_phone">Téléphone</label>
                        <input class="input" id="contact_phone" name="contact_phone" type="text" autocomplete="tel">

                        <label class="label" for="contact_email">Email</label>
                        <input class="input" id="contact_email" name="contact_email" type="email" autocomplete="email">
                    </div>

                    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px;">
                        <button class="btn btn-primary" type="submit">Créer</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                    </div>
                </form>
                <script>
                    (function () {
                        var type = document.getElementById('client_type');
                        var sectionEntreprise = document.getElementById('section-entreprise');
                        var sectionParticulier = document.getElementById('section-particulier');
                        var sectionContact = document.getElementById('section-contact');
                        var nameInput = document.getElementById('name');
                        var firstNameInput = document.getElementById('first_name');
                        var lastNameParticulier = document.getElementById('last_name_particulier');
                        var contactFirstName = document.getElementById('contact_first_name');
                        var contactLastName = document.getElementById('contact_last_name');

                        function sync() {
                            var isIndividual = type.value === 'particulier';
                            sectionEntreprise.style.display = isIndividual ? 'none' : 'block';
                            sectionParticulier.style.display = isIndividual ? 'block' : 'none';
                            sectionContact.style.display = isIndividual ? 'none' : 'block';
                            nameInput.required = !isIndividual;
                        }

                        function capitalizeFirst(str) {
                            if (!str) return '';
                            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
                        }

                        nameInput.addEventListener('blur', function () {
                            if (type.value === 'entreprise' && nameInput.value.trim() !== '') {
                                nameInput.value = nameInput.value.trim().toUpperCase();
                            }
                        });

                        firstNameInput.addEventListener('blur', function () {
                            if (type.value === 'particulier' && firstNameInput.value.trim() !== '') {
                                firstNameInput.value = capitalizeFirst(firstNameInput.value.trim());
                            }
                        });

                        lastNameParticulier.addEventListener('blur', function () {
                            if (type.value === 'particulier' && lastNameParticulier.value.trim() !== '') {
                                lastNameParticulier.value = lastNameParticulier.value.trim().toUpperCase();
                            }
                        });

                        contactFirstName.addEventListener('blur', function () {
                            if (contactFirstName.value.trim() !== '') {
                                contactFirstName.value = capitalizeFirst(contactFirstName.value.trim());
                            }
                        });

                        contactLastName.addEventListener('blur', function () {
                            if (contactLastName.value.trim() !== '') {
                                contactLastName.value = contactLastName.value.trim().toUpperCase();
                            }
                        });

                        type.addEventListener('change', sync);
                        sync();
                    })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</section>
