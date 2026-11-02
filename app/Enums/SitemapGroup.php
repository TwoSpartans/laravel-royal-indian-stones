<?php

namespace App\Enums;

enum SitemapGroup: string
{
    case Categories = 'categories';
    case Products = 'products';
    case Images = 'images';
    case Pages = 'pages';
    case Journal = 'journal';

    public function label(): string
    {
        return match ($this) {
            self::Categories => 'Categories',
            self::Products => 'Products',
            self::Images => 'Images',
            self::Pages => 'Pages',
            self::Journal => 'Journal',
        };
    }

    public function filename(int $part = 1): string
    {
        if ($part < 2) {
            return 'sitemap-'.$this->value.'.xml';
        }

        return 'sitemap-'.$this->value.'-'.$part.'.xml';
    }
}
