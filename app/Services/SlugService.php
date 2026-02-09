<?php

namespace App\Services;

use App\Models\Tour;
use Illuminate\Support\Str;

class SlugService
{
    /**
     * คำที่ไม่ต้องใส่ใน slug (stop words)
     * ตัดออกเพื่อให้ slug กระชับ
     */
    private static array $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been',
        'this', 'that', 'these', 'those', 'it', 'its',
        'has', 'have', 'had', 'do', 'does', 'did',
        'will', 'would', 'could', 'should', 'may', 'might',
        'not', 'no', 'so', 'if', 'then', 'than', 'too', 'very',
        'just', 'about', 'also', 'more', 'most', 'only',
        'tour', 'trip', 'travel', 'day', 'days', 'night', 'nights',
        'direct', 'flight', 'fly', 'flying',
        'beautiful', 'beauty', 'pretty', 'nice', 'good', 'great', 'best',
        'special', 'new', 'old', 'big', 'small',
        'airlines', 'airline',
        'shop', 'shopping', 'store',
        'directly', 'pictures', 'photos', 'photo', 'picture',
        'every', 'all', 'each', 'many', 'much', 'some',
        'like', 'love', 'want', 'need', 'get', 'go', 'going',
        'come', 'coming', 'see', 'look', 'looking',
        'full', 'complete', 'whole', 'entire',
        'free', 'included', 'including', 'include',
        'price', 'cheap', 'expensive', 'cost',
        'program', 'programme', 'package', 'plan',
    ];

    /**
     * ความยาวสูงสุดของส่วน slug (ไม่รวม tour_code prefix)
     */
    private static int $maxSlugPartLength = 50;

    /**
     * แปลงชื่อทัวร์ภาษาไทยเป็นภาษาอังกฤษสำหรับทำ slug
     * ใช้ Google Translate API ฟรี
     */
    public static function translateToEnglish(string $text): string
    {
        // ถ้าไม่มีภาษาไทยเลย → ไม่ต้องแปล
        if (!preg_match('/[\x{0E00}-\x{0E7F}]/u', $text)) {
            return $text;
        }

        try {
            $url = 'https://translate.googleapis.com/translate_a/single?' . http_build_query([
                'client' => 'gtx',
                'sl' => 'th',
                'tl' => 'en',
                'dt' => 't',
                'q' => $text,
            ]);

            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' => "User-Agent: Mozilla/5.0\r\n",
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response) {
                $data = json_decode($response, true);
                if (isset($data[0]) && is_array($data[0])) {
                    $translated = '';
                    foreach ($data[0] as $segment) {
                        if (isset($segment[0])) {
                            $translated .= $segment[0];
                        }
                    }
                    if (!empty(trim($translated))) {
                        return trim($translated);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('SlugService: Translation failed', ['error' => $e->getMessage()]);
        }

        return $text;
    }

    /**
     * ตัดคำ stop words และจำกัดความยาว
     * เช่น "Direct flight to Lijiang Dali the charm of Yunnan 5 days 4 nights"
     * → "lijiang-dali-charm-yunnan"
     */
    public static function cleanAndTruncate(string $text): string
    {
        // ลบ special characters (**ทัวร์ลงร้าน** เป็นต้น)
        $text = preg_replace('/\*+/', '', $text);
        $text = preg_replace('/[()（）\[\]【】]/', '', $text);
        
        // ลบตัวเลขที่เป็นวัน/คืน เช่น "5 วัน 4 คืน", "5d 4n", "5 days 4 nights"
        $text = preg_replace('/\d+\s*(วัน|คืน|days?|nights?|d|n)\b/i', '', $text);
        
        // ลบ airline codes ท้ายเช่น (DR), (TG), (FD)
        $text = preg_replace('/\([A-Z]{2}\)\s*$/', '', $text);
        
        // ลบ ... และ special punctuation
        $text = preg_replace('/\.{2,}/', ' ', $text);
        
        // สร้าง slug ดิบ
        $slug = Str::slug($text);
        
        if (empty($slug)) {
            return '';
        }

        // แยกเป็นคำ
        $words = explode('-', $slug);
        
        // ตัด stop words
        $filteredWords = [];
        foreach ($words as $word) {
            if (empty($word)) continue;
            if (in_array($word, self::$stopWords)) continue;
            if (is_numeric($word)) continue;
            if (strlen($word) <= 1) continue;
            $filteredWords[] = $word;
        }

        // ถ้าตัดหมด → เอาคำเดิมกลับ (ไม่รวม stop words ทั่วไป)
        if (empty($filteredWords)) {
            $filteredWords = array_filter($words, fn($w) => strlen($w) > 1 && !is_numeric($w));
            $filteredWords = array_values($filteredWords);
        }

        // จำกัดจำนวนคำ (ไม่เกิน 6 คำ) และความยาวรวมไม่เกิน maxSlugPartLength
        $result = '';
        $wordCount = 0;
        foreach ($filteredWords as $word) {
            $tentative = $result ? $result . '-' . $word : $word;
            if (strlen($tentative) > self::$maxSlugPartLength) {
                break;
            }
            $result = $tentative;
            $wordCount++;
            if ($wordCount >= 6) {
                break;
            }
        }

        return $result;
    }

    /**
     * สร้าง slug จาก title
     * Format: {tour_code}-{translated-title-keywords}
     * เช่น: ytgdr250225-lijiang-dali-charm-yunnan
     * 
     * - tour_code เป็น prefix เสมอ
     * - ถ้ามีภาษาไทย → แปลเป็นอังกฤษก่อน
     * - ตัด stop words, จำกัดความยาว (~60 ตัวอักษร)
     * - ตรวจสอบไม่ให้ซ้ำ
     */
    public static function generateSlug(string $title, ?int $excludeTourId = null, ?string $tourCode = null): string
    {
        // 1. สร้าง prefix จาก tour_code
        $prefix = '';
        if ($tourCode) {
            $prefix = Str::slug($tourCode); // เช่น YTGDR250225 → ytgdr250225
        }

        // 2. แปลภาษาไทย → อังกฤษ (ถ้ามี)
        $hasThai = (bool) preg_match('/[\x{0E00}-\x{0E7F}]/u', $title);
        
        if ($hasThai) {
            $translated = self::translateToEnglish($title);
            $slugPart = self::cleanAndTruncate($translated);
        } else {
            $slugPart = self::cleanAndTruncate($title);
        }

        // 3. รวม prefix + slug
        if ($prefix && $slugPart) {
            $slug = $prefix . '-' . $slugPart;
        } elseif ($prefix) {
            $slug = $prefix;
        } elseif ($slugPart) {
            $slug = $slugPart;
        } else {
            $slug = 'tour-' . uniqid();
        }

        // 4. ตรวจสอบไม่ให้ซ้ำ
        return self::ensureUnique($slug, $excludeTourId);
    }

    /**
     * ตรวจสอบ slug ไม่ให้ซ้ำ ถ้าซ้ำจะต่อท้าย -1, -2, -3...
     */
    public static function ensureUnique(string $slug, ?int $excludeTourId = null): string
    {
        $originalSlug = $slug;
        $count = 1;

        $query = Tour::where('slug', $slug);
        if ($excludeTourId) {
            $query->where('id', '!=', $excludeTourId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $count++;
            $query = Tour::where('slug', $slug);
            if ($excludeTourId) {
                $query->where('id', '!=', $excludeTourId);
            }
        }

        return $slug;
    }

    /**
     * ตรวจสอบว่า slug ซ้ำหรือไม่
     */
    public static function isUnique(string $slug, ?int $excludeTourId = null): bool
    {
        $query = Tour::where('slug', $slug);
        if ($excludeTourId) {
            $query->where('id', '!=', $excludeTourId);
        }
        return !$query->exists();
    }
}
