<?php

// Flat keys — dots are part of the key, NOT nested arrays. Keep in sync with
// lang/fr/buyer.php and lang/en/buyer.php.
//
// This locale renders RTL (SetLocale sets direction => 'rtl' for 'ar'), and
// app.css overrides the display font for [lang='ar'] — Fraunces and Inter
// carry no Arabic glyphs. Arabic must never be italicised, so the listing
// avoids italic styling on any string coming from this file.

return [
    // ── Product listing ───────────────────────────────────────────
    'products.hero_title_l1' => 'أصيل.',
    'products.hero_title_l2' => 'مصنوع يدوياً.',
    'products.hero_title_l3' => 'صُنع بشغف.',
    'products.hero_subtitle' => 'اكتشف قطعاً فريدة صنعتها أيادٍ ماهرة.',
    'products.feature_handmade' => 'صناعة يدوية',
    'products.feature_handmade_sub' => 'بعناية',
    'products.feature_natural' => 'مواد',
    'products.feature_natural_sub' => 'طبيعية',
    'products.feature_unique' => 'فريد',
    'products.feature_unique_sub' => 'وأصيل',
    'products.feature_artisans' => 'دعم',
    'products.feature_artisans_sub' => 'الحرفيين',
    'products.hero_slide_pottery' => 'خزّاف مغربي يشكّل قطعة في ورشته',
    'products.hero_slide_textile' => 'نسج الصوف على نول تقليدي',
    'products.hero_slide_zellige' => 'زليج منحوت يدوياً بزخرفة هندسية',
    'products.hero_slide_spice' => 'توابل مغربية معروضة في السوق',

    'products.search_placeholder' => 'ابحث عن قطعة أو ورشة…',
    'products.search_label' => 'البحث عن منتج',
    'products.search_clear' => 'مسح البحث',

    'products.refine' => 'تصفية',
    'products.refine_open' => 'فتح',
    'products.sort' => 'ترتيب',
    'products.sort_score' => 'الأنسب',
    'products.sort_latest' => 'الجديد',
    'products.sort_price_asc' => 'السعر: من الأقل',
    'products.sort_price_desc' => 'السعر: من الأعلى',
    'products.sort_rating' => 'الأعلى تقييماً',

    'products.price' => 'السعر',
    'products.price_max_label' => 'السعر الأقصى',
    'products.in_stock_only' => 'المتوفر فقط',
    'products.reset_all' => 'إعادة تعيين الكل',

    'products.badge_sale' => 'تخفيض',
    'products.badge_sold_out' => 'نفدت الكمية',
    'products.no_image' => 'بدون صورة',

    'products.empty_with_search' => 'لا توجد قطعة تطابق بحثك.',
    'products.empty' => 'لا توجد قطعة تطابق عوامل التصفية.',

    'products.load_more' => 'عرض المزيد',
    'products.loading' => 'جارٍ التحميل…',
    'products.count_of' => 'من',

    // ── Professional / trade pricing ──────────────────────────────
    'products.pro_short' => 'محترف*',
    'products.pro_from_qty' => 'ابتداءً من',
    'products.pro_price_hint' => 'سعر احترافي إرشادي — الفوترة تبقى بالسعر العام حالياً.',
    'products.pro_notice_title' => 'حساب احترافي.',
    'products.pro_notice_body' => 'تظهر أسعارك الاحترافية أسفل السعر العام. وهي إرشادية: الفوترة تبقى بالسعر العام حالياً.',
    'products.pro_cta_body' => 'تشتري لرياض أو فندق أو متجر؟ تتوفر أسعار احترافية على جزء كبير من الكتالوج.',
    'products.pro_cta_button' => 'افتح حساباً احترافياً',

    // ── Product detail ────────────────────────────────────────────
    'detail.back_to_market' => 'السوق',
    'detail.add_to_cart' => 'أضف إلى السلة',
    'detail.go_to_cart' => 'عرض السلة',
    'detail.sold_out' => 'نفدت الكمية',
    'detail.in_stock' => 'متوفر',
    'detail.only_left' => 'بقي :count فقط',
    'detail.quantity' => 'الكمية',
    'detail.decrease' => 'إنقاص',
    'detail.increase' => 'زيادة',
    'detail.wishlist_add' => 'أضف إلى المفضلة',
    'detail.wishlist_remove' => 'إزالة من المفضلة',
    'detail.description' => 'الوصف',
    'detail.no_description' => 'لا يوجد وصف.',
    'detail.delivery' => 'التوصيل عبر أمانة خلال 3 إلى 7 أيام في كل المغرب.',
    'detail.authentic' => 'قطعة أصيلة بضمان الورشة.',
    'detail.view_media' => 'عرض الوسائط :n',

    // Seller card
    'detail.sold_by' => 'يُباع من طرف',
    'detail.visit_store' => 'زيارة المتجر',
    'detail.more_from_store' => 'قطع أخرى من الورشة',

    // Pricing
    'detail.public_price' => 'السعر العام',
    'detail.your_trade_price' => 'سعرك الاحترافي',
    'detail.trade_saving' => 'توفير :percent%',
    'detail.trade_min_qty' => 'الحد الأدنى للطلب: :count قطع',
    'detail.trade_billed_note' => 'يُطبَّق هذا السعر تلقائياً عند الدفع.',
    'detail.trade_cta_title' => 'تشتري بالجملة؟',
    'detail.trade_cta_body' => 'تحصل الحسابات الاحترافية الموثّقة على أسعار خاصة في جزء كبير من الكتالوج.',
    'detail.trade_cta_button' => 'افتح حساباً احترافياً',
    'detail.trade_pending_title' => 'حساب احترافي في انتظار التوثيق.',
    'detail.trade_pending_body' => 'ستُطبَّق أسعارك الاحترافية بمجرد موافقة المشرف على حسابك.',

    // Variants
    'detail.select_variant_prompt' => 'اختر خياراً',

    // Add to cart feedback
    'detail.adding' => 'أُضيف إلى السلة',
    'detail.added_to_cart' => 'أُضيف إلى سلتك',
    'detail.add_failed' => 'تعذّرت الإضافة إلى السلة.',

    // Page furniture
    'detail.home' => 'الرئيسية',
    'detail.eyebrow_handmade' => 'صناعة يدوية',
    'detail.related' => 'قد يعجبك أيضاً',
    'detail.handmade_note' => 'كل قطعة مصنوعة يدوياً، لذا قد تختلف الألوان والنقوش قليلاً، وهو ما يجعل قطعتك فريدة.',

    // Reassurance row
    'detail.trust_materials_title' => 'مواد طبيعية',
    'detail.trust_materials_body' => 'طين عالي الجودة وطلاء طبيعي.',
    'detail.trust_handmade_title' => 'صناعة يدوية',
    'detail.trust_handmade_body' => 'كل قطعة تُشكَّل باليد.',
    'detail.trust_durable_title' => 'متين',
    'detail.trust_durable_body' => 'خزف محروق على حرارة عالية ليدوم طويلاً.',
    'detail.trust_packaging_title' => 'تغليف آمن',
    'detail.trust_packaging_body' => 'يُغلَّف بعناية لضمان وصول سالم.',
];
