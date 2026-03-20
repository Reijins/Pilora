<?php
declare(strict_types=1);

namespace Modules\Settings\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Rbac\Repositories\RbacAdminRepository;
use Modules\Quotes\Services\QuoteDeliveryService;
use Modules\Settings\Repositories\SmtpSettingsRepository;
use Modules\Users\Repositories\UserAdminRepository;

final class SettingsController extends BaseController
{
    private const ADMIN_PERMISSION = 'admin.company.manage';
    private const SETTINGS_TABS = ['general', 'smtp', 'email_templates', 'users', 'rbac'];

    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $hasAccess = in_array(self::ADMIN_PERMISSION, $userContext->permissions, true);
        if (!$hasAccess) {
            return $this->renderPage('settings/index.php', [
                'pageTitle' => 'Paramètres',
                'permissionDenied' => true,
            ]);
        }

        $companyId = $userContext->companyId;
        $repoUsers = new UserAdminRepository();
        $repoRbac = new RbacAdminRepository();

        $activeTabRaw = (string) $request->getQueryParam('tab', 'general');
        $activeTab = in_array($activeTabRaw, self::SETTINGS_TABS, true) ? $activeTabRaw : 'smtp';
        $roleIdToEditRaw = $request->getQueryParam('roleId', '');
        $roleIdToEdit = is_numeric($roleIdToEditRaw) ? (int) $roleIdToEditRaw : null;

        try {
            $roles = $repoRbac->listRolesByCompanyId($companyId);
            $permissions = $repoRbac->listPermissionsByCompanyId($companyId);
            $users = $repoUsers->listUsersWithRoles($companyId);
        } catch (\Throwable) {
            return $this->renderPage('settings/index.php', [
                'pageTitle' => 'Paramètres',
                'permissionDenied' => false,
                'csrfToken' => Csrf::token(),
                'companyId' => $companyId,
                'users' => [],
                'roles' => [],
                'permissions' => [],
                'roleIdToEdit' => null,
                'permissionsForRole' => [],
                'smtpSettings' => (new SmtpSettingsRepository())->getByCompanyId($companyId),
                'activeTab' => $activeTab,
                'flashError' => 'Erreur chargement paramètres.',
                'flashMessage' => null,
            ]);
        }

        $permissionsForRole = [];
        if ($roleIdToEdit !== null && $roleIdToEdit > 0) {
            $permissionsForRole = $repoRbac->listPermissionIdsForRole($companyId, $roleIdToEdit);
        }

        // Default role to edit.
        if (($roleIdToEdit === null || $roleIdToEdit <= 0) && !empty($roles)) {
            $roleIdToEdit = (int) $roles[0]['id'];
            $permissionsForRole = $repoRbac->listPermissionIdsForRole($companyId, $roleIdToEdit);
        }

