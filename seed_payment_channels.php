<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WebPage;

$content = <<<'HTML'
<h2>ช่องทางการชำระเงิน</h2>
<p>ท่านสามารถชำระเงินค่าบริการทัวร์ของ NextTrip ผ่านช่องทางต่างๆ ที่สะดวกสำหรับท่าน ดังนี้</p>

<h3>1. โอนเงินผ่านธนาคาร</h3>
<p>ท่านสามารถโอนเงินเข้าบัญชีธนาคารของบริษัทได้ตามรายละเอียดด้านล่าง:</p>

<div style="background:#f9fafb; padding:20px; border-radius:8px; margin:15px 0;">
<p><strong>ธนาคารกสิกรไทย (KBANK)</strong></p>
<ul style="list-style:none; padding:0;">
<li>ชื่อบัญชี: บริษัท เน็กซ์ทริป จำกัด</li>
<li>เลขบัญชี: xxx-x-xxxxx-x</li>
<li>ประเภท: ออมทรัพย์</li>
<li>สาขา: สำนักงานใหญ่</li>
</ul>
</div>

<div style="background:#f9fafb; padding:20px; border-radius:8px; margin:15px 0;">
<p><strong>ธนาคารไทยพาณิชย์ (SCB)</strong></p>
<ul style="list-style:none; padding:0;">
<li>ชื่อบัญชี: บริษัท เน็กซ์ทริป จำกัด</li>
<li>เลขบัญชี: xxx-x-xxxxx-x</li>
<li>ประเภท: ออมทรัพย์</li>
<li>สาขา: สำนักงานใหญ่</li>
</ul>
</div>

<div style="background:#f9fafb; padding:20px; border-radius:8px; margin:15px 0;">
<p><strong>ธนาคารกรุงเทพ (BBL)</strong></p>
<ul style="list-style:none; padding:0;">
<li>ชื่อบัญชี: บริษัท เน็กซ์ทริป จำกัด</li>
<li>เลขบัญชี: xxx-x-xxxxx-x</li>
<li>ประเภท: ออมทรัพย์</li>
<li>สาขา: สำนักงานใหญ่</li>
</ul>
</div>

<div style="background:#f9fafb; padding:20px; border-radius:8px; margin:15px 0;">
<p><strong>ธนาคารกรุงไทย (KTB)</strong></p>
<ul style="list-style:none; padding:0;">
<li>ชื่อบัญชี: บริษัท เน็กซ์ทริป จำกัด</li>
<li>เลขบัญชี: xxx-x-xxxxx-x</li>
<li>ประเภท: ออมทรัพย์</li>
<li>สาขา: สำนักงานใหญ่</li>
</ul>
</div>

<h3>2. บัตรเครดิต / บัตรเดบิต</h3>
<p>รองรับการชำระผ่านบัตรเครดิตและบัตรเดบิตทุกธนาคาร:</p>
<ul>
<li><strong>Visa</strong></li>
<li><strong>Mastercard</strong></li>
<li><strong>JCB</strong></li>
<li><strong>American Express</strong></li>
<li><strong>UnionPay</strong></li>
</ul>
<p><em>* มีค่าธรรมเนียมการใช้บัตร 2-3% ขึ้นอยู่กับประเภทบัตร</em></p>

<h4>โปรโมชั่นผ่อนชำระ 0%</h4>
<table style="width:100%; border-collapse:collapse; margin:15px 0;">
<tr style="background:#f3f4f6;">
<th style="padding:12px; border:1px solid #e5e7eb; text-align:left;">ธนาคาร</th>
<th style="padding:12px; border:1px solid #e5e7eb; text-align:left;">จำนวนเดือน</th>
<th style="padding:12px; border:1px solid #e5e7eb; text-align:left;">ยอดขั้นต่ำ</th>
</tr>
<tr>
<td style="padding:12px; border:1px solid #e5e7eb;">กสิกรไทย</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3, 6, 10 เดือน</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3,000 บาท</td>
</tr>
<tr>
<td style="padding:12px; border:1px solid #e5e7eb;">กรุงเทพ</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3, 6 เดือน</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3,000 บาท</td>
</tr>
<tr>
<td style="padding:12px; border:1px solid #e5e7eb;">ไทยพาณิชย์</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3, 6, 10 เดือน</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3,000 บาท</td>
</tr>
<tr>
<td style="padding:12px; border:1px solid #e5e7eb;">กรุงศรี</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3, 6, 10 เดือน</td>
<td style="padding:12px; border:1px solid #e5e7eb;">3,000 บาท</td>
</tr>
</table>

<h3>3. QR Code / PromptPay</h3>
<p>สะดวกและรวดเร็ว! สแกน QR Code ผ่าน Mobile Banking ของทุกธนาคาร</p>
<ul>
<li>รองรับทุกธนาคารที่เข้าร่วม PromptPay</li>
<li>ไม่มีค่าธรรมเนียม</li>
<li>ยืนยันการชำระเงินทันที</li>
</ul>

<h3>4. e-Wallet</h3>
<p>ชำระผ่านกระเป๋าเงินอิเล็กทรอนิกส์:</p>
<ul>
<li><strong>TrueMoney Wallet</strong></li>
<li><strong>Rabbit LINE Pay</strong></li>
<li><strong>ShopeePay</strong></li>
</ul>

<h3>5. เคาน์เตอร์เซอร์วิส</h3>
<p>ชำระเงินสดได้ที่เคาน์เตอร์ตามจุดบริการต่างๆ:</p>
<ul>
<li>7-Eleven</li>
<li>เคาน์เตอร์เซอร์วิส</li>
<li>ไปรษณีย์ไทย</li>
<li>Big C / Tesco Lotus</li>
</ul>
<p><em>* อาจมีค่าธรรมเนียม 10-20 บาท ขึ้นอยู่กับจุดชำระ</em></p>

<h3>การแจ้งชำระเงิน</h3>
<p>หลังจากชำระเงินแล้ว กรุณาแจ้งหลักฐานการชำระเงินพร้อมรหัสการจองผ่านช่องทาง:</p>
<ul>
<li><strong>LINE:</strong> @nexttrip</li>
<li><strong>อีเมล:</strong> payment@nexttrip.asia</li>
<li><strong>โทรศัพท์:</strong> 02-xxx-xxxx</li>
</ul>

<p><em>ข้อมูล ณ วันที่ 1 มกราคม 2569</em></p>
HTML;

$key = 'payment_channels';
$page = WebPage::where('key', $key)->first();

if ($page) {
    $page->update([
        'title' => 'ช่องทางการชำระเงิน',
        'content' => $content,
        'description' => 'ช่องทางและวิธีการชำระเงิน',
        'is_active' => true,
    ]);
    echo "✓ Updated: ช่องทางการชำระเงิน\n";
} else {
    WebPage::create([
        'key' => $key,
        'title' => 'ช่องทางการชำระเงิน',
        'content' => $content,
        'description' => 'ช่องทางและวิธีการชำระเงิน',
        'is_active' => true,
    ]);
    echo "✓ Created: ช่องทางการชำระเงิน\n";
}

echo "\n✅ Done!\n";
