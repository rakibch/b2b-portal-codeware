<?php

namespace Database\Seeders;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $announcements = [
            [
                'title' => ['en' => 'Eid delivery schedule', 'bn' => 'ঈদের ডেলিভারি সময়সূচি'],
                'body' => [
                    'en' => 'Orders confirmed after 5 PM on the 25th will be delivered after the Eid holidays.',
                    'bn' => '২৫ তারিখ বিকেল ৫টার পর নিশ্চিত হওয়া অর্ডার ঈদের ছুটির পরে ডেলিভারি দেওয়া হবে।',
                ],
                'audience' => AnnouncementAudience::Buyers,
                'level' => 'warning',
            ],
            [
                'title' => ['en' => 'New volume tiers on edible oil', 'bn' => 'ভোজ্য তেলে নতুন পরিমাণভিত্তিক মূল্য'],
                'body' => [
                    'en' => 'Order 100+ bottles of 5 L soybean oil and save up to ৳45 per bottle.',
                    'bn' => '৫ লিটার সয়াবিন তেল ১০০+ বোতল অর্ডারে প্রতি বোতলে ৳৪৫ পর্যন্ত সাশ্রয় করুন।',
                ],
                'audience' => AnnouncementAudience::All,
                'level' => 'success',
            ],
        ];

        foreach ($announcements as $data) {
            $announcement = Announcement::query()->firstOrNew(['audience' => $data['audience']->value, 'level' => $data['level']]);
            $announcement->fill($data + ['is_published' => true, 'published_at' => now()]);
            $announcement->save();
        }
    }
}
