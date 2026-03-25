<?php
declare(strict_types=1);

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$wh = is_numeric($workHoursPerDay ?? null) ? (float) $workHoursPerDay : 8.0;
$project = is_array($project ?? null) ? $project : [];
$pid = (int) ($project['id'] ?? 0);
$mat = isset($project['coutMateriauxTotal']) && is_numeric($project['coutMateriauxTotal']) ? (float) $project['coutMateriauxTotal'] : 0.0;
$mont = isset($project['montantFactureHt']) && is_numeric($project['montantFactureHt']) ? (float) $project['montantFactureHt'] : null;
$mLabel = $mont !== null ? number_format($mont, 2, ',', ' ') : '';

$today = (new \DateTimeImmutable('today'))->format('Y-m-d');
?>
<section class="page">
    <div class="card">
        <div class="card-header sheet-header">
            <div>
                <h2 class="sheet-title">Rentabilité</h2>
                <p class="muted" style="margin:4px 0 0;"><?= htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($project['clientName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/rentability', ENT_QUOTES, 'UTF-8') ?>">Retour</a>
        </div>
        <div class="card-body">
            <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php     
            if (empty($canSave)): ?>
                <div class="alert alert-danger">Vous ne pouvez pas modifier cette fiche.</div>
            <?php else: ?>
                <form
                    id="rent-form"
                    class="form"
                    method="post"
                    action="<?= htmlspecialchars($basePath . '/projects/rentability/save', ENT_QUOTES, 'UTF-8') ?>"
                    data-work-hours="<?= htmlspecialchars((string) $wh, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="project_id" value="<?= $pid ?>">

                    <div class="contact-form-grid" style="margin-bottom:16px;">
                        <div class="field">
                            <label class="label" for="montant_facture_ht">Montant facturé HT (€)</label>
                            <input class="input" id="montant_facture_ht" name="montant_facture_ht" type="text" inputmode="decimal" required value="<?= htmlspecialchars($mLabel, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="field">
                            <label class="label" for="cout_materiaux_total">Coût matériaux total (€)</label>
                            <input class="input" id="cout_materiaux_total" name="cout_materiaux_total" type="text" inputmode="decimal" value="<?= htmlspecialchars(number_format($mat, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <p class="muted" style="margin:0 0 12px; font-size:0.92rem;">
                        Base : <strong><?= htmlspecialchars(str_replace('.', ',', (string) $wh), ENT_QUOTES, 'UTF-8') ?> h</strong> par jour ouvré (réglage entreprise).
                        Coût main-d’œuvre calculé : <strong><?= htmlspecialchars(number_format((float) ($laborCost ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</strong>.
                    </p>

                    <h3 class="section-title" style="margin:16px 0 8px; font-size:1.05rem;">Temps passé</h3>
                    <p class="muted" style="margin:0 0 10px; font-size:0.9rem;">Saisissez soit des <strong>jours</strong> (ex. 2,5), soit des <strong>heures et minutes</strong> — les champs se mettent à jour automatiquement.</p>

                    <div class="table-wrap" style="margin-bottom:12px;">
                        <table class="table" id="time-table">
                            <thead>
                                <tr>
                                    <th>Personne</th>
                                    <th>Date</th>
                                    <th>Jours</th>
                                    <th>Heures</th>
                                    <th>Min</th>
                                </tr>
                            </thead>
                            <tbody id="time-tbody">
                                <?php
                                $entriesList = $timeEntries ?? [];
                                if ($entriesList === []) {
                                    $entriesList = [['durationMinutes' => 0, 'userId' => 0, 'assignmentDate' => $today]];
                                }
                                ?>
                                <?php foreach ($entriesList as $e): ?>
                                    <?php
                                        $m = (int) ($e['durationMinutes'] ?? 0);
                                        $daysStr = $wh > 0 && $m > 0 ? (string) round($m / ($wh * 60), 4) : '';
                                        $dh = intdiv(max(0, $m), 60);
                                        $dm = max(0, $m) % 60;
                                        $selUid = (int) ($e['userId'] ?? 0);
                                    ?>
                                    <tr class="js-time-row">
                                        <td>
                                            <select class="input js-user" name="te_user_id[]" required style="min-width:160px;">
                                                <?php $optFirst = true; foreach (($companyUsers ?? []) as $u): ?>
                                                    <?php $oid = (int) ($u['id'] ?? 0); ?>
                                                    <option value="<?= $oid ?>" <?=
                                                        ($selUid > 0 ? $oid === $selUid : $optFirst) ? 'selected' : ''
                                                    ?>>
                                                        <?= htmlspecialchars(trim((string) (($u['fullName'] ?? '') !== '' ? $u['fullName'] : ($u['email'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php $optFirst = false; endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input class="input js-date" type="date" name="te_date[]" required value="<?= htmlspecialchars((string) ($e['assignmentDate'] ?? $today), ENT_QUOTES, 'UTF-8') ?>"></td>
                                        <td><input class="input js-days" type="text" inputmode="decimal" name="te_days[]" value="<?= htmlspecialchars($daysStr !== '' ? str_replace('.', ',', $daysStr) : '', ENT_QUOTES, 'UTF-8') ?>" placeholder="ex. 1,5"></td>
                                        <td><input class="input js-hours" type="number" min="0" step="1" name="te_hours[]" value="<?= $dh ?>"></td>
                                        <td><input class="input js-minutes" type="number" min="0" max="59" step="1" name="te_minutes[]" value="<?= $dm ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-secondary" id="btn-add-time-row">Ajouter une ligne</button>

                    <template id="time-row-template">
                        <tr class="js-time-row">
                            <td>
                                <select class="input js-user" name="te_user_id[]" required style="min-width:160px;">
                                    <?php foreach (($companyUsers ?? []) as $u): ?>
                                        <?php $oid = (int) ($u['id'] ?? 0); ?>
                                        <option value="<?= $oid ?>"><?= htmlspecialchars(trim((string) (($u['fullName'] ?? '') !== '' ? $u['fullName'] : ($u['email'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="input js-date" type="date" name="te_date[]" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"></td>
                            <td><input class="input js-days" type="text" inputmode="decimal" name="te_days[]" value="" placeholder="ex. 1,5"></td>
                            <td><input class="input js-hours" type="number" min="0" step="1" name="te_hours[]" value="0"></td>
                            <td><input class="input js-minutes" type="number" min="0" max="59" step="1" name="te_minutes[]" value="0"></td>
                        </tr>
                    </template>

                    <div class="card-inner-subtle" style="margin-top:20px; padding:12px; border-radius:12px;">
                        <p style="margin:0 0 4px;"><span class="muted">Bénéfice estimé (après enregistrement des temps ci-dessus) :</span> <strong><?= htmlspecialchars(number_format((float) ($previewBenefice ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</strong></p>
                        <p style="margin:0;"><span class="muted">Marge :</span> <strong><?= isset($previewMarge) && $previewMarge !== null ? htmlspecialchars(number_format((float) $previewMarge, 2, ',', ' '), ENT_QUOTES, 'UTF-8') . ' %' : '—' ?></strong></p>
                    </div>

                    <div style="margin-top:16px;">
                        <button class="btn btn-primary" type="submit">Enregistrer la rentabilité</button>
                    </div>
                </form>

                <script>
                (function () {
                    var form = document.getElementById('rent-form');
                    if (!form) return;
                    function workHours() {
                        var v = parseFloat(form.getAttribute('data-work-hours') || '8');
                        return v > 0 ? v : 8;
                    }
                    function parseDec(s) {
                        if (!s) return 0;
                        return parseFloat(String(s).replace(',', '.')) || 0;
                    }
                    function rowFromTarget(el) {
                        return el && el.closest ? el.closest('tr.js-time-row') : null;
                    }
                    function syncFromDays(row) {
                        var d = parseDec(row.querySelector('.js-days').value);
                        var wh = workHours();
                        var mins = Math.round(d * wh * 60);
                        var h = Math.floor(mins / 60);
                        var mm = mins % 60;
                        row.querySelector('.js-hours').value = String(h);
                        row.querySelector('.js-minutes').value = String(mm);
                    }
                    function syncFromHm(row) {
                        var h = parseInt(row.querySelector('.js-hours').value, 10) || 0;
                        var mm = parseInt(row.querySelector('.js-minutes').value, 10) || 0;
                        if (mm > 59) {
                            h += Math.floor(mm / 60);
                            mm = mm % 60;
                            row.querySelector('.js-hours').value = String(h);
                            row.querySelector('.js-minutes').value = String(mm);
                        }
                        var mins = h * 60 + mm;
                        var wh = workHours();
                        var days = wh > 0 ? mins / (wh * 60) : 0;
                        var inp = row.querySelector('.js-days');
                        inp.value = days > 0 ? String(Math.round(days * 1000) / 1000).replace('.', ',') : '';
                    }
                    function bindRow(row) {
                        var dEl = row.querySelector('.js-days');
                        var hEl = row.querySelector('.js-hours');
                        var mEl = row.querySelector('.js-minutes');
                        if (dEl) dEl.addEventListener('input', function () { syncFromDays(row); });
                        if (hEl) hEl.addEventListener('input', function () { syncFromHm(row); });
                        if (mEl) mEl.addEventListener('input', function () { syncFromHm(row); });
                    }
                    document.querySelectorAll('tr.js-time-row').forEach(bindRow);
                    var btn = document.getElementById('btn-add-time-row');
                    var tpl = document.getElementById('time-row-template');
                    var tbody = document.getElementById('time-tbody');
                    if (btn && tpl && tbody) {
                        btn.addEventListener('click', function () {
                            var node = tpl.content.cloneNode(true);
                            tbody.appendChild(node);
                            var last = tbody.querySelector('tr.js-time-row:last-of-type');
                            if (last) bindRow(last);
                        });
                    }
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</section>
