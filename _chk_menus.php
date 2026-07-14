$menus = \App\Models\Menu::orderBy('location')->orderBy('sort_order')->get();
foreach ($menus as $m) {
    $prefix = $m->parent_id ? '   └─ ' : '';
    echo "[{$m->location}] {$prefix}{$m->title}  → {$m->url}  " . ($m->is_active ? '' : '(OFF)') . "\n";
}
echo "\nTotal: " . $menus->count() . "\n";
