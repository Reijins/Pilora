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
                    <h2 class="sheet-title">
                        <?php if (!empty($client['clientNumber'])): ?><code class="sheet-number"><?= htmlspecialchars((string) $client['clientNumber'], ENT_QUOTES, 'UTF-8') ?></code><?php endif; ?>
                        <?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <div class="sheet-meta">
                        <?php
                            $primaryContact = null;
                            foreach (($contacts ?? []) as $_c) {
                                if (!empty($_c['isPrimaryContact'])) { $primaryContact = $_c; break; }
                            }
                            $displayPhone = $primaryContact['phone'] ?? $client['phone'] ?? '';
                            $displayEmail = $primaryContact['email'] ?? $client['email'] ?? '';
                        ?>
                        <?php if ($displayPhone !== ''): ?>
                            <span>Téléphone : <?= htmlspecialchars((string) $displayPhone, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($displayEmail !== ''): ?>
                            <span>Email : <?= htmlspecialchars((string) $displayEmail, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <span class="chip chip-primary">Fiche client</span>
                        <?php if (isset($client['isBillable']) && (int) $client['isBillable'] === 0): ?>
                            <span class="chip chip-warning">Non facturable</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sheet-header-actions">
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients/edit?clientId=' . (int) ($client['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Modifier le compte</a>

                    <?php if ((int) ($client['isBillable'] ?? 1) === 1): ?>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/clients/toggle-billable', ENT_QUOTES, 'UTF-8') ?>" style="margin:0;" data-confirm="Ce compte sera déclaré non facturable. Aucune nouvelle affaire ne pourra être créée." data-confirm-title="Déclarer non facturable" data-confirm-variant="warning" data-confirm-btn="Confirmer">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">
                            <input type="hidden" name="billable" value="0">
                            <button class="btn btn-warning" type="submit">Non facturable</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/clients/toggle-billable', ENT_QUOTES, 'UTF-8') ?>" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">
                            <input type="hidden" name="billable" value="1">
                            <button class="btn btn-success" type="submit">Repasser facturable</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="<?= htmlspecialchars($basePath . '/clients/delete', ENT_QUOTES, 'UTF-8') ?>" style="margin:0;" data-confirm="Supprimer définitivement ce compte client ? Cette action est irréversible." data-confirm-title="Supprimer le compte" data-confirm-variant="danger" data-confirm-btn="Supprimer">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">
                        <button class="btn btn-danger" type="submit">Supprimer</button>
                    </form>

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

                <!-- Tabs principaux -->
                <div class="client-tabs" role="tablist">
                    <button type="button" class="client-tabs__btn is-active" data-client-tab="tab-affaires">Affaires</button>
                    <button type="button" class="client-tabs__btn" data-client-tab="tab-contacts">Contacts</button>
                    <button type="button" class="client-tabs__btn" data-client-tab="tab-factures">Factures</button>
                </div>

                <!-- Tab Affaires -->
                <div id="tab-affaires" class="client-tab-panel is-active">
                    <div class="client-tab-panel__header">
                        <?php if (!empty($canCreateProject) && ((int) ($client['isBillable'] ?? 1)) === 1): ?>
                            <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/projects/new?clientId=' . (int) ($client['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Créer une affaire</a>
                        <?php endif; ?>
                    </div>

                    <div class="affaires-tabs" role="tablist" aria-label="Affaires">
                        <button type="button" class="btn btn-secondary affaires-tab is-active" data-tab-target="affaires-actives">En cours</button>
                        <button type="button" class="btn btn-secondary affaires-tab" data-tab-target="affaires-historique">Historique</button>
                    </div>

                    <div id="affaires-actives" class="affaires-tab-panel is-active">
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>N°</th>
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
                                            <td><code><?= htmlspecialchars((string) ($a['projectNumber'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
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
                                        <td colspan="10" class="muted">Aucune affaire en cours.</td>
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
                                    <th>N°</th>
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
                                            <td><code><?= htmlspecialchars((string) ($a['projectNumber'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
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
                                        <td colspan="10" class="muted">Aucune affaire dans l'historique.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab Contacts -->
                <div id="tab-contacts" class="client-tab-panel">
                    <div class="client-tab-panel__header">
                        <?php if (!empty($canCreateContact)): ?>
                            <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/contacts/new?clientId=' . (int) ($client['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Ajouter un contact</a>
                        <?php endif; ?>
                    </div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Fonction</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Principal</th>
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
                                            <?php if (!empty($c['isPrimaryContact'])): ?>
                                                <span class="badge badge-success">Principal</span>
                                            <?php else: ?>
                                                <form method="POST" action="<?= htmlspecialchars($basePath . '/contacts/set-primary', ENT_QUOTES, 'UTF-8') ?>" style="margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="contact_id" value="<?= (int) ($c['id'] ?? 0) ?>">
                                                    <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">
                                                    <button class="btn btn-secondary btn-sm" type="submit">Définir principal</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="inline-actions">
                                                <a class="btn btn-secondary btn-icon" href="<?= htmlspecialchars($basePath . '/contacts/edit?contactId=' . (int) ($c['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" aria-label="Modifier">
                                                    <span aria-hidden="true">&#9998;</span>
                                                </a>
                                                <form method="POST" action="<?= htmlspecialchars($basePath . '/contacts/delete', ENT_QUOTES, 'UTF-8') ?>" data-confirm="Supprimer ce contact ?" data-confirm-title="Supprimer le contact" data-confirm-variant="danger" data-confirm-btn="Supprimer">
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
                                    <td colspan="6" class="muted">Aucun contact.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Factures -->
                <div id="tab-factures" class="client-tab-panel">
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
                <?php endif; ?>

                <script>
                    (function () {
                        // Tabs principaux
                        var clientTabBtns = document.querySelectorAll('.client-tabs__btn');
                        var clientPanels = document.querySelectorAll('.client-tab-panel');
                        clientTabBtns.forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var target = btn.getAttribute('data-client-tab') || '';
                                clientTabBtns.forEach(function (b) { b.classList.remove('is-active'); });
                                clientPanels.forEach(function (p) { p.classList.remove('is-active'); });
                                btn.classList.add('is-active');
                                var panel = document.getElementById(target);
                                if (panel) panel.classList.add('is-active');
                            });
                        });

                        // Sous-tabs affaires (en cours / historique)
                        var affaireTabs = document.querySelectorAll('.affaires-tab');
                        var affairePanels = document.querySelectorAll('.affaires-tab-panel');
                        affaireTabs.forEach(function (tabBtn) {
                            tabBtn.addEventListener('click', function () {
                                var target = tabBtn.getAttribute('data-tab-target') || '';
                                affaireTabs.forEach(function (b) { b.classList.remove('is-active'); });
                                affairePanels.forEach(function (p) { p.classList.remove('is-active'); });
                                tabBtn.classList.add('is-active');
                                var panel = document.getElementById(target);
                                if (panel) panel.classList.add('is-active');
                            });
                        });

                        // Modal statut affaire
                        var overlay = document.getElementById('status-modal-overlay');
                        var closeBtn = document.getElementById('status-modal-close');
                        var subtitle = document.getElementById('status-modal-subtitle');
                        var projectIdInput = document.getElementById('status-modal-project-id');
                        var reasonInput = document.getElementById('status-modal-reason');

                        function openStatusModal(projectId, projectName) {
                            if (!overlay || !projectIdInput || !reasonInput) return;
                            projectIdInput.value = String(projectId || 0);
                            if (subtitle) subtitle.textContent = projectName ? ('Affaire: ' + projectName) : '';
                            reasonInput.value = '';
                            overlay.style.display = 'flex';
                            reasonInput.focus();
                        }
                        function closeStatusModal() {
                            if (overlay) overlay.style.display = 'none';
                        }

                        document.querySelectorAll('.open-status-modal').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                openStatusModal(btn.getAttribute('data-project-id'), btn.getAttribute('data-project-name'));
                            });
                        });
                        if (closeBtn) closeBtn.addEventListener('click', closeStatusModal);
                        if (overlay) {
                            overlay.addEventListener('click', function (e) {
                                if (e.target === overlay) closeStatusModal();
                            });
                        }
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape' && overlay && overlay.style.display !== 'none') closeStatusModal();
                        });
                    })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</section>
