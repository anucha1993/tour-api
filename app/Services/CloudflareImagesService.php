<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\WebpEncoder;

class CloudflareImagesService
{
    protected ?string $accountId;
    protected ?string $accountHash;
    protected ?string $apiToken;
    protected string $baseUrl;

    public function __construct()
    {
        $this->accountId = config('services.cloudflare.account_id');
        $this->accountHash = config('services.cloudflare.account_hash');
        $this->apiToken = config('services.cloudflare.images_token');
        $this->baseUrl = $this->accountId 
            ? "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/images/v1"
            : '';
    }

    /**
     * ตรวจสอบว่า Cloudflare Images ถูกตั้งค่าหรือยัง
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountId) && !empty($this->apiToken);
    }

    /**
     * ดาวน์โหลด image จาก URL, แปลงเป็น webp และอัพโหลดไป Cloudflare
     *
     * @param string $imageUrl URL ของ image ต้นทาง
     * @param string|null $customId ID ที่กำหนดเอง (optional)
     * @param array $metadata metadata เพิ่มเติม
     * @return array|null ข้อมูล response จาก Cloudflare หรือ null ถ้าล้มเหลว
     */
    public function uploadFromUrl(string $imageUrl, ?string $customId = null, array $metadata = []): ?array
    {
        try {
            // ดาวน์โหลด image
            $imageContent = $this->downloadImage($imageUrl);
            if (!$imageContent) {
                Log::error("Failed to download image: {$imageUrl}");
                return null;
            }

            // แปลงเป็น webp
            $webpContent = $this->convertToWebp($imageContent);
            if (!$webpContent) {
                Log::error("Failed to convert image to webp: {$imageUrl}");
                return null;
            }

            // อัพโหลดไป Cloudflare
            return $this->uploadToCloudflare($webpContent, $customId, $metadata);
        } catch (\Exception $e) {
            Log::error("Error uploading image to Cloudflare: " . $e->getMessage(), [
                'url' => $imageUrl,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * ดาวน์โหลด image จาก URL
     */
    protected function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning("Failed to download image, status: " . $response->status(), ['url' => $url]);
            return null;
        } catch (\Exception $e) {
            Log::error("Exception downloading image: " . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    /**
     * แปลง image เป็น webp โดยใช้ Intervention Image
     */
    protected function convertToWebp(string $imageContent, int $quality = 85): ?string
    {
        try {
            $image = Image::read($imageContent);
            
            // Encode เป็น webp
            $encoded = $image->encode(new WebpEncoder(quality: $quality));
            
            return $encoded->toString();
        } catch (\Exception $e) {
            Log::error("Exception converting to webp: " . $e->getMessage());
            return null;
        }
    }

    /**
     * อัพโหลด image ไป Cloudflare Images
     */
    protected function uploadToCloudflare(string $imageContent, ?string $customId = null, array $metadata = []): ?array
    {
        try {
            $multipart = [
                [
                    'name' => 'file',
                    'contents' => $imageContent,
                    'filename' => ($customId ?? uniqid()) . '.webp',
                ],
            ];

            // เพิ่ม metadata
            if (!empty($metadata)) {
                $multipart[] = [
                    'name' => 'metadata',
                    'contents' => json_encode($metadata),
                ];
            }

            // เพิ่ม custom id
            if ($customId) {
                $multipart[] = [
                    'name' => 'id',
                    'contents' => $customId,
                ];
            }

            $response = Http::withToken($this->apiToken)
                ->timeout(60)
                ->asMultipart()
                ->post($this->baseUrl, $multipart);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] ?? false) {
                    return $data['result'];
                }
            }

            Log::warning("Cloudflare upload failed", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("Exception uploading to Cloudflare: " . $e->getMessage());
            return null;
        }
    }

    /**
     * อัพโหลด image จากไฟล์โดยตรง (ไม่ใช่ URL)
     *
     * @param \Illuminate\Http\UploadedFile|string $file ไฟล์ที่อัพโหลด หรือ path ของไฟล์
     * @param string|null $customId ID ที่กำหนดเอง (optional)
     * @param array $metadata metadata เพิ่มเติม
     * @return array|null ข้อมูล response จาก Cloudflare หรือ null ถ้าล้มเหลว
     */
    public function uploadFromFile($file, ?string $customId = null, array $metadata = []): ?array
    {
        try {
            // Get file contents
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $imageContent = file_get_contents($file->getRealPath());
            } else {
                $imageContent = file_get_contents($file);
            }

            if (!$imageContent) {
                Log::error("Failed to read file");
                return null;
            }

            // แปลงเป็น webp
            $webpContent = $this->convertToWebp($imageContent);
            if (!$webpContent) {
                Log::error("Failed to convert image to webp");
                return null;
            }

            // อัพโหลดไป Cloudflare
            return $this->uploadToCloudflare($webpContent, $customId, $metadata);
        } catch (\Exception $e) {
            Log::error("Error uploading file to Cloudflare: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * ลบ image จาก Cloudflare
     */
    public function delete(string $imageId): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->delete("{$this->baseUrl}/{$imageId}");

            return $response->successful() && ($response->json()['success'] ?? false);
        } catch (\Exception $e) {
            Log::error("Exception deleting from Cloudflare: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ดึงข้อมูล image จาก Cloudflare
     */
    public function get(string $imageId): ?array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/{$imageId}");

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] ?? false) {
                    return $data['result'];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Exception getting from Cloudflare: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ดึง URL สำหรับแสดงผล image
     */
    public function getDisplayUrl(string $imageId, string $variant = 'public'): string
    {
        return "https://imagedelivery.net/{$this->accountHash}/{$imageId}/{$variant}";
    }

    /**
     * ดึง URL สำหรับ thumbnail
     * ใช้ Cloudflare Image Resizing หรือ variant 'thumbnail'
     */
    public function getThumbnailUrl(string $imageId, string $variant = 'thumbnail'): string
    {
        return "https://imagedelivery.net/{$this->accountHash}/{$imageId}/{$variant}";
    }

    /**
     * อัพโหลด image พร้อมสร้าง thumbnail
     * 
     * @param string $imageUrl URL ของ image ต้นทาง
     * @param string|null $customId ID ที่กำหนดเอง
     * @param array $metadata metadata เพิ่มเติม
     * @param int $thumbnailWidth ความกว้าง thumbnail (px)
     * @return array|null ['original' => [...], 'thumbnail' => [...]] หรือ null
     */
    public function uploadWithThumbnail(string $imageUrl, ?string $customId = null, array $metadata = [], int $thumbnailWidth = 400): ?array
    {
        try {
            // ดาวน์โหลด image
            $imageContent = $this->downloadImage($imageUrl);
            if (!$imageContent) {
                Log::error("Failed to download image: {$imageUrl}");
                return null;
            }

            // สร้าง original webp
            $originalWebp = $this->convertToWebp($imageContent);
            if (!$originalWebp) {
                Log::error("Failed to convert image to webp: {$imageUrl}");
                return null;
            }

            // สร้าง thumbnail webp
            $thumbnailWebp = $this->createThumbnail($imageContent, $thumbnailWidth);

            // อัพโหลด original
            $originalResult = $this->uploadToCloudflare($originalWebp, $customId, $metadata);
            
            // อัพโหลด thumbnail (ถ้าสร้างได้)
            $thumbnailResult = null;
            if ($thumbnailWebp) {
                $thumbId = $customId ? "{$customId}_thumb" : null;
                $thumbnailResult = $this->uploadToCloudflare($thumbnailWebp, $thumbId, array_merge($metadata, ['type' => 'thumbnail']));
            }

            return [
                'original' => $originalResult,
                'thumbnail' => $thumbnailResult,
            ];
        } catch (\Exception $e) {
            Log::error("Error uploading image with thumbnail: " . $e->getMessage(), [
                'url' => $imageUrl,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * สร้าง thumbnail จาก image content
     */
    protected function createThumbnail(string $imageContent, int $width = 400, int $quality = 80): ?string
    {
        try {
            $image = Image::read($imageContent);
            
            // Resize proportionally
            $image->scale(width: $width);
            
            // Encode เป็น webp
            $encoded = $image->encode(new WebpEncoder(quality: $quality));
            
            return $encoded->toString();
        } catch (\Exception $e) {
            Log::error("Exception creating thumbnail: " . $e->getMessage());
            return null;
        }
    }

    /**
     * แสดงรายการ images ทั้งหมด
     */
    public function list(int $perPage = 100, ?string $continuationToken = null): ?array
    {
        try {
            $params = ['per_page' => $perPage];
            if ($continuationToken) {
                $params['continuation_token'] = $continuationToken;
            }

            $response = Http::withToken($this->apiToken)
                ->get($this->baseUrl, $params);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Exception listing Cloudflare images: " . $e->getMessage());
            return null;
        }
    }
}
