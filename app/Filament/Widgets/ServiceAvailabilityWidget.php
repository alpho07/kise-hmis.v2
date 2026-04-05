<?php
namespace App\Filament\Widgets;

use App\Models\ServiceAvailability;
use App\Models\Department;
use Filament\Widgets\Widget;

class ServiceAvailabilityWidget extends Widget
{
    protected static string $view = 'filament.widgets.service-availability-widget';
    protected static ?string $pollingInterval = '30s';
    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $branchId = auth()->user()->branch_id;

        $departments = Department::where('branch_id', $branchId)->get();
        $records     = ServiceAvailability::today()
            ->forBranch($branchId)
            ->with('department')
            ->get()
            ->keyBy('department_id');

        $statuses = $departments->map(function (Department $dept) use ($records) {
            $record = $records->get($dept->id);
            return [
                'department' => $dept,
                'available'  => $record ? $record->is_available : true,
                'reason'     => $record?->reason_code,
                'comment'    => $record?->comment,
            ];
        });

        return ['statuses' => $statuses];
    }
}
