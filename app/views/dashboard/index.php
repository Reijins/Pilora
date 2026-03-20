<?php
declare(strict_types=1);
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Bienvenue sur Pilora</h2>
            <p class="muted">
                Indicateurs pour votre entreprise (données temps réel).
            </p>
        </div>
        <div class="card-body">
            <?php $k = is_array($dashboardKpis ?? null) ? $dashboardKpis : []; ?>
            <div class="kpi-grid">
                <div class="kpi kpi-tint-1">
                    <div class="kpi-value"><?= (int) ($k['quotesToFollowUp'] ?? 0) ?></div>
                    <div class="kpi-label">Devis à relancer</div>
                </div>
                <div class="kpi kpi-tint-2">
                    <div class="kpi-value"><?= (int) ($k['overdueInvoices'] ?? 0) ?></div>
                    <div class="kpi-label">Factures impayées (échues)</div>
                </div>
                <div class="kpi kpi-tint-3">
                    <div class="kpi-value"><?= (int) ($k['lateProjects'] ?? 0) ?></div>
                    <div class="kpi-label">Chantiers en retard</div>
                </div>
                <div class="kpi kpi-tint-4">
                    <div class="kpi-value"><?= (int) ($k['missingDocs'] ?? 0) ?></div>
                    <div class="kpi-label">Chantiers sans rapport (14 j.)</div>
                </div>
            </div>
        </div>
    </div>
</section>

