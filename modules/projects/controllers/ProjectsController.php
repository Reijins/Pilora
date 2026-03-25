<?php
declare(strict_types=1);

namespace Modules\Projects\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Core\View\View;
use Modules\Clients\Repositories\ClientListRepository;
use Modules\Clients\Repositories\ClientRepository;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Contacts\Repositories\ContactRepository;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Planning\Repositories\PlanningRepository;
use Modules\PriceLibrary\Repositories\PriceLibraryRepository;
use Modules\Settings\Repositories\SmtpSettingsRepository;
use Modules\Projects\Repositories\ProjectPhotoRepository;
use Modules\Projects\Repositories\ProjectRepository;
use Modules\Projects\Repositories\ProjectReportRepository;
use Modules\Projects\Repositories\ProjectTimeEntryRepository;
use Modules\Projects\Services\ProjectRentabilityService;
use Modules\Invoices\Services\DocumentTotalsService;
use Modules\Invoices\Services\InvoiceAmountsService;
use Modules\PriceLibrary\Services\PriceLineDefaultsResolver;
use Modules\Quotes\Repositories\QuoteRepository;
use Modules\Quotes\Repositories\QuoteSignatureRepository;
use Modules\Quotes\Repositories\QuoteShareRepository;
use Modules\Quotes\Services\QuoteDeliveryService;
final class ProjectsController extends BaseController
{
    /** @param array<string, mixed>|null $pi */
    private static function resolveLineVatAndAccount(?array $pi, mixed $postedVatRaw, mixed $postedAccRaw, float $companyDefaultVat): array
    {
        return PriceLineDefaultsResolver::resolve($pi, $postedVatRaw, $postedAccRaw, $companyDefaultVat);
    }

