<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FestivalHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'start_date',
        'end_date',
        'image_url',
        'image_cf_id',
        'cover_image_url',
        'cover_image_cf_id',
        'cover_image_position',
        'badge_text',
        'badge_color',
        'badge_icon',
        'display_modes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'display_modes' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateSlug($model->name);
            }
        });

        static::updating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateSlug($model->name);
            }
        });
    }

    /**
     * Generate a URL-safe slug, supporting Thai characters
     */
    public static function generateSlug(string $name): string
    {
        // Try standard slug first
        $slug = Str::slug($name);

        // If empty (e.g. Thai-only text), transliterate manually
        if (empty($slug)) {
            // Replace non-letter/digit/mark chars with hyphens, keep unicode letters, digits, and combining marks (Thai vowels/tones)
            $slug = preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '-', $name);
            $slug = trim($slug, '-');
            $slug = mb_strtolower($slug, 'UTF-8');
        }

        // Fallback
        if (empty($slug)) {
            $slug = 'festival-' . time();
        }

        // Ensure unique
        $base = $slug;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get tour IDs that have periods matching this holiday's date range
     */
    public function getMatchingTourIds()
    {
        return Tour::where('status', 'active')
            ->whereHas('periods', function ($q) {
                $q->where('status', 'open')
                  ->where('is_visible', true)
                  ->where('start_date', '>=', $this->start_date->toDateString())
                  ->where('start_date', '<=', $this->end_date->toDateString());
            })
            ->pluck('id');
    }

    /**
     * Get tours matching this holiday with eager loading
     */
    public function getMatchingTours($perPage = 10, array $filters = [])
    {
        $query = Tour::query()
            ->where('status', 'active')
            ->whereHas('periods', function ($q) {
                $q->where('status', 'open')
                  ->where('is_visible', true)
                  ->where('start_date', '>=', $this->start_date->toDateString())
                  ->where('start_date', '<=', $this->end_date->toDateString());
            });

        // Apply user filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tour_code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['country_id'])) {
            $query->where('primary_country_id', $filters['country_id']);
        }

        if (!empty($filters['city_id'])) {
            $cityIds = array_filter(array_map('trim', explode(',', $filters['city_id'])));
            $query->whereHas('cities', function ($q) use ($cityIds) {
                $q->whereIn('cities.id', $cityIds);
            });
        }

        if (!empty($filters['price_min'])) {
            $query->where('min_price', '>=', $filters['price_min']);
        }

        if (!empty($filters['price_max'])) {
            $query->where('min_price', '<=', $filters['price_max']);
        }

        if (!empty($filters['airline_id'])) {
            $query->whereHas('transports', function ($q) use ($filters) {
                $q->where('transport_id', $filters['airline_id']);
            });
        }

        if (!empty($filters['departure_month'])) {
            $month = $filters['departure_month'];
            $query->whereHas('periods', function ($q) use ($month) {
                $q->where('status', 'open')
                  ->where('is_visible', true)
                  ->whereRaw("DATE_FORMAT(start_date, '%Y-%m') = ?", [$month]);
            });
        }

        if (!empty($filters['departure_date_from'])) {
            $query->whereHas('periods', function ($q) use ($filters) {
                $q->where('status', 'open')
                  ->where('is_visible', true)
                  ->where('start_date', '>=', $filters['departure_date_from']);
            });
        }

        if (!empty($filters['departure_date_to'])) {
            $query->whereHas('periods', function ($q) use ($filters) {
                $q->where('status', 'open')
                  ->where('is_visible', true)
                  ->where('start_date', '<=', $filters['departure_date_to']);
            });
        }

        if (!empty($filters['min_seats'])) {
            $query->whereHas('periods', function ($q) use ($filters) {
                $q->where('status', 'open')
                  ->where('is_visible', true)
                  ->whereRaw('(capacity - booked) >= ?', [(int) $filters['min_seats']]);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'departure_date';
        switch ($sortBy) {
            case 'price_asc':
                $query->orderByRaw('COALESCE(min_price, 9999999) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE(min_price, 0) DESC');
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'popular':
                $query->orderByDesc('view_count');
                break;
            case 'departure_date':
            default:
                $query->orderBy(
                    \DB::raw('(SELECT MIN(start_date) FROM periods WHERE periods.tour_id = tours.id AND start_date >= "' . $this->start_date->toDateString() . '" AND start_date <= "' . $this->end_date->toDateString() . '" AND status = "open")'),
                    'asc'
                );
                break;
        }

        $eagerLoads = [
            'primaryCountry:id,name_th,name_en,iso2,flag_emoji',
            'cities:id,name_th,name_en,slug',
            'transports' => function ($q) {
                $q->orderBy('sort_order');
            },
            'transports.transport:id,code,name,image',
            'periods' => function ($q) {
                $q->where('status', 'open')
                  ->where('is_visible', true)
                  ->orderBy('start_date');
            },
            'periods.offer.promotion',
            'itineraries' => function ($q) {
                $q->select('id', 'tour_id', 'has_breakfast', 'has_lunch', 'has_dinner');
            },
        ];

        return $query->with($eagerLoads)->paginate($perPage);
    }

    /**
     * Get matching period IDs for badge display
     */
    public function getMatchingPeriodIds()
    {
        return \DB::table('periods')
            ->join('tours', 'tours.id', '=', 'periods.tour_id')
            ->where('tours.status', 'active')
            ->where('periods.status', 'open')
            ->where('periods.is_visible', true)
            ->where('periods.start_date', '>=', $this->start_date->toDateString())
            ->where('periods.start_date', '<=', $this->end_date->toDateString())
            ->pluck('periods.id');
    }

    /**
     * Format date range for display
     */
    public function getDateRangeTextAttribute(): string
    {
        $thMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

        $start = $this->start_date;
        $end = $this->end_date;
        $buddhistYear = $start->year + 543;

        if ($start->month === $end->month && $start->year === $end->year) {
            return $start->day . '-' . $end->day . ' ' . $thMonths[$start->month] . ' ' . $buddhistYear;
        }

        return $start->day . ' ' . $thMonths[$start->month] . ' - ' . $end->day . ' ' . $thMonths[$end->month] . ' ' . $buddhistYear;
    }
}
