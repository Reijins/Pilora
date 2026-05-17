<?php
declare(strict_types=1);

namespace Modules\Platform\Services;

use Modules\Platform\Repositories\PlatformSmtpSettingsRepository;
use PHPMailer\PHPMailer\PHPMailer;

final class PlatformMailService
{
    public function send(string $toEmail, string $subject, string $bodyText): void
    {
        $smtp = (new PlatformSmtpSettingsRepository())->get();
        $host = trim((string) ($smtp['host'] ?? ''));
        if ($host === '') {
            throw new \RuntimeException('SMTP plateforme non configuré (Paramètres Pilora).');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) ($smtp['port'] ?? 587);
        $authEnabled = ((string) ($smtp['auth_enabled'] ?? '1')) !== '0';
        $mail->SMTPAuth = $authEnabled;
        $mail->Username = (string) ($smtp['username'] ?? '');
        $mail->Password = (string) ($smtp['password'] ?? '');
        $enc = (string) ($smtp['encryption'] ?? 'tls');
        $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        if ($enc === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromEmail = trim((string) ($smtp['from_email'] ?? ''));
        $fromName = trim((string) ($smtp['from_name'] ?? 'Pilora'));
        if ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Email expéditeur SMTP plateforme manquant ou invalide.');
        }

        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->setFrom($fromEmail, $fromName !== '' ? $fromName : 'Pilora');
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body = $bodyText;
        $mail->isHTML(false);
        $mail->send();
    }

    /**
     * @param array<string, string> $vars
     */
    public function renderTemplate(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }

    public function sendBillingRenewal(
        string $toEmail,
        string $packName,
        string $billingCycle,
        string $amountFormatted,
        string $renewDate
    ): void {
        $smtp = (new PlatformSmtpSettingsRepository())->get();
        $cycleLabel = $billingCycle === 'annual' ? 'annuel' : 'mensuel';
        $vars = [
            'pack_name' => $packName,
            'billing_cycle' => $cycleLabel,
            'amount' => $amountFormatted,
            'renew_date' => $renewDate,
        ];
        $subject = $this->renderTemplate((string) ($smtp['billing_email_subject'] ?? ''), $vars);
        $body = $this->renderTemplate((string) ($smtp['billing_email_body'] ?? ''), $vars);
        $this->send($toEmail, $subject, $body);
    }

    public function sendCompanyWelcome(
        string $toEmail,
        string $companyName,
        string $loginEmail,
        string $loginUrl
    ): void {
        $smtp = (new PlatformSmtpSettingsRepository())->get();
        $vars = [
            'company_name' => $companyName,
            'login_email' => $loginEmail,
            'login_url' => $loginUrl,
        ];
        $subject = $this->renderTemplate((string) ($smtp['company_welcome_subject'] ?? ''), $vars);
        $body = $this->renderTemplate((string) ($smtp['company_welcome_body'] ?? ''), $vars);
        $this->send($toEmail, $subject, $body);
    }

    public function sendDemoAck(string $toEmail, string $contactName, string $companyName): void
    {
        $smtp = (new PlatformSmtpSettingsRepository())->get();
        $vars = [
            'contact_name' => $contactName,
            'company_name' => $companyName !== '' ? $companyName : '—',
        ];
        $subject = $this->renderTemplate((string) ($smtp['demo_ack_subject'] ?? ''), $vars);
        $body = $this->renderTemplate((string) ($smtp['demo_ack_body'] ?? ''), $vars);
        $this->send($toEmail, $subject, $body);
    }

    public function sendDemoNotify(
        string $toEmail,
        string $contactName,
        string $contactEmail,
        string $companyName,
        string $message,
        int $requestId,
    ): void {
        $smtp = (new PlatformSmtpSettingsRepository())->get();
        $vars = [
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'company_name' => $companyName !== '' ? $companyName : '—',
            'message' => $message !== '' ? $message : '—',
            'request_id' => (string) $requestId,
        ];
        $subject = $this->renderTemplate((string) ($smtp['demo_notify_subject'] ?? ''), $vars);
        $body = $this->renderTemplate((string) ($smtp['demo_notify_body'] ?? ''), $vars);
        $this->send($toEmail, $subject, $body);
    }
}
