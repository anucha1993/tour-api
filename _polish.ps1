$path = 'd:\Programing\tour-manager\tour-backend\src\app\dashboard\website\contact-popup\page.tsx'
$c = [System.IO.File]::ReadAllText($path, [System.Text.Encoding]::UTF8)
$orig = $c
# 1) Section wrapper: rounded-lg border p-5 -> rounded-xl border-gray-200 p-5
$c = $c -replace 'bg-white rounded-lg border p-5', 'bg-white rounded-xl border border-gray-200 p-5'
# 2) Input/select borders lighter
$c = $c -replace 'border border-gray-300 rounded-lg', 'border border-gray-200 rounded-lg'
# 3) Focus ring also updates border (only where focus:border not already set)
$c = $c -replace 'focus:ring-2 focus:ring-blue-500(?!\s+focus:border)', 'focus:ring-2 focus:ring-blue-500 focus:border-blue-500'
# 4) Section h2 with icon: text-sm -> text-base + gray-700 -> gray-800 + add pb-2 border-b
$c = $c -replace '<h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2">', '<h2 className="text-base font-semibold text-gray-800 flex items-center gap-2 pb-2 border-b border-gray-100 w-full">'
# 5) Bare section h2 without icon
$c = $c -replace '<h2 className="text-sm font-semibold text-gray-700">', '<h2 className="text-base font-semibold text-gray-800 pb-2 border-b border-gray-100">'
[System.IO.File]::WriteAllText($path, $c, [System.Text.UTF8Encoding]::new($false))
Write-Host ("Changed: " + ($c -ne $orig))
