<?php
declare(strict_types=1);
// permissionDenied, canCreate, csrfToken, activeItems, inactiveItems, subTab, flashMessage, flashError
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$sub = isset($subTab) && in_array($subTab, ['active', 'inactive'], true) ? $subTab : 'active';
$activeItems = is_array($activeItems ?? null) ? $activeItems : [];
$inactiveItems = is_array($inactiveItems ?? null) ? $inactiveItems : [];
$formatEstimatedHours = static function ($minutes): string {
    if ($minutes === null || $minutes === '') {
        return '—';
    }
    $m = (int) $minutes;
    $h = $m / 60.0;
    $s = rtrim(rtrim(number_format($h, 4, '.', ''), '0'), '.');

    return ($s !== '' ? $s : '0') . ' h';
};
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Bibliothèque de prestations</h2>
            <p class="muted">Catalogue des prestations réutilisables dans les devis.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($canCreate)): ?>
                    <div style="margin-bottom:16px;">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/price-library/new', ENT_QUOTES, 'UTF-8') ?>">Nouvelle prestation</a>
                    </div>
                <?php endif; ?>

                <div class="email-template-subtabs price-library-subtabs" role="tablist" aria-label="Prestations par statut">
                    <a class="btn btn-secondary email-template-subtab <?= $sub === 'active' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $sub === 'active' ? 'true' : 'false' ?>" href="<?= htmlspecialchars($basePath . '/price-library?sub=active', ENT_QUOTES, 'UTF-8') ?>">
                        Actives (<?= count($activeItems) ?>)
                    </a>
                    <a class="btn btn-secondary email-template-subtab <?= $sub === 'inactive' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $sub === 'inactive' ? 'true' : 'false' ?>" href="<?= htmlspecialchars($basePath . '/price-library?sub=inactive', ENT_QUOTES, 'UTF-8') ?>">
                        Inactives (<?= count($inactiveItems) ?>)
                    </a>
                </div>

                <?php if ($sub === 'active'): ?>
                    <div class="price-library-panel" role="tabpanel">
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prix unitaire</th>
                                    <th>Temps estimé (h)</th>
                                    <th>Unité</th>
                                    <?php if (!empty($canCreate)): ?><th style="width:190px;">Actions</th><?php endif; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($activeItems)): ?>
                                    <?php foreach ($activeItems as $it): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($it['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($it['unitPrice'] ?? '0'), ENT_QUOTES, 'UTF-8') ?> €</td>
                                            <td><?= htmlspecialchars($formatEstimatedHours($it['estimatedTimeMinutes'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($it['unitLabel'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <?php if (!empty($canCreate)): ?>
                                                <td>
                                                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                                                        <a
                                                            class="btn btn-secondary btn-sm"
                                                            href="<?= htmlspecialchars($basePath . '/price-library/edit?id=' . (int) ($it['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                                                            aria-label="Modifier la prestation"
                                                            title="Modifier la prestation"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                <path d="M12 20h9"/>
                                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                                            </svg>
                                                        </a>
                                                        <form method="POST" action="<?= htmlspecialchars($basePath . '/price-library/deactivate', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="id" value="<?= (int) ($it['id'] ?? 0) ?>">
                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger btn-sm"
                                                                aria-label="Passer en inactif"
                                                                title="Passer en inactif"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="M4 12h16"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?= !empty($canCreate) ? 5 : 4 ?>" class="muted">Aucune prestation active.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="price-library-panel" role="tabpanel">
                        <p class="muted" style="margin:0 0 12px;">Les lignes inactives ne sont plus proposées dans les devis. Vous pouvez les supprimer définitivement.</p>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prix unitaire</th>
                                    <th>Temps estimé (h)</th>
                                    <th>Unité</th>
                                    <?php if (!empty($canCreate)): ?><th style="width:200px;">Actions</th><?php endif; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($inactiveItems)): ?>
                                    <?php foreach ($inactiveItems as $it): ?>
                                        <?php $iid = (int) ($it['id'] ?? 0); $iname = (string) ($it['name'] ?? ''); ?>
                                        <tr>
                                            <td><?= htmlspecialchars($iname, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($it['unitPrice'] ?? '0'), ENT_QUOTES, 'UTF-8') ?> €</td>
                                            <td><?= htmlspecialchars($formatEstimatedHours($it['estimatedTimeMinutes'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($it['unitLabel'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <?php if (!empty($canCreate)): ?>
                                                <td>
                                                    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                                                        <a
                                                            class="btn btn-secondary btn-sm"
                                                            href="<?= htmlspecialchars($basePath . '/price-library/edit?id=' . $iid, ENT_QUOTES, 'UTF-8') ?>"
                                                            aria-label="Modifier la prestation"
                                                            title="Modifier la prestation"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                <path d="M12 20h9"/>
                                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                                            </svg>
                                                        </a>
                                                        <button
                                                            type="button"
                                                            class="btn btn-danger btn-sm js-price-delete-open"
                                                            data-id="<?= $iid ?>"
                                                            data-name="<?= htmlspecialchars($iname, ENT_QUOTES, 'UTF-8') ?>"
                                                            aria-label="Supprimer la prestation"
                                                            title="Supprimer la prestation"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                <path d="M18 6L6 18"/>
                                                                <path d="M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?= !empty($canCreate) ? 5 : 4 ?>" class="muted">Aucune prestation inactive.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($canCreate) && $sub === 'inactive'): ?>
                    <div id="price-delete-modal" class="status-modal-overlay" style="display:none;" aria-hidden="true">
                        <div class="status-modal price-delete-modal" role="dialog" aria-modal="true" aria-labelledby="price-delete-title">
                            <div class="status-modal-header">
                                <h4 id="price-delete-title" class="status-modal-title">Supprimer définitivement ?</h4>
                                <button type="button" class="btn btn-secondary btn-icon" id="price-delete-close" aria-label="Fermer">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <p class="status-modal-subtitle" id="price-delete-subtitle"></p>
                            <p class="muted" style="margin:0 0 12px;">Cette action est irréversible.</p>
                            <form method="POST" action="<?= htmlspecialchars($basePath . '/price-library/delete', ENT_QUOTES, 'UTF-8') ?>" id="price-delete-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id" id="price-delete-id" value="">
                                <div class="status-reason-actions">
                                    <button type="button" class="btn btn-secondary" id="price-delete-cancel">Annuler</button>
                                    <button type="submit" class="btn btn-danger">Supprimer définitivement</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                    (function () {
                        var overlay = document.getElementById('price-delete-modal');
                        var form = document.getElementById('price-delete-form');
                        var idInput = document.getElementById('price-delete-id');
                        var subtitle = document.getElementById('price-delete-subtitle');
                        var closeBtn = document.getElementById('price-delete-close');
                        var cancelBtn = document.getElementById('price-delete-cancel');
                        if (!overlay || !form || !idInput || !subtitle) return;

                        function openModal(id, name) {
                            idInput.value = String(id || '');
                            subtitle.textContent = name ? ('Prestation : « ' + name + ' »') : '';
                            overlay.style.display = 'flex';
                            overlay.setAttribute('aria-hidden', 'false');
                        }
                        function closeModal() {
                            overlay.style.display = 'none';
                            overlay.setAttribute('aria-hidden', 'true');
                        }

                        document.querySelectorAll('.js-price-delete-open').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                openModal(btn.getAttribute('data-id'), btn.getAttribute('data-name'));
                            });
                        });
                        if (closeBtn) closeBtn.addEventListener('click', closeModal);
                        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
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
