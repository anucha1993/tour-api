<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SearchKeyword extends Model
{
    protected $fillable = ['keyword', 'search_count', 'result_count', 'last_searched_at'];

    protected $casts = [
        'last_searched_at' => 'datetime',
    ];

    /**
     * Record a search keyword — uses UPSERT to safely increment count.
     * Throttled: same keyword only increments once per 5 minutes to prevent spam.
     */
    public static function recordSearch(string $keyword, int $resultCount = 0): void
    {
        $keyword = mb_strtolower(trim($keyword));
        if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 100) return;

        // Throttle: skip if this keyword was recorded less than 5 min ago
        $existing = static::where('keyword', $keyword)->first();
        if ($existing && $existing->last_searched_at && $existing->last_searched_at->diffInMinutes(now()) < 5) {
            return; // Too soon, skip increment
        }

        if ($existing) {
            $existing->increment('search_count');
            $existing->update([
                'result_count' => $resultCount,
                'last_searched_at' => now(),
            ]);
        } else {
            static::create([
                'keyword' => $keyword,
                'search_count' => 1,
                'result_count' => $resultCount,
                'last_searched_at' => now(),
            ]);
        }
    }
}
