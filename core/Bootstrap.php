<?php
declare(strict_types=1);

namespace Core;

use Core\Auth\SessionManager;
use Core\Auth\AuthenticatedUserContextFactory;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Router;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Dashboard\Controllers\DashboardController;
use Modules\Auth\Controllers\AuthController;
use Modules\Clients\Controllers\ClientsController;
use Modules\Contacts\Controllers\ContactsController;
use Modules\Hr\Controllers\HrController;
use Modules\Settings\Controllers\SettingsController;
use Modules\Invoices\Controllers\InvoicesController;
use Modules\Invoices\Controllers\PublicInvoiceController;
use Modules\Payments\Controllers\PaymentsController;
use Modules\Projects\Controllers\ProjectsController;
use Modules\Planning\Controllers\PlanningController;
use Modules\Projects\Controllers\ProjectReportsController;
use Modules\Projects\Controllers\ProjectPhotosController;
use Modules\PriceLibrary\Controllers\PriceLibraryController;
use Modules\Platform\Repositories\PackRepository;
use Modules\Platform\Controllers\PlatformController;

final class Bootstrap
{
    public function handle(): void
    {
        $vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($vendorAutoload)) {
            require $vendorAutoload;
        }

        $loader = new Autoloader();
        $loader->register();

        SessionManager::start();

        $request = Request::fromGlobals();

        $router = new Router();
        $this->registerRoutes($router);

        $handler = $router->match($request->getMethod(), $request->getPath());
        if ($handler === null) {
            (new Response('Not Found', 404))->send();
            return;
        }

        $userContext = AuthenticatedUserContextFactory::fromSession();

        $billingLockRedirect = $this->enforceBillingRestriction($request, $userContext);
        if ($billingLockRedirect instanceof Response) {
            $billingLockRedirect->send();
            return;
        }

        $result = $handler($request, $userContext);
        if ($result instanceof Response) {
            $result->send();
            return;
        }

