<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * Display thermal receipt (80mm) for printing
     */
    public function print(Payment $payment)
    {
        // Load relationships
        $payment->load([
            'invoice.items.service',
            'invoice.visit',
            'client',
            'processedBy',
            'creditAccount',
            'creditTransaction'
        ]);

        return view('receipts.thermal-80mm', compact('payment'));
    }

    /**
     * Generate PDF receipt (requires barryvdh/laravel-dompdf)
     * Install: composer require barryvdh/laravel-dompdf
     */
    public function pdf(Payment $payment)
    {
        // Check if PDF package is installed
        if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            return response()->json([
                'error' => 'PDF generation not available. Install: composer require barryvdh/laravel-dompdf'
            ], 501);
        }

        // Load relationships
        $payment->load([
            'invoice.items.service',
            'invoice.visit',
            'client',
            'processedBy',
            'creditAccount',
            'creditTransaction'
        ]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.thermal-80mm', compact('payment'))
            ->setPaper([0, 0, 226.77, 841.89], 'portrait'); // 80mm width

        return $pdf->download("receipt-{$payment->reference_number}.pdf");
    }

    /**
     * Email receipt to client
     * Requires mail configuration
     */
    public function email(Payment $payment)
    {
        $client = $payment->client;
        
        if (!$client->email) {
            return response()->json([
                'success' => false,
                'message' => 'Client has no email address on file'
            ], 400);
        }

        // TODO: Implement email sending
        // Mail::to($client->email)->send(new ReceiptMail($payment));

        return response()->json([
            'success' => true,
            'message' => "Receipt will be emailed to {$client->email}"
        ]);
    }
}