<?php
declare(strict_types=1);

namespace Modules\Signup\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Auth\Repositories\UserRepository;
use Modules\Auth\Services\AuthService;
use Modules\Platform\Repositories\PackRepository;
use Modules\Signup\Repositories\SignupPendingRepository;
use Modules\Marketing\Services\MarketingBrandService;
use Modules\Marketing\Services\MarketingSeoService;
use Core\Config;
use Modules\Signup\Services\SignupService;

final class SignupController extends BaseController
{
    public function showForm(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId !== null) {
            return Response::redirect('dashboard');
        }

        $packs = [];
        try {
            $packs = (new PackRepository())->listAll();
            usort($packs, static function (array $a, array $b): int {
                $priceA = (float) ($a['price'] ?? 0);
                $priceB = (float) ($b['price'] ?? 0);
                if ($priceA !== $priceB) {
                    return $priceA <=> $priceB;
                }

                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
        } catch (\Throwable) {
        }

        $selectedPackId = (int) $request->getQueryParam('pack_id', 0);
        if ($selectedPackId <= 0) {
            foreach ($packs as $p) {
                if ((float) ($p['price'] ?? 0) <= 0) {
                    $selectedPackId = (int) ($p['id'] ?? 0);
                    break;
                }
            }
            if ($selectedPackId <= 0 && $packs !== []) {
                $selectedPackId = (int) ($packs[0]['id'] ?? 0);
            }
        }

        $seo = new MarketingSeoService();

        $response = $this->renderPage('marketing/inscription.php', [
            'pageTitle' => 'Créer votre espace Pilora',
            'metaDescription' => 'Inscrivez votre entreprise BTP sur Pilora : choisissez un pack, créez votre espace et connectez-vous.',
            'canonicalUrl' => $seo->canonical('/inscription'),
            'ogTitle' => 'Créer votre espace Pilora',
            'bodyClass' => 'marketing-page marketing-page--signup',
            'packs' => $packs,
            'selectedPackId' => $selectedPackId,
            'csrfToken' => Csrf::token(),
            'flashError' => $request->getQueryParam('err', null),
            'analyticsId' => trim((string) (Config::env('MARKETING_GA_MEASUREMENT_ID', '') ?? '')),
            'brandLogoUrl' => (new MarketingBrandService())->brandLogoUrl(
                rtrim(str_replace('\\', '/', (string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/')
                    ?: ''
            ),
        ], 'layouts/marketing.php');

        return $response->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function submit(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId !== null) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('inscription?err=Requete%20invalide');
        }

        $password = (string) $request->getBodyParam('password', '');
        $confirm = (string) $request->getBodyParam('password_confirm', '');
        if ($password !== $confirm) {
            Csrf::rotate();

            return Response::redirect('inscription?err=Les%20mots%20de%20passe%20ne%20correspondent%20pas');
        }

        try {
            $result = (new SignupService())->startSignup([
                'email' => $request->getBodyParam('email', ''),
                'password' => $password,
                'full_name' => $request->getBodyParam('full_name', ''),
                'company_name' => $request->getBodyParam('company_name', ''),
                'company_siret' => $request->getBodyParam('company_siret', ''),
                'company_address' => $request->getBodyParam('company_address', ''),
                'company_billing_address' => $request->getBodyParam('company_billing_address', ''),
                'pack_id' => $request->getBodyParam('pack_id', 0),
                'billing_cycle' => $request->getBodyParam('billing_cycle', 'monthly'),
            ]);
        } catch (\InvalidArgumentException $e) {
            $msg = rawurlencode($e->getMessage());
            Csrf::rotate();

            return Response::redirect('inscription?err=' . $msg);
        } catch (\Throwable $e) {
            Csrf::rotate();

            return Response::redirect('inscription?err=' . rawurlencode($e->getMessage() !== '' ? $e->getMessage() : 'Inscription%20impossible'));
        }

        Csrf::rotate();

        if ($result['requires_payment'] && !empty($result['checkout_url'])) {
            return Response::redirect((string) $result['checkout_url']);
        }

        $token = (string) $result['token'];
        $row = (new SignupPendingRepository())->findByToken($token);
        $userId = is_array($row) ? (int) ($row['userId'] ?? 0) : 0;
        if ($userId > 0 && (new AuthService(new UserRepository()))->establishSessionForUser($userId)) {
            return Response::redirect('dashboard?msg=Espace%20cree%20avec%20succes');
        }

        return Response::redirect('login?msg=Espace%20cree.%20Connectez-vous%20avec%20votre%20email.');
    }

    public function success(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getQueryParam('token', ''));
        $sessionId = trim((string) $request->getQueryParam('session_id', ''));

        if ($token === '') {
            return Response::redirect('inscription?err=Jeton%20manquant');
        }

        try {
            if ($sessionId !== '') {
                $ids = (new SignupService())->syncStripeSessionAndProvision($token, $sessionId);
            } else {
                $row = (new SignupPendingRepository())->findByToken($token);
                if ($row === null) {
                    throw new \RuntimeException('Inscription introuvable.');
                }
                if ((int) ($row['companyId'] ?? 0) <= 0) {
                    throw new \RuntimeException('Paiement en attente de confirmation.');
                }
                $ids = [
                    'companyId' => (int) $row['companyId'],
                    'userId' => (int) $row['userId'],
                ];
            }
        } catch (\Throwable $e) {
            $msg = rawurlencode($e->getMessage() !== '' ? $e->getMessage() : 'Finalisation impossible');

            return Response::redirect('inscription?err=' . $msg);
        }

        $userId = (int) ($ids['userId'] ?? 0);
        if ($userId > 0 && (new AuthService(new UserRepository()))->establishSessionForUser($userId)) {
            return Response::redirect('dashboard?msg=Bienvenue%20sur%20Pilora');
        }

        return Response::redirect('login?msg=Inscription%20terminee.%20Connectez-vous.');
    }

    public function cancelled(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getQueryParam('token', ''));
        if ($token !== '') {
            (new SignupPendingRepository())->markCancelled($token);
        }

        return Response::redirect('inscription?err=Paiement%20annule');
    }

    public function stripeWebhook(Request $request, UserContext $userContext): Response
    {
        $payload = $request->getRawBody();
        $sigHeader = (string) $request->getHeader('Stripe-Signature');
        if ($sigHeader === '' || $payload === '') {
            return new Response('Bad Request', 400);
        }

        try {
            (new SignupService())->handleStripeWebhook($payload, $sigHeader);
        } catch (\Throwable) {
            return new Response('Invalid signature', 400);
        }

        return new Response('OK', 200);
    }
}
