<?php

namespace App\Services;

use App\Models\Tour;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TourPdfGenerator
{
    /**
     * Generate PDF from tour data and return binary content.
     * This is for real-time preview/streaming — no upload.
     *
     * @param Tour $tour
     * @return array{success: bool, content?: string, filename?: string, message?: string}
     */
    public function generate(Tour $tour): array
    {
        try {
            // Load relationships
            $tour->load([
                'countries',
                'cities',
                'itineraries',
                'transports.transport:id,code,name,type,image',
                'periods' => function ($query) {
                    $query->where('start_date', '>=', now()->toDateString())
                          ->where('status', 'open')
                          ->orderBy('start_date')
                          ->limit(20)
                          ->with('offer.promotions');
                },
                'wholesaler:id,name',
            ]);

            // Render HTML from Blade template
            $html = view('pdf.tour-program', [
                'tour' => $tour,
                'itineraries' => $tour->itineraries->sortBy('day_number'),
                'periods' => $tour->periods,
                'countries' => $tour->countries,
                'cities' => $tour->cities,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ])->render();

            // Configure mPDF with built-in Garuda font (Thai support)
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_header' => 5,
                'margin_footer' => 5,
                'default_font_size' => 11,
                'default_font' => 'garuda',
                'tempDir' => storage_path('app/mpdf-temp'),
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);

            $mpdf->SetTitle($tour->tour_code . ' - ' . $tour->title);
            $mpdf->SetAuthor('NowTravel');

            $mpdf->WriteHTML($html);

            // Return PDF as binary string
            $pdfContent = $mpdf->Output('', 'S');

            return [
                'success' => true,
                'content' => $pdfContent,
                'filename' => Str::slug($tour->tour_code) . '.pdf',
            ];
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'tour_id' => $tour->id,
                'tour_code' => $tour->tour_code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'สร้าง PDF ล้มเหลว: ' . $e->getMessage(),
            ];
        }
    }
}
