<?php

namespace App\Filament\Admin\Pages;

use App\Support\AdminSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.admin.pages.system-settings';

    protected static ?string $navigationLabel = 'Cài đặt hệ thống';

    protected static ?string $title = 'Cài đặt hệ thống';

    protected static ?string $navigationGroup = 'Cài đặt';

    protected static ?int $navigationSort = 9999;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) ($user && method_exists($user, 'isAdmin') && $user->isAdmin());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $geminiKeys = AdminSettings::getEncryptedLines('gemini_api_key');
        $apifyTokens = AdminSettings::getEncryptedLines('apify_token');

        $this->form->fill([
            'apify_token' => $apifyTokens !== []
                ? implode("\n", $apifyTokens)
                : '',
            'apify_actor_id' => (string) AdminSettings::get('apify_actor_id', 'aqPbs3KeH9aD8b22w'),
            'apify_blog_image_actor_id' => (string) AdminSettings::get('apify_blog_image_actor_id', 'IOrPh0bOfzJiGxsvk'),
            'traffic_threshold_default' => (int) AdminSettings::get('traffic_threshold_default', 100000),
            'gemini_api_key' => $geminiKeys !== []
                ? implode("\n", $geminiKeys)
                : '',
            'gemini_model' => (string) AdminSettings::get('gemini_model', config('gemini.model', 'gemini-1.5-flash-latest')),
            'gemini_timeout' => (int) AdminSettings::get('gemini_timeout', config('gemini.timeout', 60)),
            'site_contact_email' => (string) AdminSettings::get('site_contact_email', config('mail.from.address', 'contact@example.com')),
            'auto_blog_enabled' => (bool) AdminSettings::get('auto_blog_enabled', true),
            'auto_blog_variant_best' => (bool) AdminSettings::get('auto_blog_variant_best', true),
            'auto_blog_variant_guide' => (bool) AdminSettings::get('auto_blog_variant_guide', true),
            'auto_blog_variant_comparison' => (bool) AdminSettings::get('auto_blog_variant_comparison', true),
            'auto_blog_brand_intro_enabled' => (bool) AdminSettings::get('auto_blog_brand_intro_enabled', true),
            'auto_blog_brand_intro_interval_hours' => (float) AdminSettings::get('auto_blog_brand_intro_interval_hours', 1),
            'auto_blog_global_idea' => (string) AdminSettings::get('auto_blog_global_idea', ''),
            'seo_title_suffix' => (string) AdminSettings::get('seo_title_suffix', '- ' . config('app.name')),
            'seo_meta_description_default' => (string) AdminSettings::get('seo_meta_description_default', 'Best coupons, deals and store reviews.'),
            'seo_og_image_default' => (string) AdminSettings::get('seo_og_image_default', ''),
            'tracking_head_code' => (string) AdminSettings::get('tracking_head_code', ''),
            'tracking_body_code' => (string) AdminSettings::get('tracking_body_code', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Traffic API (Apify)')
                    ->description('Dùng cho lọc traffic theo domain và lấy ảnh blog AI.')
                    ->schema([
                        Textarea::make('apify_token')
                            ->label('Apify token')
                            ->rows(4)
                            ->helperText('Nhập nhiều token, mỗi token một dòng. Hệ thống dùng từ trên xuống; token lỗi sẽ tự chuyển sang token tiếp theo.')
                            ->columnSpanFull(),
                        TextInput::make('apify_actor_id')
                            ->label('Actor ID (traffic)')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('apify_blog_image_actor_id')
                            ->label('Actor ID (ảnh blog AI)')
                            ->required()
                            ->default('IOrPh0bOfzJiGxsvk')
                            ->helperText('Apify Google Images — dùng khi tạo bài giới thiệu cửa hàng hoặc nhập brand/domain.')
                            ->maxLength(100),
                        TextInput::make('traffic_threshold_default')
                            ->label('Ngưỡng traffic mặc định')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('AI Content (Gemini)')
                    ->description('Dùng cho tạo blog AI trong admin và cron.')
                    ->schema([
                        Textarea::make('gemini_api_key')
                            ->label('Gemini API key')
                            ->rows(4)
                            ->helperText('Nhập nhiều key, mỗi key một dòng. Hệ thống dùng từ trên xuống; key lỗi sẽ tự chuyển sang key tiếp theo.')
                            ->columnSpanFull(),
                        Select::make('gemini_model')
                            ->label('Gemini model (ưu tiên)')
                            ->options(function (): array {
                                $models = config('gemini.supported_models', []);
                                $selected = AdminSettings::get('gemini_model', config('gemini.model'));

                                if ($selected !== null && ! in_array((string) $selected, $models, true)) {
                                    $models = array_merge([(string) $selected => (string) $selected], $models);
                                }

                                return array_combine($models, $models);
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Model ưu tiên. Nếu lỗi sẽ tự thử model khác trong danh sách.'),
                        TextInput::make('gemini_timeout')
                            ->label('Timeout (giây)')
                            ->numeric()
                            ->minValue(5)
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Auto Blog')
                    ->description('Thiết lập tạo blog tự động theo ngày, khung giờ và variant.')
                    ->schema([
                        Toggle::make('auto_blog_enabled')
                            ->label('Bật Auto Blog')
                            ->inline(false),
                        Toggle::make('auto_blog_variant_best')
                            ->label('Bật variant: Bài viết lựa chọn tốt nhất')
                            ->inline(false),
                        Toggle::make('auto_blog_variant_guide')
                            ->label('Bật variant: Bài viết hướng dẫn')
                            ->inline(false),
                        Toggle::make('auto_blog_variant_comparison')
                            ->label('Bật variant: Bài viết so sánh')
                            ->inline(false),
                        Toggle::make('auto_blog_brand_intro_enabled')
                            ->label('Bật variant: Bài viết về cửa hàng đang có')
                            ->inline(false),
                        TextInput::make('auto_blog_brand_intro_interval_hours')
                            ->label('Khoảng cách đăng bài (giờ)')
                            ->numeric()
                            ->minValue(0.1)
                            ->step(0.1)
                            ->required(),
                        Textarea::make('auto_blog_global_idea')
                            ->label('Ý tưởng chung cho tất cả bài viết')
                            ->placeholder('VD: Viết tiếng Anh, giọng thân thiện, thêm FAQ cuối bài, khoảng 1000 từ...')
                            ->helperText('Áp dụng cho mọi bài AI (cron + nút tạo bài). Ưu tiên cao hơn luồng mặc định. Ý tưởng trong popup (nếu có) sẽ ghi đè ý tưởng chung này. Để trống = theo luồng mặc định.')
                            ->rows(5)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('SEO mặc định')
                    ->description('Áp dụng cho các trang dùng layout chính.')
                    ->schema([
                        TextInput::make('seo_title_suffix')
                            ->label('Title suffix')
                            ->helperText('Ví dụ: - ReviewHays')
                            ->maxLength(120),
                        TextInput::make('seo_meta_description_default')
                            ->label('Meta description fallback')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('seo_og_image_default')
                            ->label('OpenGraph image mặc định (URL)')
                            ->url()
                            ->maxLength(500),
                    ])
                    ->columns(1),
                Section::make('Tracking / Meta Tag')
                    ->description('Dán mã Google Ads, Google Tag Manager, meta xác minh site. Mã được chèn trên toàn bộ trang public (trang chủ, blog, landing).')
                    ->schema([
                        Textarea::make('tracking_head_code')
                            ->label('Mã trong <head>')
                            ->rows(10)
                            ->placeholder('<script async src="https://www.googletagmanager.com/gtag/js?id=AW-XXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  gtag(\'config\', \'AW-XXXXXXXXX\');
</script>')
                            ->helperText('Google Ads / gtag.js / GTM phần head / thẻ meta verification. Chèn ngay trước </head>.')
                            ->columnSpanFull(),
                        Textarea::make('tracking_body_code')
                            ->label('Mã ngay sau <body> (tuỳ chọn)')
                            ->rows(6)
                            ->placeholder('<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>')
                            ->helperText('Dùng khi Google yêu cầu snippet noscript ngay sau thẻ body mở.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Thiết lập chung')
                    ->schema([
                        TextInput::make('site_contact_email')
                            ->label('Email liên hệ hiển thị ở trang Contact')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu cài đặt')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $apifyTokenInput = (string) ($data['apify_token'] ?? '');
        $newApifyTokens = [];
        foreach (preg_split('/\R/', $apifyTokenInput) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line !== '' && $line !== '********') {
                $newApifyTokens[] = $line;
            }
        }
        AdminSettings::setEncryptedLines('apify_token', $newApifyTokens);

        $geminiApiKeyInput = (string) ($data['gemini_api_key'] ?? '');
        $newKeys = [];
        foreach (preg_split('/\R/', $geminiApiKeyInput) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $newKeys[] = $line;
            }
        }
        AdminSettings::setEncryptedLines('gemini_api_key', $newKeys);

        AdminSettings::set('apify_actor_id', trim((string) ($data['apify_actor_id'] ?? 'aqPbs3KeH9aD8b22w')));
        AdminSettings::set('apify_blog_image_actor_id', trim((string) ($data['apify_blog_image_actor_id'] ?? 'IOrPh0bOfzJiGxsvk')));
        AdminSettings::set('traffic_threshold_default', (int) ($data['traffic_threshold_default'] ?? 100000));
        AdminSettings::set('gemini_model', trim((string) ($data['gemini_model'] ?? config('gemini.model', 'gemini-1.5-flash-latest'))));
        AdminSettings::set('gemini_timeout', max(5, (int) ($data['gemini_timeout'] ?? config('gemini.timeout', 60))));
        AdminSettings::set('site_contact_email', trim((string) ($data['site_contact_email'] ?? config('mail.from.address', 'contact@example.com'))));
        AdminSettings::set('auto_blog_enabled', (bool) ($data['auto_blog_enabled'] ?? true));
        AdminSettings::set('auto_blog_variant_best', (bool) ($data['auto_blog_variant_best'] ?? true));
        AdminSettings::set('auto_blog_variant_guide', (bool) ($data['auto_blog_variant_guide'] ?? true));
        AdminSettings::set('auto_blog_variant_comparison', (bool) ($data['auto_blog_variant_comparison'] ?? true));
        AdminSettings::set('auto_blog_brand_intro_enabled', (bool) ($data['auto_blog_brand_intro_enabled'] ?? true));
        AdminSettings::set('auto_blog_brand_intro_interval_hours', (float) ($data['auto_blog_brand_intro_interval_hours'] ?? 1));
        AdminSettings::set('auto_blog_global_idea', trim((string) ($data['auto_blog_global_idea'] ?? '')));
        AdminSettings::set('seo_title_suffix', trim((string) ($data['seo_title_suffix'] ?? ('- ' . config('app.name')))));
        AdminSettings::set('seo_meta_description_default', trim((string) ($data['seo_meta_description_default'] ?? 'Best coupons, deals and store reviews.')));
        AdminSettings::set('seo_og_image_default', trim((string) ($data['seo_og_image_default'] ?? '')));
        AdminSettings::set('tracking_head_code', (string) ($data['tracking_head_code'] ?? ''));
        AdminSettings::set('tracking_body_code', (string) ($data['tracking_body_code'] ?? ''));

        $this->mount();

        Notification::make()
            ->title('Đã lưu cài đặt hệ thống')
            ->success()
            ->send();
    }
}
