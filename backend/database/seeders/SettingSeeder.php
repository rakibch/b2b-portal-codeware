<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'seller' => [
                'seller.name' => ['en' => 'Codeware Wholesale Ltd.', 'bn' => 'কোডওয়্যার হোলসেল লিমিটেড'],
                'seller.address' => [
                    'en' => 'House 12, Road 7, Block C, Tejgaon I/A, Dhaka 1208, Bangladesh',
                    'bn' => 'বাড়ি ১২, রোড ৭, ব্লক সি, তেজগাঁও শিল্প এলাকা, ঢাকা ১২০৮, বাংলাদেশ',
                ],
                'seller.phone' => '+880 1700-000000',
                'seller.email' => 'sales@example.com',
                'seller.bin' => '000123456-0101',
                'seller.tin' => '123456789012',
                'seller.trade_license' => 'TRAD/DNCC/012345/2026',
            ],
            'invoice' => [
                'invoice.footer_note' => [
                    'en' => 'Goods once sold are subject to the return policy. This is a computer-generated document.',
                    'bn' => 'বিক্রিত পণ্য রিটার্ন নীতিমালা সাপেক্ষে। এটি একটি কম্পিউটার-জেনারেটেড ডকুমেন্ট।',
                ],
                'invoice.bank_details' => [
                    'en' => 'Bank: Example Bank PLC, A/C: 0123456789012, Branch: Tejgaon, Routing: 123456789',
                    'bn' => 'ব্যাংক: এক্সাম্পল ব্যাংক পিএলসি, হিসাব নং: ০১২৩৪৫৬৭৮৯০১২, শাখা: তেজগাঁও, রাউটিং: ১২৩৪৫৬৭৮৯',
                ],
            ],
            'orders' => [
                'orders.cod_max_amount' => '200000.00',
                'orders.min_order_amount' => '1000.00',
                'orders.free_shipping_threshold' => '20000.00',
                'orders.flat_shipping_fee' => '500.00',
            ],
        ];

        foreach ($settings as $group => $items) {
            foreach ($items as $key => $value) {
                Setting::put($key, $value, $group);
            }
        }
    }
}
