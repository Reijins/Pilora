<?php
declare(strict_types=1);

namespace Modules\Clients\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Clients\Repositories\ClientRepository;
use Modules\Contacts\Repositories\ContactRepository;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Projects\Repositories\ProjectRepository;
use Modules\Quotes\Repositories\QuoteRepository;

final class ClientsController extends BaseController
{
    private function extractMetaFromNotes(?string $notes): array
    {
        $raw = (string) ($notes ?? '');
        $meta = ['clientType' => 'entreprise', 'siret' => '', 'firstName' => '', 'createContactWithClient' => false];
        if (preg_match('/\[CLIENT_TYPE:([a-z_]+)\]/i', $raw, $m)) {
            $meta['clientType'] = strtolower((string) $m[1]) === 'particulier' ? 'particulier' : 'entreprise';
        }
        if (preg_match('/\[SIRET:([0-9]{14})\]/', $raw, $m)) {
            $meta['siret'] = (string) $m[1];
        }
        if (preg_match('/\[FIRST_NAME:([^\]]+)\]/', $raw, $m)) {
            $meta['firstName'] = trim((string) $m[1]);
        }
        if (str_contains($raw, '[CREATE_CONTACT:1]')) {
            $meta['createContactWithClient'] = true;
        }
        return $meta;
    }

    private function buildNotesWithMeta(?string $notes, string $clientType, ?string $siret, ?string $firstName, bool $createContactWithClient): ?string
    {
        $clean = trim((string) ($notes ?? ''));
        $clean = preg_replace('/\[CLIENT_TYPE:[^\]]+\]/i', '', $clean ?? '') ?? '';
        $clean = preg_replace('/\[SIRET:[^\]]+\]/i', '', $clean) ?? '';
        $clean = preg_replace('/\[FIRST_NAME:[^\]]+\]/i', '', $clean) ?? '';
        $clean = preg_replace('/\[CREATE_CONTACT:[^\]]+\]/i', '', $clean) ?? '';
        $prefix = '[CLIENT_TYPE:' . ($clientType === 'particulier' ? 'particulier' : 'entreprise') . ']';
        if ($siret !== null && trim($siret) !== '') {
            $prefix .= '[SIRET:' . trim($siret) . ']';
        }
        if ($firstName !== null && trim($firstName) !== '') {
            $prefix .= '[FIRST_NAME:' . trim($firstName) . ']';
        }
        if ($createContactWithClient) {
            $prefix .= '[CREATE_CONTACT:1]';
        }
        $final = trim($prefix . ' ' . $clean);
        return $final !== '' ? $final : null;
    }
    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canRead = in_array('client.read', $userContext->permissions, true);
        if (!$canRead) {
            return $this->renderPage('clients/index.php', [
                'pageTitle' => 'Clients',
                'permissionDenied' => true,
            ]);
        }

        $q = $request->getQueryParam('q', null);

        $canCreateClient = in_array('client.create', $userContext->permissions, true);
        $canCreateQuote = in_array('quote.create', $userContext->permissions, true);

        $repo = new ClientRepository();
        try {
            $clients = $repo->searchByCompanyId(
                companyId: $userContext->companyId,
                query: is_string($q) ? $q : null,
            );
        } catch (\Throwable) {
            $clients = [];
        }

