<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselorClassAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'class_code',
        'class_name',
        'normalized_class_name',
        'college_code',
        'college_name',
        'source',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function normalizeClassName(?string $value): string
    {
        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? '';
        $normalized = preg_replace('/班$/u', '', $normalized) ?? '';

        return mb_strtolower($normalized);
    }
}
