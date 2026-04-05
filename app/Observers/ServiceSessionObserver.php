<?php
namespace App\Observers;

use App\Models\ServiceSession;

class ServiceSessionObserver
{
    public function created(ServiceSession $session): void
    {
        $count = ServiceSession::where('service_booking_id', $session->service_booking_id)->count();

        // Set session_sequence (1-based)
        $session->updateQuietly(['session_sequence' => $count]);

        // Auto-complete booking if prescribed count reached
        $booking = $session->serviceBooking()->with('service')->first();
        if (
            $booking &&
            $booking->service?->default_session_count &&
            $count >= $booking->service->default_session_count
        ) {
            $booking->update(['service_status' => 'completed']);
        }
    }
}
