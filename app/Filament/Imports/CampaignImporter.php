<?php

namespace App\Filament\Imports;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Coupon;
use App\Services\LogoFromDomainService;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Csv\Info;
use League\Csv\Reader as CsvReader;
use League\Csv\Statement;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CampaignImporter extends Importer
{
    protected static ?string $model = Campaign::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('category')
                ->label('Danh mục')
                ->rules(['nullable', 'max:255'])
                ->example('Thời trang')
                ->exampleHeader('Danh mục')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('brand')
                ->label('Cửa hàng (tên hoặc ID)')
                ->guess(['Cửa hàng'])
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('Nexalabs Shop')
                ->exampleHeader('Cửa hàng')
                ->fillRecordUsing(function (Campaign $record, string $state, array $data): void {
                    static::fillBrandForRecord($record, $state, $data);
                }),
            ImportColumn::make('domain')
                ->label('Domain (lấy logo)')
                ->guess(['Domain', 'Domain (lấy logo)'])
                ->rules(['nullable', 'max:255'])
                ->example('nexalabs.com')
                ->exampleHeader('Domain (lấy logo)')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('title')
                ->label('Tiêu đề')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('Black Friday Sale 2025')
                ->exampleHeader('Tiêu đề'),
            ImportColumn::make('slug')
                ->label('Slug')
                ->rules(['nullable', 'max:255'])
                ->helperText('Để trống sẽ tự tạo từ tiêu đề')
                ->example('black-friday-sale-2025')
                ->exampleHeader('Slug'),
            ImportColumn::make('intro')
                ->label('Giới thiệu (HTML)')
                ->guess(['Giới thiệu'])
                ->rules(['nullable', 'max:65535'])
                ->example('<p>Giới thiệu chiến dịch</p>')
                ->exampleHeader('Giới thiệu')
                ->fillRecordUsing(function (Campaign $record, ?string $state) {
                    if ($state === null || trim($state) === '') {
                        $record->intro = null;
                        return;
                    }
                    $raw = preg_replace('/\r\n|\r/', "\n", (string) $state);
                    // Nếu là plain text (có xuống dòng trong CSV): chuyển thành <br> để form Giới thiệu hiển thị đúng
                    if (! preg_match('/<[a-z][a-z0-9]*\b/i', $raw)) {
                        $raw = nl2br(e($raw));
                    }
                    $record->intro = $raw;
                }),
            ImportColumn::make('status')
                ->label('Trạng thái')
                ->rules(['required', 'in:draft,active,paused'])
                ->example('active')
                ->exampleHeader('Trạng thái'),
                ImportColumn::make('type')
                ->label('Loại chiến dịch')
                ->fillRecordUsing(function (Campaign $record, ?string $state) {
                    $record->type = $state ?: 'coupon';
                }),
            ImportColumn::make('template')
                ->label('Template')
                ->fillRecordUsing(function (Campaign $record, ?string $state, array $data) {
                    $type = $data['type'] ?? 'coupon';
            
                    $record->template = $state
                        ?: ($type === 'key' ? 'template_key' : 'template1');
                }),
            ImportColumn::make('affiliate_url')
                ->label('URL Affiliate')
                ->requiredMapping()
                ->rules(['required', 'url'])
                ->example('https://example.com/?ref=xxx')
                ->exampleHeader('URL Affiliate'),
            ImportColumn::make('link_network')
                ->label('Link Network')
                ->guess(['Link Network'])
                ->rules(['nullable', 'max:255'])
                ->example('https://network.example.com/offer')
                ->exampleHeader('Link Network'),
            ImportColumn::make('email')
                ->label('Email')
                ->guess(['Email'])
                ->rules(['nullable', 'email', 'max:255'])
                ->example('contact@example.com')
                ->exampleHeader('Email'),
            ImportColumn::make('coupon_codes')
                ->label('Mã giảm giá (phân cách bằng xuống hàng)')
                ->guess(['Mã giảm giá'])
                ->rules(['nullable', 'max:2000'])
                ->example("SAVE10\nNO")
                ->exampleHeader('Mã giảm giá')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('coupon_offers')
                ->label('Offer (phân cách bằng xuống hàng)')
                ->guess(['Offer'])
                ->rules(['nullable', 'max:2000'])
                ->example("10%\nFree Shipping")
                ->exampleHeader('Offer')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('coupon_descriptions')
                ->label('Mô tả mã giảm giá (phân cách bằng xuống hàng)')
                ->guess(['Mô tả'])
                ->rules(['nullable', 'max:5000'])
                ->example("NO\nNO")
                ->exampleHeader('Mô tả')
                ->helperText('Nhập "NO" để tự tạo mô tả ngẫu nhiên theo Offer và loại coupon (Get Code / Get Deal / Free Shipping).')
                ->fillRecordUsing(fn () => null),
        ];
    }

    public function resolveRecord(): ?Campaign
    {
        return new Campaign();
    }

    public function __invoke(array $row): void
    {
        app()->instance('current_import_id', $this->import->getKey());
        try {
            parent::__invoke($row);
        } catch (RowImportFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RowImportFailedException(
                strlen($e->getMessage()) > 200 ? substr($e->getMessage(), 0, 200) . '...' : $e->getMessage()
            );
        } finally {
            app()->forgetInstance('current_import_id');
        }
    }

    protected function beforeSave(): void
    {
        $this->record->import_id = $this->import->getKey();
        
        // Set type mặc định nếu chưa có
        if (empty($this->record->type)) {
            $this->record->type = 'coupon';
        }
        
        // Set template dựa trên type nếu chưa có hoặc không hợp lệ
        if (empty($this->record->template) || !in_array($this->record->template, ['template1', 'template2', 'template3', 'template4', 'template_key'])) {
            $this->record->template = $this->record->type === 'key' ? 'template_key' : 'template1';
        }
        
        $this->record->slug = Campaign::normalizeCampaignSlug(
            $this->record->slug ?: Str::slug($this->record->title),
            $this->record->title ?? '',
            $this->record->id
        );
    }

    protected function afterSave(): void
    {
        $this->createCoupons();
    }

    public static function fillBrandForRecord(Campaign $record, string $state, array $data): void
    {
        $categoryName = trim((string) ($data['category'] ?? ''));
        $domain = trim((string) ($data['domain'] ?? ''));

        $category = static::resolveOrCreateCategory($categoryName);
        $brand = static::resolveOrCreateBrand($state, $category, $domain);

        $record->brand_id = $brand->id;
    }

    protected function createCoupons(): void
    {
        $codes = $this->parseListByNewline($this->data['coupon_codes'] ?? '');
        $offers = $this->parseListByNewline($this->data['coupon_offers'] ?? '');
        $descriptions = $this->parseListByNewline($this->data['coupon_descriptions'] ?? '');

        $count = max(count($codes), count($offers), count($descriptions));
        if ($count === 0) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $code = $codes[$i] ?? '';
            if (strtolower(trim($code)) === 'no') {
                $code = '';
            }
            $offer = $offers[$i] ?? '';
            $description = $descriptions[$i] ?? '';
            if (strtolower(trim($description)) === 'no') {
                $description = $this->generateCouponDescription($offer, $code !== '');
            }

            Coupon::create([
                'campaign_id' => $this->record->id,
                'code' => $code,
                'offer' => $offer,
                'description' => $description,
                'sort_order' => $i + 1,
            ]);
        }
    }

    protected function generateCouponDescription(string $offer, bool $hasCode): string
    {
        $offerTrim = trim($offer);
        $offerLower = strtolower($offerTrim);

        $freeShippingTemplates = [
            'Free Shipping on All Orders',
            'Enjoy Free Shipping Storewide',
            'Get Free Delivery on Selected Items',
        ];

        $codeTemplates = [
            'Get [offer] OFF on Selected Items',
            'Enjoy [offer] OFF Storewide Today',
            'Take Extra 20% OFF Clearance Deals',
            'Get [offer] OFF Your First Purchase',
            'Apply Code to Get 10% OFF at Checkout',
            'Extra [offer] OFF When You Pay Online',
            'Limited Time: [offer] OFF Sitewide',
            'Instantly Save [offer] on Your Order',
        ];

        $dealTemplates = [
            'Up to [offer] OFF Storewide Offers',
            'Limited Time Deals – Save Big Today',
            'Flash Deals – Prices Dropped on Selected Items',
            'Check Out Today’s Best Deals & Offers',
            'Enjoy Additional Discounts on Sale Items',
        ];

        $isFreeShipping = $offerLower === 'free shipping' || str_contains($offerLower, 'free shipping');
        $templates = $isFreeShipping
            ? $freeShippingTemplates
            : ($hasCode ? $codeTemplates : $dealTemplates);

        $template = Arr::random($templates);
        return str_replace('[offer]', $offerTrim, $template);
    }

    /** Phân cách bằng xuống hàng (\\n) thay vì , hoặc ; */
    protected function parseListByNewline(?string $value): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        return array_values(array_filter(array_map('trim', $lines)));
    }

    public static function resolveOrCreateCategory(string $name): Category
    {
        $importId = app()->bound('current_import_id') ? app('current_import_id') : null;

        if (empty($name)) {
            return Category::first() ?? Category::create([
                'name' => 'Uncategorized',
                'slug' => 'uncategorized',
                'is_active' => true,
                'import_id' => $importId,
            ]);
        }

        $userId = \Illuminate\Support\Facades\Auth::id();
        $user = \App\Models\User::find($userId);
        $userCode = $user?->code ?? '00000';
        
        $baseSlug = Str::slug($name);
        $fullSlug = "{$userCode}/{$baseSlug}";
        
        // Tìm category theo slug đầy đủ hoặc name trong cùng user
        $category = Category::where(function ($q) use ($fullSlug, $name, $userId) {
            $q->where('slug', $fullSlug)
              ->orWhere(function ($q2) use ($name, $userId) {
                  $q2->where('name', $name)->where('user_id', $userId);
              });
        })->first();

        return $category ?? Category::create([
            'name' => $name,
            'slug' => $fullSlug,
            'is_active' => true,
            'import_id' => $importId,
            'user_id' => $userId,
        ]);
    }

    public static function resolveOrCreateBrand(string $nameOrId, Category $category, string $domain = ''): Brand
    {
        $name = trim($nameOrId);
        if (empty($name)) {
            throw new RowImportFailedException('Tên cửa hàng là bắt buộc.');
        }

        if (is_numeric($name)) {
            $brand = Brand::find((int) $name);
            if ($brand) {
                // Cập nhật domain nếu được truyền vào và brand chưa có domain
                $cleanDomain = LogoFromDomainService::cleanDomain($domain);
                if ($cleanDomain && empty($brand->domain)) {
                    $brand->domain = $cleanDomain;
                    $brand->save();
                }
                return $brand;
            }
            throw new RowImportFailedException("Không tìm thấy cửa hàng với ID: {$name}");
        }

        $userId = \Illuminate\Support\Facades\Auth::id();
        $user = \App\Models\User::find($userId);
        $userCode = $user?->code ?? '00000';

        $baseSlug = Str::slug($name);

        // Tìm brand theo slug đầy đủ hoặc name trong cùng user
        $brand = Brand::where(function ($q) use ($baseSlug, $name, $userId, $userCode) {
            $q->where('slug', "{$userCode}/{$baseSlug}")
              ->orWhere(function ($q2) use ($name, $userId) {
                  $q2->where('name', $name)->where('user_id', $userId);
              });
        })->first();

        // Brand đã tồn tại: dùng nguyên trạng, không sửa (tránh ảnh hưởng dữ liệu cũ)
        if ($brand) {
            return $brand;
        }

        $imagePath = null;
        $cleanDomain = LogoFromDomainService::cleanDomain($domain);
        if (! empty($domain)) {
            try {
                $imagePath = LogoFromDomainService::fetchAndSave($domain, $userCode);
            } catch (\Throwable) {
                // Bỏ qua lỗi logo
            }
        }

        $importId = app()->bound('current_import_id') ? app('current_import_id') : null;

        // Tạo slug không trùng
        $uniqueSlug = Brand::ensureUniqueBrandSlug($baseSlug);

        return Brand::create([
            'name' => $name,
            'slug' => "{$userCode}/{$uniqueSlug}",
            'domain' => $cleanDomain,
            'category_id' => $category->id,
            'image' => $imagePath,
            'approved' => true,
            'import_id' => $importId,
            'user_id' => $userId,
        ]);
    }

    public function getJobConnection(): ?string
    {
        return 'sync'; // sync: import chạy ngay. database: cần php artisan queue:work để hiển thị tiến trình %
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $success = (int) $import->successful_rows;
        $failed = $import->getFailedRowsCount();

        $body = 'Đã upload thành công ' . number_format($success) . ' chiến dịch.';
        if ($failed > 0) {
            $body .= ' Không import được ' . number_format($failed) . ' chiến dịch. Bạn có thể tải file CSV chứa các dòng lỗi.';
        }

        return $body;
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        $success = (int) $import->successful_rows;
        $failed = $import->getFailedRowsCount();

        return $failed > 0
            ? "Import: {$success} thành công, {$failed} thất bại"
            : "Import hoàn tất: {$success} chiến dịch";
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Placeholder::make('import_preview')
                ->label('')
                ->content(function (Get $get) {
                    $file = Arr::first((array) ($get('file') ?? []));
                    if (! $file instanceof TemporaryUploadedFile) {
                        return 'Chọn file CSV để xem trước số lượng record chuẩn bị import.';
                    }
                    $path = $file->getRealPath();
                    if (! $path || ! is_readable($path)) {
                        return 'Không thể đọc file.';
                    }
                    try {
                        $reader = CsvReader::createFromPath($path);
                        $delimiterStats = Info::getDelimiterStats($reader, [',', ';', '|', "\t"], 10);
                        $delimiter = ',';
                        if (is_array($delimiterStats) && $delimiterStats !== []) {
                            $best = array_key_first($delimiterStats);
                            if ($best !== null && $best !== '') {
                                $delimiter = $best;
                            }
                        }

                        $reader1 = CsvReader::createFromPath($path)->setDelimiter($delimiter)->setHeaderOffset(0);
                        $result = (new Statement)->process($reader1);
                        $rows = iterator_to_array($result);
                        $count = count($rows);

                        $seenTitles = [];
                        $seenSlugs = [];
                        $duplicateCount = 0;
                        $existingTitleCount = 0;
                        $existingSlugCount = 0;
                        $titleKey = null;
                        $slugKey = null;
                        $headers = $reader1->getHeader();

                        foreach ($headers as $header) {
                            $headerLower = strtolower($header);
                            if ($headerLower === 'tiêu đề' || $headerLower === 'title') {
                                $titleKey = $header;
                            }
                            if ($headerLower === 'slug') {
                                $slugKey = $header;
                            }
                        }

                        $existingTitles = [];
                        $existingSlugs = [];
                        if ($titleKey || $slugKey) {
                            $query = Campaign::query();
                            if ($titleKey) {
                                $existingTitles = $query->pluck('title')->map(fn ($t) => strtolower(trim((string) $t)))->filter()->unique()->values()->all();
                            }
                            if ($slugKey) {
                                $existingSlugs = Campaign::pluck('slug')->map(fn ($s) => strtolower(trim((string) $s)))->filter()->unique()->values()->all();
                            }
                        }

                        foreach ($rows as $row) {
                            $title = trim((string) ($titleKey ? ($row[$titleKey] ?? '') : ''));
                            $slug = trim((string) ($slugKey ? ($row[$slugKey] ?? '') : Str::slug($title)));

                            $titleLower = strtolower($title);
                            $slugLower = strtolower($slug);

                            $slugCount = $seenSlugs[$slugLower] ?? 0;
                            $titleCount = $seenTitles[$titleLower] ?? 0;

                            if ($slugCount > 0 || $titleCount > 0) {
                                $duplicateCount++;
                            }

                            $seenTitles[$titleLower] = $titleCount + 1;
                            $seenSlugs[$slugLower] = $slugCount + 1;

                            if ($titleLower !== '' && in_array($titleLower, $existingTitles, true)) {
                                $existingTitleCount++;
                            }
                            if ($slugLower !== '' && in_array($slugLower, $existingSlugs, true)) {
                                $existingSlugCount++;
                            }
                        }

                        $existingCount = max($existingTitleCount, $existingSlugCount);

                        return view('filament.imports.import-preview', [
                            'count' => $count,
                            'duplicateCount' => $duplicateCount,
                            'existingCount' => $existingCount,
                        ])->render();
                    } catch (\Throwable $e) {
                        return 'Không thể đọc file CSV. ' . $e->getMessage();
                    }
                })
                ->visible(fn (Get $get): bool => Arr::first((array) ($get('file') ?? [])) instanceof TemporaryUploadedFile),
        ];
    }
}
