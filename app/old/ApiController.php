<?php
namespace App\Http\Controllers\Functions;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use ReallySimpleJWT\Token;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Webpanel\LogsController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManagerStatic as Image;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\Backend\TourModel;
use App\Models\Backend\TourGroupModel;
use App\Models\Backend\TourTypeModel;
use App\Models\Backend\TourDetailModel;
use App\Models\Backend\TourPeriodModel;

use App\Models\Backend\LandmassModel;
use App\Models\Backend\CountryModel;
use App\Models\Backend\CityModel;
use App\Models\Backend\ProvinceModel;
use App\Models\Backend\DistrictModel;
use App\Models\Backend\TravelTypeModel;
use App\Models\Backend\PromotionModel;
use App\Models\Backend\TourGalleryModel;
use App\Models\Backend\ImagePDFModel;
use setasign\Fpdi\Fpdi;

class ApiController extends Controller
{
    public function package_tour(){
        
        // echo ini_get('allow_url_fopen');

        ini_set('memory_limit', '2G');

        Log::info('Start zego_api');
        $this->zego_api();
        Log::info('Finished zego_api');
        Log::info('Start bestconsortium_api');
        $this->bestconsortium_api();
        Log::info('Finished bestconsortium_api');
        Log::info('Start ttn_api_japan');
        $this->ttn_api_japan();
        Log::info('Finished ttn_api_japan');
        Log::info('Start ttn_api_all');
        $this->ttn_api_all();
        Log::info('Finished ttn_api_all');
        Log::info('Start itravel_api');
        $this->itravel_api();
        Log::info('Finished itravel_api');
        Log::info('Start superbholiday_api');
        $this->superbholiday_api();
        Log::info('Finished superbholiday_api');
        Log::info('Start tourfactory_api');
        $this->tourfactory_api();
        Log::info('Finished tourfactory_api');
        Log::info('Start go365_api');
        $this->go365_api();
        Log::info('Finished go365_api');

        date_default_timezone_set("Asia/Bangkok");
        $data['datetime'] = date('Y-m-d H:i:s');
        DB::table('tb_cronjob')->insert($data);

    }

    public function save_pdf($filename){
        $data = ImagePDFModel::first();
        $image_heder = $data->header;
        $image_footer = $data->footer;

        $filePath = public_path($filename);
        $outputFilePath = public_path($filename);
        
        $fpdi = new Fpdi;
        $count = $fpdi->setSourceFile($filePath);
  
        for ($i=1; $i<=$count; $i++) {
            $template = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($template);
            $fpdi->AddPage($size['orientation'], array($size['width'], $size['height']));
            $fpdi->useTemplate($template);
            $fpdi->Image(public_path($image_heder), 0, 0,210);
            $fpdi->Image(public_path($image_footer), 0, 285,210);
            // $fpdi->Image("header-pic.jpg", 0, 0);
            // $fpdi->Image("footer-pic.jpg", 0, 260);
        }

        $fpdi->Output($outputFilePath, 'F');
    }


    
    public function zego_api(){ // ปรับเปลี่ยน API เส้นใหม่ 13-12-24
        try {
            DB::beginTransaction();
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "auth-token" => env('ZEGO_API_KEY'),
            ])
            ->get('https://www.zegoapi.com/v1.5/programtours');
            // ->get('https://www.zegoapi.com/v1.4/Lists/ProgramTour'); // 13-12-24

