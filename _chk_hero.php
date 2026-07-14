$slides = \App\Models\HeroSlide::orderBy('sort_order')->get();
foreach ($slides as $s) {
    echo "── id={$s->id}  sort_order={$s->sort_order}  active=" . ($s->is_active ? 'Y' : 'N') . "\n";
    echo "  filename    : {$s->filename}\n";
    echo "  alt         : " . var_export($s->alt, true) . "\n";
    echo "  title       : " . var_export($s->title, true) . "\n";
    echo "  subtitle    : " . var_export($s->subtitle, true) . "\n";
    echo "  button_text : " . var_export($s->button_text, true) . "\n";
    echo "  button_link : " . var_export($s->button_link, true) . "\n";
    echo "\n";
}
echo "Total: " . $slides->count() . "\n";
