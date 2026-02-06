<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Enums\ClaimStatusEnum;
use Illuminate\Support\Facades\DB;

class InsuranceClaimService
{
    public function createClaimFromInvoice(Invoice $invoice): InsuranceClaim
    {
        return DB::transaction(function () use ($invoice) {
            $claim = InsuranceClaim::create([
                'claim_number' => $this->generateClaimNumber(),
                'invoice_id' => $invoice->id,
                'visit_id' => $invoice->visit_id,
                'client_id' => $invoice->client_id,
                'branch_id' => $invoice->branch_id,
                'insurance_provider_id' => $invoice->insurance_provider_id,
                'sponsor_type' => $this->determineSponsorType($invoice),
                'claim_amount' => $invoice->total_sponsor_amount,
                'approved_amount' => 0,
                'paid_amount' => 0,
                'status' => ClaimStatusEnum::PENDING->value,
                'service_date' => $invoice->created_at,  // ✅ KEPT: Using service_date (matches updated model)
                'submitted_by' => auth()->id(),
            ]);

            // Create claim items
            foreach ($invoice->items as $invoiceItem) {
                $claim->items()->create([
                    'invoice_item_id' => $invoiceItem->id,
                    'service_id' => $invoiceItem->service_id,
                    'service_name' => $invoiceItem->service->name,
                    'quantity' => $invoiceItem->quantity,
                    'unit_price' => $invoiceItem->unit_price,
                    'total_amount' => $invoiceItem->subtotal,
                    'sponsor_percentage' => $invoiceItem->sponsor_percentage ?? 0,
                    'sponsor_amount' => $invoiceItem->sponsor_amount ?? 0,
                    'client_amount' => $invoiceItem->client_amount ?? $invoiceItem->subtotal,
                    'claimed_amount' => $invoiceItem->sponsor_amount ?? 0,  // ✅ ADDED: claimed_amount
                    'approved_amount' => 0,  // ✅ ADDED: approved_amount (starts at 0)
                ]);
            }

            return $claim;
        });
    }

    public function submitClaim(InsuranceClaim $claim): void
    {
        $claim->update([
            'status' => ClaimStatusEnum::SUBMITTED->value,
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);
    }

    public function approveClaim(InsuranceClaim $claim, float $approvedAmount): void
    {
        $claim->update([
            'status' => ClaimStatusEnum::APPROVED->value,
            'approved_amount' => $approvedAmount,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);
    }

    public function markAsPaid(InsuranceClaim $claim, float $paidAmount): void
    {
        $newPaidAmount = $claim->paid_amount + $paidAmount;
        
        $claim->update([
            'paid_amount' => $newPaidAmount,
            'status' => $newPaidAmount >= $claim->approved_amount 
                ? ClaimStatusEnum::PAID->value 
                : ClaimStatusEnum::PARTIALLY_PAID->value,
            'payment_date' => $newPaidAmount >= $claim->approved_amount ? now() : $claim->payment_date,
        ]);
    }

    protected function generateClaimNumber(): string
    {
        $year = now()->year;
        $month = now()->format('m');
        $sequence = InsuranceClaim::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('CLM-%s%s-%05d', $year, $month, $sequence);
    }

    /**
     * ✅ ADDED: Determine sponsor type from invoice
     */
    protected function determineSponsorType(Invoice $invoice): string
    {
        // Check if invoice has insurance provider
        if ($invoice->insurance_provider_id) {
            $provider = $invoice->insuranceProvider;
            
            // Map provider code to sponsor type
            return match(strtoupper($provider->code ?? '')) {
                'SHA' => 'sha',
                'NCPWD' => 'ncpwd',
                default => 'insurance_private',
            };
        }

        // Default to checking invoice items
        $firstItemWithSponsor = $invoice->items()
            ->whereNotNull('sponsor_type')
            ->first();

        return $firstItemWithSponsor?->sponsor_type ?? 'insurance_private';
    }
}