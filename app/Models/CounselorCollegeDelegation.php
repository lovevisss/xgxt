<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselorCollegeDelegation extends Model
{
    protected $fillable = ['employee_no', 'college_code', 'college_name', 'reason', 'is_active', 'ended_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'ended_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_no', 'cas_username');
    }
}
