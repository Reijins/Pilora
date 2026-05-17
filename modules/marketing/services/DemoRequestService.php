<?php
declare(strict_types=1);

namespace Modules\Marketing\Services;

use Core\Http\ClientInfo;
use Modules\Marketing\Repositories\DemoRequestRepository;
use Modules\Platform\Repositories\PlatformBillingSettingsRepository;
use Modules\Platform\Repositories\PlatformSmtpSettingsRepository;
use Modules\Platform\Services\PlatformMailService;

final class DemoRequestService
{
    public function submit(
        string $name,
        string $email,
        string $company,
        string $message,
    ): int {
        $name = trim($name);
        $email = trim($email);
        $company = trim($company);
        $message = trim($message);

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Informations incomplètes.');
        }

        $repo = new DemoRequestRepository();
        $id = $repo->create(
            name: $name,
            email: $email,
            companyName: $company !== '' ? $company : null,
            message: $message !== '' ? $message : null,
            ipAddress: ClientInfo::ipAddress(),
            userAgent: ClientInfo::userAgent(),
        );

        $mail = new PlatformMailService();
        try {
            $mail->sendDemoAck($email, $name, $company);
            $repo->markAckSent($id);
        } catch (\Throwable) {
        }

        try {
            $notifyTo = $this->resolveNotifyEmail();
            if ($notifyTo !== '') {
                $mail->sendDemoNotify($notifyTo, $name, $email, $company, $message, $id);
                $repo->markNotifySent($id);
            }
        } catch (\Throwable) {
        }

        return $id;
    }

    private function resolveNotifyEmail(): string
    {
        $smtp = (new PlatformSmtpSettingsRepository())->get();
        $configured = trim((string) ($smtp['demo_notify_email'] ?? ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $billing = (new PlatformBillingSettingsRepository())->get();
        $fallback = trim((string) ($billing['email'] ?? ''));

        return $fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL) ? $fallback : '';
    }
}
