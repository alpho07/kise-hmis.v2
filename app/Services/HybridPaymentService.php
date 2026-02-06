<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CreditTransaction;
use App\Enums\PaymentMethodEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HybridPaymentService
{
    public function processHybridPayment(Invoice $invoice, array $paymentData): array
    {
        return DB::transaction(function () use ($invoice, $paymentData) {
            $payments = [];
            $totalPaid = 0;

            // Process Cash
            if (!empty($paymentData['cash_amount']) && $paymentData['cash_amount'] > 0) {
                $payments[] = $this->createPayment(
                    $invoice,
                    PaymentMethodEnum::CASH,
                    $paymentData['cash_amount']
                );
                $totalPaid += $paymentData['cash_amount'];
            }

            // Process M-PESA
            if (!empty($paymentData['mpesa_amount']) && $paymentData['mpesa_amount'] > 0) {
                $payments[] = $this->createPayment(
                    $invoice,
                    PaymentMethodEnum::MPESA,
                    $paymentData['mpesa_amount'],
                    $paymentData['mpesa_transaction_code'] ?? null
                );
                $totalPaid += $paymentData['mpesa_amount'];
            }

            // Process Bank Transfer
            if (!empty($paymentData['bank_amount']) && $paymentData['bank_amount'] > 0) {
                $payments[] = $this->createPayment(
                    $invoice,
                    PaymentMethodEnum::BANK_TRANSFER,
                    $paymentData['bank_amount'],
                    $paymentData['bank_reference'] ?? null
                );
                $totalPaid += $paymentData['bank_amount'];
            }

            // Process Credit Account
            if (!empty($paymentData['credit_amount']) && $paymentData['credit_amount'] > 0) {
                $creditPayment = $this->processCreditPayment(
                    $invoice,
                    $paymentData['credit_amount']
                );
                $payments[] = $creditPayment;
                $totalPaid += $paymentData['credit_amount'];
            }

            // Update invoice
            $invoice->update([
                'amount_paid' => $totalPaid,
                'payment_status' => $totalPaid >= $invoice->total_client_amount ? 'paid' : 'partial',
                'paid_at' => $totalPaid >= $invoice->total_client_amount ? now() : null,
            ]);

            return [
                'success' => true,
                'payments' => $payments,
                'total_paid' => $totalPaid,
                'balance' => max(0, $invoice->total_client_amount - $totalPaid),
            ];
        });
    }

    protected function createPayment(
        Invoice $invoice,
        PaymentMethodEnum $method,
        float $amount,
        ?string $reference = null
    ): Payment {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'visit_id' => $invoice->visit_id,
            'client_id' => $invoice->client_id,
            'branch_id' => $invoice->branch_id,
            'payment_method' => $method->value,
            'amount_paid' => $amount,  // ✅ FIXED: Was 'amount', now 'amount_paid'
            'transaction_reference' => $reference ?? strtoupper(Str::random(12)),
            'payment_date' => now(),
            'processed_by' => auth()->id(),
            'notes' => "Hybrid payment - {$method->label()}",
            'status' => 'completed',   // ✅ ADDED: Set payment status
        ]);
    }

    protected function processCreditPayment(Invoice $invoice, float $amount): Payment
    {
        $creditAccount = $invoice->client->creditAccount;

        if (!$creditAccount) {
            throw new \Exception('Client does not have a credit account');
        }

        if ($creditAccount->available_credit < $amount) {
            throw new \Exception('Insufficient credit balance. Available: KES ' . number_format($creditAccount->available_credit, 2));
        }

        // Create debit transaction using the account's charge method
        $creditAccount->charge(
            $amount,
            "Payment for Invoice #{$invoice->invoice_number}",
            $invoice->id
        );

        return $this->createPayment(
            $invoice,
            PaymentMethodEnum::CREDIT_ACCOUNT,
            $amount,
            "CREDIT-{$creditAccount->account_number}"
        );
    }

    public function calculatePaymentSplit(Invoice $invoice): array
    {
        $items = $invoice->items()->with('service')->get();

        return [
            'total_amount' => $invoice->total_amount,
            'sponsor_amount' => $invoice->total_sponsor_amount,
            'client_amount' => $invoice->total_client_amount,
            'items' => $items->map(function ($item) {
                return [
                    'service_name' => $item->service->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total_amount ?? $item->subtotal,
                    'sponsor_pays' => $item->sponsor_amount ?? 0,
                    'client_pays' => $item->client_amount ?? $item->subtotal,
                    'split_percentage' => $item->sponsor_percentage ?? 0,
                ];
            })->toArray(),
        ];
    }
}