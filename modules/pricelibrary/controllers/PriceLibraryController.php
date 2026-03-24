<?php
declare(strict_types=1);

namespace Modules\PriceLibrary\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\PriceLibrary\Repositories\PriceLibraryRepository;

final class PriceLibraryController extends BaseController
{
    /** Saisie en heures (décimales) → stockage en minutes. */
    private static function parseEstimatedTimeHoursToMinutes(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $raw = str_replace(',', '.', trim($raw));
        }
        if (!is_numeric($raw)) {
            return null;
        }
        $hours = (float) $raw;
        if ($hours < 0) {
            throw new \InvalidArgumentException('Temps estimé invalide.');
        }

        return (int) round($hours * 60);
    }

    private function canManage(UserContext $userContext): bool
    {
        return in_array('price.library.create', $userContext->permissions, true);
    }

    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canRead = in_array('price.library.read', $userContext->permissions, true);
        $canCreate = $this->canManage($userContext);
        if (!$canRead) {
            return $this->renderPage('price_library/index.php', [
                'pageTitle' => 'Bibliothèque de prestations',
                'permissionDenied' => true,
            ]);
        }

        $subRaw = (string) $request->getQueryParam('sub', 'active');
        $subTab = in_array($subRaw, ['active', 'inactive'], true) ? $subRaw : 'active';

        $repo = new PriceLibraryRepository();
        $items = [];
        try {
            $items = $repo->listByCompanyId($userContext->companyId, false, 500);
        } catch (\Throwable) {
            $items = [];
        }

        $activeItems = [];
        $inactiveItems = [];
        foreach ($items as $it) {
            $st = (string) ($it['status'] ?? 'active');
            if ($st === 'inactive') {
                $inactiveItems[] = $it;
            } else {
                $activeItems[] = $it;
            }
        }

        return $this->renderPage('price_library/index.php', [
            'pageTitle' => 'Bibliothèque de prestations',
            'permissionDenied' => false,
            'canCreate' => $canCreate,
            'csrfToken' => Csrf::token(),
            'activeItems' => $activeItems,
            'inactiveItems' => $inactiveItems,
            'subTab' => $subTab,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function new(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!$this->canManage($userContext)) {
            return Response::redirect('price-library');
        }

        $err = $request->getQueryParam('err', null);
        $errStr = is_string($err) && $err !== '' ? $err : null;

        return $this->renderPage('price_library/new.php', [
            'pageTitle' => 'Nouvelle prestation',
            'csrfToken' => Csrf::token(),
            'error' => $errStr,
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!$this->canManage($userContext)) {
            return Response::redirect('price-library');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('price-library/new?err=' . rawurlencode('Requête invalide (CSRF).'));
        }

        $name = trim((string) $request->getBodyParam('name', ''));
        $description = trim((string) $request->getBodyParam('description', ''));
        $unitLabel = trim((string) $request->getBodyParam('unit_label', ''));
        $unitPriceRaw = $request->getBodyParam('unit_price', '0');
        $estimatedTimeRaw = $request->getBodyParam('estimated_time_hours', null);
        $status = trim((string) $request->getBodyParam('status', 'active'));
        $defaultVatRaw = str_replace(',', '.', trim((string) $request->getBodyParam('default_vat_rate', '')));
        $defaultVat = null;
        if ($defaultVatRaw !== '') {
            if (!is_numeric($defaultVatRaw)) {
                return Response::redirect('price-library/new?err=' . rawurlencode('Taux de TVA défaut invalide.'));
            }
            $defaultVat = max(0.0, min(100.0, (float) $defaultVatRaw));
        }
        $defaultRevAcc = trim((string) $request->getBodyParam('default_revenue_account', ''));

        if ($name === '') {
            return Response::redirect('price-library/new?err=' . rawurlencode('Le nom est obligatoire.'));
        }
        $unitPrice = is_numeric($unitPriceRaw) ? (float) $unitPriceRaw : 0.0;
        if ($unitPrice < 0) {
            return Response::redirect('price-library/new?err=' . rawurlencode('Prix unitaire invalide.'));
        }
        try {
            $estimatedTime = self::parseEstimatedTimeHoursToMinutes($estimatedTimeRaw);
        } catch (\InvalidArgumentException) {
            return Response::redirect('price-library/new?err=' . rawurlencode('Temps estimé invalide (heures ≥ 0).'));
        }

        $repo = new PriceLibraryRepository();
        try {
            $repo->create(
                companyId: $userContext->companyId,
                code: null,
                name: $name,
                description: $description !== '' ? $description : null,
                unitLabel: $unitLabel !== '' ? $unitLabel : null,
                unitPrice: $unitPrice,
                defaultVatRate: $defaultVat,
                defaultRevenueAccount: $defaultRevAcc !== '' ? $defaultRevAcc : null,
                estimatedTimeMinutes: $estimatedTime,
                status: $status
            );
        } catch (\Throwable) {
            return Response::redirect('price-library/new?err=' . rawurlencode('Impossible de créer la prestation.'));
        }

        Csrf::rotate();
        return Response::redirect('price-library?msg=Prestation%20ajout%C3%A9e');
    }

    public function edit(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!$this->canManage($userContext)) {
            return Response::redirect('price-library');
        }

        $id = (int) $request->getQueryParam('id', 0);
        if ($id <= 0) {
            return Response::redirect('price-library');
        }

        $repo = new PriceLibraryRepository();
        $row = $repo->findByCompanyAndId($userContext->companyId, $id);
        if ($row === null) {
            return Response::redirect('price-library?err=' . rawurlencode('Prestation introuvable.'));
        }

        $err = $request->getQueryParam('err', null);
        $errStr = is_string($err) && $err !== '' ? $err : null;

        return $this->renderPage('price_library/edit.php', [
            'pageTitle' => 'Modifier la prestation',
            'csrfToken' => Csrf::token(),
            'item' => $row,
            'error' => $errStr,
        ]);
    }

    public function update(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!$this->canManage($userContext)) {
            return Response::redirect('price-library');
        }

        $csrf = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
            return Response::redirect('price-library?err=' . rawurlencode('Requête invalide (CSRF).'));
        }

        $id = (int) $request->getBodyParam('id', 0);
        if ($id <= 0) {
            return Response::redirect('price-library');
        }

        $repo = new PriceLibraryRepository();
        if ($repo->findByCompanyAndId($userContext->companyId, $id) === null) {
            return Response::redirect('price-library?err=' . rawurlencode('Prestation introuvable.'));
        }

        $name = trim((string) $request->getBodyParam('name', ''));
        $description = trim((string) $request->getBodyParam('description', ''));
        $unitLabel = trim((string) $request->getBodyParam('unit_label', ''));
        $unitPriceRaw = $request->getBodyParam('unit_price', '0');
        $estimatedTimeRaw = $request->getBodyParam('estimated_time_hours', null);
        $status = trim((string) $request->getBodyParam('status', 'active'));
        $sub = trim((string) $request->getBodyParam('return_sub', 'active'));
        $returnSub = in_array($sub, ['active', 'inactive'], true) ? $sub : 'active';

        if ($name === '') {
            return Response::redirect('price-library/edit?id=' . $id . '&err=' . rawurlencode('Le nom est obligatoire.'));
        }
        $unitPrice = is_numeric($unitPriceRaw) ? (float) $unitPriceRaw : 0.0;
        if ($unitPrice < 0) {
            return Response::redirect('price-library/edit?id=' . $id . '&err=' . rawurlencode('Prix unitaire invalide.'));
        }
        $defaultVatRaw = str_replace(',', '.', trim((string) $request->getBodyParam('default_vat_rate', '')));
        $defaultVatVal = null;
        if ($defaultVatRaw !== '') {
            if (!is_numeric($defaultVatRaw)) {
                return Response::redirect('price-library/edit?id=' . $id . '&err=' . rawurlencode('Taux de TVA défaut invalide.'));
            }
            $defaultVatVal = max(0.0, min(100.0, (float) $defaultVatRaw));
        }
        $defaultRevAcc = trim((string) $request->getBodyParam('default_revenue_account', ''));
        try {
            $estimatedTime = self::parseEstimatedTimeHoursToMinutes($estimatedTimeRaw);
        } catch (\InvalidArgumentException) {
            return Response::redirect('price-library/edit?id=' . $id . '&err=' . rawurlencode('Temps estimé invalide (heures ≥ 0).'));
        }

        try {
            $repo->updateByCompanyAndId($userContext->companyId, $id, [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'unitLabel' => $unitLabel !== '' ? $unitLabel : null,
                'unitPrice' => $unitPrice,
                'defaultVatRate' => $defaultVatVal,
                'defaultRevenueAccount' => $defaultRevAcc,
                'estimatedTimeMinutes' => $estimatedTime,
                'status' => $status === 'inactive' ? 'inactive' : 'active',
            ]);
        } catch (\Throwable) {
            return Response::redirect('price-library/edit?id=' . $id . '&err=' . rawurlencode('Enregistrement impossible.'));
        }

        Csrf::rotate();
        return Response::redirect(
            'price-library?sub=' . rawurlencode($returnSub) . '&msg=' . rawurlencode('Prestation enregistrée.')
        );
    }

    public function delete(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!$this->canManage($userContext)) {
            return Response::redirect('price-library');
        }

        $csrfDel = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfDel) ? $csrfDel : null)) {
            return Response::redirect('price-library?sub=inactive&err=' . rawurlencode('Requête invalide (CSRF).'));
        }

        $id = (int) $request->getBodyParam('id', 0);
        if ($id <= 0) {
            return Response::redirect('price-library?sub=inactive');
        }

        $repo = new PriceLibraryRepository();
        $row = $repo->findByCompanyAndId($userContext->companyId, $id);
        if ($row === null || (string) ($row['status'] ?? '') !== 'inactive') {
            return Response::redirect('price-library?sub=inactive&err=' . rawurlencode('Suppression impossible (prestation introuvable ou encore active).'));
        }

        $ok = $repo->deleteByCompanyAndId($userContext->companyId, $id);
        if (!$ok) {
            return Response::redirect('price-library?sub=inactive&err=' . rawurlencode('Suppression impossible.'));
        }

        Csrf::rotate();
        return Response::redirect('price-library?sub=inactive&msg=' . rawurlencode('Prestation supprimée définitivement.'));
    }

    public function deactivate(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!$this->canManage($userContext)) {
            return Response::redirect('price-library');
        }

        $csrf = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
            return Response::redirect('price-library?sub=active&err=' . rawurlencode('Requête invalide (CSRF).'));
        }

        $id = (int) $request->getBodyParam('id', 0);
        if ($id <= 0) {
            return Response::redirect('price-library?sub=active');
        }

        $repo = new PriceLibraryRepository();
        $row = $repo->findByCompanyAndId($userContext->companyId, $id);
        if ($row === null) {
            return Response::redirect('price-library?sub=active&err=' . rawurlencode('Prestation introuvable.'));
        }
        if ((string) ($row['status'] ?? 'active') === 'inactive') {
            return Response::redirect('price-library?sub=inactive&msg=' . rawurlencode('Prestation déjà inactive.'));
        }

        try {
            $defVat = null;
            if (isset($row['defaultVatRate']) && is_numeric($row['defaultVatRate'])) {
                $defVat = max(0.0, min(100.0, (float) $row['defaultVatRate']));
            }
            $defAcc = isset($row['defaultRevenueAccount']) ? trim((string) $row['defaultRevenueAccount']) : '';
            $repo->updateByCompanyAndId($userContext->companyId, $id, [
                'name' => (string) ($row['name'] ?? ''),
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'unitLabel' => isset($row['unitLabel']) ? (string) $row['unitLabel'] : null,
                'unitPrice' => (float) ($row['unitPrice'] ?? 0),
                'defaultVatRate' => $defVat,
                'defaultRevenueAccount' => $defAcc,
                'estimatedTimeMinutes' => isset($row['estimatedTimeMinutes']) && $row['estimatedTimeMinutes'] !== null
                    ? (int) $row['estimatedTimeMinutes']
                    : null,
                'status' => 'inactive',
            ]);
        } catch (\Throwable) {
            return Response::redirect('price-library?sub=active&err=' . rawurlencode('Impossible de passer la prestation en inactive.'));
        }

        Csrf::rotate();
        return Response::redirect('price-library?sub=active&msg=' . rawurlencode('Prestation passée en inactive.'));
    }
}
