<?php

/**
 * Seed Payment Terms Content
 * Run: php seed_payment_terms.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WebPage;

$content = <<<'HTML'
<div class="max-w-4xl mx-auto p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
   
    <div class="prose prose-lg text-gray-600 mb-10 leading-relaxed text-sm">
        <span class="text-orange-600">บริษัทเน็กซ์ ทริป ฮอลิเดย์</span> จำกัดบริการทัวร์ในรูปแบบ ทัวร์หมู่คณะ/แพ็คเกจทัวร์อิสระ ในรูปแบบจัดทัวร์ร่วมกับกลุ่มบริษัทพันธมิตร (Partner) และทัวร์ที่จัดขึ้นเองในนาม <span class="text-orange-600">บริษัท เน็กซ์ ทริป ฮอลิเดย์ จำกัด</span> เพื่อให้ลูกค้าสามารถเลือกบริการทัวร์ได้ในความต้องการที่หลากหลาย ทั้งในเรื่องของสถานที่ท่องเที่ยว สายการบินที่เดินทางออกจากประเทศไทยทั้งสนามบินดอนเมืองและสุวรรณภูมิ หรือเชียงใหม่ เพื่อเดินทางไปยังประเทศปลายทางทั่วโลก ลูกค้าสามารถเลือกโปรแกรมท่องเที่ยวได้ <span class="text-orange-600">มากกว่า 5,000 รายการท่องเที่ยว</span> ที่ต้องการเปิดประสบการณ์ในการเดินทาง
    </div>

    <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            ช่องทางการจองและชำระเงิน
        </h3>
        
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Channel 1 -->
            <div class="bg-gray-50 rounded-xl p-6 border border-gray-100 relative overflow-hidden group hover:border-blue-200 transition-all duration-300" style="background-color: #f9fafb; border-color: #f3f4f6;">
                <div class="absolute top-0 right-0 -mt-2 -mr-2 w-16 h-16 bg-green-100 rounded-full blur-xl opacity-50 group-hover:opacity-70 transition-opacity"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-green-100 rounded-lg text-green-600">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .5C5.8.5.5 5.8.5 12a11.3 11.3 0 0 0 3.3 8.3c-.1.8-.8 3-2.9 3.5 0 0 3.5-.2 5.3-2.3 1.8.6 3.8.9 5.8.9 6.2 0 11.5-5.3 11.5-11.5S18.2.5 12 .5z"/></svg>
                        </div>
                        <h4 class="font-bold text-gray-900">Line OA: @nexttripholiday</h4>
                    </div>
                    
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 w-1.5 h-1.5 bg-green-500 rounded-full flex-shrink-0"></span>
                            <span><strong>จองล่วงหน้า 30 วัน:</strong> ชำระมัดจำภายใน 1 วันหลังได้รับเอกสารยืนยัน (ก่อน 13.00 น.)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 w-1.5 h-1.5 bg-green-500 rounded-full flex-shrink-0"></span>
                            <span><strong>ส่วนที่เหลือ:</strong> ชำระก่อนเดินทางอย่างน้อย 30 วัน</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 w-1.5 h-1.5 bg-green-500 rounded-full flex-shrink-0"></span>
                            <span><strong>จองด่วน (<30 วัน):</strong> ชำระเต็มจำนวน 100% ทันที</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Channel 2 -->
            <div class="bg-gray-50 rounded-xl p-6 border border-gray-100 relative overflow-hidden group hover:border-blue-200 transition-all duration-300" style="background-color: #f9fafb; border-color: #f3f4f6;">
                <div class="absolute top-0 right-0 -mt-2 -mr-2 w-16 h-16 bg-blue-100 rounded-full blur-xl opacity-50 group-hover:opacity-70 transition-opacity"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-blue-100 rounded-lg text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        </div>
                        <h4 class="font-bold text-gray-900">Website: nexttripholiday.com</h4>
                    </div>
                    
                    <p class="text-sm text-gray-600 mb-3 leading-relaxed">
                        สำหรับลูกค้าที่ทำรายการผ่านหน้าเว็บไซต์
                    </p>
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 w-1.5 h-1.5 bg-blue-500 rounded-full flex-shrink-0"></span>
                            <span>รอเจ้าหน้าที่/ระบบยืนยันที่นั่งทางอีเมล</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 w-1.5 h-1.5 bg-blue-500 rounded-full flex-shrink-0"></span>
                            <span>การจองสมบูรณ์เมื่อชำระเงินแล้วเท่านั้น</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Important Notes -->
    <div class="mt-12 bg-orange-50/50 rounded-2xl p-8 border border-orange-100" style="background-color: #fff7ed; border-color: #ffedd5;">
        <h3 class="text-xl font-bold text-orange-900 mb-6 flex items-center gap-3">
            <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            ข้อสำคัญและเงื่อนไขเพิ่มเติม
        </h3>
        
        <ul class="space-y-4">
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">1</span>
                <span>เมื่อลูกค้าได้ตกลงซื้อขายทัวร์ในราคาที่ท่านพึงพอใจ และใตร่ตรองมาเป็นอย่างดีแล้ว หากมีการชำระเงินกับทางบริษัทแล้วนั้นเมื่อทางบริษัททำการปรับลดราคาที่แตกต่างจากราคาที่ลูกค้าซื้อทางบริษัทจะไม่มีการคืนเงินส่วนต่างแต่อย่างไร บริษัทจะถือว่าท่านได้พึงพอใจกับราคาที่ชำระมาแล้ว</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">2</span>
                <span>ลูกค้าจะต้องทำการอ่านรายละเอียดทัวร์ในส่วนของ ค่าทิปพนักงานขับรถ หัวหน้าทัวร์ และ มัคคุเทศก์ท้องถิ่น ตามธรรมเนียม โดยเงื่อนไขการรวมและไม่รวมค่าทิป ต่อ ลูกค้า ผู้เดินทาง 1 ท่าน รวมไปถึงเด็ก ยกเว้นเด็กอายุไม่ถึง 2 ปี ณ วันเดินทางกลับ (INFANT) ทั้งนี้ท่านจะต้องชำระตามที่โปรแกรมระบุทุกกรณี ท่านสามารถให้มากกว่านี้ได้ตามความเหมาะสมและความพึงพอใจของท่าน โดยส่วนนี้ ทางบริษัทขอสงวนสิทธิ์ในการเรียกเก็บก่อนเดินทางทุกท่าน ที่สนามบิน ในวันเช็คอิน หรือในระหว่างการท่องเที่ยว ตามเงื่อนไขบริษัท</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">3</span>
                <span>ถ้าหากมีผู้เดินทางต่ำกว่า จำนวนที่ทางบริษัทกำหนด ทางบริษัทขอสงวนสิทธิ์ในการยกเลิกการเดินทางหรือมีการเปลี่ยนแปลงราคา โดยจะแจ้งให้ทราบล่วงหน้า 7 วันก่อนการเดินทาง ในกรณีที่ลูกค้าจองตั๋วเครื่องบิน/ห้องพัก/บัตรโดยสาร โดยไม่ได้แจ้งบริษัท ขอสงวนสิทธิ์ในการรับผิดชอบทุกกรณี</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">4</span>
                <span>กรณีพัก 3 ท่าน/ห้อง ทางโรงแรมจะทำการเสริมเตียงให้เท่านั้น การเสริมเตียงจะขึ้นอยู่กับนโยบายของโรงแรมนั้นๆ (บางโรงแรมใช้เตียง SGL ในการเสริมเตียง บางโรงแรมจะใช้ฟูกในการเสริมเตียง) กรณีที่ห้อง TRP เต็ม ทางบริษัทอาจจะต้องปรับไปนอนห้องพักสำหรับ 1 ท่าน 1 ห้องและสำหรับ 2 ท่าน 1 ห้อง โดยท่านจะต้องชำระค่าห้องพักเดี่ยวเพิ่ม</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">5</span>
                <span>อัตราค่าบริการทัวร์เฉพาะนักท่องเที่ยวที่ถือหนังสือเดินทางไทยเท่านั้น กรณีถือหนังสือเดินทางต่างประเทศ ทางบริษัทขอสงวนสิทธิ์เรียกเก็บค่าธรรมเนียมเพิ่มจากราคาทัวร์ ท่านละ 100 USD. (เป็นเงินไทยประมาณ 3,200 บาท)</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">6</span>
                <span>กรณีผู้เดินทางถูกปฏิเสธการเข้า – ออกประเทศจากประเทศต้นทางและปลายทาง ทั้งจากที่ด้านหน้าเคาน์เตอร์เช็คอิน หรือจากด่านตรวจคนเข้าเมืองก็ตาม ทางบริษัทขอสงวนสิทธิ์ไม่รับผิดชอบค่าใช้จ่ายที่จะเกิดขึ้นตามมา และ จะไม่สามารถคืนเงินค่าทัวร์ที่ท่านชำระเรียบร้อยแล้วไม่ว่าส่วนใดส่วนหนึ่ง</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">7</span>
                <span>กรณีต้องการตัดกรุ๊ปส่วนตัว กรุ๊ปเหมาที่สถานะผู้เดินทางเป็น เด็กนักเรียน นักศึกษา ครู ธุรกิจขายตรงเครื่องสำอาง หมอ พยาบาล ชาวต่างชาติ หรือคณะที่ต้องการให้เพิ่มสถานที่ขอดูงาน กรุณาติดต่อแจ้งรายละเอียด โดยละเอียด กับเจ้าหน้าที่เพื่อทำราคาให้ใหม่ทุกครั้ง เนื่องจากบางโปรแกรมมีความจำเป็นในการชาร์จค่าใช้จ่ายในบางอาชีพหากท่านปกปิดข้อมูล หากทางบริษัททราบภายหลังลูกค้าจะต้องเป็นผู้รับผิดชอบค่าใช้จ่ายที่เกิดขึ้นหากมีการเรียกเก็บเพิ่มจากทางฝั่งต่างประเทศ</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">8</span>
                <span>กรณีที่ท่านต้องออกบัตรโดยสารภายใน (ตั๋วภายในประเทศ เช่น ตั๋วเครื่องบิน , ตั๋วรถทัวร์ , ตั๋วรถไฟ) กรุณาติดต่อสอบถามเพื่อยืนยันกับเจ้าหน้าที่ก่อนทุกครั้ง และควรจองบัตรโดยสารภายในที่สามารถเลื่อนวันและเวลาเดินทางได้ เพราะมีบางกรณีที่สายการบินอาจมีการปรับเปลี่ยนไฟล์ทบิน หรือ เวลาบิน โดยไม่แจ้งให้ทราบล่วงหน้า ทั้งนี้ขึ้นอยู่กับฤดูกาล สภาพภูมิกาศ และ ตารางบินของท่าอากาศยานเป็นสำคัญเท่านั้น สิ่งสำคัญ ท่านจำเป็นต้องมาถึงสนามบินเพื่อเช็คอินก่อนเครื่องบิน อย่างน้อย 3 ชั่วโมง โดยในส่วนนี้หากเกิดความเสียหายใดๆบริษัทขอสงวนสิทธิ์ในการไม่รับผิดชอบค่าใช้จ่ายที่เกิดขึ้นใดๆทั้งสิ้น</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">9</span>
                <span>กรณีที่ออกบัตรโดยสาร (ตั๋ว) เรียบร้อยแล้ว มีรายละเอียดส่วนใดผิด ทางบริษัทขอสงวนสิทธิ์ในการรับผิดชอบไม่ว่าส่วนใดส่วนหนึ่ง หากท่านไม่ดำเนินการส่งสำเนาหน้าแรกของหนังสือเดินทางให้ทางบริษัทเพื่อใช้ในการออกบัตรโดยสาร</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">10</span>
                <span>หลังจากท่านชำระค่าทัวร์ครบตามจำนวนเรียบร้อยแล้ว ทางบริษัทจะนำส่งใบนัดหมายและเตรียมตัวการเดินทางให้ท่านอย่างน้อย 1 หรือ 3 วัน ก่อนออกเดินทาง</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">11</span>
                <span>อัตราทัวร์นี้ เป็นอัตราสำหรับบัตรโดยสารเครื่องบินแบบหมู่คณะ (ตั๋วกรุ๊ป) ท่านจะไม่สามารถเลื่อนไฟล์ท วัน ไป หรือ กลับส่วนใดได้ จำเป็นจะต้องไป และ กลับ ตามกำหนดการเท่านั้น หากต้องการเปลี่ยนแปลงกรุณาติดต่อเจ้าหน้าที่เป็นกรณีพิเศษ</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">12</span>
                <span>ทางบริษัทไม่มีนโยบายจัดคู่นอนให้กับลูกค้าที่ไม่รู้จักกันมาก่อน เช่น กรณีที่ท่านเดินทาง 1 ท่าน จำเป็นต้องชำระค่าห้องพักเดี่ยวตามที่ระบุ</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">13</span>
                <span>หนังสือเดินทาง หรือ พาสปอร์ต ต้องมีอายุใช้งานได้คงเหลือไม่น้อยกว่า 6 เดือน ณ วันกลับ หากลูกค้าทำการจองและชำระเงินเรียบร้อย หากเกิดปัยหาในส่วนนี้ทางบริษัทจะไม่รับผิดชอบทุกกรณี</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">14</span>
                <span>กรณีที่ท่านเป็นอิสลาม ไม่ทานเนื้อสัตว์ หรือ แพ้อาหารบางประเภท กรุณาแจ้งเจ้าหน้าที่เป็นกรณีพิเศษ</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">15</span>
                <span>บริษัทฯ จะไม่รับผิดชอบค่าเสียหายในเหตุการณ์ที่เกิดจากยานพาหนะ การยกเลิกเที่ยวบิน การล่าช้าของสายการบิน ภัยธรรมชาติ การเมือง จราจล ประท้วง คำสั่งของเจ้าหน้าที่รัฐ และอื่นๆ ที่อยู่นอกเหนือการควบคุมของทางบริษัท</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">16</span>
                <span>เมื่อท่านออกเดินทางไปกับคณะแล้ว ท่านงดใช้บริการใดบริการหนึ่ง หรือไม่เดินทางพร้อมคณะ ถือว่าท่านสละสิทธิ์ ไม่สามารถเรียกร้องค่าบริการคืนได้ ไม่ว่ากรณีใดๆ ทั้งสิ้น</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">17</span>
                <span>มัคคุเทศก์ พนักงาน และตัวแทนบริษัทฯ ไม่มีอำนาจในการตัดสินใจหรือให้คำสัญญาใดๆ ทั้งสิ้นแทน บริษัทฯนอกจากมีเอกสารลงนามโดยผู้มีอำนาจของบริษัทฯกำกับเท่านั้นกรณีต้องการเปลี่ยนแปลงพีเรียดวันเดินทาง (เลื่อนวันเดินทาง) ทางบริษัทขอสงวนสิทธิ์ในการหักค่าใช้จ่ายการดำเนินการต่างๆ ที่เกิดขึ้นจริงสำหรับการดำเนินการจองครั้งแรก ตามจำนวนครั้งที่เปลี่ยนแปลง ไม่ว่ากรณีใดๆทั้งสิ้น</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">18</span>
                <span>บริษัทขอสงวนสิทธิ์ ในการไม่รับผิดชอบใดๆทั้งสิ้น หากเกิดสิ่งของสูญหายระหว่างการเดินทาง และ ขอสงวนสิทธิ์ในการเรียกเก็บค่าใช้จ่ายตามจริง กรณีท่านลืมสิ่งของไว้ที่โรงแรมและจำเป็นต้องส่งมายังจุดหมายปลายทางตามที่ท่านต้องการ</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">19</span>
                <span>ขอสงวนสิทธิ์การเก็บค่าน้ำมันและภาษีสนามบินทุกแห่งเพิ่ม หากสายการบินมีการปรับขึ้นก่อนวันเดินทาง</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">20</span>
                <span>เนื่องจากการเดินทางท่องเที่ยวในครั้งนี้ เป็นการชำระแบบเหมาจ่ายขาดกับบริษัทตัวแทนในต่างประเทศ ทางบริษัทจึงขอสงวนสิทธิ์ ไม่สามารถขอรับเงินคืนได้ในบริการบางส่วน หรือ ส่วนใดส่วนหนึ่งที่ท่านไม่ต้องการได้รับบริการ หากระหว่างเดินทาง สถานที่ท่องเที่ยวใดที่ไม่สามารถเข้าชมได้ ไม่ว่าด้วยสาเหตุใดก็ตาม ทางบริษัทขอสงวนสิทธิ์ในการไม่สามารถคืนค่าใช้จ่ายไม่ว่าส่วนใดส่วนหนึ่งให้ท่าน เนื่องจากทางบริษัทได้ทำการจองและถูกเก็บค่าใช้จ่ายแบบเหมาจ่ายไปล่วงหน้าทั้งหมดแล้ว</span>
            </li>
            <li class="flex items-start gap-3 text-gray-700 text-sm leading-relaxed">
                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-700 text-xs font-bold rounded-full mt-0.5" style="background-color: #ffedd5; color: #c2410c;">21</span>
                <span>หากมีสถานที่ ร้านค้าที่ไม่สามารถเปิดให้บริการได้ภายหลัง โปรแกรมอาจมีการเปลี่ยนแปลงได้ตามความเหมาะสม โดยไม่แจ้งให้ทราบล่วงหน้า โดยส่วนนี้ทางบริษัทจะคำนึงถึงประโยชน์ของลูกค้าเป็นสำคัญ หากกรณีที่จำเป็นจะต้องมีค่าใช้จ่ายเพิ่ม ทางบริษัทจะแจ้งให้ทราบล่วงหน้า</span>
            </li>
        </ul>
    </div>
</div>
HTML;

WebPage::updateOrCreate(
    ['key' => 'terms'],
    [
        'title' => 'เงื่อนไขการให้บริการ',
        'content' => $content,
        'description' => 'เงื่อนไขการให้บริการ',
        'is_active' => true,
    ]
);

echo "terms  content seeded successfully.\n";