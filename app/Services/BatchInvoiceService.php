<?php

namespace App\Services;

use App\Models\InsuranceBatchInvoice;
use App\Models\InsuranceClaim;
use App\Models\InsuranceProvider;
use App\Enums\BatchInvoiceStatusEnum;
use App\Enums\ClaimStatusEnum;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class BatchInvoiceService
{
    public function generateMonthlyBatch(
        InsuranceProvider $provider,
        int $year,
        int $month
    ): InsuranceBatchInvoice {
        return DB::transaction(function () use ($provider, $year, $month) {
            $claims = InsuranceClaim::where('insurance_provider_id', $provider->id)
                ->where('status', ClaimStatusEnum::APPROVED->value)
                ->whereNull('insurance_batch_invoice_id')
                ->whereYear('approved_at', $year)
                ->whereMonth('approved_at', $month)
                ->get();

            if ($claims->isEmpty()) {
                throw new \Exception('No approved claims found for this period');
            }

            $totalAmount = $claims->sum('approved_amount');

            $batch = InsuranceBatchInvoice::create([
                'batch_number' => $this->generateBatchNumber($provider, $year, $month),
                'sponsor_type' => $this->determineSponsorType($provider),
                'insurance_provider_id' => $provider->id,
                'insurance_provider_name' => $provider->name,
                'branch_id' => auth()->user()->branch_id,
                'billing_period_start' => now()->setYear($year)->setMonth($month)->startOfMonth(),  // ✅ FIXED: Using billing_period_start
                'billing_period_end' => now()->setYear($year)->setMonth($month)->endOfMonth(),      // ✅ FIXED: Using billing_period_end
                'period_label' => now()->setYear($year)->setMonth($month)->format('F Y'),
                'total_claims' => $claims->count(),
                'total_amount' => $totalAmount,        // ✅ FIXED: Using total_amount
                'approved_amount' => $totalAmount,     // ✅ FIXED: Using approved_amount
                'paid_amount' => 0,                    // ✅ FIXED: Using paid_amount
                'rejected_amount' => 0,                // ✅ FIXED: Using rejected_amount
                'status' => BatchInvoiceStatusEnum::DRAFT->value,
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]);

            // Attach claims to batch
            $claims->each(function ($claim) use ($batch) {
                $claim->update(['insurance_batch_invoice_id' => $batch->id]);
            });

            return $batch;
        });
    }

    public function generatePDF(InsuranceBatchInvoice $batch): string
    {
        $data = [
            'batch' => $batch->load(['provider', 'claims.client', 'claims.items.service']),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView('pdfs.batch-invoice', $data)
            ->setPaper('a4', 'portrait');

        $filename = "batch-invoice-{$batch->batch_number}.pdf";
        
        // Ensure directory exists
        $directory = storage_path('app/public/batch-invoices');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $path = "{$directory}/{$filename}";

        $pdf->save($path);

        // Update batch with PDF path
        $batch->update(['pdf_path' => "batch-invoices/{$filename}"]);

        return $path;
    }

    protected function generateBatchNumber(
        InsuranceProvider $provider,
        int $year,
        int $month
    ): string {
        $prefix = strtoupper(substr($provider->code ?? $provider->name, 0, 3));
        return sprintf('BATCH-%s-%s%02d-%03d', 
            $prefix, 
            $year, 
            $month,
            InsuranceBatchInvoice::where('insurance_provider_id', $provider->id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count() + 1
        );
    }

    /**
     * ✅ ADDED: Determine sponsor type from provider
     */
    protected function determineSponsorType(InsuranceProvider $provider): string
    {
        return match(strtoupper($provider->code ?? '')) {
            'SHA' => 'sha',
            'NCPWD' => 'ncpwd',
            default => 'insurance_private',
        };
    }
}