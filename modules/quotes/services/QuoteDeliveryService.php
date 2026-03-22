<?php
declare(strict_types=1);

namespace Modules\Quotes\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Modules\Settings\Repositories\SmtpSettingsRepository;
use PHPMailer\PHPMailer\PHPMailer;

final class QuoteDeliveryService
{
    public function buildPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    public function sendQuoteEmail(
        int $companyId,
        string $toEmail,
        string $subject,
        string $bodyText,
        string $pdfContent,
        string $pdfFileName
    ): void {
        $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) ($smtp['host'] ?? '');
        $mail->Port = (int) ($smtp['port'] ?? 587);
        $username = (string) ($smtp['username'] ?? '');
        $password = (string) ($smtp['password'] ?? '');
        $authEnabled = ((string) ($smtp['auth_enabled'] ?? '1')) !== '0';
        $mail->SMTPAuth = $authEnabled;
        $mail->Username = $username;
        $mail->Password = $password;
        $enc = (string) ($smtp['encryption'] ?? 'tls');
        $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        if ($enc === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromEmail = (string) ($smtp['from_email'] ?? '');
        $fromName = (string) ($smtp['from_name'] ?? 'Pilora');
        if ($fromEmail === '') {
            throw new \RuntimeException('Email expéditeur SMTP manquant.');
        }

        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body = $bodyText;
        $mail->isHTML(false);
        $mail->addStringAttachment($pdfContent, $pdfFileName, 'base64', 'application/pdf');
        $mail->send();
    }

    public function sendTestEmail(
        int $companyId,
        string $toEmail,
        string $subject,
        string $bodyText
    ): void {
        $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) ($smtp['host'] ?? '');
        $mail->Port = (int) ($smtp['port'] ?? 587);
        $username = (string) ($smtp['username'] ?? '');
        $password = (string) ($smtp['password'] ?? '');
        $authEnabled = ((string) ($smtp['auth_enabled'] ?? '1')) !== '0';
        $mail->SMTPAuth = $authEnabled;
        $mail->Username = $username;
        $mail->Password = $password;
        $enc = (string) ($smtp['encryption'] ?? 'tls');
        $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        if ($enc === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
        $fromEmail = (string) ($smtp['from_email'] ?? '');
        $fromName = (string) ($smtp['from_name'] ?? 'Pilora');
        if ($fromEmail === '') {
            throw new \RuntimeException('Email expéditeur SMTP manquant.');
        }
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body = $bodyText;
        $mail->isHTML(false);
        $mail->send();
    }
}

