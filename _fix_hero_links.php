$slides = \App\Models\HeroSlide::whereNotNull('button_link')->where('button_link', '!=', '')->get();
$fixed = 0;
foreach ($slides as $s) {
    $orig = $s->button_link;
    $link = trim($orig);
    if ($link === '') continue;
    // Skip absolute urls / mailto / tel / anchors / already-leading-slash
    if (preg_match('#^(https?:)?//#i', $link) || preg_match('#^(mailto:|tel:|#)#i', $link) || $link[0] === '/') {
        continue;
    }
    // Add leading /
    $new = '/' . $link;
    $s->button_link = $new;
    $s->save();
    echo "id={$s->id}  '{$orig}'  →  '{$new}'\n";
    $fixed++;
}
echo "\nFixed: {$fixed}\n";