        // Handler can return string/array for quick prototyping. We keep it strict for now.
        (new Response($result ?? '', 200))->send();
    }

    private function registerRoutes(Router $router): void
    {
        $router->get('/', function (Request $request, UserContext $userContext): Response {
            return (new DashboardController())->index($request, $userContext);
        });

        $router->get('/dashboard', function (Request $request, UserContext $userContext): Response {
            return (new DashboardController())->index($request, $userContext);
        });

        $router->get('/login', function (Request $request, UserContext $userContext): Response {
            return (new AuthController())->showLogin($request, $userContext);
        });

        $router->post('/login', function (Request $request, UserContext $userContext): Response {
            return (new AuthController())->login($request, $userContext);
        });

        $router->post('/logout', function (Request $request, UserContext $userContext): Response {
            return (new AuthController())->logout($request, $userContext);
        });

        $router->get('/profile', function (Request $request, UserContext $userContext): Response {
            return (new AuthController())->profile($request, $userContext);
        });

        $router->get('/clients', function (Request $request, UserContext $userContext): Response {
            return (new ClientsController())->index($request, $userContext);
        });

        $router->get('/clients/new', function (Request $request, UserContext $userContext): Response {
            return (new ClientsController())->new($request, $userContext);
        });

        $router->post('/clients/create', function (Request $request, UserContext $userContext): Response {
            return (new ClientsController())->create($request, $userContext);
        });

        $router->get('/clients/show', function (Request $request, UserContext $userContext): Response {
            return (new ClientsController())->show($request, $userContext);
        });

        $router->get('/clients/edit', function (Request $request, UserContext $userContext): Response {
            return (new ClientsController())->edit($request, $userContext);
        });

        $router->post('/clients/update', function (Request $request, UserContext $userContext): Response {
            return (new ClientsController())->update($request, $userContext);
        });

        $router->get('/contacts', function (Request $request, UserContext $userContext): Response {
            return (new ContactsController())->index($request, $userContext);
        });

        $router->get('/contacts/new', function (Request $request, UserContext $userContext): Response {
            return (new ContactsController())->new($request, $userContext);
        });

        $router->post('/contacts/create', function (Request $request, UserContext $userContext): Response {
            return (new ContactsController())->create($request, $userContext);
        });

        $router->get('/contacts/edit', function (Request $request, UserContext $userContext): Response {
            return (new ContactsController())->edit($request, $userContext);
        });

        $router->post('/contacts/update', function (Request $request, UserContext $userContext): Response {
            return (new ContactsController())->update($request, $userContext);
        });

        $router->post('/contacts/delete', function (Request $request, UserContext $userContext): Response {
            return (new ContactsController())->delete($request, $userContext);
        });

        $router->get('/settings', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->index($request, $userContext);
        });

        $router->get('/settings/users/new', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->newUser($request, $userContext);
        });

        $router->get('/invoices', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->index($request, $userContext);
        });

        $router->get('/invoices/export', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->export($request, $userContext);
        });

        $router->get('/invoices/export-accounting', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->exportAccounting($request, $userContext);
        });

        $router->post('/invoices/export-accounting', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->exportAccounting($request, $userContext);
        });

        $router->get('/invoices/show', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->show($request, $userContext);
        });

        $router->get('/invoices/edit', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->edit($request, $userContext);
        });

        $router->post('/invoices/save-draft', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->saveDraft($request, $userContext);
        });

        $router->get('/invoices/new-manual', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->newManualFromProject($request, $userContext);
        });

        $router->post('/invoices/create-manual', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->createManualFromProject($request, $userContext);
        });

        $router->post('/invoices/delete-manual-draft', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->deleteManualDraft($request, $userContext);
        });

        $router->get('/invoices/new', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->new($request, $userContext);
        });

        $router->post('/invoices/create', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->create($request, $userContext);
        });

        $router->post('/invoices/send', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->sendFromProject($request, $userContext);
        });

        $router->post('/invoices/resend', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->resendFromProject($request, $userContext);
        });

        $router->post('/invoices/payment/manual', function (Request $request, UserContext $userContext): Response {
            return (new InvoicesController())->recordManualPaymentFromProject($request, $userContext);
        });

        $router->get('/payments/new', function (Request $request, UserContext $userContext): Response {
            return (new PaymentsController())->new($request, $userContext);
        });

        $router->post('/payments/create', function (Request $request, UserContext $userContext): Response {
            return (new PaymentsController())->create($request, $userContext);
        });

        $router->get('/projects', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->index($request, $userContext);
        });

        $router->get('/projects/show', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->show($request, $userContext);
        });

        $router->get('/projects/new', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->new($request, $userContext);
        });

        $router->post('/projects/create', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->create($request, $userContext);
        });

        $router->post('/projects/quotes/version/create', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->createQuoteVersion($request, $userContext);
        });

        $router->get('/projects/quotes/version/new', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->newQuoteVersion($request, $userContext);
        });

        $router->post('/projects/status/update', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->statusUpdate($request, $userContext);
        });

        $router->post('/projects/quotes/send', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->sendQuote($request, $userContext);
        });

        $router->post('/projects/quotes/validate', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->validateQuote($request, $userContext);
        });

        $router->post('/projects/planify', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->planifyProject($request, $userContext);
        });

        $router->get('/projects/rentability', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->rentabilityDashboard($request, $userContext);
        });

        $router->get('/projects/rentability/form', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->rentabilityForm($request, $userContext);
        });

        $router->post('/projects/rentability/save', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->rentabilitySave($request, $userContext);
        });

        $router->post('/projects/complete', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->completeAffaire($request, $userContext);
        });

        $router->get('/quotes/view', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->publicQuoteView($request, $userContext);
        });

        $router->post('/quotes/signature/request-code', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->requestQuoteSignatureCode($request, $userContext);
        });

        $router->post('/quotes/signature/confirm', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->confirmQuoteSignature($request, $userContext);
        });

        $router->get('/quotes/signed/download', function (Request $request, UserContext $userContext): Response {
            return (new ProjectsController())->downloadSignedQuotePdf($request, $userContext);
        });

        $router->get('/invoice/pay', function (Request $request, UserContext $userContext): Response {
            return (new PublicInvoiceController())->pay($request, $userContext);
        });

        $router->post('/invoice/pay/stripe', function (Request $request, UserContext $userContext): Response {
            return (new PublicInvoiceController())->stripeCheckout($request, $userContext);
        });

        $router->post('/webhooks/stripe', function (Request $request, UserContext $userContext): Response {
            return (new PublicInvoiceController())->stripeWebhook($request, $userContext);
        });

        $router->get('/project-reports', function (Request $request, UserContext $userContext): Response {
            return (new ProjectReportsController())->index($request, $userContext);
        });

        $router->post('/project-reports/create', function (Request $request, UserContext $userContext): Response {
            return (new ProjectReportsController())->create($request, $userContext);
        });

        $router->get('/project-photos', function (Request $request, UserContext $userContext): Response {
            return (new ProjectPhotosController())->index($request, $userContext);
        });

        $router->post('/project-photos/upload', function (Request $request, UserContext $userContext): Response {
            return (new ProjectPhotosController())->upload($request, $userContext);
        });

        $router->get('/price-library', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->index($request, $userContext);
        });

        $router->get('/price-library/new', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->new($request, $userContext);
        });

        $router->post('/price-library/create', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->create($request, $userContext);
        });

        $router->get('/price-library/edit', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->edit($request, $userContext);
        });

        $router->post('/price-library/update', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->update($request, $userContext);
        });

        $router->post('/price-library/delete', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->delete($request, $userContext);
        });
        
        $router->post('/price-library/deactivate', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->deactivate($request, $userContext);
        });

        $router->post('/price-library/category/create', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->createCategory($request, $userContext);
        });

        $router->post('/price-library/category/update', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->updateCategory($request, $userContext);
        });

        $router->get('/price-library/category/new', function (Request $request, UserContext $userContext): Response {
            return (new PriceLibraryController())->categoryNew($request, $userContext);
        });

        $router->get('/planning', function (Request $request, UserContext $userContext): Response {
            return (new PlanningController())->index($request, $userContext);
        });

        $router->post('/planning/create', function (Request $request, UserContext $userContext): Response {
            return (new PlanningController())->create($request, $userContext);
        });

        $router->get('/hr', function (Request $request, UserContext $userContext): Response {
            return (new HrController())->index($request, $userContext);
        });

        $router->get('/hr/leave/new', function (Request $request, UserContext $userContext): Response {
            return (new HrController())->newLeaveRequest($request, $userContext);
        });

        $router->post('/hr/leave/create', function (Request $request, UserContext $userContext): Response {
            return (new HrController())->createLeaveRequest($request, $userContext);
        });

        $router->post('/hr/leave/approve', function (Request $request, UserContext $userContext): Response {
            return (new HrController())->approveLeaveRequest($request, $userContext);
        });

        $router->post('/settings/users/create', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->createUser($request, $userContext);
        });

        $router->post('/settings/users/cout-horaire', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->updateUserCoutHoraire($request, $userContext);
        });

        $router->post('/settings/roles/permissions', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->updateRolePermissions($request, $userContext);
        });

        $router->post('/settings/smtp/update', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->updateSmtp($request, $userContext);
        });

        $router->post('/settings/smtp/test', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->testSmtp($request, $userContext);
        });

        $router->post('/settings/billing/subscribe', function (Request $request, UserContext $userContext): Response {
            return (new SettingsController())->subscribeBilling($request, $userContext);
        });

        $router->get('/platform/companies', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companiesIndex($request, $userContext);
        });

        $router->post('/platform/settings/billing/save', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->platformBillingSave($request, $userContext);
        });

        $router->get('/platform/companies/new', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyNew($request, $userContext);
        });

        $router->post('/platform/companies/create', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyCreate($request, $userContext);
        });

        $router->get('/platform/companies/show', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyShow($request, $userContext);
        });

        $router->post('/platform/companies/update', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyUpdate($request, $userContext);
        });

        $router->post('/platform/companies/billing', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->billingUpdate($request, $userContext);
        });

        $router->post('/platform/packs/upsert', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->packsUpsert($request, $userContext);
        });

        $router->get('/platform/packs/new', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->packsNew($request, $userContext);
        });

        $router->get('/platform/packs/edit', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->packsEdit($request, $userContext);
        });

        $router->post('/platform/packs/delete', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->packsDelete($request, $userContext);
        });

        $router->post('/platform/companies/users/create', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyUserCreate($request, $userContext);
        });
        $router->get('/platform/companies/users/new', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyUserNew($request, $userContext);
        });

        $router->post('/platform/companies/users/delete', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyUserDelete($request, $userContext);
        });

        $router->post('/platform/companies/users/update', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->companyUserUpdate($request, $userContext);
        });

        $router->post('/platform/users/create', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->platformUserCreate($request, $userContext);
        });

        $router->get('/platform/users/new', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->platformUserNew($request, $userContext);
        });

        $router->post('/platform/users/update', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->platformUserUpdate($request, $userContext);
        });

        $router->get('/platform/users/edit', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->platformUserEdit($request, $userContext);
        });

        $router->post('/platform/users/delete', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->platformUserDelete($request, $userContext);
        });

        $router->get('/platform/audit', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->auditIndex($request, $userContext);
        });

        $router->post('/platform/impersonate/start', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->impersonateStart($request, $userContext);
        });

        $router->post('/platform/impersonate/stop', function (Request $request, UserContext $userContext): Response {
            return (new PlatformController())->impersonateStop($request, $userContext);
        });
    }

    private function enforceBillingRestriction(Request $request, UserContext $userContext): ?Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return null;
        }
        // Les comptes plateforme ne sont pas bloqués par ce tunnel.
        if (in_array('platform.company.manage', $userContext->permissions, true)) {
            return null;
        }

        $path = $request->getPath();
        $allowedPaths = ['/logout', '/settings', '/settings/billing/subscribe'];
        if (in_array($path, $allowedPaths, true)) {
            if ($path !== '/settings') {
                return null;
            }
            $tab = (string) $request->getQueryParam('tab', '');
            if ($tab === 'billing') {
                return null;
            }
        }

        $company = (new CompanyRepository())->findById((int) $userContext->companyId);
        if (!is_array($company)) {
            return null;
        }
        $plan = trim((string) ($company['billingPlan'] ?? ''));
        $renew = trim((string) ($company['subscriptionRenewsAt'] ?? ''));
        if ($plan === '' || $renew === '') {
            return null;
        }

        $isFreePlan = false;
        foreach ((new PackRepository())->listAll() as $p) {
            if (trim((string) ($p['name'] ?? '')) === $plan && (float) ($p['price'] ?? 0) <= 0) {
                $isFreePlan = true;
                break;
            }
        }
        if (!$isFreePlan) {
            return null;
        }

        $renewDate = \DateTimeImmutable::createFromFormat('Y-m-d', substr($renew, 0, 10));
        if (!$renewDate) {
            return null;
        }
        $today = new \DateTimeImmutable('today');
        if ($renewDate >= $today) {
            return null;
        }

        return Response::redirect('settings?tab=billing&err=Periode%20gratuite%20expiree.%20Choisissez%20un%20abonnement%20payant');
    }
}

