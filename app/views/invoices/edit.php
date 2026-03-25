<?php
declare(strict_types=1);

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$invoice = is_array($invoice ?? null) ? $invoice : [];
$items = is_array($items ?? null) ? $items : [];
$invoiceId = (int) ($invoice['id'] ?? 0);
$projectId = (int) ($projectId ?? 0);
$vatR = isset($quoteVatRate) && is_numeric($quoteVatRate) ? (float) $quoteVatRate : 20.0;
$dueRaw = (string) ($invoice['dueDate'] ?? '');
$dueYmd = strlen($dueRaw) >= 10 ? substr($dueRaw, 0, 10) : $dueRaw;
?>
<section class="page">
    <div class="card affair-form-shell">
        <div class="card-header sheet-header">
            <h2 class="sheet-title">Modifier la facture</h2>
            <p class="muted" style="max-width:760px;">
                Modifiez les informations et les lignes tant que la facture est en brouillon ou envoyée (non soldée).
            </p>
        </div>
        <div class="card-body">
            <?php if (is_string($flashMessage ?? null) && trim((string) $flashMessage) !== ''): ?>
                <div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashMessage), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/save-draft', ENT_QUOTES, 'UTF-8') ?>" class="affair-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">

                <div class="affair-grid">
                    <section class="section-card">
                        <h3 class="section-title">En-tête</h3>
                        <div class="section-content affair-fields">
                            <div class="field affair-field-full">
                                <label class="label" for="title">Titre</label>
                                <input class="input" id="title" name="title" type="text" required value="<?= htmlspecialchars((string) ($invoice['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field">
                                <label class="label" for="due_date">Date d’échéance</label>
                                <input class="input" id="due_date" name="due_date" type="date" required value="<?= htmlspecialchars($dueYmd, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field affair-field-full">
                                <label class="label" for="notes">Notes internes</label>
                                <textarea class="input" id="notes" name="notes" style="min-height:80px;"><?= htmlspecialchars((string) ($invoice['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="section-card">
                        <h3 class="section-title">Lignes de facturation</h3>
                        <div class="section-content affair-fields">
                            <?php
                                $datalistOptions = [];
                                foreach (($priceItems ?? []) as $it) {
                                    $nm = trim((string) ($it['name'] ?? ''));
                                    if ($nm !== '') {
                                        $datalistOptions[] = $nm;
                                    }
                                }
                                $datalistOptions = array_values(array_unique($datalistOptions));
                            ?>
                            <datalist id="invoicePriceLibraryNames">
                                <?php foreach ($datalistOptions as $nm): ?>
                                    <option value="<?= htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') ?>"></option>
                                <?php endforeach; ?>
                            </datalist>

                            <div class="affair-field-full">
                                <div class="affair-quote-table-scroll" style="overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%;">
                                    <table class="table" id="invoice-draft-items-table" style="table-layout:auto; min-width:880px; width:100%;">
                                        <thead>
                                        <tr>
                                            <th style="width:30%;">Prestation</th>
                                            <th style="width:7%;">Qté</th>
                                            <th style="width:7%;">Unité</th>
                                            <th style="width:11%;">PU HT (€)</th>
                                            <th style="width:8%;">TVA %</th>
                                            <th style="width:11%;">Cpte vente</th>
                                            <th style="width:4%;"></th>
                                        </tr>
                                        </thead>
                                        <tbody id="invoice-draft-items-body">
                                        <?php
                                            $rows = $items !== [] ? $items : [[
                                                'description' => '',
                                                'quantity' => 1,
                                                'unitPrice' => 0,
                                                'vatRate' => $vatR,
                                                'revenueAccount' => '',
                                                'priceLibraryItemId' => null,
                                                'unitLabel' => null,
                                            ]];
                                        ?>
                                        <?php foreach ($rows as $idx => $row): ?>
                                            <?php
                                                $rowDesc = trim((string) ($row['description'] ?? ''));
                                                $lineVat = isset($row['vatRate']) && is_numeric($row['vatRate']) ? (float) $row['vatRate'] : $vatR;
                                                $lineVatDisp = rtrim(rtrim(number_format($lineVat, 2, '.', ''), '0'), '.');
                                                if ($lineVatDisp === '') {
                                                    $lineVatDisp = '0';
                                                }
                                                $lineAcc = trim((string) ($row['revenueAccount'] ?? ''));
                                                $libId = isset($row['priceLibraryItemId']) && $row['priceLibraryItemId'] !== null && $row['priceLibraryItemId'] !== ''
                                                    ? (int) $row['priceLibraryItemId']
                                                    : 0;
                                                $uLab = trim((string) ($row['unitLabel'] ?? ''));
                                                $rowUnitDisp = $uLab !== '' ? $uLab : '—';
                                            ?>
                                            <tr class="invoice-draft-item-row" data-row-index="<?= (int) $idx ?>">
                                                <td style="min-width:160px;">
                                                    <input class="input invoice-item-name-input" name="item_name[]" type="text" list="invoicePriceLibraryNames" placeholder="Nom de la prestation" required style="width:100%;" value="<?= htmlspecialchars($rowDesc, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" class="invoice-item-price-item-id" name="item_price_item_id[]" value="<?= $libId > 0 ? (string) $libId : '' ?>">
                                                </td>
                                                <td style="white-space:nowrap;"><input class="input invoice-item-quantity" name="item_quantity[]" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string) ($row['quantity'] ?? 1), ENT_QUOTES, 'UTF-8') ?>" style="min-width:4.75rem; width:5.25rem; max-width:none;"></td>
                                                <td class="muted invoice-item-unit-cell"><span class="invoice-item-unit-label"><?= htmlspecialchars($rowUnitDisp, ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td><input class="input invoice-item-unit-price" name="item_unit_price[]" type="number" step="0.01" value="<?= htmlspecialchars((string) ($row['unitPrice'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" style="width:100%; max-width:none;"></td>
                                                <td><input class="input invoice-item-vat-rate" name="item_vat_rate[]" type="number" step="0.01" min="0" max="100" value="<?= htmlspecialchars($lineVatDisp, ENT_QUOTES, 'UTF-8') ?>" style="width:100%; max-width:none;"></td>
                                                <td><input class="input invoice-item-revenue-account" name="item_revenue_account[]" type="text" maxlength="32" placeholder="706…" value="<?= htmlspecialchars($lineAcc, ENT_QUOTES, 'UTF-8') ?>" style="width:100%; max-width:none;"></td>
                                                <td style="text-align:center;">
                                                    <button class="btn btn-danger btn-icon invoice-btn-remove-row" type="button" aria-label="Supprimer la ligne"><span aria-hidden="true">&times;</span></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="affair-field-full">
                                <button class="btn btn-secondary" id="invoice-btn-add-row" type="button">Ajouter une prestation</button>
                            </div>
                            <div class="affair-field-full affair-quote-totals" style="margin-top:12px; padding:14px 16px; border:1px solid var(--border); border-radius:12px; background:var(--app-bg);">
                                <div style="display:flex; flex-wrap:wrap; gap:12px 28px; align-items:baseline; font-size:15px;">
                                    <span><strong>Total HT</strong> : <span id="invoice-total-ht">0,00</span> €</span>
                                    <span><strong>Total TTC</strong> : <span id="invoice-total-ttc">0,00</span> €</span>
                                    <span class="muted" style="font-size:13px;">TTC = somme par ligne. Défaut société : <?= htmlspecialchars((string) $vatR, ENT_QUOTES, 'UTF-8') ?> %.</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="affair-actions">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/invoices/show?invoiceId=' . $invoiceId, ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                    <?php if ($projectId > 0): ?>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . $projectId, ENT_QUOTES, 'UTF-8') ?>">Fiche affaire</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($canDeleteManualDraft)): ?>
                <div class="affair-actions" style="margin-top:12px; padding-top:16px; border-top:1px solid var(--border, #e2e8f0);">
                    <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/delete-manual-draft', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Supprimer définitivement ce brouillon (facture manuelle sans devis) ?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                        <input type="hidden" name="project_id" value="<?= (int) ($projectId ?? 0) ?>">
                        <button class="btn btn-danger" type="submit">Supprimer ce brouillon</button>
                    </form>
                    <span class="muted" style="font-size:13px; margin-left:10px;">Réservé aux factures créées manuellement (sans devis).</span>
                </div>
            <?php endif; ?>

            <script>
                (function () {
                    var body = document.getElementById('invoice-draft-items-body');
                    var btnAdd = document.getElementById('invoice-btn-add-row');
                    if (!body || !btnAdd) return;

                    var priceCatalog = <?= json_encode(array_map(function ($it) {
                        return [
                            'id' => (int) ($it['id'] ?? 0),
                            'name' => (string) ($it['name'] ?? ''),
                            'unitLabel' => isset($it['unitLabel']) && (string) $it['unitLabel'] !== '' ? (string) $it['unitLabel'] : null,
                            'unitPrice' => (float) ($it['unitPrice'] ?? 0),
                            'defaultVatRate' => isset($it['defaultVatRate']) && is_numeric($it['defaultVatRate']) ? (float) $it['defaultVatRate'] : null,
                            'defaultRevenueAccount' => isset($it['defaultRevenueAccount']) && (string) $it['defaultRevenueAccount'] !== '' ? (string) $it['defaultRevenueAccount'] : null,
                            'categoryDefaultVatRate' => isset($it['categoryDefaultVatRate']) && is_numeric($it['categoryDefaultVatRate']) ? (float) $it['categoryDefaultVatRate'] : null,
                            'categoryDefaultRevenueAccount' => isset($it['categoryDefaultRevenueAccount']) && (string) $it['categoryDefaultRevenueAccount'] !== '' ? (string) $it['categoryDefaultRevenueAccount'] : null,
                        ];
                    }, ($priceItems ?? [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                    var vatRate = <?= json_encode($vatR) ?>;

                    function normalize(s) { return (s || '').toString().trim().toLowerCase(); }
                    var byName = {};
                    priceCatalog.forEach(function (it) {
                        var nm = normalize(it.name);
                        if (nm && !byName[nm]) byName[nm] = it;
                    });

                    function formatMoney(n) {
                        var x = Math.round(Number(n) * 100) / 100;
                        if (isNaN(x)) x = 0;
                        return x.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    function setRowUnitLabel(row, label) {
                        var el = row.querySelector('.invoice-item-unit-label');
                        if (!el) return;
                        var t = (label !== null && label !== undefined && String(label).trim() !== '') ? String(label).trim() : '—';
                        el.textContent = t;
                    }

                    function updateUnitLabelFromName(row) {
                        var nameInput = row.querySelector('.invoice-item-name-input');
                        if (!nameInput) return;
                        var match = byName[normalize(nameInput.value || '')] || null;
                        setRowUnitLabel(row, match ? match.unitLabel : null);
                    }

                    function recalcTotals() {
                        var htEl = document.getElementById('invoice-total-ht');
                        var ttcEl = document.getElementById('invoice-total-ttc');
                        if (!htEl || !ttcEl) return;
                        var rows = body.querySelectorAll('.invoice-draft-item-row');
                        var ht = 0;
                        var ttc = 0;
                        rows.forEach(function (row) {
                            var qIn = row.querySelector('.invoice-item-quantity');
                            var pIn = row.querySelector('.invoice-item-unit-price');
                            var vatIn = row.querySelector('.invoice-item-vat-rate');
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

                    function syncRowFromName(row) {
                        var nameInput = row.querySelector('.invoice-item-name-input');
                        var hiddenId = row.querySelector('.invoice-item-price-item-id');
                        var unitPriceInput = row.querySelector('.invoice-item-unit-price');
                        var vatIn = row.querySelector('.invoice-item-vat-rate');
                        var accIn = row.querySelector('.invoice-item-revenue-account');
                        if (!nameInput || !hiddenId) return;

                        var match = byName[normalize(nameInput.value || '')] || null;
                        if (match) {
                            hiddenId.value = String(match.id);
                            unitPriceInput.value = String(match.unitPrice);
                            if (vatIn) {
                                if (match.defaultVatRate !== null && match.defaultVatRate !== undefined && !isNaN(Number(match.defaultVatRate))) {
                                    vatIn.value = String(match.defaultVatRate);
                                } else if (match.categoryDefaultVatRate !== null && match.categoryDefaultVatRate !== undefined && !isNaN(Number(match.categoryDefaultVatRate))) {
                                    vatIn.value = String(match.categoryDefaultVatRate);
                                } else {
                                    vatIn.value = String(vatRate);
                                }
                            }
                            if (accIn) {
                                if (match.defaultRevenueAccount !== null && match.defaultRevenueAccount !== undefined && String(match.defaultRevenueAccount).trim() !== '') {
                                    accIn.value = String(match.defaultRevenueAccount);
                                } else if (match.categoryDefaultRevenueAccount !== null && match.categoryDefaultRevenueAccount !== undefined && String(match.categoryDefaultRevenueAccount).trim() !== '') {
                                    accIn.value = String(match.categoryDefaultRevenueAccount);
                                } else {
                                    accIn.value = '';
                                }
                            }
                            setRowUnitLabel(row, match.unitLabel);
                        } else {
                            hiddenId.value = '';
                            if (vatIn) vatIn.value = String(vatRate);
                            if (accIn) accIn.value = '';
                            setRowUnitLabel(row, null);
                        }
                        recalcTotals();
                    }

                    function bindRow(row) {
                        var nameInput = row.querySelector('.invoice-item-name-input');
                        var removeBtn = row.querySelector('.invoice-btn-remove-row');
                        if (nameInput) {
                            nameInput.addEventListener('change', function () { syncRowFromName(row); });
                            nameInput.addEventListener('blur', function () { syncRowFromName(row); });
                            nameInput.addEventListener('input', function () { updateUnitLabelFromName(row); });
                        }
                        if (removeBtn) {
                            removeBtn.addEventListener('click', function () {
                                if (body.children.length <= 1) return;
                                row.remove();
                                recalcTotals();
                            });
                        }
                        ['.invoice-item-quantity', '.invoice-item-unit-price', '.invoice-item-vat-rate'].forEach(function (sel) {
                            var el = row.querySelector(sel);
                            if (el) el.addEventListener('input', recalcTotals);
                        });
                    }

                    btnAdd.addEventListener('click', function () {
                        var first = body.querySelector('.invoice-draft-item-row');
                        if (!first) return;
                        var clone = first.cloneNode(true);
                        clone.querySelectorAll('input').forEach(function (input) {
                            if (input.classList.contains('invoice-item-quantity')) input.value = '1';
                            else if (input.classList.contains('invoice-item-unit-price')) input.value = '0';
                            else if (input.classList.contains('invoice-item-vat-rate')) input.value = String(vatRate);
                            else if (input.classList.contains('invoice-item-revenue-account')) input.value = '';
                            else input.value = '';
                        });
                        var uLab = clone.querySelector('.invoice-item-unit-label');
                        if (uLab) uLab.textContent = '—';
                        body.appendChild(clone);
                        bindRow(clone);
                        recalcTotals();
                    });

                    body.querySelectorAll('.invoice-draft-item-row').forEach(bindRow);
                    recalcTotals();
                })();
            </script>
        </div>
    </div>
</section>
