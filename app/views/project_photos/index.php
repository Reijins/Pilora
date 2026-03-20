<?php
declare(strict_types=1);
// Variables:
// permissionDenied, csrfToken, projectId, projectName, photos, canUpload, flashMessage, flashError

use Core\Support\DateFormatter;
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Photos de chantier</h2>
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

                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>

                <div style="margin-bottom:14px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($projectId ?? 0), ENT_QUOTES, 'UTF-8') ?>">Retour à l'affaire</a>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px;">
                    <?php foreach (($photos ?? []) as $p): ?>
                        <?php $src = $basePath . (string) ($p['filePath'] ?? ''); ?>
                        <div style="border:1px solid var(--border); border-radius:14px; background:#fff; overflow:hidden;">
                            <div style="aspect-ratio: 4 / 3; background:#f3f4f6; display:flex; align-items:center; justify-content:center;">
                                <img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                            <div style="padding:10px 12px;">
                                <div style="font-weight:800; font-size:13px;">
                                    <?= htmlspecialchars((string) ($p['caption'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if (!empty($p['takenAt'])): ?>
                                    <div class="muted" style="font-size:12px; margin-top:4px;">
                                        <?= htmlspecialchars(DateFormatter::frDateTime((string) $p['takenAt']), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($photos)): ?>
                        <div class="muted">Aucune photo pour ce chantier.</div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($canUpload)): ?>
                    <h3 style="margin:18px 0 10px;">Ajouter une photo</h3>
                    <form method="POST" action="<?= htmlspecialchars($basePath . '/project-photos/upload', ENT_QUOTES, 'UTF-8') ?>" class="form" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="project_id" value="<?= (int) ($projectId ?? 0) ?>">

                        <label class="label" for="photo">Photo (JPG/PNG/WebP/GIF, max 5MB)</label>
                        <input class="input" id="photo" name="photo" type="file" accept="image/*" required>

                        <label class="label" for="caption">Légende (optionnel)</label>
                        <input class="input" id="caption" name="caption" type="text">

                        <button class="btn btn-primary" type="submit">Téléverser</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

