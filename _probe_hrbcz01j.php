// Search DB broadly for any tour resembling this route (Harbin/Changbaishan Snow Town) from wholesaler 3
$tours = App\Models\Tour::where('wholesaler_id', 3)
    ->where(function($q) {
        $q->where('title', 'like', '%ฉางไป๋ซาน%')
          ->orWhere('title', 'like', '%หิมะ%')
          ->orWhere('title', 'like', '%Snow Town%')
          ->orWhere('wholesaler_tour_code', 'like', '%HRB%');
    })
    ->get(['id','tour_code','wholesaler_tour_code','title','status','sync_status','last_synced_at','created_at','deleted_at']);
foreach ($tours as $t) {
    echo json_encode($t->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
}
if ($tours->isEmpty()) echo "no matches\n";

// Also check with trashed (soft deletes) if the model uses SoftDeletes
echo "\nUses SoftDeletes: " . (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(App\Models\Tour::class)) ? 'YES' : 'NO') . "\n";