    /** Saisie heures (formulaire affaire) → minutes stockées. */
    private static function estimatedMinutesFromHoursInput(mixed $raw): ?int
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
            return null;
        }

        return (int) round($hours * 60);
    }

    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('project.read', $userContext->permissions, true)) {
            return $this->renderPage('projects/affaires.php', [
                'pageTitle' => 'Affaires',
                'permissionDenied' => true,
                'projects' => [],
                'currentTab' => 'active',
                'tabs' => [
                    'all' => 'Toutes',
                    'active' => 'En cours',
                    'planned' => 'Planifiées',
                    'waiting' => 'En attente planif.',
                    'done' => 'Terminées',
                ],
                'canUpdateProject' => false,
                'csrfToken' => Csrf::token(),
                'flashMessage' => null,
                'flashError' => null,
            ]);
        }

        $tabRaw = $request->getQueryParam('tab', 'active');
        $tab = is_string($tabRaw) ? trim($tabRaw) : 'active';

        $tabs = [
            'all' => 'Toutes',
            'active' => 'En cours',
            'planned' => 'Planifiées',
            'waiting' => 'En attente planif.',
            'done' => 'Terminées',
        ];

        $projects = [];
        try {
            $projects = (new ProjectRepository())->listByCompanyIdWithTab($userContext->companyId, $tab, 400);
        } catch (\Throwable) {
            $projects = [];
        }

        return $this->renderPage('projects/affaires.php', [
            'pageTitle' => 'Affaires',
            'permissionDenied' => false,
            'projects' => $projects,
            'currentTab' => $tab,
            'tabs' => $tabs,
            'canCreateProject' => in_array('project.create', $userContext->permissions, true),
            'canUpdateProject' => in_array('project.update', $userContext->permissions, true),
            'csrfToken' => Csrf::token(),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function new(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('project.create', $userContext->permissions, true)) {
            return $this->renderPage('projects/new.php', [
                'pageTitle' => 'Nouvelle affaire',
                'permissionDenied' => true,
            ]);
        }

        $repoClients = new ClientListRepository();
        $clients = [];
        try {
            $clients = $repoClients->listByCompanyId($userContext->companyId);
        } catch (\Throwable) {
            $clients = [];
        }

        $selectedClientIdRaw = $request->getQueryParam('clientId', null);
        $selectedClientId = is_numeric($selectedClientIdRaw) ? (int) $selectedClientIdRaw : 0;

        $priceItems = [];
        try {
            $priceItems = (new PriceLibraryRepository())->listByCompanyId($userContext->companyId, true, 300);
        } catch (\Throwable) {
            $priceItems = [];
        }
        $contacts = [];
        try {
            $contacts = (new ContactRepository())->listByCompanyId($userContext->companyId, 500);
        } catch (\Throwable) {
            $contacts = [];
        }

        $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
        $quoteVatRate = is_numeric($smtp['vat_rate'] ?? null) ? (float) $smtp['vat_rate'] : 20.0;

        return $this->renderPage('projects/new.php', [
            'pageTitle' => 'Nouvelle affaire',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'clients' => $clients,
            'selectedClientId' => $selectedClientId,
            'canCreateQuote' => in_array('quote.create', $userContext->permissions, true),
            'priceItems' => $priceItems,
            'contacts' => $contacts,
            'quoteVatRate' => $quoteVatRate,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('project.create', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('projects/new?clientId=' . max(0, (int) $request->getBodyParam('client_id', 0)) . '&err=CSRF%20invalide');
        }

        $clientIdRaw = $request->getBodyParam('client_id', null);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : 0;
        $name = trim((string) $request->getBodyParam('name', ''));
        $status = 'planned';
        $plannedStartDateYmd = trim((string) $request->getBodyParam('planned_start_date', ''));
        $plannedEndDateYmd = trim((string) $request->getBodyParam('planned_end_date', ''));
        $siteAddress = trim((string) $request->getBodyParam('site_address', ''));
        $siteCity = trim((string) $request->getBodyParam('site_city', ''));
        $sitePostalCode = trim((string) $request->getBodyParam('site_postal_code', ''));
        $notes = trim((string) $request->getBodyParam('notes', ''));
        $quoteTitle = trim((string) $request->getBodyParam('quote_title', ''));
        $contactIdRaw = $request->getBodyParam('contact_id', null);
        $contactId = is_numeric($contactIdRaw) ? (int) $contactIdRaw : 0;

        if ($clientId <= 0) {
            return Response::redirect('projects/new?err=Client%20invalide');
        }
        if ($name === '') {
            return Response::redirect('projects/new?clientId=' . $clientId . '&err=Nom%20requis');
        }

        $plannedStartDateYmd = $plannedStartDateYmd !== '' ? $plannedStartDateYmd : null;
        $plannedEndDateYmd = $plannedEndDateYmd !== '' ? $plannedEndDateYmd : null;
        if ($contactId > 0) {
            $notes = trim('[CONTACT_ID:' . $contactId . '] ' . $notes);
        }
        $notes = $notes !== '' ? $notes : null;

        $repo = new ProjectRepository();
        $projectId = 0;
        try {
            $projectId = $repo->createProject(
                companyId: $userContext->companyId,
                clientId: $clientId,
                name: $name,
                status: $status,
                plannedStartDateYmd: $plannedStartDateYmd,
                plannedEndDateYmd: $plannedEndDateYmd,
                siteAddress: $siteAddress !== '' ? $siteAddress : null,
                siteCity: $siteCity !== '' ? $siteCity : null,
                sitePostalCode: $sitePostalCode !== '' ? $sitePostalCode : null,
                notes: $notes,
                createdByUserId: $userContext->userId,
            );
        } catch (\Throwable) {
            return Response::redirect('projects/new?clientId=' . $clientId . '&err=Impossible%20de%20cr%C3%A9er%20l%27affaire');
        }

        $devisCreated = false;
        $devisCreationFailed = false;
        $createdQuoteId = 0;
        // Case décochée = param absent : ne pas défaut à « envoyer ».
        $sendQuoteEmail = (string) $request->getBodyParam('send_quote_email', '') === '1';
        $canCreateQuote = in_array('quote.create', $userContext->permissions, true);
        if ($canCreateQuote) {
            $title = $quoteTitle !== '' ? $quoteTitle : ('Devis - ' . $name);
            $smtpQuote = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
            $companyDefaultVat = is_numeric($smtpQuote['vat_rate'] ?? null) ? (float) $smtpQuote['vat_rate'] : 20.0;

            $priceItems = [];
            try {
                $priceItems = (new PriceLibraryRepository())->listByCompanyId($userContext->companyId, true, 1000);
            } catch (\Throwable) {
                $priceItems = [];
            }
            $priceItemMap = [];
            $priceItemMapByName = [];
            foreach ($priceItems as $pi) {
                $id = (int) ($pi['id'] ?? 0);
                if ($id > 0) {
                    $priceItemMap[$id] = $pi;
                }
                $nm = trim((string) ($pi['name'] ?? ''));
                if ($nm !== '') {
                    $key = function_exists('mb_strtolower') ? mb_strtolower($nm, 'UTF-8') : strtolower($nm);
                    if (!isset($priceItemMapByName[$key])) {
                        $priceItemMapByName[$key] = $pi;
                    }
                }
            }

            $itemNamesRaw = $request->getBodyParam('item_name', []);
            $priceItemIdsRaw = $request->getBodyParam('item_price_item_id', []);
            $quantitiesRaw = $request->getBodyParam('item_quantity', []);
            $unitPricesRaw = $request->getBodyParam('item_unit_price', []);
            $estimatedTimesRaw = $request->getBodyParam('item_estimated_time_hours', []);
            $saveToLibraryRaw = $request->getBodyParam('item_save_to_library', []);
            $itemVatsRaw = $request->getBodyParam('item_vat_rate', []);
            $itemAccsRaw = $request->getBodyParam('item_revenue_account', []);

            $itemNames = is_array($itemNamesRaw) ? $itemNamesRaw : [];
            $priceItemIds = is_array($priceItemIdsRaw) ? $priceItemIdsRaw : [];
            $quantities = is_array($quantitiesRaw) ? $quantitiesRaw : [];
            $unitPrices = is_array($unitPricesRaw) ? $unitPricesRaw : [];
            $estimatedTimes = is_array($estimatedTimesRaw) ? $estimatedTimesRaw : [];
            $saveToLibrary = is_array($saveToLibraryRaw) ? $saveToLibraryRaw : [];
            $itemVats = is_array($itemVatsRaw) ? $itemVatsRaw : [];
            $itemAccs = is_array($itemAccsRaw) ? $itemAccsRaw : [];

            $count = max(
                count($itemNames),
                count($priceItemIds),
                count($quantities),
                count($unitPrices),
                count($estimatedTimes),
                count($saveToLibrary),
                count($itemVats),
                count($itemAccs)
            );

            $items = [];
            $manualItemsToSave = [];
            $canCreatePriceLibrary = in_array('price.library.create', $userContext->permissions, true);

            for ($i = 0; $i < $count; $i++) {
                $priceItemId = isset($priceItemIds[$i]) && is_numeric($priceItemIds[$i]) ? (int) $priceItemIds[$i] : null;
                if ($priceItemId !== null && $priceItemId <= 0) {
                    $priceItemId = null;
                }

                $nameItem = trim((string) ($itemNames[$i] ?? ''));
                $quantity = is_numeric($quantities[$i] ?? null) ? (float) $quantities[$i] : 1.0;
                $unitPrice = is_numeric($unitPrices[$i] ?? null) ? (float) $unitPrices[$i] : 0.0;
                $estimatedTime = self::estimatedMinutesFromHoursInput($estimatedTimes[$i] ?? null);

                $description = $nameItem;
                if ($priceItemId === null && $nameItem !== '') {
                    $key = function_exists('mb_strtolower') ? mb_strtolower($nameItem, 'UTF-8') : strtolower($nameItem);
                    if (isset($priceItemMapByName[$key])) {
                        $pi = $priceItemMapByName[$key];
                        $priceItemId = (int) ($pi['id'] ?? 0);
                    }
                }

                if ($priceItemId !== null && isset($priceItemMap[$priceItemId])) {
                    $pi = $priceItemMap[$priceItemId];
                    $unitPrice = is_numeric($pi['unitPrice'] ?? null) ? (float) $pi['unitPrice'] : $unitPrice;
                    if (is_numeric($pi['estimatedTimeMinutes'] ?? null)) {
                        $estimatedTime = (int) $pi['estimatedTimeMinutes'];
                    }
                    if ($nameItem === '') {
                        $nameItem = (string) ($pi['name'] ?? '');
                    }
                    $description = (string) (($pi['description'] ?? '') !== '' ? $pi['description'] : ($pi['name'] ?? $description));
                }

                if ($nameItem === '' && $priceItemId === null) {
                    continue;
                }
                if ($quantity <= 0 || $unitPrice <= 0) {
                    continue;
                }

                $libRow = ($priceItemId !== null && isset($priceItemMap[$priceItemId]))
                    ? $priceItemMap[$priceItemId]
                    : null;
                $comm = self::resolveLineVatAndAccount(
                    is_array($libRow) ? $libRow : null,
                    $itemVats[$i] ?? null,
                    $itemAccs[$i] ?? null,
                    $companyDefaultVat,
                );

                $items[] = [
                    'priceLibraryItemId' => $priceItemId,
                    'description' => $description !== '' ? $description : ($nameItem !== '' ? $nameItem : 'Prestation'),
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice,
                    'estimatedTimeMinutes' => $estimatedTime,
                    'vatRate' => $comm['vatRate'],
                    'revenueAccount' => $comm['revenueAccount'],
                ];

                $shouldSave = isset($saveToLibrary[(string) $i]) || isset($saveToLibrary[$i]);
                if ($canCreatePriceLibrary && $shouldSave && $priceItemId === null && $nameItem !== '') {
                    $manualItemsToSave[] = [
                        'name' => $nameItem,
                        'description' => $description !== '' ? $description : $nameItem,
                        'unitPrice' => $unitPrice,
                        'estimatedTimeMinutes' => $estimatedTime,
                        'defaultVatRate' => $comm['vatRate'],
                        'defaultRevenueAccount' => $comm['revenueAccount'],
                    ];
                }
            }

            // Fallback de sécurité si aucune ligne valide soumise.
            if ($items === []) {
                $items[] = [
                    'priceLibraryItemId' => null,
                    'description' => 'Prestation initiale',
                    'quantity' => 1.0,
                    'unitPrice' => 1.0,
                    'estimatedTimeMinutes' => null,
                    'vatRate' => $companyDefaultVat,
                    'revenueAccount' => null,
                ];
            }

            try {
                $createdQuoteId = (new QuoteRepository())->createQuoteWithItems(
                    companyId: $userContext->companyId,
                    clientId: $clientId,
                    projectId: $projectId > 0 ? $projectId : null,
                    title: $title,
                    status: 'brouillon',
                    quoteNumber: null,
                    createdByUserId: (int) $userContext->userId,
                    items: $items,
                );
                if ($canCreatePriceLibrary && $manualItemsToSave !== []) {
                    $priceRepo = new PriceLibraryRepository();
                    foreach ($manualItemsToSave as $mi) {
                        $dv = $mi['defaultVatRate'] ?? null;
                        $priceRepo->create(
                            companyId: $userContext->companyId,
                            code: null,
                            name: (string) $mi['name'],
                            description: (string) $mi['description'],
                            unitLabel: null,
                            unitPrice: (float) $mi['unitPrice'],
                            categoryId: null,
                            defaultVatRate: is_numeric($dv) ? (float) $dv : null,
                            defaultRevenueAccount: isset($mi['defaultRevenueAccount']) && $mi['defaultRevenueAccount'] !== null
                                ? (string) $mi['defaultRevenueAccount']
                                : null,
                            estimatedTimeMinutes: isset($mi['estimatedTimeMinutes']) && is_numeric($mi['estimatedTimeMinutes']) ? (int) $mi['estimatedTimeMinutes'] : null,
                            status: 'active',
                        );
                    }
                }
                $devisCreated = true;
            } catch (\Throwable) {
                // L'affaire est créée, on ne bloque pas tout en cas d'échec devis.
                $devisCreationFailed = true;
            }
        }

        Csrf::rotate();
        if ($devisCreationFailed) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Affaire%20cr%C3%A9%C3%A9e%2C%20mais%20le%20devis%20n%27a%20pas%20pu%20%C3%AAtre%20cr%C3%A9%C3%A9');
        }
        $msg = $devisCreated ? 'Affaire%20et%20devis%20cr%C3%A9%C3%A9s' : 'Affaire%20cr%C3%A9%C3%A9e';
        if ($devisCreated && $sendQuoteEmail && $createdQuoteId > 0) {
            $emailSent = $this->sendQuoteEmailInternal($userContext->companyId, $projectId, $createdQuoteId);
            if ($emailSent) {
                $msg = 'Affaire%20cr%C3%A9%C3%A9e%20et%20devis%20envoy%C3%A9%20par%20email';
            } else {
                $msg = 'Affaire%20et%20devis%20cr%C3%A9%C3%A9s%20%28envoi%20email%20%C3%A9chou%C3%A9%29';
            }
        }
        $quoteParam = $createdQuoteId > 0 ? '&quoteId=' . $createdQuoteId : '';
        return Response::redirect('projects/show?projectId=' . $projectId . '&msg=' . $msg . $quoteParam);
    }

    public function show(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('project.read', $userContext->permissions, true)) {
            return $this->renderPage('projects/show.php', [
                'pageTitle' => 'Fiche affaire',
                'permissionDenied' => true,
            ]);
        }

        $projectIdRaw = $request->getQueryParam('projectId', 0);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : 0;
        if ($projectId <= 0) {
            return Response::redirect('clients?err=Affaire%20invalide');
        }

        $project = null;
        try {
            $project = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
        } catch (\Throwable) {
            $project = null;
        }

        if ($project === null) {
            return Response::redirect('clients?err=Affaire%20introuvable');
        }

        $quotes = [];
        $invoices = [];
        $reports = [];
        $photos = [];
        $paidAmount = 0.0;
        $quoteItemsByQuoteId = [];

        $quoteRepo = new QuoteRepository();
        try {
            $quotes = $quoteRepo->listByCompanyIdAndProjectId($userContext->companyId, $projectId, null, 200);
        } catch (\Throwable) {
            $quotes = [];
        }

        // Fallback robuste: si aucun devis lié par projectId, tente une correspondance
        // sur les devis du client par titre d'affaire (utile si liaison projectId indisponible).
        if ($quotes === [] && (int) ($project['clientId'] ?? 0) > 0) {
            try {
                $clientQuotes = $quoteRepo->listByCompanyIdAndClientId(
                    companyId: $userContext->companyId,
                    clientId: (int) $project['clientId'],
                    status: null,
                    limit: 300
                );
                $projectName = trim((string) ($project['name'] ?? ''));
                $titleCandidates = [];
                if ($projectName !== '') {
                    $titleCandidates[] = $projectName;
                    $titleCandidates[] = 'Devis - ' . $projectName;
                }
                if ($titleCandidates !== []) {
                    $quotes = array_values(array_filter(
                        $clientQuotes,
                        static function (array $q) use ($titleCandidates): bool {
                            $title = trim((string) ($q['title'] ?? ''));
                            foreach ($titleCandidates as $candidate) {
                                if ($title !== '' && $title === $candidate) {
                                    return true;
                                }
                            }
                            return false;
                        }
                    ));
                }
            } catch (\Throwable) {
                // no-op fallback
            }
        }

        // Fallback création immédiate: si un quoteId est passé en query, forcer son affichage.
        $forcedQuoteIdRaw = $request->getQueryParam('quoteId', null);
        $forcedQuoteId = is_numeric($forcedQuoteIdRaw) ? (int) $forcedQuoteIdRaw : 0;
        if ($forcedQuoteId > 0) {
            try {
                $forcedQuote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $forcedQuoteId);
                if (is_array($forcedQuote) && (int) ($forcedQuote['clientId'] ?? 0) === (int) ($project['clientId'] ?? 0)) {
                    $alreadyInList = false;
                    foreach ($quotes as $q) {
                        if ((int) ($q['id'] ?? 0) === $forcedQuoteId) {
                            $alreadyInList = true;
                            break;
                        }
                    }
                    if (!$alreadyInList) {
                        array_unshift($quotes, $forcedQuote);
                    }
                }
            } catch (\Throwable) {
                // no-op
            }
        }
        try {
            $invoices = (new InvoiceRepository())->listByCompanyIdAndProjectId($userContext->companyId, $projectId, null, 200);
            $invRepo = new InvoiceRepository();
            foreach ($invoices as &$invRow) {
                try {
                    $invRow['paymentToken'] = $invRepo->ensurePaymentToken(
                        $userContext->companyId,
                        (int) ($invRow['id'] ?? 0)
                    );
                } catch (\Throwable) {
                    $invRow['paymentToken'] = '';
                }
            }
            unset($invRow);
        } catch (\Throwable) {
            $invoices = [];
        }

        $sortedInvoicesForNav = array_values($invoices);
        usort($sortedInvoicesForNav, static function (array $a, array $b): int {
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });
        $activeInvoiceIdRaw = $request->getQueryParam('invoiceId', null);
        $activeInvoiceIdParam = is_numeric($activeInvoiceIdRaw) ? (int) $activeInvoiceIdRaw : 0;
        $activeInvoice = null;
        $invoiceNavIndex = 0;
        $invoiceNavTotal = count($sortedInvoicesForNav);
        $invoiceNavPrevId = 0;
        $invoiceNavNextId = 0;
        if ($invoiceNavTotal > 0) {
            $foundIdx = -1;
            if ($activeInvoiceIdParam > 0) {
                foreach ($sortedInvoicesForNav as $idx => $invRow) {
                    if ((int) ($invRow['id'] ?? 0) === $activeInvoiceIdParam) {
                        $foundIdx = $idx;
                        break;
                    }
                }
            }
            if ($foundIdx < 0) {
                $foundIdx = $invoiceNavTotal - 1;
            }
            $invoiceNavIndex = $foundIdx;
            $activeInvoice = $sortedInvoicesForNav[$foundIdx];
            if ($foundIdx > 0) {
                $invoiceNavPrevId = (int) ($sortedInvoicesForNav[$foundIdx - 1]['id'] ?? 0);
            }
            if ($foundIdx < $invoiceNavTotal - 1) {
                $invoiceNavNextId = (int) ($sortedInvoicesForNav[$foundIdx + 1]['id'] ?? 0);
            }
        }

        try {
            $reports = (new ProjectReportRepository())->listByCompanyIdAndProjectId($userContext->companyId, $projectId, 8);
        } catch (\Throwable) {
            $reports = [];
        }
        try {
            $photos = (new ProjectPhotoRepository())->listByCompanyIdAndProjectId($userContext->companyId, $projectId, 12);
        } catch (\Throwable) {
            $photos = [];
        }
        $quoteAmount = 0.0;
        foreach ($quotes as $q) {
            $qid = (int) ($q['id'] ?? 0);
            if ($qid <= 0) {
                continue;
            }
            if ((string) ($q['status'] ?? '') === 'accepte') {
                try {
                    $quoteAmount += $quoteRepo->computeQuoteTotalAmount($userContext->companyId, $qid);
                } catch (\Throwable) {
                    // no-op
                }
            }
            try {
                $quoteItemsByQuoteId[$qid] = $quoteRepo->listItemsByCompanyIdAndQuoteId($userContext->companyId, $qid);
            } catch (\Throwable) {
                $quoteItemsByQuoteId[$qid] = [];
            }
        }

        $activeQuoteIdRaw = $request->getQueryParam('quoteVersionId', null);
        $activeQuoteId = is_numeric($activeQuoteIdRaw) ? (int) $activeQuoteIdRaw : 0;
        if ($activeQuoteId > 0) {
            $activeAlreadyPresent = false;
            foreach ($quotes as $q) {
                if ((int) ($q['id'] ?? 0) === $activeQuoteId) {
                    $activeAlreadyPresent = true;
                    break;
                }
            }
            if (!$activeAlreadyPresent) {
                try {
                    $activeForcedQuote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $activeQuoteId);
                    if (is_array($activeForcedQuote) && (int) ($activeForcedQuote['clientId'] ?? 0) === (int) ($project['clientId'] ?? 0)) {
                        $quotes[] = $activeForcedQuote;
                    }
                } catch (\Throwable) {
                    // no-op
                }
            }
        }
        if ($activeQuoteId <= 0 && !empty($quotes)) {
            $activeQuoteId = (int) ($quotes[0]['id'] ?? 0);
        }

        $activeQuote = null;
        foreach ($quotes as $q) {
            if ((int) ($q['id'] ?? 0) === $activeQuoteId) {
                $activeQuote = $q;
                break;
            }
        }
        if ($activeQuote === null && !empty($quotes)) {
            $activeQuote = $quotes[0];
            $activeQuoteId = (int) ($activeQuote['id'] ?? 0);
        }

        $quoteVersions = [];
        $quoteVersionIndex = 0;
        if (is_array($activeQuote)) {
            $activeTitle = trim((string) ($activeQuote['title'] ?? ''));
            foreach ($quotes as $q) {
                if (trim((string) ($q['title'] ?? '')) === $activeTitle) {
                    $quoteVersions[] = $q;
                }
            }
            usort($quoteVersions, static function (array $a, array $b): int {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            });
            foreach ($quoteVersions as $idx => $v) {
                if ((int) ($v['id'] ?? 0) === $activeQuoteId) {
                    $quoteVersionIndex = $idx;
                    break;
                }
            }
        }

        $invoiceAmount = 0.0;
        $remainingAmount = 0.0;
        foreach ($invoices as $inv) {
            if ((string) ($inv['status'] ?? '') === 'annulee') {
                continue;
            }
            $invoiceAmount += (float) ($inv['amountTotal'] ?? 0);
            $remainingAmount += (float) ($inv['amountRemaining'] ?? 0);
            $paidAmount += (float) ($inv['amountPaid'] ?? 0);
        }
        $invoiceAmount = (float) round($invoiceAmount, 2);
        $remainingAmount = (float) round($remainingAmount, 2);
        $paidAmount = (float) round($paidAmount, 2);
        $quoteAmount = (float) round($quoteAmount, 2);

        $canCreateQuote = in_array('quote.create', $userContext->permissions, true);
        $companySettings = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
        $vatRate = is_numeric($companySettings['vat_rate'] ?? null) ? (float) $companySettings['vat_rate'] : 20.0;
        $proofRequired = (string) ($companySettings['proof_required'] ?? '0') === '1';
        $canSendQuote = in_array('quote.read', $userContext->permissions, true);
        $canPlanningCreate = in_array('planning.create', $userContext->permissions, true);
        $canReportRead = in_array('project.report.read', $userContext->permissions, true);
        $canReportCreate = in_array('project.report.create', $userContext->permissions, true);
        $canPhotoRead = in_array('project.photo.read', $userContext->permissions, true);
        $canPhotoUpload = in_array('project.photo.upload', $userContext->permissions, true);
        $canInvoiceSend = in_array('invoice.read', $userContext->permissions, true);
        $canInvoiceCreate = in_array('invoice.create', $userContext->permissions, true);
        $canInvoiceMarkPaid = in_array('invoice.mark_paid', $userContext->permissions, true);
        $canInvoiceUpdate = in_array('invoice.update', $userContext->permissions, true);

        try {
            $defaultInvoiceDueDate = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d');
        } catch (\Throwable) {
            $defaultInvoiceDueDate = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d');
        }

        $companyRow = [];
        $workHoursPerDayUi = 8.0;
        try {
            $companyRow = (new CompanyRepository())->findById($userContext->companyId) ?: [];
            if (is_numeric($companyRow['workHoursPerDay'] ?? null)) {
                $workHoursPerDayUi = (float) $companyRow['workHoursPerDay'];
            }
        } catch (\Throwable) {
            $companyRow = [];
        }
        $isTerminated = ProjectRentabilityService::isProjectTerminated($project);
        $canUpdateProj = in_array('project.update', $userContext->permissions, true);
        $rentStat = (string) ($project['rentabiliteStatut'] ?? 'a_renseigner');
        $laborPreview = 0.0;
        if ($isTerminated) {
            try {
                $laborPreview = (new ProjectRentabilityService())->laborCostAmount($userContext->companyId, $projectId);
            } catch (\Throwable) {
                $laborPreview = 0.0;
            }
        }

        return $this->renderPage('projects/show.php', [
            'pageTitle' => 'Fiche affaire',
            'permissionDenied' => false,
            'project' => $project,
            'kpiQuoteAmount' => $quoteAmount,
            'kpiInvoiceAmount' => $invoiceAmount,
            'kpiPaidAmount' => $paidAmount,
            'kpiRemainingAmount' => $remainingAmount,
            'quotesCount' => count($quotes),
            'quotes' => $quotes,
            'activeQuote' => $activeQuote,
            'quoteVersions' => $quoteVersions,
            'quoteVersionIndex' => $quoteVersionIndex,
            'quoteItemsByQuoteId' => $quoteItemsByQuoteId,
            'invoicesCount' => count(array_filter(
                $invoices,
                static fn (array $inv): bool => (string) ($inv['status'] ?? '') !== 'annulee'
            )),
            'invoices' => $invoices,
            'activeInvoice' => $activeInvoice,
            'invoiceNavIndex' => $invoiceNavIndex,
            'invoiceNavTotal' => $invoiceNavTotal,
            'invoiceNavPrevId' => $invoiceNavPrevId,
            'invoiceNavNextId' => $invoiceNavNextId,
            'reports' => $reports,
            'photos' => $photos,
            'csrfToken' => Csrf::token(),
            'canCreateQuote' => $canCreateQuote,
            'canSendQuote' => $canSendQuote,
            'canPlanningCreate' => $canPlanningCreate,
            'canReportRead' => $canReportRead,
            'canReportCreate' => $canReportCreate,
            'canPhotoRead' => $canPhotoRead,
            'canPhotoUpload' => $canPhotoUpload,
            'canInvoiceSend' => $canInvoiceSend,
            'canInvoiceCreate' => $canInvoiceCreate,
            'canInvoiceMarkPaid' => $canInvoiceMarkPaid,
            'canInvoiceUpdate' => $canInvoiceUpdate,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
            'vatRate' => $vatRate,
            'proofRequired' => $proofRequired,
            'defaultInvoiceDueDate' => $defaultInvoiceDueDate,
            'isAffaireTerminee' => $isTerminated,
            'workHoursPerDayUi' => $workHoursPerDayUi,
            'canCompleteAffaire' => $canUpdateProj && !$isTerminated && !ProjectRentabilityService::isTerminalCancelledOrRefused((string) ($project['notes'] ?? '')),
            'canEditRentabilite' => $canUpdateProj && $isTerminated,
            'laborCostPreview' => $laborPreview,
            'rentabiliteStatutCode' => $rentStat,
        ]);
    }

    public function createQuoteVersion(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('quote.create', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }

        $projectIdRaw = $request->getBodyParam('project_id', 0);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : 0;
        $sourceQuoteIdRaw = $request->getBodyParam('source_quote_id', 0);
        $sourceQuoteId = is_numeric($sourceQuoteIdRaw) ? (int) $sourceQuoteIdRaw : 0;
        $title = trim((string) $request->getBodyParam('quote_title', ''));
        $sendQuoteEmail = (string) $request->getBodyParam('send_quote_email', '') === '1';

        if ($projectId <= 0 || $sourceQuoteId <= 0) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Version%20de%20devis%20invalide');
        }

        $quoteRepo = new QuoteRepository();
        $sourceQuote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $sourceQuoteId);
        if (!is_array($sourceQuote)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Devis%20source%20introuvable');
        }

        $clientId = (int) ($sourceQuote['clientId'] ?? 0);
        if ($clientId <= 0) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Client%20du%20devis%20introuvable');
        }
        if ($title === '') {
            $title = (string) ($sourceQuote['title'] ?? ('Devis - Affaire #' . $projectId));
        }

        $smtpVer = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
        $companyDefaultVat = is_numeric($smtpVer['vat_rate'] ?? null) ? (float) $smtpVer['vat_rate'] : 20.0;

        $itemNamesRaw = $request->getBodyParam('item_name', []);
        $priceItemIdsRaw = $request->getBodyParam('item_price_item_id', []);
        $quantitiesRaw = $request->getBodyParam('item_quantity', []);
        $unitPricesRaw = $request->getBodyParam('item_unit_price', []);
        $timesRaw = $request->getBodyParam('item_estimated_time_hours', []);
        $saveToLibraryRaw = $request->getBodyParam('item_save_to_library', []);
        $itemVatsRaw = $request->getBodyParam('item_vat_rate', []);
        $itemAccsRaw = $request->getBodyParam('item_revenue_account', []);

        $itemNames = is_array($itemNamesRaw) ? $itemNamesRaw : [];
        $priceItemIds = is_array($priceItemIdsRaw) ? $priceItemIdsRaw : [];
        $quantities = is_array($quantitiesRaw) ? $quantitiesRaw : [];
        $unitPrices = is_array($unitPricesRaw) ? $unitPricesRaw : [];
        $times = is_array($timesRaw) ? $timesRaw : [];
        $saveToLibrary = is_array($saveToLibraryRaw) ? $saveToLibraryRaw : [];
        $itemVats = is_array($itemVatsRaw) ? $itemVatsRaw : [];
        $itemAccs = is_array($itemAccsRaw) ? $itemAccsRaw : [];

        $priceItems = [];
        try {
            $priceItems = (new PriceLibraryRepository())->listByCompanyId($userContext->companyId, true, 1000);
        } catch (\Throwable) {
            $priceItems = [];
        }
        $priceItemMap = [];
        $priceItemMapByName = [];
        foreach ($priceItems as $pi) {
            $id = (int) ($pi['id'] ?? 0);
            if ($id > 0) {
                $priceItemMap[$id] = $pi;
            }
            $nm = trim((string) ($pi['name'] ?? ''));
            if ($nm !== '') {
                $key = function_exists('mb_strtolower') ? mb_strtolower($nm, 'UTF-8') : strtolower($nm);
                if (!isset($priceItemMapByName[$key])) {
                    $priceItemMapByName[$key] = $pi;
                }
            }
        }

        $count = max(
            count($itemNames),
            count($priceItemIds),
            count($quantities),
            count($unitPrices),
            count($times),
            count($saveToLibrary),
            count($itemVats),
            count($itemAccs)
        );
        $items = [];
        $manualItemsToSave = [];
        $canCreatePriceLibrary = in_array('price.library.create', $userContext->permissions, true);
        for ($i = 0; $i < $count; $i++) {
            $priceItemId = isset($priceItemIds[$i]) && is_numeric($priceItemIds[$i]) ? (int) $priceItemIds[$i] : null;
            if ($priceItemId !== null && $priceItemId <= 0) {
                $priceItemId = null;
            }
            $nameItem = trim((string) ($itemNames[$i] ?? ''));
            $quantity = is_numeric($quantities[$i] ?? null) ? (float) $quantities[$i] : 0.0;
            $unitPrice = is_numeric($unitPrices[$i] ?? null) ? (float) $unitPrices[$i] : 0.0;
            $estimated = self::estimatedMinutesFromHoursInput($times[$i] ?? null);

            $description = $nameItem;
            if ($priceItemId === null && $nameItem !== '') {
                $key = function_exists('mb_strtolower') ? mb_strtolower($nameItem, 'UTF-8') : strtolower($nameItem);
                if (isset($priceItemMapByName[$key])) {
                    $pi = $priceItemMapByName[$key];
                    $priceItemId = (int) ($pi['id'] ?? 0);
                }
            }
            if ($priceItemId !== null && isset($priceItemMap[$priceItemId])) {
                $pi = $priceItemMap[$priceItemId];
                $unitPrice = is_numeric($pi['unitPrice'] ?? null) ? (float) $pi['unitPrice'] : $unitPrice;
                if (is_numeric($pi['estimatedTimeMinutes'] ?? null)) {
                    $estimated = (int) $pi['estimatedTimeMinutes'];
                }
                if ($nameItem === '') {
                    $nameItem = (string) ($pi['name'] ?? '');
                }
                $description = (string) (($pi['description'] ?? '') !== '' ? $pi['description'] : ($pi['name'] ?? $description));
            }

            if (($nameItem === '' && $priceItemId === null) || $quantity <= 0 || $unitPrice <= 0) {
                continue;
            }

            $libRow = ($priceItemId !== null && isset($priceItemMap[$priceItemId]))
                ? $priceItemMap[$priceItemId]
                : null;
            $comm = self::resolveLineVatAndAccount(
                is_array($libRow) ? $libRow : null,
                $itemVats[$i] ?? null,
                $itemAccs[$i] ?? null,
                $companyDefaultVat,
            );

            $items[] = [
                'priceLibraryItemId' => $priceItemId,
                'description' => $description !== '' ? $description : ($nameItem !== '' ? $nameItem : 'Prestation'),
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'estimatedTimeMinutes' => $estimated,
                'vatRate' => $comm['vatRate'],
                'revenueAccount' => $comm['revenueAccount'],
            ];

            $shouldSave = isset($saveToLibrary[(string) $i]) || isset($saveToLibrary[$i]);
            if ($canCreatePriceLibrary && $shouldSave && $priceItemId === null && $nameItem !== '') {
                $manualItemsToSave[] = [
                    'name' => $nameItem,
                    'description' => $description !== '' ? $description : $nameItem,
                    'unitPrice' => $unitPrice,
                    'estimatedTimeMinutes' => $estimated,
                    'defaultVatRate' => $comm['vatRate'],
                    'defaultRevenueAccount' => $comm['revenueAccount'],
                ];
            }
        }

        if ($items === []) {
            return Response::redirect('projects/quotes/version/new?projectId=' . $projectId . '&sourceQuoteId=' . $sourceQuoteId . '&err=Aucune%20ligne%20valide%20pour%20la%20nouvelle%20version');
        }

        try {
            $newQuoteId = $quoteRepo->createQuoteWithItems(
                companyId: $userContext->companyId,
                clientId: $clientId,
                projectId: $projectId,
                title: $title,
                status: 'brouillon',
                quoteNumber: null,
                createdByUserId: (int) $userContext->userId,
                items: $items
            );
            if ($canCreatePriceLibrary && $manualItemsToSave !== []) {
                $priceRepo = new PriceLibraryRepository();
                foreach ($manualItemsToSave as $mi) {
                    $dv = $mi['defaultVatRate'] ?? null;
                    $priceRepo->create(
                        companyId: $userContext->companyId,
                        code: null,
                        name: (string) $mi['name'],
                        description: (string) $mi['description'],
                        unitLabel: null,
                        unitPrice: (float) $mi['unitPrice'],
                        categoryId: null,
                        defaultVatRate: is_numeric($dv) ? (float) $dv : null,
                        defaultRevenueAccount: isset($mi['defaultRevenueAccount']) && $mi['defaultRevenueAccount'] !== null
                            ? (string) $mi['defaultRevenueAccount']
                            : null,
                        estimatedTimeMinutes: isset($mi['estimatedTimeMinutes']) && is_numeric($mi['estimatedTimeMinutes']) ? (int) $mi['estimatedTimeMinutes'] : null,
                        status: 'active',
                    );
                }
            }
        } catch (\Throwable) {
            return Response::redirect('projects/quotes/version/new?projectId=' . $projectId . '&sourceQuoteId=' . $sourceQuoteId . '&err=Impossible%20de%20cr%C3%A9er%20la%20nouvelle%20version');
        }

        Csrf::rotate();
        $msg = 'Nouvelle%20version%20du%20devis%20cr%C3%A9%C3%A9e';
        if ($sendQuoteEmail) {
            $emailSent = $this->sendQuoteEmailInternal($userContext->companyId, $projectId, $newQuoteId);
            if ($emailSent) {
                $msg = 'Nouvelle%20version%20cr%C3%A9%C3%A9e%20et%20envoy%C3%A9e';
            } else {
                $msg = 'Nouvelle%20version%20cr%C3%A9%C3%A9e%20%28envoi%20email%20%C3%A9chou%C3%A9%29';
            }
        }
        return Response::redirect('projects/show?projectId=' . $projectId . '&quoteVersionId=' . $newQuoteId . '&msg=' . $msg);
    }

    public function newQuoteVersion(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('quote.create', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $projectIdRaw = $request->getQueryParam('projectId', 0);
        $sourceQuoteIdRaw = $request->getQueryParam('sourceQuoteId', 0);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : 0;
        $sourceQuoteId = is_numeric($sourceQuoteIdRaw) ? (int) $sourceQuoteIdRaw : 0;
        if ($projectId <= 0 || $sourceQuoteId <= 0) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Version%20de%20devis%20invalide');
        }

        $project = null;
        try {
            $project = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
        } catch (\Throwable) {
            $project = null;
        }
        if (!is_array($project)) {
            return Response::redirect('clients?err=Affaire%20introuvable');
        }

        $quoteRepo = new QuoteRepository();
        $sourceQuote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $sourceQuoteId);
        if (!is_array($sourceQuote)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Devis%20source%20introuvable');
        }

        $sourceItems = $quoteRepo->listItemsByCompanyIdAndQuoteId($userContext->companyId, $sourceQuoteId);
        $priceItems = [];
        try {
            $priceItems = (new PriceLibraryRepository())->listByCompanyId($userContext->companyId, true, 300);
        } catch (\Throwable) {
            $priceItems = [];
        }

        $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
        $quoteVatRate = is_numeric($smtp['vat_rate'] ?? null) ? (float) $smtp['vat_rate'] : 20.0;

        return $this->renderPage('projects/quote_version_new.php', [
            'pageTitle' => 'Nouvelle version de devis',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'project' => $project,
            'sourceQuote' => $sourceQuote,
            'sourceItems' => $sourceItems,
            'priceItems' => $priceItems,
            'quoteVatRate' => $quoteVatRate,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
            'canCreateQuote' => true,
        ]);
    }

    public function statusUpdate(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('project.update', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $returnTo = trim((string) $request->getBodyParam('return_to', ''));
        $returnTabRaw = trim((string) $request->getBodyParam('return_tab', 'all'));
        $allowedReturnTabs = ['all', 'active', 'planned', 'waiting', 'done'];
        $returnTab = in_array($returnTabRaw, $allowedReturnTabs, true) ? $returnTabRaw : 'all';
        $fromProjects = $returnTo === 'projects';

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            $clientIdEarly = (int) $request->getBodyParam('client_id', 0);
            if ($fromProjects) {
                return Response::redirect('projects?tab=' . rawurlencode($returnTab) . '&err=CSRF%20invalide');
            }

            return Response::redirect('clients/show?clientId=' . max(0, $clientIdEarly) . '&err=CSRF%20invalide');
        }

        $projectIdRaw = $request->getBodyParam('project_id', null);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : 0;
        $clientIdRaw = $request->getBodyParam('client_id', null);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : 0;

        $newStatus = trim((string) $request->getBodyParam('new_status', ''));
        $reason = trim((string) $request->getBodyParam('reason', ''));

        $allowedStatuses = ['cancelled', 'refused_client'];
        if ($projectId <= 0) {
            if ($fromProjects) {
                return Response::redirect('projects?tab=' . rawurlencode($returnTab) . '&err=Affaire%20invalide');
            }

            return Response::redirect('clients/show?clientId=' . max(0, $clientId) . '&err=Affaire%20invalide');
        }
        if (!in_array($newStatus, $allowedStatuses, true)) {
            if ($fromProjects) {
                return Response::redirect('projects?tab=' . rawurlencode($returnTab) . '&err=Statut%20invalide');
            }

            return Response::redirect('clients/show?clientId=' . max(0, $clientId) . '&err=Statut%20invalide');
        }
        if ($reason === '') {
            if ($fromProjects) {
                return Response::redirect('projects?tab=' . rawurlencode($returnTab) . '&err=Raison%20requise');
            }

            return Response::redirect('clients/show?clientId=' . max(0, $clientId) . '&err=Raison%20requise');
        }

        try {
            (new ProjectRepository())->updateStatusAndReason(
                companyId: $userContext->companyId,
                projectId: $projectId,
                status: $newStatus,
                reason: $reason
            );
        } catch (\Throwable) {
            if ($fromProjects) {
                return Response::redirect('projects?tab=' . rawurlencode($returnTab) . '&err=Impossible%20de%20mettre%20%C3%A0%20jour');
            }

            return Response::redirect('clients/show?clientId=' . max(0, $clientId) . '&err=Impossible%20de%20mettre%20%C3%A0%20jour');
        }

        Csrf::rotate();
        $msg = $newStatus === 'cancelled' ? 'Affaire%20annul%C3%A9e' : 'Refus%20client%20enregistr%C3%A9';
        if ($fromProjects) {
            return Response::redirect('projects?tab=' . rawurlencode($returnTab) . '&msg=' . $msg);
        }

        return Response::redirect('clients/show?clientId=' . max(0, $clientId) . '&msg=' . $msg);
    }

    public function sendQuote(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('quote.read', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }

        $projectId = (int) $request->getBodyParam('project_id', 0);
        $quoteId = (int) $request->getBodyParam('quote_id', 0);
        if ($projectId <= 0 || $quoteId <= 0) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Devis%20invalide');
        }

        if (!$this->sendQuoteEmailInternal($userContext->companyId, $projectId, $quoteId)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&quoteVersionId=' . $quoteId . '&err=Envoi%20email%20impossible');
        }

        Csrf::rotate();
        return Response::redirect('projects/show?projectId=' . $projectId . '&quoteVersionId=' . $quoteId . '&msg=Devis%20envoy%C3%A9%20par%20email');
    }

    public function validateQuote(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('quote.read', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }
        $projectId = (int) $request->getBodyParam('project_id', 0);
        $quoteId = (int) $request->getBodyParam('quote_id', 0);
        if ($projectId <= 0 || $quoteId <= 0) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Devis%20invalide');
        }
        $proofRequired = (string) ((new SmtpSettingsRepository())->getByCompanyId($userContext->companyId)['proof_required'] ?? '0') === '1';

        $proofRelativePath = null;
        if ($proofRequired) {
            $saved = $this->saveQuoteProofDocument(
                companyId: $userContext->companyId,
                quoteId: $quoteId,
            );
            if ($saved === null) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&quoteVersionId=' . $quoteId . '&err=Preuve%20de%20commande%20requise%20%28PDF%20ou%20image%29');
            }
            $proofRelativePath = $saved;
        }

        $quoteRepo = new QuoteRepository();
        $quoteRow = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $quoteId);
        if (!is_array($quoteRow) || (int) ($quoteRow['projectId'] ?? 0) !== $projectId) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Devis%20invalide%20pour%20cette%20affaire');
        }

        $quotes = $quoteRepo->listByCompanyIdAndProjectId($userContext->companyId, $projectId, null, 300);
        $hasAccepted = false;
        foreach ($quotes as $q) {
            if ((string) ($q['status'] ?? '') === 'accepte') {
                $hasAccepted = true;
                break;
            }
        }
        if ($hasAccepted) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&quoteVersionId=' . $quoteId . '&err=Un%20devis%20est%20deja%20valide');
        }
        $invoiceDueRaw = trim((string) $request->getBodyParam('invoice_due_date', ''));
        try {
            $now = new \DateTimeImmutable('now');
            $quoteRepo->markQuoteAsAcceptedWithProofPath(
                companyId: $userContext->companyId,
                quoteId: $quoteId,
                now: $now,
                proofFilePathRelative: $proofRelativePath,
            );
            (new ProjectRepository())->markWaitingPlanning($userContext->companyId, $projectId);
            $this->createDraftInvoiceForAcceptedQuoteIfMissing(
                companyId: $userContext->companyId,
                projectId: $projectId,
                quoteId: $quoteId,
                userId: (int) $userContext->userId,
                dueDateYmd: $invoiceDueRaw,
            );
        } catch (\Throwable) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&quoteVersionId=' . $quoteId . '&err=Validation%20impossible');
        }
        Csrf::rotate();
        return Response::redirect('projects/show?projectId=' . $projectId . '&quoteVersionId=' . $quoteId . '&msg=Devis%20valide%2C%20affaire%20en%20attente%20de%20planification');
    }

    /**
     * Crée une facture brouillon liée au devis accepté (une seule fois par devis).
     */
    private function createDraftInvoiceForAcceptedQuoteIfMissing(
        int $companyId,
        int $projectId,
        int $quoteId,
        int $userId,
        string $dueDateYmd,
    ): void {
        $invoiceRepo = new InvoiceRepository();
        if ($invoiceRepo->existsByCompanyIdAndQuoteId($companyId, $quoteId)) {
            return;
        }
        $quoteRepo = new QuoteRepository();
        $q = $quoteRepo->findByCompanyIdAndId($companyId, $quoteId);
        if (!is_array($q) || (int) ($q['projectId'] ?? 0) !== $projectId) {
            return;
        }
        if ((string) ($q['status'] ?? '') !== 'accepte') {
            return;
        }

        $due = trim($dueDateYmd);
        if ($due !== '') {
            try {
                $due = (new \DateTimeImmutable($due))->format('Y-m-d');
            } catch (\Throwable) {
                $due = '';
            }
        }
        if ($due === '') {
            $due = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d');
        }

        $totals = InvoiceAmountsService::fromQuote($companyId, $quoteId);
        $invoiceRepo->createInvoiceFromQuote(
            companyId: $companyId,
            quoteId: $quoteId,
            clientId: (int) ($q['clientId'] ?? 0),
            invoiceNumber: null,
            title: (string) (($q['title'] ?? '') !== '' ? $q['title'] : ('Facture affaire #' . $projectId)),
            dueDateYmd: $due,
            status: 'brouillon',
            amountTotal: $totals['ttc'],
            createdByUserId: $userId,
            notes: 'Facture générée automatiquement à l\'acceptation du devis.'
        );
    }

    /**
     * Enregistre la preuve de commande uploadée. Retourne le chemin relatif web ou null si échec / fichier manquant.
     */
    private function saveQuoteProofDocument(int $companyId, int $quoteId): ?string
    {
        if (!isset($_FILES['proof_document']) || !is_array($_FILES['proof_document'])) {
            return null;
        }
        $file = $_FILES['proof_document'];
        if (!isset($file['error']) || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $maxBytes = 10 * 1024 * 1024;
        $size = isset($file['size']) && is_int($file['size']) ? $file['size'] : 0;
        if ($size <= 0 || $size > $maxBytes) {
            return null;
        }
        $tmpPath = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: '';
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return null;
        }
        $ext = $allowed[$mime];
        $fileName = 'preuve_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $appRoot = dirname(__DIR__, 3);
        $storageDir = $appRoot . '/public/storage/uploads/' . $companyId . '/quote-proofs/' . $quoteId . '/';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $destPath = $storageDir . $fileName;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            return null;
        }

        return '/public/storage/uploads/' . $companyId . '/quote-proofs/' . $quoteId . '/' . $fileName;
    }

    public function planifyProject(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('planning.create', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }
        $projectId = (int) $request->getBodyParam('project_id', 0);
        $start = trim((string) $request->getBodyParam('planned_start_date', ''));
        $end = trim((string) $request->getBodyParam('planned_end_date', ''));
        $addr = trim((string) $request->getBodyParam('site_address', ''));
        $city = trim((string) $request->getBodyParam('site_city', ''));
        $postal = trim((string) $request->getBodyParam('site_postal_code', ''));
        $invoiceDueRaw = trim((string) $request->getBodyParam('invoice_due_date', ''));
        if ($projectId <= 0 || $start === '' || $end === '') {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Informations%20de%20planification%20invalides');
        }
        try {
            (new ProjectRepository())->planProject(
                companyId: $userContext->companyId,
                projectId: $projectId,
                startDateYmd: $start,
                endDateYmd: $end,
                siteAddress: $addr !== '' ? $addr : null,
                siteCity: $city !== '' ? $city : null,
                sitePostalCode: $postal !== '' ? $postal : null
            );
            (new PlanningRepository())->createPlanningEntry(
                companyId: $userContext->companyId,
                projectId: $projectId,
                taskId: null,
                userId: null,
                entryType: 'task',
                title: 'Planification initiale',
                notes: 'Planification automatique depuis validation devis',
                startAt: new \DateTimeImmutable($start . ' 08:00:00'),
                endAt: new \DateTimeImmutable($end . ' 17:00:00'),
                createdByUserId: $userContext->userId
            );
        } catch (\Throwable) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Planification%20impossible');
        }
        Csrf::rotate();
        return Response::redirect('planning?projectId=' . $projectId . '&msg=Affaire%20planifiee');
    }

    /** @return array{0:string,1:string,2:string} [startYmd, endYmd, mode] */
    private function resolveRentabilityPeriod(Request $request): array
    {
        $mode = trim((string) $request->getQueryParam('period', 'month'));
        if (!in_array($mode, ['month', 'year', 'current'], true)) {
            $mode = 'month';
        }
        try {
            $now = new \DateTimeImmutable('today');
        } catch (\Throwable) {
            return [date('Y-m-01'), date('Y-m-t'), 'month'];
        }
        if ($mode === 'current') {
            return [$now->format('Y-m-01'), $now->format('Y-m-t'), $mode];
        }
        if ($mode === 'year') {
            $y = (int) $request->getQueryParam('year', (int) $now->format('Y'));
            $y = max(2000, min(2100, $y));

            return [sprintf('%04d-01-01', $y), sprintf('%04d-12-31', $y), $mode];
        }
        $ymRaw = trim((string) $request->getQueryParam('month', $now->format('Y-m')));
        $d = \DateTimeImmutable::createFromFormat('!Y-m', $ymRaw) ?: $now;

        return [$d->format('Y-m-01'), $d->format('Y-m-t'), $mode];
    }

    private static function parseMoneyAmount(mixed $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $s = str_replace(["\u{00A0}", ' '], '', trim((string) $raw));
        $s = str_replace(',', '.', $s);
        if ($s === '' || !is_numeric($s)) {
            return null;
        }

        return round(max(0.0, (float) $s), 2);
    }

    public function rentabilityDashboard(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('project.read', $userContext->permissions, true)) {
            return $this->renderPage('projects/rentability_index.php', [
                'pageTitle' => 'Rentabilité des chantiers',
                'permissionDenied' => true,
            ]);
        }

        [$d1, $d2, $periodMode] = $this->resolveRentabilityPeriod($request);
        $repo = new ProjectRepository();
        $kpis = ['ca' => 0.0, 'benefice' => 0.0];
        $pendingCount = 0;
        $pending = [];
        $history = [];
        try {
            $kpis = $repo->aggregateRentabilityKpi($userContext->companyId, $d1, $d2);
            $pendingCount = $repo->countRentabilityToFill($userContext->companyId);
            $sub = trim((string) $request->getQueryParam('sub', 'a_renseigner'));
            $subTab = $sub === 'historique' ? 'historique' : 'a_renseigner';
            if ($subTab === 'a_renseigner') {
                $pending = $repo->listRentabilityToFill($userContext->companyId, 250);
            } else {
                $history = $repo->listRentabilityHistory($userContext->companyId, 250);
            }
        } catch (\Throwable) {
            // garder valeurs vides
        }

        return $this->renderPage('projects/rentability_index.php', [
            'pageTitle' => 'Rentabilité des chantiers',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'kpiCa' => $kpis['ca'],
            'kpiBenefice' => $kpis['benefice'],
            'pendingCount' => $pendingCount,
            'periodStart' => $d1,
            'periodEnd' => $d2,
            'periodMode' => $periodMode,
            'subTab' => $subTab,
            'pendingRows' => $pending,
            'historyRows' => $history,
            'canUpdateProject' => in_array('project.update', $userContext->permissions, true),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function rentabilityForm(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('project.read', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Acces%20refuse');
        }
        $projectId = (int) $request->getQueryParam('projectId', 0);
        if ($projectId <= 0) {
            return Response::redirect('projects/rentability?err=Affaire%20invalide');
        }
        $repo = new ProjectRepository();
        $project = $repo->findByCompanyIdAndId($userContext->companyId, $projectId);
        if (!is_array($project)) {
            return Response::redirect('projects/rentability?err=Affaire%20introuvable');
        }
        if (!ProjectRentabilityService::isProjectTerminated($project)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Affaire%20non%20terminee');
        }
        $company = (new CompanyRepository())->findById($userContext->companyId) ?: [];
        $workH = is_numeric($company['workHoursPerDay'] ?? null) ? (float) $company['workHoursPerDay'] : 8.0;
        $timeRepo = new ProjectTimeEntryRepository();
        $entries = [];
        $users = [];
        try {
            $entries = $timeRepo->listByCompanyIdAndProjectId($userContext->companyId, $projectId);
            $users = (new \Modules\Users\Repositories\UserAdminRepository())->listBasicByCompanyId($userContext->companyId);
        } catch (\Throwable) {
            $entries = [];
            $users = [];
        }
        $rentService = new ProjectRentabilityService();
        $labor = $rentService->laborCostAmount($userContext->companyId, $projectId);
        $mat = is_numeric($project['coutMateriauxTotal'] ?? null) ? (float) $project['coutMateriauxTotal'] : 0.0;
        $mont = isset($project['montantFactureHt']) && is_numeric($project['montantFactureHt']) ? (float) $project['montantFactureHt'] : null;
        $preview = $rentService->computeTotals($mont, $mat, $labor);

        return $this->renderPage('projects/rentability_form.php', [
            'pageTitle' => 'Rentabilité — ' . (string) ($project['name'] ?? ''),
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'project' => $project,
            'workHoursPerDay' => $workH,
            'timeEntries' => $entries,
            'companyUsers' => $users,
            'laborCost' => $labor,
            'previewBenefice' => $preview['beneficeTotal'],
            'previewMarge' => $preview['margePercent'],
            'canSave' => in_array('project.update', $userContext->permissions, true),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function rentabilitySave(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('project.update', $userContext->permissions, true)) {
            return Response::redirect('projects/rentability?err=Permissions%20insuffisantes');
        }
        if (!Csrf::verify(is_string($request->getBodyParam('csrf_token', null)) ? (string) $request->getBodyParam('csrf_token', null) : null)) {
            return Response::redirect('projects/rentability?err=Requete%20invalide');
        }
        $projectId = (int) $request->getBodyParam('project_id', 0);
        if ($projectId <= 0) {
            return Response::redirect('projects/rentability?err=Affaire%20invalide');
        }
        $repo = new ProjectRepository();
        $project = $repo->findByCompanyIdAndId($userContext->companyId, $projectId);
        if (!is_array($project) || !ProjectRentabilityService::isProjectTerminated($project)) {
            return Response::redirect('projects/rentability?err=Affaire%20invalide');
        }
        $mont = self::parseMoneyAmount($request->getBodyParam('montant_facture_ht', null));
        if ($mont === null) {
            return Response::redirect('projects/rentability/form?projectId=' . $projectId . '&err=Montant%20facture%20HT%20requis');
        }
        $matRaw = self::parseMoneyAmount($request->getBodyParam('cout_materiaux_total', '0'));
        $mat = $matRaw ?? 0.0;

        $company = (new CompanyRepository())->findById($userContext->companyId) ?: [];
        $workH = is_numeric($company['workHoursPerDay'] ?? null) ? (float) $company['workHoursPerDay'] : 8.0;

        $uids = $request->getBodyParam('te_user_id', []);
        $dates = $request->getBodyParam('te_date', []);
        $daysArr = $request->getBodyParam('te_days', []);
        $hoursArr = $request->getBodyParam('te_hours', []);
        $minsArr = $request->getBodyParam('te_minutes', []);
        if (!is_array($uids)) {
            $uids = [];
        }
        if (!is_array($dates)) {
            $dates = [];
        }
        if (!is_array($daysArr)) {
            $daysArr = [];
        }
        if (!is_array($hoursArr)) {
            $hoursArr = [];
        }
        if (!is_array($minsArr)) {
            $minsArr = [];
        }

        $n = max(count($uids), count($dates), count($daysArr), count($hoursArr), count($minsArr));
        $allowedUser = [];
        try {
            foreach ((new \Modules\Users\Repositories\UserAdminRepository())->listBasicByCompanyId($userContext->companyId) as $u) {
                $allowedUser[(int) ($u['id'] ?? 0)] = true;
            }
        } catch (\Throwable) {
            $allowedUser = [];
        }

        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $uid = isset($uids[$i]) && is_numeric($uids[$i]) ? (int) $uids[$i] : 0;
            $d = isset($dates[$i]) ? trim((string) $dates[$i]) : '';
            if ($uid <= 0 || !isset($allowedUser[$uid]) || $d === '') {
                continue;
            }
            if (\DateTimeImmutable::createFromFormat('Y-m-d', $d) === false) {
                return Response::redirect('projects/rentability/form?projectId=' . $projectId . '&err=Date%20invalide');
            }
            $min = ProjectRentabilityService::resolveDurationMinutes(
                $daysArr[$i] ?? '',
                $hoursArr[$i] ?? 0,
                $minsArr[$i] ?? 0,
                $workH,
            );
            if ($min <= 0) {
                continue;
            }
            $rows[] = ['userId' => $uid, 'assignmentDate' => $d, 'durationMinutes' => $min];
        }

        $pdo = \Core\Database\Connection::pdo();
        $timeRepo = new ProjectTimeEntryRepository();
        $rentService = new ProjectRentabilityService();

        try {
            $pdo->beginTransaction();
            $timeRepo->deleteByCompanyIdAndProjectId($userContext->companyId, $projectId);
            $timeRepo->insertMany($userContext->companyId, $projectId, $rows);
            $labor = $rentService->laborCostAmount($userContext->companyId, $projectId);
            $tot = $rentService->computeTotals($mont, $mat, $labor);
            $repo->saveRentabilityFigures($userContext->companyId, $projectId, [
                'montantFactureHt' => $mont,
                'coutMateriauxTotal' => $mat,
                'beneficeTotal' => $tot['beneficeTotal'],
                'margePercent' => $tot['margePercent'],
            ]);
            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return Response::redirect('projects/rentability/form?projectId=' . $projectId . '&err=Enregistrement%20impossible');
        }

        Csrf::rotate();

        return Response::redirect('projects/rentability?sub=historique&msg=Rentabilite%20enregistree');
    }

    public function completeAffaire(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('project.update', $userContext->permissions, true)) {
            return Response::redirect('projects/show?projectId=' . (int) $request->getBodyParam('project_id', 0) . '&err=Permissions');
        }
        if (!Csrf::verify(is_string($request->getBodyParam('csrf_token', null)) ? (string) $request->getBodyParam('csrf_token', null) : null)) {
            $pidEarly = (int) $request->getBodyParam('project_id', 0);

            return Response::redirect('projects/show?projectId=' . max(0, $pidEarly) . '&err=Requete%20invalide');
        }
        $projectId = (int) $request->getBodyParam('project_id', 0);
        $dateFin = trim((string) $request->getBodyParam('actual_end_date', ''));
        if ($projectId <= 0 || $dateFin === '' || \DateTimeImmutable::createFromFormat('Y-m-d', $dateFin) === false) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Date%20de%20fin%20invalide');
        }
        try {
            (new ProjectRepository())->completeProject($userContext->companyId, $projectId, $dateFin);
        } catch (\Throwable) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Impossible%20de%20terminer');
        }
        Csrf::rotate();

        return Response::redirect('projects/show?projectId=' . $projectId . '&msg=Affaire%20terminee');
    }

    private function sendQuoteEmailInternal(int $companyId, int $projectId, int $quoteId): bool
    {
        try {
            $project = (new ProjectRepository())->findByCompanyIdAndId($companyId, $projectId);
            $quoteRepo = new QuoteRepository();
            $quote = $quoteRepo->findByCompanyIdAndId($companyId, $quoteId);
            if (!is_array($project) || !is_array($quote)) {
                return false;
            }
            $projectQuotes = $quoteRepo->listByCompanyIdAndProjectId($companyId, $projectId, null, 300);
            foreach ($projectQuotes as $pq) {
                if ((string) ($pq['status'] ?? '') === 'accepte' && (int) ($pq['id'] ?? 0) !== $quoteId) {
                    return false;
                }
            }
            $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($quote['clientId'] ?? 0));
            $toEmail = trim((string) ($client['email'] ?? ''));
            if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }

            $items = $quoteRepo->listItemsByCompanyIdAndQuoteId($companyId, $quoteId);
            $projectNotes = (string) ($project['notes'] ?? '');
            $contact = null;
            if (preg_match('/\[CONTACT_ID:([0-9]+)\]/', $projectNotes, $m)) {
                $contact = (new ContactRepository())->findByCompanyIdAndId($companyId, (int) $m[1]);
            }
            $totAgg = DocumentTotalsService::aggregate($items);
            $totalHt = $totAgg['ht'];
            $vatRate = $totAgg['vat_rate'];
            $vatAmount = $totAgg['vat_amount'];
            $totalTtc = $totAgg['ttc'];

            $token = (new QuoteShareRepository())->createOrRefresh(
                companyId: $companyId,
                quoteId: $quoteId,
                expiresAt: (new \DateTimeImmutable('now'))->modify('+90 days')
            );
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
            $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            $basePath = ($basePath === '.' || $basePath === '\\') ? '' : $basePath;
            $quoteLink = $scheme . '://' . $host . $basePath . '/quotes/view?token=' . urlencode($token);
            $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);
            $companyIdentity = (new CompanyRepository())->getDocumentIdentity($companyId, $smtp);
            $companyName = (string) $companyIdentity['name'];

            $viewData = [
                'project' => $project,
                'quote' => $quote,
                'client' => $client ?? [],
                'contact' => $contact ?? [],
                'company' => [
                    'name' => $companyIdentity['name'],
                    'email' => $companyIdentity['email'],
                    'billing_email' => $companyIdentity['billing_email'],
                ],
                'items' => $items,
                'totalHt' => $totalHt,
                'vatRate' => $vatRate,
                'vatAmount' => $vatAmount,
                'totalTtc' => $totalTtc,
                'vatByRate' => $totAgg['vat_by_rate'],
                'quoteLink' => $quoteLink,
            ];
            $viewsRoot = dirname(__DIR__, 3) . '/app/views';
            $pdfHtml = View::render($viewsRoot . '/quotes/pdf.php', $viewData);
            $subjectTpl = (string) ($smtp['quote_email_subject'] ?? 'Votre devis {{quote_number}}');
            $bodyTpl = (string) ($smtp['quote_email_body'] ?? "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe (PDF).\nVous pouvez aussi le consulter en ligne : {{quote_link}}\n\nCordialement,\n{{company_name}}");
            $repl = [
                '{{company_name}}' => $companyName,
                '{{client_name}}' => (string) (($client['name'] ?? '') ?: ($project['clientName'] ?? 'Client')),
                '{{quote_number}}' => (string) ($quote['quoteNumber'] ?? ('DEV-' . $quoteId)),
                '{{quote_title}}' => (string) ($quote['title'] ?? ''),
                '{{quote_total_ht}}' => number_format($totalHt, 2, ',', ' ') . ' EUR',
                '{{quote_link}}' => $quoteLink,
            ];
            $subject = strtr($subjectTpl, $repl);
            $body = strtr($bodyTpl, $repl);

            $delivery = new QuoteDeliveryService();
            $pdfContent = $delivery->buildPdf($pdfHtml);
            $delivery->sendQuoteEmail(
                companyId: $companyId,
                toEmail: $toEmail,
                subject: $subject,
                bodyText: $body,
                pdfContent: $pdfContent,
                pdfFileName: 'devis-' . $quoteId . '.pdf'
            );
            $quoteRepo->markQuoteAsSent($companyId, $quoteId, new \DateTimeImmutable('now'));
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function publicQuoteView(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getQueryParam('token', ''));
        if ($token === '') {
            return new Response('Lien invalide', 404);
        }
        $tokenRow = (new QuoteShareRepository())->findByToken($token);
        if (!is_array($tokenRow)) {
            return new Response('Lien expiré ou invalide', 404);
        }

        $companyId = (int) ($tokenRow['companyId'] ?? 0);
        $quoteId = (int) ($tokenRow['quoteId'] ?? 0);
        $quoteRepo = new QuoteRepository();
        $quote = $quoteRepo->findByCompanyIdAndId($companyId, $quoteId);
        if (!is_array($quote)) {
            return new Response('Devis introuvable', 404);
        }
        $items = $quoteRepo->listItemsByCompanyIdAndQuoteId($companyId, $quoteId);
        $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($quote['clientId'] ?? 0));
        $project = null;
        if (is_numeric($quote['projectId'] ?? null) && (int) $quote['projectId'] > 0) {
            $project = (new ProjectRepository())->findByCompanyIdAndId($companyId, (int) $quote['projectId']);
        }
        $contact = null;
        if (is_array($project)) {
            $notes = (string) ($project['notes'] ?? '');
            if (preg_match('/\[CONTACT_ID:([0-9]+)\]/', $notes, $m)) {
                $contact = (new ContactRepository())->findByCompanyIdAndId($companyId, (int) $m[1]);
            }
        }
        $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);
        $companyIdentity = (new CompanyRepository())->getDocumentIdentity($companyId, $smtp);
        $companyInfo = [
            'name' => $companyIdentity['name'],
            'email' => $companyIdentity['email'],
            'billing_email' => $companyIdentity['billing_email'],
        ];

        $totAgg = DocumentTotalsService::aggregate($items);
        $totalHt = $totAgg['ht'];
        $vatRate = $totAgg['vat_rate'];
        $vatAmount = $totAgg['vat_amount'];
        $totalTtc = $totAgg['ttc'];

        $viewsRoot = dirname(__DIR__, 3) . '/app/views';
        $html = View::render($viewsRoot . '/quotes/public_view.php', [
            'quote' => $quote,
            'items' => $items,
            'client' => $client ?? [],
            'contact' => $contact ?? [],
            'company' => $companyInfo,
            'token' => $token,
            'vatRate' => $vatRate,
            'totalHt' => $totalHt,
            'vatAmount' => $vatAmount,
            'totalTtc' => $totalTtc,
            'vatByRate' => $totAgg['vat_by_rate'],
        ]);
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function requestQuoteSignatureCode(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getBodyParam('token', ''));
        if ($token === '') return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Lien%20invalide');
        $tokenRow = (new QuoteShareRepository())->findByToken($token);
        if (!is_array($tokenRow)) return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Lien%20invalide');
        $companyId = (int) ($tokenRow['companyId'] ?? 0);
        $quoteId = (int) ($tokenRow['quoteId'] ?? 0);
        $quote = (new QuoteRepository())->findByCompanyIdAndId($companyId, $quoteId);
        if (!is_array($quote)) return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Devis%20introuvable');
        $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($quote['clientId'] ?? 0));
        $toEmail = '';
        $contact = null;
        $projectId = is_numeric($quote['projectId'] ?? null) ? (int) $quote['projectId'] : 0;
        if ($projectId > 0) {
            $project = (new ProjectRepository())->findByCompanyIdAndId($companyId, $projectId);
            if (is_array($project)) {
                $notes = (string) ($project['notes'] ?? '');
                if (preg_match('/\[CONTACT_ID:([0-9]+)\]/', $notes, $m)) {
                    $contact = (new ContactRepository())->findByCompanyIdAndId($companyId, (int) $m[1]);
                    $toEmail = trim((string) ($contact['email'] ?? ''));
                }
            }
        }
        if ($toEmail === '') {
            $toEmail = trim((string) ($client['email'] ?? ''));
        }
        if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Email%20client%20invalide');
        }
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = new \DateTimeImmutable('now +10 minutes');
        try {
            (new QuoteSignatureRepository())->createCode($companyId, $quoteId, $toEmail, $otp, $expiresAt);
            (new QuoteDeliveryService())->sendTestEmail(
                companyId: $companyId,
                toEmail: $toEmail,
                subject: 'Code de signature devis',
                bodyText: "Votre code de signature est: {$otp}\nCe code est valable 10 minutes."
            );
        } catch (\Throwable) {
            return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Impossible%20d%27envoyer%20le%20code');
        }
        return Response::redirect('quotes/view?token=' . urlencode($token) . '&msg=' . rawurlencode('Code envoyé'));
    }

    public function confirmQuoteSignature(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getBodyParam('token', ''));
        $otp = trim((string) $request->getBodyParam('signature_code', ''));
        if ($token === '' || $otp === '') return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Code%20invalide');
        $tokenRow = (new QuoteShareRepository())->findByToken($token);
        if (!is_array($tokenRow)) return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Lien%20invalide');
        $companyId = (int) ($tokenRow['companyId'] ?? 0);
        $quoteId = (int) ($tokenRow['quoteId'] ?? 0);
        $quote = (new QuoteRepository())->findByCompanyIdAndId($companyId, $quoteId);
        if (!is_array($quote)) return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Devis%20introuvable');
        $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($quote['clientId'] ?? 0));
        $toEmail = '';
        $contact = null;
        $projectId = is_numeric($quote['projectId'] ?? null) ? (int) $quote['projectId'] : 0;
        if ($projectId > 0) {
            $project = (new ProjectRepository())->findByCompanyIdAndId($companyId, $projectId);
            if (is_array($project)) {
                $notes = (string) ($project['notes'] ?? '');
                if (preg_match('/\[CONTACT_ID:([0-9]+)\]/', $notes, $m)) {
                    $contact = (new ContactRepository())->findByCompanyIdAndId($companyId, (int) $m[1]);
                    $toEmail = trim((string) ($contact['email'] ?? ''));
                }
            }
        }
        if ($toEmail === '') {
            $toEmail = trim((string) ($client['email'] ?? ''));
        }
        if ($toEmail === '') return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Email%20client%20invalide');
        $ok = (new QuoteSignatureRepository())->verifyAndConsumeCode($companyId, $quoteId, $toEmail, $otp, new \DateTimeImmutable('now'));
        if (!$ok) return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Code%20invalide%20ou%20expire');
        try {
            $quoteRepo = new QuoteRepository();
            $quoteRepo->markQuoteAsAccepted($companyId, $quoteId, new \DateTimeImmutable('now'));
            $signedFirstName = trim((string) ($contact['firstName'] ?? ''));
            $signedLastName = trim((string) ($contact['lastName'] ?? ''));
            if ($signedFirstName === '' && $signedLastName === '') {
                $signedLastName = trim((string) ($client['name'] ?? 'Client'));
            }
            $quoteRepo->setSignatureMetadata(
                companyId: $companyId,
                quoteId: $quoteId,
                signedFirstName: $signedFirstName,
                signedLastName: $signedLastName,
                signedEmail: $toEmail,
                signedAt: new \DateTimeImmutable('now')
            );
            $projectId = is_numeric($quote['projectId'] ?? null) ? (int) $quote['projectId'] : 0;
            if ($projectId > 0) {
                (new ProjectRepository())->markWaitingPlanning($companyId, $projectId);
                $this->createDraftInvoiceForAcceptedQuoteIfMissing(
                    companyId: $companyId,
                    projectId: $projectId,
                    quoteId: $quoteId,
                    userId: (int) ($userContext->userId ?? 0),
                    dueDateYmd: '',
                );
            }
        } catch (\Throwable) {
            return Response::redirect('quotes/view?token=' . urlencode($token) . '&err=Signature%20enregistree%20mais%20validation%20devis%20echouee');
        }
        return Response::redirect('quotes/view?token=' . urlencode($token) . '&msg=' . rawurlencode('Signature enregistrée — le devis est accepté.'));
    }

    public function downloadSignedQuotePdf(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getQueryParam('token', ''));
        if ($token === '') return new Response('Lien invalide', 404);
        $tokenRow = (new QuoteShareRepository())->findByToken($token);
        if (!is_array($tokenRow)) return new Response('Lien invalide', 404);
        $companyId = (int) ($tokenRow['companyId'] ?? 0);
        $quoteId = (int) ($tokenRow['quoteId'] ?? 0);
        $quoteRepo = new QuoteRepository();
        $quote = $quoteRepo->findByCompanyIdAndId($companyId, $quoteId);
        if (!is_array($quote)) return new Response('Devis introuvable', 404);
        $items = $quoteRepo->listItemsByCompanyIdAndQuoteId($companyId, $quoteId);
        $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($quote['clientId'] ?? 0));
        $project = is_numeric($quote['projectId'] ?? null) ? (new ProjectRepository())->findByCompanyIdAndId($companyId, (int) $quote['projectId']) : null;
        $contact = null;
        if (is_array($project)) {
            $notes = (string) ($project['notes'] ?? '');
            if (preg_match('/\[CONTACT_ID:([0-9]+)\]/', $notes, $m)) {
                $contact = (new ContactRepository())->findByCompanyIdAndId($companyId, (int) $m[1]);
            }
        }
        $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);
        $totAgg = DocumentTotalsService::aggregate($items);
        $totalHt = $totAgg['ht'];
        $vatRate = $totAgg['vat_rate'];
        $vatAmount = $totAgg['vat_amount'];
        $totalTtc = $totAgg['ttc'];
        $viewsRoot = dirname(__DIR__, 3) . '/app/views';
        $html = View::render($viewsRoot . '/quotes/pdf.php', [
            'quote' => $quote,
            'items' => $items,
            'client' => $client ?? [],
            'contact' => $contact ?? [],
            'company' => (new CompanyRepository())->getDocumentIdentity($companyId, $smtp),
            'vatRate' => $vatRate,
            'totalHt' => $totalHt,
            'vatAmount' => $vatAmount,
            'totalTtc' => $totalTtc,
            'vatByRate' => $totAgg['vat_by_rate'],
        ]);
        $pdf = (new QuoteDeliveryService())->buildPdf($html);
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="devis-signe-' . $quoteId . '.pdf"',
        ]);
    }
}

