<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\ServiceAvailabilityWidget;
use App\Filament\Widgets\TodayAppointmentsWidget;
use App\Filament\Widgets\WalkInQueueWidget;
use Filament\Pages\Page;

class AppointmentsHubPage extends Page
{
    protected static string $view = 'filament.pages.appointments-hub';

    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Appointments Hub';
    protected static ?string $navigationGroup = 'Client Management';
    protected static ?int    $navigationSort  = 3;

    protected static ?string $title = 'Appointments Hub';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['receptionist', 'admin', 'super_admin']);
    }

    public function getWidgets(): array
    {
        return [
            ServiceAvailabilityWidget::class,
            WalkInQueueWidget::class,
            TodayAppointmentsWidget::class,
        ];
    }
}
