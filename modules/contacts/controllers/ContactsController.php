<?php
declare(strict_types=1);

namespace Modules\Contacts\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Clients\Repositories\ClientListRepository;
use Modules\Contacts\Repositories\ContactRepository;

final class ContactsController extends BaseController
{
    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canRead = in_array('client.read', $userContext->permissions, true);
        if (!$canRead) {
            return $this->renderPage('contacts/index.php', [
                'pageTitle' => 'Contacts',
                'permissionDenied' => true,
            ]);
        }

        $clientIdRaw = $request->getQueryParam('clientId', null);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : null;

        if ($clientId === null || $clientId <= 0) {
            return $this->renderPage('contacts/index.php', [
                'pageTitle' => 'Contacts',
                'permissionDenied' => false,
                'clientId' => null,
                'contacts' => [],
            ]);
        }

        $repo = new ContactRepository();
        try {
            $contacts = $repo->listByCompanyIdAndClientId(
                companyId: $userContext->companyId,
                clientId: $clientId,
            );
        } catch (\Throwable) {
            $contacts = [];
        }

        return $this->renderPage('contacts/index.php', [
            'pageTitle' => 'Contacts',
            'permissionDenied' => false,
            'clientId' => $clientId,
            'contacts' => $contacts,
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('client.create', $userContext->permissions, true)) {
            return Response::redirect('clients');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }

        $returnTo = trim((string) $request->getBodyParam('return_to', ''));
        if ($returnTo !== 'contacts') {
            $returnTo = 'client_show';
        }

        $clientIdRaw = $request->getBodyParam('client_id', null);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : 0;
        if ($clientId <= 0) {
            return Response::redirect('clients?err=Client%20invalide');
        }

        $firstName = trim((string) $request->getBodyParam('first_name', ''));
        $lastName = trim((string) $request->getBodyParam('last_name', ''));
        $functionLabel = trim((string) $request->getBodyParam('function_label', ''));
        $email = trim((string) $request->getBodyParam('email', ''));
        $phone = trim((string) $request->getBodyParam('phone', ''));

        if ($firstName === '' && $lastName === '') {
            $target = $returnTo === 'contacts' ? 'contacts/new?clientId=' . $clientId : 'clients/show?clientId=' . $clientId;
            return Response::redirect($target . '&err=Nom%20contact%20requis');
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $target = $returnTo === 'contacts' ? 'contacts/new?clientId=' . $clientId : 'clients/show?clientId=' . $clientId;
            return Response::redirect($target . '&err=Email%20contact%20invalide');
        }

        try {
            (new ContactRepository())->create(
                companyId: $userContext->companyId,
                clientId: $clientId,
                firstName: $firstName !== '' ? $firstName : null,
                lastName: $lastName !== '' ? $lastName : null,
                functionLabel: $functionLabel !== '' ? $functionLabel : null,
                email: $email !== '' ? $email : null,
                phone: $phone !== '' ? $phone : null,
                notes: null
            );
        } catch (\Throwable) {
            $target = $returnTo === 'contacts' ? 'contacts/new?clientId=' . $clientId : 'clients/show?clientId=' . $clientId;
            return Response::redirect($target . '&err=Impossible%20de%20cr%C3%A9er%20le%20contact');
        }

        Csrf::rotate();
        if ($returnTo === 'contacts') {
            return Response::redirect('contacts?clientId=' . $clientId . '&msg=Contact%20ajout%C3%A9');
        }
        return Response::redirect('clients/show?clientId=' . $clientId . '&msg=Contact%20ajout%C3%A9');
    }

    public function new(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canCreate = in_array('client.create', $userContext->permissions, true);
        if (!$canCreate) {
            return $this->renderPage('contacts/new.php', [
                'pageTitle' => 'Nouveau contact',
                'permissionDenied' => true,
            ]);
        }

        $clientIdRaw = $request->getQueryParam('clientId', null);
        $clientId = is_numeric($clientIdRaw) ? (int) $clientIdRaw : 0;

        $clients = [];
        try {
            $clients = (new ClientListRepository())->listByCompanyId($userContext->companyId, 300);
        } catch (\Throwable) {
            $clients = [];
        }

        return $this->renderPage('contacts/new.php', [
            'pageTitle' => 'Nouveau contact',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'clients' => $clients,
            'selectedClientId' => $clientId,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function edit(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('client.create', $userContext->permissions, true)) {
            return Response::redirect('clients');
        }
        $contactId = (int) $request->getQueryParam('contactId', 0);
        if ($contactId <= 0) {
            return Response::redirect('clients?err=Contact%20invalide');
        }
        $repo = new ContactRepository();
        $contact = $repo->findByCompanyIdAndId($userContext->companyId, $contactId);
        if (!is_array($contact)) {
            return Response::redirect('clients?err=Contact%20introuvable');
        }
        $clients = [];
        try {
            $clients = (new ClientListRepository())->listByCompanyId($userContext->companyId, 300);
        } catch (\Throwable) {
            $clients = [];
        }
        return $this->renderPage('contacts/edit.php', [
            'pageTitle' => 'Modifier contact',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'clients' => $clients,
            'contact' => $contact,
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
            return Response::redirect('clients');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }
        $contactId = (int) $request->getBodyParam('contact_id', 0);
        $clientId = (int) $request->getBodyParam('client_id', 0);
        $firstName = trim((string) $request->getBodyParam('first_name', ''));
        $lastName = trim((string) $request->getBodyParam('last_name', ''));
        $functionLabel = trim((string) $request->getBodyParam('function_label', ''));
        $email = trim((string) $request->getBodyParam('email', ''));
        $phone = trim((string) $request->getBodyParam('phone', ''));
        if ($contactId <= 0 || $clientId <= 0) {
            return Response::redirect('clients?err=Contact%20invalide');
        }
        if ($firstName === '' && $lastName === '') {
            return Response::redirect('contacts/edit?contactId=' . $contactId . '&err=Nom%20contact%20requis');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return Response::redirect('contacts/edit?contactId=' . $contactId . '&err=Email%20invalide');
        }
        try {
            (new ContactRepository())->update(
                companyId: $userContext->companyId,
                contactId: $contactId,
                clientId: $clientId,
                firstName: $firstName !== '' ? $firstName : null,
                lastName: $lastName !== '' ? $lastName : null,
                functionLabel: $functionLabel !== '' ? $functionLabel : null,
                email: $email !== '' ? $email : null,
                phone: $phone !== '' ? $phone : null
            );
        } catch (\Throwable) {
            return Response::redirect('contacts/edit?contactId=' . $contactId . '&err=Impossible%20de%20mettre%20a%20jour');
        }
        Csrf::rotate();
        return Response::redirect('clients/show?clientId=' . $clientId . '&msg=Contact%20mis%20a%20jour');
    }

    public function delete(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('client.create', $userContext->permissions, true)) {
            return Response::redirect('clients');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }
        $contactId = (int) $request->getBodyParam('contact_id', 0);
        $clientId = (int) $request->getBodyParam('client_id', 0);
        if ($contactId <= 0 || $clientId <= 0) {
            return Response::redirect('clients?err=Contact%20invalide');
        }
        try {
            (new ContactRepository())->delete($userContext->companyId, $contactId);
        } catch (\Throwable) {
            return Response::redirect('clients/show?clientId=' . $clientId . '&err=Suppression%20impossible');
        }
        Csrf::rotate();
        return Response::redirect('clients/show?clientId=' . $clientId . '&msg=Contact%20supprime');
    }
}

