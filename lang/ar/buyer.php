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
    'products.hero_eyebrow' => 'صُنع على مهل · يُتوارث عبر الأجيال',
    'products.hero_title' => 'اكتشف الحرف المغربية',
    'products.hero_subtitle' => 'قطع مصنوعة يدوياً على يد حرفيين مغاربة، تحمل طابع حرفتهم إلى بيتك.',

    'products.collection_eyebrow' => 'المجموعة الكاملة',
    'products.collection_title' => 'قطع لها حكاية',
    'products.category_all' => 'الكل',
    'products.categories' => 'الفئات',
    'products.count_pieces' => 'قطعة مصنوعة يدوياً',
    'products.made_in_morocco' => 'صنع في المغرب',

    'products.search_placeholder' => 'ابحث عن منتج مصنوع يدوياً…',
    'products.search_label' => 'البحث عن منتج',

    'products.reset_all' => 'إعادة تعيين الكل',

    'products.view_product' => 'عرض القطعة',
    'products.wishlist_add' => 'أضف إلى المفضلة',
    'products.wishlist_remove' => 'أزل من المفضلة',

    'products.badge_sale' => 'تخفيض',
    'products.badge_sold_out' => 'نفدت الكمية',
    'products.no_image' => 'بدون صورة',
    'products.price_coming_soon' => 'السعر قريباً',

    'products.empty_with_search' => 'لا توجد قطعة تطابق بحثك.',
    'products.empty' => 'لا توجد قطعة تطابق عوامل التصفية.',

    'products.load_more' => 'عرض المزيد',
    'products.loading' => 'جارٍ التحميل…',

    'products.closing_title' => 'الحرفة تحمل الذاكرة.',
    'products.closing_subtitle' => 'مختارة بعناية من ورشات مستقلة في مختلف أنحاء المغرب.',

    // ── Professional / trade pricing ──────────────────────────────
    'products.pro_short' => 'محترف*',
    'products.pro_from_qty' => 'ابتداءً من',
    'products.pro_price_hint' => 'سعرك الاحترافي — وهو ما يفوتره الدفع.',

    // ── الجديد ────────────────────────────────────────────────────
    'new.title' => 'الجديد',
    'new.eyebrow' => 'أُضيف للتو',
    'new.heading' => 'طازج من الورشات',
    'new.lead' => 'أحدث القطع التي نشرها حرفيونا، الأجدّ أولاً.',
    'new.browse_all' => 'تصفّح المجموعة الكاملة',
    'new.empty' => 'لم يُنشر شيء بعد — عُد قريباً جداً.',

    // ── سجل الطلبات ───────────────────────────────────────────────
    'orders.status_pending' => 'قيد الانتظار',
    'orders.status_confirmed' => 'مؤكد',
    'orders.status_shipped' => 'تم الشحن',
    'orders.status_delivered' => 'تم التسليم',
    'orders.status_cancelled' => 'ملغى',

    // البطاقة الواحدة = طلب واحد، وقد يشمل عدة ورشات — انظر
    // BuyerAccountService::purchases().
    'orders.shops_count' => ':count ورشات',
    'orders.items_total' => 'القطع',
    'orders.delivery' => 'التوصيل',
    'orders.total' => 'المجموع',

    // Order card — pages/buyer/orders/index.tsx.
    'orders.number' => 'الطلب رقم :reference',
    'orders.products_one' => 'منتج واحد',
    'orders.products_many' => ':count منتجات',
    'orders.by' => 'بواسطة :name',
    'orders.status' => 'الحالة',
    'orders.delivery_date' => 'تاريخ التسليم',
    'orders.delivery_pending' => 'سيتم تأكيده',
    'orders.delivered_to' => 'التسليم إلى',
    'orders.quantity' => 'الكمية',
    'orders.variant' => 'الخيار',
    'orders.workshop' => 'الورشة',
    'orders.download_invoice' => 'تحميل الفاتورة',
    'orders.more_actions' => 'إجراءات أخرى',
    'orders.copy_reference' => 'نسخ المرجع',
    'orders.reference_copied' => 'تم نسخ المرجع',
    'orders.contact' => 'التواصل مع خدمة العملاء',
    'orders.track' => 'سجل الطلب',
    'orders.rate' => 'قيّم منتجاتي',
    'orders.my_reviews' => 'عرض تقييماتي',
    'orders.back' => 'العودة إلى طلباتي',

    'tracking.title' => 'تتبع الطلب',
    'tracking.intro' => 'كل مراحل طلبك، من التأكيد إلى التسليم.',
    'tracking.step_placed' => 'تم الطلب',
    'tracking.step_confirmed' => 'تم التأكيد',
    'tracking.step_shipped' => 'تم الشحن',
    'tracking.step_delivered' => 'تم التسليم',
    'tracking.history' => 'السجل',
    'tracking.event_pending' => 'تم استلام الطلب',
    'tracking.event_confirmed' => 'تم تأكيد الطلب مع الحرفيين',
    'tracking.event_shipped' => 'تم تسليم الطرد لشركة النقل',
    'tracking.event_delivered' => 'تم تسليم الطرد',
    'tracking.event_cancelled' => 'تم إلغاء الطلب',
    'tracking.event_update' => 'تحديث الشحن',
    'tracking.carrier' => 'شركة النقل',
    'tracking.tracking_number' => 'رقم التتبع',
    'tracking.only' => 'يخص: :names',
    'tracking.cancelled_notice' => 'تم إلغاء هذا الطلب.',

    'rate.title' => 'قيّم منتجاتي',
    'rate.intro' => 'تقييم ورأي لكل قطعة استلمتها — فذلك يساعد الحرفيين والمشترين الآخرين.',
    'rate.comment_placeholder' => 'ما رأيك في هذه القطعة؟ الجودة، التشطيب، مطابقتها للصور…',
    'rate.not_delivered' => 'يمكنك تقييمها بعد التسليم',
    'rate.submit' => 'إرسال تقييماتي',
    'rate.all_done' => 'شكرًا! تم تقييم جميع القطع التي استلمتها.',

    // الحالة الفارغة — components/buyer/orders/EmptyOrders.tsx.
    'orders.empty_eyebrow' => 'مرحباً',
    'orders.empty_title' => 'جاهز للتسوق؟',
    'orders.empty_cta' => 'تسوّق الآن',

    // ── التقييمات ─────────────────────────────────────────────────
    // تُعرض فقط على طلب تم تسليمه، ومرّة واحدة لكل قطعة — انظر ReviewService.
    // لم تعد الورشة نفسها تُقيَّم.
    'reviews.heading' => 'كيف كانت التجربة؟',
    'reviews.all_rated_heading' => 'تقييمك',
    'reviews.rate_product' => 'قيّم هذه القطعة',
    'reviews.rated' => 'تم التقييم',
    'reviews.star_label' => ':count من 5',
    'reviews.comment_placeholder' => 'هل تودّ إضافة شيء؟ (اختياري)',
    'reviews.final_notice' => 'لا يمكن تعديل التقييم بعد إرساله.',
    'reviews.submit' => 'إرسال',
    'reviews.thanks' => 'شكراً لك.',

    // النافذة التي تظهر عند الدخول إذا بقي طلب مُسلَّم بلا تقييم.
    'reviews.prompt_title' => 'وصل طلبك',
    'reviews.prompt_body' => 'أخبرنا برأيك في القطع التي استلمتها. لن يستغرق الأمر سوى لحظة، وهو يساعد الحرفي.',
    'reviews.prompt_body_plural' => 'لديك :count طلبات مُسلَّمة بانتظار التقييم. لن يستغرق الأمر سوى لحظة، وهو يساعد الحرفيين.',
    'reviews.prompt_cta' => 'تقييم طلبي',
    'reviews.prompt_later' => 'لاحقاً',

    // ── Product detail ────────────────────────────────────────────
    'detail.back_to_market' => 'السوق',
    'detail.add_to_cart' => 'أضف إلى السلة',
    'detail.buy_now' => 'اشترِ الآن',
    'detail.go_to_cart' => 'عرض السلة',
    'detail.sold_out' => 'نفدت الكمية',
    'detail.price_coming_soon' => 'السعر قريباً',
    'detail.in_stock' => 'متوفر',
    'detail.quantity' => 'الكمية',
    'detail.decrease' => 'إنقاص',
    'detail.increase' => 'زيادة',
    'detail.wishlist_add' => 'أضف إلى المفضلة',
    'detail.wishlist_remove' => 'إزالة من المفضلة',
    'detail.description' => 'الوصف',
    'detail.no_description' => 'لا يوجد وصف.',
    'detail.delivery' => 'التوصيل خلال 3 إلى 7 أيام في المتوسط داخل المغرب، و7 إلى 14 يومًا خارجه.',
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
    'detail.buy_now_failed' => 'تعذّر الانتقال إلى الدفع بهذه القطعة.',

    // Page furniture
    'detail.home' => 'الرئيسية',
    'detail.eyebrow_handmade' => 'صناعة يدوية',
    'detail.related' => 'قد يعجبك أيضاً',
    'detail.reviews_heading' => 'آراء المشترين',
    'detail.reviews_count_one' => 'تقييم واحد',
    'detail.reviews_count' => ':n تقييمات',
    'detail.reviews_out_of' => 'من 5',
    'detail.reviews_stars_label' => ':n من 5 نجوم',
    'detail.reviews_star_row' => ':n نجوم',
    'detail.reviews_empty' => 'لا توجد تقييمات لهذه القطعة بعد.',
    'detail.reviews_empty_hint' => 'يترك المشترون تقييماتهم بعد استلام طلباتهم.',
    'detail.reviews_verified' => 'شراء موثّق',
    'detail.reviews_anonymous' => 'مشتري من حرفتي',
    'detail.reviews_more' => 'عرض المزيد من التقييمات',
    'detail.handmade_note' => 'كل قطعة مصنوعة يدوياً، لذا قد تختلف الألوان والنقوش قليلاً، وهو ما يجعل قطعتك فريدة.',
    'detail.specs_heading' => 'الوزن والأبعاد (مع التغليف)',
    'detail.specs_weight' => 'الوزن',
    'detail.specs_dimensions' => 'الأبعاد (الطول × العرض × الارتفاع)',
    'detail.specs_length' => 'الطول',
    'detail.specs_width' => 'العرض',
    'detail.specs_height' => 'الارتفاع',

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
    'onboarding.title' => 'مرحباً بك',
    'onboarding.skip' => 'تخطي',
    'onboarding.back' => 'رجوع',
    'onboarding.next' => 'متابعة',
    'onboarding.finish' => 'اكتشف السوق',

    'onboarding.welcome.title' => 'مرحباً، :name',
    'onboarding.welcome.title_generic' => 'مرحباً',
    'onboarding.welcome.body' => 'حرفتي تجمع حرفيي المغرب في مكان واحد. دقيقتان للتعارف، ثم السوق بين يديك.',
    'onboarding.welcome.point_direct' => 'تشتري مباشرة من الحرفي — بدون وسيط.',
    'onboarding.welcome.point_authentic' => 'كل قطعة مصنوعة يدوياً، وكل متجر تتحقق منه فريقنا.',
    'onboarding.welcome.point_delivery' => 'التوصيل إلى كل أنحاء المغرب، مع تتبع من صفحة طلباتك.',

    'onboarding.interests.title' => 'ما الذي يجذبك؟',
    'onboarding.interests.body' => 'اختر الحرف التي تهمك، وسنستعملها لإبراز ما يناسبك.',
    'onboarding.interests.optional' => 'اختياري — يمكنك تغيير رأيك في أي وقت.',

    /*
     * `.placeholder` و`.hint` هما المفتاحان الباقيان من خطوة الحساب
     * المهني السابقة: لم تعد الجولة تطرحهما، لكن
     * pages/buyer/professional-account.tsx يعيد استخدامهما لنفس السؤال
     * حين يطلبه المشتري لاحقاً بالفعل. لا تحذفهما.
     */
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
    'account.preferences_title' => 'اللغة والعملة',
    'account.preferences_subtitle' => 'طريقة عرض الموقع لك. تُكتشف من منطقتك ما لم تختر بنفسك.',
    'account.currency_auto' => 'تُعرض الأسعار بالدرهم المغربي (MAD)، وهي العملة التي تُحتسب بها الطلبات.',

    'account.nav_overview' => 'حسابي',
    'account.nav_orders' => 'الطلبات',
    'account.nav_wishlist' => 'المفضلة',
    'account.nav_payment' => 'الأداء',
    /*
     * لا تزال مستخدمة في عنوان صفحة /professional-account نفسها. شريط
     * تبويبات الحساب لم يعد يعرضها — انظر الملاحظة في
     * layouts/buyer/account-layout.tsx.
     */
    'account.nav_professional' => 'الحساب المهني',
    'account.logout' => 'تسجيل الخروج',

    // نوع الحساب — يحلّ محلّ الصورة والاسم الذين كانا أعلى الشريط الجانبي.
    'account.badge_personal' => 'حساب شخصي',
    'account.badge_professional' => 'حساب مهني',

    // بطاقة واحدة لكل عملية شراء منذ أن صار السجل يجمعها — توزّعها على
    // الورشات يظهر داخل البطاقة، لا كبطاقات إضافية.
    'account.orders_intro' => 'كل ما طلبته. عملية الشراء التي تشمل عدة ورشات تبقى طلباً واحداً هنا، مع متابعة كل ورشة على حدة.',
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
    'account.field_full_name_empty' => 'أضف اسمك',
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

    // الحالة الفارغة — تشترك مع الطلبات في components/buyer/EmptyShowcase.tsx.
    'wishlist.empty_eyebrow' => 'لم تحفظ شيئاً بعد',
    'wishlist.empty_title' => 'أعجبتك قطعة؟',
    'wishlist.empty_cta' => 'تجوّل في السوق',

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
    'cart.total' => 'المجموع',
    'cart.checkout' => 'إتمام الطلب',
    'cart.secure_payment' => 'دفع آمن',
    'cart.split_note' => 'ستُقسَّم سلتك إلى طلب لكل ورشة.',

    'cart.you_might_also_like' => 'قد يعجبك أيضًا',

    // ── إتمام الطلب ──────────────────────────────────────────────────────
    'checkout.title' => 'إتمام الطلب',
    'checkout.back' => 'العودة إلى السلة',
    'checkout.back_to_product' => 'العودة إلى القطعة',

    'checkout.delivery_heading' => 'التوصيل',
    'checkout.saved_addresses' => 'عناوينك المحفوظة',
    'checkout.default_badge' => 'الافتراضي',
    'checkout.use_new_address' => 'استخدام عنوان آخر',
    'checkout.new_address' => 'عنوان جديد',
    'checkout.city' => 'المدينة',
    'checkout.address_line' => 'العنوان الكامل',
    'checkout.address_placeholder' => 'الشارع، الحي، رقم المنزل…',
    'checkout.phone' => 'الهاتف',
    'checkout.phone_hint' => 'سيتصل بك الموصّل على هذا الرقم.',
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

    'checkout.summary' => 'ملخص الطلب',
    'checkout.products' => 'المنتجات (:count)',
    'checkout.delivery_fee' => 'رسوم التوصيل',
    'checkout.delivery_service_heading' => 'طريقة التوصيل',
    'checkout.total' => 'المجموع',
    'checkout.processing' => 'جارٍ المعالجة…',

    'checkout.err_address' => 'يرجى تحديد عنوان التوصيل.',
    'checkout.err_city' => 'اختر مدينة.',
    'checkout.err_phone' => 'رقم الهاتف مطلوب للتوصيل.',
    'checkout.err_empty' => 'سلتك فارغة.',
    'checkout.err_buy_now_gone' => 'لم تعد هذه القطعة متوفرة. عد إلى صفحتها للمحاولة مجدداً.',
    'checkout.customer_heading' => 'معلوماتك',
    'checkout.first_name' => 'الاسم الشخصي',
    'checkout.first_name_placeholder' => 'سارة',
    'checkout.last_name' => 'الاسم العائلي',
    'checkout.last_name_placeholder' => 'بناني',
    'checkout.email' => 'البريد الإلكتروني',
    'checkout.shipping_heading' => 'عنوان التوصيل',
    'checkout.postal_code' => 'الرمز البريدي',
    'checkout.country' => 'البلد',
    'checkout.country_ma' => 'المغرب',
    'checkout.payment_heading' => 'طريقة الدفع',
    'checkout.payment_card' => 'البطاقة البنكية',
    'checkout.card_note' => 'دفع آمن بتقنية 3D Secure عبر Payzone (Visa وMastercard). ستنتقل إلى صفحة الدفع ثم تعود إلى هنا. يُخصم المبلغ بالدرهم المغربي.',
    'checkout.payment_paypal' => 'PayPal',
    'checkout.paypal_note' => 'ادفع بحسابك على PayPal، أو بالبطاقة عبر PayPal. ستنتقل إلى PayPal ثم تعود إلى هنا.',
    'checkout.paypal_charged' => 'لا يقبل PayPal الدرهم: سيُخصم منك :amount.',
    'checkout.payment_unavailable' => 'الدفع عبر الإنترنت غير متاح مؤقتاً. يُرجى إعادة المحاولة بعد قليل.',
    'checkout.confirm_card' => 'المتابعة إلى الدفع',
    'checkout.confirm_paypal' => 'المتابعة عبر PayPal',
    'checkout.coupon' => 'رمز الخصم',
    'checkout.coupon_placeholder' => 'WELCOME10',
    'checkout.coupon_apply' => 'تطبيق',
    'checkout.err_first_name' => 'أدخل اسمك الشخصي.',
    'checkout.err_last_name' => 'أدخل اسمك العائلي.',
    'checkout.err_postal_code' => 'أدخل الرمز البريدي.',
    'checkout.err_country' => 'نوصّل داخل المغرب فقط في الوقت الحالي.',
    'checkout.err_payment_method' => 'اختر طريقة الدفع.',
    'checkout.err_coupon_unknown' => 'رمز الخصم هذا غير صالح.',
    'order_placed.eyebrow' => 'تهانينا',
    'order_placed.title' => 'تم تسجيل طلبك!',
    'order_placed.body' => 'شكرًا على ثقتك. سيبدأ حرفيّونا العمل على طلبك، وسنُبقيك على اطلاع عبر البريد الإلكتروني في كل مرحلة.',
    'order_placed.reference' => 'المرجع',
    'order_placed.email' => 'تم إرسال ملخص الطلب إلى :email.',
    'order_placed.view' => 'عرض طلباتي',
    'order_placed.continue' => 'متابعة التسوق',
    'order_placed.summary' => 'ملخص الطلب',
    'order_placed.next_title' => 'ماذا بعد؟',
    'order_placed.step_confirm' => 'يُؤكَّد طلبك فور قبول الدفع — والحرفيون يجهّزون قطعك الآن.',
    'order_placed.step_ship' => 'تُجهَّز قطعك ثم تُشحن، وتُعلمك رسالة عند انطلاق الطرد.',
    'order_placed.step_deliver' => 'يسلّمك عون التوصيل طردك — مدفوعاً مسبقاً، فلا شيء تدفعه عند الباب. ويمكنك بعدها تقييم كل قطعة من صفحة طلباتك.',

    // PDF invoice — resources/views/invoices/purchase.blade.php.
    'invoice.title' => 'فاتورة',
    'invoice.number' => 'رقم',
    'invoice.date' => 'التاريخ:',
    'invoice.billed_to' => 'فاتورة باسم',
    'invoice.delivered_to' => 'التسليم إلى',
    'invoice.carrier' => 'شركة النقل:',
    'invoice.item' => 'المنتج',
    'invoice.quantity' => 'الكمية',
    'invoice.unit_price' => 'سعر الوحدة',
    'invoice.subtotal' => 'المجموع الفرعي',
    'invoice.workshop' => 'الورشة:',
    'invoice.items_total' => 'مجموع المنتجات',
    'invoice.delivery' => 'التوصيل',
    'invoice.total' => 'المجموع شامل الضريبة',
    'invoice.payment' => 'طريقة الدفع: الدفع عند الاستلام.',
    'invoice.payment_card' => 'طريقة الدفع: البطاقة البنكية (Payzone)، مدفوعة عبر الإنترنت.',
    'invoice.payment_paypal' => 'طريقة الدفع: PayPal، مدفوعة عبر الإنترنت.',
    'invoice.footer' => 'شكرًا لدعمك الصناعة التقليدية المغربية — hirfaty.com',
    'invoice.filename' => 'hirfaty-invoice-:reference',

    // ── صفحة المتجر ──────────────────────────────────────────────────────
    'store.back_to_market' => 'السوق',
    'store.type_artist' => 'حرفي',
    'store.type_company' => 'شركة',
    'store.type_cooperative' => 'تعاونية',
    'store.since' => 'على حرفتي منذ :date',
    // علامة الثقة بجانب اسم الورشة، في صفحة المنتج وصفحة الورشة نفسها. تعكس
    // مصادقة الإدارة التي وجب على الورشة الحصول عليها قبل أن تبيع.
    'store.verified' => 'موثّق من حرفتي',
    'store.count_one' => 'قطعة واحدة',
    'store.count_many' => ':count قطع',
    'store.creations' => 'إبداعات الورشة',
    'store.empty_title' => 'لا توجد قطع منشورة',
    'store.empty_body' => 'لم تنشر هذه الورشة أي قطعة بعد. عد قريباً.',
    'store.empty_cta' => 'اكتشف السوق',
    'store.load_more' => 'عرض المزيد',
    'store.loading' => 'جارٍ التحميل…',
    'orders.payment' => 'الدفع',
    'orders.payment_card' => 'البطاقة البنكية — مدفوع',
    'orders.payment_paypal' => 'PayPal — مدفوع',
    'payment.redirect_title' => 'دفع آمن',
    'payment.redirecting' => 'جارٍ توجيهك إلى صفحة الدفع الآمنة…',
    'payment.redirecting_note' => 'يُرجى عدم إغلاق هذه النافذة.',
    'payment.continue' => 'المتابعة إلى الدفع',
    'payment.title' => 'دفع طلبك',
    'payment.reference' => 'الطلب',
    'payment.amount' => 'المبلغ',
    'payment.awaiting_title' => 'نتحقق من عملية الدفع…',
    'payment.awaiting_body' => 'ترسل لنا Payzone نتيجة الدفع. يستغرق ذلك عادةً بضع ثوانٍ — تتحدّث هذه الصفحة تلقائياً.',
    'payment.awaiting_body_paypal' => 'يؤكّد لنا PayPal عملية الدفع. يستغرق ذلك عادةً بضع ثوانٍ — تتحدّث هذه الصفحة تلقائياً.',
    'payment.slow_body' => 'يتأخر التأكيد قليلاً. إن خُصم المبلغ من بطاقتك فسيُعتمد طلبك تلقائياً وستصلك رسالة بالبريد الإلكتروني، وإلا يمكنك إعادة المحاولة أدناه.',
    'payment.slow_body_paypal' => 'لا يزال PayPal يراجع عملية الدفع. بمجرد قبولها، يُؤكَّد طلبك تلقائياً وتصلك رسالة بالبريد الإلكتروني. وإلا يمكنك إعادة المحاولة أدناه.',
    'payment.failed_title' => 'لم تتمّ عملية الدفع',
    'payment.failed_body' => 'لم يُخصم أي مبلغ من بطاقتك. يمكنك إعادة المحاولة بالبطاقة نفسها أو بأخرى، أو إلغاء الطلب.',
    'payment.failed_body_paypal' => 'لم يُخصم منك أي مبلغ. يمكنك إعادة المحاولة عبر PayPal، أو إلغاء الطلب.',
    'payment.cancel_title' => 'توقفت عملية الدفع',
    'payment.cancel_body' => 'غادرت صفحة الدفع. طلبك محجوز لبعض الوقت: أعد محاولة الدفع، أو ألغِ الطلب.',
    'payment.pending_title' => 'طلبك بانتظار الدفع',
    'payment.pending_body' => 'أكمل الدفع بالبطاقة لتأكيد طلبك.',
    'payment.pending_body_paypal' => 'أكمل الدفع عبر PayPal لتأكيد طلبك.',
    'payment.released_title' => 'انتهت صلاحية هذا الطلب',
    'payment.released_body' => 'لم يصلنا الدفع في الوقت المحدد، لذا أُلغي الطلب. منتجاتك ما زالت في سلتك.',
    'payment.retry' => 'إعادة محاولة الدفع بالبطاقة',
    'payment.retry_paypal' => 'إعادة المحاولة عبر PayPal',
    'payment.charged_paypal' => 'المبلغ المخصوم عبر PayPal: :amount',
    'payment.cancel_order' => 'إلغاء الطلب',
    'payment.back_to_cart' => 'العودة إلى السلة',
    'cancel_reason.out_of_stock' => 'لم تعد القطعة متوفرة لدى الحرفي',
    'cancel_reason.quality_issue' => 'لم تجتز القطعة مراقبة الجودة لدينا',
    'cancel_reason.damaged' => 'تعرّضت القطعة للتلف قبل الشحن',
    'cancel_reason.buyer_request' => 'أُلغي بناءً على طلبك',
    'cancel_reason.address_issue' => 'يتعذّر التوصيل إلى العنوان المُدخل',
    'cancel_reason.unreachable' => 'لم نتمكّن من التواصل معك لتأكيد الطلب',
    'cancel_reason.delay' => 'ستكون مدة الصنع طويلة جداً',
    'cancel_reason.duplicate' => 'تم تقديم الطلب مرتين',
    'cancel_reason.pricing_error' => 'خطأ في سعر القطعة',
];