            if($response->successful()){

                $callback = $response->json();

                if(count($callback) > 0){
                    $tour = array();
                    $tour_api_id = array();
                    $period = array();
                    $period_api_id = array();
                    foreach($callback as $call){
                        // if($call['programtour_id'] == 968){

                        // 13-12-24
                        // $data = TourModel::where(['api_id'=>$call['programtour_id'], 'api_type'=>'zego'])->whereNull('deleted_at')->first();
                        $data = TourModel::where(['api_id'=>$call['ProductID'], 'api_type'=>'zego'])->whereNull('deleted_at')->first();
    
                        $allow_img = ['png', 'jpeg', 'jpg','webp'];
    
                        if($data == null){
                            $data = new TourModel;
    
                            $code_tour = IdGenerator::generate([
                                'table' => 'tb_tour', 
                                'field' => 'code', 
                                'length' => 10, 
                                'prefix' =>'NT'.date('ym'),
                                'reset_on_prefix_change' => true 
                            ]);
    
                            $data->code = $code_tour;
    
                            // 13-12-24
                            // if($call['Country']){
                            //     $country = CountryModel::where('country_name_en', 'like', '%'.$call['Country'].'%')->where('status','on')->whereNull('deleted_at')->first();
                            if($call['CountryName']){
                                $country = CountryModel::where('country_name_en', 'like', '%'.$call['CountryName'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                $arr = array();
                                if($country){
                                    $arr[] = "$country->id";
                                    $data->country_id = json_encode($arr);
                                }else{
                                    $data->country_id = "[]";
                                }
                            }
    
                            // 13-12-24
                            // if($call['Airline']){
                            //     $airline = TravelTypeModel::where('code',$call['Airline'])->where('status','on')->whereNull('deleted_at')->first();
                            if($call['AirlineCode']){
                                $airline = TravelTypeModel::where('code',$call['AirlineCode'])->where('status','on')->whereNull('deleted_at')->first();
                                if($airline){
                                    $data->airline_id = $airline->id;
                                }
                            }
    
                            // 13-12-24
                            // if ($call['UrlImage']) {
        
                            //     $path = $call['UrlImage'];
                            if ($call['URLImage']) {
        
                                $path = $call['URLImage'];
    
                                $response = Http::get($path);
    
                                if ($response->successful()) {
    
                                    $filename = basename($path);
            
                                    $lg = Image::make($path);
                                    $ext = explode("/", $lg->mime());
                                    $lg->resize(600, 600)->stream();
                                    // $lg->resize(784, 522)->stream();
                                    
                                    $new = 'upload/tour/zegoapi/' . $filename;
                                    // $new = 'upload/tour/zegoapi/' . date('dmY-Hism') . '.' . $ext[1];
                                    
                                    if (in_array($ext[1], $allow_img)) {
                                        Storage::disk('public')->put($new, $lg);
                                        $data->image = $new;
                                    }
                                }
                            }
    
                            $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                            $data->name = $call['ProductName'];
                            $data->description = str_replace("\n","",$call['Highlight']);
    
                        }else{
                            if($data->country_check_change == null){
                                // 13-12-24
                                // if($call['Country']){
                                //     $country = CountryModel::where('country_name_en', 'like', '%'.$call['Country'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                if($call['CountryName']){
                                    $country = CountryModel::where('country_name_en', 'like', '%'.$call['CountryName'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                    $arr = array();
                                    if($country){
                                        $arr[] = "$country->id";
                                        $data->country_id = json_encode($arr);
                                    }else{
                                        $data->country_id = "[]";
                                    }
                                }
                            }
    
                            if($data->airline_check_change == null){
                                // 13-12-24
                                // if($call['Airline']){
                                //     $airline = TravelTypeModel::where('code',$call['Airline'])->where('status','on')->whereNull('deleted_at')->first();
                                if($call['AirlineCode']){
                                    $airline = TravelTypeModel::where('code',$call['AirlineCode'])->where('status','on')->whereNull('deleted_at')->first();
                                    if($airline){
                                        $data->airline_id = $airline->id;
                                    }
                                }
                            }
    
                            if($data->name_check_change == null){
                                $data->name = $call['ProductName'];
                            }
    
                            if($data->description_check_change == null){
                                $data->description = str_replace("\n","",$call['Highlight']);
                            }
    
                            if($data->image_check_change == 2){
                                // 13-12-24
                                // if ($call['UrlImage']) {
    
                                //     $path = $call['UrlImage'];
                                if ($call['URLImage']) {
    
                                    $path = $call['URLImage'];
    
                                    $response = Http::get($path);
    
                                    if ($response->successful()) {
    
                                        if ($data->image != null) {
                                            Storage::disk('public')->delete($data->image);
                                        }
    
                                        $filename = basename($path);
                
                                        $lg = Image::make($path);
                                        $ext = explode("/", $lg->mime());
                                        $lg->resize(600, 600)->stream();
                                        
                                        $new = 'upload/tour/zegoapi/' . $filename;
                                        
                                        if (in_array($ext[1], $allow_img)) {
                                            Storage::disk('public')->put($new, $lg);
                                            $data->image = $new;
                                        }
                                    }
        
                                }
                            }
    
                        }
    
                        $data->api_id = $call['ProductID']; // 13-12-24
                        // $data->api_id = $call['programtour_id'];
                        $data->code1 = $call['ProductCode'];
                        $data->rating = $call['MaxHotelStars']; // 13-12-24
                        // $data->rating = $call['HotelStar'];
    
                        // จัดการ wholesale_id ให้เป็น zego
                        $data->group_id = 3;
                        $data->wholesale_id = 3;
    
                        $allow_word = ['doc', 'docx'];
                        $allow_pdf = ['pdf'];
    
                        if ($call['FilePDF']) {
                            $url = $call['FilePDF'];
    
                            $headers = get_headers($url, 1);
    
                            $response = Http::get($url);
    
                            if ($response->successful()) {
    
                                if($response->header('Content-Type')){
                                
                                    $contentType = $response->header('Content-Type');
    
                                    if(strpos($contentType, 'application/pdf') !== false){
    
                                        $filename = basename($url);
                                        $ext = explode(".", $filename);
                                        $new = 'upload/tour/pdf_file/zegoapi/' . $filename;
                                        $newFileSize = strlen($response->body());
            
                                        if(Storage::disk('public')->exists($data->pdf_file)){
            
                                            // $newFileSize = $response->header('content-length');
                                            // $newFileSize = strlen($response->body());
                                            // $oldFileSize = Storage::disk('public')->size($data->pdf_file);
    
                                            // $oldFileSize = $data->pdf_file_size;
                                            // if($newFileSize != $oldFileSize){
                                            //     Storage::disk('public')->delete($data->pdf_file);
            
                                            //     Storage::disk('public')->put($new, $response->body());
                                            //     $img_pdf = ImagePDFModel::first();
                                            //     if($img_pdf->status == 'on'){
                                            //         $this->save_pdf($new);
                                            //     }
    
                                            //     $data->pdf_file = $new;
                                            //     $data->pdf_file_size = $newFileSize;
                                            // }
    
                                            // เช็ค Date Modified
                                            if(isset($headers['Last-Modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                $oldModified = $data->date_mod_pdf;
                                                if($lastModified != $oldModified){
                                                    Storage::disk('public')->delete($data->pdf_file);
            
                                                    Storage::disk('public')->put($new, $response->body());
                                                    $img_pdf = ImagePDFModel::first();
                                                    $pdfversion = 0;
                                                    if($img_pdf->status == 'on'){
                                                        $filepdf = fopen(public_path($new),"r");
                                                        $line_first = fgets($filepdf);
                                                        preg_match_all('!\d+!', $line_first, $matches);
                                                        $pdfversion = implode('.', $matches[0]);

                                                        if($pdfversion > 1.6){
                                                            $this->save_pdf($new);
                                                        }
                                                        // $this->save_pdf($new);
                                                    }
                                                    $data->pdf_file = $new;
                                                    $data->date_mod_pdf = $lastModified;
                                                }
                                            }
            
                                        }else{

                                            // เช็ค Date Modified
                                            if(isset($headers['Last-Modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                Storage::disk('public')->put($new, $response->body());
                                                $img_pdf = ImagePDFModel::first();
                                                $pdfversion = 0;
                                                if($img_pdf->status == 'on'){
                                                    $filepdf = fopen(public_path($new),"r");
                                                    $line_first = fgets($filepdf);
                                                    preg_match_all('!\d+!', $line_first, $matches);
                                                    $pdfversion = implode('.', $matches[0]);

                                                    if($pdfversion > 1.6){
                                                        $this->save_pdf($new);
                                                    }
                                                    // $this->save_pdf($new);
                                                }
                                                $data->pdf_file = $new;
                                                $data->date_mod_pdf = $lastModified;
                                            }
                                        }
    
                                    }
                                }
                            }
                        }
    
                        $data->data_type = 2; // 1 system , 2 api
                        $data->api_type = "zego";
    
                        if($data->save()){
    
                            $tour[] = $data->id;
                            $tour_api_id[] = $data->api_id;
                            
                            if($call['Periods']){
                                $max = array();
                                $cal1 = 0;
                                $cal2 = 0;
                                $cal3 = 0;
                                $cal4 = 0;
                                foreach($call['Periods'] as $pe){
    
                                    $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_api_id'=>$pe['PeriodID'], 'api_type'=>'zego'])->whereNull('deleted_at')->first();

                                    if($data3 == null){
                                        $data3 = new TourPeriodModel;
                                    }

                                    // $price_start = $pe['Price_Start']; // ราคาเริ่มต้น
                                    // $price_end = $pe['Price_End']; // ราคาสิ้นสุด
                                    // $price1 = $pe['Price_Twin']; // ผู้ใหญ่พักคู่
                                    // $price2 = $pe['Price_Single']; //ผู้ใหญ่พักเดี่ยว
                                    // $price3 = $pe['Price_Child']; // เด็กมีเตียง
                                    // $price4 = $pe['Price_ChildNB']; // เด็กไม่มีเตียง

                                    // 13-12-24
                                    $price_start = $pe['Price']; // ราคาเริ่มต้นผู้ใหญ่พักคู่
                                    $price_end = $pe['Price_End']; // ราคาสิ้นสุดผู้ใหญ่พักคู่
                                    $price1 = $pe['Price']; // ผู้ใหญ่พักคู่

                                    $price_single_start = $pe['Price_Single_Bed']; //ราคาเริ่มต้นผู้ใหญ่พักเดี่ยว
                                    $price_single_end = $pe['Price_Single_Bed_End']; //ราคาสิ้นสุดผู้ใหญ่พักเดี่ยว
                                    $price2 = $pe['Price_Single_Bed']; //ผู้ใหญ่พักเดี่ยว

                                    $price_child_start = $pe['Price_Child']; //ราคาเริ่มต้นเด็กมีเตียง
                                    $price_child_end = $pe['Price_Child_End']; //ราคาสิ้นสุดเด็กมีเตียง
                                    $price3 = $pe['Price_Child']; // เด็กมีเตียง

                                    $price_childnb_start = $pe['Price_ChildNB']; //ราคาเริ่มต้นเด็กไม่มีเตียง
                                    $price_childnb_end = $pe['Price_ChildNB_End']; //ราคาสิ้นสุดเด็กไม่มีเตียง
                                    $price4 = $pe['Price_ChildNB']; // เด็กไม่มีเตียง

                                    // ผู้ใหญ่พักคู่
                                    if($price_start > $price_end && $price_end > 0){
                                        $cal = $price_start - $price_end;
                                        if($cal > 0){
                                            $data3->price1 = $price_start;
                                            if($cal < $price_start){
                                                $data3->special_price1 = $cal;
                                            }else{
                                                $data3->special_price1 = 0.00;
                                            }
                                            if (isset($price_start) && $price_start != 0) {
                                                $cal1 = ($cal / $price_start) * 100;
                                            } else {
                                                $cal1 = 0;
                                            }
                                        }
                                    }else{
                                        $data3->price1 = $price1;
                                        $cal1 = 0;
                                    }

                                    // ผู้ใหญ่พักเดี่ยว
                                    if($price_single_start > $price_single_end && $price_single_end > 0){
                                        $cal = $price_single_start - $price_single_end;
                                        if($cal > 0){
                                            $data3->price2 = $price_single_start;
                                            if($cal < $price_single_start){
                                                $data3->special_price2 = $cal;
                                            }else{
                                                $data3->special_price2 = 0.00;
                                            }
                                            if (isset($price_single_start) && $price_single_start != 0) {
                                                $cal2 = ($cal / $price_single_start) * 100;
                                            } else {
                                                $cal2 = 0;
                                            }
                                        }
                                    }else{
                                        $data3->price2 = $price2;
                                        $cal2 = 0;
                                    }

                                    // เด็กมีเตียง
                                    if($price_child_start > $price_child_end && $price_child_end > 0){
                                        $cal = $price_child_start - $price_child_end;
                                        if($cal > 0){
                                            $data3->price3 = $price_child_start;
                                            if($cal < $price_child_start){
                                                $data3->special_price3 = $cal;
                                            }else{
                                                $data3->special_price3 = 0.00;
                                            }
                                            if (isset($price_child_start) && $price_child_start != 0) {
                                                $cal3 = ($cal / $price_child_start) * 100;
                                            } else {
                                                $cal3 = 0;
                                            }
                                        }
                                    }else{
                                        $data3->price3 = $price3;
                                        $cal3 = 0;
                                    }

                                    // เด็กไม่มีเตียง
                                    if($price_childnb_start > $price_childnb_end && $price_childnb_end > 0){
                                        $cal = $price_childnb_start - $price_childnb_end;
                                        if($cal > 0){
                                            $data3->price4 = $price_childnb_start;
                                            if($cal < $price_childnb_start){
                                                $data3->special_price4 = $cal;
                                            }else{
                                                $data3->special_price4 = 0.00;
                                            }
                                            if (isset($price_childnb_start) && $price_childnb_start != 0) {
                                                $cal4 = ($cal / $price_childnb_start) * 100;
                                            } else {
                                                $cal4 = 0;
                                            }
                                        }
                                    }else{
                                        $data3->price4 = $price4;
                                        $cal4 = 0;
                                    }
    
                                    $data3->tour_id = $data->id;
                                    $data3->period_api_id = $pe['PeriodID'];
                                    $data3->group_date = date('mY',strtotime($pe['PeriodStartDate']));
                                    $data3->start_date = $pe['PeriodStartDate'];
                                    $data3->end_date = $pe['PeriodEndDate'];
                                    // $data3->day = $pe['Day'];
                                    // $data3->night = $pe['Night'];
                                    $data3->day = $call['Days']; // 13-12-24
                                    $data3->night = $call['Nights']; // 13-12-24
                                    $data3->group = $pe['GroupSize'];
                                    $data3->count = $pe['Seat'];
                                    $data3->status_display = "on";
    
                                    // if($pe['Status'] == "Book"){
                                    //     $data3->status_period = 1;
                                    // }else if($pe['Status'] == "Waitlist"){
                                    //     $data3->status_period = 2;
                                    // // }else if($pe['Status'] == "Close group" || $pe['Status'] == "Soldout"){
                                    // }else if($pe['Status'] == "Close Group" || $pe['Status'] == "Soldout"){ // 13-12-24
                                    //     $data3->status_period = 3;
                                    // }
                                    if($pe['PeriodStatus'] == "Book"){
                                        $data3->status_period = 1;
                                    }else if($pe['PeriodStatus'] == "Waitlist"){
                                        $data3->status_period = 2;
                                    // }else if($pe['Status'] == "Close group" || $pe['Status'] == "Soldout"){
                                    }else if($pe['PeriodStatus'] == "Close Group" || $pe['PeriodStatus'] == "Soldout"){ // 13-12-24
                                        $data3->status_period = 3;
                                    }
                                    $data3->api_type = "zego";
    
                                    if($data3->save()){
                                        $period[] = $data3->id;
                                        $period_api_id[] = $data3->period_api_id;
                                    }
                                    
                                    $calmax = max($cal1, $cal2, $cal3, $cal4);
                                    array_push($max, $calmax);
                                }
    
                                // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                // $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'zego'])->whereNull('deleted_at')->orderby('start_date','asc')->first(); // asc
                                $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'zego'])->whereNull('deleted_at')->get();
                                if($data4){
                                    // $maxSpecialPrice = $data4->max('special_price1');
    
                                    $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                        return $item->price1 - $item->special_price1;
                                    })->first();
            
                                    // $data5 = $data4->where('special_price1', $maxSpecialPrice)->first();
                                    if($data5){
                                        $num_day = "";
                                        if($data5->day && $data5->night){
                                            $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                        }
                                        $price = $data5->price1;
                                        $special_price = $data5->special_price1;
                                        // if($data5->special_price1 > 0){
                                        //     $special_price = $price - $data5->special_price1;
                                        // }else{
                                        //     $special_price = 0.00;
                                        // }
    
                                        if($special_price && $special_price > 0){
                                            $net_price = $price - $special_price;
                                        }else{
                                            $net_price = $price;
                                        }
    
                                        if($net_price && $net_price > 0){
                                            if($net_price <= 10000 ){
                                                $price_group = 1;
                                            }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                $price_group = 2;
                                            }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                $price_group = 3;
                                            }
                                            else if($net_price  > 30000 && $net_price <= 50000  ){
                                                $price_group = 4;
                                            }
                                            else if($net_price  > 50000 && $net_price <= 80000  ){
                                                $price_group = 5;
                                            }else if($net_price  > 80000  ){
                                                $price_group = 6;
                                            }
                                        }else{
                                            $price_group = 0;
                                        }
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'zego'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                    }
                                }
    
                                $maxCheck = max($max);
                                if($maxCheck > 0 && $maxCheck >= 30){
                                    TourModel::where(['id'=>$data->id, 'api_type'=>'zego'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                }elseif($maxCheck > 0 && $maxCheck < 30){
                                    TourModel::where(['id'=>$data->id, 'api_type'=>'zego'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                }else{
                                    TourModel::where(['id'=>$data->id, 'api_type'=>'zego'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                }
    
                            }
    
                            DB::commit();
                        }else{
                            DB::rollback();
                        }
    
                        // } // endif $call['programtour_id']
                        
                    } // end foreach
                    
                    // ลบข้อมูลทัวร์ และ Period
                    TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','zego')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','zego')->update(['deleted_at'=>null]);
                    TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','zego')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','zego')->update(['deleted_at'=>null]);
                    
                }
            }

        } catch (\Exception $e) {
            DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'zego = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);

            // return view("$this->prefix.alert.alert", [
            //     'url' => $error_url,
            //     'title' => "เกิดข้อผิดพลาดทางโปรแกรม",
            //     'text' => "กรุณาแจ้งรหัส Code : $log_id ให้ทางผู้พัฒนาโปรแกรม ",
            //     'icon' => 'error'
            // ]);
        }
    }

    public function bestconsortium_api(){ // status_period ลูกค้าแจ้งมาให้เอง ปรับเปลี่ยน API เส้นใหม่ 13-12-24
        try {
            // DB::beginTransaction();
            $response = Http::withHeaders([
                "Content-Type" => "application/json; charset=UTF-8",
            ])
            // ->withoutVerifying()
            ->get('https://api.best-consortium.com/v1/series/country');
            
            if($response->successful()){

                $callback1 = $response->json();

                if(count($callback1) > 0){

                    $error_code = array();
                    $tour = array();
                    $tour_api_id = array();
                    // $tour_call2_api_id = array();
                    $period = array();
                    $period_api_id = array();
                    foreach($callback1 as $call1){

                        $response = Http::withHeaders([
                            "Content-Type" => "application/json; charset=UTF-8",
                        ])
                        // ->withoutVerifying()
                        // ->get('https://tour-api.bestinternational.com/api/tour-programs/v2/30');
                        ->get('https://tour-api.bestinternational.com/api/tour-programs/v2/'.$call1['id']);
                        // ->get('https://api.best-consortium.com/v1/series/'.$call1['id']); // 13-12-24
                        
                        $remaining = $response->header('X-RateLimit-Remaining');
                        $resetTime = $response->header('X-RateLimit-Reset');
                        
                        if ($remaining !== null && $resetTime !== null) {
                            if ($remaining == "") {
                                if (!is_numeric($resetTime)) {
                                    $resetTime = time() + 60;
                                }
                                $waitTime = max(1, $resetTime - time());
                                sleep($waitTime);
                                $response = Http::withHeaders([
                                    "Content-Type" => "application/json; charset=UTF-8",
                                ])
                                ->get('https://tour-api.bestinternational.com/api/tour-programs/v2/'.$call1['id']);
                            }
                        } else {
                            if ($response->status() == 429 || $response->status() == 404) {
                                if (!in_array($call1['id'], $error_code)) {
                                    $error_code[$response->status()] = $response->status().'-'.$call1['id'];
                                }
                                sleep(60);
                                $response = Http::withHeaders([
                                    "Content-Type" => "application/json; charset=UTF-8",
                                ])
                                ->get('https://tour-api.bestinternational.com/api/tour-programs/v2/'.$call1['id']);
                            }
                        }
                        
                        if($response->successful()){

                            $callback2 = $response->json();

                            foreach($callback2 as $call2){

                                // if (!in_array($call2['id'], $tour_call2_api_id)) {
                                //     $tour_call2_api_id[] = $call2['id'];
                                // }

                                try {
                                    // DB::beginTransaction();
                                    
                                    $data = TourModel::where(['api_id'=>$call2['id'], 'api_type'=>'best'])->whereNull('deleted_at')->first();
            
                                    $allow_img = ['png', 'jpeg', 'jpg','webp'];
            
                                    if($data == null){
                                        $data = new TourModel;
            
                                        $code_tour = IdGenerator::generate([
                                            'table' => 'tb_tour', 
                                            'field' => 'code', 
                                            'length' => 10, 
                                            'prefix' =>'NT'.date('ym'),
                                            'reset_on_prefix_change' => true 
                                        ]);
            
                                        $data->code = $code_tour;
            
                                        if($call1['nameEng']){
                                            $country = CountryModel::where('country_name_en', 'like', '%'.$call1['nameEng'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                            $arr = array();
                                            if($country){
                                                $arr[] = "$country->id";
                                                $data->country_id = json_encode($arr);
                                            }else{
                                                $data->country_id = "[]";
                                            }
                                        }
            
                                        // 13-12-24
                                        // if($call2['airline']){
                                        //     $airline = TravelTypeModel::where('code',$call2['airline'])->where('status','on')->whereNull('deleted_at')->first();
                                        if($call2['airline_name']){
                                            $airline = TravelTypeModel::where('travel_name', 'like', '%'.$call2['airline_name'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                            if($airline){
                                                $data->airline_id = $airline->id;
                                            }
                                        }
            
                                        if ($call2['bannerSq']) {
                    
                                            $path = $call2['bannerSq'];
            
                                            $response = Http::get($path);
            
                                            if ($response->successful()) {

                                                $contentLength = $response->header('Content-Length');
            
                                                if(!empty($contentLength) && intval($contentLength) > 0){

                                                    $filename = basename($path);
                                                    $lg = Image::make($path);
                                                    $ext = explode("/", $lg->mime());
                                                    $lg->resize(600, 600)->stream();
                                                    
                                                    $new = 'upload/tour/bestapi/' . $filename;
                                                    
                                                    if (in_array($ext[1], $allow_img)) {
                                                        Storage::disk('public')->put($new, $lg);
                                                        $data->image = $new;
                                                    }

                                                }
                                                
                                            }
                                        }
            
                                        $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                                        $data->name = $call2['name'];
                                        // $data->description = str_replace("\n","",$call2['Highlight']); // ไม่มี
            
                                    }else{
                                        if($data->country_check_change == null){
                                            if($call1['nameEng']){
                                                $country = CountryModel::where('country_name_en', 'like', '%'.$call1['nameEng'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                                $arr = array();
                                                if($country){
                                                    $arr[] = "$country->id";
                                                    $data->country_id = json_encode($arr);
                                                }else{
                                                    $data->country_id = "[]";
                                                }
                                            }
                                        }
            
                                        if($data->airline_check_change == null){
                                            // 13-12-24
                                            // if($call2['airline']){
                                            //     $airline = TravelTypeModel::where('code',$call2['airline'])->where('status','on')->whereNull('deleted_at')->first();
                                            if($call2['airline_name']){
                                                $airline = TravelTypeModel::where('travel_name', 'like', '%'.$call2['airline_name'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                                if($airline){
                                                    $data->airline_id = $airline->id;
                                                }
                                            }
                                        }
            
                                        if($data->name_check_change == null){
                                            $data->name = $call2['name'];
                                        }
            
                                        // if($data->description_check_change == null){ // ไม่มี
                                        //     $data->description = str_replace("\n","",$call2['Highlight']);
                                        // }
            
                                        if($data->image_check_change == 2){
                                            if ($call2['bannerSq']) {
            
                                                $path = $call2['bannerSq'];
                                                
                                                $response = Http::get($path);
            
                                                if ($response->successful()) {

                                                    $contentLength = $response->header('Content-Length');

                                                    if(!empty($contentLength) && intval($contentLength) > 0){

                                                        if ($data->image != null) {
                                                            Storage::disk('public')->delete($data->image);
                                                        }
                
                                                        $filename = basename($path);
                                
                                                        $lg = Image::make($path);
                                                        $ext = explode("/", $lg->mime());
                                                        $lg->resize(600, 600)->stream();
                                                        
                                                        $new = 'upload/tour/bestapi/' . $filename;
                                                        
                                                        if (in_array($ext[1], $allow_img)) {
                                                            Storage::disk('public')->put($new, $lg);
                                                            $data->image = $new;
                                                        }

                                                    }
            
                                                }
                    
                                            }
                                        }
            
                                    }
            
                                    $data->api_id = $call2['id'];
                                    $data->code1 = $call2['code'];
                                    // $data->rating = $call2['HotelStar']; // ไม่มี
            
                                    // จัดการ wholesale_id ให้เป็น best
                                    $data->group_id = 3;
                                    $data->wholesale_id = 11;
            
                                    $allow_word = ['doc', 'docx'];
                                    $allow_pdf = ['pdf'];
            
                                    if ($call2['filePdf']) {
                                        $url = $call2['filePdf'];

                                        $headers = get_headers($url, 1);
            
                                        $response = Http::get($url);
            
                                        if ($response->successful()) {
            
                                            if($response->header('Content-Type')){
                                    
                                                $contentType = $response->header('Content-Type');
                
                                                if(strpos($contentType, 'application/pdf') !== false){
            
                                                    $path = parse_url($url, PHP_URL_PATH); // 13-12-24
                                                    $filename = basename($path); // 13-12-24
                                                    // $filename = basename($url); // 13-12-24
                                                    $ext = explode(".", $filename);
                                                    $new = 'upload/tour/pdf_file/bestapi/' . $filename .'.pdf';
                                                    $newFileSize = strlen($response->body());
            
                                                    if(Storage::disk('public')->exists($data->pdf_file)){
            
                                                        // $newFileSize = strlen($response->body());
                                                        // $oldFileSize = Storage::disk('public')->size($data->pdf_file);
            
                                                        // $oldFileSize = $data->pdf_file_size;
                                                        // if($newFileSize != $oldFileSize){
                                                        //     Storage::disk('public')->delete($data->pdf_file);
            
                                                        //     Storage::disk('public')->put($new, $response->body());
                                                        //     $img_pdf = ImagePDFModel::first();
                                                        //     if($img_pdf->status == 'on'){
                                                        //         $this->save_pdf($new);
                                                        //     }
                                                        //     $data->pdf_file = $new;
                                                        //     $data->pdf_file_size = $newFileSize;
                                                        // }
            
                                                        // เช็ค Date Modified
                                                        if(isset($headers['Last-Modified'])){
                                                            $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                            $oldModified = $data->date_mod_pdf;
                                                            if($lastModified != $oldModified){
                                                                Storage::disk('public')->delete($data->pdf_file);
                        
                                                                Storage::disk('public')->put($new, $response->body());
                                                                $img_pdf = ImagePDFModel::first();
                                                                $pdfversion = 0;
                                                                if($img_pdf->status == 'on'){
                                                                    $filepdf = fopen(public_path($new),"r");
                                                                    $line_first = fgets($filepdf);
                                                                    preg_match_all('!\d+!', $line_first, $matches);
                                                                    $pdfversion = implode('.', $matches[0]);

                                                                    if($pdfversion > 1.6){
                                                                        $this->save_pdf($new);
                                                                    }
                                                                    // $this->save_pdf($new);
                                                                }
                                                                $data->pdf_file = $new;
                                                                $data->date_mod_pdf = $lastModified;
                                                            }
                                                        }
                                                    }else{
                                                        // Storage::disk('public')->put($new, $response->body());
                                                        // $img_pdf = ImagePDFModel::first();
                                                        // if($img_pdf->status == 'on'){
                                                        //     $this->save_pdf($new);
                                                        // }
                                                        // $data->pdf_file = $new;
                                                        // $data->pdf_file_size = $newFileSize;
            
                                                        if(isset($headers['Last-Modified'])){
                                                            $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                            Storage::disk('public')->put($new, $response->body());
                                                            $img_pdf = ImagePDFModel::first();
                                                            $pdfversion = 0;
                                                            if($img_pdf->status == 'on'){
                                                                $filepdf = fopen(public_path($new),"r");
                                                                $line_first = fgets($filepdf);
                                                                preg_match_all('!\d+!', $line_first, $matches);
                                                                $pdfversion = implode('.', $matches[0]);

                                                                if($pdfversion > 1.6){
                                                                    $this->save_pdf($new);
                                                                }
                                                                // $this->save_pdf($new);
                                                            }
                                                            $data->pdf_file = $new;
                                                            $data->date_mod_pdf = $lastModified;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
            
                                    $data->data_type = 2; // 1 system , 2 api
                                    $data->api_type = "best";
            
                                    if($data->save()){
            
                                        // $tour[] = $data->id;
                                        // $tour_api_id[] = $data->api_id;

                                        if (!in_array($data->id, $tour)) {
                                            $tour[] = $data->id;
                                        }
                                        if (!in_array($data->api_id, $tour_api_id)) {
                                            $tour_api_id[] = $data->api_id;
                                        }
                                        
                                        if($call2['period']){
                                            $max = array();
                                            $cal1 = 0;
                                            $cal2 = 0;
                                            $cal3 = 0;
                                            $cal4 = 0;
                                            foreach($call2['period'] as $pe){
            
                                                $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_api_id'=>$pe['pid'], 'api_type'=>'best'])->whereNull('deleted_at')->first();
            
                                                // $price1_old = $pe['adultPrice_old']; // ผู้ใหญ่พักคู่เดิม
                                                // $price1 = $pe['adultPrice']; // ผู้ใหญ่พักคู่
                                                // $price2 = $pe['singlePrice']; //ผู้ใหญ่พักเดี่ยว
                                                // $price3 = $pe['childWbPrice']; // เด็กมีเตียง
                                                // $price4 = $pe['childNbPrice']; // เด็กไม่มีเตียง

                                                $price1_old = is_numeric($pe['adultPrice_old']) ? floatval($pe['adultPrice_old']) : 0; // ผู้ใหญ่พักคู่เดิม
                                                $price1 = is_numeric($pe['adultPrice']) ? floatval($pe['adultPrice']) : 0; // ผู้ใหญ่พักคู่
                                                $price2 = is_numeric($pe['singlePrice']) ? floatval($pe['singlePrice']) : 0; //ผู้ใหญ่พักเดี่ยว
                                                $price3 = is_numeric($pe['childWbPrice']) ? floatval($pe['childWbPrice']) : 0; // เด็กมีเตียง
                                                $price4 = is_numeric($pe['childNbPrice']) ? floatval($pe['childNbPrice']) : 0; // เด็กไม่มีเตียง
            
            
                                                if($data3 == null){
                                                    $data3 = new TourPeriodModel;
                                                    if($price1_old > $price1 && $price1 > 0){
                                                        $cal = $price1_old - $price1;
                                                        if($cal > 0){
                                                            $data3->price1 = $price1_old;
                                                            if($cal < $price1_old){
                                                                $data3->special_price1 = $cal;
                                                            }else{
                                                                $data3->special_price1 = 0.00;
                                                            }
                                                            if (isset($price1_old) && $price1_old != 0) {
                                                                $cal1 = ($cal / $price1_old) * 100;
                                                            } else {
                                                                $cal1 = 0;
                                                            }
                                                        }
                                                    }else{
                                                        $data3->price1 = $price1;
                                                        $cal1 = 0;
                                                    }
                                                    
                                                    $data3->price2 = $price2;
                                                    $data3->price3 = $price3;
                                                    $data3->price4 = $price4;
                                                }else{
                                                    if($price1_old > $price1 && $price1 > 0){
                                                        $cal = $price1_old - $price1;
                                                        if($cal > 0){
                                                            $data3->price1 = $price1_old;
                                                            if($cal < $price1_old){
                                                                $data3->special_price1 = $cal;
                                                            }else{
                                                                $data3->special_price1 = 0.00;
                                                            }
                                                            if (isset($price1_old) && $price1_old != 0) {
                                                                $cal1 = ($cal / $price1_old) * 100;
                                                            } else {
                                                                $cal1 = 0;
                                                            }
                                                        }
                                                    }else{
                                                        $data3->price1 = $price1;
                                                        $cal1 = 0;
                                                    }
            
                                                    // ราคาผู้ใหญ่ พักเดี่ยว ไม่ต้องคำนวณเปอเซ็นต์ส่วนลด
                                                    // $data3->old_price2 = 0.00;
                                                    // $data3->special_price2 = 0.00;
                                                    $data3->price2 = $price2;
                                                    $cal2 = 0;
            
                                                    $data3->price3 = $price3;
                                                    $cal3 = 0;
            
                                                    $data3->price4 = $price4;
                                                    $cal4 = 0;
                                                }
            
                                                $data3->tour_id = $data->id;
                                                $data3->period_api_id = $pe['pid'];
            
                                                if($pe['dateGo']){
                                                    $go = explode('/',$pe['dateGo']);
                                                    $dayGo = $go[1];
                                                    $monthGo = $go[0];
                                                    $yearGo = $go[2];
                                                    $dateGo = "$yearGo-$monthGo-$dayGo";
                                                }else{
                                                    $dateGo = "";
                                                }
                                                
                                                if($pe['dateBack']){
                                                    $back = explode('/',$pe['dateBack']);
                                                    $dayBack = $back[1];
                                                    $monthBack = $back[0];
                                                    $yearBack = $back[2];
                                                    $dateBack = "$yearBack-$monthBack-$dayBack";
                                                }else{
                                                    $dateBack = "";
                                                }
            
                                                // if($dateGo || $dateBack){
                                                //     $day = \Carbon\Carbon::parse($dateGo)->diffInDays(\Carbon\Carbon::parse($dateBack));
                                                //     $night = $day-1;
                                                // }else{
                                                //     $day = 0;
                                                //     $night = 0;
                                                // }

                                                // 8-8-24 หลิวเพิ่ม หาวันกับคืนจาก "5 วัน 3 คืน"
                                                preg_match_all('/\d+/', $call2['time'], $matches);
                                                $day = isset($matches[0][0]) ? $matches[0][0] : 0;
                                                $night = isset($matches[0][1]) ? $matches[0][1] : 0;
                                                
                                                // $data3->group_date = $call2['dateMon']; // ตัวใหม่ใช้อันนี้ได้ 13-12-24
                                                $data3->group_date = date('mY',strtotime($dateGo));
                                                $data3->start_date = $dateGo;
                                                $data3->end_date = $dateBack;
                                                $data3->day = $day;
                                                $data3->night = $night;
                                                $data3->group = $pe['groupSize'];
                                                if($pe['avbl'] == "เต็ม" || $pe['avbl'] == "ปิดกรุ๊ป" || $pe['avbl'] == "รอคิว" || $pe['avbl'] == "รอชำระเงิน" || $pe['avbl'] == "W/L"){
                                                    $data3->count = 0;
                                                }else{
                                                    $data3->count = $pe['avbl'];
                                                }
            
                                                // $pe['avbl'] มีสถานะทั้งหมด int, เต็ม, ปิดกรุ๊ป , รอคิว, รอชำระเงิน ????

                                                $data3->status_display = "on";
            
                                                if($pe['avbl'] == "เต็ม" || $pe['avbl'] == "ปิดกรุ๊ป"){
                                                    $data3->status_period = 3;
                                                }else if($pe['avbl'] == "รอชำระเงิน" || $pe['avbl'] == "รอคิว" || $pe['avbl'] == "W/L"){
                                                    $data3->status_period = 2;
                                                }else{
                                                    $data3->status_period = 1;
                                                }
                                                $data3->api_type = "best";
            
                                                if($data3->save()){
                                                    $period[] = $data3->id;
                                                    $period_api_id[] = $data3->period_api_id;
                                                }
                                                
                                                $calmax = max($cal1, $cal2, $cal3, $cal4);
                                                array_push($max, $calmax);
                                            }
            
                                            // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                            $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'best'])->whereNull('deleted_at')->get();
                                            if($data4){
                                                // $maxSpecialPrice = $data4->max('special_price1');
            
                                                $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                                    return $item->price1 - $item->special_price1;
                                                })->first();
                        
                                                // $data5 = $data4->where('special_price1', $maxSpecialPrice)->first();
                                                if($data5){
                                                    $num_day = "";
                                                    if($data5->day && $data5->night){
                                                        $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                                    }
                                                    $price = $data5->price1;
                                                    $special_price = $data5->special_price1;
            
                                                    if($special_price && $special_price > 0){
                                                        $net_price = $price - $special_price;
                                                    }else{
                                                        $net_price = $price;
                                                    }
            
                                                    if($net_price && $net_price > 0){
                                                        if($net_price <= 10000 ){
                                                            $price_group = 1;
                                                        }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                            $price_group = 2;
                                                        }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                            $price_group = 3;
                                                        }
                                                        else if($net_price  > 30000 && $net_price <= 50000  ){
                                                            $price_group = 4;
                                                        }
                                                        else if($net_price  > 50000 && $net_price <= 80000  ){
                                                            $price_group = 5;
                                                        }else if($net_price  > 80000  ){
                                                            $price_group = 6;
                                                        }
                                                    }else{
                                                        $price_group = 0;
                                                    }
                                                    TourModel::where(['id'=>$data->id, 'api_type'=>'best'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                                }
                                            }
            
                                            $maxCheck = max($max);
                                            if($maxCheck > 0 && $maxCheck >= 30){
                                                TourModel::where(['id'=>$data->id, 'api_type'=>'best'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                            }elseif($maxCheck > 0 && $maxCheck < 30){
                                                TourModel::where(['id'=>$data->id, 'api_type'=>'best'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                            }else{
                                                TourModel::where(['id'=>$data->id, 'api_type'=>'best'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                            }
            
                                        }
            
                                        // DB::commit();
                                    }
                                } catch (\Exception $e) {
                                    // DB::rollback();
                                    $error_tour_id = isset($data) ? $data->id : 'unknown';
        
                                    $error_log = 'best = '.$e->getMessage().' | tour_id = '.$error_tour_id;
                                    $error_line = $e->getLine();
                                    $type_log = 'backend';
                                    $error_url = url()->current();
                                    $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
                                    continue;
                                }

                            } // end foreach call2 ทัวร์รายประเทศ
                            
                        }

                    } // end foreach call1 ประเทศ

                    // \Log::info('Tour Id: ', $tour);
                    // \Log::info('Tour Api Id: ', $tour_api_id);
                    // \Log::info('Tour Call2 Api Id: ', $tour_call2_api_id);
                    // \Log::info('Api Error: ', $error_code);

                    // ลบข้อมูลทัวร์ และ Period
                    if (!empty($tour) && !empty($tour_api_id)) {
                        TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','best')->update(['deleted_at' => date('Y-m-d H:i:s')]);
                        TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','best')->update(['deleted_at' => null]);
                    }

                    if (!empty($period) && !empty($period_api_id)) {
                        TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','best')->update(['deleted_at' => date('Y-m-d H:i:s')]);
                        TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','best')->update(['deleted_at' => null]);
                    }
    
                    // ลบข้อมูลทัวร์ และ Period
                    // TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','best')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    // TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','best')->update(['deleted_at'=>null]);
                    // TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','best')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    // TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','best')->update(['deleted_at'=>null]);
                    
                }

            }


        } catch (\Exception $e) {
            // DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'best2 = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
        }
    }

    public function ttn_api_japan(){ // api_type = ttn ไฟล์ PDF ได้มาเป็น google drive แก้ไขโดยบันทึกเป็นลิ้ง google drive
        try {
            // DB::beginTransaction();
            $response = Http::withHeaders([
                "Content-Type" => "application/json; charset=UTF-8",
            ])
            ->get('https://online.ttnconnect.com/api/agency/get-programId');
            
            if($response->successful()){

                $callback1 = $response->json();

                if(count($callback1) > 0){
                    $tour = array();
                    $tour_api_id = array();
                    $period = array();
                    $period_api_id = array();
                    foreach($callback1 as $call1){
                        $response = Http::withHeaders([
                            "Content-Type" => "application/json; charset=UTF-8",
                        ])
                        ->get('https://online.ttnconnect.com/api/agency/program/'.$call1['P_ID']);
                        
                        if($response->successful()){

                            $callback2 = $response->json();

                            foreach($callback2 as $call2){
                                    
                                $data = TourModel::where(['api_id'=>$call2['P_ID'], 'api_type'=>'ttn'])->whereNull('deleted_at')->first();
        
                                $allow_img = ['png', 'jpeg', 'jpg','webp'];
        
                                if($data == null){
                                    $data = new TourModel;
        
                                    $code_tour = IdGenerator::generate([
                                        'table' => 'tb_tour', 
                                        'field' => 'code', 
                                        'length' => 10, 
                                        'prefix' =>'NT'.date('ym'),
                                        'reset_on_prefix_change' => true 
                                    ]);
        
                                    $data->code = $code_tour;

                                    $country = CountryModel::where('country_name_en', 'like', '%JAPAN%')->where('status','on')->whereNull('deleted_at')->first();
                                    $arr = array();
                                    if($country){
                                        $arr[] = "$country->id";
                                        $data->country_id = json_encode($arr);
                                    }else{
                                        $data->country_id = "[]";
                                    }

                                    if($call2['P_LOCATION']){
                                        $city = CityModel::where('city_name_en', 'like', '%'.$call2['P_LOCATION'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                        $arr_ci = array();
                                        if($city){
                                            $arr_ci[] = "$city->id";
                                            $data->city_id = json_encode($arr_ci);
                                        }else{
                                            $data->city_id = "[]";
                                        }
                                    }
        
                                    if($call2['P_AIRLINE']){
                                        $airline = TravelTypeModel::where('code',$call2['P_AIRLINE'])->where('status','on')->whereNull('deleted_at')->first();
                                        if($airline){
                                            $data->airline_id = $airline->id;
                                        }
                                    }
        
                                    if ($call2['BANNER']) {
                
                                        $path = $call2['BANNER'];
        
                                        $response = Http::get($path);
        
                                        if ($response->successful()) {

                                            $contentLength = $response->header('Content-Length');
        
                                            if(!empty($contentLength) && intval($contentLength) > 0){

                                                $urlParts = parse_url($path);
                                                parse_str($urlParts['query'], $queryParams);
                                                $filePath = $queryParams['url'] ?? ''; // ดึง URL ของไฟล์ภาพ
                                                $filename = basename($filePath);

                                                // $filename = basename($path);
                                                $lg = Image::make($path);
                                                $ext = explode("/", $lg->mime());
                                                $lg->resize(600, 600)->stream();
                                                
                                                $new = 'upload/tour/ttnapi/' . $filename;
                                                
                                                if (in_array($ext[1], $allow_img)) {
                                                    Storage::disk('public')->put($new, $lg);
                                                    $data->image = $new;
                                                }

                                            }
                                            
                                        }
                                    }
        
                                    $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                                    $data->name = $call2['P_NAME'];
                                    $data->description = $call2['P_HIGHLIGHT'];
        
                                }else{
                                    if($data->country_check_change == null){
                                        $country = CountryModel::where('country_name_en', 'like', '%JAPAN%')->where('status','on')->whereNull('deleted_at')->first();
                                        $arr = array();
                                        if($country){
                                            $arr[] = "$country->id";
                                            $data->country_id = json_encode($arr);
                                        }else{
                                            $data->country_id = "[]";
                                        }
            
                                        if($call2['P_LOCATION']){
                                            $city = CityModel::where('city_name_en', 'like', '%'.$call2['P_LOCATION'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                            $arr_ci = array();
                                            if($city){
                                                $arr_ci[] = "$city->id";
                                                $data->city_id = json_encode($arr_ci);
                                            }else{
                                                $data->city_id = "[]";
                                            }
                                        }
                                    }
        
                                    if($data->airline_check_change == null){
                                        if($call2['P_AIRLINE']){
                                            $airline = TravelTypeModel::where('code',$call2['P_AIRLINE'])->where('status','on')->whereNull('deleted_at')->first();
                                            if($airline){
                                                $data->airline_id = $airline->id;
                                            }
                                        }
                                    }
        
                                    if($data->name_check_change == null){
                                        $data->name = $call2['P_NAME'];
                                    }

                                    if($data->description_check_change == null){
                                        $data->description = $call2['P_HIGHLIGHT'];
                                    }
        
                                    if($data->image_check_change == 2){
                                        if ($call2['BANNER']) {
        
                                            $path = $call2['BANNER'];
                                            
                                            $response = Http::get($path);
        
                                            if ($response->successful()) {

                                                $contentLength = $response->header('Content-Length');

                                                if(!empty($contentLength) && intval($contentLength) > 0){

                                                    if ($data->image != null) {
                                                        Storage::disk('public')->delete($data->image);
                                                    }

                                                    $urlParts = parse_url($path);
                                                    parse_str($urlParts['query'], $queryParams);
                                                    $filePath = $queryParams['url'] ?? ''; // ดึง URL ของไฟล์ภาพ
                                                    $filename = basename($filePath);
            
                                                    // $filename = basename($path);
                                                    $lg = Image::make($path);
                                                    $ext = explode("/", $lg->mime());
                                                    $lg->resize(600, 600)->stream();
                                                    
                                                    $new = 'upload/tour/ttnapi/' . $filename;
                                                    
                                                    if (in_array($ext[1], $allow_img)) {
                                                        Storage::disk('public')->put($new, $lg);
                                                        $data->image = $new;
                                                    }

                                                }
        
                                            }
                
                                        }
                                    }
        
                                }
        
                                $data->api_id = $call2['P_ID'];
                                $data->code1 = $call2['P_CODE'];
                                $data->rating = $call2['P_HOTEL_STAR'];
        
                                // จัดการ wholesale_id ให้เป็น ttn_japan
                                $data->group_id = 3;
                                $data->wholesale_id = 35;
        
                                $allow_word = ['doc', 'docx'];
                                $allow_pdf = ['pdf'];
        
                                // if ($call2['PDF']) {
                                //     $url = $call2['PDF'];

                                //     $headers = get_headers($url, 1);
        
                                //     $response = Http::get($url);
        
                                //     if ($response->successful()) {
        
                                //         if($response->header('Content-Type')){
                                
                                //             $contentType = $response->header('Content-Type');
            
                                //             if(strpos($contentType, 'application/pdf') !== false){
        
                                //                 $filename = basename($url);
                                //                 $ext = explode(".", $filename);
                                //                 $new = 'upload/tour/pdf_file/ttnapi/' . $filename .'.pdf';
                                //                 $newFileSize = strlen($response->body());
        
                                //                 if(Storage::disk('public')->exists($data->pdf_file)){

                                //                     // เช็ค Date Modified
                                //                     if(isset($headers['Last-Modified'])){
                                //                         $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                //                         $oldModified = $data->date_mod_pdf;
                                //                         if($lastModified != $oldModified){
                                //                             Storage::disk('public')->delete($data->pdf_file);
                    
                                //                             Storage::disk('public')->put($new, $response->body());
                                //                             $img_pdf = ImagePDFModel::first();
                                //                             if($img_pdf->status == 'on'){
                                //                                 $this->save_pdf($new);
                                //                             }
                                //                             $data->pdf_file = $new;
                                //                             $data->date_mod_pdf = $lastModified;
                                //                         }
                                //                     }
                                //                 }else{

                                //                     if(isset($headers['Last-Modified'])){
                                //                         $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                //                         Storage::disk('public')->put($new, $response->body());
                                //                         $img_pdf = ImagePDFModel::first();
                                //                         if($img_pdf->status == 'on'){
                                //                             $this->save_pdf($new);
                                //                         }
                                //                         $data->pdf_file = $new;
                                //                         $data->date_mod_pdf = $lastModified;
                                //                     }
                                //                 }
                                //             }
                                //         }
                                //     }
                                // }

                                // PDF
                                $data->pdf_file = $call2['PDF'];
        
                                $data->data_type = 2; // 1 system , 2 api
                                $data->api_type = "ttn";
        
                                if($data->save()){

                                    $tour[] = $data->id;
                                    $tour_api_id[] = $data->api_id;

                                    $response = Http::withHeaders([
                                        "Content-Type" => "application/json; charset=UTF-8",
                                    ])
                                    ->get('https://online.ttnconnect.com/api/agency/program/period/'.$call2['P_ID']);
                                    
                                    if($response->successful()){

                                        $callback3 = $response->json();
                        
                                        if(count($callback3) > 0){
                        
                                            foreach($callback3 as $call3){
                                                
                                                $max = array();
                                                $cal1 = 0;
                                                $cal2 = 0;
                                                $cal3 = 0;
                                                $cal4 = 0;
                                                foreach($call3['Price'] as $pe){
                        
                                                    $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_api_id'=>$call3['P_ID'], 'api_type'=>'ttn'])->whereNull('deleted_at')->first();
                        
                                                    $price1 = $pe['P_ADULT_PRICE']; // ผู้ใหญ่พักคู่
                                                    $price2 = $pe['P_SINGLE_PRICE']; //ผู้ใหญ่พักเดี่ยว
                        
                                                    if($data3 == null){
                                                        $data3 = new TourPeriodModel;
                                                        
                                                        $data3->price1 = $price1;
                                                        $data3->price2 = $price2;
                                                    }else{
                                                        $data3->price1 = $price1;
                                                        $data3->price2 = $price2;
                                                    }
                        
                                                    $data3->tour_id = $data->id;
                                                    $data3->period_api_id = $call3['P_ID']; // เอามาจากอันหลัก
                                                    $data3->group_date = date('mY',strtotime($call3['P_DUE_START'])); // เอามาจากอันหลัก
                                                    $data3->start_date = $call3['P_DUE_START']; // เอามาจากอันหลัก
                                                    $data3->end_date = $call3['P_DUE_END']; // เอามาจากอันหลัก
                                                    $data3->day = $call2['P_DAY']; // เอามาจากอันหลัก
                                                    $data3->night = $call2['P_NIGHT']; // เอามาจากอันหลัก
                                                    $data3->group = $pe['P_VOLUME'];
                                                    $data3->count = $pe['P_AVAILABLE'];
                                                    $data3->status_display = "on";
                        
                                                    if($pe['P_AVAILABLE'] === "Open"){
                                                        $data3->status_period = 1;
                                                    }else if($pe['P_AVAILABLE'] === "ChangePrice"){
                                                        $data3->status_period = 3;
                                                    }
                                                    $data3->api_type = "ttn";
                        
                                                    if($data3->save()){
                                                        $period[] = $data3->id;
                                                        $period_api_id[] = $data3->period_api_id;
                                                    }
                                                    
                                                    $calmax = max($cal1, $cal2);
                                                    array_push($max, $calmax);
                                                }
                        
                                                // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                                $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'ttn'])->whereNull('deleted_at')->get();
                                                if($data4){
                        
                                                    $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                                        return $item->price1 - $item->special_price1;
                                                    })->first();
                        
                                                    if($data5){
                                                        $num_day = "";
                                                        if($data5->day && $data5->night){
                                                            $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                                        }
                                                        $price = $data5->price1;
                                                        $special_price = $data5->special_price1;
                        
                                                        if($special_price && $special_price > 0){
                                                            $net_price = $price - $special_price;
                                                        }else{
                                                            $net_price = $price;
                                                        }
                        
                                                        if($net_price && $net_price > 0){
                                                            if($net_price <= 10000 ){
                                                                $price_group = 1;
                                                            }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                                $price_group = 2;
                                                            }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                                $price_group = 3;
                                                            }
                                                            else if($net_price  > 30000 && $net_price <= 50000  ){
                                                                $price_group = 4;
                                                            }
                                                            else if($net_price  > 50000 && $net_price <= 80000  ){
                                                                $price_group = 5;
                                                            }else if($net_price  > 80000  ){
                                                                $price_group = 6;
                                                            }
                                                        }else{
                                                            $price_group = 0;
                                                        }
                                                        TourModel::where(['id'=>$data->id, 'api_type'=>'ttn'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                                    }
                                                }
                        
                                                $maxCheck = max($max);
                                                if($maxCheck > 0 && $maxCheck >= 30){
                                                    TourModel::where(['id'=>$data->id, 'api_type'=>'ttn'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                                }elseif($maxCheck > 0 && $maxCheck < 30){
                                                    TourModel::where(['id'=>$data->id, 'api_type'=>'ttn'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                                }else{
                                                    TourModel::where(['id'=>$data->id, 'api_type'=>'ttn'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                                }
                        
                                            }
                                        } // end foreach call3
                                    }
        
                                    // DB::commit();
                                }else{
                                    // DB::rollback();
                                }
                            } // end foreach call2
                            
                        }

                    } // end foreach call1 ประเทศ
    
                    // ลบข้อมูลทัวร์ และ Period
                    TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','ttn')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','ttn')->update(['deleted_at'=>null]);
                    TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','ttn')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','ttn')->update(['deleted_at'=>null]);
                    
                }

            }


        } catch (\Exception $e) {
            // DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'ttn_japan = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
        }
    }

    public function ttn_api_all(){ // api_type = ttn_all
        try {
            // DB::beginTransaction();
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
            ])
            ->get('https://www.ttnplus.co.th/api/program');

            if($response->successful()){

                $callback = $response->json();

                if(is_array($callback) && count($callback) > 0){
                    $tour = array();
                    $tour_api_id = array();
                    $period = array();
                    $period_api_id = array();
                    foreach($callback as $call){

                        try {
                            // DB::beginTransaction();
                            $data = TourModel::where(['api_id'=>$call['P_ID'], 'api_type'=>'ttn_all'])->whereNull('deleted_at')->first();

                            $allow_img = ['png', 'jpeg', 'jpg','webp'];

                            if($data == null){
                                $data = new TourModel;

                                $code_tour = IdGenerator::generate([
                                    'table' => 'tb_tour', 
                                    'field' => 'code', 
                                    'length' => 10, 
                                    'prefix' =>'NT'.date('ym'),
                                    'reset_on_prefix_change' => true 
                                ]);

                                $data->code = $code_tour;

                                if($call['P_LOCATION']){
                                    $country = CountryModel::where('country_name_en', 'like', '%'.$call['P_LOCATION'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                    $arr = array();
                                    if($country){
                                        $arr[] = "$country->id";
                                        $data->country_id = json_encode($arr);
                                    }else{
                                        $data->country_id = "[]";
                                    }
                                }

                                if($call['P_AIRLINE']){ // ได้ข้อมูลมาเป็น Austrian Airlines (OS) บางอันก็ไม่มี (โค้ด) เลยต้อง explode เอาค่าในวงเล็บ
                                    $parts = explode('(', $call['P_AIRLINE']);

                                    $code_airline = ""; 
                                    if(isset($parts[1])){
                                        $code_airline = trim($parts[1], ') ');
                                    }

                                    $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                    if($airline){
                                        $data->airline_id = $airline->id;
                                    }
                                }

                                if ($call['banner_url']) {
            
                                    $path = $call['banner_url'];

                                    $response = Http::get($path);

                                    if ($response->successful()) {
                                        $filename = basename($path);
                
                                        $lg = Image::make($response->body());
                                        // $lg = Image::make($path);
                                        $ext = explode("/", $lg->mime());
                                        $lg->resize(600, 600)->stream();
                                        
                                        $new = 'upload/tour/ttn_allapi/' . $filename;
                                        
                                        if (in_array($ext[1], $allow_img)) {
                                            Storage::disk('public')->put($new, $lg);
                                            $data->image = $new;
                                        }
                                    }
                                }

                                $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                                $data->name = $call['P_NAME'];
                                // $data->description = str_replace("\n","",$call['Highlight']); ไม่มี

                            }else{
                                if($data->country_check_change == null){
                                    if($call['P_LOCATION']){
                                        $country = CountryModel::where('country_name_en', 'like', '%'.$call['P_LOCATION'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                        $arr = array();
                                        if($country){
                                            $arr[] = "$country->id";
                                            $data->country_id = json_encode($arr);
                                        }else{
                                            $data->country_id = "[]";
                                        }
                                    }
                                }

                                if($data->airline_check_change == null){
                                    if($call['P_AIRLINE']){
                                        $parts = explode('(', $call['P_AIRLINE']);

                                        $code_airline = ""; 
                                        if(isset($parts[1])){
                                            $code_airline = trim($parts[1], ') ');
                                        }

                                        $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                        if($airline){
                                            $data->airline_id = $airline->id;
                                        }
                                    }
                                }

                                if($data->name_check_change == null){
                                    $data->name = $call['P_NAME'];
                                }

                                // if($data->description_check_change == null){
                                //     $data->description = str_replace("\n","",$call['Highlight']); ไม่มี
                                // }

                                if($data->image_check_change == 2){
                                    if ($call['banner_url']) {

                                        $path = $call['banner_url'];

                                        $response = Http::get($path);

                                        if ($response->successful()) {

                                            if ($data->image != null) {
                                                Storage::disk('public')->delete($data->image);
                                            }

                                            $filename = basename($path);
                    
                                            $lg = Image::make($response->body());
                                            // $lg = Image::make($path);
                                            $ext = explode("/", $lg->mime());
                                            $lg->resize(600, 600)->stream();
                                            
                                            $new = 'upload/tour/ttn_allapi/' . $filename;
                                            
                                            if (in_array($ext[1], $allow_img)) {
                                                Storage::disk('public')->put($new, $lg);
                                                $data->image = $new;
                                            }
                                        }
            
                                    }
                                }

                            }

                            $data->api_id = $call['P_ID'];
                            $data->code1 = $call['P_CODE'];
                            // $data->rating = $call['HotelStar']; ไม่มี

                            // จัดการ wholesale_id ให้เป็น ttn
                            $data->group_id = 3;
                            $data->wholesale_id = 10;

                            $allow_word = ['doc', 'docx'];
                            $allow_pdf = ['pdf'];

                            if ($call['pdf_url']) {
                                $url = $call['pdf_url'];
            
                                $headers = get_headers($url, 1);
            
                                $response = Http::get($url);
            
                                if ($response->successful()) {
            
                                    if($response->header('Content-Type')){
                            
                                        $contentType = $response->header('Content-Type');
            
                                        if(strpos($contentType, 'application/pdf') !== false){
            
                                            $filename = basename($url);
                                            $ext = explode(".", $filename);
                                            $new = 'upload/tour/pdf_file/ttn_allapi/' . $filename;
                                            $newFileSize = strlen($response->body());
            
                                            if(Storage::disk('public')->exists($data->pdf_file)){
            
                                                // เช็ค Date Modified
                                                if(isset($headers['Last-Modified'])){
                                                    $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                    $oldModified = $data->date_mod_pdf;
                                                    if($lastModified != $oldModified){
                                                        Storage::disk('public')->delete($data->pdf_file);
                
                                                        Storage::disk('public')->put($new, $response->body());
                                                        $img_pdf = ImagePDFModel::first();
                                                        $pdfversion = 0;
                                                        if($img_pdf->status == 'on'){
                                                            $filepdf = fopen(public_path($new),"r");
                                                            $line_first = fgets($filepdf);
                                                            preg_match_all('!\d+!', $line_first, $matches);
                                                            $pdfversion = implode('.', $matches[0]);

                                                            if($pdfversion > 1.6){
                                                                $this->save_pdf($new);
                                                            }
                                                            // $this->save_pdf($new);
                                                        }
                                                        $data->pdf_file = $new;
                                                        $data->date_mod_pdf = $lastModified;
                                                    }
                                                }
                                            }else{

                                                if(isset($headers['Last-Modified'])){
                                                    $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                    Storage::disk('public')->put($new, $response->body());
                                                    $img_pdf = ImagePDFModel::first();
                                                    $pdfversion = 0;
                                                    if($img_pdf->status == 'on'){
                                                        $filepdf = fopen(public_path($new),"r");
                                                        $line_first = fgets($filepdf);
                                                        preg_match_all('!\d+!', $line_first, $matches);
                                                        $pdfversion = implode('.', $matches[0]);

                                                        if($pdfversion > 1.6){
                                                            $this->save_pdf($new);
                                                        }
                                                        // $this->save_pdf($new);
                                                    }
                                                    $data->pdf_file = $new;
                                                    $data->date_mod_pdf = $lastModified;
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            $data->data_type = 2; // 1 system , 2 api
                            $data->api_type = "ttn_all";
                            
                            if($data->save()){

                                $tour[] = $data->id;
                                $tour_api_id[] = $data->api_id;
                                
                                if($call['period']){
                                    $max = array();
                                    $cal1 = 0;
                                    $cal2 = 0;
                                    $cal3 = 0;
                                    $cal4 = 0;
                                    foreach($call['period'] as $pe){

                                        $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_api_id'=>$pe['P_ID'], 'api_type'=>'ttn_all'])->whereNull('deleted_at')->first();

                                        $price_new = $pe['P_NEWPRICE']; // ราคาใหม่
                                        $price1 = $pe['P_ADULT']; // ผู้ใหญ่พักคู่
                                        $price2 = $pe['P_SINGLE']; //ผู้ใหญ่พักเดี่ยว

                                        if($data3 == null){
                                            $data3 = new TourPeriodModel;

                                            if($price1 > $price_new && $price_new > 0){
                                                $cal = $price1 - $price_new;
                                                if($cal > 0){
                                                    $data3->price1 = $price1;
                                                    if($cal < $price1){
                                                        $data3->special_price1 = $cal;
                                                    }else{
                                                        $data3->special_price1 = 0.00;
                                                    }
                                                    if (isset($price1) && $price1 != 0) {
                                                        $cal1 = ($cal / $price1) * 100;
                                                    } else {
                                                        $cal1 = 0;
                                                    }
                                                }
                                            }else{
                                                $data3->price1 = $price1;
                                                $cal1 = 0;
                                            }
                                            
                                            $data3->price2 = $price2;
                                        }else{
                                            if($price1 > $price_new && $price_new > 0){
                                                $cal = $price1 - $price_new;
                                                if($cal > 0){
                                                    $data3->price1 = $price1;
                                                    if($cal < $price1){
                                                        $data3->special_price1 = $cal;
                                                    }else{
                                                        $data3->special_price1 = 0.00;
                                                    }
                                                    if (isset($price1) && $price1 != 0) {
                                                        $cal1 = ($cal / $price1) * 100;
                                                    } else {
                                                        $cal1 = 0;
                                                    }
                                                }
                                            }else{
                                                $data3->price1 = $price1;
                                                $cal1 = 0;
                                            }

                                            // ราคาผู้ใหญ่ พักเดี่ยว ไม่ต้องคำนวณเปอเซ็นต์ส่วนลด
                                            // $data3->old_price2 = 0.00;
                                            // $data3->special_price2 = 0.00;
                                            $data3->price2 = $price2;
                                            $cal2 = 0;
                                        }

                                        $data3->tour_id = $data->id;
                                        $data3->period_api_id = $pe['P_ID'];
                                        $data3->group_date = date('mY',strtotime($pe['P_DUE_START']));
                                        $data3->start_date = $pe['P_DUE_START'];
                                        $data3->end_date = $pe['P_DUE_END'];
                                        $data3->day = $call['P_DAY']; // เอามาจากอันหลัก
                                        $data3->night = $call['P_NIGHT']; // เอามาจากอันหลัก
                                        $data3->group = $pe['P_VOLUME'];
                                        $data3->count = $pe['P_AVAILABLE']; // เส้น all ชื่อนี้
                                        // $data3->count = $pe['available']; // เส้น japan ชื่อนี้
                                        $data3->status_display = "on";

                                        if($pe['P_AVAILABLE'] > 0){
                                            $data3->status_period = 1;
                                        }else{
                                            $data3->status_period = 3;
                                        }
                                        $data3->api_type = "ttn_all";

                                        if($data3->save()){
                                            $period[] = $data3->id;
                                            $period_api_id[] = $data3->period_api_id;
                                        }
                                        
                                        $calmax = max($cal1, $cal2);
                                        // $calmax = max($cal1, $cal2, $cal3, $cal4);
                                        array_push($max, $calmax);
                                    }

                                    // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                    $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'ttn_all'])->whereNull('deleted_at')->get();
                                    if($data4){
                                        // $maxSpecialPrice = $data4->max('special_price1');

                                        $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                            return $item->price1 - $item->special_price1;
                                        })->first();
                
                                        // $data5 = $data4->where('special_price1', $maxSpecialPrice)->first();
                                        if($data5){
                                            $num_day = "";
                                            if($data5->day && $data5->night){
                                                $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                            }
                                            $price = $data5->price1;
                                            $special_price = $data5->special_price1;

                                            if($special_price && $special_price > 0){
                                                $net_price = $price - $special_price;
                                            }else{
                                                $net_price = $price;
                                            }

                                            if($net_price && $net_price > 0){
                                                if($net_price <= 10000 ){
                                                    $price_group = 1;
                                                }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                    $price_group = 2;
                                                }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                    $price_group = 3;
                                                }
                                                else if($net_price  > 30000 && $net_price <= 50000  ){
                                                    $price_group = 4;
                                                }
                                                else if($net_price  > 50000 && $net_price <= 80000  ){
                                                    $price_group = 5;
                                                }else if($net_price  > 80000  ){
                                                    $price_group = 6;
                                                }
                                            }else{
                                                $price_group = 0;
                                            }
                                            TourModel::where(['id'=>$data->id, 'api_type'=>'ttn_all'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                        }
                                    }

                                    $maxCheck = max($max);
                                    if($maxCheck > 0 && $maxCheck >= 30){
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'ttn_all'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                    }elseif($maxCheck > 0 && $maxCheck < 30){
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'ttn_all'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                    }else{
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'ttn_all'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                    }

                                }

                                // DB::commit();
                            }else{
                                // DB::rollback();
                            }
                        } catch (\Exception $e) {
                            // DB::rollback();
                            $error_tour_id = isset($data) ? $data->id : 'unknown';
                        
                            $error_log = 'ttn_all = '.$e->getMessage().' | tour_id = '.$error_tour_id;
                            $error_line = $e->getLine();
                            $type_log = 'backend';
                            $error_url = url()->current();
                            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
                            continue;
                        }
                        
                    } // end foreach
                    
                    // ลบข้อมูลทัวร์ และ Period
                    TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','ttn_all')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','ttn_all')->update(['deleted_at'=>null]);
                    TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','ttn_all')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','ttn_all')->update(['deleted_at'=>null]);
                    
                }
            }

        } catch (\Exception $e) {
            // DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'ttn_all2 = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
        }
    }

    public function itravel_api(){
        try {
            // DB::beginTransaction();
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "itravels-secret" => env('ITRAVEL_API_KEY'),
            ])
            ->get('https://itravels.center/api/program');

            if($response->successful()){

                $callback1 = $response->json();

                if(count($callback1['data']) > 0){
                    $tour = array();
                    // $tour_api_id = array();
                    $tour_code = array();
                    $period = array();
                    $period_api_id = array();
                    foreach($callback1['data'] as $call1){
                            
                        $data = TourModel::where(['code1'=>$call1['code'], 'api_type'=>'itravel'])->whereNull('deleted_at')->first();
                        // $data = TourModel::where(['api_id'=>$call1['programtour_id'], 'api_type'=>'itravel'])->whereNull('deleted_at')->first();
    
                        $allow_img = ['png', 'jpeg', 'jpg','webp'];
    
                        if($data == null){
                            $data = new TourModel;
    
                            $code_tour = IdGenerator::generate([
                                'table' => 'tb_tour', 
                                'field' => 'code', 
                                'length' => 10, 
                                'prefix' =>'NT'.date('ym'),
                                'reset_on_prefix_change' => true 
                            ]);
    
                            $data->code = $code_tour;

                            if($call1['departure_by'] && $call1['departure_by'] !== "null"){ // ได้ข้อมูลมาเป็น VZ822 บางอันก็ไม่มี เว้นวรรคก่อนVZ822 เลยต้องเอา 1-2 ตำแหน่งแรก
                                $code_airline = substr($call1['departure_by'], 0, 2);

                                $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                if($airline){
                                    $data->airline_id = $airline->id;
                                }
                            }
    
                            if ($call1['banner_square']) {
        
                                $path = $call1['banner_square'];
    
                                $response = Http::get($path);
    
                                if ($response->successful()) {
    
                                    $filename = basename($path);
            
                                    $lg = Image::make($path);
                                    $ext = explode("/", $lg->mime());
                                    $lg->resize(600, 600)->stream();
                                    
                                    $new = 'upload/tour/itravelapi/' . $filename;
                                    
                                    if (in_array($ext[1], $allow_img)) {
                                        Storage::disk('public')->put($new, $lg);
                                        $data->image = $new;
                                    }
                                }
                            }
    
                            $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                            $data->name = $call1['name'];
    
                        }else{
                            if($data->name_check_change == null){
                                $data->name = $call1['name'];
                            }

                            if($data->airline_check_change == null){
                                if($call1['departure_by'] && $call1['departure_by'] !== "null"){
                                    $code_airline = substr($call1['departure_by'], 0, 2);
    
                                    $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                    if($airline){
                                        $data->airline_id = $airline->id;
                                    }
                                }
                            }
    
                            if($data->image_check_change == 2){
                                if ($call1['banner_square']) {
    
                                    $path = $call1['banner_square'];
    
                                    $response = Http::get($path);
    
                                    if ($response->successful()) {
    
                                        if ($data->image != null) {
                                            Storage::disk('public')->delete($data->image);
                                        }
    
                                        $filename = basename($path);
                
                                        $lg = Image::make($path);
                                        $ext = explode("/", $lg->mime());
                                        $lg->resize(600, 600)->stream();
                                        
                                        $new = 'upload/tour/itravelapi/' . $filename;
                                        
                                        if (in_array($ext[1], $allow_img)) {
                                            Storage::disk('public')->put($new, $lg);
                                            $data->image = $new;
                                        }
                                    }
        
                                }
                            }
    
                        }
    
                        // $data->api_id = $call1['programtour_id'];
                        $data->code1 = $call1['code'];
    
                        // จัดการ wholesale_id ให้เป็น itravel
                        $data->group_id = 3;
                        $data->wholesale_id = 5;
    
                        $allow_word = ['doc', 'docx'];
                        $allow_pdf = ['pdf'];
    
                        if ($call1['program_detail_file_pdf']) {
                            $url = $call1['program_detail_file_pdf'];
    
                            $headers = get_headers($url, 1);
    
                            $response = Http::get($url);
    
                            if ($response->successful()) {
    
                                if($response->header('Content-Type')){
                                
                                    $contentType = $response->header('Content-Type');
    
                                    if(strpos($contentType, 'application/pdf') !== false){
    
                                        $filename = basename($url);
                                        $ext = explode(".", $filename);
                                        $new = 'upload/tour/pdf_file/itravelapi/' . $filename;
                                        $newFileSize = strlen($response->body());
            
                                        if(Storage::disk('public')->exists($data->pdf_file)){

                                            // เช็ค Date Modified
                                            // $headers['Last-Modified']
                                            if(isset($headers['last-modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['last-modified']));
                                                $oldModified = $data->date_mod_pdf;
                                                if($lastModified != $oldModified){
                                                    Storage::disk('public')->delete($data->pdf_file);
            
                                                    Storage::disk('public')->put($new, $response->body());
                                                    $img_pdf = ImagePDFModel::first();
                                                    $pdfversion = 0;
                                                    if($img_pdf->status == 'on'){
                                                        $filepdf = fopen(public_path($new),"r");
                                                        $line_first = fgets($filepdf);
                                                        preg_match_all('!\d+!', $line_first, $matches);
                                                        $pdfversion = implode('.', $matches[0]);

                                                        if($pdfversion > 1.6){
                                                            $this->save_pdf($new);
                                                        }
                                                        // $this->save_pdf($new);
                                                    }
                                                    $data->pdf_file = $new;
                                                    $data->date_mod_pdf = $lastModified;
                                                }
                                            }
            
                                        }else{
                                            // เช็ค Date Modified
                                            if(isset($headers['last-modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['last-modified']));
                                                Storage::disk('public')->put($new, $response->body());
                                                $img_pdf = ImagePDFModel::first();
                                                $pdfversion = 0;
                                                if($img_pdf->status == 'on'){
                                                    $filepdf = fopen(public_path($new),"r");
                                                    $line_first = fgets($filepdf);
                                                    preg_match_all('!\d+!', $line_first, $matches);
                                                    $pdfversion = implode('.', $matches[0]);

                                                    if($pdfversion > 1.6){
                                                        $this->save_pdf($new);
                                                    }
                                                    // $this->save_pdf($new);
                                                }
                                                $data->pdf_file = $new;
                                                $data->date_mod_pdf = $lastModified;
                                            }
                                        }
    
                                    }
                                }
                            }
                        }
    
                        $data->data_type = 2; // 1 system , 2 api
                        $data->api_type = "itravel";
    
                        if($data->save()){
    
                            $tour[] = $data->id;
                            $tour_code[] = $data->code1;
                            // $tour_api_id[] = $data->api_id;

                            $response = Http::withHeaders([
                                "Content-Type" => "application/json",
                                "itravels-secret" => env('ITRAVEL_API_KEY'),
                            ])
                            ->get('https://itravels.center/api/program/'.$call1['code']);
                            
                            if($response->successful()){
                                $callback2 = $response->json();
                                if(count($callback2) > 0){
                                    $max = array();
                                    $cal1 = 0;
                                    $cal2 = 0;
                                    $cal3 = 0;
                                    $cal4 = 0;
                
                                    foreach($callback2['data'] as $call2){
                                        
                                        // เดิม 26-8-24
                                        // $max = array();
                                        // $cal1 = 0;
                                        // $cal2 = 0;
                                        // $cal3 = 0;
                                        // $cal4 = 0;

                                        $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_api_id'=>$call2['id'], 'api_type'=>'itravel'])->whereNull('deleted_at')->first();

                                        $price1 = isset($call2['price']['adult'][0]['price']) ? $call2['price']['adult'][0]['price'] : 0; // ผู้ใหญ่พักคู่
                                        $price2 = isset($call2['price']['single_person'][0]['price']) ? $call2['price']['single_person'][0]['price'] : 0; //ผู้ใหญ่พักเดี่ยว
                                        $price3 = 0; //เด็กมีเตียง
                                        $price4 = isset($call2['price']['child'][0]['price']) ? $call2['price']['child'][0]['price'] : 0; //เด็กไม่มีเตียง
                                        // $price1 = $call2['price']['adult'][0]['price']; // ผู้ใหญ่พักคู่
                                        // $price2 = $call2['price']['single_person'][0]['price']; //ผู้ใหญ่พักเดี่ยว
                                        // $price3 = 0; //เด็กมีเตียง
                                        // $price4 = $call2['price']['child'][0]['price']; //เด็กไม่มีเตียง

                                        if($data3 == null){
                                            $data3 = new TourPeriodModel;
                                            
                                            $data3->price1 = $price1;
                                            $data3->price2 = $price2;
                                            $data3->price3 = $price3;
                                            $data3->price4 = $price4;
                                        }else{
                                            $data3->price1 = $price1;
                                            $data3->price2 = $price2;
                                            $data3->price3 = $price3;
                                            $data3->price4 = $price4;
                                        }

                                        $data3->tour_id = $data->id;
                                        $data3->period_api_id = $call2['id'];
                                        $data3->group_date = date('mY',strtotime($call2['date_start']));
                                        $data3->start_date = $call2['date_start'];
                                        $data3->end_date = $call2['date_end'];
                                        $data3->day = $call1['day']; //เอามาจากอันหลัก
                                        $data3->night = $call1['night']; //เอามาจากอันหลัก
                                        $data3->group = $call2['seat'];
                                        $data3->count = $call2['available_seat'];
                                        $data3->status_display = "on";

                                        if($call2['status'] == "AVAILABLE"){
                                            $data3->status_period = 1;
                                        }else if($call2['status'] == "WAITLIST"){
                                            $data3->status_period = 2;
                                        }else if($call2['status'] == "FULL" || $call2['status'] == "NO_CANCEL"){
                                            $data3->status_period = 3;
                                        }
                                        $data3->api_type = "itravel";
            
                                        if($data3->save()){
                                            $period[] = $data3->id;
                                            $period_api_id[] = $data3->period_api_id;
                                        }
                                        
                                        $calmax = max($cal1, $cal2, $cal3);
                                        array_push($max, $calmax);
                
                                    } // end foreach call2

                                    // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                    $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'itravel'])->whereNull('deleted_at')->get();
                                    if($data4){
            
                                        $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                            return $item->price1 - $item->special_price1;
                                        })->first();
            
                                        if($data5){
                                            $num_day = "";
                                            if($data5->day && $data5->night){
                                                $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                            }
                                            $price = $data5->price1;
                                            $special_price = $data5->special_price1;
            
                                            if($special_price && $special_price > 0){
                                                $net_price = $price - $special_price;
                                            }else{
                                                $net_price = $price;
                                            }
            
                                            if($net_price && $net_price > 0){
                                                if($net_price <= 10000 ){
                                                    $price_group = 1;
                                                }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                    $price_group = 2;
                                                }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                    $price_group = 3;
                                                }
                                                else if($net_price  > 30000 && $net_price <= 50000  ){
                                                    $price_group = 4;
                                                }
                                                else if($net_price  > 50000 && $net_price <= 80000  ){
                                                    $price_group = 5;
                                                }else if($net_price  > 80000  ){
                                                    $price_group = 6;
                                                }
                                            }else{
                                                $price_group = 0;
                                            }
                                            TourModel::where(['id'=>$data->id, 'api_type'=>'itravel'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                        }
                                    }
            
                                    $maxCheck = max($max);
                                    if($maxCheck > 0 && $maxCheck >= 30){ 
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'itravel'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                    }elseif($maxCheck > 0 && $maxCheck < 30){
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'itravel'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                    }else{
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'itravel'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                    }
                                }
                            }
    
                            // DB::commit();
                        }else{
                            // DB::rollback();
                        }
                        
                    } // end foreach
                    
                    // ลบข้อมูลทัวร์ และ Period
                    TourModel::whereNotIn('id',$tour)->whereNotIn('code1',$tour_code)->where('api_type','itravel')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourModel::whereIn('id',$tour)->whereIn('code1',$tour_code)->where('api_type','itravel')->update(['deleted_at'=>null]);
                    TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','itravel')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','itravel')->update(['deleted_at'=>null]);
                    
                }
            }

        } catch (\Exception $e) {
            // DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'itravel = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
        }
    }

    public function superbholiday_api(){ // ลูกค้าแจ้งมา 17-2-25
        try {
            // DB::beginTransaction();
            // $tourIds = [21,23,25,24,18,2,3,17,1,19];
            $tourIds = [21,29,28,23,25,24,18,2,3,17,1,19]; // เพิ่ม 29,28 19-5-25

            if($tourIds){
                $tour = array();
                $tour_api_id = array();
                $period = array();
                $period_code = array();
                // $period_api_id = array();

                foreach($tourIds as $Ids){

                    $response = Http::withHeaders([
                        "Content-Type" => "application/json",
                    ])
                    ->get('https://superbholidayz.com/superb/apiweb.php?id=' . $Ids);
        
                    if($response->successful()){
        
                        $callback1 = $response->json();
        
                        if(count($callback1) > 0){

                            foreach($callback1 as $call1){
                                    
                                $data = TourModel::where(['api_id'=>$call1['mainid'], 'api_type'=>'superbholiday'])->whereNull('deleted_at')->first();
            
                                $allow_img = ['png', 'jpeg', 'jpg','webp'];
            
                                if($data == null){
                                    $data = new TourModel;
            
                                    $code_tour = IdGenerator::generate([
                                        'table' => 'tb_tour', 
                                        'field' => 'code', 
                                        'length' => 10, 
                                        'prefix' =>'NT'.date('ym'),
                                        'reset_on_prefix_change' => true 
                                    ]);
            
                                    $data->code = $code_tour;
        
                                    if($call1['Country']){
                                        $country = CountryModel::where('country_name_th', 'like', '%'.$call1['Country'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                        $arr = array();
                                        if($country){
                                            $arr[] = "$country->id";
                                            $data->country_id = json_encode($arr);
                                        }else{
                                            $data->country_id = "[]";
                                        }
                                    }
        
                                    if($call1['aey']){
                                        $parts = explode('(', $call1['aey']);
        
                                        $code_airline = ""; 
                                        if(isset($parts[1])){
                                            $code_airline = trim($parts[1], ') ');
                                        }
    
                                        $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                        if($airline){
                                            $data->airline_id = $airline->id;
                                        }

                                        // ปรับใหม่ 25-3-25
                                        // $airline = TravelTypeModel::where('travel_name',$call1['Airline'])->where('status','on')->whereNull('deleted_at')->first();
                                        // if($airline){
                                        //     $data->airline_id = $airline->id;
                                        // }
                                    }
            
                                    if ($call1['banner']) {
                
                                        $path = $call1['banner'];
            
                                        $response = Http::get($path);
            
                                        if ($response->successful()) {
            
                                            $filename = basename($path);
                    
                                            $lg = Image::make($path);
                                            $ext = explode("/", $lg->mime());
                                            $lg->resize(600, 600)->stream();
                                            
                                            $new = 'upload/tour/superbholidayapi/' . $filename;
                                            
                                            if (in_array($ext[1], $allow_img)) {
                                                Storage::disk('public')->put($new, $lg);
                                                $data->image = $new;
                                            }
                                        }
                                    }
            
                                    $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                                    $data->name = $call1['title'];
            
                                }else{
                                    if($data->name_check_change == null){
                                        $data->name = $call1['title'];
                                    }
        
                                    if($data->country_check_change == null){
                                        if($call1['Country']){
                                            $country = CountryModel::where('country_name_th', 'like', '%'.$call1['Country'].'%')->where('status','on')->whereNull('deleted_at')->first();
                                            $arr = array();
                                            if($country){
                                                $arr[] = "$country->id";
                                                $data->country_id = json_encode($arr);
                                            }else{
                                                $data->country_id = "[]";
                                            }
                                        }
                                    }
        
                                    if($data->airline_check_change == null){
                                        if($call1['aey']){
                                            $parts = explode('(', $call1['aey']);
        
                                            $code_airline = ""; 
                                            if(isset($parts[1])){
                                                $code_airline = trim($parts[1], ') ');
                                            }
        
                                            $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                            if($airline){
                                                $data->airline_id = $airline->id;
                                            }
                                        }

                                        // ปรับใหม่ 25-3-25
                                        // if($call1['Airline']){
                                        //     $airline = TravelTypeModel::where('travel_name',$call1['Airline'])->where('status','on')->whereNull('deleted_at')->first();
                                        //     if($airline){
                                        //         $data->airline_id = $airline->id;
                                        //     }
                                        // }
                                    }
            
                                    if($data->image_check_change == 2){
                                        if ($call1['banner']) {
                                            $path = $call1['banner'];
                                            $filename = basename($path);
                                            $new = 'upload/tour/superbholidayapi/' . $filename;
                                        
                                            // เช็คว่ารูปใหม่หรือเก่า ถ้าใหม่ให้บันทึก
                                            if ($data->image != $new) {
                                                $response = Http::get($path);
                                        
                                                if ($response->successful()) {

                                                    if (!empty($data->image)) {
                                                        Storage::disk('public')->delete($data->image);
                                                    }
                                        
                                                    $lg = Image::make($path);
                                                    $ext = explode("/", $lg->mime());
                                                    $lg->resize(600, 600)->stream();
                                        
                                                    if (in_array($ext[1], $allow_img)) {
                                                        Storage::disk('public')->put($new, $lg);
                                                        $data->image = $new;
                                                    }
                                                }
                                            }
                                        }
                                        
                                        // ปิด 25-3-25
                                        // if ($call1['banner']) {
            
                                        //     $path = $call1['banner'];
            
                                        //     $response = Http::get($path);
            
                                        //     if ($response->successful()) {

                                        //         $filename = basename($path);
            
                                        //         if ($data->image != null) {
                                        //             Storage::disk('public')->delete($data->image);
                                        //         }
                    
                                        //         $lg = Image::make($path);
                                        //         $ext = explode("/", $lg->mime());
                                        //         $lg->resize(600, 600)->stream();
                                                
                                        //         $new = 'upload/tour/superbholidayapi/' . $filename;
                                                
                                        //         if (in_array($ext[1], $allow_img)) {
                                        //             Storage::disk('public')->put($new, $lg);
                                        //             $data->image = $new;
                                        //         }
                                        //     }
                
                                        // }
                                    }
            
                                }
            
                                $data->api_id = $call1['mainid'];
                                $data->code1 = $call1['maincode'];
            
                                // จัดการ wholesale_id ให้เป็น superbholiday
                                $data->group_id = 3;
                                $data->wholesale_id = 22;
            
                                if ($call1['pdf']) {
                                    $url = $call1['pdf'];
        
                                    $headers = get_headers($url, 1); // ต้องใช้ $response->header('Last-Modified')
        
                                    $response = Http::get($url);
        
                                    if ($response->successful()) {
                                        
                                        if($response->header('Content-Type')){
                                
                                            $contentType = $response->header('Content-Type');
            
                                            if(strpos($contentType, 'application/pdf') !== false){
        
                                                $filename = basename($url);
                                                $new = 'upload/tour/pdf_file/superbholidayapi/' . $filename;
                                                $newFileSize = strlen($response->body());
        
                                                if(Storage::disk('public')->exists($data->pdf_file)){
                                                    // $newFileSize = strlen($response->body());
                                                    // $oldFileSize = Storage::disk('public')->size($data->pdf_file);
        
                                                    // $oldFileSize = $data->pdf_file_size;
                                                    // if($newFileSize != $oldFileSize){
                                                    //     Storage::disk('public')->delete($data->pdf_file);
        
                                                    //     Storage::disk('public')->put($new, $response->body());
                                                    //     $img_pdf = ImagePDFModel::first();
                                                    //     if($img_pdf->status == 'on'){
                                                    //         $this->save_pdf($new);
                                                    //     }
                                                    //     $data->pdf_file = $new;
                                                    //     $data->pdf_file_size = $newFileSize;
                                                    // }
        
                                                    // Superbholiday ไม่มี Last-Modified มีแล้ว 19-2-25
                                                    // เช็ค Date Modified
                                                    if($response->header('Last-Modified')){
                                                    // if(isset($headers['Last-Modified'])){
                                                        $lastModified = date("Y-m-d H:i:s",strtotime($response->header('Last-Modified')));
                                                        $oldModified = $data->date_mod_pdf;
                                                        if($lastModified != $oldModified){
                                                            Storage::disk('public')->delete($data->pdf_file);
                    
                                                            Storage::disk('public')->put($new, $response->body());
                                                            $img_pdf = ImagePDFModel::first();
                                                            $pdfversion = 0;
                                                            if($img_pdf->status == 'on'){
                                                                $filepdf = fopen(public_path($new),"r");
                                                                $line_first = fgets($filepdf);
                                                                preg_match_all('!\d+!', $line_first, $matches);
                                                                $pdfversion = implode('.', $matches[0]);
        
                                                                if($pdfversion > 1.6){
                                                                    $this->save_pdf($new);
                                                                }
                                                                // $this->save_pdf($new);
                                                            }
                                                            $data->pdf_file = $new;
                                                            $data->date_mod_pdf = $lastModified;
                                                        }
                                                    }
                                                }else{
                                                    // Superbholiday ไม่มี Last-Modified มีแล้ว 19-5-25
                                                    if($response->header('Last-Modified')){
                                                    // if(isset($headers['Last-Modified'])){
                                                        $lastModified = date("Y-m-d H:i:s",strtotime($response->header('Last-Modified')));
                                                        $oldModified = $data->date_mod_pdf;
                                                        if($lastModified != $oldModified){
                                                            Storage::disk('public')->delete($data->pdf_file);
                    
                                                            Storage::disk('public')->put($new, $response->body());
                                                            $img_pdf = ImagePDFModel::first();
                                                            $pdfversion = 0;
                                                            if($img_pdf->status == 'on'){
                                                                $filepdf = fopen(public_path($new),"r");
                                                                $line_first = fgets($filepdf);
                                                                preg_match_all('!\d+!', $line_first, $matches);
                                                                $pdfversion = implode('.', $matches[0]);

                                                                if($pdfversion > 1.6){
                                                                    $this->save_pdf($new);
                                                                }
                                                                // $this->save_pdf($new);
                                                            }
                                                            $data->pdf_file = $new;
                                                            $data->date_mod_pdf = $lastModified;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
            
                                $data->data_type = 2; // 1 system , 2 api
                                $data->api_type = "superbholiday";
            
                                if($data->save()){

                                    if (!in_array($data->id, $tour)) {
                                        $tour[] = $data->id;
                                    }
                                    if (!in_array($data->api_id, $tour_api_id)) {
                                        $tour_api_id[] = $data->api_id;
                                    }
                                        
                                    // ไม่มีการหาส่วนต่างปรับ Promotion
                                    // $max = array();
                                    // $cal1 = 0;
                                    // $cal2 = 0;
                                    // $cal3 = 0;
                                    // $cal4 = 0;
        
                                    $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_code'=>$call1['pid'], 'api_type'=>'superbholiday'])->whereNull('deleted_at')->first();
        
                                    $price1 = isset($call1['Adult']) ? $call1['Adult'] : 0; // ผู้ใหญ่พักคู่
                                    $price2 = isset($call1['Single']) ? $call1['Single'] : 0; //ผู้ใหญ่พักเดี่ยว
                                    $price3 = isset($call1['Chd+B']) ? $call1['Chd+B'] : 0; //เด็กมีเตียง
                                    $price4 = isset($call1['ChdNB']) ? $call1['ChdNB'] : 0; //เด็กมีเตียง
        
                                    if($data3 == null){
                                        $data3 = new TourPeriodModel;
                                    }
        
                                    $data3->price1 = $price1;
                                    $data3->price2 = $price2;
                                    $data3->price3 = $price3;
                                    $data3->price4 = $price4;
        
                                    // if($call1['Date'] && $call1['ENDDate']){
                                    //     $day = \Carbon\Carbon::parse($call1['Date'])->diffInDays(\Carbon\Carbon::parse($call1['ENDDate'])) + 1;
                                    //     $night = $day-1;
                                    // }else{
                                    //     $day = 0;
                                    //     $night = 0;
                                    // }
        
                                    $data3->tour_id = $data->id;
                                    $data3->period_code = $call1['pid'];
                                    // $data3->period_api_id = $call1['id'];
                                    $data3->group_date = date('mY',strtotime($call1['Date']));
                                    $data3->start_date = $call1['Date'];
                                    $data3->end_date = $call1['ENDDate'];
                                    $data3->day = $call1['day'];
                                    $data3->night = $call1['night'];
                                    // $data3->day = $day;
                                    // $data3->night = $night;
                                    $data3->group = $call1['Size'];
                                    $data3->count = $call1['AVBL'];
                                    $data3->status_display = "on";
        
                                    // ไม่มีสถานะส่งมา
                                    if($call1['AVBL'] > 0){
                                        $data3->status_period = 1;
                                    }else{
                                        $data3->status_period = 3;
                                    }
        
                                    $data3->api_type = "superbholiday";
        
                                    if($data3->save()){
                                        $period[] = $data3->id;
                                        $period_code[] = $data3->period_code;
                                        // $period_api_id[] = $data3->period_api_id;
                                    }
                                    
                                    // $calmax = max($cal1, $cal2, $cal3, $cal4);
                                    // array_push($max, $calmax);
            
                                    // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                    $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'superbholiday'])->whereNull('deleted_at')->get();
                                    if($data4){
            
                                        $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                            return $item->price1 - $item->special_price1;
                                        })->first();
            
                                        if($data5){
                                            $num_day = "";
                                            if($data5->day && $data5->night){
                                                $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                            }
                                            $price = $data5->price1;
                                            $special_price = $data5->special_price1;
            
                                            if($special_price && $special_price > 0){
                                                $net_price = $price - $special_price;
                                            }else{
                                                $net_price = $price;
                                            }
            
                                            if($net_price && $net_price > 0){
                                                if($net_price <= 10000 ){
                                                    $price_group = 1;
                                                }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                    $price_group = 2;
                                                }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                    $price_group = 3;
                                                }
                                                else if($net_price  > 30000 && $net_price <= 50000  ){
                                                    $price_group = 4;
                                                }
                                                else if($net_price  > 50000 && $net_price <= 80000  ){
                                                    $price_group = 5;
                                                }else if($net_price  > 80000  ){
                                                    $price_group = 6;
                                                }
                                            }else{
                                                $price_group = 0;
                                            }
                                            TourModel::where(['id'=>$data->id, 'api_type'=>'superbholiday'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                        }
                                    }
            
                                    // $maxCheck = max($max);
                                    // if($maxCheck > 0 && $maxCheck >= 30){
                                    //     TourModel::where(['id'=>$data->id, 'api_type'=>'superbholiday'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                    // }elseif($maxCheck > 0 && $maxCheck < 30){
                                    //     TourModel::where(['id'=>$data->id, 'api_type'=>'superbholiday'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                    // }else{
                                    //     TourModel::where(['id'=>$data->id, 'api_type'=>'superbholiday'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                    // }
            
                                    // DB::commit();
                                }
                                
                            } // end foreach

                            // ลบข้อมูลทัวร์ และ Period เดิม 20-5-25
                            // if (!empty($tour) && !empty($tour_api_id)) {
                            //     TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','superbholiday')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                            //     TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','superbholiday')->update(['deleted_at'=>null]);
                            // }

                            // if (!empty($period) && !empty($period_code)) {
                            //     TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_code',$period_code)->where('api_type','superbholiday')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                            //     TourPeriodModel::whereIn('id',$period)->whereIn('period_code',$period_code)->where('api_type','superbholiday')->update(['deleted_at'=>null]);
                            // }
                            
                        }
                    }
                }

                if (!empty($tour) && !empty($tour_api_id)) {
                    TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','superbholiday')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','superbholiday')->update(['deleted_at'=>null]);
                }

                if (!empty($period) && !empty($period_code)) {
                    TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_code',$period_code)->where('api_type','superbholiday')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourPeriodModel::whereIn('id',$period)->whereIn('period_code',$period_code)->where('api_type','superbholiday')->update(['deleted_at'=>null]);
                }
            }

        } catch (\Exception $e) {
            // DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'superbholiday = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
        }
    }

    public function tourfactory_api(){ // ลูกค้าแจ้งมา 28-5-25
        try {
            // DB::beginTransaction();
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "Accept" => "application/json",
                // "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36"
            ])
            ->get('https://api.tourfactory.co.th/v1/programtours');

            if($response->successful()){

                $callback = $response->json();
                
                if(count($callback) > 0){
                    $tour = array();
                    $tour_api_id = array();
                    $period = array();
                    $period_api_id = array();
                    foreach($callback as $call){

                        $data = TourModel::where(['api_id'=>$call['id'], 'api_type'=>'tourfactory'])->whereNull('deleted_at')->first();
    
                        $allow_img = ['png', 'jpeg', 'jpg','webp'];
    
                        if($data == null){
                            $data = new TourModel;
    
                            $code_tour = IdGenerator::generate([
                                'table' => 'tb_tour', 
                                'field' => 'code', 
                                'length' => 10, 
                                'prefix' =>'NT'.date('ym'),
                                'reset_on_prefix_change' => true 
                            ]);
    
                            $data->code = $code_tour;

                            // if(isset($call['countries'][0]['name'])){
                            //     $country = CountryModel::where('country_name_th', 'like', '%'.$call['countries'][0]['name'].'%')->where('status','on')->whereNull('deleted_at')->first();
                            //     $arr = array();
                            //     if($country){
                            //         $arr[] = "$country->id";
                            //         $data->country_id = json_encode($arr);
                            //     }else{
                            //         $data->country_id = "[]";
                            //     }
                            // }

                            $arr = [];
                            if (isset($call['countries']) && is_array($call['countries'])) {
                                foreach ($call['countries'] as $countryData) {
                                    if (isset($countryData['code'])) {
                                        $country = CountryModel::where('iso2', $countryData['code'])->where('status', 'on')->whereNull('deleted_at')->first();
                                        // $country = CountryModel::where('country_name_th', 'like', '%' . $countryData['name'] . '%')->where('status', 'on')->whereNull('deleted_at')->first();
                                        if ($country) {
                                            $arr[] = (string) $country->id;
                                        }
                                    }
                                }
                            }
                            $data->country_id = json_encode($arr);

                            if($call['vehicle']){

                                if(preg_match('/\((.*?)\)/', $call['vehicle'], $matches)){
                                    $code_airline = $matches[1];
                                }else{
                                    $code_airline = null;
                                }

                                if($code_airline){
                                    $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                    if($airline){
                                        $data->airline_id = $airline->id;
                                    }
                                }
                                
                            }
    
                            // ได้มาเป็น 410 ตลอด บันทึกไม่ได้ ต้องใช้อีกแบบ
                            if ($call['banner']) {
        
                                $path = $call['banner'];

                                $response = Http::withHeaders([
                                    'User-Agent' => 'Mozilla/5.0',
                                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                                    'Referer' => 'https://booking.tourfactory.co.th/',
                                ])->get($path);
    
                                // $response = Http::get($path);
    
                                if ($response->successful()) {
    
                                    $filename = basename($path);
            
                                    $lg = Image::make($path);
                                    $ext = explode("/", $lg->mime());
                                    $lg->resize(600, 600)->stream();
                                    
                                    $new = 'upload/tour/tourfactoryapi/' . $filename;
                                    
                                    if (in_array($ext[1], $allow_img)) {
                                        Storage::disk('public')->put($new, $lg);
                                        $data->image = $new;
                                    }
                                }
                            }
    
                            $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                            $data->name = $call['name'];
                            $data->description = str_replace("\n","",$call['highlight']);
    
                        }else{
                            if($data->country_check_change == null){
                                $arr = [];
                                if (isset($call['countries']) && is_array($call['countries'])) {
                                    foreach ($call['countries'] as $countryData) {
                                        if (isset($countryData['code'])) {
                                            $country = CountryModel::where('iso2', $countryData['code'])->where('status', 'on')->whereNull('deleted_at')->first();
                                            // $country = CountryModel::where('country_name_th', 'like', '%' . $countryData['name'] . '%')->where('status', 'on')->whereNull('deleted_at')->first();
                                            if ($country) {
                                                $arr[] = (string) $country->id;
                                            }
                                        }
                                    }
                                }
                                $data->country_id = json_encode($arr);
                            }
    
                            if($data->airline_check_change == null){
                                if($call['vehicle']){

                                    if(preg_match('/\((.*?)\)/', $call['vehicle'], $matches)){
                                        $code_airline = $matches[1];
                                    }else{
                                        $code_airline = null;
                                    }

                                    if($code_airline){
                                        $airline = TravelTypeModel::where('code',$code_airline)->where('status','on')->whereNull('deleted_at')->first();
                                        if($airline){
                                            $data->airline_id = $airline->id;
                                        }
                                    }
                                    
                                }
                            }
    
                            if($data->name_check_change == null){
                                $data->name = $call['name'];
                            }
    
                            if($data->description_check_change == null){
                                $data->description = str_replace("\n","",$call['highlight']);
                            }
    
                            // ได้มาเป็น 410 ตลอด บันทึกไม่ได้ ต้องใช้อีกแบบ
                            if($data->image_check_change == 2){
                                if ($call['banner']) {
    
                                    $path = $call['banner'];

                                    $response = Http::withHeaders([
                                        'User-Agent' => 'Mozilla/5.0',
                                        'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                                        'Referer' => 'https://booking.tourfactory.co.th/',
                                    ])->get($path);
    
                                    // $response = Http::get($path);
    
                                    if ($response->successful()) {
    
                                        if ($data->image != null) {
                                            Storage::disk('public')->delete($data->image);
                                        }
    
                                        $filename = basename($path);
                
                                        $lg = Image::make($path);
                                        $ext = explode("/", $lg->mime());
                                        $lg->resize(600, 600)->stream();
                                        
                                        $new = 'upload/tour/tourfactoryapi/' . $filename;
                                        
                                        if (in_array($ext[1], $allow_img)) {
                                            Storage::disk('public')->put($new, $lg);
                                            $data->image = $new;
                                        }
                                    }
        
                                }
                            }
    
                        }
    
                        $data->api_id = $call['id'];
                        $data->code1 = $call['code'];
                        // $data->rating = $call['MaxHotelStars']; ไม่มี
    
                        // จัดการ wholesale_id ให้เป็น tourfactory
                        $data->group_id = 3;
                        $data->wholesale_id = 55;
    
                        // ได้มาเป็น 410 ตลอด บันทึกไม่ได้ ต้องใช้อีกแบบ
                        if ($call['pdf']) {
                            $url = $call['pdf'];

                            $response = Http::withHeaders([
                                'User-Agent' => 'Mozilla/5.0',
                                'Accept' => 'application/pdf',
                                'Referer' => 'https://booking.tourfactory.co.th/',
                            ])->get($url);
    
                            $headers = get_headers($url, 1);
    
                            // $response = Http::get($url);
    
                            if ($response->successful()) {
    
                                if($response->header('Content-Type')){
                                
                                    $contentType = $response->header('Content-Type');
    
                                    if(strpos($contentType, 'application/pdf') !== false){
    
                                        $filename = basename($url);
                                        $ext = explode(".", $filename);
                                        $new = 'upload/tour/pdf_file/tourfactoryapi/' . $filename;
                                        $newFileSize = strlen($response->body());
            
                                        if(Storage::disk('public')->exists($data->pdf_file)){
    
                                            // เช็ค Date Modified
                                            if(isset($headers['Last-Modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                $oldModified = $data->date_mod_pdf;
                                                if($lastModified != $oldModified){
                                                    Storage::disk('public')->delete($data->pdf_file);
            
                                                    Storage::disk('public')->put($new, $response->body());
                                                    $img_pdf = ImagePDFModel::first();
                                                    $pdfversion = 0;
                                                    if($img_pdf->status == 'on'){
                                                        $filepdf = fopen(public_path($new),"r");
                                                        $line_first = fgets($filepdf);
                                                        preg_match_all('!\d+!', $line_first, $matches);
                                                        $pdfversion = implode('.', $matches[0]);

                                                        if($pdfversion > 1.6){
                                                            $this->save_pdf($new);
                                                        }
                                                        // $this->save_pdf($new);
                                                    }
                                                    $data->pdf_file = $new;
                                                    $data->date_mod_pdf = $lastModified;
                                                }
                                            }
            
                                        }else{

                                            // เช็ค Date Modified
                                            if(isset($headers['Last-Modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                Storage::disk('public')->put($new, $response->body());
                                                $img_pdf = ImagePDFModel::first();
                                                $pdfversion = 0;
                                                if($img_pdf->status == 'on'){
                                                    $filepdf = fopen(public_path($new),"r");
                                                    $line_first = fgets($filepdf);
                                                    preg_match_all('!\d+!', $line_first, $matches);
                                                    $pdfversion = implode('.', $matches[0]);

                                                    if($pdfversion > 1.6){
                                                        $this->save_pdf($new);
                                                    }
                                                    // $this->save_pdf($new);
                                                }
                                                $data->pdf_file = $new;
                                                $data->date_mod_pdf = $lastModified;
                                            }
                                        }
    
                                    }
                                }
                            }
                        }
    
                        $data->data_type = 2; // 1 system , 2 api
                        $data->api_type = "tourfactory";
    
                        if($data->save()){
    
                            $tour[] = $data->id;
                            $tour_api_id[] = $data->api_id;
                            
                            if($call['periods']){
                                $max = array();
                                $cal1 = 0;
                                $cal2 = 0;
                                $cal3 = 0;
                                $cal4 = 0;
                                foreach($call['periods'] as $pe){
    
                                    $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_api_id'=>$pe['id'], 'api_type'=>'tourfactory'])->whereNull('deleted_at')->first();

                                    if($data3 == null){
                                        $data3 = new TourPeriodModel;
                                    }

                                    // $price_for_one = is_numeric($pe['priceForOne']) ? floatval($pe['priceForOne']) : 0; // ผู้ใหญ่พักเดี่ยวก่อนคำนวณ
                                    $price1 = is_numeric($pe['priceAdultDouble']) ? floatval($pe['priceAdultDouble']) : 0; // ผู้ใหญ่พักคู่
                                    $price2 = is_numeric($pe['priceSingleRoomAdd']) ? floatval($pe['priceSingleRoomAdd']) : 0; // ผู้ใหญ่พักเดี่ยว
                                    // $price2 = $price_for_one - $price1; //ผู้ใหญ่พักเดี่ยว
                                    $price3 = is_numeric($pe['priceChild']) ? floatval($pe['priceChild']) : 0; // เด็กมีเตียง
                                    $price4 = is_numeric($pe['priceChildNoBed']) ? floatval($pe['priceChildNoBed']) : 0; // เด็กไม่มีเตียง

                                    $data3->price1 = $price1;
                                    $data3->price2 = $price2;
                                    $data3->price3 = $price3;
                                    $data3->price4 = $price4;
    
                                    $data3->tour_id = $data->id;
                                    $data3->period_api_id = $pe['id'];
                                    $data3->group_date = date('mY',strtotime($pe['start']));
                                    $data3->start_date = $pe['start'];
                                    $data3->end_date = $pe['end'];
                                    $data3->day = $call['day'];
                                    $data3->night = $call['night'];
                                    $data3->group = $pe['group'];
                                    $data3->count = $pe['available'];
                                    $data3->status_display = "on";

                                    if($pe['status'] == "Open"){
                                        $data3->status_period = 1;
                                    }else if($pe['status'] == "Wait"){
                                        $data3->status_period = 2;
                                    }else if($pe['status'] == "Close" || $pe['status'] == "Cancel"){
                                        $data3->status_period = 3;
                                    }

                                    $data3->api_type = "tourfactory";
    
                                    if($data3->save()){
                                        $period[] = $data3->id;
                                        $period_api_id[] = $data3->period_api_id;
                                    }
                                    
                                    $calmax = max($cal1, $cal2, $cal3, $cal4);
                                    array_push($max, $calmax);
                                }
    
                                // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'tourfactory'])->whereNull('deleted_at')->get();
                                if($data4){
    
                                    $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                        return $item->price1 - $item->special_price1;
                                    })->first();
            
                                    if($data5){
                                        $num_day = "";
                                        if($data5->day && $data5->night){
                                            $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                        }
                                        $price = $data5->price1;
                                        $special_price = $data5->special_price1;
    
                                        if($special_price && $special_price > 0){
                                            $net_price = $price - $special_price;
                                        }else{
                                            $net_price = $price;
                                        }
    
                                        if($net_price && $net_price > 0){
                                            if($net_price <= 10000 ){
                                                $price_group = 1;
                                            }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                $price_group = 2;
                                            }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                $price_group = 3;
                                            }
                                            else if($net_price  > 30000 && $net_price <= 50000  ){
                                                $price_group = 4;
                                            }
                                            else if($net_price  > 50000 && $net_price <= 80000  ){
                                                $price_group = 5;
                                            }else if($net_price  > 80000  ){
                                                $price_group = 6;
                                            }
                                        }else{
                                            $price_group = 0;
                                        }
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'tourfactory'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                    }
                                }
    
                                $maxCheck = max($max);
                                if($maxCheck > 0 && $maxCheck >= 30){
                                    TourModel::where(['id'=>$data->id, 'api_type'=>'tourfactory'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                }elseif($maxCheck > 0 && $maxCheck < 30){
                                    TourModel::where(['id'=>$data->id, 'api_type'=>'tourfactory'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                }else{
                                    TourModel::where(['id'=>$data->id, 'api_type'=>'tourfactory'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                }
    
                            }
    
                            // DB::commit();
                        }
                        
                    } // end foreach
                    
                    // ลบข้อมูลทัวร์ และ Period
                    TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','tourfactory')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','tourfactory')->update(['deleted_at'=>null]);
                    TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','tourfactory')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                    TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','tourfactory')->update(['deleted_at'=>null]);
                    
                }
            }

        } catch (\Exception $e) {
            // DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'tourfactory = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
        }
    }

    public function go365_api(){
        try {
            // DB::beginTransaction();
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "x-api-key" => env('GO365_API_KEY'),
            ])
            ->post('https://api.kaikongservice.com/api/v1/tours/search', [
                "search" => "",
                "country_id" => [],
                "start_page" => "1",
                "limit_page" => "300",
                "date_start" => "",
                "date_end" => "",
                "sort" => "price_min"
            ]);

            if($response->successful()){

                $callback1 = $response->json();

                if(count($callback1['data']) > 0){
                    $tour = array();
                    $tour_api_id = array();
                    $period = array();
                    $period_api_id = array();
                    foreach($callback1['data'] as $call1){
                            
                        $data = TourModel::where(['api_id'=>$call1['tour_id'], 'api_type'=>'go365'])->whereNull('deleted_at')->first();
    
                        $allow_img = ['png', 'jpeg', 'jpg','webp'];
    
                        if($data == null){
                            $data = new TourModel;
    
                            $code_tour = IdGenerator::generate([
                                'table' => 'tb_tour', 
                                'field' => 'code', 
                                'length' => 10, 
                                'prefix' =>'NT'.date('ym'),
                                'reset_on_prefix_change' => true 
                            ]);
    
                            $data->code = $code_tour;

                            $arr = [];
                            if (isset($call1['tour_country']) && is_array($call1['tour_country'])) {
                                foreach ($call1['tour_country'] as $countryData) {
                                    if (isset($countryData['country_code_2'])) {
                                        $country = CountryModel::where('iso2', $countryData['country_code_2'])->where('status', 'on')->whereNull('deleted_at')->first();
                                        if ($country) {
                                            $arr[] = (string) $country->id;
                                        }
                                    }
                                }
                            }
                            $data->country_id = json_encode($arr);

                            if(isset($call1['tour_airline']['airline_iata']) && $call1['tour_airline']['airline_iata']){
                                $airline = TravelTypeModel::where('code',$call1['tour_airline']['airline_iata'])->where('status','on')->whereNull('deleted_at')->first();
                                if($airline){
                                    $data->airline_id = $airline->id;
                                }
                            }
    
                            if ($call1['tour_cover_image']) {
        
                                $path = $call1['tour_cover_image'];
    
                                $response = Http::get($path);
    
                                if ($response->successful()) {
    
                                    $filename = basename($path);
            
                                    $lg = Image::make($path);
                                    $ext = explode("/", $lg->mime());
                                    $lg->resize(600, 600)->stream();
                                    
                                    $new = 'upload/tour/go365api/' . $filename;
                                    
                                    if (in_array($ext[1], $allow_img)) {
                                        Storage::disk('public')->put($new, $lg);
                                        $data->image = $new;
                                    }
                                }
                            }
    
                            $data->image_check_change = 2; // 1 ไม่ดึงรูปจาก Api , 2 ดึงรูปจาก Api
                            $data->name = $call1['tour_name'];
                            $data->description = $call1['tour_description'];
    
                        }else{
                            if($data->name_check_change == null){
                                $data->name = $call1['tour_name'];
                            }

                            if($data->country_check_change == null){
                                $arr = [];
                                if (isset($call1['tour_country']) && is_array($call1['tour_country'])) {
                                    foreach ($call1['tour_country'] as $countryData) {
                                        if (isset($countryData['country_code_2'])) {
                                            $country = CountryModel::where('iso2', $countryData['country_code_2'])->where('status', 'on')->whereNull('deleted_at')->first();
                                            if ($country) {
                                                $arr[] = (string) $country->id;
                                            }
                                        }
                                    }
                                }
                                $data->country_id = json_encode($arr);
                            }

                            if($data->airline_check_change == null){
                                if(isset($call1['tour_airline']['airline_iata']) && $call1['tour_airline']['airline_iata']){
                                    $airline = TravelTypeModel::where('code',$call1['tour_airline']['airline_iata'])->where('status','on')->whereNull('deleted_at')->first();
                                    if($airline){
                                        $data->airline_id = $airline->id;
                                    }
                                }
                            }

                            if($data->description_check_change == null){
                                $data->description = $call1['tour_description'];
                            }
    
                            if($data->image_check_change == 2){
                                if ($call1['tour_cover_image']) {
    
                                    $path = $call1['tour_cover_image'];
    
                                    $response = Http::get($path);
    
                                    if ($response->successful()) {
    
                                        if ($data->image != null) {
                                            Storage::disk('public')->delete($data->image);
                                        }
    
                                        $filename = basename($path);
                
                                        $lg = Image::make($path);
                                        $ext = explode("/", $lg->mime());
                                        $lg->resize(600, 600)->stream();
                                        
                                        $new = 'upload/tour/go365api/' . $filename;
                                        
                                        if (in_array($ext[1], $allow_img)) {
                                            Storage::disk('public')->put($new, $lg);
                                            $data->image = $new;
                                        }
                                    }
        
                                }
                            }
    
                        }
    
                        $data->api_id = $call1['tour_id'];
                        $data->code1 = $call1['tour_code'];
    
                        // จัดการ wholesale_id ให้เป็น go365
                        $data->group_id = 3;
                        $data->wholesale_id = 41;
    
                        $allow_word = ['doc', 'docx'];
                        $allow_pdf = ['pdf'];
    
                        if (isset($call1['tour_file']['file_pdf']) && $call1['tour_file']['file_pdf']) {
                            $url = $call1['tour_file']['file_pdf'];
    
                            $headers = get_headers($url, 1);
    
                            $response = Http::get($url);
    
                            if ($response->successful()) {
    
                                if($response->header('Content-Type')){
                                
                                    $contentType = $response->header('Content-Type');
    
                                    if(strpos($contentType, 'application/pdf') !== false){
    
                                        $filename = basename($url);

                                        $ext = explode(".", $filename);
                                        $new = 'upload/tour/pdf_file/go365api/' . $filename;
                                        $newFileSize = strlen($response->body());
            
                                        if(Storage::disk('public')->exists($data->pdf_file)){

                                            // เช็ค Date Modified
                                            if(isset($headers['Last-Modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                $oldModified = $data->date_mod_pdf;
                                                if($lastModified != $oldModified){
                                                    Storage::disk('public')->delete($data->pdf_file);
            
                                                    Storage::disk('public')->put($new, $response->body());
                                                    $img_pdf = ImagePDFModel::first();
                                                    $pdfversion = 0;
                                                    if($img_pdf->status == 'on'){
                                                        $filepdf = fopen(public_path($new),"r");
                                                        $line_first = fgets($filepdf);
                                                        preg_match_all('!\d+!', $line_first, $matches);
                                                        $pdfversion = implode('.', $matches[0]);

                                                        if($pdfversion > 1.6){
                                                            $this->save_pdf($new);
                                                        }
                                                        // $this->save_pdf($new);
                                                    }
                                                    $data->pdf_file = $new;
                                                    $data->date_mod_pdf = $lastModified;
                                                }
                                            }
            
                                        }else{
                                            // เช็ค Date Modified
                                            if(isset($headers['Last-Modified'])){
                                                $lastModified = date("Y-m-d H:i:s",strtotime($headers['Last-Modified']));
                                                Storage::disk('public')->put($new, $response->body());
                                                $img_pdf = ImagePDFModel::first();
                                                $pdfversion = 0;
                                                if($img_pdf->status == 'on'){
                                                    $filepdf = fopen(public_path($new),"r");
                                                    $line_first = fgets($filepdf);
                                                    preg_match_all('!\d+!', $line_first, $matches);
                                                    $pdfversion = implode('.', $matches[0]);

                                                    if($pdfversion > 1.6){
                                                        $this->save_pdf($new);
                                                    }
                                                    // $this->save_pdf($new);
                                                }
                                                $data->pdf_file = $new;
                                                $data->date_mod_pdf = $lastModified;
                                            }
                                        }
    
                                    }
                                }
                            }
                        }
    
                        $data->data_type = 2; // 1 system , 2 api
                        $data->api_type = "go365";
    
                        if($data->save()){
    
                            $tour[] = $data->id;
                            $tour_api_id[] = $data->api_id;

                            $response = Http::withHeaders([
                                "Content-Type" => "application/json",
                                "x-api-key" => env('GO365_API_KEY'),
                            ])
                            ->get('https://api.kaikongservice.com/api/v1/tours/detail/'.$call1['tour_id']);
                            
                            if($response->successful()){

                                $callback2 = $response->json();
                
                                if(isset($callback2['data'][0]['tour_period']) && is_array($callback2['data'][0]['tour_period']) && count($callback2['data'][0]['tour_period']) > 0){

                                    $max = array();
                                    $cal1 = 0;
                                    $cal2 = 0;
                                    $cal3 = 0;
                                    $cal4 = 0;
                
                                    foreach($callback2['data'][0]['tour_period'] as $call2){

                                        $data3 = TourPeriodModel::where(['tour_id'=>$data->id, 'period_api_id'=>$call2['period_id'], 'api_type'=>'go365'])->whereNull('deleted_at')->first();

                                        $price1 = isset($call2['period_rate_adult_twn']) ? $call2['period_rate_adult_twn'] : 0; // ผู้ใหญ่พักคู่
                                        $price2_cal = isset($call2['period_rate_adult_sgl']) ? $call2['period_rate_adult_sgl'] : 0; // ผู้ใหญ่พักคู่

                                        if ($price2_cal >= $price1) {
                                            $price2 = $price2_cal - $price1; //ผู้ใหญ่พักเดี่ยว
                                        } else {
                                            $price2 = 0; //ผู้ใหญ่พักเดี่ยว
                                        }
                                        $price3 = $price1; //เด็กมีเตียง ใช้ราคาพักคู่
                                        $price4 = $price1; //เด็กไม่มีเตียง ใช้ราคาพักคู่

                                        if($data3 == null){
                                            $data3 = new TourPeriodModel;
                                        }

                                        $data3->price1 = $price1;
                                        $data3->price2 = $price2;
                                        $data3->price3 = $price3;
                                        $data3->price4 = $price4;

                                        $data3->tour_id = $data->id;
                                        $data3->period_api_id = $call2['period_id'];
                                        $data3->group_date = date('mY',strtotime($call2['period_date']));
                                        $data3->start_date = $call2['period_date'];
                                        $data3->end_date = $call2['period_back'];
                                        $data3->day = $call1['tour_num_day']; //เอามาจากอันหลัก
                                        $data3->night = $call1['tour_num_night']; //เอามาจากอันหลัก
                                        $data3->group = $call2['period_quota']; // ใช้อันไหน period_quota period_total
                                        $data3->count = $call2['period_available'];
                                        $data3->status_display = "on";

                                        if($call2['period_visible'] == 1 || $call2['period_visible'] == 2){
                                            $data3->status_period = 1;
                                        }else{
                                            $data3->status_period = 3;
                                        }

                                        $data3->api_type = "go365";
            
                                        if($data3->save()){
                                            $period[] = $data3->id;
                                            $period_api_id[] = $data3->period_api_id;
                                        }
                                        
                                        $calmax = max($cal1, $cal2, $cal3, $cal4);
                                        array_push($max, $calmax);
                
                                    } // end foreach call2

                                    // บันทึกจำนวนวัน และ ราคาเข้าไป tb_tour
                                    $data4 = TourPeriodModel::where(['tour_id'=>$data->id, 'api_type'=>'go365'])->whereNull('deleted_at')->get();
                                    if($data4){
            
                                        $data5 = $data4->sortBy(function ($item) { // ดึงข้อมูลที่มียอด total น้อยที่สุด
                                            return $item->price1 - $item->special_price1;
                                        })->first();
            
                                        if($data5){
                                            $num_day = "";
                                            if($data5->day && $data5->night){
                                                $num_day = $data5->day.' วัน '.$data5->night.' คืน';
                                            }
                                            $price = $data5->price1;
                                            $special_price = $data5->special_price1;
            
                                            if($special_price && $special_price > 0){
                                                $net_price = $price - $special_price;
                                            }else{
                                                $net_price = $price;
                                            }
            
                                            if($net_price && $net_price > 0){
                                                if($net_price <= 10000 ){
                                                    $price_group = 1;
                                                }else if($net_price  > 10000 && $net_price <= 20000  ){
                                                    $price_group = 2;
                                                }else if($net_price  > 20000 && $net_price <= 30000  ){
                                                    $price_group = 3;
                                                }
                                                else if($net_price  > 30000 && $net_price <= 50000  ){
                                                    $price_group = 4;
                                                }
                                                else if($net_price  > 50000 && $net_price <= 80000  ){
                                                    $price_group = 5;
                                                }else if($net_price  > 80000  ){
                                                    $price_group = 6;
                                                }
                                            }else{
                                                $price_group = 0;
                                            }
                                            TourModel::where(['id'=>$data->id, 'api_type'=>'go365'])->update(['num_day'=> $num_day,'price'=> $price,'price_group' => $price_group,'special_price'=> $special_price]);
                                        }
                                    }
            
                                    $maxCheck = max($max);
                                    if($maxCheck > 0 && $maxCheck >= 30){
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'go365'])->update(['promotion1'=>'Y','promotion2'=>'N']); // เป็นโปรไฟไหม้
                                    }elseif($maxCheck > 0 && $maxCheck < 30){
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'go365'])->update(['promotion1'=>'N','promotion2'=>'Y']); // เป็นโปรธรรมดา
                                    }else{
                                        TourModel::where(['id'=>$data->id, 'api_type'=>'go365'])->update(['promotion1'=>'N','promotion2'=>'N']); // ไม่เป็นโปรโมชั่น
                                    }
                                }
                            }
    
                            // DB::commit();
                        }else{
                            // DB::rollback();
                        }
                        
                    } // end foreach
                    
                    // ลบข้อมูลทัวร์ และ Period
                    if (!empty($tour) && !empty($tour_api_id)) {
                        TourModel::whereNotIn('id',$tour)->whereNotIn('api_id',$tour_api_id)->where('api_type','go365')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                        TourModel::whereIn('id',$tour)->whereIn('api_id',$tour_api_id)->where('api_type','go365')->update(['deleted_at'=>null]);
                    }

                    if (!empty($period) && !empty($period_api_id)) {
                        TourPeriodModel::whereNotIn('id',$period)->whereNotIn('period_api_id',$period_api_id)->where('api_type','go365')->update(['deleted_at'=>date('Y-m-d H:i:s')]);
                        TourPeriodModel::whereIn('id',$period)->whereIn('period_api_id',$period_api_id)->where('api_type','go365')->update(['deleted_at'=>null]);
                    }
                    
                }
            }

        } catch (\Exception $e) {
            // DB::rollback();
            $error_tour_id = isset($data) ? $data->id : 'unknown';
        
            $error_log = 'go365 = '.$e->getMessage().' | tour_id = '.$error_tour_id;
            $error_line = $e->getLine();
            $type_log = 'backend';
            $error_url = url()->current();
            $log_id = LogsController::save_logbackend($type_log, $error_log, $error_line, $error_url);
        }
    }
}
