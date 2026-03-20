<?php
declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Auth\Repositories\UserRepository;
use Modules\Auth\Services\AuthService;

final class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService(
            userRepository: new UserRepository(),
        );
    }

    public function showLogin(Request $request, UserContext $userContext): Response
    {
        $error = (string) $request->getQueryParam('error', '');

        return $this->renderPage('auth/login.php', [
            'pageTitle' => 'Connexion',
            'error' => $error !== '' ? $error : null,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function login(Request $request, UserContext $userContext): Response
    {
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return $this->renderPage('auth/login.php', [
                'pageTitle' => 'Connexion',
                'error' => 'Requête invalide (CSRF).',
                'csrfToken' => Csrf::token(),
            ]);
        }

        $email = trim((string) $request->getBodyParam('email', ''));
        $password = (string) $request->getBodyParam('password', '');

        $emailValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $passwordValid = $password !== '' && mb_strlen($password) >= 8;

        if (!$emailValid || !$passwordValid) {
            return $this->renderPage('auth/login.php', [
                'pageTitle' => 'Connexion',
                'error' => 'Email ou mot de passe invalide.',
            ]);
        }

        $ok = $this->authService->login($email, $password);
        if (!$ok) {
            return $this->renderPage('auth/login.php', [
                'pageTitle' => 'Connexion',
                'error' => 'Identifiants incorrects ou compte inactif.',
                'csrfToken' => Csrf::token(),
            ]);
        }

        Csrf::rotate();
        return Response::redirect('dashboard');
    }

    public function logout(Request $request, UserContext $userContext): Response
    {
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            // Si CSRF invalide, on refuse la déconnexion (meilleur en prod que de simplifier).
            return Response::redirect('profile');
        }

        $this->authService->logout();
        return Response::redirect('login');
    }

    public function profile(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $homeId = $userContext->homeCompanyId();
        $effectiveId = $userContext->companyId;
        $impersonating = $homeId !== null && $effectiveId !== null && $homeId !== $effectiveId;

        return $this->renderPage('auth/profile.php', [
            'pageTitle' => 'Mon profil',
            'companyId' => $effectiveId,
            'homeCompanyId' => $homeId,
            'impersonating' => $impersonating,
            'userId' => $userContext->userId,
            'csrfToken' => Csrf::token(),
        ]);
    }
}

