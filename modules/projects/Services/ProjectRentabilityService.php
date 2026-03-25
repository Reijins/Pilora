<?php
declare(strict_types=1);

namespace Modules\Projects\Services;

use Modules\Projects\Repositories\ProjectTimeEntryRepository;

/**
 * Calcul centralisé bénéfice / marge chantier (hors persistance).
 */
final class ProjectRentabilityService
{
    public function __construct(
        private readonly ProjectTimeEntryRepository $timeEntryRepository = new ProjectTimeEntryRepository(),
    ) {
    }

    public function laborCostAmount(int $companyId, int $projectId): float
    {
        return $this->timeEntryRepository->sumLaborCostAmount($companyId, $projectId);
    }

    /**
     * @return array{
     *   coutMainOeuvre: float,
     *   coutTotal: float,
     *   beneficeTotal: float,
     *   margePercent: ?float
     * }
     */
    public function computeTotals(?float $montantFactureHt, float $coutMateriaux, float $coutMainOeuvre): array
    {
        $montant = $montantFactureHt;
        if ($montant === null) {
            $montant = 0.0;
        }
        $coutMateriaux = round(max(0.0, $coutMateriaux), 2);
        $coutMainOeuvre = round(max(0.0, $coutMainOeuvre), 2);
        $coutTotal = round($coutMateriaux + $coutMainOeuvre, 2);
        $beneficeTotal = round($montant - $coutTotal, 2);
        $margePercent = null;
        if ($montant > 0.0) {
            $margePercent = round(($beneficeTotal / $montant) * 100.0, 2);
        }

        return [
            'coutMainOeuvre' => $coutMainOeuvre,
            'coutTotal' => $coutTotal,
            'beneficeTotal' => $beneficeTotal,
            'margePercent' => $margePercent,
        ];
    }

    /**
     * Déduit les minutes à partir de jours OU heures+minutes (une seule source à la fois : jours prioritaire si renseigné).
     */
    public static function resolveDurationMinutes(
        mixed $daysRaw,
        mixed $hoursRaw,
        mixed $minutesRaw,
        float $workHoursPerDay
    ): int {
        $workHoursPerDay = max(0.01, $workHoursPerDay);
        if ($daysRaw !== null && $daysRaw !== '') {
            $d = is_string($daysRaw) ? str_replace(',', '.', trim($daysRaw)) : $daysRaw;
            if (is_numeric($d) && (float) $d > 0) {
                return (int) round((float) $d * $workHoursPerDay * 60.0);
            }
        }
        $h = is_numeric($hoursRaw) ? (int) $hoursRaw : 0;
        if ($h < 0) {
            $h = 0;
        }
        $m = is_numeric($minutesRaw) ? (int) $minutesRaw : 0;
        if ($m < 0) {
            $m = 0;
        }
        if ($m > 59) {
            $h += intdiv($m, 60);
            $m %= 60;
        }

        return max(0, $h * 60 + $m);
    }

    public static function isTerminalCancelledOrRefused(?string $notes): bool
    {
        $n = (string) $notes;
        return str_contains($n, '[STATUS:CANCELLED]') || str_contains($n, '[STATUS:REFUSED_CLIENT]');
    }

    public static function isProjectTerminated(array $projectRow): bool
    {
        if (self::isTerminalCancelledOrRefused($projectRow['notes'] ?? null)) {
            return false;
        }
        if ((string) ($projectRow['status'] ?? '') === 'completed') {
            return true;
        }
        $end = $projectRow['actualEndDate'] ?? null;

        return $end !== null && $end !== '';
    }
}
