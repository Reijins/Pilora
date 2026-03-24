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
                            <?php $vatR = isset($quoteVatRate) && is_numeric($quoteVatRate) ? (float) $quoteVatRate : 20.0; ?>
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
                                        <div class="affair-quote-table-scroll" style="overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%;">
                                            <table class="table" id="affair-quote-items-table" style="table-layout:auto; min-width:860px; width:100%;">
                                                <thead>
                                                <tr>
                                                    <th style="width:34%;">Prestation</th>
                                                    <th style="width:7%;">Qté</th>
                                                    <th style="width:8%;">Unité</th>
                                                    <th style="width:12%;">PU HT (€)</th>
                                                    <th style="width:9%;">TVA %</th>
                                                    <th style="width:8%;">Temps (h)</th>
                                                    <th style="width:7%;">Biblio</th>
                                                    <th style="width:4%;"></th>
                                                </tr>
                                                </thead>
                                                <tbody id="affair-quote-items-body">
                                                <tr class="affair-quote-item-row" data-row-index="0">
                                                    <td style="min-width:160px;">
                                                        <input class="input affair-item-name-input" name="item_name[]" type="text" list="affairPriceLibraryNames" placeholder="Nom de la prestation" required style="width:100%;">
                                                        <input type="hidden" class="affair-item-price-item-id" name="item_price_item_id[]" value="">
                                                        <input type="hidden" class="affair-item-revenue-hidden" name="item_revenue_account[]" value="">
                                                    </td>
                                                    <td style="white-space:nowrap;"><input class="input affair-item-quantity" name="item_quantity[]" type="number" step="1" min="0" value="1" style="min-width:4.75rem; width:5.25rem; max-width:none;"></td>
                                                    <td class="muted affair-item-unit-cell"><span class="affair-item-unit-label">—</span></td>
                                                    <td><input class="input affair-item-unit-price" name="item_unit_price[]" type="number" step="0.01" value="0" style="width:100%; max-width:none;"></td>
                                                    <td><input class="input affair-item-vat-rate" name="item_vat_rate[]" type="number" step="0.01" min="0" max="100" value="<?= htmlspecialchars((string) $vatR, ENT_QUOTES, 'UTF-8') ?>" style="width:100%; max-width:none;"></td>
                                                    <td><input class="input affair-item-est-time" name="item_estimated_time_hours[]" type="number" step="1" min="0" placeholder="h" style="width:100%; max-width:none;"></td>
                                                    <td>
                                                        <label class="checkbox-item" style="padding:0; margin:0; justify-content:center;">
                                                            <input type="checkbox" class="affair-item-save-library" name="item_save_to_library[0]" value="1">
                                                        </label>
                                                    </td>
                                                    <td style="text-align:center;">
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
                                    <div class="affair-field-full affair-quote-totals" style="margin-top:12px; padding:14px 16px; border:1px solid var(--border); border-radius:12px; background:var(--app-bg);">
                                        <div style="display:flex; flex-wrap:wrap; gap:12px 28px; align-items:baseline; font-size:15px;">
                                            <span><strong>Total devis HT</strong> : <span id="affair-total-ht">0,00</span> €</span>
                                            <span><strong>Total devis TTC</strong> : <span id="affair-total-ttc">0,00</span> €</span>
                                            <span class="muted" style="font-size:13px;">TTC = somme par ligne (TVA par ligne). Défaut société : <?= htmlspecialchars((string) $vatR, ENT_QUOTES, 'UTF-8') ?> %.</span>
                                        </div>
                                    </div>
                                    <div class="field affair-field-full" style="margin-top:12px;">
                                        <label class="checkbox-item" style="padding:0; margin:0;">
                                            <input type="checkbox" id="send_quote_email" name="send_quote_email" value="1" checked>
                                            <span>Envoyer le devis par email</span>
                                        </label>
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
                                        'unitLabel' => isset($it['unitLabel']) && (string) $it['unitLabel'] !== '' ? (string) $it['unitLabel'] : null,
                                        'unitPrice' => (float) ($it['unitPrice'] ?? 0),
                                        'estimatedTimeMinutes' => $it['estimatedTimeMinutes'] ?? null,
                                        'defaultVatRate' => isset($it['defaultVatRate']) && is_numeric($it['defaultVatRate']) ? (float) $it['defaultVatRate'] : null,
                                        'defaultRevenueAccount' => isset($it['defaultRevenueAccount']) && (string) $it['defaultRevenueAccount'] !== '' ? (string) $it['defaultRevenueAccount'] : null,
                                    ];
                                }, ($priceItems ?? [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>;
                            var vatRate = <?= json_encode(isset($quoteVatRate) && is_numeric($quoteVatRate) ? (float) $quoteVatRate : 20.0) ?>;

                            function normalize(s) { return (s || '').toString().trim().toLowerCase(); }

                            var byName = {};
                            priceCatalog.forEach(function (it) {
                                var nm = normalize(it.name);
                                if (nm && !byName[nm]) byName[nm] = it;
                            });

                            function minutesToHoursStr(m) {
                                if (m === null || m === undefined || m === '') return '';
                                var h = Number(m) / 60;
                                if (isNaN(h)) return '';
                                return String(Math.round(h));
                            }

                            function formatMoney(n) {
                                var x = Math.round(Number(n) * 100) / 100;
                                if (isNaN(x)) x = 0;
                                return x.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }

                            function recalcTotals() {
                                var htEl = document.getElementById('affair-total-ht');
                                var ttcEl = document.getElementById('affair-total-ttc');
                                if (!htEl || !ttcEl) return;
                                var rows = body.querySelectorAll('.affair-quote-item-row');
                                var ht = 0;
                                var ttc = 0;
                                rows.forEach(function (row) {
                                    var qIn = row.querySelector('.affair-item-quantity');
                                    var pIn = row.querySelector('.affair-item-unit-price');
                                    var vatIn = row.querySelector('.affair-item-vat-rate');
                                    var q = parseFloat(qIn && qIn.value ? qIn.value : '0');
                                    var p = parseFloat(pIn && pIn.value ? pIn.value : '0');
                                    if (isNaN(q) || isNaN(p)) return;
                                    var lineHt = q * p;
                                    ht += lineHt;
                                    var vr = parseFloat(vatIn && vatIn.value !== '' ? vatIn.value : String(vatRate));
                                    if (isNaN(vr)) vr = Number(vatRate) || 0;
                                    ttc += lineHt * (1 + vr / 100);
                                });
                                htEl.textContent = formatMoney(ht);
                                ttcEl.textContent = formatMoney(ttc);
                            }

                            function setRowUnitLabel(row, label) {
                                var el = row.querySelector('.affair-item-unit-label');
                                if (!el) return;
                                var t = (label !== null && label !== undefined && String(label).trim() !== '') ? String(label).trim() : '—';
                                el.textContent = t;
                            }

                            function updateUnitLabelFromName(row) {
                                var nameInput = row.querySelector('.affair-item-name-input');
                                if (!nameInput) return;
                                var match = byName[normalize(nameInput.value || '')] || null;
                                setRowUnitLabel(row, match ? match.unitLabel : null);
                            }

                            function syncRowFromName(row) {
                                var nameInput = row.querySelector('.affair-item-name-input');
                                var hiddenId = row.querySelector('.affair-item-price-item-id');
                                var unitPriceInput = row.querySelector('.affair-item-unit-price');
                                var timeInput = row.querySelector('.affair-item-est-time');
                                var vatIn = row.querySelector('.affair-item-vat-rate');
                                var accIn = row.querySelector('.affair-item-revenue-hidden');
                                var saveCb = row.querySelector('.affair-item-save-library');
                                if (!nameInput || !hiddenId) return;

                                var match = byName[normalize(nameInput.value || '')] || null;
                                if (match) {
                                    hiddenId.value = String(match.id);
                                    unitPriceInput.value = String(match.unitPrice);
                                    if (match.estimatedTimeMinutes !== null && match.estimatedTimeMinutes !== undefined) {
                                        timeInput.value = minutesToHoursStr(match.estimatedTimeMinutes);
                                    } else {
                                        timeInput.value = '';
                                    }
                                    if (vatIn) {
                                        if (match.defaultVatRate !== null && match.defaultVatRate !== undefined && !isNaN(Number(match.defaultVatRate))) {
                                            vatIn.value = String(match.defaultVatRate);
                                        } else {
                                            vatIn.value = String(vatRate);
                                        }
                                    }
                                    if (accIn) {
                                        accIn.value = (match.defaultRevenueAccount !== null && match.defaultRevenueAccount !== undefined) ? String(match.defaultRevenueAccount) : '';
                                    }
                                    setRowUnitLabel(row, match.unitLabel);
                                    if (saveCb) saveCb.checked = false;
                                } else {
                                    hiddenId.value = '';
                                    if (vatIn) vatIn.value = String(vatRate);
                                    if (accIn) accIn.value = '';
                                    setRowUnitLabel(row, null);
                                }
                                recalcTotals();
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
                                    nameInput.addEventListener('input', function () { updateUnitLabelFromName(row); });
                                }
                                if (removeBtn) {
                                    removeBtn.addEventListener('click', function () {
                                        if (body.children.length <= 1) return;
                                        row.remove();
                                        reindexRows();
                                        recalcTotals();
                                    });
                                }
                                ['.affair-item-quantity', '.affair-item-unit-price', '.affair-item-vat-rate'].forEach(function (sel) {
                                    var el = row.querySelector(sel);
                                    if (el) el.addEventListener('input', recalcTotals);
                                });
                            }

                            btnAdd.addEventListener('click', function () {
                                var first = body.querySelector('.affair-quote-item-row');
                                if (!first) return;
                                var clone = first.cloneNode(true);
                                clone.querySelectorAll('input').forEach(function (input) {
                                    if (input.type === 'checkbox') input.checked = false;
                                    else if (input.classList.contains('affair-item-quantity')) input.value = '1';
                                    else if (input.classList.contains('affair-item-unit-price')) input.value = '0';
                                    else if (input.classList.contains('affair-item-vat-rate')) input.value = String(vatRate);
                                    else if (input.classList.contains('affair-item-revenue-hidden')) input.value = '';
                                    else input.value = '';
                                });
                                var uLab = clone.querySelector('.affair-item-unit-label');
                                if (uLab) uLab.textContent = '—';
                                body.appendChild(clone);
                                reindexRows();
                                bindRow(clone);
                                recalcTotals();
                            });

                            body.querySelectorAll('.affair-quote-item-row').forEach(bindRow);
                            reindexRows();
                            recalcTotals();
                        })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

