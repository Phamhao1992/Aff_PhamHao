<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chọn store (brand) + chiến dịch để viết blog giới thiệu (theo engagement hoặc theo gợi ý admin).
 */
class BrandIntroBlogCandidateService
{
    /**
     * Tìm brand + một campaign có affiliate_url theo tên brand hoặc domain (dùng nút AI trong admin).
     *
     * @return array{0: Brand, 1: Campaign}|null
     */
    public function findBrandAndCampaignByHint(string $hint): ?array
    {
        $hint = trim($hint);
        if ($hint === '') {
            return null;
        }

        $brand = $this->findBrandByHint($hint);
        if (! $brand) {
            return null;
        }

        $campaign = $this->pickTopImportedCampaignForBrand($brand);
        if (! $campaign) {
            return null;
        }

        return [$brand, $campaign];
    }

    protected function findBrandByHint(string $hint): ?Brand
    {
        $base = Brand::query()->whereNull('deleted_at');

        $lower = Str::lower($hint);
        $brand = (clone $base)->whereRaw('LOWER(name) = ?', [$lower])->first();
        if ($brand) {
            return $brand;
        }

        $escaped = addcslashes($hint, '%_\\');
        $brand = (clone $base)
            ->where('name', 'like', '%'.$escaped.'%')
            ->orderByDesc('id')
            ->first();
        if ($brand) {
            return $brand;
        }

        $host = $this->normalizeHostForLookup($hint);
        if ($host === null || $host === '') {
            return null;
        }

        return (clone $base)
            ->whereNotNull('domain')
            ->where('domain', '!=', '')
            ->whereRaw('LOWER(domain) LIKE ?', ['%'.$host.'%'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Chuẩn hóa domain từ chuỗi người dùng (có thể là URL hoặc example.com).
     */
    protected function normalizeHostForLookup(string $input): ?string
    {
        $t = trim($input);
        if ($t === '') {
            return null;
        }

        if (! preg_match('~^https?://~i', $t)) {
            $t = 'https://'.$t;
        }

        $host = parse_url($t, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return Str::lower(preg_replace('/^www\./i', '', $host));
        }

        $plain = Str::lower(preg_replace('/^www\./i', '', rtrim($t, '/')));
        if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $plain)) {
            return $plain;
        }

        return null;
    }

    /**
     * Tìm brand + campaign tiếp theo theo vòng lặp, bỏ qua campaign đã có blog.
     *
     * @return array{0: Brand, 1: Campaign}|null
     */
    public function findBrandAndCampaignForIntro(): ?array
    {
        // Lấy danh sách campaign_id đã có blog giới thiệu (chưa xóa)
        $usedCampaignIds = Blog::whereNotNull('campaign_id')
            ->where('intro_type', 'store')
            ->pluck('campaign_id')
            ->toArray();

        $brandRows = DB::table('brands')
            ->join('campaigns', 'campaigns.brand_id', '=', 'brands.id')
            ->whereNull('campaigns.deleted_at')
            ->whereNotNull('campaigns.affiliate_url')
            ->where('campaigns.affiliate_url', '!=', '')
            ->whereNull('brands.deleted_at')
            ->when(app()->environment('production'), fn ($q) => $q->where('campaigns.status', 'active'))
            ->select('brands.id', 'brands.name', DB::raw('MIN(campaigns.created_at) as oldest_campaign_at'))
            ->groupBy('brands.id', 'brands.name')
            ->orderBy('oldest_campaign_at')
            ->get();

        if ($brandRows->isEmpty()) {
            return null;
        }

        // Lọc bỏ brand mà tất cả campaign đã có blog
        $availableBrandIds = [];
        foreach ($brandRows as $row) {
            $brandCampaigns = DB::table('campaigns')
                ->where('brand_id', $row->id)
                ->whereNull('deleted_at')
                ->whereNotNull('affiliate_url')
                ->where('affiliate_url', '!=', '')
                ->when(app()->environment('production'), fn ($q) => $q->where('status', 'active'))
                ->pluck('id')
                ->toArray();

            $hasAvailableCampaign = false;
            foreach ($brandCampaigns as $cid) {
                if (!in_array($cid, $usedCampaignIds)) {
                    $hasAvailableCampaign = true;
                    break;
                }
            }

            if ($hasAvailableCampaign) {
                $availableBrandIds[] = (int) $row->id;
            }
        }

        if (empty($availableBrandIds)) {
            return null;
        }

        // Mỗi ngày trong năm chọn 1 brand khác nhau (xoay vòng theo day-of-year)
        $index = now()->dayOfYear() % $brandRows->count();
        $row = $brandRows[$index];

        $brandId = (int) $row->brand_id;
        $brand = Brand::query()->with('category')->whereKey($brandId)->first();
        if (! $brand) {
            return null;
        }

        // Tìm campaign cũ nhất của brand chưa có blog
        $campaign = Campaign::query()
            ->where('campaigns.brand_id', $brand->id)
            ->whereNull('campaigns.deleted_at')
            ->whereNotNull('campaigns.affiliate_url')
            ->where('campaigns.affiliate_url', '!=', '')
            ->when(app()->environment('production'), fn ($q) => $q->where('campaigns.status', 'active'))
            ->whereNotIn('campaigns.id', $usedCampaignIds)
            ->orderBy('campaigns.created_at')
            ->orderBy('campaigns.id')
            ->first();

        if (! $campaign) {
            return null;
        }

        return [$brand, $campaign];
    }

    /**
     * Chiến dịch đã import + affiliate_url, engagement (views + clicks) cao nhất trong brand.
     */
    public function pickTopImportedCampaignForBrand(Brand $brand): ?Campaign
    {
        $pvSub = DB::table('page_views')
            ->select('campaign_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('campaign_id');

        $ckSub = DB::table('clicks')
            ->whereNull('deleted_at')
            ->select('campaign_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('campaign_id');

        return Campaign::query()
            ->leftJoinSub($pvSub, 'pv', 'pv.campaign_id', '=', 'campaigns.id')
            ->leftJoinSub($ckSub, 'ck', 'ck.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.brand_id', $brand->id)
            ->whereNull('campaigns.deleted_at')
            ->whereNotNull('campaigns.affiliate_url')
            ->where('campaigns.affiliate_url', '!=', '')
            ->when(app()->environment('production'), fn ($q) => $q->where('campaigns.status', 'active'))
            ->select('campaigns.*')
            ->selectRaw('(COALESCE(pv.cnt, 0) + COALESCE(ck.cnt, 0)) AS engagement')
            ->orderByDesc('engagement')
            ->orderBy('campaigns.id')
            ->first();
    }

    /**
     * Ưu tiên campaign chưa có blog intro; nếu brand đã có bài thì vẫn lấy campaign engagement cao nhất để viết thêm.
     */
    public function pickCampaignForStoreBlog(Brand $brand): ?Campaign
    {
        $usedCampaignIds = Blog::query()
            ->where('intro_type', 'store')
            ->whereNotNull('campaign_id')
            ->pluck('campaign_id')
            ->all();

        $pvSub = DB::table('page_views')
            ->select('campaign_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('campaign_id');

        $ckSub = DB::table('clicks')
            ->whereNull('deleted_at')
            ->select('campaign_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('campaign_id');

        $unused = Campaign::query()
            ->leftJoinSub($pvSub, 'pv', 'pv.campaign_id', '=', 'campaigns.id')
            ->leftJoinSub($ckSub, 'ck', 'ck.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.brand_id', $brand->id)
            ->whereNull('campaigns.deleted_at')
            ->whereNotNull('campaigns.affiliate_url')
            ->where('campaigns.affiliate_url', '!=', '')
            ->when(app()->environment('production'), fn ($q) => $q->where('campaigns.status', 'active'))
            ->when($usedCampaignIds !== [], fn ($q) => $q->whereNotIn('campaigns.id', $usedCampaignIds))
            ->select('campaigns.*')
            ->selectRaw('(COALESCE(pv.cnt, 0) + COALESCE(ck.cnt, 0)) AS engagement')
            ->orderByDesc('engagement')
            ->orderBy('campaigns.id')
            ->first();

        return $unused ?: $this->pickTopImportedCampaignForBrand($brand);
    }

    /**
     * Cửa hàng đủ điều kiện viết blog intro (có campaign + affiliate URL), tách đã có / chưa có bài.
     *
     * @return array{with: \Illuminate\Support\Collection<int, Brand>, without: \Illuminate\Support\Collection<int, Brand>}
     */
    public function storeIntroCoverage(): array
    {
        $eligible = Brand::query()
            ->whereExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('campaigns')
                    ->whereColumn('campaigns.brand_id', 'brands.id')
                    ->whereNull('campaigns.deleted_at')
                    ->whereNotNull('campaigns.affiliate_url')
                    ->where('campaigns.affiliate_url', '!=', '');
                if (app()->environment('production')) {
                    $q->where('campaigns.status', 'active');
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'domain']);

        $brandIdsWithBlog = $this->brandIdsThatHaveStoreIntroBlog();

        return [
            'with' => $eligible->filter(fn (Brand $b): bool => in_array((int) $b->id, $brandIdsWithBlog, true))->values(),
            'without' => $eligible->reject(fn (Brand $b): bool => in_array((int) $b->id, $brandIdsWithBlog, true))->values(),
        ];
    }

    /**
     * @return list<int>
     */
    public function brandIdsThatHaveStoreIntroBlog(): array
    {
        return DB::table('blogs')
            ->join('campaigns', 'campaigns.id', '=', 'blogs.campaign_id')
            ->where('blogs.intro_type', 'store')
            ->whereNull('blogs.deleted_at')
            ->whereNotNull('campaigns.brand_id')
            ->distinct()
            ->pluck('campaigns.brand_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function brandHasStoreIntroBlog(Brand $brand): bool
    {
        return Blog::query()
            ->where('intro_type', 'store')
            ->whereHas('campaign', fn ($q) => $q->where('brand_id', $brand->id))
            ->exists();
    }
}
