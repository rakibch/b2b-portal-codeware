<?php

return [
    'greeting' => 'প্রিয় :name,',
    'salutation' => 'শুভেচ্ছান্তে, :app',

    'kyc_submitted' => [
        'subject' => 'নতুন কেওয়াইসি জমা: :company',
        'body' => ':company পর্যালোচনার জন্য ব্যবসা যাচাইয়ের কাগজপত্র জমা দিয়েছে।',
        'action' => 'কেওয়াইসি পর্যালোচনা করুন',
    ],
    'kyc_approved' => [
        'subject' => 'আপনার ব্যবসায়িক অ্যাকাউন্ট অনুমোদিত হয়েছে',
        'body' => 'সুখবর! :company যাচাই সম্পন্ন হয়েছে। এখন আপনি পাইকারি মূল্য দেখতে ও অর্ডার করতে পারবেন।',
        'action' => 'অর্ডার শুরু করুন',
    ],
    'kyc_rejected' => [
        'subject' => 'ব্যবসা যাচাইয়ে আপনার মনোযোগ প্রয়োজন',
        'body' => ':company যাচাই করা সম্ভব হয়নি। কারণ: :reason। অনুগ্রহ করে কাগজপত্র হালনাগাদ করে আবার জমা দিন।',
        'action' => 'পুনরায় জমা দিন',
    ],
    'kyc_suspended' => [
        'subject' => 'আপনার ব্যবসায়িক অ্যাকাউন্ট স্থগিত করা হয়েছে',
        'body' => ':company এর অ্যাকাউন্ট স্থগিত করা হয়েছে। কারণ: :reason।',
        'action' => 'অ্যাকাউন্ট দেখুন',
    ],
    'rfq_submitted' => [
        'subject' => 'নতুন মূল্য অনুরোধ :reference',
        'body' => ':company একটি মূল্য প্রস্তাব (:reference) চেয়েছে।',
        'action' => 'মূল্য প্রস্তাব তৈরি করুন',
    ],
    'rfq_quoted' => [
        'subject' => 'আপনার মূল্য প্রস্তাব :reference প্রস্তুত',
        'body' => 'আপনার অনুরোধ :reference এর মূল্য প্রস্তুত, যা :valid_until পর্যন্ত বৈধ।',
        'action' => 'মূল্য প্রস্তাব দেখুন',
    ],
    'rfq_declined' => [
        'subject' => 'আপনার মূল্য অনুরোধ :reference এর হালনাগাদ',
        'body' => 'অনুরোধ :reference এর জন্য মূল্য প্রস্তাব দেওয়া সম্ভব হচ্ছে না। মন্তব্য: :reason',
        'action' => 'অনুরোধ দেখুন',
    ],
    'rfq_responded' => [
        'subject' => 'মূল্য প্রস্তাব :reference — :status',
        'body' => ':company মূল্য প্রস্তাব :reference এর উত্তর দিয়েছে: :status।',
        'action' => 'প্রস্তাব খুলুন',
    ],
    'company_invitation' => [
        'subject' => ':company এ যোগ দেওয়ার আমন্ত্রণ',
        'body' => ':inviter আপনাকে আমাদের পাইকারি পোর্টালে :company এর পক্ষে অর্ডার করার জন্য আমন্ত্রণ জানিয়েছেন। এই আমন্ত্রণের মেয়াদ :hours ঘণ্টা।',
        'action' => 'আমন্ত্রণ গ্রহণ করুন',
    ],
    'order_placed' => [
        'subject' => ':company থেকে নতুন অর্ডার :number',
        'body' => ':company :total মূল্যের অর্ডার :number দিয়েছে।',
        'action' => 'অর্ডার দেখুন',
    ],
    'order_approval_requested' => [
        'subject' => 'অর্ডার :number আপনার অনুমোদনের অপেক্ষায়',
        'body' => ':name :total মূল্যের অর্ডার :number দিয়েছেন, যা তাঁর অনুমোদন সীমার বেশি।',
        'action' => 'দেখে অনুমোদন দিন',
    ],
    'order_status_changed' => [
        'subject' => 'অর্ডার :number এখন :status',
        'body' => 'আপনার অর্ডার :number এর অবস্থা পরিবর্তিত হয়েছে: :status।',
        'action' => 'অর্ডার ট্র্যাক করুন',
    ],
    'payment_submitted' => [
        'subject' => 'পেমেন্ট :reference যাচাইয়ের অপেক্ষায়',
        'body' => ':company :amount পেমেন্ট (:reference) জমা দিয়েছে।',
        'action' => 'পেমেন্ট যাচাই করুন',
    ],
    'payment_verified' => [
        'subject' => 'পেমেন্ট :reference গৃহীত হয়েছে',
        'body' => 'আপনার :amount পেমেন্ট (:reference) গৃহীত ও যাচাই করা হয়েছে। ধন্যবাদ!',
        'action' => 'হিসাব বিবরণী দেখুন',
    ],
    'payment_rejected' => [
        'subject' => 'পেমেন্ট :reference যাচাই করা যায়নি',
        'body' => 'আপনার :amount পেমেন্ট (:reference) যাচাই করা যায়নি। কারণ: :reason',
        'action' => 'পেমেন্ট দেখুন',
    ],
    'invoice_overdue' => [
        'subject' => 'ইনভয়েস :number এর পরিশোধের মেয়াদ পেরিয়েছে',
        'body' => ':amount বকেয়াসহ ইনভয়েস :number এর পরিশোধের তারিখ ছিল :due_date। ক্রেডিট সুবিধা চালু রাখতে অনুগ্রহ করে পরিশোধ করুন।',
        'action' => 'এখনই পরিশোধ করুন',
    ],
];
