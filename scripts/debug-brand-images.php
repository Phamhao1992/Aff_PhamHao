<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'APP_URL=' . config('app.url') . PHP_EOL;
echo 'ASSET_URL=' . var_export(config('app.asset_url'), true) . PHP_EOL;
echo 'FS_PUBLIC_URL=' . config('filesystems.disks.public.url') . PHP_EOL;
echo 'DEFAULT_DISK=' . config('filesystems.default') . PHP_EOL;
echo 'public_storage_is_link=' . (is_link(public_path('storage')) ? 'yes' : 'no') . PHP_EOL;
echo 'public_storage=' . public_path('storage') . PHP_EOL;
echo 'app_public=' . storage_path('app/public') . PHP_EOL;

$brands = App\Models\Brand::query()->orderByDesc('id')->limit(20)->get(['id', 'name', 'image']);
foreach ($brands as $b) {
    $path = (string) $b->image;
    $exists = $path === '' ? 'EMPTY' : (Illuminate\Support\Facades\Storage::disk('public')->exists($path) ? 'YES' : 'NO');
    echo implode(' | ', [
        $b->id,
        $exists,
        $path,
        $b->image_url,
    ]) . PHP_EOL;
}
