<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('storefront.sitemap', 3600, fn () => $this->buildXml());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function buildXml(): string
    {
        $entries = [];

        $entries[] = $this->entry(url('/'), now());
        $entries[] = $this->entry(route('shop'), now());
        $entries[] = $this->entry(route('offers.index'), now());
        $entries[] = $this->entry(route('brands.index'), now());
        $entries[] = $this->entry(route('pages.about'), now());
        $entries[] = $this->entry(route('pages.contact'), now());
        $entries[] = $this->entry(route('pages.faq'), now());

        Page::query()
            ->published()
            ->whereNotIn('slug', ['about', 'contact', 'faq'])
            ->orderBy('updated_at', 'desc')
            ->get(['slug', 'updated_at'])
            ->each(function (Page $page) use (&$entries) {
                $entries[] = $this->entry(route('pages.show', $page->slug), $page->updated_at);
            });

        Product::query()
            ->active()
            ->published()
            ->orderBy('updated_at', 'desc')
            ->get(['slug', 'updated_at'])
            ->each(function (Product $product) use (&$entries) {
                $entries[] = $this->entry(route('products.show', $product->slug), $product->updated_at);
            });

        Offer::query()
            ->active()
            ->orderBy('updated_at', 'desc')
            ->get(['slug', 'updated_at'])
            ->each(function (Offer $offer) use (&$entries) {
                $entries[] = $this->entry(route('offers.show', $offer->slug), $offer->updated_at);
            });

        Brand::query()
            ->active()
            ->orderBy('updated_at', 'desc')
            ->get(['slug', 'updated_at'])
            ->each(function (Brand $brand) use (&$entries) {
                $entries[] = $this->entry(route('shop', ['brand' => $brand->slug]), $brand->updated_at);
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        $xml .= implode("\n", $entries)."\n";
        $xml .= '</urlset>';

        return $xml;
    }

    private function entry(string $loc, ?\DateTimeInterface $lastmod = null): string
    {
        return Seo::sitemapUrlXml(
            $loc,
            Seo::sitemapLastmod($lastmod),
            Seo::hreflangAlternates($loc),
        );
    }
}
