<?php
declare(strict_types=1);

use Core\Support\DateFormatter;

/** @var array<string, string> $tabs */
/** @var array<int, array<string, mixed>> $projects */

$isAffaireActiveForCancel = static function (array $p): bool {
    $statusCode = (string) ($p['status'] ?? '');
    $notesRaw = (string) ($p['notes'] ?? '');
    if ($statusCode === 'completed') {
        return false;
    }
    if (str_contains($notesRaw, '[STATUS:CANCELLED]') || str_contains($notesRaw, '[STATUS:REFUSED_CLIENT]')) {
        return false;
    }

    return true;
};
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Affaires</h2>
            <p class="muted">Liste des affaires (chantiers). Filtrez par statut via les onglets.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <?php $currentTab = isset($currentTab) && is_string($currentTab) ? $currentTab : 'all'; ?>
                <?php $tabs = isset($tabs) && is_array($tabs) ? $tabs : []; ?>

                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; align-items:center;">
                    <?php foreach ($tabs as $code => $label): ?>
                        <a
                            class="btn <?= $currentTab === (string) $code ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= htmlspecialchars($basePath . '/projects?tab=' . urlencode((string) $code), ENT_QUOTES, 'UTF-8') ?>"
                        ><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endforeach; ?>
                    <?php if (!empty($canCreateProject)): ?>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/new', ENT_QUOTES, 'UTF-8') ?>" style="margin-left:auto;">Nouvelle affaire</a>
                    <?php endif; ?>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Affaire</th>
                                <th>Client</th>
                                <th>Statut</th>
                                <th>Début prévu</th>
                                <th>Fin prévue</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($projects)): ?>
                                <?php foreach ($projects as $p): ?>
                                    <?php
                                        $pid = (int) ($p['id'] ?? 0);
                                        $clientId = (int) ($p['clientId'] ?? 0);
                                        $notesRaw = (string) ($p['notes'] ?? '');
                                        $statusCode = (string) ($p['status'] ?? '');
                                        $statusLabel = $statusCode !== '' ? $statusCode : '—';
                                        if (str_contains($notesRaw, '[STATUS:WAITING_PLANNING]')) {
                                            $statusLabel = 'En attente de planification';
                                        } elseif (str_contains($notesRaw, '[STATUS:PLANNED]')) {
                                            $statusLabel = 'Planifié';
                                        } elseif (str_contains($notesRaw, '[STATUS:CANCELLED]')) {
                                            $statusLabel = 'Annulé';
                                        } elseif (str_contains($notesRaw, '[STATUS:REFUSED_CLIENT]')) {
                                            $statusLabel = 'Refus client';
                                        } elseif ($statusCode === 'planned') {
                                            $statusLabel = 'Prévu';
                                        } elseif ($statusCode === 'in_progress') {
                                            $statusLabel = 'En cours';
                                        } elseif ($statusCode === 'paused') {
                                            $statusLabel = 'En pause';
                                        } elseif ($statusCode === 'completed') {
                                            $statusLabel = 'Terminé';
                                        }
                                        $statusChipClass = 'chip chip-affaire chip-affaire--neutral';
                                        if (str_contains($notesRaw, '[STATUS:WAITING_PLANNING]')) {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--waiting';
                                        } elseif (str_contains($notesRaw, '[STATUS:PLANNED]')) {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--confirmed';
                                        } elseif (str_contains($notesRaw, '[STATUS:CANCELLED]')) {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--cancelled';
                                        } elseif (str_contains($notesRaw, '[STATUS:REFUSED_CLIENT]')) {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--refused-client';
                                        } elseif ($statusCode === 'planned') {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--preve';
                                        } elseif ($statusCode === 'in_progress') {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--progress';
                                        } elseif ($statusCode === 'paused') {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--paused';
                                        } elseif ($statusCode === 'completed') {
                                            $statusChipClass = 'chip chip-affaire chip-affaire--completed';
                                        }
                                        $showCancelBtn = !empty($canUpdateProject) && $isAffaireActiveForCancel($p);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($p['name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($p['clientName'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="<?= htmlspecialchars($statusChipClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDate(isset($p['plannedStartDate']) ? (string) $p['plannedStartDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDate(isset($p['plannedEndDate']) ? (string) $p['plannedEndDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($pid > 0): ?>
                                                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                                    <a class="link-action" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . $pid, ENT_QUOTES, 'UTF-8') ?>">Ouvrir</a>
                                                    <?php if ($showCancelBtn): ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-danger btn-icon open-status-modal"
                                                            data-project-id="<?= $pid ?>"
                                                            data-client-id="<?= $clientId ?>"
                                                            data-project-name="<?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            aria-label="Annuler ou refus client"
                                                        >
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="muted">Aucune affaire dans cette vue.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                                <input type="hidden" name="client_id" id="status-modal-client-id" value="">
                                <input type="hidden" name="return_to" value="projects">
                                <input type="hidden" name="return_tab" value="<?= htmlspecialchars($currentTab, ENT_QUOTES, 'UTF-8') ?>">

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
                            var clientIdInput = document.getElementById('status-modal-client-id');
                            var reasonInput = document.getElementById('status-modal-reason');
                            if (!overlay || !projectIdInput || !reasonInput || !clientIdInput) return;

                            function openModal(projectId, projectName, clientId) {
                                projectIdInput.value = String(projectId || 0);
                                clientIdInput.value = String(clientId || 0);
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
                                    openModal(
                                        btn.getAttribute('data-project-id'),
                                        btn.getAttribute('data-project-name'),
                                        btn.getAttribute('data-client-id')
                                    );
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
                        })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
