<?php

namespace App\Filament\Resources\VisitResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class VisitServicesOverview extends Widget
{
    protected static string $view = 'filament.resources.widgets.visit.visit-services-overview';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        if (!$this->record) {
            return [
                'serviceBookings' => collect([]),
                'hasServices' => false,
                'totalServices' => 0,
            ];
        }

        $serviceBookings = $this->record->serviceBookings()
            ->with(['service.department', 'queueEntries'])
            ->get()
            ->map(function ($booking) {
                $queueEntry = $booking->queueEntries()->latest()->first();
                
                return [
                    'id' => $booking->id,
                    'service_name' => $booking->service->name,
                    'department_name' => $booking->service->department->name,
                    'booking_status' => $booking->booking_status,
                    'service_status' => $booking->service_status,
                    'booking_date' => $booking->booking_date,
                    'scheduled_date' => $booking->scheduled_date,
                    
                    // Queue information
                    'in_queue' => $queueEntry !== null,
                    'queue_number' => $queueEntry?->queue_number,
                    'queue_status' => $queueEntry?->status,
                    'joined_queue_at' => $queueEntry?->joined_at,
                    'queue_wait_time' => $queueEntry && $queueEntry->joined_at 
                        ? $queueEntry->joined_at->diffForHumans() 
                        : null,
                    
                    // Status colors
                    'booking_status_color' => $this->getBookingStatusColor($booking->booking_status),
                    'service_status_color' => $this->getServiceStatusColor($booking->service_status),
                    'queue_status_color' => $queueEntry ? $this->getQueueStatusColor($queueEntry->status) : 'gray',
                ];
            });

        // Calculate statistics
        $stats = [
            'total' => $serviceBookings->count(),
            'pending_payment' => $serviceBookings->where('booking_status', 'pending_payment')->count(),
            'confirmed' => $serviceBookings->where('booking_status', 'confirmed')->count(),
            'in_progress' => $serviceBookings->where('service_status', 'in_progress')->count(),
            'completed' => $serviceBookings->where('service_status', 'completed')->count(),
            'in_queue' => $serviceBookings->where('in_queue', true)->count(),
        ];

        return [
            'serviceBookings' => $serviceBookings,
            'hasServices' => $serviceBookings->isNotEmpty(),
            'totalServices' => $serviceBookings->count(),
            'stats' => $stats,
        ];
    }

    protected function getBookingStatusColor(string $status): string
    {
        return match($status) {
            'pending_payment' => 'warning',
            'confirmed' => 'success',
            'cancelled' => 'danger',
            'rescheduled' => 'info',
            default => 'gray',
        };
    }

    protected function getServiceStatusColor(string $status): string
    {
        return match($status) {
            'not_started' => 'gray',
            'in_progress' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
            'on_hold' => 'amber',
            default => 'gray',
        };
    }

    protected function getQueueStatusColor(string $status): string
    {
        return match($status) {
            'waiting' => 'info',
            'called' => 'warning',
            'serving' => 'primary',
            'completed' => 'success',
            'no_show' => 'danger',
            default => 'gray',
        };
    }
}