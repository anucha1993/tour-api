$menus = \App\Models\Menu::whereNotNull('url')->where('url', '!=', '')->get();
$fixed = 0;
foreach ($menus as $m) {
    $orig = $m->url;
    $url = trim($orig);
    if ($url === '') continue;
    if (preg_match('~^(https?:)?//~i', $url) || preg_match('~^(mailto:|tel:|#)~i', $url) || $url[0] === '/') {
        continue;
    }
    $new = '/' . $url;
    $m->url = $new;
    $m->save();
    echo "id={$m->id}  [{$m->location}]  '{$orig}'  →  '{$new}'\n";
    $fixed++;
}
echo "\nFixed: {$fixed}\n";
