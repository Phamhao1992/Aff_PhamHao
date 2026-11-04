<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CampaignResource\Pages;
use App\Filament\Admin\Resources\CampaignResource\RelationManagers;
use App\Filament\Imports\CampaignImporter;
use App\Filament\Exports\CampaignExporter;
use App\Models\Campaign;
use App\Models\Brand;
use App\Models\User;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    
    protected static ?string $navigationLabel = 'Chiến dịch';
    
    protected static ?string $modelLabel = 'Chiến dịch';
    
    protected static ?string $pluralModelLabel = 'Chiến dịch';
    
    protected static ?string $navigationGroup = 'Quản lý';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin cơ bản')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->label('Cửa hàng')
                            ->relationship(
                                'brand',
                                'name',
                                modifyQueryUsing: function ($query) {
                                    $user = Filament::auth()->user();
                                    $isAdmin = $user && method_exists($user, 'isAdmin') ? (bool) $user->{'isAdmin'}() : false;
                                    $userId = $isAdmin ? null : ($user?->id);

                                    return $query->when(
                                        $userId,
                                        fn (Builder $q) => $q->where('brands.user_id', $userId),
                                    );
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, string $operation) {
                                if ($state) {
                                    $brand = Brand::find($state);
                                    if ($brand) {
                                        if (empty($get('title'))) {
                                            $set('title', $brand->name);
                                        }
                                        if ($operation === 'create' && empty($get('slug'))) {
                                            $set('slug', \Illuminate\Support\Str::slug($get('title') ?: $brand->name));
                                        }
                                    }
                                }
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Tên cửa hàng')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set(
                                        'slug',
                                        \Illuminate\Support\Str::slug($state)
                                    )),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                            ])
                            ->helperText('Chọn cửa hàng hoặc tạo mới'),
                        Forms\Components\TextInput::make('title')
                            ->label('Tiêu đề')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, string $operation) {
                                if ($operation === 'create') {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule->whereNull('deleted_at')
                            )
                            ->helperText('Duy nhất toàn hệ thống. URL landing: /store/{slug}. Slug chỉ tự tạo khi thêm chiến dịch mới.'),
                        Forms\Components\TextInput::make('affiliate_url')
                            ->label('URL Affiliate')
                            ->required()
                            ->url()
                            ->helperText('URL affiliate đầy đủ với tham số tracking'),
                        Forms\Components\TextInput::make('link_network')
                            ->label('Link Network')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\RichEditor::make('intro')
                            ->label('Giới thiệu')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strikeThrough',
                                'link',
                                'image',
                                'orderedList',
                                'bulletList',
                                'blockquote',
                                'codeBlock',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull()
                            ->extraInputAttributes([
                                'style' => 'min-height: 200px;',
                            ])
                    ])->columns(2),
                
                Forms\Components\Section::make('Cài đặt chiến dịch')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'draft' => 'Bản nháp',
                                'active' => 'Hoạt động',
                                'paused' => 'Tạm dừng',
                            ])
                            ->required()
                            ->default('active'),
                        Forms\Components\Select::make('type')
                            ->label('Loại chiến dịch')
                            ->options([
                                'coupon' => 'Coupon',
                                'key' => 'Key',
                            ])
                            ->required()
                            ->default('coupon')
                            ->live(),
                        Forms\Components\Select::make('template')
                            ->label('Giao diện (Template)')
                            ->options(function (Forms\Get $get) {
                                $type = $get('type') ?? 'coupon';
                                if ($type === 'key') {
                                    return [
                                        'template_key' => 'Template 2 (Key)',
                                    ];
                                }
                                return [
                                    'template1' => 'Template 1 (Coupon)',
                                    'template2' => 'Template 2 (Coupon)',
                                    'template3' => 'Template 3 (Coupon)',
                                    'template4' => 'Template 4 (Coupon)',
                                ];
                            })
                            ->required()
                            ->default(function (Forms\Get $get) {
                                return $get('type') === 'key' ? 'template_key' : 'template1';
                            })
                            ->helperText('Chọn template landing page'),
                    ])->columns(3),
                
                Forms\Components\Section::make('Hình ảnh')
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->label('Logo chiến dịch')
                            ->image()
                            ->directory('campaigns/logo')
                            ->maxSize(2048)
                            ->helperText('Logo sẽ hiển thị trên landing page'),
                        Forms\Components\FileUpload::make('cover_image')
                            ->label('Ảnh bìa')
                            ->image()
                            ->directory('campaigns/cover')
                            ->maxSize(5120)
                            ->helperText('Ảnh bìa chính của chiến dịch')
                            ->visible(fn (Forms\Get $get) => ($get('type') ?? 'coupon') === 'coupon'),
                        Forms\Components\FileUpload::make('product_images')
                            ->label('Ảnh sản phẩm')
                            ->image()
                            ->directory('campaigns/products')
                            ->maxSize(5120)
                            ->multiple()
                            ->maxFiles(10)
                            ->helperText('Có thể upload nhiều ảnh sản phẩm (tối đa 10 ảnh)')
                            ->visible(fn (Forms\Get $get) => ($get('type') ?? 'coupon') === 'coupon'),
                        Forms\Components\FileUpload::make('background_image')
                            ->label('Ảnh nền (Key)')
                            ->image()
                            ->directory('campaigns/background')
                            ->maxSize(10240)
                            ->helperText('Ảnh nền cho landing page Key (chỉ dành cho chiến dịch Key)')
                            ->visible(fn (Forms\Get $get) => ($get('type') ?? 'coupon') === 'key'),
                        Forms\Components\FileUpload::make('key_product_images')
                            ->label('Ảnh sản phẩm (Key)')
                            ->image()
                            ->directory('campaigns/key-products')
                            ->maxSize(5120)
                            ->multiple()
                            ->maxFiles(10)
                            ->helperText('Ảnh sản phẩm cho landing page Key (tối đa 10 ảnh)')
                            ->visible(fn (Forms\Get $get) => ($get('type') ?? 'coupon') === 'key'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                
                
                Forms\Components\Section::make('Mã giảm giá')
                    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'coupon') === 'coupon')
                    ->schema([
                        Forms\Components\Repeater::make('couponItems')
                            ->relationship('couponItems')
                            ->orderColumn('sort_order')
                            ->label('Danh sách mã giảm giá')
                            ->visible(fn (Forms\Get $get) => ($get('type') ?? 'coupon') === 'coupon')
                            ->schema(function () {
                                $descriptionTemplates = [
                                    'Get up to an additional :offer off the entire website when you check out.',
                                    'Extra :offer OFF Storewide – Limited Time',
                                    'Save :offer OFF on All Items at Checkout',
                                    'Up to :offer OFF Selected Items',
                                    'Extra :offer OFF Each Item at Checkout',
                                    'Free Shipping on All Orders',
                                    'Free Shipping on Orders Over :offer',
                                    'Buy 2 Get 1 Free – Limited Offer',
                                    'Extra Savings Applied at Checkout',
                                    'Exclusive Coupon – Limited Time Only',
                                    'Extra :offer OFF Automatically at Checkout',
                                    'Bonus :offer OFF with Promo Code',
                                    'Additional :offer OFF on Sale Items',
                                    'Extra Discount on All Products',
                                    'Flash Sale – Extra :offer OFF Today',
                                    'Limited Stock – Extra :offer OFF',
                                    'Seasonal Sale – Up to :offer OFF',
                                    'Special Deal – Save More at Checkout',
                                    'Extra :offer OFF on Bestsellers',
                                    'New Customer Deal – Extra :offer OFF',
                                    'Extra :offer OFF on Your Entire Order',
                                    'Discounts up to :offer',
                                ];

                                return [
                                    Forms\Components\TextInput::make('code')
                                        ->label('Mã giảm giá')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('offer')
                                        ->label('Offer')
                                        ->maxLength(50)
                                        ->live(onBlur: false)
                                        ->helperText('Nhập offer để xem gợi ý mô tả'),
                                    Forms\Components\Select::make('description_suggestion')
                                        ->label('Gợi ý mô tả')
                                        ->searchable()
                                        ->preload()
                                        ->options(function (Get $get) use ($descriptionTemplates): array {
                                            $rawOffer = trim((string) $get('offer'));

                                            if ($rawOffer === '') {
                                                return [];
                                            }

                                            // Chuẩn hoá Offer: chỉ giữ phần chứa số + % hoặc $, ví dụ 10%, $20, 20$
                                            $cleanSource = strtolower($rawOffer);
                                            $cleanSource = preg_replace('/off/i', '', $cleanSource);

                                            $match = null;
                                            if (preg_match('/([\$]?\d+%?|\d+[\$])/i', $cleanSource, $m)) {
                                                $match = trim($m[0]);
                                            }

                                            if (empty($match)) {
                                                return [];
                                            }

                                            // Nếu dạng "20$" thì vẫn giữ nguyên như user nhập
                                            $offer = $match;

                                            return collect($descriptionTemplates)
                                                ->mapWithKeys(function (string $template) use ($offer) {
                                                    $text = str_replace(':offer', $offer, $template);
                                                    return [$text => $text];
                                                })
                                                ->toArray();
                                        })
                                        ->live()
                                        ->visible(fn (Get $get) => trim((string) $get('offer')) !== '')
                                        ->helperText('Chọn một gợi ý mô tả từ danh sách')
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            if (! empty($state)) {
                                                // Khi chọn gợi ý thì đổ vào trường mô tả chính, cho phép user sửa tiếp
                                                $set('description', $state);
                                                // Xoá lựa chọn gợi ý để tránh nhầm
                                                $set('description_suggestion', null);
                                            }
                                        }),
                                    Forms\Components\TextInput::make('description')
                                        ->label('Mô tả ngắn')
                                        ->maxLength(500)
                                        ->helperText('Mô tả chi tiết về mã giảm giá (có thể chọn từ gợi ý ở trên)'),
                                ];
                            })
                            // Mỗi item (coupon) là 1 "thẻ", các thẻ xếp lưới 3 cột
                            ->grid(3)
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['code'] ?? null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->importer(CampaignImporter::class)
                    ->job(\App\Jobs\ImportCsvWithNullUser::class)
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray'),
            ])
            ->columns([
                Tables\Columns\ImageColumn::make('brand.image')
                    ->label('Logo')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-brand.svg')),
                Tables\Columns\TextColumn::make('title')
                    ->label('Tên chiến dịch')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->wrap(),
                Tables\Columns\TextColumn::make('landing_url')
                    ->label('URL')
                    ->state(function ($record) {
                        if (! $record->slug) {
                            return '';
                        }

                        return url(route('landing.show', ['slug' => $record->slug]));
                    })
                    ->copyable()
                    ->copyMessage('Đã copy URL')
                    ->copyMessageDuration(1500)
                    ->limit(45)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Cửa hàng')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Hoạt động',
                        'paused' => 'Tạm dừng',
                        'draft' => 'Bản nháp',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('template')
                    ->label('Template')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Ngày cập nhật')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->visible(function (): bool {
                        $user = Filament::auth()->user();
                        return $user && method_exists($user, 'isAdmin') ? (bool) $user->{'isAdmin'}() : false;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $userId): Builder => $q->whereHas('brand', fn (Builder $b) => $b->where('user_id', $userId)),
                        );
                    }),
                Tables\Filters\SelectFilter::make('brand')
                    ->label('Lọc theo cửa hàng')
                    ->relationship(
                        'brand',
                        'name',
                        modifyQueryUsing: function (Builder $query) {
                            $user = Filament::auth()->user();
                            $isAdmin = $user && method_exists($user, 'isAdmin') ? (bool) $user->{'isAdmin'}() : false;
                            $userId = $isAdmin ? null : ($user?->id);

                            return $query->when(
                                $userId,
                                fn (Builder $q) => $q->where('brands.user_id', $userId),
                            );
                        }
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Hoạt động',
                        'paused' => 'Tạm dừng',
                        'draft' => 'Bản nháp',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->label('Ngày tạo')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Từ ngày'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Đến ngày'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('has_offers')
                    ->label('Có Offer / Mã giảm giá')
                    ->query(function (Builder $query) {
                        return $query
                            ->whereNotNull('coupon_code')
                            ->orWhereHas('couponItems');
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->tooltip('Sửa')
                    ->color('primary'),
                Tables\Actions\ReplicateAction::make()
                    ->label('')
                    ->icon('heroicon-o-document-duplicate')
                    ->tooltip('Nhân bản')
                    ->color('warning')
                    ->mutateRecordDataUsing(function (array $data, Campaign $record): array {
                        $baseTitle = $record->title;
                        $slugPart = $record->slug;
                        $segments = array_values(array_filter(explode('/', (string) $slugPart)));
                        if (count($segments) >= 2) {
                            $slugPart = end($segments);
                        }

                        $title = $baseTitle . '-copy';
                        $newSlug = $slugPart . '-copy';
                        $n = 0;
                        while (Campaign::where('title', $title)->exists()
                            || Campaign::where('slug', $newSlug)->whereNull('deleted_at')->exists()) {
                            $n++;
                            $title = $baseTitle . '-copy' . $n;
                            $newSlug = $slugPart . '-copy' . $n;
                        }
                        $data['title'] = $title;
                        $data['slug'] = $newSlug;

                        return $data;
                    }),
                Tables\Actions\Action::make('copy_landing_link')
                    ->label('')
                    ->icon('heroicon-o-clipboard-document')
                    ->tooltip('Copy link landing')
                    ->color('gray')
                    ->visible(fn (Campaign $record): bool => filled($record->slug))
                    ->action(function (Campaign $record, $livewire): void {
                        $fullUrl = url(route('landing.show', ['slug' => $record->slug]));

                        $livewire->js('navigator.clipboard.writeText('.json_encode($fullUrl).')');

                        \Filament\Notifications\Notification::make()
                            ->title('Đã copy link landing')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view_landing')
                    ->label('')
                    ->url(fn ($record) => $record->slug ? route('landing.show', ['slug' => $record->slug]) : '#')
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->tooltip('Xem landing page')
                    ->color('success'),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->tooltip('Xóa chiến dịch')
                    ->color('danger'),
                Tables\Actions\RestoreAction::make()
                    ->label('')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->tooltip('Khôi phục')
                    ->color('info'),
                Tables\Actions\ForceDeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->tooltip('Xóa vĩnh viễn')
                    ->color('danger'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(CampaignExporter::class)
                        ->job(\App\Jobs\PrepareCsvExportPreserveSession::class)
                        ->label('Xuất CSV')
                        ->icon('heroicon-o-document-arrow-down')
                        ->formats([ExportFormat::Csv])
                        ->columnMapping(false)
                        ->fileName(fn (): string => 'campaigns-' . now()->format('Y-m-d-His')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();
        $isAdmin = $user && method_exists($user, 'isAdmin') ? (bool) $user->{'isAdmin'}() : false;
        $userId = $isAdmin ? null : ($user?->id);

        $alert = request()->query('alert');

        return parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->when(
                $userId,
                fn (Builder $query) => $query->whereHas(
                    'brand',
                    fn (Builder $brandQuery) => $brandQuery->where('user_id', $userId),
                ),
            )
            ->when($alert === 'missing_logo', function (Builder $query) {
                $query->whereHas('brand', function (Builder $b) {
                    $b->whereNull('image')
                        ->orWhere('image', '');
                });
            })
            ->when($alert === 'missing_intro', function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->whereNull('intro')
                        ->orWhere('intro', '');
                });
            })
            ->when($alert === 'missing_category', function (Builder $query) {
                $query->whereHas('brand', function (Builder $b) {
                    $b->whereNull('category_id');
                });
            })
            ->when($alert === 'missing_affiliate', function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->whereNull('affiliate_url')
                        ->orWhere('affiliate_url', '');
                });
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
