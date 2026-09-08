<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ArabicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();
        $this->seedAttributes();
        $this->seedProducts();
        $this->seedBanners();
        $this->seedPages();
    }

    protected function seedCategories(): void
    {
        $map = [
            'face-care' => [
                'name_ar' => 'العناية بالوجه',
                'description_ar' => 'اكتشفي مجموعة العناية بالوجه.',
            ],
            'body-care' => [
                'name_ar' => 'العناية بالجسم',
                'description_ar' => 'اكتشفي مجموعة العناية بالجسم.',
            ],
            'hair-care' => [
                'name_ar' => 'العناية بالشعر',
                'description_ar' => 'اكتشفي مجموعة العناية بالشعر.',
            ],
            'fragrance' => [
                'name_ar' => 'العطور',
                'description_ar' => 'اكتشفي مجموعة العطور.',
            ],
            'makeup' => [
                'name_ar' => 'المكياج',
                'description_ar' => 'اكتشفي مجموعة المكياج.',
            ],
            'bath-ritual' => [
                'name_ar' => 'الاستحمام والطقوس',
                'description_ar' => 'اكتشفي مجموعة الاستحمام والطقوس.',
            ],
            'sun-care' => [
                'name_ar' => 'العناية من الشمس',
                'description_ar' => 'اكتشفي مجموعة العناية من الشمس.',
            ],
            'mens-grooming' => [
                'name_ar' => 'العناية الرجالية',
                'description_ar' => 'اكتشفي مجموعة العناية الرجالية.',
            ],
            'gift-sets' => [
                'name_ar' => 'مجموعات الهدايا',
                'description_ar' => 'اكتشفي مجموعة مجموعات الهدايا.',
            ],
            'wellness-oils' => [
                'name_ar' => 'زيوت العافية',
                'description_ar' => 'اكتشفي مجموعة زيوت العافية.',
            ],
        ];

        foreach ($map as $slug => $fields) {
            Category::query()->where('slug', $slug)->update($fields);
        }
    }

    protected function seedBrands(): void
    {
        Brand::query()->update([
            'description_ar' => 'منتقاة بعناية لـ Aura & Oath.',
        ]);
    }

    protected function seedAttributes(): void
    {
        $attributes = [
            'shade' => 'الدرجة',
            'size' => 'الحجم',
            'scent' => 'العطر',
            'unit' => 'الوحدة',
        ];

        foreach ($attributes as $slug => $nameAr) {
            Attribute::query()->where('slug', $slug)->update(['name_ar' => $nameAr]);
        }

        $values = [
            'ivory' => 'عاجي',
            'blush' => 'وردي فاتح',
            'sand' => 'رملي',
            'rose' => 'وردي',
            'amber' => 'عنبر',
            'fig' => 'تين',
            'jasmine' => 'ياسمين',
            'cedar' => 'أرز',
        ];

        foreach ($values as $slug => $valueAr) {
            AttributeValue::query()->where('slug', $slug)->update(['value_ar' => $valueAr]);
        }
    }

    protected function seedProducts(): void
    {
        $catalog = [
            'AO-0001' => [
                'name_ar' => 'سيروم حريري متوهج',
                'short_description_ar' => 'سيروم مرطب للوجه يمنحك إشراقة هادئة.',
                'description_ar' => 'سيروم مرطب للوجه يمنحك إشراقة هادئة. صُمّم لطقوس Aura & Oath — دافئ، راقٍ، وفاخر ببساطة.',
                'ingredients_ar' => 'ماء، جلسرين، مستخلصات نباتية، توكофيرول.',
                'how_to_use_ar' => 'يُطبّق صباحاً ومساءً على بشرة نظيفة. يُدلّك بلطف حتى الامتصاص.',
            ],
            'AO-0002' => [
                'name_ar' => 'كريم ليلي مخملي',
                'short_description_ar' => 'استعيدي نعومة البشرة ليلاً بزيوت نباتية.',
                'description_ar' => 'استعيدي نعومة البشرة ليلاً بزيوت نباتية. صُمّم لطقوس Aura & Oath — دافئ، راقٍ، وفاخر ببساطة.',
                'ingredients_ar' => 'ماء، جلسرين، مستخلصات نباتية، توكوفيرول.',
                'how_to_use_ar' => 'يُطبّق صباحاً ومساءً على بشرة نظيفة. يُدلّك بلطف حتى الامتصاص.',
            ],
            'AO-0003' => [
                'name_ar' => 'بلسم تنظيف عاجي',
                'short_description_ar' => 'يزيل المكياج دون أن يسحب رطوبة البشرة.',
                'description_ar' => 'يزيل المكياج دون أن يسحب رطوبة البشرة. صُمّم لطقوس Aura & Oath — دافئ، راقٍ، وفاخر ببساطة.',
                'ingredients_ar' => 'ماء، جلسرين، مستخلصات نباتية، توكوفيرول.',
                'how_to_use_ar' => 'يُطبّق صباحاً ومساءً على بشرة نظيفة. يُدلّك بلطف حتى الامتصاص.',
            ],
            'AO-0004' => [
                'name_ar' => 'رذاذ وردي ناعم',
                'short_description_ar' => 'رذاذ منعش للوجه يمنحك هدوءاً في منتصف اليوم.',
                'description_ar' => 'رذاذ منعش للوجه يمنحك هدوءاً في منتصف اليوم. صُمّم لطقوس Aura & Oath — دافئ، راقٍ، وفاخر ببساطة.',
                'ingredients_ar' => 'ماء، جلسرين، مستخلصات نباتية، توكوفيرول.',
                'how_to_use_ar' => 'يُطبّق صباحاً ومساءً على بشرة نظيفة. يُدلّك بلطف حتى الامتصاص.',
            ],
            'AO-0005' => [
                'name_ar' => 'زيت الجسم Oath',
                'short_description_ar' => 'يغذّي البشرة بنفحات عنبر دافئة.',
                'description_ar' => 'يغذّي البشرة بنفحات عنبر دافئة. صُمّم لطقوس Aura & Oath — دافئ، راقٍ، وفاخر ببساطة.',
                'ingredients_ar' => 'ماء، جلسرين، مستخلصات نباتية، توكوفيرول.',
                'how_to_use_ar' => 'يُطبّق صباحاً ومساءً على بشرة نظيفة. يُدلّك بلطف حتى الامتصاص.',
            ],
            'AO-0006' => [
                'name_ar' => 'قناع شعر هادئ',
                'short_description_ar' => 'إصلاح عميق لشعر ناعم ومتوهج.',
                'description_ar' => 'إصلاح عميق لشعر ناعم ومتوهج. صُمّم لطقوس Aura & Oath — دافئ، راقٍ، وفاخر ببساطة.',
                'ingredients_ar' => 'ماء، جلسرين، مستخلصات نباتية، توكوفيرول.',
                'how_to_use_ar' => 'يُطبّق صباحاً ومساءً على بشرة نظيفة. يُدلّك بلطف حتى الامتصاص.',
            ],
            'AO-0007' => [
                'name_ar' => 'ماء عطر Cedar Rose',
                'short_description_ar' => 'عطر زهرية ناعمة للمساء.',
                'description_ar' => 'عطر زهرية ناعمة للمساء. صُمّم لطقوس Aura & Oath — دافئ، راقٍ، وفاخر ببساطة.',
                'ingredients_ar' => 'ماء، جلسرين، مستخلصات نباتية، توكوفيرول.',
                'how_to_use_ar' => 'يُطبّق صباحاً ومساءً على بشرة نظيفة. يُدلّك بلطف حتى الامتصاص.',
            ],
        ];

        foreach ($catalog as $sku => $fields) {
            Product::query()->where('sku', $sku)->update($fields);
        }
    }

    protected function seedBanners(): void
    {
        Banner::query()->where('title', 'Quiet luxury for home and self')->update([
            'title_ar' => 'رفاهية هادئة للمنزل والذات',
            'subtitle_ar' => 'اكتشفي Aura & Oath — جمال ومنزل ومستلزمات يومية، بأناقة هادئة.',
            'button_text_ar' => 'تسوّقي المجموعة',
        ]);

        Banner::query()->where('title', 'New Arrivals')->update([
            'title_ar' => 'وصول جديد',
            'subtitle_ar' => 'اختيارات جديدة، ألوان هادئة، عناية تدوم.',
            'button_text_ar' => 'اكتشفي الجديد',
        ]);
    }

    protected function seedPages(): void
    {
        Page::query()->where('slug', 'privacy-policy')->update([
            'title_ar' => 'سياسة الخصوصية',
            'content_ar' => 'Aura & Oath متجر صغير مقره لبنان. توضّح هذه السياسة بلغة بسيطة كيف نتعامل مع معلوماتك.',
        ]);

        Page::query()->where('slug', 'terms-of-service')->update([
            'title_ar' => 'شروط الخدمة',
            'content_ar' => 'تنطبق هذه الشروط عند التسوّق من Aura & Oath، متجر إلكتروني مقره لبنان.',
        ]);

        Page::query()->where('slug', 'shipping-policy')->update([
            'title_ar' => 'سياسة التوصيل',
            'content_ar' => 'نوصّل في جميع أنحاء لبنان. تختلف الرسوم حسب منطقتك.',
        ]);

        Page::query()->where('slug', 'returns-policy')->update([
            'title_ar' => 'سياسة المرتجعات',
            'content_ar' => 'يُقبل الإرجاع خلال 24 ساعة من التسليم، فقط عند وجود مشكلة حقيقية.',
        ]);
    }
}
