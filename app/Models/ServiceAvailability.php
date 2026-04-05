<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAvailability extends Model
{
    protected $table = 'service_availability';

    protected $fillable = [
        'branch_id',
        'department_id',
        'date',
        'is_available',
        'reason_code',
        'comment',
        'updated_by',
    ];

    protected $casts = [
        'date'         => 'date',
        'is_available' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public static function isDepartmentAvailable(int $departmentId, ?\Carbon\Carbon $date = null): bool
    {
        $date ??= today();
        $record = static::where('department_id', $departmentId)->whereDate('date', $date)->first();
        return $record ? $record->is_available : true; // default = available if no record
    }
}
