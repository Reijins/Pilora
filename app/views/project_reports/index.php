<?php
declare(strict_types=1);
// Variables:
// permissionDenied, csrfToken, projectId, projectName, reports, canCreate, flashMessage, flashError

use Core\Support\DateFormatter;
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Rapports de chantier</h2>
            <p class="muted">
                Chantier : <?= htmlspecialchars((string) ($projectName ?? ('Chantier #' . (int) ($projectId ?? 0))), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé.</div>
            <?php else: ?>
                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom:14px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                    <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($projectId ?? 0), ENT_QUOTES, 'UTF-8') ?>">Retour à l'affaire</a>
                </div>

                <div style="margin-bottom:14px;">
                    <?php foreach (($reports ?? []) as $r): ?>
                        <div class="card-inner-subtle" style="border:1px solid var(--border); border-radius:14px; padding:12px; background:#fff; margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                <div style="font-weight:800;"><?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="muted" style="margin-top:2px;">
                                    <?= htmlspecialchars(DateFormatter::frDateTime(isset($r['createdAt']) ? (string) $r['createdAt'] : null), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <?php if (!empty($r['content'])): ?>
                                <div style="margin-top:8px; color:var(--main-text);">
                                    <?= nl2br(htmlspecialchars((string) $r['content'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($reports)): ?>
                        <div class="muted">Aucun rapport pour ce chantier.</div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($canCreate)): ?>
                    <h3 style="margin:18px 0 10px;">Ajouter un rapport</h3>
                    <form method="POST" action="<?= htmlspecialchars($basePath . '/project-reports/create', ENT_QUOTES, 'UTF-8') ?>" class="form" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="project_id" value="<?= (int) ($projectId ?? 0) ?>">

                        <label class="label" for="title">Titre</label>
                        <input class="input" id="title" name="title" type="text" required>

                        <label class="label" for="content">Contenu</label>
                        <textarea class="input" id="content" name="content" rows="6" placeholder="Résumé du rapport"></textarea>

                        <button class="btn btn-primary" type="submit">Enregistrer</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

