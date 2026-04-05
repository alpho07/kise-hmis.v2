<?php
namespace App\Services;

use App\Models\Client;
use App\Models\NotificationLog;

class NotificationService
{
    public function send(Client $client, string $type, array $data, ?int $appointmentId = null): NotificationLog
    {
        $body = $this->buildMessage($type, $client, $data);

        $log = NotificationLog::create([
            'client_id'       => $client->id,
            'recipient_phone' => $client->phone_primary ?? $client->phone ?? '',
            'message_type'    => $type,
            'message_body'    => $body,
            'appointment_id'  => $appointmentId,
            'status'          => 'mock',
            'staff_id'        => auth()->id(),
            'sent_at'         => now(),
        ]);

        // --- CELCOM GATEWAY STUB (uncomment to enable real SMS) ---
        // $apiKey   = config('services.celcom.api_key');   // CELCOM_API_KEY in .env
        // $endpoint = config('services.celcom.endpoint');  // CELCOM_ENDPOINT in .env
        // try {
        //     $response = \Illuminate\Support\Facades\Http::withToken($apiKey)->post($endpoint, [
        //         'to'      => $log->recipient_phone,
        //         'message' => $body,
        //     ]);
        //     $log->update(['status' => $response->successful() ? 'sent' : 'failed']);
        // } catch (\Throwable $e) {
        //     $log->update(['status' => 'failed']);
        // }
        // --- END STUB ---

        return $log;
    }

    private function buildMessage(string $type, Client $client, array $data): string
    {
        $name    = $client->first_name;
        $service = $data['service'] ?? 'your service';
        $date    = isset($data['date']) ? \Carbon\Carbon::parse($data['date'])->format('d M Y') : '';
        $time    = $data['time'] ?? '';
        $reason  = $data['reason'] ?? 'operational reasons';

        return match ($type) {
            'appointment_reminder'  => "Dear {$name}, your appointment at KISE on {$date} at {$time} for {$service} is confirmed. Reply STOP to opt out.",
            'check_in_confirmation' => "Dear {$name}, you have been checked in at KISE for {$service}. Please proceed to Triage.",
            'disruption_alert'      => "Dear {$name}, {$service} at KISE is unavailable on {$date} ({$reason}). We will contact you to reschedule.",
            'follow_up_booking'     => "Dear {$name}, your next {$service} appointment at KISE has been booked for {$date} at {$time}.",
            default                 => "Dear {$name}, you have a message from KISE regarding {$service}.",
        };
    }
}
