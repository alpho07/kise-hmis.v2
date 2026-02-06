<?php
namespace App\Filament\Resources\CashierQueueResource\Pages;

use App\Filament\Resources\CashierQueueResource;
use Filament\Resources\Pages\Page;

class ManageServiceRequests extends Page
{
    protected static string $resource = CashierQueueResource::class;

    protected static string $view = 'filament.pages.manage-service-requests';

    protected static ?string $title = 'Service Requests - Pending Payment';

    protected static ?string $navigationLabel = 'Service Requests';

    public function getTable(): \Filament\Tables\Table
    {
        return CashierQueueResource::getServiceRequestsTable();
    }
}