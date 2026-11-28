<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $units = $this->seedUnits();
            $categories = $this->seedCategories();
            $brands = $this->seedBrands();
            $groups = CustomerGroup::query()->pluck('id', 'code');

            foreach ($this->products() as $data) {
                $this->seedProduct($data, $units, $categories, $brands, $groups->all());
            }
        });
    }

    /** @return array<string, int> */
    private function seedUnits(): array
    {
        $units = [
            'pcs' => [['en' => 'Piece', 'bn' => 'পিস'], ['en' => 'pcs', 'bn' => 'পিস']],
            'kg' => [['en' => 'Kilogram', 'bn' => 'কেজি'], ['en' => 'kg', 'bn' => 'কেজি']],
            'ltr' => [['en' => 'Litre', 'bn' => 'লিটার'], ['en' => 'L', 'bn' => 'লি']],
            'pkt' => [['en' => 'Packet', 'bn' => 'প্যাকেট'], ['en' => 'pkt', 'bn' => 'প্যাকেট']],
            'btl' => [['en' => 'Bottle', 'bn' => 'বোতল'], ['en' => 'btl', 'bn' => 'বোতল']],
        ];

        $ids = [];

        foreach ($units as $code => [$name, $short]) {
            $ids[$code] = Unit::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'short_name' => $short, 'is_active' => true],
            )->id;
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedCategories(): array
    {
        $categories = [
            'rice-grains' => [['en' => 'Rice & Grains', 'bn' => 'চাল ও শস্য'], ['en' => 'Premium rice, lentils and flour in bulk packs.', 'bn' => 'বাল্ক প্যাকে প্রিমিয়াম চাল, ডাল ও আটা।']],
            'edible-oil' => [['en' => 'Edible Oil', 'bn' => 'ভোজ্য তেল'], ['en' => 'Soybean, mustard and rice bran oils.', 'bn' => 'সয়াবিন, সরিষা ও রাইস ব্র্যান তেল।']],
            'spices' => [['en' => 'Spices', 'bn' => 'মসলা'], ['en' => 'Ground and whole spices.', 'bn' => 'গুঁড়া ও আস্ত মসলা।']],
            'beverages' => [['en' => 'Beverages', 'bn' => 'পানীয়'], ['en' => 'Tea, drinking water and juices.', 'bn' => 'চা, খাবার পানি ও জুস।']],
            'snacks-biscuits' => [['en' => 'Snacks & Biscuits', 'bn' => 'স্ন্যাকস ও বিস্কুট'], ['en' => 'Biscuits, noodles and chips.', 'bn' => 'বিস্কুট, নুডলস ও চিপস।']],
            'personal-care' => [['en' => 'Personal Care', 'bn' => 'ব্যক্তিগত যত্ন'], ['en' => 'Soaps, shampoo and toothpaste.', 'bn' => 'সাবান, শ্যাম্পু ও টুথপেস্ট।']],
            'home-care' => [['en' => 'Home Care', 'bn' => 'গৃহস্থালি পরিচ্ছন্নতা'], ['en' => 'Detergents and cleaners.', 'bn' => 'ডিটারজেন্ট ও ক্লিনার।']],
            'dairy' => [['en' => 'Dairy', 'bn' => 'দুগ্ধজাত পণ্য'], ['en' => 'Milk powder and ghee.', 'bn' => 'গুঁড়া দুধ ও ঘি।']],
        ];

        $ids = [];
        $order = 0;

        foreach ($categories as $slug => [$name, $description]) {
            $ids[$slug] = Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'sort_order' => $order += 10, 'is_active' => true],
            )->id;
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedBrands(): array
    {
        $ids = [];

        foreach (['Padma Agro', 'Meghna Consumer', 'Sonar Bangla Foods', 'Jamuna Fresh', 'Rupali Care', 'Karnaphuli Tea'] as $name) {
            $slug = Str::slug($name);
            $ids[$slug] = Brand::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true])->id;
        }

        return $ids;
    }

    private function seedProduct(array $data, array $units, array $categories, array $brands, array $groups): void
    {
        $product = Product::query()->withTrashed()->firstOrNew(['sku' => $data['sku']]);

        $product->fill([
            'barcode' => $data['barcode'],
            'slug' => Str::slug($data['name']['en']).'-'.Str::lower($data['sku']),
            'category_id' => $categories[$data['category']],
            'brand_id' => $brands[$data['brand']],
            'unit_id' => $units[$data['unit']],
            'name' => $data['name'],
            'description' => $data['description'],
            'pack_size' => $data['pack_size'],
            'pack_label' => $data['pack_label'],
            'base_price' => $data['base_price'],
            'vat_percent' => $data['vat'],
            'moq' => $data['moq'],
            'step_quantity' => $data['step'],
            'max_order_quantity' => $data['max'] ?? null,
            'low_stock_threshold' => $data['low_stock'],
            'is_active' => true,
            'is_featured' => $data['featured'] ?? false,
        ]);
        // stock_quantity is guarded by design; set explicitly for seeding.
        $product->forceFill(['stock_quantity' => $data['stock'], 'deleted_at' => null])->save();

        $product->priceTiers()->delete();

        foreach ($data['tiers'] as [$min, $max, $price]) {
            $this->createTier($product, null, $min, $max, $price);
        }

        foreach ($data['group_tiers'] ?? [] as $groupCode => $tiers) {
            foreach ($tiers as [$min, $max, $price]) {
                $this->createTier($product, $groups[$groupCode], $min, $max, $price);
            }
        }
    }

    private function createTier(Product $product, ?int $groupId, int $min, ?int $max, string $price): void
    {
        $tier = new ProductPriceTier([
            'customer_group_id' => $groupId,
            'min_quantity' => $min,
            'max_quantity' => $max,
            'unit_price' => $price,
        ]);
        $product->priceTiers()->save($tier);
    }

    private function products(): array
    {
        $carton = ['en' => 'Carton', 'bn' => 'কার্টন'];
        $sack = ['en' => 'Sack', 'bn' => 'বস্তা'];

        return [
            [
                'sku' => 'RIC-MIN-25', 'barcode' => '8941000100015', 'category' => 'rice-grains', 'brand' => 'padma-agro', 'unit' => 'kg',
                'name' => ['en' => 'Miniket Rice Premium', 'bn' => 'মিনিকেট চাল প্রিমিয়াম'],
                'description' => ['en' => 'Polished premium Miniket rice, sold per kg in 25 kg sacks.', 'bn' => 'পলিশড প্রিমিয়াম মিনিকেট চাল, ২৫ কেজির বস্তায় কেজি হিসেবে বিক্রি।'],
                'pack_size' => 25, 'pack_label' => $sack, 'base_price' => '78.00', 'vat' => '0.00',
                'moq' => 50, 'step' => 25, 'stock' => 12000, 'low_stock' => 1000, 'featured' => true,
                'tiers' => [[50, 249, '76.00'], [250, 999, '74.00'], [1000, null, '72.50']],
                'group_tiers' => ['distributor' => [[1000, null, '71.00']]],
            ],
            [
                'sku' => 'RIC-NAZ-25', 'barcode' => '8941000100022', 'category' => 'rice-grains', 'brand' => 'padma-agro', 'unit' => 'kg',
                'name' => ['en' => 'Nazirshail Rice', 'bn' => 'নাজিরশাইল চাল'],
                'description' => ['en' => 'Aromatic Nazirshail rice in 25 kg sacks.', 'bn' => '২৫ কেজির বস্তায় সুগন্ধি নাজিরশাইল চাল।'],
                'pack_size' => 25, 'pack_label' => $sack, 'base_price' => '86.00', 'vat' => '0.00',
                'moq' => 50, 'step' => 25, 'stock' => 8000, 'low_stock' => 800,
                'tiers' => [[50, 249, '84.00'], [250, 999, '82.00'], [1000, null, '80.00']],
            ],
            [
                'sku' => 'DAL-MSR-50', 'barcode' => '8941000100039', 'category' => 'rice-grains', 'brand' => 'sonar-bangla-foods', 'unit' => 'kg',
                'name' => ['en' => 'Red Lentil (Moshur Dal)', 'bn' => 'মসুর ডাল (দেশি)'],
                'description' => ['en' => 'Sorted local red lentils, 50 kg sacks.', 'bn' => 'বাছাইকৃত দেশি মসুর ডাল, ৫০ কেজির বস্তা।'],
                'pack_size' => 50, 'pack_label' => $sack, 'base_price' => '135.00', 'vat' => '0.00',
                'moq' => 50, 'step' => 50, 'stock' => 4000, 'low_stock' => 500,
                'tiers' => [[50, 199, '132.00'], [200, 499, '128.00'], [500, null, '124.00']],
            ],
            [
                'sku' => 'ATA-2KG', 'barcode' => '8941000100046', 'category' => 'rice-grains', 'brand' => 'sonar-bangla-foods', 'unit' => 'pkt',
                'name' => ['en' => 'Whole Wheat Atta 2 kg', 'bn' => 'লাল আটা ২ কেজি'],
                'description' => ['en' => 'Fresh-milled whole wheat flour, 2 kg pack, 10 packs per bale.', 'bn' => 'তাজা ভাঙানো লাল আটা, ২ কেজি প্যাক, প্রতি বেলে ১০টি।'],
                'pack_size' => 10, 'pack_label' => ['en' => 'Bale', 'bn' => 'বেল'], 'base_price' => '130.00', 'vat' => '0.00',
                'moq' => 20, 'step' => 10, 'stock' => 3000, 'low_stock' => 300,
                'tiers' => [[20, 99, '126.00'], [100, 499, '122.00'], [500, null, '118.00']],
            ],
            [
                'sku' => 'OIL-SOY-5L', 'barcode' => '8941000100053', 'category' => 'edible-oil', 'brand' => 'meghna-consumer', 'unit' => 'btl',
                'name' => ['en' => 'Fortified Soybean Oil 5 L', 'bn' => 'ফর্টিফাইড সয়াবিন তেল ৫ লিটার'],
                'description' => ['en' => 'Vitamin-A fortified refined soybean oil, 5 L bottle, 4 per carton.', 'bn' => 'ভিটামিন-এ সমৃদ্ধ পরিশোধিত সয়াবিন তেল, ৫ লিটার বোতল, প্রতি কার্টনে ৪টি।'],
                'pack_size' => 4, 'pack_label' => $carton, 'base_price' => '860.00', 'vat' => '5.00',
                'moq' => 8, 'step' => 4, 'stock' => 2400, 'low_stock' => 200, 'featured' => true,
                'tiers' => [[8, 39, '845.00'], [40, 99, '830.00'], [100, null, '815.00']],
                'group_tiers' => ['gold' => [[40, null, '822.00']], 'distributor' => [[100, null, '805.00']]],
            ],
            [
                'sku' => 'OIL-MUS-1L', 'barcode' => '8941000100060', 'category' => 'edible-oil', 'brand' => 'meghna-consumer', 'unit' => 'btl',
                'name' => ['en' => 'Pure Mustard Oil 1 L', 'bn' => 'খাঁটি সরিষার তেল ১ লিটার'],
                'description' => ['en' => 'Cold-pressed (ghani) mustard oil, 12 bottles per carton.', 'bn' => 'ঘানি ভাঙা খাঁটি সরিষার তেল, প্রতি কার্টনে ১২ বোতল।'],
                'pack_size' => 12, 'pack_label' => $carton, 'base_price' => '320.00', 'vat' => '5.00',
                'moq' => 12, 'step' => 12, 'stock' => 1800, 'low_stock' => 144,
                'tiers' => [[12, 59, '312.00'], [60, 143, '305.00'], [144, null, '298.00']],
            ],
            [
                'sku' => 'SPC-TUR-200', 'barcode' => '8941000100077', 'category' => 'spices', 'brand' => 'sonar-bangla-foods', 'unit' => 'pkt',
                'name' => ['en' => 'Turmeric Powder 200 g', 'bn' => 'হলুদ গুঁড়া ২০০ গ্রাম'],
                'description' => ['en' => 'Pure turmeric powder, 200 g pouch, 50 per carton.', 'bn' => 'খাঁটি হলুদ গুঁড়া, ২০০ গ্রাম পাউচ, প্রতি কার্টনে ৫০টি।'],
                'pack_size' => 50, 'pack_label' => $carton, 'base_price' => '95.00', 'vat' => '5.00',
                'moq' => 50, 'step' => 50, 'stock' => 5000, 'low_stock' => 500,
                'tiers' => [[50, 199, '92.00'], [200, 499, '89.00'], [500, null, '86.00']],
            ],
            [
                'sku' => 'SPC-CHL-200', 'barcode' => '8941000100084', 'category' => 'spices', 'brand' => 'sonar-bangla-foods', 'unit' => 'pkt',
                'name' => ['en' => 'Red Chilli Powder 200 g', 'bn' => 'মরিচ গুঁড়া ২০০ গ্রাম'],
                'description' => ['en' => 'Hot red chilli powder, 200 g pouch, 50 per carton.', 'bn' => 'ঝাল মরিচ গুঁড়া, ২০০ গ্রাম পাউচ, প্রতি কার্টনে ৫০টি।'],
                'pack_size' => 50, 'pack_label' => $carton, 'base_price' => '110.00', 'vat' => '5.00',
                'moq' => 50, 'step' => 50, 'stock' => 60, 'low_stock' => 100,
                'tiers' => [[50, 199, '107.00'], [200, 499, '104.00'], [500, null, '100.00']],
            ],
            [
                'sku' => 'TEA-PRM-400', 'barcode' => '8941000100091', 'category' => 'beverages', 'brand' => 'karnaphuli-tea', 'unit' => 'pkt',
                'name' => ['en' => 'Premium Black Tea 400 g', 'bn' => 'প্রিমিয়াম কালো চা ৪০০ গ্রাম'],
                'description' => ['en' => 'Sylhet garden blend, 400 g pack, 24 per carton.', 'bn' => 'সিলেটের বাগানের ব্লেন্ড, ৪০০ গ্রাম প্যাক, প্রতি কার্টনে ২৪টি।'],
                'pack_size' => 24, 'pack_label' => $carton, 'base_price' => '240.00', 'vat' => '7.50',
                'moq' => 24, 'step' => 24, 'stock' => 2400, 'low_stock' => 240, 'featured' => true,
                'tiers' => [[24, 95, '234.00'], [96, 239, '228.00'], [240, null, '222.00']],
            ],
            [
                'sku' => 'WTR-500', 'barcode' => '8941000100107', 'category' => 'beverages', 'brand' => 'jamuna-fresh', 'unit' => 'btl',
                'name' => ['en' => 'Drinking Water 500 ml', 'bn' => 'খাবার পানি ৫০০ মিলি'],
                'description' => ['en' => 'BSTI certified purified water, 24 bottles per carton.', 'bn' => 'বিএসটিআই অনুমোদিত বিশুদ্ধ পানি, প্রতি কার্টনে ২৪ বোতল।'],
                'pack_size' => 24, 'pack_label' => $carton, 'base_price' => '15.00', 'vat' => '15.00',
                'moq' => 48, 'step' => 24, 'stock' => 20000, 'low_stock' => 2000,
                'tiers' => [[48, 479, '14.20'], [480, 2399, '13.50'], [2400, null, '12.80']],
            ],
            [
                'sku' => 'BSC-MLK-100', 'barcode' => '8941000100114', 'category' => 'snacks-biscuits', 'brand' => 'jamuna-fresh', 'unit' => 'pkt',
                'name' => ['en' => 'Milk Biscuits 100 g', 'bn' => 'মিল্ক বিস্কুট ১০০ গ্রাম'],
                'description' => ['en' => 'Crispy milk biscuits, 48 packets per carton.', 'bn' => 'মচমচে মিল্ক বিস্কুট, প্রতি কার্টনে ৪৮ প্যাকেট।'],
                'pack_size' => 48, 'pack_label' => $carton, 'base_price' => '20.00', 'vat' => '15.00',
                'moq' => 96, 'step' => 48, 'stock' => 9600, 'low_stock' => 960,
                'tiers' => [[96, 479, '19.00'], [480, 1439, '18.20'], [1440, null, '17.50']],
            ],
            [
                'sku' => 'NDL-MSL-8', 'barcode' => '8941000100121', 'category' => 'snacks-biscuits', 'brand' => 'jamuna-fresh', 'unit' => 'pkt',
                'name' => ['en' => 'Masala Instant Noodles (8-pack)', 'bn' => 'মসলা ইনস্ট্যান্ট নুডলস (৮ প্যাক)'],
                'description' => ['en' => 'Family pack of 8, 12 family packs per carton.', 'bn' => '৮টির ফ্যামিলি প্যাক, প্রতি কার্টনে ১২টি।'],
                'pack_size' => 12, 'pack_label' => $carton, 'base_price' => '165.00', 'vat' => '15.00',
                'moq' => 12, 'step' => 12, 'stock' => 1440, 'low_stock' => 144,
                'tiers' => [[12, 59, '160.00'], [60, 239, '155.00'], [240, null, '150.00']],
            ],
            [
                'sku' => 'SOP-BTY-100', 'barcode' => '8941000100138', 'category' => 'personal-care', 'brand' => 'rupali-care', 'unit' => 'pcs',
                'name' => ['en' => 'Beauty Soap 100 g', 'bn' => 'বিউটি সাবান ১০০ গ্রাম'],
                'description' => ['en' => 'Moisturising beauty soap, 72 pcs per carton.', 'bn' => 'ময়েশ্চারাইজিং বিউটি সাবান, প্রতি কার্টনে ৭২ পিস।'],
                'pack_size' => 72, 'pack_label' => $carton, 'base_price' => '55.00', 'vat' => '15.00',
                'moq' => 72, 'step' => 72, 'stock' => 7200, 'low_stock' => 720,
                'tiers' => [[72, 359, '53.00'], [360, 1079, '51.00'], [1080, null, '49.00']],
            ],
            [
                'sku' => 'TPS-HRB-150', 'barcode' => '8941000100145', 'category' => 'personal-care', 'brand' => 'rupali-care', 'unit' => 'pcs',
                'name' => ['en' => 'Herbal Toothpaste 150 g', 'bn' => 'হারবাল টুথপেস্ট ১৫০ গ্রাম'],
                'description' => ['en' => 'Neem & clove herbal toothpaste, 48 tubes per carton.', 'bn' => 'নিম ও লবঙ্গ হারবাল টুথপেস্ট, প্রতি কার্টনে ৪৮টি।'],
                'pack_size' => 48, 'pack_label' => $carton, 'base_price' => '120.00', 'vat' => '15.00',
                'moq' => 48, 'step' => 48, 'stock' => 2880, 'low_stock' => 288,
                'tiers' => [[48, 191, '116.00'], [192, 479, '112.00'], [480, null, '108.00']],
            ],
            [
                'sku' => 'DET-PWD-1KG', 'barcode' => '8941000100152', 'category' => 'home-care', 'brand' => 'meghna-consumer', 'unit' => 'pkt',
                'name' => ['en' => 'Detergent Powder 1 kg', 'bn' => 'ডিটারজেন্ট পাউডার ১ কেজি'],
                'description' => ['en' => 'Active-enzyme detergent powder, 20 packs per carton.', 'bn' => 'অ্যাকটিভ এনজাইম ডিটারজেন্ট পাউডার, প্রতি কার্টনে ২০ প্যাকেট।'],
                'pack_size' => 20, 'pack_label' => $carton, 'base_price' => '175.00', 'vat' => '15.00',
                'moq' => 20, 'step' => 20, 'stock' => 3000, 'low_stock' => 300,
                'tiers' => [[20, 99, '170.00'], [100, 399, '165.00'], [400, null, '159.00']],
            ],
            [
                'sku' => 'DRY-MLK-1KG', 'barcode' => '8941000100169', 'category' => 'dairy', 'brand' => 'jamuna-fresh', 'unit' => 'pkt',
                'name' => ['en' => 'Full Cream Milk Powder 1 kg', 'bn' => 'ফুল ক্রিম গুঁড়া দুধ ১ কেজি'],
                'description' => ['en' => 'Instant full cream milk powder, 12 packs per carton.', 'bn' => 'ইনস্ট্যান্ট ফুল ক্রিম গুঁড়া দুধ, প্রতি কার্টনে ১২ প্যাকেট।'],
                'pack_size' => 12, 'pack_label' => $carton, 'base_price' => '880.00', 'vat' => '5.00',
                'moq' => 12, 'step' => 12, 'stock' => 0, 'low_stock' => 60,
                'tiers' => [[12, 59, '865.00'], [60, 143, '850.00'], [144, null, '835.00']],
            ],
        ];
    }
}
