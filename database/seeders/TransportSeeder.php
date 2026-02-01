<?php

namespace Database\Seeders;

use App\Services\CloudflareImagesService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransportSeeder extends Seeder
{
    protected CloudflareImagesService $cloudflare;
    protected string $baseUrl = 'https://nexttripholidays.com/';

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Run the database seeds.
     * ข้อมูลจาก tb_travel_type ในฐานข้อมูลเดิม
     * ดาวน์โหลด image จาก nexttripholiday.com แปลงเป็น webp แล้วอัพโหลดไป Cloudflare
     */
    public function run(): void
    {
        $transports = [
            ['id' => 1, 'code' => 'M3', 'code1' => 'TUS', 'name' => 'ABSA Cargo Airline', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 2, 'code' => 'GB', 'code1' => 'ABX', 'name' => 'ABX Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03102023-09505110.jpeg', 'status' => 'off'],
            ['id' => 3, 'code' => '9T', 'code1' => 'RUN', 'name' => 'ACT Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 4, 'code' => '9N', 'code1' => 'AJV', 'name' => 'ANA & JP Express', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 5, 'code' => 'V8', 'code1' => 'VAS', 'name' => 'ATRAN Cargo Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 6, 'code' => '2E', 'code1' => 'PHW', 'name' => 'AVE.com', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 7, 'code' => 'K5', 'code1' => 'ABE', 'name' => 'Aban Air', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 8, 'code' => 'W9', 'code1' => 'AAB', 'name' => 'Abelag Aviation', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 9, 'code' => 'MO', 'code1' => 'AUH', 'name' => 'Abu Dhabi Amiri Flight', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 10, 'code' => 'ZY', 'code1' => 'ADE', 'name' => 'Ada Air', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 11, 'code' => 'JP', 'code1' => 'ADR', 'name' => 'Adria Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 12, 'code' => 'A3', 'code1' => 'AEE', 'name' => 'Aegean Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 13, 'code' => 'RE', 'code1' => 'REA', 'name' => 'Aer Arann', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 14, 'code' => 'EI', 'code1' => 'EIN', 'name' => 'Aer Lingus', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 15, 'code' => 'E4', 'code1' => 'RSO', 'name' => 'Aero Asia International', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 16, 'code' => 'EM', 'code1' => 'AEB', 'name' => 'Aero Benin', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 17, 'code' => '7L', 'code1' => 'CRN', 'name' => 'Aero Caribbean', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 18, 'code' => 'Q6', 'code1' => 'CDP', 'name' => 'Aero Condor Peru', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 19, 'code' => 'AJ', 'code1' => 'NIG', 'name' => 'Aero Contractors', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 20, 'code' => 'QL', 'code1' => 'RLN', 'name' => 'Aero Lanka', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 21, 'code' => 'M0', 'code1' => 'MNG', 'name' => 'Aero Mongolia', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 22, 'code' => 'W4', 'code1' => 'BES', 'name' => 'Aero Services Executive', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 23, 'code' => 'DW', 'code1' => 'UCR', 'name' => 'Aero-Charter Ukraine', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 24, 'code' => 'BF', 'code1' => 'RSR', 'name' => 'Aero-Service', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 25, 'code' => 'HC', 'code1' => 'ATI', 'name' => 'Aero-Tropics Air Services', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 26, 'code' => '3S', 'code1' => 'BOX', 'name' => 'AeroLogic', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 27, 'code' => 'AM', 'code1' => 'AMX', 'name' => 'Aeroméxico', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 28, 'code' => 'P5', 'code1' => 'RPB', 'name' => 'AeroRepublica', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 29, 'code' => '6N', 'code1' => 'KRE', 'name' => 'AeroSucre', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 30, 'code' => '2B', 'code1' => 'ARD', 'name' => 'Aerocondor', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 31, 'code' => 'SU', 'code1' => 'AFL', 'name' => 'Aeroflot Russian Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 32, 'code' => 'SU', 'code1' => 'RCF', 'name' => 'Aeroflot-Cargo', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 33, 'code' => 'D9', 'code1' => 'DNV', 'name' => 'Aeroflot-Don', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 34, 'code' => '5N', 'code1' => 'AUL', 'name' => 'Aeroflot-Nord', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 35, 'code' => 'KG', 'code1' => 'GTV', 'name' => 'Aerogaviota', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 36, 'code' => 'AR', 'code1' => 'ARG', 'name' => 'Aerolineas Argentinas', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 37, 'code' => '2K', 'code1' => 'GLG', 'name' => 'Aerolineas Galapagos (Aerogal)', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 38, 'code' => 'P4', 'code1' => 'NSO', 'name' => 'Aerolineas Sosa', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 39, 'code' => 'VW', 'code1' => 'TAO', 'name' => 'Aeromar', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 40, 'code' => 'QO', 'code1' => 'MPX', 'name' => 'Aeromexpress', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 41, 'code' => 'HT', 'code1' => 'AHW', 'name' => 'Aeromist-Kharkiv', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 42, 'code' => '5D', 'code1' => 'SLI', 'name' => 'Aeroméxico Connect', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 43, 'code' => 'OT', 'code1' => 'PEL', 'name' => 'Aeropelican Air Services', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 44, 'code' => 'WL', 'code1' => 'APP', 'name' => 'Aeroperlas', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 45, 'code' => 'VH', 'code1' => 'LAV', 'name' => 'Aeropostal Alas de Venezuela', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 46, 'code' => '5L', 'code1' => 'RSU', 'name' => 'Aerosur', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 47, 'code' => 'VV', 'code1' => 'AEW', 'name' => 'Aerosvit Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 48, 'code' => 'FK', 'code1' => 'WTA', 'name' => 'Africa West', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 49, 'code' => 'XU', 'code1' => 'AXK', 'name' => 'African Express Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 50, 'code' => '6F', 'code1' => 'FRJ', 'name' => 'Afrijet Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 51, 'code' => 'Q9', 'code1' => 'AFU', 'name' => 'Afrinat International Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 52, 'code' => '8U', 'code1' => 'AAW', 'name' => 'Afriqiyah Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 53, 'code' => 'ZI', 'code1' => 'AAF', 'name' => 'Aigle Azur', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 54, 'code' => 'AH', 'code1' => 'DAH', 'name' => 'Air Algerie', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 55, 'code' => 'GD', 'code1' => 'AHA', 'name' => 'Air Alpha Greenland', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 56, 'code' => 'A6', 'code1' => 'LPV', 'name' => 'Air Alps Aviation', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 57, 'code' => 'G9', 'code1' => 'ABY', 'name' => 'Air Arabia', 'type' => 'airline', 'image' => 'upload/travel-type/logo15022024-12034402.png', 'status' => 'on'],
            ['id' => 58, 'code' => 'QN', 'code1' => 'ARR', 'name' => 'Air Armenia', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 59, 'code' => 'KC', 'code1' => 'KZR', 'name' => 'Air Astana', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12154710.png', 'status' => 'on'],
            ['id' => 60, 'code' => 'CC', 'code1' => 'ABD', 'name' => 'Air Atlanta Icelandic', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 61, 'code' => 'KI', 'code1' => 'AAG', 'name' => 'Air Atlantique', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 62, 'code' => 'UU', 'code1' => 'REU', 'name' => 'Air Austral', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12335410.jpeg', 'status' => 'on'],
            ['id' => 63, 'code' => 'W9', 'code1' => 'JAB', 'name' => 'Air Bagan', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 64, 'code' => 'BT', 'code1' => 'BTI', 'name' => 'Air Baltic', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 65, 'code' => 'AB', 'code1' => 'BER', 'name' => 'Air Berlin', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12010410.png', 'status' => 'on'],
            ['id' => 66, 'code' => 'BP', 'code1' => 'BOT', 'name' => 'Air Botswana', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 67, 'code' => '2J', 'code1' => 'VBW', 'name' => 'Air Burkina', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 68, 'code' => 'TY', 'code1' => 'TPC', 'name' => 'Air Caledonie', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 69, 'code' => 'AC', 'code1' => 'ACA', 'name' => 'Air Canada', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18054611.jpeg', 'status' => 'on'],
            ['id' => 70, 'code' => 'QK', 'code1' => 'JZA', 'name' => 'Air Canada Jazz', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 71, 'code' => 'TX', 'code1' => 'FWI', 'name' => 'Air Caraibes', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 72, 'code' => '2Q', 'code1' => 'SNC', 'name' => 'Air Cargo Carriers', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 73, 'code' => 'NV', 'code1' => 'CRF', 'name' => 'Air Central', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 74, 'code' => 'CV', 'code1' => 'CVA', 'name' => 'Air Chathams', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 75, 'code' => 'CA', 'code1' => 'CCA', 'name' => 'Air China', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18381711.jpeg', 'status' => 'on'],
            ['id' => 76, 'code' => 'CA', 'code1' => 'CAO', 'name' => 'Air China Cargo', 'type' => 'airline', 'image' => null, 'status' => 'off'],
            ['id' => 77, 'code' => 'QD', 'code1' => 'QCL', 'name' => 'Air Class Lineas Aereas', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 78, 'code' => 'A7', 'code1' => 'MPD', 'name' => 'Air Comet', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 79, 'code' => 'AG', 'code1' => 'ABR', 'name' => 'Air Contractors', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 80, 'code' => 'QC', 'code1' => 'CRD', 'name' => 'Air Corridor', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 81, 'code' => 'YN', 'code1' => 'CRQ', 'name' => 'Air Creebec', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 82, 'code' => 'EN', 'code1' => 'DLA', 'name' => 'Air Dolomiti', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 83, 'code' => 'UX', 'code1' => 'AEA', 'name' => 'Air Europa', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 84, 'code' => 'PC', 'code1' => 'FAJ', 'name' => 'Air Fiji', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 85, 'code' => 'OF', 'code1' => 'FIF', 'name' => 'Air Finland', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 86, 'code' => 'AF', 'code1' => 'AFR', 'name' => 'Air France', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12022410.png', 'status' => 'on'],
            ['id' => 87, 'code' => 'AF', 'code1' => 'AFN', 'name' => 'Air Freight NZ', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 88, 'code' => 'ZX', 'code1' => 'GGN', 'name' => 'Air Georgian', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 89, 'code' => '7T', 'code1' => 'AGV', 'name' => 'Air Glaciers', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 90, 'code' => 'GL', 'code1' => 'GRL', 'name' => 'Air Greenland', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 91, 'code' => 'LQ', 'code1' => 'GNC', 'name' => 'Air Guinea Cargo', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 92, 'code' => 'GG', 'code1' => 'GUY', 'name' => 'Air Guyane Express', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 93, 'code' => 'LD', 'code1' => 'AHK', 'name' => 'Air Hong Kong', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 94, 'code' => '8C', 'code1' => 'HZT', 'name' => 'Air Horizon', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 95, 'code' => 'NY', 'code1' => 'FXI', 'name' => 'Air Iceland Connect', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 96, 'code' => 'AI', 'code1' => 'AIC', 'name' => 'Air India', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18472411.jpeg', 'status' => 'on'],
            ['id' => 97, 'code' => 'IX', 'code1' => 'AXB', 'name' => 'Air India Express', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 98, 'code' => 'CD', 'code1' => 'LLR', 'name' => 'Air India Regional', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 99, 'code' => '3H', 'code1' => 'AIE', 'name' => 'Air Inuit', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 100, 'code' => 'I9', 'code1' => 'AEY', 'name' => 'Air Italy', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            // ... สายการบินหลักที่สำคัญ
            ['id' => 158, 'code' => 'AK', 'code1' => 'AXM', 'name' => 'AirAsia', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 159, 'code' => 'D7', 'code1' => 'XAX', 'name' => 'AirAsia X', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 174, 'code' => 'AS', 'code1' => 'ASA', 'name' => 'Alaska Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 178, 'code' => 'AZ', 'code1' => 'AZA', 'name' => 'Alitalia', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12045210.png', 'status' => 'on'],
            ['id' => 180, 'code' => 'NH', 'code1' => 'ANA', 'name' => 'All Nippon Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo05012026-20105101.png', 'status' => 'on'],
            ['id' => 189, 'code' => 'AA', 'code1' => 'AAL', 'name' => 'American Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12364610.jpeg', 'status' => 'on'],
            ['id' => 207, 'code' => 'OZ', 'code1' => 'AAR', 'name' => 'Asiana Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18485211.jpeg', 'status' => 'on'],
            ['id' => 226, 'code' => 'OS', 'code1' => 'AUA', 'name' => 'Austrian Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18302611.jpeg', 'status' => 'on'],
            ['id' => 252, 'code' => 'PG', 'code1' => 'BKP', 'name' => 'Bangkok Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19270011.jpeg', 'status' => 'on'],
            ['id' => 282, 'code' => 'BA', 'code1' => 'BAW', 'name' => 'British Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12070510.jpeg', 'status' => 'on'],
            ['id' => 308, 'code' => 'CX', 'code1' => 'CPA', 'name' => 'Cathay Pacific', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18500811.jpeg', 'status' => 'on'],
            ['id' => 310, 'code' => '5J', 'code1' => 'CEB', 'name' => 'Cebu Pacific', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 318, 'code' => 'CI', 'code1' => 'CAL', 'name' => 'China Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18494211.jpeg', 'status' => 'on'],
            ['id' => 320, 'code' => 'MU', 'code1' => 'CES', 'name' => 'China Eastern Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19300811.jpeg', 'status' => 'on'],
            ['id' => 322, 'code' => 'CZ', 'code1' => 'CSN', 'name' => 'China Southern Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18542111.jpeg', 'status' => 'on'],
            ['id' => 371, 'code' => 'DL', 'code1' => 'DAL', 'name' => 'Delta Air Lines', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12090110.jpeg', 'status' => 'on'],
            ['id' => 379, 'code' => 'KA', 'code1' => 'HDA', 'name' => 'Dragonair, Hong Kong Dragon Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-11550110.png', 'status' => 'on'],
            ['id' => 382, 'code' => 'BR', 'code1' => 'EVA', 'name' => 'EVA Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19023611.jpeg', 'status' => 'on'],
            ['id' => 395, 'code' => 'MS', 'code1' => 'MSR', 'name' => 'Egyptair', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18561911.jpeg', 'status' => 'on'],
            ['id' => 396, 'code' => 'LY', 'code1' => 'ELY', 'name' => 'El Al Israel Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12200310.png', 'status' => 'on'],
            ['id' => 397, 'code' => 'EK', 'code1' => 'UAE', 'name' => 'Emirates Airline', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18535011.jpeg', 'status' => 'on'],
            ['id' => 405, 'code' => 'ET', 'code1' => 'ETH', 'name' => 'Ethiopian Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18564811.jpeg', 'status' => 'on'],
            ['id' => 406, 'code' => 'EY', 'code1' => 'ETD', 'name' => 'Etihad Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18554311.jpeg', 'status' => 'on'],
            ['id' => 425, 'code' => 'AY', 'code1' => 'FIN', 'name' => 'Finnair', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18064511.jpeg', 'status' => 'on'],
            ['id' => 439, 'code' => 'HK', 'code1' => 'FSC', 'name' => 'Hongkong Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo27082025-11151408.jpeg', 'status' => 'on'],
            ['id' => 448, 'code' => 'GA', 'code1' => 'GIA', 'name' => 'Garuda Indonesia', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12110910.png', 'status' => 'on'],
            ['id' => 474, 'code' => 'HU', 'code1' => 'CHH', 'name' => 'Hainan Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo31102024-18054710.jpeg', 'status' => 'on'],
            ['id' => 476, 'code' => 'HA', 'code1' => 'HAL', 'name' => 'Hawaiian Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 492, 'code' => 'UO', 'code1' => 'HKE', 'name' => 'Hong Kong Express Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19003811.jpeg', 'status' => 'on'],
            ['id' => 495, 'code' => 'IB', 'code1' => 'IBE', 'name' => 'Iberia Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 499, 'code' => 'FI', 'code1' => 'ICE', 'name' => 'Icelandair', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 501, 'code' => '6E', 'code1' => 'IGO', 'name' => 'IndiGo Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19210711.jpeg', 'status' => 'on'],
            ['id' => 529, 'code' => 'JL', 'code1' => 'JAL', 'name' => 'Japan Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19391911.jpeg', 'status' => 'on'],
            ['id' => 533, 'code' => '7C', 'code1' => 'JJA', 'name' => 'Jeju Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18042811.jpeg', 'status' => 'on'],
            ['id' => 535, 'code' => '9W', 'code1' => 'JAI', 'name' => 'Jet Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-11570610.png', 'status' => 'on'],
            ['id' => 539, 'code' => 'B6', 'code1' => 'JBU', 'name' => 'JetBlue Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 544, 'code' => 'JQ', 'code1' => 'JST', 'name' => 'Jetstar Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 545, 'code' => '3K', 'code1' => 'JSA', 'name' => 'Jetstar Asia Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 548, 'code' => 'LJ', 'code1' => 'JNA', 'name' => 'Jin Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18305311.jpeg', 'status' => 'on'],
            ['id' => 551, 'code' => 'HO', 'code1' => 'DKH', 'name' => 'Juneyao Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo19042024-10400304.png', 'status' => 'on'],
            ['id' => 555, 'code' => 'KL', 'code1' => 'KLM', 'name' => 'KLM Royal Dutch Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12165610.png', 'status' => 'on'],
            ['id' => 567, 'code' => 'KQ', 'code1' => 'KQA', 'name' => 'Kenya Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 575, 'code' => 'KE', 'code1' => 'KAL', 'name' => 'Korean Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18013211.jpeg', 'status' => 'on'],
            ['id' => 578, 'code' => 'KU', 'code1' => 'KAC', 'name' => 'Kuwait Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12173010.png', 'status' => 'on'],
            ['id' => 588, 'code' => 'LA', 'code1' => 'LAN', 'name' => 'LAN Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 595, 'code' => 'LO', 'code1' => 'LOT', 'name' => 'LOT Polish Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 598, 'code' => 'QV', 'code1' => 'LAO', 'name' => 'Lao Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19314711.jpeg', 'status' => 'on'],
            ['id' => 608, 'code' => '8L', 'code1' => 'LKE', 'name' => 'Lucky Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19144311.jpeg', 'status' => 'on'],
            ['id' => 610, 'code' => 'LH', 'code1' => 'DLH', 'name' => 'Lufthansa', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19415711.jpeg', 'status' => 'on'],
            ['id' => 615, 'code' => 'LG', 'code1' => 'LGL', 'name' => 'Luxair', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 622, 'code' => 'OM', 'code1' => 'MGL', 'name' => 'MIAT Mongolian Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19422411.jpeg', 'status' => 'on'],
            ['id' => 627, 'code' => 'W5', 'code1' => 'IRM', 'name' => 'Mahan Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19130711.jpeg', 'status' => 'on'],
            ['id' => 628, 'code' => 'MH', 'code1' => 'MAS', 'name' => 'Malaysia Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19121811.jpeg', 'status' => 'on'],
            ['id' => 654, 'code' => 'ME', 'code1' => 'MEA', 'name' => 'Middle East Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 666, 'code' => 'UB', 'code1' => 'UBA', 'name' => 'Myanmar Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19251311.jpeg', 'status' => 'on'],
            ['id' => 667, 'code' => '8M', 'code1' => 'UBA', 'name' => 'Myanmar Airways International', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19282011.jpeg', 'status' => 'on'],
            ['id' => 678, 'code' => 'RA', 'code1' => 'RNA', 'name' => 'Nepal Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 685, 'code' => 'DD', 'code1' => 'NOK', 'name' => 'Nok Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19140511.jpeg', 'status' => 'on'],
            ['id' => 695, 'code' => 'DY', 'code1' => 'NAX', 'name' => 'Norwegian Air Shuttle', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 704, 'code' => 'WY', 'code1' => 'OMA', 'name' => 'Oman Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19293711.jpeg', 'status' => 'on'],
            ['id' => 710, 'code' => 'OX', 'code1' => 'OEA', 'name' => 'Orient Thai Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo16082025-23322108.jpeg', 'status' => 'on'],
            ['id' => 723, 'code' => 'PK', 'code1' => 'PIA', 'name' => 'Pakistan International Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 738, 'code' => 'PR', 'code1' => 'PAL', 'name' => 'Philippine Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12241810.png', 'status' => 'on'],
            ['id' => 752, 'code' => 'QF', 'code1' => 'QFA', 'name' => 'Qantas', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12260510.png', 'status' => 'on'],
            ['id' => 753, 'code' => 'QR', 'code1' => 'QTR', 'name' => 'Qatar Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18300011.jpeg', 'status' => 'on'],
            ['id' => 772, 'code' => 'RJ', 'code1' => 'RJA', 'name' => 'Royal Jordanian', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19430511.jpeg', 'status' => 'on'],
            ['id' => 778, 'code' => 'FR', 'code1' => 'RYR', 'name' => 'Ryanair', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 779, 'code' => 'S7', 'code1' => 'SBI', 'name' => 'S7 Airlines (Siberia Airlines)', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19305911.jpeg', 'status' => 'on'],
            ['id' => 793, 'code' => 'SV', 'code1' => 'SVA', 'name' => 'Saudi Arabian Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo31102024-17120610.jpeg', 'status' => 'on'],
            ['id' => 795, 'code' => 'SK', 'code1' => 'SAS', 'name' => 'Scandinavian Airlines System (SAS)', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 804, 'code' => 'SC', 'code1' => 'CDG', 'name' => 'Shandong Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo08122025-10261512.jpeg', 'status' => 'on'],
            ['id' => 805, 'code' => 'FM', 'code1' => 'CSH', 'name' => 'Shanghai Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19342511.jpeg', 'status' => 'on'],
            ['id' => 808, 'code' => 'ZH', 'code1' => 'CSZ', 'name' => 'Shenzhen Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo31102024-17192310.jpeg', 'status' => 'on'],
            ['id' => 811, 'code' => '3U', 'code1' => 'CSC', 'name' => 'Sichuan Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19204111.jpeg', 'status' => 'on'],
            ['id' => 815, 'code' => 'MI', 'code1' => 'SLK', 'name' => 'SilkAir', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-11581110.png', 'status' => 'on'],
            ['id' => 816, 'code' => 'SQ', 'code1' => 'SIA', 'name' => 'Singapore Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-17583411.png', 'status' => 'on'],
            ['id' => 842, 'code' => 'WN', 'code1' => 'SWA', 'name' => 'Southwest Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 844, 'code' => 'SG', 'code1' => 'SEJ', 'name' => 'Spicejet', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19303211.jpeg', 'status' => 'on'],
            ['id' => 847, 'code' => 'UL', 'code1' => 'ALK', 'name' => 'SriLankan Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 868, 'code' => 'LX', 'code1' => 'SWR', 'name' => 'Swiss International Air Lines', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12191810.png', 'status' => 'on'],
            ['id' => 892, 'code' => 'FD', 'code1' => 'AIQ', 'name' => 'Thai AirAsia', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18371411.jpeg', 'status' => 'on'],
            ['id' => 893, 'code' => 'TG', 'code1' => 'THA', 'name' => 'Thai Airways International', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18283211.jpeg', 'status' => 'on'],
            ['id' => 899, 'code' => 'IT', 'code1' => 'IT', 'name' => 'Tiger Air Taiwan', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19385611.jpeg', 'status' => 'on'],
            ['id' => 923, 'code' => 'TU', 'code1' => 'TAR', 'name' => 'Tunisair', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 925, 'code' => 'TK', 'code1' => 'THY', 'name' => 'Turkish Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19433311.jpeg', 'status' => 'on'],
            ['id' => 926, 'code' => 'T5', 'code1' => 'TUA', 'name' => 'Turkmenistan Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo31102024-17085910.jpeg', 'status' => 'on'],
            ['id' => 937, 'code' => 'UA', 'code1' => 'UAL', 'name' => 'United Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 942, 'code' => 'HY', 'code1' => 'UZB', 'name' => 'Uzbekistan Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo17102023-12150610.png', 'status' => 'on'],
            ['id' => 951, 'code' => 'VN', 'code1' => 'HVN', 'name' => 'Vietnam Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19220211.jpeg', 'status' => 'on'],
            ['id' => 955, 'code' => 'VS', 'code1' => 'VIR', 'name' => 'Virgin Atlantic Airways', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 981, 'code' => 'W6', 'code1' => 'WZZ', 'name' => 'Wizz Air', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 987, 'code' => 'MF', 'code1' => 'CXA', 'name' => 'Xiamen Airlines', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 1093, 'code' => 'U2', 'code1' => 'EZY', 'name' => 'easyJet', 'type' => 'airline', 'image' => null, 'status' => 'on'],
            ['id' => 1094, 'code' => 'GF', 'code1' => 'GFA', 'name' => 'Gulf Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18574011.jpeg', 'status' => 'on'],
            ['id' => 1098, 'code' => 'VZ', 'code1' => 'VZ', 'name' => 'Vietjet Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19234611.jpeg', 'status' => 'on'],
            ['id' => 1099, 'code' => 'XJ', 'code1' => 'XJ', 'name' => 'Air Asia X', 'type' => 'airline', 'image' => 'upload/travel-type/logo08112023-15494311.jpeg', 'status' => 'on'],
            ['id' => 1100, 'code' => 'HX', 'code1' => 'HX', 'name' => 'Hong Kong Airline', 'type' => 'airline', 'image' => 'upload/travel-type/logo27082025-11170908.jpeg', 'status' => 'on'],
            ['id' => 1101, 'code' => 'MM', 'code1' => 'MM', 'name' => 'Peach', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19124511.jpeg', 'status' => 'on'],
            ['id' => 1102, 'code' => 'BX', 'code1' => 'BX', 'name' => 'Air Busan', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18424411.jpeg', 'status' => 'on'],
            ['id' => 1103, 'code' => 'ZG', 'code1' => 'ZG', 'name' => 'ZIPAIR', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18445511.jpeg', 'status' => 'on'],
            ['id' => 1104, 'code' => 'SR', 'code1' => 'SR', 'name' => 'SWISSAIR', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18464811.jpeg', 'status' => 'on'],
            ['id' => 1105, 'code' => 'HQ', 'code1' => 'HQ', 'name' => 'Bamboo Airways', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18481811.jpeg', 'status' => 'on'],
            ['id' => 1106, 'code' => 'TR', 'code1' => 'TR', 'name' => 'SCOOT', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18522011.jpeg', 'status' => 'on'],
            ['id' => 1107, 'code' => 'TW', 'code1' => 'TW', 'name' => 'T\'way', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-18551511.jpeg', 'status' => 'on'],
            ['id' => 1108, 'code' => 'JD', 'code1' => 'JD', 'name' => 'Beijing Capital Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19053811.jpeg', 'status' => 'on'],
            ['id' => 1109, 'code' => 'GB', 'code1' => 'GB', 'name' => 'Greater Bay Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19064111.jpeg', 'status' => 'on'],
            ['id' => 1110, 'code' => 'YP', 'code1' => 'YP', 'name' => 'Air Premia', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19073811.jpeg', 'status' => 'on'],
            ['id' => 1111, 'code' => 'SL', 'code1' => 'SL', 'name' => 'Thai Lion Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19260011.jpeg', 'status' => 'on'],
            ['id' => 1112, 'code' => 'FZ', 'code1' => 'FZ', 'name' => 'Fly Dubai', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19155111.jpeg', 'status' => 'on'],
            ['id' => 1113, 'code' => 'G2', 'code1' => 'G2', 'name' => 'GullivAir', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19165111.jpeg', 'status' => 'on'],
            ['id' => 1114, 'code' => 'G5', 'code1' => 'G5', 'name' => 'China Express Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19174311.jpeg', 'status' => 'on'],
            ['id' => 1115, 'code' => 'VU', 'code1' => 'VU', 'name' => 'Vietravel Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19231711.jpeg', 'status' => 'on'],
            ['id' => 1116, 'code' => '9C', 'code1' => '9C', 'name' => 'Spring Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19331211.jpeg', 'status' => 'on'],
            ['id' => 1117, 'code' => 'JX', 'code1' => 'JX', 'name' => 'STARLUX Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19402111.jpeg', 'status' => 'on'],
            ['id' => 1118, 'code' => 'KY', 'code1' => 'KY', 'name' => 'Kunming Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19412811.jpeg', 'status' => 'on'],
            ['id' => 1119, 'code' => 'DR', 'code1' => 'DR', 'name' => 'Ruili Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03112023-19450011.jpeg', 'status' => 'on'],
            ['id' => 1120, 'code' => 'QW', 'code1' => 'QW', 'name' => 'QINGDAO AIRLINES', 'type' => 'airline', 'image' => 'upload/travel-type/logo31102024-17154010.jpeg', 'status' => 'on'],
            // ประเภทอื่นๆ (รถบัส, รถตู้, เรือ)
            ['id' => 1121, 'code' => 'BUS', 'code1' => 'BUS', 'name' => 'รถบัส VIP', 'type' => 'bus', 'image' => null, 'status' => 'on'],
            ['id' => 1122, 'code' => 'VAN', 'code1' => 'VAN', 'name' => 'รถตู้ปรับอากาศ VIP', 'type' => 'van', 'image' => 'upload/travel-type/logo22112023-10275911.jpeg', 'status' => 'on'],
            ['id' => 1123, 'code' => 'AQ', 'code1' => 'AQ', 'name' => '9AIR', 'type' => 'airline', 'image' => 'upload/travel-type/logo31102024-18031510.jpeg', 'status' => 'on'],
            ['id' => 1124, 'code' => 'B3', 'code1' => 'B3', 'name' => 'BHUTAN AIRLINE', 'type' => 'airline', 'image' => 'upload/travel-type/logo06122023-21424112.jpeg', 'status' => 'on'],
            ['id' => 1126, 'code' => 'OD', 'code1' => 'OD', 'name' => 'MALINDO AIR', 'type' => 'airline', 'image' => 'upload/travel-type/logo13122023-13193812.png', 'status' => 'on'],
            ['id' => 1128, 'code' => 'UQ', 'code1' => 'UQ', 'name' => 'Urumqi Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo12012024-10455101.png', 'status' => 'on'],
            ['id' => 1130, 'code' => 'OV', 'code1' => 'OV', 'name' => 'Salam Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo04072024-17424607.png', 'status' => 'on'],
            ['id' => 1131, 'code' => 'GX', 'code1' => null, 'name' => 'GX airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo31102024-18085110.jpeg', 'status' => 'on'],
            ['id' => 1132, 'code' => 'ZE', 'code1' => null, 'name' => 'Eastar Jet', 'type' => 'airline', 'image' => 'upload/travel-type/logo28082024-19405308.png', 'status' => 'on'],
            ['id' => 1133, 'code' => 'EU', 'code1' => 'EU', 'name' => 'Chengdu Airlines', 'type' => 'airline', 'image' => 'upload/travel-type/logo03032025-19404203.png', 'status' => 'on'],
            ['id' => 1134, 'code' => '(Cruise)', 'code1' => '(Cruise)', 'name' => 'เรือ', 'type' => 'boat', 'image' => null, 'status' => 'on'],
            ['id' => 1135, 'code' => 'E9', 'code1' => 'E9', 'name' => 'Iberojet (อิเบโรเจ็ต)', 'type' => 'airline', 'image' => 'upload/travel-type/logo20062025-10420006.jpeg', 'status' => 'on'],
            ['id' => 1137, 'code' => 'GJ', 'code1' => 'GJ', 'name' => 'Loong Air', 'type' => 'airline', 'image' => 'upload/travel-type/logo05012026-20140301.png', 'status' => 'on'],
            ['id' => 1138, 'code' => 'C6', 'code1' => null, 'name' => 'CENTRUM AIR', 'type' => 'airline', 'image' => 'upload/travel-type/logo05012026-20131901.png', 'status' => 'on'],
        ];

        // ปิดการตรวจสอบ foreign key constraints ชั่วคราว
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // ลบข้อมูลเดิม
        DB::table('transports')->truncate();
        
        // Insert ข้อมูล
        $now = now();
        $successCount = 0;
        $failCount = 0;

        foreach ($transports as &$transport) {
            $transport['created_at'] = $now;
            $transport['updated_at'] = $now;

            // ถ้ามี image path ให้ดาวน์โหลด แปลง webp แล้วอัพโหลดไป Cloudflare
            if (!empty($transport['image'])) {
                $originalImagePath = $transport['image'];
                $imageUrl = $this->baseUrl . $originalImagePath;
                
                // ดึงชื่อไฟล์จาก path (ไม่รวม extension)
                $filename = pathinfo($originalImagePath, PATHINFO_FILENAME);
                
                try {
                    $this->command->info("Processing: {$transport['name']} - {$originalImagePath}");
                    
                    // ใช้ format: transports/{filename} เพื่อจัดระเบียบใน Cloudflare
                    $result = $this->cloudflare->uploadFromUrl(
                        $imageUrl,
                        "transports/{$filename}",
                        [
                            'folder' => 'transports',
                            'type' => 'transport',
                            'transport_id' => $transport['id'],
                            'transport_code' => $transport['code'],
                            'original_path' => $originalImagePath,
                        ]
                    );

                    if ($result) {
                        // เก็บ Cloudflare image URL ใหม่
                        $transport['image'] = $this->cloudflare->getDisplayUrl($result['id']);
                        $this->command->info("  ✓ Uploaded: {$transport['image']}");
                        $successCount++;
                    } else {
                        $this->command->warn("  ✗ Failed to upload: {$originalImagePath}");
                        $transport['image'] = null; // ล้างค่าถ้าอัพโหลดไม่สำเร็จ
                        $failCount++;
                    }
                } catch (\Exception $e) {
                    $this->command->error("  ✗ Error: {$e->getMessage()}");
                    $transport['image'] = null;
                    $failCount++;
                }
            }
        }
        
        // Insert เป็น chunk เพื่อประสิทธิภาพ
        foreach (array_chunk($transports, 100) as $chunk) {
            DB::table('transports')->insert($chunk);
        }
        
        // เปิดการตรวจสอบ foreign key constraints กลับ
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('');
        $this->command->info('=== Transport Seeder Summary ===');
        $this->command->info("Total records: " . count($transports));
        $this->command->info("Images uploaded: {$successCount}");
        $this->command->warn("Images failed: {$failCount}");
    }
}
