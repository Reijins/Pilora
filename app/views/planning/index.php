<?php
declare(strict_types=1);
// Variables: permissionDenied, projectsByDay, weekDays, weekStart, prevWeekStart, nextWeekStart, rangeStart/rangeEnd

use Core\Support\DateFormatter;
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Planning</h2>
            <p class="muted">Vue hebdomadaire (squelette). Période : <?= htmlspecialchars(DateFormatter::frDate(isset($rangeStart) ? (string) $rangeStart : null), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(DateFormatter::frDate(isset($rangeEnd) ? (string) $rangeEnd : null), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>

                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div>
                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:12px; flex-wrap:wrap;">
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/planning?weekStart=' . urlencode((string) ($prevWeekStart ?? '')), ENT_QUOTES, 'UTF-8') ?>">&larr; Semaine précédente</a>
                        <span class="status-pill">Semaine du <?= htmlspecialchars(DateFormatter::frDate((string) ($weekStart ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/planning?weekStart=' . urlencode((string) ($nextWeekStart ?? '')), ENT_QUOTES, 'UTF-8') ?>">Semaine suivante &rarr;</a>
                    </div>
                    <div class="settings-grid planning-week-grid" style="display:grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap:10px; width:100%; max-width:100%; box-sizing:border-box; overflow-x:hidden;">
                        <?php foreach (($weekDays ?? []) as $dayYmd): ?>
                            <div class="section-card" style="min-width:0; max-width:100%; overflow:hidden;">
                                <h3 class="section-title" style="margin-bottom:8px;"><?= htmlspecialchars(DateFormatter::frDate((string) $dayYmd), ENT_QUOTES, 'UTF-8') ?></h3>
                                <div class="section-content" style="display:flex; flex-direction:column; gap:8px;">
                                    <?php $rows = is_array($projectsByDay[$dayYmd] ?? null) ? $projectsByDay[$dayYmd] : []; ?>
                                    <?php if (!empty($rows)): ?>
                                        <?php foreach ($rows as $r): ?>
                                            <a href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($r['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" style="display:block; border:1px solid #dbe7ef; border-radius:10px; padding:8px; background:#fff; word-break:break-word; text-decoration:none; color:inherit;">
                                                <div style="font-weight:700; margin-bottom:4px;"><?= htmlspecialchars((string) ($r['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="muted" style="font-size:12px;">Client: <?= htmlspecialchars((string) ($r['clientName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="muted" style="font-size:12px;">Équipe: <?= htmlspecialchars((string) (($r['teamMembers'] ?? '') !== '' ? $r['teamMembers'] : 'Aucune équipe affectée'), ENT_QUOTES, 'UTF-8') ?></div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="muted">Aucun chantier.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

