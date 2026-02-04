/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `airports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `airports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(4) NOT NULL COMMENT 'IATA code (BKK, DMK, HKG)',
  `name_en` varchar(150) NOT NULL,
  `name_th` varchar(150) DEFAULT NULL,
  `city_en` varchar(100) DEFAULT NULL,
  `city_th` varchar(100) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL COMMENT 'Asia/Bangkok',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `airports_code_unique` (`code`),
  KEY `airports_country_id_index` (`country_id`),
  KEY `airports_is_active_index` (`is_active`),
  KEY `airports_city_id_index` (`city_id`),
  CONSTRAINT `airports_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `airports_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(150) NOT NULL,
  `name_th` varchar(150) DEFAULT NULL,
  `slug` varchar(150) NOT NULL,
  `country_id` bigint(20) unsigned NOT NULL,
  `description` text DEFAULT NULL COMMENT 'City description',
  `is_popular` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Popular destination',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cities_slug_unique` (`slug`),
  KEY `cities_country_id_index` (`country_id`),
  KEY `cities_is_active_index` (`is_active`),
  KEY `cities_is_popular_index` (`is_popular`),
  CONSTRAINT `cities_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `iso2` varchar(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2 (TH, JP, CN)',
  `iso3` varchar(3) NOT NULL COMMENT 'ISO 3166-1 alpha-3 (THA, JPN, CHN)',
  `name_en` varchar(100) NOT NULL,
  `name_th` varchar(100) DEFAULT NULL,
  `slug` varchar(100) NOT NULL COMMENT 'URL slug: thailand, japan',
  `region` varchar(50) DEFAULT NULL COMMENT 'Asia, Europe, etc.',
  `flag_emoji` varchar(10) DEFAULT NULL COMMENT '?? ??',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_iso2_unique` (`iso2`),
  UNIQUE KEY `countries_iso3_unique` (`iso3`),
  UNIQUE KEY `countries_slug_unique` (`slug`),
  KEY `countries_is_active_index` (`is_active`),
  KEY `countries_region_index` (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gallery_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gallery_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cloudflare_id` varchar(255) DEFAULT NULL COMMENT 'Cloudflare Image ID',
  `url` varchar(500) NOT NULL COMMENT 'Full image URL',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT 'Thumbnail URL (400x267)',
  `filename` varchar(255) NOT NULL COMMENT 'Original filename',
  `alt` varchar(255) DEFAULT NULL COMMENT 'Alt text for SEO',
  `caption` varchar(500) DEFAULT NULL COMMENT 'Image caption',
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Tags for matching: ["ซากุระ", "ฟูจิ", "วัด"]' CHECK (json_valid(`tags`)),
  `width` int(10) unsigned NOT NULL DEFAULT 1200 COMMENT 'Image width in px',
  `height` int(10) unsigned NOT NULL DEFAULT 800 COMMENT 'Image height in px',
  `file_size` int(10) unsigned DEFAULT NULL COMMENT 'File size in bytes',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gallery_images_country_id_is_active_index` (`country_id`,`is_active`),
  KEY `gallery_images_city_id_is_active_index` (`city_id`,`is_active`),
  KEY `gallery_images_is_active_index` (`is_active`),
  CONSTRAINT `gallery_images_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gallery_images_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `offer_promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offer_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` bigint(20) unsigned NOT NULL,
  `promo_code` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('discount_amount','discount_percent','freebie') NOT NULL DEFAULT 'discount_amount',
  `value` decimal(10,2) DEFAULT NULL COMMENT '500 หรือ 10%',
  `apply_to` enum('per_pax','per_booking') NOT NULL DEFAULT 'per_pax',
  `start_at` timestamp NULL DEFAULT NULL,
  `end_at` timestamp NULL DEFAULT NULL,
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"min_pax": 2, "booking_before_days": 30}' CHECK (json_valid(`conditions`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `offer_promotions_offer_id_index` (`offer_id`),
  KEY `offer_promotions_start_at_end_at_is_active_index` (`start_at`,`end_at`,`is_active`),
  CONSTRAINT `offer_promotions_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_id` bigint(20) unsigned NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'THB',
  `price_adult` decimal(10,2) NOT NULL COMMENT 'ราคาผู้ใหญ่',
  `discount_adult` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดผู้ใหญ่พัก 2-3',
  `price_child` decimal(10,2) DEFAULT NULL COMMENT 'ราคาเด็ก',
  `discount_child_bed` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดเด็กมีเตียง',
  `price_child_nobed` decimal(10,2) DEFAULT NULL COMMENT 'เด็กไม่เสริมเตียง',
  `discount_child_nobed` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดเด็กไม่มีเตียง',
  `price_infant` decimal(10,2) DEFAULT NULL COMMENT 'ทารก',
  `price_joinland` decimal(10,2) DEFAULT NULL COMMENT 'ไม่รวมตั๋วเครื่องบิน',
  `price_single` decimal(10,2) DEFAULT NULL COMMENT 'พักเดี่ยวเพิ่ม',
  `discount_single` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดพักเดี่ยว',
  `deposit` decimal(10,2) DEFAULT NULL COMMENT 'มัดจำ',
  `commission_agent` decimal(10,2) DEFAULT NULL,
  `commission_sale` decimal(10,2) DEFAULT NULL,
  `cancellation_policy` text DEFAULT NULL COMMENT 'เงื่อนไขยกเลิก - Required',
  `refund_policy` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `promo_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อโปรโมชั่น',
  `promo_start_date` date DEFAULT NULL COMMENT 'วันเริ่มโปรโมชั่น',
  `promo_end_date` date DEFAULT NULL COMMENT 'วันสิ้นสุดโปรโมชั่น',
  `promo_quota` int(11) DEFAULT NULL COMMENT 'จำนวนโปรโมชั่นที่ใช้ได้',
  `promo_used` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนโปรโมชั่นที่ใช้ไปแล้ว',
  `promotion_id` bigint(20) unsigned DEFAULT NULL,
  `ttl_minutes` smallint(5) unsigned NOT NULL DEFAULT 10 COMMENT 'อายุข้อมูลราคา (นาที)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offers_period_id_unique` (`period_id`),
  KEY `offers_promotion_id_foreign` (`promotion_id`),
  CONSTRAINT `offers_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offers_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `outbound_api_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outbound_api_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `action` enum('fetch_tours','fetch_detail','fetch_periods','fetch_itineraries','check_availability','hold','confirm','cancel','modify','check_status','ack_sync','health_check','oauth_token') DEFAULT NULL,
  `endpoint` varchar(500) NOT NULL,
  `method` enum('GET','POST','PUT','PATCH','DELETE') NOT NULL,
  `request_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_headers`)),
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_body`)),
  `response_code` int(11) DEFAULT NULL,
  `response_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_headers`)),
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_body`)),
  `response_time_ms` int(11) DEFAULT NULL,
  `tour_id` bigint(20) unsigned DEFAULT NULL,
  `sync_log_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('success','failed','timeout','error') NOT NULL,
  `error_type` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_of_id` bigint(20) unsigned DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `outbound_api_logs_tour_id_foreign` (`tour_id`),
  KEY `outbound_api_logs_sync_log_id_foreign` (`sync_log_id`),
  KEY `outbound_api_logs_action_status_created_at_index` (`action`,`status`,`created_at`),
  KEY `outbound_api_logs_wholesaler_id_created_at_index` (`wholesaler_id`,`created_at`),
  CONSTRAINT `outbound_api_logs_sync_log_id_foreign` FOREIGN KEY (`sync_log_id`) REFERENCES `sync_logs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outbound_api_logs_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outbound_api_logs_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_api_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `api_key` varchar(64) NOT NULL COMMENT 'Public key',
  `api_secret` varchar(128) NOT NULL COMMENT 'Secret สำหรับ signature',
  `name` varchar(100) DEFAULT NULL COMMENT 'Production, Test, etc.',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `partner_api_keys_api_key_unique` (`api_key`),
  KEY `partner_api_keys_wholesaler_id_index` (`wholesaler_id`),
  KEY `partner_api_keys_is_active_index` (`is_active`),
  CONSTRAINT `partner_api_keys_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_ip_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_ip_whitelist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4 หรือ IPv6',
  `description` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `partner_ip_whitelist_wholesaler_id_ip_address_unique` (`wholesaler_id`,`ip_address`),
  KEY `partner_ip_whitelist_wholesaler_id_index` (`wholesaler_id`),
  CONSTRAINT `partner_ip_whitelist_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` bigint(20) unsigned NOT NULL,
  `data_source` enum('api','manual') NOT NULL DEFAULT 'manual' COMMENT 'api = มาจาก Wholesaler API, manual = สร้างเอง',
  `sync_status` enum('active','paused','disconnected') NOT NULL DEFAULT 'active' COMMENT 'active = กำลัง sync, paused = หยุดชั่วคราว, disconnected = ไม่ sync',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `sync_hash` varchar(64) DEFAULT NULL COMMENT 'MD5/SHA256 hash ของข้อมูลจาก API เพื่อตรวจสอบการเปลี่ยนแปลง',
  `external_updated_at` timestamp NULL DEFAULT NULL COMMENT 'updated_at จากฝั่ง Wholesaler API',
  `external_id` varchar(50) NOT NULL COMMENT 'รหัสจาก Wholesale',
  `period_code` varchar(50) NOT NULL COMMENT 'รหัสรอบ: CAN-260313A-AQ',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `capacity` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'ที่นั่งทั้งหมด',
  `booked` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'จองแล้ว',
  `available` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'คงเหลือ',
  `status` enum('open','closed','sold_out','cancelled') NOT NULL DEFAULT 'open',
  `guarantee_status` enum('pending','guaranteed','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'pending = รอยืนยัน, guaranteed = ยืนยันเดินทาง, cancelled = ยกเลิก',
  `is_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะแสดง on/off',
  `sale_status` enum('available','booking','sold_out') NOT NULL DEFAULT 'available' COMMENT 'สถานะวางขาย: ไลน์(available), จอง(booking), เต็ม(sold_out)',
  `updated_at_source` timestamp NULL DEFAULT NULL COMMENT 'เวลาอัปเดตจาก Wholesale',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `periods_tour_id_external_id_unique` (`tour_id`,`external_id`),
  KEY `periods_start_date_status_index` (`start_date`,`status`),
  KEY `periods_status_available_index` (`status`,`available`),
  KEY `periods_period_code_index` (`period_code`),
  KEY `idx_periods_sync` (`data_source`,`sync_status`),
  KEY `idx_periods_external` (`tour_id`,`external_id`),
  CONSTRAINT `periods_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` bigint(20) unsigned NOT NULL,
  `price_adult_old` decimal(10,2) DEFAULT NULL,
  `price_adult_new` decimal(10,2) DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT NULL COMMENT 'sync / admin / api',
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `price_history_offer_id_index` (`offer_id`),
  KEY `price_history_changed_at_index` (`changed_at`),
  CONSTRAINT `price_history_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('discount_amount','discount_percent','free_gift','installment','special') NOT NULL DEFAULT 'special',
  `discount_value` decimal(10,2) DEFAULT NULL COMMENT 'ส่วนลด (บาท หรือ %)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `section_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_definitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `section_name` varchar(50) NOT NULL COMMENT 'tour, period, pricing, content, media, seo',
  `field_name` varchar(100) NOT NULL,
  `data_type` enum('TEXT','INT','DECIMAL','DATE','DATETIME','BOOLEAN','ENUM','ARRAY_TEXT','ARRAY_INT','ARRAY_DECIMAL','JSON') NOT NULL,
  `enum_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'For ENUM type: ["join", "incentive"]' CHECK (json_valid(`enum_values`)),
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `default_value` varchar(500) DEFAULT NULL,
  `validation_rules` varchar(500) DEFAULT NULL COMMENT 'Laravel validation rules',
  `lookup_table` varchar(100) DEFAULT NULL COMMENT 'countries, cities, transports',
  `lookup_match_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["name_en", "name_th", "iso2"]' CHECK (json_valid(`lookup_match_fields`)),
  `lookup_return_field` varchar(100) NOT NULL DEFAULT 'id',
  `lookup_create_if_not_found` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'System fields cannot be deleted',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_definitions_section_name_field_name_unique` (`section_name`,`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_group_key_index` (`group`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_batch_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_batch_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sync_batch_id` bigint(20) unsigned NOT NULL,
  `entity_type` enum('tour','departure') NOT NULL,
  `external_id` varchar(50) NOT NULL,
  `result` enum('created','updated','skipped','error') NOT NULL,
  `error_code` varchar(10) DEFAULT NULL COMMENT 'E001, E002, ...',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sync_batch_items_sync_batch_id_index` (`sync_batch_id`),
  KEY `sync_batch_items_entity_type_external_id_index` (`entity_type`,`external_id`),
  CONSTRAINT `sync_batch_items_sync_batch_id_foreign` FOREIGN KEY (`sync_batch_id`) REFERENCES `sync_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` varchar(100) NOT NULL COMMENT 'Idempotency key',
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `mode` enum('delta','full') NOT NULL DEFAULT 'delta',
  `status` enum('pending','processing','completed','partial','failed') NOT NULL DEFAULT 'pending',
  `total_items` int(10) unsigned NOT NULL DEFAULT 0,
  `success_count` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `skipped_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'เวลาที่ partner ส่ง',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT 'เวลาที่ประมวลผลเสร็จ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_batches_request_id_unique` (`request_id`),
  KEY `sync_batches_wholesaler_id_index` (`wholesaler_id`),
  KEY `sync_batches_status_index` (`status`),
  KEY `sync_batches_created_at_index` (`created_at`),
  CONSTRAINT `sync_batches_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_cursors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_cursors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `sync_type` enum('tours','periods','prices','all') NOT NULL DEFAULT 'all',
  `cursor_value` varchar(500) DEFAULT NULL,
  `cursor_type` enum('string','timestamp','integer') NOT NULL DEFAULT 'string',
  `last_sync_id` varchar(100) DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `total_received` int(11) NOT NULL DEFAULT 0,
  `last_batch_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_cursors_wholesaler_id_sync_type_unique` (`wholesaler_id`,`sync_type`),
  CONSTRAINT `sync_cursors_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_error_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sync_log_id` bigint(20) unsigned NOT NULL,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `external_tour_code` varchar(200) DEFAULT NULL,
  `tour_id` bigint(20) unsigned DEFAULT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `field_name` varchar(100) DEFAULT NULL,
  `error_type` enum('mapping','validation','lookup','type_cast','api','database','unknown') NOT NULL DEFAULT 'unknown',
  `error_message` text NOT NULL,
  `received_value` text DEFAULT NULL,
  `expected_type` varchar(50) DEFAULT NULL,
  `raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_data`)),
  `stack_trace` text DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sync_error_logs_sync_log_id_foreign` (`sync_log_id`),
  KEY `sync_error_logs_tour_id_foreign` (`tour_id`),
  KEY `sync_error_logs_resolved_by_foreign` (`resolved_by`),
  KEY `sync_error_logs_wholesaler_id_is_resolved_created_at_index` (`wholesaler_id`,`is_resolved`,`created_at`),
  CONSTRAINT `sync_error_logs_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sync_error_logs_sync_log_id_foreign` FOREIGN KEY (`sync_log_id`) REFERENCES `sync_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sync_error_logs_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sync_error_logs_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `sync_type` enum('full','incremental','webhook','manual') NOT NULL DEFAULT 'incremental',
  `sync_id` varchar(100) DEFAULT NULL,
  `started_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `status` enum('running','completed','failed','partial') NOT NULL DEFAULT 'running',
  `tours_received` int(11) NOT NULL DEFAULT 0,
  `tours_created` int(11) NOT NULL DEFAULT 0,
  `tours_updated` int(11) NOT NULL DEFAULT 0,
  `tours_skipped` int(11) NOT NULL DEFAULT 0,
  `tours_failed` int(11) NOT NULL DEFAULT 0,
  `periods_received` int(11) NOT NULL DEFAULT 0,
  `periods_created` int(11) NOT NULL DEFAULT 0,
  `periods_updated` int(11) NOT NULL DEFAULT 0,
  `error_count` int(11) NOT NULL DEFAULT 0,
  `error_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`error_summary`)),
  `ack_sent` tinyint(1) NOT NULL DEFAULT 0,
  `ack_sent_at` timestamp NULL DEFAULT NULL,
  `ack_accepted` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_logs_sync_id_unique` (`sync_id`),
  KEY `sync_logs_wholesaler_id_started_at_index` (`wholesaler_id`,`started_at`),
  CONSTRAINT `sync_logs_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tour_cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_cities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` bigint(20) unsigned NOT NULL,
  `city_id` bigint(20) unsigned NOT NULL,
  `country_id` bigint(20) unsigned NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `days_in_city` tinyint(3) unsigned DEFAULT NULL COMMENT 'จำนวนวันในเมืองนี้',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tour_cities_tour_id_city_id_unique` (`tour_id`,`city_id`),
  KEY `tour_cities_city_id_index` (`city_id`),
  KEY `tour_cities_country_id_index` (`country_id`),
  CONSTRAINT `tour_cities_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tour_cities_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tour_cities_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tour_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` bigint(20) unsigned NOT NULL,
  `country_id` bigint(20) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `days_in_country` tinyint(3) unsigned DEFAULT NULL COMMENT 'จำนวนวันในประเทศนี้',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tour_countries_tour_id_country_id_unique` (`tour_id`,`country_id`),
  KEY `tour_countries_country_id_is_primary_index` (`country_id`,`is_primary`),
  KEY `tour_countries_is_primary_index` (`is_primary`),
  CONSTRAINT `tour_countries_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tour_countries_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tour_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_gallery` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` bigint(20) unsigned NOT NULL,
  `url` varchar(500) NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `alt` varchar(255) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `width` smallint(5) unsigned DEFAULT NULL,
  `height` smallint(5) unsigned DEFAULT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tour_gallery_tour_id_index` (`tour_id`),
  CONSTRAINT `tour_gallery_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tour_itineraries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_itineraries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) DEFAULT NULL COMMENT 'ID from external wholesaler API',
  `data_source` varchar(50) DEFAULT NULL COMMENT 'Source: manual, tourkrub, itsawongsaeng, etc.',
  `tour_id` bigint(20) unsigned NOT NULL,
  `day_number` tinyint(3) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL COMMENT 'กรุงเทพฯ – กวางเจา',
  `description` text DEFAULT NULL,
  `places` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`places`)),
  `accommodation` varchar(150) DEFAULT NULL,
  `hotel_star` tinyint(3) unsigned DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `sort_order` smallint(5) unsigned DEFAULT NULL,
  `has_breakfast` tinyint(1) NOT NULL DEFAULT 0,
  `has_lunch` tinyint(1) NOT NULL DEFAULT 0,
  `has_dinner` tinyint(1) NOT NULL DEFAULT 0,
  `meals_note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tour_itineraries_tour_id_day_no_unique` (`tour_id`,`day_number`),
  KEY `tour_itineraries_tour_id_index` (`tour_id`),
  KEY `idx_itinerary_external_sync` (`external_id`,`data_source`),
  CONSTRAINT `tour_itineraries_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tour_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` bigint(20) unsigned NOT NULL,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL COMMENT 'ชื่อสถานที่ (fallback ถ้าไม่มี city)',
  `name_en` varchar(100) DEFAULT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tour_locations_tour_id_index` (`tour_id`),
  KEY `tour_locations_city_id_index` (`city_id`),
  CONSTRAINT `tour_locations_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tour_locations_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tour_transports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_transports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` bigint(20) unsigned NOT NULL,
  `transport_id` bigint(20) unsigned DEFAULT NULL,
  `transport_code` varchar(10) DEFAULT NULL COMMENT 'AQ, TG (fallback)',
  `transport_name` varchar(100) DEFAULT NULL,
  `flight_no` varchar(10) DEFAULT NULL COMMENT 'AQ1280',
  `route_from` varchar(4) DEFAULT NULL COMMENT 'IATA code: DMK',
  `route_to` varchar(4) DEFAULT NULL COMMENT 'IATA code: CAN',
  `depart_time` time DEFAULT NULL,
  `arrive_time` time DEFAULT NULL,
  `transport_type` enum('outbound','inbound','domestic') NOT NULL DEFAULT 'outbound',
  `day_no` tinyint(3) unsigned DEFAULT NULL COMMENT 'วันที่เท่าไหร่',
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tour_transports_tour_id_index` (`tour_id`),
  KEY `tour_transports_transport_id_index` (`transport_id`),
  CONSTRAINT `tour_transports_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tour_transports_transport_id_foreign` FOREIGN KEY (`transport_id`) REFERENCES `transports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tours` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `data_source` enum('api','manual') NOT NULL DEFAULT 'manual' COMMENT 'api = มาจาก Wholesaler API, manual = สร้างเอง',
  `sync_status` enum('active','paused','disconnected') NOT NULL DEFAULT 'active' COMMENT 'active = กำลัง sync, paused = หยุดชั่วคราว, disconnected = ไม่ sync',
  `sync_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'true = ห้ามแก้ไข fields ที่ sync มา',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `sync_hash` varchar(64) DEFAULT NULL COMMENT 'MD5/SHA256 hash ของข้อมูลจาก API เพื่อตรวจสอบการเปลี่ยนแปลง',
  `external_updated_at` timestamp NULL DEFAULT NULL COMMENT 'updated_at จากฝั่ง Wholesaler API',
  `manual_override_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["title", "description"] = fields ที่แก้ไขเอง ไม่ให้ sync ทับ' CHECK (json_valid(`manual_override_fields`)),
  `external_id` varchar(50) NOT NULL COMMENT 'รหัสจาก Wholesale',
  `tour_code` varchar(50) NOT NULL COMMENT 'รหัสทัวร์ในระบบเรา',
  `wholesaler_tour_code` varchar(100) DEFAULT NULL COMMENT 'รหัสทัวร์จาก Wholesaler',
  `title` varchar(255) NOT NULL,
  `tour_type` enum('join','incentive','collective') NOT NULL DEFAULT 'join' COMMENT 'JOIN=จอยทัวร์, INCENTIVE=จัดกรุ๊ป, COLLECTIVE=รวมกรุ๊ป',
  `primary_country_id` bigint(20) unsigned DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL COMMENT 'ASIA, EUROPE, etc.',
  `sub_region` varchar(50) DEFAULT NULL COMMENT 'EAST_ASIA, WEST_EUROPE',
  `duration_days` tinyint(3) unsigned NOT NULL,
  `duration_nights` tinyint(3) unsigned NOT NULL,
  `highlights` text DEFAULT NULL COMMENT 'ไฮไลต์ทัวร์',
  `shopping_highlights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shopping_highlights`)),
  `food_highlights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`food_highlights`)),
  `special_highlights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`special_highlights`)),
  `hotel_star_min` tinyint(3) unsigned DEFAULT NULL COMMENT 'ระดับดาวโรงแรมต่ำสุด',
  `hotel_star_max` tinyint(3) unsigned DEFAULT NULL COMMENT 'ระดับดาวโรงแรมสูงสุด',
  `hotel_star` tinyint(3) unsigned DEFAULT NULL,
  `inclusions` text DEFAULT NULL COMMENT 'รวมอะไรบ้าง',
  `exclusions` text DEFAULT NULL COMMENT 'ไม่รวมอะไร',
  `conditions` text DEFAULT NULL COMMENT 'เงื่อนไขทั่วไป',
  `description` text DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["ทัวร์ฮ่องกง", "จีน"]' CHECK (json_valid(`keywords`)),
  `cover_image_url` varchar(500) DEFAULT NULL,
  `cover_image_alt` varchar(255) DEFAULT NULL,
  `og_image_url` varchar(500) DEFAULT NULL COMMENT 'สำหรับ Social Share',
  `pdf_url` varchar(500) DEFAULT NULL,
  `docx_url` varchar(500) DEFAULT NULL,
  `themes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["SHOPPING", "CULTURE", "TEMPLE"]' CHECK (json_valid(`themes`)),
  `suitable_for` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["FAMILY", "COUPLE", "GROUP"]' CHECK (json_valid(`suitable_for`)),
  `hashtags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["#ทัวร์ฮ่องกง", "#ช้อปปิ้ง"]' CHECK (json_valid(`hashtags`)),
  `departure_airports` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["DMK", "BKK"]' CHECK (json_valid(`departure_airports`)),
  `min_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาต่ำสุด (calculated)',
  `display_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาแสดง (ราคาผู้ใหญ่ถูกที่สุด)',
  `price_adult` decimal(12,2) DEFAULT NULL COMMENT 'แสดง "เริ่มต้น X บาท" บนหน้า listing',
  `discount_adult` decimal(12,2) DEFAULT NULL COMMENT 'ส่วนลดผู้ใหญ่ (บาท/%)',
  `discount_amount` decimal(10,2) DEFAULT NULL COMMENT 'ส่วนลด (บาท)',
  `promotion_type` enum('none','normal','fire_sale') NOT NULL DEFAULT 'none' COMMENT 'none=ไม่มีโปร, normal=โปรธรรมดา, fire_sale=โปรไฟไหม้',
  `max_discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดสูงสุด % จากทุก Period',
  `discount_label` varchar(50) DEFAULT NULL COMMENT 'ข้อความส่วนลด: "ลด 2,000"',
  `max_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาสูงสุด (calculated)',
  `next_departure_date` date DEFAULT NULL COMMENT 'วันเดินทางถัดไป (calculated)',
  `total_departures` smallint(5) unsigned NOT NULL DEFAULT 0,
  `available_seats` smallint(5) unsigned NOT NULL DEFAULT 0,
  `has_promotion` tinyint(1) NOT NULL DEFAULT 0,
  `badge` varchar(20) DEFAULT NULL COMMENT 'HOT, NEW, BEST_SELLER',
  `tour_category` enum('budget','premium') DEFAULT NULL COMMENT 'ประเภททัวร์: budget=ทัวร์ราคาถูก, premium=ทัวร์พรีเมียม',
  `transport_id` bigint(20) unsigned DEFAULT NULL,
  `popularity_score` int(10) unsigned NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','active','inactive') NOT NULL DEFAULT 'draft',
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `updated_at_source` timestamp NULL DEFAULT NULL COMMENT 'เวลาอัปเดตจาก Wholesale',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tours_wholesaler_id_external_id_unique` (`wholesaler_id`,`external_id`),
  UNIQUE KEY `tours_tour_code_unique` (`tour_code`),
  UNIQUE KEY `tours_slug_unique` (`slug`),
  KEY `tours_country_id_status_is_published_index` (`primary_country_id`,`status`,`is_published`),
  KEY `tours_region_status_index` (`region`,`status`),
  KEY `tours_min_price_index` (`min_price`),
  KEY `tours_next_departure_date_index` (`next_departure_date`),
  KEY `tours_popularity_score_index` (`popularity_score`),
  KEY `tours_wholesaler_tour_code_index` (`wholesaler_tour_code`),
  KEY `tours_transport_id_foreign` (`transport_id`),
  KEY `idx_tours_sync` (`data_source`,`sync_status`),
  KEY `idx_tours_external` (`wholesaler_id`,`external_id`),
  KEY `tours_promotion_type_index` (`promotion_type`),
  KEY `tours_max_discount_percent_index` (`max_discount_percent`),
  CONSTRAINT `tours_primary_country_id_foreign` FOREIGN KEY (`primary_country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tours_transport_id_foreign` FOREIGN KEY (`transport_id`) REFERENCES `transports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tours_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) DEFAULT NULL COMMENT 'IATA code 2 ตัว (TG, AQ, FD)',
  `code1` varchar(100) DEFAULT NULL COMMENT 'ICAO code 3 ตัว (THA, ANK, AFR)',
  `name` varchar(250) DEFAULT NULL COMMENT 'ชื่อผู้ให้บริการ',
  `type` enum('airline','bus','boat','train','van','other') NOT NULL DEFAULT 'airline' COMMENT 'ประเภทยานพาหนะ',
  `image` varchar(255) DEFAULT NULL COMMENT 'รูปโลโก้',
  `status` enum('on','off') NOT NULL DEFAULT 'on',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transports_code_index` (`code`),
  KEY `transports_type_index` (`type`),
  KEY `transports_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','manager','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `webhook_id` varchar(100) DEFAULT NULL COMMENT 'ID จาก Wholesaler',
  `event_type` varchar(100) NOT NULL COMMENT 'tour.created, tour.updated, booking.confirmed, etc.',
  `source_ip` varchar(45) DEFAULT NULL,
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `signature` varchar(200) DEFAULT NULL COMMENT 'Signature สำหรับ verify',
  `signature_valid` tinyint(1) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','ignored') NOT NULL DEFAULT 'pending',
  `received_at` timestamp NOT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processing_time_ms` int(11) DEFAULT NULL,
  `tours_affected` int(11) NOT NULL DEFAULT 0,
  `periods_affected` int(11) NOT NULL DEFAULT 0,
  `result_message` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `error_trace` text DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `max_retries` int(11) NOT NULL DEFAULT 3,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_logs_webhook_id_unique` (`webhook_id`),
  KEY `webhook_logs_wholesaler_id_status_received_at_index` (`wholesaler_id`,`status`,`received_at`),
  KEY `webhook_logs_event_type_status_index` (`event_type`,`status`),
  CONSTRAINT `webhook_logs_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wholesaler_api_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wholesaler_api_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `api_base_url` varchar(500) NOT NULL,
  `api_version` varchar(20) NOT NULL DEFAULT 'v1',
  `api_format` enum('rest','soap','graphql') NOT NULL DEFAULT 'rest',
  `auth_type` enum('api_key','oauth2','basic','bearer','custom') NOT NULL DEFAULT 'api_key',
  `auth_credentials` text DEFAULT NULL COMMENT 'Encrypted JSON',
  `auth_header_name` varchar(100) NOT NULL DEFAULT 'Authorization',
  `rate_limit_per_minute` int(11) NOT NULL DEFAULT 60,
  `rate_limit_per_day` int(11) NOT NULL DEFAULT 10000,
  `connect_timeout_seconds` int(11) NOT NULL DEFAULT 10,
  `request_timeout_seconds` int(11) NOT NULL DEFAULT 30,
  `retry_attempts` int(11) NOT NULL DEFAULT 3,
  `sync_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sync_method` enum('cursor','ack_callback','last_modified') NOT NULL DEFAULT 'cursor',
  `sync_mode` enum('single','two_phase') NOT NULL DEFAULT 'single' COMMENT 'single: tours+periods together, two_phase: separate API calls for periods',
  `sync_schedule` varchar(100) NOT NULL DEFAULT '0 */2 * * *' COMMENT 'Every 2 hours',
  `sync_limit` int(10) unsigned DEFAULT NULL COMMENT 'Maximum records per sync (null = unlimited)',
  `full_sync_schedule` varchar(100) NOT NULL DEFAULT '0 3 * * *' COMMENT 'Daily 3 AM',
  `webhook_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `webhook_secret` varchar(200) DEFAULT NULL,
  `webhook_url` varchar(500) DEFAULT NULL,
  `webhook_events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["tour.created", "tour.updated", "period.updated", "availability.changed"]' CHECK (json_valid(`webhook_events`)),
  `callback_url` varchar(500) DEFAULT NULL COMMENT 'URL ที่ Wholesaler ส่ง callback มา (ถ้าต่างจาก webhook_url)',
  `callback_auth_type` enum('none','signature','token','basic') NOT NULL DEFAULT 'signature',
  `supports_availability_check` tinyint(1) NOT NULL DEFAULT 1,
  `supports_hold_booking` tinyint(1) NOT NULL DEFAULT 1,
  `supports_modify_booking` tinyint(1) NOT NULL DEFAULT 0,
  `pdf_header_image` varchar(500) DEFAULT NULL COMMENT 'Cloudflare URL for PDF header image',
  `pdf_footer_image` varchar(500) DEFAULT NULL COMMENT 'Cloudflare URL for PDF footer image',
  `aggregation_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`aggregation_config`)),
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `extract_cities_from_name` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Extract city names from tour name when API does not provide cities',
  `notification_emails` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_emails`)),
  `notification_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_types`)),
  `pdf_header_height` int(11) DEFAULT NULL COMMENT 'Header image height in pixels (auto from image)',
  `pdf_footer_height` int(11) DEFAULT NULL COMMENT 'Footer image height in pixels (auto from image)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_health_check_at` timestamp NULL DEFAULT NULL,
  `last_health_check_status` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `enabled_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`enabled_fields`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `wholesaler_api_configs_wholesaler_id_unique` (`wholesaler_id`),
  CONSTRAINT `wholesaler_api_configs_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wholesaler_field_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wholesaler_field_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wholesaler_id` bigint(20) unsigned NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `our_field` varchar(100) NOT NULL,
  `their_field` varchar(200) DEFAULT NULL COMMENT 'Simple field name',
  `their_field_path` varchar(500) DEFAULT NULL COMMENT 'JSON path: data.tour.details.name',
  `transform_type` enum('direct','value_map','formula','split','concat','lookup','custom') NOT NULL DEFAULT 'direct',
  `transform_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`transform_config`)),
  `default_value` varchar(500) DEFAULT NULL,
  `is_required_override` tinyint(1) DEFAULT NULL COMMENT 'Override section definition',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mapping` (`wholesaler_id`,`section_name`,`our_field`),
  CONSTRAINT `wholesaler_field_mappings_wholesaler_id_foreign` FOREIGN KEY (`wholesaler_id`) REFERENCES `wholesalers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wholesalers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wholesalers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'รหัส Wholesale เช่น ZEGO, TOURKRUB',
  `name` varchar(255) NOT NULL COMMENT 'ชื่อบริษัท',
  `logo_url` varchar(500) DEFAULT NULL COMMENT 'URL โลโก้',
  `website` varchar(255) DEFAULT NULL COMMENT 'เว็บไซต์',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะเปิด/ปิดใช้งาน',
  `notes` text DEFAULT NULL COMMENT 'หมายเหตุภายใน',
  `contact_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อผู้ติดต่อ',
  `contact_email` varchar(255) DEFAULT NULL COMMENT 'Email ติดต่อ',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT 'เบอร์โทรติดต่อ',
  `tax_id` varchar(20) DEFAULT NULL COMMENT 'เลขประจำตัวผู้เสียภาษี 13 หลัก',
  `company_name_th` varchar(255) DEFAULT NULL COMMENT 'ชื่อบริษัท ภาษาไทย',
  `company_name_en` varchar(255) DEFAULT NULL COMMENT 'ชื่อบริษัท English',
  `branch_code` varchar(10) NOT NULL DEFAULT '00000' COMMENT 'รหัสสาขา (00000 = สำนักงานใหญ่)',
  `branch_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อสาขา',
  `address` text DEFAULT NULL COMMENT 'ที่อยู่เต็ม',
  `phone` varchar(50) DEFAULT NULL COMMENT 'เบอร์โทรบริษัท',
  `fax` varchar(50) DEFAULT NULL COMMENT 'เบอร์แฟกซ์',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wholesalers_code_unique` (`code`),
  KEY `wholesalers_is_active_index` (`is_active`),
  KEY `wholesalers_tax_id_index` (`tax_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_01_24_134542_create_wholesalers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_01_24_135721_create_personal_access_tokens_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_01_25_012152_add_role_and_is_active_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_01_25_100001_create_countries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_01_25_100002_create_transports_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_01_25_100003_create_airports_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_01_25_100004_create_tours_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_01_26_100001_create_cities_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_01_26_100002_remove_unnecessary_columns_from_cities_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_01_26_200001_create_tour_system_tables',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_01_25_100008_create_sync_tables',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_01_25_100009_create_partner_security_tables',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_01_26_200002_add_tour_countries_pivot_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_01_26_200003_add_missing_tour_fields',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_01_26_200004_create_tour_cities_pivot_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_01_26_200005_add_period_visibility_and_discount_fields',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_01_26_200010_create_promotions_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_01_26_220001_add_wholesaler_tour_code',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_01_26_125431_convert_highlights_to_json',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_01_26_130346_add_tour_category_to_tours_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_01_26_141609_add_missing_fields_to_tours_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_01_27_034223_create_gallery_images_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_01_27_200001_create_wholesaler_integration_tables',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_01_28_100001_add_sync_fields_to_tours_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_01_28_100002_create_webhook_logs_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_01_28_020054_add_enabled_fields_to_wholesaler_api_configs_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_01_28_031927_add_sync_fields_to_periods_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_01_28_050000_alter_tour_itineraries_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_01_28_050100_add_sync_fields_to_tour_itineraries_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_01_29_100001_add_pdf_branding_to_wholesaler_api_configs',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_01_29_030422_add_sync_limit_to_wholesaler_api_configs_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_01_30_021442_create_settings_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_01_30_021458_add_aggregation_config_to_wholesaler_api_configs',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_01_30_043653_add_promotion_type_to_tours_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_01_30_075127_add_sync_mode_to_wholesaler_api_configs',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_01_31_014029_add_fetch_periods_to_outbound_api_logs_action',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_02_02_045100_change_meta_description_to_text_in_tours_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_02_02_100000_add_notification_settings_to_wholesaler_api_configs',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_02_03_100000_add_extract_cities_to_wholesaler_api_configs',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_02_03_154150_rename_price_single_surcharge_to_price_single_in_offers',36);
