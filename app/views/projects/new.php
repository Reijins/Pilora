<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken, $clients, $flashMessage, $flashError
?>
<section class="page">
    <div class="card affair-form-shell">
        <div class="card-header sheet-header">
            <h2 class="sheet-title">Nouvelle affaire</h2>
            <p class="muted" style="max-width:760px;">
                Crée une affaire en une seule action : chantier + devis lié automatiquement.
                Renseigne les informations principales ci-dessous.
            </p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <?php if (is_string($flashMessage ?? null) && trim((string) $flashMessage) !== ''): ?>
                    <div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= htmlspecialchars($basePath . '/projects/create', ENT_QUOTES, 'UTF-8') ?>" class="affair-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="affair-grid">
                        <section class="section-card">
                            <h3 class="section-title">Informations affaire</h3>
                            <div class="section-content affair-fields">
                                <div class="field">
                                    <label class="label" for="client_id">Client</label>
                                    <select class="input" id="client_id" name="client_id" required>
                                        <?php foreach (($clients ?? []) as $c): ?>
                                            <option
                                                value="<?= (int) ($c['id'] ?? 0) ?>"
                                                <?= (int) ($c['id'] ?? 0) === (int) ($selectedClientId ?? 0) ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label class="label" for="name">Nom de l'affaire</label>
                                    <input class="input" id="name" name="name" type="text" required placeholder="Ex: Rénovation complète Villa Dupont">
                                </div>
                                <div class="field">
                                    <label class="label" for="contact_id">Contact affaire</label>
                                    <select class="input" id="contact_id" name="contact_id">
                                        <option value="">Aucun</option>
                                        <?php foreach (($contacts ?? []) as $ct): ?>
                                            <option value="<?= (int) ($ct['id'] ?? 0) ?>" data-client-id="<?= (int) ($ct['clientId'] ?? 0) ?>">
                                                <?= htmlspecialchars(trim((string) ($ct['firstName'] ?? '') . ' ' . (string) ($ct['lastName'] ?? '')) ?: ('Contact #' . (int) ($ct['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <input type="hidden" name="status" value="planned">

                                <div class="field">
                                    <label class="label" for="planned_start_date">Démarrage prévu</label>
                                    <input class="input" id="planned_start_date" name="planned_start_date" type="date">
                                </div>

                                <div class="field">
                                    <label class="label" for="planned_end_date">Fin prévue</label>
                                    <input class="input" id="planned_end_date" name="planned_end_date" type="date">
                                </div>

                                <div class="field affair-field-full">
                                    <label class="label" for="site_address">Adresse chantier</label>
                                    <input class="input" id="site_address" name="site_address" type="text" placeholder="Ex: 18 rue des Lilas">
                                </div>

                                <div class="field">
                                    <label class="label" for="site_postal_code">Code postal</label>
                                    <input class="input" id="site_postal_code" name="site_postal_code" type="text" placeholder="75000">
                                </div>

                                <div class="field">
                                    <label class="label" for="site_city">Ville</label>
                                    <input class="input" id="site_city" name="site_city" type="text" placeholder="Paris">
                                </div>

                                <div class="field affair-field-full">
                                    <label class="label" for="notes">Contexte / notes</label>
                                    <textarea class="input affair-textarea" id="notes" name="notes" placeholder="Contraintes, accès chantier, points d'attention..."></textarea>
                                </div>
                            </div>
                        </section>

                        <?php if (!empty($canCreateQuote)): ?>
                            <section class="section-card">
                                <h3 class="section-title">Contenu du devis lié</h3>
                                <div class="section-content affair-fields">
                                    <p class="muted affair-note">
                                        Le devis brouillon est créé automatiquement. Tu peux composer les prestations ici.
                                    </p>

                                    <div class="field affair-field-full">
                                        <label class="label" for="quote_title">Titre du devis</label>
                                        <input class="input" id="quote_title" name="quote_title" type="text" placeholder="Ex: Devis initial - gros oeuvre">
                                    </div>

                                    <div class="field affair-field-full">
                                        <label class="checkbox-item" style="padding:0; margin:0;">
                                            <input type="checkbox" name="send_quote_email" value="1" checked>
                                            <span>Envoyer le devis par email</span>
                                        </label>
                                    </div>

                                    <?php
                                        $datalistOptions = [];
                                        foreach (($priceItems ?? []) as $it) {
                                            $nm = trim((string) ($it['name'] ?? ''));
                                            if ($nm !== '') $datalistOptions[] = $nm;
                                        }
                                        $datalistOptions = array_values(array_unique($datalistOptions));
                                    ?>
                                    <datalist id="affairPriceLibraryNames">
                                        <?php foreach ($datalistOptions as $nm): ?>
                                            <option value="<?= htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>

                                    <div class="affair-field-full">
                                        <div style="overflow-x:visible;">
                                            <table class="table" id="affair-quote-items-table" style="table-layout:fixed; width:100%;">
                                                <thead>
                                                <tr>
                                                    <th style="width:50%;">Prestation</th>
                                                    <th style="width:9%;">Qté</th>
                                                    <th style="width:15%;">Prix (€)</th>
                                                    <th style="width:12%;">Temps (min)</th>
                                                    <th style="width:9%;">Biblio</th>
                                                    <th style="width:5%;"></th>
                                                </tr>
                                                </thead>
                                                <tbody id="affair-quote-items-body">
                                                <tr class="affair-quote-item-row" data-row-index="0">
                                                    <td style="width:50%; min-width:240px;">
                                                        <input class="input affair-item-name-input" name="item_name[]" type="text" list="affairPriceLibraryNames" placeholder="Nom de la prestation" required style="width:100%;">
                                                        <input type="hidden" class="affair-item-price-item-id" name="item_price_item_id[]" value="">
                                                    </td>
                                                    <td style="width:9%;"><input class="input affair-item-quantity" name="item_quantity[]" type="number" step="0.01" value="1" style="width:100%; max-width:none;"></td>
                                                    <td style="width:15%;"><input class="input affair-item-unit-price" name="item_unit_price[]" type="number" step="0.01" value="0" style="width:100%; max-width:none;"></td>
                                                    <td style="width:12%;"><input class="input affair-item-est-time" name="item_estimated_time_minutes[]" type="number" step="1" style="width:100%; max-width:none;"></td>
                                                    <td>
                                                        <label class="checkbox-item" style="padding:0; margin:0; justify-content:center;">
                                                            <input type="checkbox" class="affair-item-save-library" name="item_save_to_library[0]" value="1">
                                                        </label>
                                                    </td>
                                                    <td style="width:5%; text-align:center;">
                                                        <button class="btn btn-danger btn-icon affair-btn-remove-row" type="button" aria-label="Supprimer la ligne"><span aria-hidden="true">&times;</span></button>
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="affair-field-full">
                                        <button class="btn btn-secondary" id="affair-btn-add-row" type="button">Ajouter une prestation</button>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>

                    <div class="affair-actions">
                        <button class="btn btn-primary" type="submit">Créer l'affaire</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients/show?clientId=' . (int) ($selectedClientId ?? 0), ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                    </div>
                </form>
                <?php if (!empty($canCreateQuote)): ?>
                    <script>
                        (function () {
                            var affairNameInput = document.getElementById('name');
                            var quoteTitleInput = document.getElementById('quote_title');
                            var clientSelect = document.getElementById('client_id');
                            var contactSelect = document.getElementById('contact_id');
                            var quoteTitleManuallyEdited = false;

                            function filterContactsByClient() {
                                if (!clientSelect || !contactSelect) return;
                                var currentClientId = String(clientSelect.value || '');
                                Array.prototype.forEach.call(contactSelect.options, function (opt, idx) {
                                    if (idx === 0) return;
                                    var cid = String(opt.getAttribute('data-client-id') || '');
                                    opt.hidden = currentClientId !== '' && cid !== currentClientId;
                                });
                                if (contactSelect.selectedIndex > 0) {
                                    var selected = contactSelect.options[contactSelect.selectedIndex];
                                    if (selected && selected.hidden) contactSelect.selectedIndex = 0;
                                }
                            }
                            if (clientSelect && contactSelect) {
                                clientSelect.addEventListener('change', filterContactsByClient);
                                filterContactsByClient();
                            }

                            if (quoteTitleInput) {
                                quoteTitleInput.addEventListener('input', function () {
                                    quoteTitleManuallyEdited = (quoteTitleInput.value || '').trim() !== '';
                                });
                            }
                            if (affairNameInput && quoteTitleInput) {
                                function syncQuoteTitleFromAffairName() {
                                    if (quoteTitleManuallyEdited) return;
                                    quoteTitleInput.value = (affairNameInput.value || '').trim();
                                }
                                affairNameInput.addEventListener('input', syncQuoteTitleFromAffairName);
                                syncQuoteTitleFromAffairName();
                            }

                            var body = document.getElementById('affair-quote-items-body');
                            var btnAdd = document.getElementById('affair-btn-add-row');
                            if (!body || !btnAdd) return;

                            var priceCatalog = <?=
                                json_encode(array_map(function ($it) {
                                    return [
                                        'id' => (int) ($it['id'] ?? 0),
                                        'name' => (string) ($it['name'] ?? ''),
                                        'description' => (string) ($it['description'] ?? ''),
                                        'unitPrice' => (float) ($it['unitPrice'] ?? 0),
                                        'estimatedTimeMinutes' => $it['estimatedTimeMinutes'] ?? null,
                                    ];
                                }, ($priceItems ?? [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>;

                            function normalize(s) { return (s || '').toString().trim().toLowerCase(); }

                            var byName = {};
                            priceCatalog.forEach(function (it) {
                                var nm = normalize(it.name);
                                if (nm && !byName[nm]) byName[nm] = it;
                            });

                            function syncRowFromName(row) {
                                var nameInput = row.querySelector('.affair-item-name-input');
                                var hiddenId = row.querySelector('.affair-item-price-item-id');
                                var unitPriceInput = row.querySelector('.affair-item-unit-price');
                                var timeInput = row.querySelector('.affair-item-est-time');
                                var saveCb = row.querySelector('.affair-item-save-library');
                                if (!nameInput || !hiddenId) return;

                                var match = byName[normalize(nameInput.value || '')] || null;
                                if (match) {
                                    hiddenId.value = String(match.id);
                                    var currentPrice = parseFloat(unitPriceInput.value || '0');
                                    if (!unitPriceInput.value || isNaN(currentPrice) || currentPrice <= 0) {
                                        unitPriceInput.value = String(match.unitPrice);
                                    }
                                    var tRaw = timeInput.value || '';
                                    var t = tRaw === '' ? NaN : parseFloat(tRaw);
                                    if (tRaw === '' || isNaN(t)) {
                                        if (match.estimatedTimeMinutes !== null && match.estimatedTimeMinutes !== undefined) {
                                            timeInput.value = String(match.estimatedTimeMinutes);
                                        }
                                    }
                                    if (saveCb) saveCb.checked = false;
                                } else {
                                    hiddenId.value = '';
                                }
                            }

                            function reindexRows() {
                                var rows = body.querySelectorAll('.affair-quote-item-row');
                                rows.forEach(function (row, idx) {
                                    row.setAttribute('data-row-index', String(idx));
                                    var cb = row.querySelector('.affair-item-save-library');
                                    if (cb) cb.setAttribute('name', 'item_save_to_library[' + idx + ']');
                                });
                            }

                            function bindRow(row) {
                                var nameInput = row.querySelector('.affair-item-name-input');
                                var removeBtn = row.querySelector('.affair-btn-remove-row');
                                if (nameInput) {
                                    nameInput.addEventListener('change', function () { syncRowFromName(row); });
                                    nameInput.addEventListener('blur', function () { syncRowFromName(row); });
                                }
                                if (removeBtn) {
                                    removeBtn.addEventListener('click', function () {
                                        if (body.children.length <= 1) return;
                                        row.remove();
                                        reindexRows();
                                    });
                                }
                            }

                            btnAdd.addEventListener('click', function () {
                                var first = body.querySelector('.affair-quote-item-row');
                                if (!first) return;
                                var clone = first.cloneNode(true);
                                clone.querySelectorAll('input').forEach(function (input) {
                                    if (input.type === 'checkbox') input.checked = false;
                                    else if (input.classList.contains('affair-item-quantity')) input.value = '1';
                                    else if (input.classList.contains('affair-item-unit-price')) input.value = '0';
                                    else input.value = '';
                                });
                                body.appendChild(clone);
                                reindexRows();
                                bindRow(clone);
                            });

                            body.querySelectorAll('.affair-quote-item-row').forEach(bindRow);
                            reindexRows();
                        })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

