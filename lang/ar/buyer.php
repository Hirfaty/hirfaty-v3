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

    // Sign-in gate
    'detail.login_to_buy' => 'سجّل الدخول للشراء',
    'detail.login_to_buy_hint' => 'سلتك وأسعارك مرتبطة بحسابك.',
    'detail.login_to_buy_register' => 'إنشاء حساب',
    'detail.below_min_qty' => 'الكمية المتوفرة أقل من حدّك الأدنى للطلب',
    'detail.below_min_stock' => 'بقي :count فقط — الحد الأدنى لطلبك :min',

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

    // ── التعريف بالمنصة (أول زيارة للمشتري) ──────────────────────────────
    'onboarding.title' => 'مرحباً بك في حرفتي',
    'onboarding.skip' => 'تخطي',
    'onboarding.back' => 'رجوع',
    'onboarding.next' => 'متابعة',
    'onboarding.finish' => 'اكتشف السوق',

    'onboarding.welcome.quote' => 'خلف كل قطعة يدان وحرفة متوارثة.',
    'onboarding.welcome.title' => 'مرحباً، :name 👋',
    'onboarding.welcome.body' => 'حرفتي تجمع حرفيي المغرب في مكان واحد. دقيقتان للتعارف، ثم السوق بين يديك.',
    'onboarding.welcome.point_direct' => 'تشتري مباشرة من الحرفي — بدون وسيط.',
    'onboarding.welcome.point_authentic' => 'كل قطعة مصنوعة يدوياً، وكل متجر تتحقق منه فريقنا.',
    'onboarding.welcome.point_delivery' => 'التوصيل إلى كل أنحاء المغرب، مع تتبع من صفحة طلباتك.',

    'onboarding.interests.quote' => 'خزف آسفي، زرابي تازناخت، جلد فاس — للمغرب ألف حرفة.',
    'onboarding.interests.title' => 'ما الذي يجذبك؟',
    'onboarding.interests.body' => 'اختر الحرف التي تهمك، وسنستعملها لإبراز ما يناسبك.',
    'onboarding.interests.optional' => 'اختياري — يمكنك تغيير رأيك في أي وقت.',

    'onboarding.professional.quote' => 'رياضات وفنادق ومتاجر: يؤثثون فضاءاتهم من عند حرفيينا.',
    'onboarding.professional.title' => 'هل تشتري لحساب مؤسسة؟',
    'onboarding.professional.body' => 'رياض، فندق، مطعم، متجر… إذا كنت تطلب بكميات، يمكن أن يصبح حسابك مهنياً.',
    'onboarding.professional.perk' => 'تستفيد الحسابات المهنية من أسعار الجملة. يراجع فريقنا كل طلب قبل تفعيله.',
    'onboarding.professional.cta' => 'اطلب حساباً مهنياً',
    'onboarding.professional.label' => 'حدثنا عن نشاطك',
    'onboarding.professional.placeholder' => 'مثال: أدير رياضاً من 8 غرف في مراكش وأبحث عن مفروشات وأوانٍ مصنوعة يدوياً بكميات.',
    'onboarding.professional.hint' => '20 حرفاً على الأقل. يمكنك مواصلة الطلب كالمعتاد أثناء مراجعة طلبك.',

    // ── الحساب المهني (صفحة مستقلة) ──────────────────────────────────────
    'professional.title' => 'الحساب المهني',
    'professional.intro' => 'رياض، فندق، مطعم، متجر: إذا كنت تشتري بكميات، اطلب الوصول إلى أسعار الجملة.',
    'professional.label' => 'حدثنا عن نشاطك',
    'professional.submit' => 'إرسال الطلب',
    'professional.pending_title' => 'الطلب قيد المراجعة',
    'professional.pending_body' => 'يدرس فريقنا طلبك. ستصلك رسالة بمجرد اتخاذ القرار — ويمكنك مواصلة الطلب كالمعتاد في انتظار ذلك.',
    'professional.approved_title' => 'الحساب المهني مفعّل',
    'professional.approved_body' => 'تُطبَّق أسعار الجملة تلقائياً على المنتجات المعنية.',
    'professional.declined_title' => 'لم يتم قبول الطلب',
    'professional.declined_reason' => 'السبب',
    'professional.declined_retry' => 'يمكنك تقديم طلب جديد.',
    'professional.submitted_on' => 'أُرسل في :date',

    // ── مساحة الحساب: أربع صفحات تتقاسم الشريط الجانبي ────────────────────
    'account.title' => 'حسابي',
    'account.subtitle' => 'معلوماتك وطلباتك والقطع التي احتفظت بها.',

    'account.nav_overview' => 'حسابي',
    'account.nav_orders' => 'الطلبات',
    'account.nav_wishlist' => 'المفضلة',
    'account.nav_payment' => 'الأداء',
    'account.nav_professional' => 'الحساب المهني',
    'account.logout' => 'تسجيل الخروج',

    'account.orders_intro' => 'تُقسَّم السلة إلى طلب لكل ورشة، لذا قد تظهر عملية شراء واحدة هنا أكثر من مرة.',
    'account.wishlist_intro' => 'القطع التي احتفظت بها، تبقى إلى أن تحذفها.',
    'account.payment_intro' => 'البطاقات ووسائل الأداء المستعملة لتسوية طلباتك.',
    'account.professional_intro' => 'اطلب الوصول المهني، أو تحقق من حالة طلب سبق إرساله.',

    'account.placeholder' => 'لا شيء لعرضه هنا بعد.',

    // ── ‎/account: الهوية وكلمة المرور والعنوان الافتراضي ─────────────────
    'account.section_identity' => 'المعلومات الشخصية',
    'account.section_identity_desc' => 'الاسم الذي تُحرَّر به طلباتك وسندات التسليم.',
    'account.section_password' => 'كلمة المرور',
    'account.section_password_desc' => 'اختر كلمة مرور جديدة. ستبقى متصلاً على هذا الجهاز.',
    'account.section_address' => 'عنوان التوصيل الافتراضي',
    'account.section_address_desc' => 'المكان الذي تُرسل إليه طلباتك. يملؤه الأداء تلقائياً.',

    'account.field_full_name' => 'الاسم الكامل',
    'account.field_first_name' => 'الاسم الشخصي',
    'account.field_last_name' => 'الاسم العائلي',
    'account.field_email' => 'البريد الإلكتروني',
    'account.field_phone' => 'الهاتف',
    'account.locked_note' => 'لا يمكن تغيير بريدك الإلكتروني ورقم هاتفك من هنا حالياً — تواصل معنا وسنحدّثهما لك.',

    'account.field_password' => 'كلمة المرور',
    'account.change_password' => 'تغيير كلمة المرور',
    'account.field_current_password' => 'كلمة المرور الحالية',
    'account.field_new_password' => 'كلمة المرور الجديدة',
    'account.field_confirm_password' => 'تأكيد كلمة المرور الجديدة',

    'account.no_address' => 'لا يوجد عنوان محفوظ بعد.',
    'account.add_address' => 'إضافة عنوان',
    'account.field_address_label' => 'اسم العنوان',
    'account.field_address_label_hint' => 'اختياري — لتسهيل تمييزه.',
    'account.field_address_label_ph' => 'المنزل، رياض، متجر…',
    'account.field_city' => 'المدينة',
    'account.field_city_ph' => 'اختر مدينة',
    'account.field_address_line' => 'العنوان',
    'account.field_address_line_ph' => 'رقم 12، زنقة الخزافين، الحي…',
    'account.field_recipient' => 'المستلم',
    'account.field_recipient_phone' => 'هاتف المستلم',

    'account.saved' => 'تم الحفظ.',
    'account.update_password' => 'تحديث كلمة المرور',
    'account.password_saved' => 'تم تحديث كلمة المرور.',
    'account.save_address' => 'حفظ العنوان',

    'account.profile_saved' => 'تم تحديث معلوماتك.',
    'account.address_saved' => 'تم حفظ عنوان التوصيل.',
    'account.address_default_saved' => 'تم تحديث عنوان التوصيل الافتراضي.',
    'account.address_deleted' => 'تم حذف العنوان.',

    'account.err_first_name' => 'أدخل اسمك الشخصي.',
    'account.err_last_name' => 'أدخل اسمك العائلي.',
    'account.err_address_line' => 'أدخل عنوان التوصيل.',
    'account.err_phone' => 'أدخل رقم هاتف للتوصيل.',
    'account.err_city' => 'اختر مدينة.',

    // ── المفضلة ──────────────────────────────────────────────────────────
    'wishlist.title' => 'مفضلتي',

    // ── السلة ────────────────────────────────────────────────────────────
    'cart.title' => 'سلتك',
    'cart.back' => 'تابع التصفح',
    'cart.count_one' => 'منتج واحد',
    'cart.count_many' => ':count منتجات',

    'cart.empty_title' => 'سلتك فارغة',
    'cart.empty_body' => 'تصفّح السوق ودع أعمال حرفيينا تفاجئك.',
    'cart.empty_cta' => 'اكتشف السوق',

    'cart.sold_by' => 'من :store',
    'cart.remove' => 'إزالة',
    'cart.decrease' => 'إنقاص الكمية',
    'cart.increase' => 'زيادة الكمية',
    'cart.per_unit' => 'للقطعة',
    'cart.stock_left' => 'بقي :count فقط',
    'cart.min_qty' => 'الحد الأدنى للطلب: :count',
    'cart.unpriced' => 'السعر قيد التأكيد — أزل هذا المنتج للمتابعة.',

    'cart.summary' => 'الملخص',
    'cart.subtotal' => 'المجموع الفرعي',
    'cart.savings' => 'التوفير',
    'cart.delivery' => 'التوصيل عبر أمانة',
    'cart.delivery_one_store' => 'ورشة واحدة',
    'cart.delivery_many_stores' => ':count ورشات',
    'cart.total' => 'المجموع',
    'cart.checkout' => 'إتمام الطلب',
    'cart.secure_payment' => 'دفع آمن',
    'cart.split_note' => 'ستُقسَّم سلتك إلى طلب لكل ورشة.',

    // ── إتمام الطلب ──────────────────────────────────────────────────────
    'checkout.title' => 'إتمام الطلب',
    'checkout.back' => 'العودة إلى السلة',

    'checkout.delivery_heading' => 'التوصيل',
    'checkout.saved_addresses' => 'عناوينك المحفوظة',
    'checkout.default_badge' => 'الافتراضي',
    'checkout.use_new_address' => 'استخدام عنوان آخر',
    'checkout.new_address' => 'عنوان جديد',
    'checkout.city' => 'المدينة',
    'checkout.address_line' => 'العنوان الكامل',
    'checkout.address_placeholder' => 'الشارع، الحي، رقم المنزل…',
    'checkout.phone' => 'الهاتف',
    'checkout.phone_hint' => 'سيتصل بك موصّل أمانة على هذا الرقم.',
    'checkout.phone_placeholder' => '06 12 34 56 78',
    'checkout.save_address' => 'احفظ هذا العنوان في دفتر عناويني',
    'checkout.address_label' => 'اسم العنوان',
    'checkout.address_label_placeholder' => 'المنزل، المكتب…',
    'checkout.notes' => 'ملاحظات',
    'checkout.optional' => 'اختياري',
    'checkout.notes_placeholder' => 'تعليمات للموصّل…',

    'checkout.shipments_heading' => 'تفاصيل الشحنات',
    'checkout.shipments_note_one' => 'سلتك من ورشة واحدة: ستصلك طرد واحد.',
    'checkout.shipments_note_many' => 'سلتك من :count ورشات: ستصلك :count طرود منفصلة.',
    'checkout.shipped_by' => 'شحن بواسطة :store',
    'checkout.quantity' => 'الكمية: :count',
    'checkout.amana_standard' => 'توصيل أمانة (عادي)',

    'checkout.summary' => 'ملخص الطلب',
    'checkout.products' => 'المنتجات (:count)',
    'checkout.delivery_fee' => 'رسوم التوصيل (:count طرود)',
    'checkout.total' => 'المجموع',
    'checkout.cod_note' => 'الدفع عند الاستلام. تدفع مباشرةً لموصّل أمانة عند وصول طرودك.',
    'checkout.confirm' => 'تأكيد الطلب',
    'checkout.processing' => 'جارٍ المعالجة…',

    'checkout.err_address' => 'يرجى تحديد عنوان التوصيل.',
    'checkout.err_city' => 'اختر مدينة.',
    'checkout.err_phone' => 'رقم الهاتف مطلوب للتوصيل.',
    'checkout.err_empty' => 'سلتك فارغة.',
    'checkout.placed' => 'تم تسجيل :count طلب بنجاح!',
];
