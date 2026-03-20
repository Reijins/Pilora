<?php
declare(strict_types=1);

namespace Modules\Payments\Services;

use Core\Database\Connection;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Payments\Repositories\PaymentRepository;

final class PaymentService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly PaymentRepository $paymentRepository,
    ) {}

    public function registerSucceededPaymentAndUpdateInvoice(
        int $companyId,
        int $invoiceId,
        float $amount,
        string $provider,
        ?string $reference,
    ): void {
        $pdo = Connection::pdo();
        $pdo->beginTransaction();

        try {
            $invoice = $this->invoiceRepository->findByCompanyIdAndId($companyId, $invoiceId);
            if ($invoice === null) {
                throw new \RuntimeException('Facture introuvable.');
            }

            $total = (float) ($invoice['amountTotal'] ?? 0);
            $paid = (float) ($invoice['amountPaid'] ?? 0);
            $dueDateStr = (string) ($invoice['dueDate'] ?? '');
            $dueDate = $dueDateStr !== '' ? new \DateTimeImmutable($dueDateStr) : null;

            $remaining = $total - $paid;
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Montant invalide.');
            }
            if ($remaining < 0) {
                $remaining = 0;
            }
            if ($amount > $remaining) {
                // On bloque pour éviter les incohérences montant payé > total.
                throw new \InvalidArgumentException('Montant supérieur au restant à payer.');
            }

            $paidAt = new \DateTimeImmutable('now');

            $this->paymentRepository->createPaymentSucceeded(
                companyId: $companyId,
                invoiceId: $invoiceId,
                amount: $amount,
                provider: $provider,
                reference: $reference,
                paidAt: $paidAt,
            );

            $newPaid = (float) round($paid + $amount, 2);

            if ($total > 0 && $newPaid >= $total) {
                $newStatus = 'payee';
                $newPaidAt = $paidAt;
            } elseif ($newPaid > 0) {
                $newStatus = 'partiellement_payee';
                $newPaidAt = $paidAt;
            } else {
                // Aucun paiement effectif (cas théorique ici).
                $newStatus = ($dueDate !== null && $dueDate < $paidAt) ? 'echue' : (string) ($invoice['status'] ?? 'brouillon');
                $newPaidAt = null;
            }

            // Mise à jour de la facture.
            $stmt = $pdo->prepare('
                UPDATE Invoice
                SET
                    amountPaid = :amountPaid,
                    status = :status,
                    paidAt = :paidAt,
                    updatedAt = NOW()
                WHERE companyId = :companyId AND id = :invoiceId
            ');
            $stmt->execute([
                'amountPaid' => $newPaid,
                'status' => $newStatus,
                'paidAt' => $newPaidAt ? $newPaidAt->format('Y-m-d H:i:s') : null,
                'companyId' => $companyId,
                'invoiceId' => $invoiceId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

