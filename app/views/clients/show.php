<?php
declare(strict_types=1);
use Core\Support\DateFormatter;
// Variables: $permissionDenied, $client, $projects, $affaires, $contacts, $quotes, $invoices, $canViewProjects, $canReportRead, $canPhotoRead, $canViewQuotes, $canViewInvoices, $canCreateInvoice, $canMarkPaid, $canCreateQuote, $canCreateProject, $canUpdateProject, $canCreateContact, $csrfToken, $flashMessage, $flashError
?>
<section class="page">
    <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
    <div class="card">
        <div class="card-header sheet-header">
            <div class="sheet-headline">
                <div>
                    <h2 class="sheet-title"><?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="sheet-meta">
                        <?php if (!empty($client['phone'])): ?>
                            <span>Téléphone : <?= htmlspecialchars((string) $client['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($client['email'])): ?>
                            <span>Email : <?= htmlspecialchars((string) $client['email'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <span class="chip chip-primary">Fiche client</span>
                    </div>
                </div>
                <div>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>">Retour</a>
                </div>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php if (is_string($flashMessage) && trim($flashMessage) !== ''): ?>
                    <div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (is_string($flashError) && trim($flashError) !== ''): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php
                    $invoiceStatusLabels = [
                        'brouillon' => 'Brouillon',
                        'envoyee' => 'Envoyée',
                        'partiellement_payee' => 'Partiellement payée',
                        'payee' => 'Payée',
                        'echue' => 'Échue',
                        'annulee' => 'Annulée',
                    ];
                    $affaireStatusLabels = [
                        'planned' => 'Prévu',
                        'waiting_planning' => 'En attente de planification',
                        'planned_confirmed' => 'Planifié',
                        'in_progress' => 'En cours',
                        'paused' => 'En pause',
                        'completed' => 'Terminé',
                        'cancelled' => 'Annulé',
                        'refused_client' => 'Refus client',
                    ];

                    $getAffaireDisplayStatus = static function (array $af, array $labels): string {
                        $statusCode = (string) ($af['projectStatus'] ?? '');
                        $notesRaw = (string) ($af['projectNotes'] ?? '');
                        if (str_contains($notesRaw, '[STATUS:CANCELLED]')) {
                            return $labels['cancelled'];
                        }
                        if (str_contains($notesRaw, '[STATUS:REFUSED_CLIENT]')) {
                            return $labels['refused_client'];
                        }
                        if (str_contains($notesRaw, '[STATUS:WAITING_PLANNING]')) {
                            return $labels['waiting_planning'];
                        }
                        if (str_contains($notesRaw, '[STATUS:PLANNED]')) {
                            return $labels['planned_confirmed'];
                        }
                        return (string) ($labels[$statusCode] ?? ($statusCode !== '' ? $statusCode : '—'));
                    };
                    $getAffaireStatusClass = static function (array $af): string {
                        $statusCode = (string) ($af['projectStatus'] ?? '');
                        $notesRaw = (string) ($af['projectNotes'] ?? '');
                        if (str_contains($notesRaw, '[STATUS:CANCELLED]')) {
                            return 'chip chip-affaire chip-affaire--cancelled';
                        }
                        if (str_contains($notesRaw, '[STATUS:REFUSED_CLIENT]')) {
                            return 'chip chip-affaire chip-affaire--refused-client';
                        }
                        if (str_contains($notesRaw, '[STATUS:WAITING_PLANNING]')) {
                            return 'chip chip-affaire chip-affaire--waiting';
                        }
                        if (str_contains($notesRaw, '[STATUS:PLANNED]')) {
                            return 'chip chip-affaire chip-affaire--confirmed';
                        }
                        if ($statusCode === 'planned') {
                            return 'chip chip-affaire chip-affaire--preve';
                        }
                        if ($statusCode === 'in_progress') {
                            return 'chip chip-affaire chip-affaire--progress';
                        }
                        if ($statusCode === 'completed') {
                            return 'chip chip-affaire chip-affaire--completed';
                        }
                        if ($statusCode === 'paused') {
                            return 'chip chip-affaire chip-affaire--paused';
                        }

                        return 'chip chip-affaire chip-affaire--neutral';
                    };
                    $isHistoricalAffaire = static function (array $af): bool {
                        $statusCode = (string) ($af['projectStatus'] ?? '');
                        $notesRaw = (string) ($af['projectNotes'] ?? '');
                        if ($statusCode === 'completed') {
                            return true;
                        }
                        if (str_contains($notesRaw, '[STATUS:CANCELLED]') || str_contains($notesRaw, '[STATUS:REFUSED_CLIENT]')) {
                            return true;
                        }
                        return false;
                    };

                    $activeAffaires = [];
                    $historicalAffaires = [];
                    foreach (($affaires ?? []) as $af) {
                        if ($isHistoricalAffaire($af)) {
                            $historicalAffaires[] = $af;
                        } else {
                            $activeAffaires[] = $af;
                        }
                    }
                ?>

                <div class="inline-actions" style="margin-bottom:14px;">
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients/edit?clientId=' . (int) ($client['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Modifier le compte client</a>
                    <?php if (!empty($canCreateContact)): ?>
                        <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/contacts/new?clientId=' . (int) ($client['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Ajouter un contact</a>
                    <?php endif; ?>
                    <?php if (!empty($canCreateProject)): ?>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/new?clientId=' . (int) ($client['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Créer une affaire</a>
                    <?php endif; ?>
                </div>

                <div class="sheet-stack">
                    <div class="section-card">
                        <h3 class="section-title">Affaires</h3>
                        <div class="section-content">
                            <div class="affaires-tabs" role="tablist" aria-label="Affaires">
                                <button type="button" class="btn btn-secondary affaires-tab is-active" data-tab-target="affaires-actives">Affaires en cours</button>
                                <button type="button" class="btn btn-secondary affaires-tab" data-tab-target="affaires-historique">Historique</button>
                            </div>

                            <div id="affaires-actives" class="affaires-tab-panel is-active">
                                <div class="table-wrap">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th>Affaire / Chantier</th>
                                            <th>Statut</th>
                                            <th>Devis</th>
                                            <th>Factures</th>
                                            <th>Total devis</th>
                                            <th>Total facturé</th>
                                            <th>Encaissé</th>
                                            <th>Reste</th>
                                            <th>Actions</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($activeAffaires)): ?>
                                            <?php foreach ($activeAffaires as $a): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) ($a['projectName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><span class="<?= htmlspecialchars($getAffaireStatusClass($a), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($getAffaireDisplayStatus($a, $affaireStatusLabels), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td><?= (int) ($a['quotesCount'] ?? 0) ?></td>
                                                    <td><?= (int) ($a['invoicesCount'] ?? 0) ?></td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['quoteAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['invoiceAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['paidAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['remainingAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td>
                                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                            <a class="link-action" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($a['projectId'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Fiche</a>

                                                            <?php if (!empty($canUpdateProject)): ?>
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-danger btn-icon open-status-modal"
                                                                    data-project-id="<?= (int) ($a['projectId'] ?? 0) ?>"
                                                                    data-project-name="<?= htmlspecialchars((string) ($a['projectName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                    aria-label="Annuler ou refus client"
                                                                >
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="muted">Aucune affaire en cours.</td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="affaires-historique" class="affaires-tab-panel">
                                <div class="table-wrap">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th>Affaire / Chantier</th>
                                            <th>Statut</th>
                                            <th>Devis</th>
                                            <th>Factures</th>
                                            <th>Total devis</th>
                                            <th>Total facturé</th>
                                            <th>Encaissé</th>
                                            <th>Reste</th>
                                            <th>Actions</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($historicalAffaires)): ?>
                                            <?php foreach ($historicalAffaires as $a): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) ($a['projectName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><span class="<?= htmlspecialchars($getAffaireStatusClass($a), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($getAffaireDisplayStatus($a, $affaireStatusLabels), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td><?= (int) ($a['quotesCount'] ?? 0) ?></td>
                                                    <td><?= (int) ($a['invoicesCount'] ?? 0) ?></td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['quoteAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['invoiceAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['paidAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td><?= htmlspecialchars(number_format((float) ($a['remainingAmount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                    <td>
                                                        <a class="link-action" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($a['projectId'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Fiche</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="muted">Aucune affaire dans l'historique.</td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <h3 class="section-title">Contacts</h3>
                        <div class="section-content">
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Fonction</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($contacts)): ?>
                                    <?php foreach ($contacts as $c): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars(
                                                    trim((string) ($c['firstName'] ?? '') . ' ' . (string) ($c['lastName'] ?? '')),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>
                                            <td><?= htmlspecialchars((string) ($c['functionLabel'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($c['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($c['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <div class="inline-actions">
                                                    <a class="btn btn-secondary btn-icon" href="<?= htmlspecialchars($basePath . '/contacts/edit?contactId=' . (int) ($c['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" aria-label="Modifier">
                                                        <span aria-hidden="true">&#9998;</span>
                                                    </a>
                                                    <form method="POST" action="<?= htmlspecialchars($basePath . '/contacts/delete', ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Supprimer ce contact ?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="contact_id" value="<?= (int) ($c['id'] ?? 0) ?>">
                                                        <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">
                                                        <button class="btn btn-danger btn-icon" type="submit" aria-label="Supprimer"><span aria-hidden="true">&times;</span></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="muted">Aucun contact.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <h3 class="section-title">Factures</h3>
                        <div class="section-content">
                        <?php if (!empty($canViewInvoices)): ?>
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Titre</th>
                                        <th>Échéance</th>
                                        <th>Statut</th>
                                        <th>Reste</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($invoices)): ?>
                                        <?php foreach ($invoices as $inv): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) ($inv['invoiceNumber'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) ($inv['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(DateFormatter::frDate(isset($inv['dueDate']) ? (string) $inv['dueDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><span class="status-pill"><?= htmlspecialchars((string) ($invoiceStatusLabels[$inv['status']] ?? ($inv['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td><?= htmlspecialchars((string) number_format((float) ($inv['amountRemaining'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                <td>
                                                    <?php
                                                        $remaining = (float) ($inv['amountRemaining'] ?? 0);
                                                        $invSt = (string) ($inv['status'] ?? '');
                                                    ?>
                                                    <?php if (!empty($canMarkPaid) && $remaining > 0.0 && $invSt !== 'annulee'): ?>
                                                        <a class="link-action" href="<?= htmlspecialchars($basePath . '/payments/new?invoiceId=' . (int) ($inv['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Payer</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="muted">Aucune facture.</td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">Accès refusé : lecture des factures indisponible.</div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($canUpdateProject)): ?>
                    <div id="status-modal-overlay" class="status-modal-overlay" style="display:none;">
                        <div class="status-modal" role="dialog" aria-modal="true" aria-labelledby="status-modal-title">
                            <div class="status-modal-header">
                                <h4 id="status-modal-title" class="status-modal-title">Mettre à jour l'affaire</h4>
                                <button type="button" class="btn btn-secondary btn-icon" id="status-modal-close" aria-label="Fermer">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <p id="status-modal-subtitle" class="status-modal-subtitle"></p>
                            <form method="POST" action="<?= htmlspecialchars($basePath . '/projects/status/update', ENT_QUOTES, 'UTF-8') ?>" class="status-reason-form status-modal-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="project_id" id="status-modal-project-id" value="">
                                <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">

                                <label class="label" for="status-modal-reason">Raison</label>
                                <textarea
                                    class="input status-reason-input"
                                    id="status-modal-reason"
                                    name="reason"
                                    required
                                    placeholder="Ex: Refus client (délai / conditions / budget)"
                                ></textarea>

                                <div class="status-reason-actions">
                                    <button class="btn btn-danger" type="submit" name="new_status" value="cancelled">Annuler</button>
                                    <button class="btn btn-secondary" type="submit" name="new_status" value="refused_client">Refus client</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                        (function () {
                            var overlay = document.getElementById('status-modal-overlay');
                            var closeBtn = document.getElementById('status-modal-close');
                            var subtitle = document.getElementById('status-modal-subtitle');
                            var projectIdInput = document.getElementById('status-modal-project-id');
                            var reasonInput = document.getElementById('status-modal-reason');
                            if (!overlay || !projectIdInput || !reasonInput) return;

                            function openModal(projectId, projectName) {
                                projectIdInput.value = String(projectId || 0);
                                subtitle.textContent = projectName ? ('Affaire: ' + projectName) : '';
                                reasonInput.value = '';
                                overlay.style.display = 'flex';
                                reasonInput.focus();
                            }

                            function closeModal() {
                                overlay.style.display = 'none';
                            }

                            document.querySelectorAll('.open-status-modal').forEach(function (btn) {
                                btn.addEventListener('click', function () {
                                    openModal(btn.getAttribute('data-project-id'), btn.getAttribute('data-project-name'));
                                });
                            });

                            if (closeBtn) {
                                closeBtn.addEventListener('click', closeModal);
                            }
                            overlay.addEventListener('click', function (e) {
                                if (e.target === overlay) closeModal();
                            });
                            document.addEventListener('keydown', function (e) {
                                if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal();
                            });

                            var tabs = document.querySelectorAll('.affaires-tab');
                            var panels = document.querySelectorAll('.affaires-tab-panel');
                            tabs.forEach(function (tabBtn) {
                                tabBtn.addEventListener('click', function () {
                                    var target = tabBtn.getAttribute('data-tab-target') || '';
                                    tabs.forEach(function (b) { b.classList.remove('is-active'); });
                                    panels.forEach(function (p) { p.classList.remove('is-active'); });
                                    tabBtn.classList.add('is-active');
                                    var panel = document.getElementById(target);
                                    if (panel) panel.classList.add('is-active');
                                });
                            });
                        })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