        return $this->renderPage('settings/index.php', [
            'pageTitle' => 'Paramètres',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'companyId' => $companyId,
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'roleIdToEdit' => $roleIdToEdit,
            'permissionsForRole' => $permissionsForRole,
            'smtpSettings' => (new SmtpSettingsRepository())->getByCompanyId($companyId),
            'activeTab' => $activeTab,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function newUser(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        $hasAccess = in_array(self::ADMIN_PERMISSION, $userContext->permissions, true);
        if (!$hasAccess) {
            return Response::redirect('settings');
        }

        $roles = [];
        try {
            $roles = (new UserAdminRepository())->listRoleIdsByCompanyId($userContext->companyId);
        } catch (\Throwable) {
            $roles = [];
        }

        return $this->renderPage('settings/user_new.php', [
            'pageTitle' => 'Créer un utilisateur',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'roles' => $roles,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function updateSmtp(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        $hasAccess = in_array(self::ADMIN_PERMISSION, $userContext->permissions, true);
        if (!$hasAccess) {
            return Response::redirect('settings');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('settings/users/new?err=Requete%20invalide');
        }

        $host = trim((string) $request->getBodyParam('smtp_host', ''));
        $portRaw = $request->getBodyParam('smtp_port', '587');
        $port = is_numeric($portRaw) ? (int) $portRaw : 587;
        $authEnabledRaw = (string) $request->getBodyParam('smtp_auth_enabled', '1');
        $authEnabled = $authEnabledRaw === '0' ? '0' : '1';
        $username = trim((string) $request->getBodyParam('smtp_username', ''));
        $password = trim((string) $request->getBodyParam('smtp_password', ''));
        $encryption = trim((string) $request->getBodyParam('smtp_encryption', 'tls'));
        $fromEmail = trim((string) $request->getBodyParam('smtp_from_email', ''));
        $fromName = trim((string) $request->getBodyParam('smtp_from_name', ''));
        $vatRateRaw = $request->getBodyParam('vat_rate', '20');
        $vatRate = is_numeric($vatRateRaw) ? (float) $vatRateRaw : 20.0;
        if ($vatRate < 0) $vatRate = 0;
        if ($vatRate > 100) $vatRate = 100;
        $proofRequired = (string) $request->getBodyParam('proof_required', '0') === '1' ? '1' : '0';
        $quoteEmailSubject = trim((string) $request->getBodyParam('quote_email_subject', ''));
        $quoteEmailBody = trim((string) $request->getBodyParam('quote_email_body', ''));
        $settingsTab = trim((string) $request->getBodyParam('settings_tab', 'smtp'));
        if (!in_array($settingsTab, self::SETTINGS_TABS, true)) {
            $settingsTab = 'smtp';
        }

        if ($settingsTab === 'smtp' && ($host === '' || $port <= 0)) {
            return Response::redirect('settings?tab=' . urlencode($settingsTab) . '&err=SMTP%20invalide');
        }
        if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            return Response::redirect('settings?tab=' . urlencode($settingsTab) . '&err=Email%20expediteur%20invalide');
        }
        if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
            $encryption = 'tls';
        }

        $newLogoPath = '';
        if ($settingsTab === 'general') {
            $uploadedLogo = $this->saveCompanyLogoFromUpload($userContext->companyId);
            if ($uploadedLogo === false) {
                return Response::redirect('settings?tab=general&err=Logo%20invalide%20ou%20trop%20volumineux');
            }
            $newLogoPath = $uploadedLogo;
        }

        try {
            $existing = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
            $logoPath = $newLogoPath !== ''
                ? $newLogoPath
                : (string) ($existing['company_logo_path'] ?? '');
            (new SmtpSettingsRepository())->saveByCompanyId($userContext->companyId, [
                'host' => $host !== '' ? $host : (string) ($existing['host'] ?? ''),
                'port' => $port > 0 ? $port : (int) ($existing['port'] ?? 587),
                'auth_enabled' => $authEnabled,
                'username' => $username !== '' ? $username : (string) ($existing['username'] ?? ''),
                'password' => $password !== '' ? $password : (string) ($existing['password'] ?? ''),
                'encryption' => $encryption,
                'from_email' => $fromEmail !== '' ? $fromEmail : (string) ($existing['from_email'] ?? ''),
                'from_name' => $fromName !== '' ? $fromName : (string) ($existing['from_name'] ?? ''),
                'vat_rate' => $vatRate,
                'proof_required' => $proofRequired,
                'quote_email_subject' => $quoteEmailSubject !== '' ? $quoteEmailSubject : 'Votre devis {{quote_number}}',
                'quote_email_body' => $quoteEmailBody !== '' ? $quoteEmailBody : "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe (PDF).\nVous pouvez aussi le consulter en ligne : {{quote_link}}\n\nCordialement,\n{{company_name}}",
                'company_logo_path' => $logoPath,
            ]);
        } catch (\Throwable) {
            return Response::redirect('settings?tab=' . urlencode($settingsTab) . '&err=Impossible%20de%20sauvegarder%20SMTP');
        }

        Csrf::rotate();
        $msg = $settingsTab === 'general'
            ? 'Parametres%20generaux%20enregistres'
            : 'Parametres%20SMTP%20enregistres';
        return Response::redirect('settings?tab=' . urlencode($settingsTab) . '&msg=' . $msg);
    }

    /**
     * @return string|false chemin relatif si upload OK, chaîne vide si aucun fichier, false si erreur
     */
    private function saveCompanyLogoFromUpload(int $companyId): string|false
    {
        if (!isset($_FILES['company_logo']) || !is_array($_FILES['company_logo'])) {
            return '';
        }
        $file = $_FILES['company_logo'];
        if (!isset($file['error']) || !is_int($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $maxBytes = 2 * 1024 * 1024;
        $size = isset($file['size']) && is_int($file['size']) ? $file['size'] : 0;
        if ($size <= 0 || $size > $maxBytes) {
            return false;
        }
        $tmpPath = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return false;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return false;
        }
        if (!function_exists('imagewebp')) {
            return false;
        }
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png' => @imagecreatefrompng($tmpPath),
            'image/webp' => @imagecreatefromwebp($tmpPath),
            'image/gif' => @imagecreatefromgif($tmpPath),
            default => null,
        };
        if (!is_resource($image) && !($image instanceof \GdImage)) {
            return false;
        }
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);
        $fileName = 'logo_' . bin2hex(random_bytes(8)) . '.webp';
        $appRoot = dirname(__DIR__, 3);
        $dir = $appRoot . '/public/storage/uploads/' . $companyId . '/brand/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dest = $dir . $fileName;
        if (!imagewebp($image, $dest, 88)) {
            imagedestroy($image);
            return false;
        }
        imagedestroy($image);

        return '/public/storage/uploads/' . $companyId . '/brand/' . $fileName;
    }

    public function testSmtp(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        $hasAccess = in_array(self::ADMIN_PERMISSION, $userContext->permissions, true);
        if (!$hasAccess) {
            return Response::redirect('settings');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('settings?tab=smtp&err=Requete%20invalide');
        }
        $toEmail = trim((string) $request->getBodyParam('smtp_test_email', ''));
        if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return Response::redirect('settings?tab=smtp&err=Email%20de%20test%20invalide');
        }
        try {
            (new QuoteDeliveryService())->sendTestEmail(
                companyId: $userContext->companyId,
                toEmail: $toEmail,
                subject: 'Test SMTP Pilora',
                bodyText: "Bonjour,\n\nCe message confirme que vos paramètres SMTP Pilora fonctionnent."
            );
        } catch (\Throwable $e) {
            $message = trim($e->getMessage());
            $suffix = $message !== '' ? ('%20-%20' . rawurlencode($message)) : '';
            return Response::redirect('settings?tab=smtp&err=Test%20SMTP%20echoue' . $suffix);
        }
        Csrf::rotate();
        return Response::redirect('settings?tab=smtp&msg=Test%20SMTP%20envoye');
    }

    public function createUser(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        $hasAccess = in_array(self::ADMIN_PERMISSION, $userContext->permissions, true);
        if (!$hasAccess) {
            return Response::redirect('settings');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('settings?err=Requete%20invalide');
        }

        $companyId = $userContext->companyId;
        $email = trim((string) $request->getBodyParam('email', ''));
        $password = (string) $request->getBodyParam('password', '');
        $fullName = trim((string) $request->getBodyParam('full_name', ''));
        $roleIds = $request->getBodyParam('role_ids', []);
        $roleIds = is_array($roleIds) ? $roleIds : [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::redirect('settings/users/new?err=Email%20invalide');
        }
        if (mb_strlen($password) < 8) {
            return Response::redirect('settings/users/new?err=Mot%20de%20passe%20trop%20court');
        }
        if ($fullName === '') {
            return Response::redirect('settings/users/new?err=Nom%20complet%20requis');
        }

        $repoUsers = new UserAdminRepository();
        try {
            $repoUsers->createUserWithRoles(
                companyId: $companyId,
                email: $email,
                password: $password,
                fullName: $fullName,
                roleIds: $roleIds,
            );
        } catch (\Throwable $e) {
            return Response::redirect('settings/users/new?err=Impossible%20de%20cr%C3%A9er%20l%E2%80%99utilisateur');
        }

        Csrf::rotate();
        return Response::redirect('settings/users/new?msg=Utilisateur%20cr%C3%A9%C3%A9');
    }

    public function updateRolePermissions(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $hasAccess = in_array(self::ADMIN_PERMISSION, $userContext->permissions, true);
        if (!$hasAccess) {
            return Response::redirect('settings');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('settings?err=Requete%20invalide');
        }

        $companyId = $userContext->companyId;
        $roleIdRaw = $request->getBodyParam('role_id', null);
        $roleId = is_numeric($roleIdRaw) ? (int) $roleIdRaw : 0;
        $permissionIds = $request->getBodyParam('permission_ids', []);
        $permissionIds = is_array($permissionIds) ? $permissionIds : [];

        if ($roleId <= 0) {
            return Response::redirect('settings?err=R%C3%B4le%20invalide');
        }

        $repoRbac = new RbacAdminRepository();
        try {
            $repoRbac->setPermissionsForRole(
                companyId: $companyId,
                roleId: $roleId,
                permissionIds: $permissionIds,
            );
        } catch (\Throwable) {
            return Response::redirect('settings?err=Impossible%20de%20mettre%20%C3%A0%20jour%20les%20permissions');
        }

        Csrf::rotate();
        return Response::redirect('settings?tab=rbac&msg=Permissions%20mises%20%C3%A0%20jour&roleId=' . $roleId);
    }
}

