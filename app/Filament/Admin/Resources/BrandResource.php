<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BrandResource\Pages;
use App\Filament\Admin\Resources\BrandResource\RelationManagers;
use App\Models\Brand;
use App\Services\LogoFromDomainService;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationLabel = 'Cửa hàng';
    
    protected static ?string $modelLabel = 'Cửa hàng';
    
    protected static ?string $pluralModelLabel = 'Cửa hàng';
    
    protected static ?string $navigationGroup = 'Quản lý';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin cửa hàng')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Danh mục')
                            ->relationship(
                                'category',
                                'name',
                                modifyQueryUsing: function ($query) {
                                    $user = Filament::auth()->user();
                                    $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
                                    $userId = $isAdmin ? null : ($user?->id);

                                    return $query->when(
                                        $userId,
                                        fn (Builder $q) => $q->where('categories.user_id', $userId),
                                    );
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Tên danh mục')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $user = \Filament\Facades\Filament::auth()->user();
                                        $userCode = $user?->code ?? '00000';
                                        $baseSlug = \Illuminate\Support\Str::slug($state);
                                        $set('slug', "{$userCode}/{$baseSlug}");
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                            ])
                            ->helperText('Chọn danh mục hoặc tạo mới'),
                        Forms\Components\TextInput::make('name')
                            ->label('Tên cửa hàng')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, $livewire, ?Brand $record) {
                                $baseSlug = \Illuminate\Support\Str::slug($state);
                                $user = \Filament\Facades\Filament::auth()->user();
                                $userCode = $user?->code ?? '00000';

                                // Nếu đang edit và brand có slug, giữ nguyên phần userCode prefix
                                if ($record && $record->slug) {
                                    $parts = explode('/', $record->slug, 2);
                                    if (count($parts) === 2) {
                                        $userCode = $parts[0];
                                        $baseSlug = $parts[1];
                                    }
                                }

                                $uniqueSlug = Brand::ensureUniqueBrandSlug($baseSlug, $record?->id);
                                $set('slug', "{$userCode}/{$uniqueSlug}");
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule->whereNull('deleted_at')
                            )
                            ->helperText('Tự động tạo từ tên'),
                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->maxLength(255)
                            ->placeholder('example.com')
                            ->helperText('Nhập domain để tự động lấy logo (ví dụ: amazon.com)')
                            ->live(onBlur: true),
                        Forms\Components\Hidden::make('logo_preview_path')
                            ->default(null),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('fetchLogo')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->label('Lấy logo')
                                ->color('success')
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $domain = $get('domain');
                                    if (empty($domain)) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Vui lòng nhập domain')
                                            ->danger()
                                            ->send();
                                        return;
                                    }
                                    $domain = LogoFromDomainService::cleanDomain($domain);
                                    if (empty($domain)) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Domain không hợp lệ')
                                            ->danger()
                                            ->send();
                                        return;
                                    }
                                    try {
                                        $path = LogoFromDomainService::fetchAndSave($domain, Filament::auth()->user()?->code);
                                        if ($path !== null) {
                                            $set('logo_preview_path', $path);
                                            \Filament\Notifications\Notification::make()
                                                ->title('Đã tải logo vào thư viện!')
                                                ->success()
                                                ->send();
                                        } else {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Không tìm thấy logo')
                                                ->body('Không thể tải logo từ domain này. Vui lòng upload thủ công.')
                                                ->warning()
                                                ->send();
                                        }
                                    } catch (\Throwable $e) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Lỗi khi tải logo')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ])
                            ->columnSpanFull(),
                        Forms\Components\View::make('filament.admin.components.logo-preview')
                            ->viewData(fn (Forms\Get $get) => [
                                'previewPath' => $get('logo_preview_path'),
                            ])
                            ->visible(fn (Forms\Get $get) => !empty($get('logo_preview_path')))
                            ->columnSpanFull(),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Group::make([
                                    Forms\Components\FileUpload::make('image')
                                        ->label('Logo / Hình ảnh')
                                        ->image()
                                        ->disk('public')
                                        ->directory(fn () => 'users/' . (Filament::auth()->user()?->code ?? '00000') . '/brands')
                                        ->maxSize(2048)
                                        ->default(null)
                                        ->helperText('Tải lên file (tối đa 2MB), dùng "Lấy logo" từ domain, hoặc "Chọn từ ảnh đã có" bên dưới để thay/thêm ảnh.')
                                        ->saveUploadedFileUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string {
                                            $userCode = Filament::auth()->user()?->code ?? '00000';
                                            $dir = "users/{$userCode}/brands";
                                            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($dir)) {
                                                \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($dir, 0755, true);
                                            }
                                            return $file->storeAs(
                                                $dir,
                                                $file->getClientOriginalName() ?: \Illuminate\Support\Str::ulid() . '.' . $file->getClientOriginalExtension(),
                                                'public'
                                            );
                                        }),
                                    Forms\Components\View::make('filament.admin.components.brand-logo-picker-livewire'),
                                ]),
                                Forms\Components\TextInput::make('events')
                                    ->label('Events')
                                    ->maxLength(255)
                                    ->placeholder('e.g., Uncategorized'),
                            ])
                            ->columnSpanFull(),
                    ])->columns(2),
                
                Forms\Components\Section::make('Media & Nội dung')
                    ->schema([
                        Forms\Components\Toggle::make('approved')
                            ->label('Duyệt bài')
                            ->default(false),
                        Forms\Components\RichEditor::make('short_description')
                            ->label('Mô tả ngắn')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strikeThrough',
                                'link',
                                'orderedList',
                                'bulletList',
                                'blockquote',
                                'codeBlock',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull()
                            ->helperText('Nếu để trống hệ thống sẽ tự động tạo mô tả'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->circular()
                    ->size(30)
                    ->defaultImageUrl(url('/images/default-brand.svg'))
                    ->extraImgAttributes(['loading' => 'lazy']),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên cửa hàng')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('events')
                    ->searchable(),
                Tables\Columns\IconColumn::make('approved')
                    ->label('Duyệt bài')
                    ->boolean(),
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
                    ->visible(fn (): bool => (bool) (Filament::auth()->user()?->isAdmin())),
                Tables\Filters\SelectFilter::make('approved')
                    ->label('Duyệt bài')
                    ->options([
                        true => 'Đã duyệt',
                        false => 'Chưa duyệt',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Danh mục')
                    ->relationship(
                        'category',
                        'name',
                        modifyQueryUsing: function (Builder $query) {
                            $user = Filament::auth()->user();
                            $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
                            $userId = $isAdmin ? null : ($user?->id);

                            return $query->when(
                                $userId,
                                fn (Builder $q) => $q->where('categories.user_id', $userId),
                            );
                        }
                    )
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at_range')
                    ->label('Ngày tạo')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('Từ ngày'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Đến ngày'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (\Illuminate\Database\Eloquent\Builder $q, $date) => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (\Illuminate\Database\Eloquent\Builder $q, $date) => $q->whereDate('created_at', '<=', $date),
                            );
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
                    ->mutateRecordDataUsing(function (array $data, Brand $record): array {
                        $baseName = $record->name;
                        $baseSlug = $record->slug;
                        
                        $parts = explode('/', $baseSlug, 2);
                        $userCode = count($parts) === 2 ? $parts[0] : (\Filament\Facades\Filament::auth()->user()?->code ?? '00000');
                        $slugPart = count($parts) === 2 ? $parts[1] : $baseSlug;
                        
                        $name = $baseName . '-copy';
                        $newSlugPart = $slugPart . '-copy';
                        $slug = "{$userCode}/{$newSlugPart}";
                        $n = 0;
                        while (Brand::where('name', $name)->where('user_id', $record->user_id)->exists() || Brand::where('slug', $slug)->exists()) {
                            $n++;
                            $name = $baseName . '-copy' . $n;
                            $newSlugPart = $slugPart . '-copy' . $n;
                            $slug = "{$userCode}/{$newSlugPart}";
                        }
                        $data['name'] = $name;
                        $data['slug'] = $slug;
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->tooltip('Xóa')
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
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        $userId = $isAdmin ? null : ($user?->id);

        return parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->when(
                $userId,
                fn (Builder $query) => $query->where('user_id', $userId),
            );
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
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
