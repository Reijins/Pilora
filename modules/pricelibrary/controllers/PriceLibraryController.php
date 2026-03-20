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
    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canRead = in_array('price.library.read', $userContext->permissions, true);
        $canCreate = in_array('price.library.create', $userContext->permissions, true);
        if (!$canRead) {
            return $this->renderPage('price_library/index.php', [
                'pageTitle' => 'Bibliothèque de prestations',
                'permissionDenied' => true,
            ]);
        }

        $repo = new PriceLibraryRepository();
        $items = [];
        try {
            $items = $repo->listByCompanyId($userContext->companyId, false, 300);
        } catch (\Throwable) {
            $items = [];
        }

        return $this->renderPage('price_library/index.php', [
            'pageTitle' => 'Bibliothèque de prestations',
            'permissionDenied' => false,
            'canCreate' => $canCreate,
            'csrfToken' => Csrf::token(),
            'items' => $items,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('price.library.create', $userContext->permissions, true)) {
            return Response::redirect('price-library');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('price-library?err=CSRF%20invalide');
        }

        $name = trim((string) $request->getBodyParam('name', ''));
        $description = trim((string) $request->getBodyParam('description', ''));
        $unitLabel = trim((string) $request->getBodyParam('unit_label', ''));
        $unitPriceRaw = $request->getBodyParam('unit_price', '0');
        $estimatedTimeRaw = $request->getBodyParam('estimated_time_minutes', null);
        $status = trim((string) $request->getBodyParam('status', 'active'));

        if ($name === '') {
            return Response::redirect('price-library?err=Nom%20requis');
        }
        $unitPrice = is_numeric($unitPriceRaw) ? (float) $unitPriceRaw : 0.0;
        if ($unitPrice < 0) {
            return Response::redirect('price-library?err=Prix%20invalide');
        }
        $estimatedTime = is_numeric($estimatedTimeRaw) ? (int) $estimatedTimeRaw : null;

        $repo = new PriceLibraryRepository();
        try {
            $repo->create(
                companyId: $userContext->companyId,
                code: null,
                name: $name,
                description: $description !== '' ? $description : null,
                unitLabel: $unitLabel !== '' ? $unitLabel : null,
                unitPrice: $unitPrice,
                estimatedTimeMinutes: $estimatedTime,
                status: $status
            );
        } catch (\Throwable) {
            return Response::redirect('price-library?err=Impossible%20de%20cr%C3%A9er%20la%20prestation');
        }

        Csrf::rotate();
        return Response::redirect('price-library?msg=Prestation%20ajout%C3%A9e');
    }
}