        return $this->renderPage('clients/index.php', [
            'pageTitle' => 'Clients',
            'permissionDenied' => false,
            'searchQuery' => is_string($q) ? $q : '',
            'clients' => $clients,
            'canCreateClient' => $canCreateClient,
            'canCreateQuote' => $canCreateQuote,
        ]);
    }

    public function new(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canCreateClient = in_array('client.create', $userContext->permissions, true);
        if (!$canCreateClient) {
            return $this->renderPage('clients/new.php', [
                'pageTitle' => 'Nouveau client',
                'permissionDenied' => true,
                'csrfToken' => Csrf::token(),
            ]);
        }

        return $this->renderPage('clients/new.php', [
            'pageTitle' => 'Nouveau client',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'clientType' => 'entreprise',
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients');
        }

        if (!in_array('client.create', $userContext->permissions, true)) {
            return Response::redirect('clients');
        }

        $name = trim((string) $request->getBodyParam('name', ''));
        $phone = trim((string) $request->getBodyParam('phone', ''));
        $email = trim((string) $request->getBodyParam('email', ''));
        $address = trim((string) $request->getBodyParam('address', ''));
        $notes = trim((string) $request->getBodyParam('notes', ''));
        $clientType = trim((string) $request->getBodyParam('client_type', 'entreprise'));
        if (!in_array($clientType, ['particulier', 'entreprise'], true)) {
            $clientType = 'entreprise';
        }
        $siret = trim((string) $request->getBodyParam('siret', ''));
        $firstName = trim((string) $request->getBodyParam('first_name', ''));
        $createContactWithClient = (string) $request->getBodyParam('create_contact_with_client', '0') === '1';

        if ($name === '') {
            return Response::redirect('clients?err=Nom%20obligatoire');
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return Response::redirect('clients?err=Email%20invalide');
        }
        if ($clientType === 'entreprise' && $siret !== '' && !preg_match('/^[0-9]{14}$/', $siret)) {
            return Response::redirect('clients/new?err=SIRET%20invalide%20%2814%20chiffres%29');
        }
        if ($clientType === 'particulier' && $firstName === '') {
            return Response::redirect('clients/new?err=Prenom%20requis');
        }

        $notes = (string) ($this->buildNotesWithMeta(
            notes: $notes !== '' ? $notes : null,
            clientType: $clientType,
            siret: $clientType === 'entreprise' ? ($siret !== '' ? $siret : null) : null,
            firstName: $clientType === 'particulier' ? ($firstName !== '' ? $firstName : null) : null,
            createContactWithClient: $clientType === 'particulier' && $createContactWithClient
        ) ?? '');

        $repo = new ClientRepository();
        try {
            $clientId = $repo->createClient(
                companyId: $userContext->companyId,
                name: $name,
                phone: $phone !== '' ? $phone : null,
                email: $email !== '' ? $email : null,
                address: $address !== '' ? $address : null,
                notes: $notes !== '' ? $notes : null,
            );
            if ($clientType === 'particulier' && $createContactWithClient) {
                (new ContactRepository())->create(
                    companyId: $userContext->companyId,
                    clientId: $clientId,
                    firstName: $firstName !== '' ? $firstName : null,
                    lastName: $name !== '' ? $name : null,
                    functionLabel: 'Particulier',
                    email: $email !== '' ? $email : null,
                    phone: $phone !== '' ? $phone : null,
                    notes: null
                );
            }
        } catch (\Throwable) {
            return Response::redirect('clients?err=Impossible%20de%20cr%C3%A9er%20le%20client');
        }

        Csrf::rotate();
        return Response::redirect('clients?msg=Client%20cr%C3%A9%C3%A9');
    }

    public function show(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canRead = in_array('client.read', $userContext->permissions, true);
        if (!$canRead) {
            return $this->renderPage('clients/show.php', [
                'pageTitle' => 'Fiche client',
                'permissionDenied' => true,
            ]);
        }

        $clientIdRaw = $request->getQueryParam('clientId', null);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : 0;
        if ($clientId <= 0) {
            return Response::redirect('clients?err=Client%20invalide');
        }

        $repoClients = new ClientRepository();
        $client = null;
        try {
            $client = $repoClients->findByCompanyIdAndId(
                companyId: $userContext->companyId,
                clientId: $clientId
            );
        } catch (\Throwable) {
            $client = null;
        }

        if ($client === null) {
            return Response::redirect('clients?err=Client%20introuvable');
        }

        $canViewProjects = in_array('project.read', $userContext->permissions, true);
        $canAssignTeam = in_array('project.assign_team', $userContext->permissions, true);
        $canReportRead = in_array('project.report.read', $userContext->permissions, true);
        $canPhotoRead = in_array('project.photo.read', $userContext->permissions, true);
        $canViewQuotes = in_array('quote.read', $userContext->permissions, true);
        $canViewInvoices = in_array('invoice.read', $userContext->permissions, true);
        $canCreateInvoice = in_array('invoice.create', $userContext->permissions, true);
        $canMarkPaid = in_array('invoice.mark_paid', $userContext->permissions, true);
        $canCreateQuote = in_array('quote.create', $userContext->permissions, true);
        $canCreateProject = in_array('project.create', $userContext->permissions, true);
        $canUpdateProject = in_array('project.update', $userContext->permissions, true);
        $canCreateContact = in_array('client.create', $userContext->permissions, true);

        $projects = [];
        $affaires = [];
        $contacts = [];
        $quotes = [];
        $invoices = [];

        try {
            $projectRepo = new ProjectRepository();
            $projects = $projectRepo->listByCompanyIdAndClientId(
                companyId: $userContext->companyId,
                clientId: $clientId,
                limit: 200
            );

            try {
                $affaires = $projectRepo->listAffairesByCompanyIdAndClientId(
                    companyId: $userContext->companyId,
                    clientId: $clientId,
                    limit: 200
                );
            } catch (\Throwable) {
                // Fallback robuste si schéma incomplet (ex: Quote.projectId absent).
                $affaires = array_map(
                    static fn (array $p): array => [
                        'projectId' => (int) ($p['id'] ?? 0),
                        'projectName' => (string) ($p['name'] ?? ''),
                        'projectStatus' => (string) ($p['status'] ?? ''),
                        'quotesCount' => 0,
                        'quoteAmount' => 0.0,
                        'invoicesCount' => 0,
                        'invoiceAmount' => 0.0,
                        'paidAmount' => 0.0,
                        'remainingAmount' => 0.0,
                    ],
                    $projects
                );
            }
        } catch (\Throwable) {
            $projects = [];
            $affaires = [];
        }

        try {
            // Liste contacts: dépend de clientId + companyId.
            $contacts = (new ContactRepository())->listByCompanyIdAndClientId(
                companyId: $userContext->companyId,
                clientId: $clientId
            );
        } catch (\Throwable) {
            $contacts = [];
        }

        if ($canViewQuotes) {
            try {
                $quotes = (new QuoteRepository())->listByCompanyIdAndClientId(
                    companyId: $userContext->companyId,
                    clientId: $clientId,
                    status: null,
                    limit: 200
                );
            } catch (\Throwable) {
                $quotes = [];
            }
        }

        if ($canViewInvoices) {
            try {
                $invoices = (new InvoiceRepository())->listByCompanyIdAndClientId(
                    companyId: $userContext->companyId,
                    clientId: $clientId,
                    status: null,
                    limit: 200
                );
            } catch (\Throwable) {
                $invoices = [];
            }
        }

        return $this->renderPage('clients/show.php', [
            'pageTitle' => 'Fiche client',
            'permissionDenied' => false,
            'client' => $client,
            'projects' => $projects,
            'affaires' => $affaires,
            'contacts' => $contacts,
            'quotes' => $quotes,
            'invoices' => $invoices,
            'canViewProjects' => $canViewProjects,
            'canAssignTeam' => $canAssignTeam,
            'canReportRead' => $canReportRead,
            'canPhotoRead' => $canPhotoRead,
            'canViewQuotes' => $canViewQuotes,
            'canViewInvoices' => $canViewInvoices,
            'canCreateInvoice' => $canCreateInvoice,
            'canMarkPaid' => $canMarkPaid,
            'canCreateQuote' => $canCreateQuote,
            'canCreateProject' => $canCreateProject,
            'canUpdateProject' => $canUpdateProject,
            'canCreateContact' => $canCreateContact,
            'csrfToken' => Csrf::token(),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
            'clientMeta' => $this->extractMetaFromNotes((string) ($client['notes'] ?? '')),
        ]);
    }

    public function edit(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('client.create', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }
        $clientIdRaw = $request->getQueryParam('clientId', null);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : 0;
        if ($clientId <= 0) {
            return Response::redirect('clients?err=Client%20invalide');
        }
        $client = (new ClientRepository())->findByCompanyIdAndId($userContext->companyId, $clientId);
        if (!is_array($client)) {
            return Response::redirect('clients?err=Client%20introuvable');
        }
        $clientMeta = $this->extractMetaFromNotes((string) ($client['notes'] ?? ''));
        return $this->renderPage('clients/edit.php', [
            'pageTitle' => 'Modifier le client',
            'permissionDenied' => false,
            'client' => $client,
            'clientMeta' => $clientMeta,
            'csrfToken' => Csrf::token(),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function update(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('client.create', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=Requete%20invalide');
        }
        $clientIdRaw = $request->getBodyParam('client_id', 0);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : 0;
        if ($clientId <= 0) {
            return Response::redirect('clients?err=Client%20invalide');
        }
        $name = trim((string) $request->getBodyParam('name', ''));
        $phone = trim((string) $request->getBodyParam('phone', ''));
        $email = trim((string) $request->getBodyParam('email', ''));
        $address = trim((string) $request->getBodyParam('address', ''));
        $notes = trim((string) $request->getBodyParam('notes', ''));
        $clientType = trim((string) $request->getBodyParam('client_type', 'entreprise'));
        if (!in_array($clientType, ['particulier', 'entreprise'], true)) {
            $clientType = 'entreprise';
        }
        $siret = trim((string) $request->getBodyParam('siret', ''));
        $firstName = trim((string) $request->getBodyParam('first_name', ''));
        $createContactWithClient = (string) $request->getBodyParam('create_contact_with_client', '0') === '1';
        if ($name === '') {
            return Response::redirect('clients/edit?clientId=' . $clientId . '&err=Nom%20obligatoire');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return Response::redirect('clients/edit?clientId=' . $clientId . '&err=Email%20invalide');
        }
        if ($clientType === 'entreprise' && $siret !== '' && !preg_match('/^[0-9]{14}$/', $siret)) {
            return Response::redirect('clients/edit?clientId=' . $clientId . '&err=SIRET%20invalide%20%2814%20chiffres%29');
        }
        if ($clientType === 'particulier' && $firstName === '') {
            return Response::redirect('clients/edit?clientId=' . $clientId . '&err=Prenom%20requis');
        }
        $notes = (string) ($this->buildNotesWithMeta(
            notes: $notes !== '' ? $notes : null,
            clientType: $clientType,
            siret: $clientType === 'entreprise' ? ($siret !== '' ? $siret : null) : null,
            firstName: $clientType === 'particulier' ? ($firstName !== '' ? $firstName : null) : null,
            createContactWithClient: $clientType === 'particulier' && $createContactWithClient
        ) ?? '');
        try {
            (new ClientRepository())->updateClient(
                companyId: $userContext->companyId,
                clientId: $clientId,
                name: $name,
                phone: $phone !== '' ? $phone : null,
                email: $email !== '' ? $email : null,
                address: $address !== '' ? $address : null,
                notes: $notes !== '' ? $notes : null
            );
        } catch (\Throwable) {
            return Response::redirect('clients/edit?clientId=' . $clientId . '&err=Impossible%20de%20mettre%20a%20jour');
        }
        Csrf::rotate();
        return Response::redirect('clients/show?clientId=' . $clientId . '&msg=Client%20mis%20a%20jour');
    }
}

