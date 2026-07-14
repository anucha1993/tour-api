// Seed initial SEO content ONLY for slugs that don't have data yet.
// Existing SEO records are LEFT UNTOUCHED.

$defaults = [
    // ── Main ─────────────────────────────────────────
    'reviews' => [
        'meta_title'       => 'รีวิวจากลูกค้าจริง | Next Trip Holiday',
        'meta_description' => 'อ่านรีวิวและประสบการณ์จริงจากลูกค้าที่เดินทางกับ Next Trip Holiday บริษัททัวร์ที่ได้รับความไว้วางใจ พร้อมรูปภาพและคะแนนความพึงพอใจจริงจากผู้เดินทาง',
        'meta_keywords'    => 'รีวิวทัวร์, รีวิวลูกค้า, ประสบการณ์เที่ยว, next trip holiday, รีวิวบริษัททัวร์',
        'og_title'         => 'รีวิวจากลูกค้าจริง — Next Trip Holiday',
        'og_description'   => 'ประสบการณ์และรีวิวจริงจากลูกค้าที่เดินทางกับเรา',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],

    // ── Tours ────────────────────────────────────────
    'tours-festival' => [
        'meta_title'       => 'ทัวร์เทศกาลตามฤดูกาล — ซากุระ ใบไม้แดง Sky Lantern | Next Trip Holiday',
        'meta_description' => 'รวมโปรแกรมทัวร์เทศกาลพิเศษ ชมซากุระบานที่ญี่ปุ่น ใบไม้เปลี่ยนสีเกาหลี เทศกาลไฟ Yi Peng และเทศกาลชื่อดังทั่วโลก จองล่วงหน้าก่อนที่นั่งเต็ม',
        'meta_keywords'    => 'ทัวร์เทศกาล, ทัวร์ซากุระ, ทัวร์ใบไม้แดง, ทัวร์ตามฤดูกาล, ทัวร์เทศกาลไฟ, sky lantern',
        'og_title'         => 'ทัวร์เทศกาลตามฤดูกาล — Next Trip Holiday',
        'og_description'   => 'ทัวร์เทศกาลพิเศษ ซากุระ ใบไม้เปลี่ยนสี Sky Lantern และอื่นๆ',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],
    'tours-packages' => [
        'meta_title'       => 'แพ็คเกจทัวร์ราคาพิเศษ — ที่พัก + ตั๋วเครื่องบิน | Next Trip Holiday',
        'meta_description' => 'รวมแพ็คเกจทัวร์คุ้มค่า จองครบจบในราคาเดียว รวมที่พัก ตั๋วเครื่องบิน กิจกรรม และรถรับส่ง พร้อมส่วนลดพิเศษเฉพาะแพ็คเกจ',
        'meta_keywords'    => 'แพ็คเกจทัวร์, ทัวร์แพ็คเกจ, ทัวร์ราคาถูก, จองแพ็คเกจเที่ยว, แพ็คเกจเที่ยวต่างประเทศ',
        'og_title'         => 'แพ็คเกจทัวร์ราคาพิเศษ — Next Trip Holiday',
        'og_description'   => 'จองครบจบในราคาเดียว ที่พัก + ตั๋วเครื่องบิน + กิจกรรม',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],
    'tours-group' => [
        'meta_title'       => 'รับจัดกรุ๊ปทัวร์ส่วนตัว — บริษัท คณะ ดูงาน สัมมนา | Next Trip Holiday',
        'meta_description' => 'บริการรับจัดกรุ๊ปทัวร์เฉพาะกิจสำหรับบริษัท องค์กร คณะดูงาน สัมมนา หรือกลุ่มเพื่อน ออกแบบเส้นทางตามงบประมาณ พร้อมทีมงานมืออาชีพดูแลตลอดทริป',
        'meta_keywords'    => 'รับจัดกรุ๊ปทัวร์, ทัวร์บริษัท, ทัวร์ดูงาน, ทัวร์สัมมนา, กรุ๊ปทัวร์, incentive tour',
        'og_title'         => 'รับจัดกรุ๊ปทัวร์ส่วนตัว — Next Trip Holiday',
        'og_description'   => 'จัดกรุ๊ปทัวร์บริษัท ดูงาน สัมมนา ออกแบบตามงบประมาณ',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],

    // ── Legal / policy ───────────────────────────────
    'terms' => [
        'meta_title'       => 'เงื่อนไขการให้บริการ | Next Trip Holiday',
        'meta_description' => 'ข้อกำหนดและเงื่อนไขการใช้บริการของบริษัท Next Trip Holiday ครอบคลุมสิทธิและหน้าที่ของผู้ใช้บริการทัวร์และเว็บไซต์',
        'meta_keywords'    => 'เงื่อนไขบริการ, ข้อกำหนดการใช้งาน, terms of service',
        'og_title'         => 'เงื่อนไขการให้บริการ — Next Trip Holiday',
        'og_description'   => 'ข้อกำหนดและเงื่อนไขการใช้บริการทัวร์และเว็บไซต์',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],
    'privacy-policy' => [
        'meta_title'       => 'นโยบายความเป็นส่วนตัว (PDPA) | Next Trip Holiday',
        'meta_description' => 'นโยบายการเก็บ ใช้ และเปิดเผยข้อมูลส่วนบุคคลของลูกค้า ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA)',
        'meta_keywords'    => 'นโยบายความเป็นส่วนตัว, PDPA, การคุ้มครองข้อมูล, privacy policy',
        'og_title'         => 'นโยบายความเป็นส่วนตัว — Next Trip Holiday',
        'og_description'   => 'การเก็บ ใช้ และคุ้มครองข้อมูลส่วนบุคคลตาม PDPA',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],
    'cookie-policy' => [
        'meta_title'       => 'นโยบายคุกกี้ | Next Trip Holiday',
        'meta_description' => 'อธิบายการใช้งานคุกกี้บนเว็บไซต์ Next Trip Holiday ประเภทของคุกกี้ที่ใช้งาน และวิธีจัดการคุกกี้จากผู้ใช้',
        'meta_keywords'    => 'นโยบายคุกกี้, cookie policy',
        'og_title'         => 'นโยบายคุกกี้ — Next Trip Holiday',
        'og_description'   => 'การใช้งานคุกกี้บนเว็บไซต์และการจัดการโดยผู้ใช้',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],
    'data-deletion' => [
        'meta_title'       => 'คำขอลบข้อมูลบัญชี | Next Trip Holiday',
        'meta_description' => 'ขั้นตอนและวิธีการขอลบข้อมูลบัญชีผู้ใช้งาน ตามสิทธิของเจ้าของข้อมูลส่วนบุคคลภายใต้ พ.ร.บ. PDPA',
        'meta_keywords'    => 'ขอลบข้อมูล, ลบบัญชี, PDPA, สิทธิเจ้าของข้อมูล',
        'og_title'         => 'คำขอลบข้อมูลบัญชี — Next Trip Holiday',
        'og_description'   => 'ขั้นตอนขอลบข้อมูลบัญชีตาม พ.ร.บ. PDPA',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],
    'payment-channels' => [
        'meta_title'       => 'ช่องทางการชำระเงิน — โอนธนาคาร บัตรเครดิต PromptPay | Next Trip Holiday',
        'meta_description' => 'ช่องทางชำระเงินค่าทัวร์ที่รองรับ ทั้งโอนผ่านธนาคารชั้นนำ บัตรเครดิต/เดบิต และ QR PromptPay สะดวก รวดเร็ว ปลอดภัย',
        'meta_keywords'    => 'ช่องทางชำระเงิน, จ่ายค่าทัวร์, PromptPay, บัตรเครดิต, โอนเงินธนาคาร',
        'og_title'         => 'ช่องทางการชำระเงิน — Next Trip Holiday',
        'og_description'   => 'ชำระค่าทัวร์ผ่านธนาคาร บัตรเครดิต หรือ QR PromptPay',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],
    'payment-terms' => [
        'meta_title'       => 'เงื่อนไขการชำระเงินและการยกเลิกทัวร์ | Next Trip Holiday',
        'meta_description' => 'เงื่อนไขการมัดจำ การชำระเงินส่วนที่เหลือ นโยบายการยกเลิกและการคืนเงินทัวร์ อ่านก่อนตัดสินใจจอง',
        'meta_keywords'    => 'เงื่อนไขชำระเงิน, ยกเลิกทัวร์, คืนเงินทัวร์, มัดจำทัวร์',
        'og_title'         => 'เงื่อนไขการชำระเงินและยกเลิกทัวร์ — Next Trip Holiday',
        'og_description'   => 'เงื่อนไขมัดจำ ชำระเงิน ยกเลิกและคืนเงินทัวร์',
        'robots_index'     => true,
        'robots_follow'    => true,
    ],

    // ── Utility / auth (noindex) ─────────────────────
    'search' => [
        'meta_title'       => 'ค้นหาทัวร์ | Next Trip Holiday',
        'meta_description' => 'ค้นหาทัวร์ตามคำค้น ประเทศ ราคา ระยะเวลา และตัวกรองอื่นๆ พบทัวร์ที่ใช่จากทัวร์กว่า 2,000 รายการ',
        'meta_keywords'    => null,
        'og_title'         => 'ค้นหาทัวร์ — Next Trip Holiday',
        'og_description'   => 'ค้นหาทัวร์ตามความต้องการของคุณ',
        'robots_index'     => false,   // dynamic search results — noindex
        'robots_follow'    => true,
    ],
    'login' => [
        'meta_title'       => 'เข้าสู่ระบบสมาชิก | Next Trip Holiday',
        'meta_description' => 'เข้าสู่ระบบเพื่อจองทัวร์ ดูใบเสนอราคา สะสมแต้ม และรับสิทธิพิเศษเฉพาะสมาชิก Next Trip Holiday',
        'meta_keywords'    => null,
        'og_title'         => 'เข้าสู่ระบบสมาชิก',
        'og_description'   => 'เข้าสู่ระบบเพื่อใช้งานสิทธิพิเศษของสมาชิก',
        'robots_index'     => false,
        'robots_follow'    => true,
    ],
    'register' => [
        'meta_title'       => 'สมัครสมาชิกฟรี — รับส่วนลดและโปรโมชั่นพิเศษ | Next Trip Holiday',
        'meta_description' => 'สมัครสมาชิกฟรี รับส่วนลดพิเศษเฉพาะสมาชิก บันทึกประวัติการจอง และข่าวสารโปรโมชั่นทัวร์ล่าสุดก่อนใคร',
        'meta_keywords'    => null,
        'og_title'         => 'สมัครสมาชิกฟรี',
        'og_description'   => 'รับส่วนลด โปรโมชั่น และข่าวสารทัวร์เฉพาะสมาชิก',
        'robots_index'     => false,
        'robots_follow'    => true,
    ],
    'forgot-password' => [
        'meta_title'       => 'ลืมรหัสผ่าน | Next Trip Holiday',
        'meta_description' => 'ขอตั้งรหัสผ่านใหม่สำหรับบัญชีสมาชิก Next Trip Holiday ผ่านอีเมลหรือเบอร์โทรที่ลงทะเบียนไว้',
        'meta_keywords'    => null,
        'og_title'         => 'ลืมรหัสผ่าน',
        'og_description'   => 'ขอตั้งรหัสผ่านใหม่สำหรับบัญชีสมาชิก',
        'robots_index'     => false,
        'robots_follow'    => true,
    ],
];

$created = 0;
$skipped = 0;

foreach ($defaults as $slug => $data) {
    $existing = \App\Models\SeoSetting::where('page_slug', $slug)->first();
    if ($existing) {
        echo "  ⏭  SKIP    {$slug}  (already exists — meta_title=\"" . mb_substr($existing->meta_title ?? '', 0, 40) . "...\")\n";
        $skipped++;
        continue;
    }

    \App\Models\SeoSetting::create(array_merge(['page_slug' => $slug], $data));
    echo "  ✅ CREATE  {$slug}\n";
    $created++;
}

echo "\nCreated: {$created}  |  Skipped (existing): {$skipped}\n";
