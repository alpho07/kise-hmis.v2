<?php

namespace App\Exports;

use App\Models\InsuranceBatchInvoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BatchInvoiceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected InsuranceBatchInvoice $batch;

    public function __construct(InsuranceBatchInvoice $batch)
    {
        $this->batch = $batch->load(['claims.client', 'claims.items.service']);
    }

    public function collection()
    {
        return $this->batch->claims;
    }

    public function headings(): array
    {
        return [
            'Claim Number',
            'Client Name',
            'UCI',
            'Service Date',
            'Service Description',
            'Quantity',
            'Unit Price',
            'Claimed Amount',
            'Approved Amount',
            'Status',
        ];
    }

    public function map($claim): array
    {
        $rows = [];
        
        foreach ($claim->items as $item) {
            $rows[] = [
                $claim->claim_number,
                $claim->client->full_name,
                $claim->client->uci,
                $claim->service_date->format('d/m/Y'),
                $item->service->name,
                $item->quantity,
                number_format($item->unit_price, 2),
                number_format($item->claimed_amount, 2),
                number_format($item->approved_amount, 2),
                strtoupper($claim->status),
            ];
        }
        
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return substr($this->batch->batch_number, 0, 31);
    }
}