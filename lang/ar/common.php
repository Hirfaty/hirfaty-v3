<?php

return [
    'app_name' => 'حرفتي',
    'save' => 'حفظ',
    'cancel' => 'إلغاء',
    'delete' => 'حذف',
    'edit' => 'تعديل',
    'view' => 'عرض',
    'search' => 'بحث',
    'filter' => 'تصفية',
    'expand' => 'توسيع',
    'collapse' => 'طي',
    'loading' => 'جار التحميل...',
    'confirm' => 'هل أنت متأكد؟',
    'back' => 'رجوع',
    'next' => 'التالي',
    'submit' => 'إرسال',
    'total' => 'المجموع',
    'status' => 'الحالة',
    'date' => 'التاريخ',
    'actions' => 'الإجراءات',
    'yes' => 'نعم',
    'no' => 'لا',
    'welcome' => 'مرحباً',
    'logout' => 'تسجيل الخروج',
    'login' => 'تسجيل الدخول',
    'register' => 'إنشاء حساب',

    /*
     * ── الباب الواحد: /login ─────────────────────────────────────────────
     *
     * تسجيل الدخول وإنشاء الحساب يتقاسمان شاشة واحدة، ومن ثمّ مجموعة
     * مفاتيح واحدة. اختفى `auth.register.*` لأنه لم تبقَ شاشة ثانية ينتمي
     * إليها. والنصوص مرتّبة بالترتيب الذي يلقاه الزائر: التعريف ← كلمة
     * المرور (أو الرمز) ← رمز التسجيل ← البيانات.
     */
    'auth.login.page_title' => 'تسجيل الدخول أو إنشاء حساب',
    'auth.login.back_home' => 'العودة إلى السوق',

    // شريط الخطوات. يظهر في مسار التسجيل وحده: رحلة الزائر العائد شاشتان،
    // فالقول «الخطوة 1 من 3» سيكون غير صحيح.
    'auth.login.step_of' => 'الخطوة :current من :total',
    'auth.login.step_email' => 'البريد',
    'auth.login.step_code' => 'الرمز',
    'auth.login.step_details' => 'البيانات',

    // ── التعريف: حقل البريد الإلكتروني ──────────────────────────────────
    'auth.login.identify_title' => 'سجّل الدخول أو',
    'auth.login.identify_title_accent' => 'أنشئ حسابك',
    'auth.login.identify_subtitle' => 'لا تتذكّر إن كان لديك حساب؟ أدخل بريدك الإلكتروني وسنتحقّق لك.',
    'auth.login.email_required' => 'البريد الإلكتروني (مطلوب)',
    'auth.login.identify_submit' => 'متابعة',
    'auth.login.privacy_note' => 'حماية معلوماتك الشخصية أولويتنا. لا نستخدم بريدك إلا لتأكيد الحساب.',
    'auth.login.identify_again' => 'استغرق ذلك وقتاً طويلاً — أدخل بريدك الإلكتروني مرة أخرى للمتابعة.',

    // بابا الحرفي والأعمال، في بطاقتين أسفل الاستمارة. «Hirfaty Seller»
    // و«Hirfaty Business» أسماء منتجات تبقى بالحرف اللاتيني في كل اللغات،
    // كما هي العادة مع أسماء العلامات.
    'auth.login.card_seller_brand' => 'للحرفيين',
    'auth.login.card_seller_body' => 'بِع منتجاتك اليدوية في كل أنحاء المغرب.',
    'auth.login.card_seller_cta' => 'إنشاء حساب حرفي',
    'auth.login.card_business_body' => 'تشتري لحساب عملك؟ اطلب بأسعار الجملة.',
    'auth.login.card_business_cta' => 'إنشاء حساب أعمال',

    // ── كلمة المرور: لحساب قائم ─────────────────────────────────────────
    'auth.login.password_title' => 'أهلاً',
    'auth.login.password_title_accent' => 'بعودتك',
    'auth.login.password_subtitle' => 'لديك حساب بهذا العنوان بالفعل. أدخل كلمة المرور لتسجيل الدخول.',
    'auth.login.password' => 'كلمة المرور',
    'auth.login.remember' => 'أبقني مسجّلاً',
    'auth.login.submit' => 'تسجيل الدخول',
    'auth.login.forgot' => 'نسيت كلمة المرور؟',
    'auth.login.use_another_email' => 'استخدام عنوان آخر',
    'auth.login.use_code_instead' => 'أرسل لي رمزاً بدلاً من ذلك',
    'auth.login.use_password_instead' => 'استخدام كلمة المرور',

    // ── رمز الدخول: تسجيل دخول بلا كلمة مرور ───────────────────────────
    'auth.login.code_title' => 'تحقّق من',
    'auth.login.code_title_accent' => 'بريدك',
    'auth.login.code_subtitle' => 'أرسلنا رمز دخول من ستة أرقام إلى :email. أدخله أدناه وستدخل حسابك — بلا كلمة مرور.',
    'auth.login.code_submit' => 'سجّل دخولي',
    'auth.login.code_sent' => 'رمز الدخول في الطريق إليك.',
    'auth.login.code_unavailable' => 'هذا الحساب يحتاج كلمة المرور. الرموز غير متاحة للحسابات التي تستخدم التحقّق بخطوتين.',

    // ── رمز التسجيل: تأكيد عنوان جديد ──────────────────────────────────
    'auth.login.verify_title' => 'تحقّق من',
    'auth.login.verify_title_accent' => 'بريدك',
    'auth.login.verify_subtitle' => 'أنت جديد هنا — أرسلنا رمزاً من ستة أرقام إلى :email. أدخله لتأكيد العنوان.',
    'auth.login.verify_submit' => 'تأكيد بريدي الإلكتروني',
    'auth.login.wrong_email' => 'العنوان خطأ؟ ابدأ من جديد',
    'auth.login.session_expired' => 'انتهت صلاحية هذا التسجيل. أدخل بريدك الإلكتروني للبدء من جديد.',

    // رابط «تأكيد بريدي الإلكتروني» في رسالة التسجيل.
    'auth.login.link_invalid' => 'هذا الرابط لم يعد صالحاً. إذا كان حسابك قد أُنشئ بالفعل، سجّل الدخول؛ وإلا أدخل بريدك الإلكتروني لتلقي رمز جديد.',
    'auth.login.link_title' => 'أوشكت',
    'auth.login.link_title_accent' => 'على الانتهاء',
    'auth.login.link_subtitle' => 'لقد فتحت الرابط المرسل إلى :email. تابع هنا لإتمام تسجيلك.',
    'auth.login.link_submit' => 'متابعة التسجيل',

    // مشتركة بين شاشتي الرمز (انظر CodeEntry.tsx).
    'auth.login.code_label' => 'رمز التأكيد',
    'auth.login.code_invalid' => 'هذا الرمز غير صحيح. راجع الرسالة وحاول مرة أخرى.',
    'auth.login.code_locked' => 'محاولات كثيرة على هذا الرمز. اطلب رمزاً جديداً.',
    'auth.login.code_resent' => 'رمز جديد في الطريق إليك.',
    'auth.login.code_resend' => 'إرسال رمز جديد',
    'auth.login.code_resend_in' => 'يمكنك طلب رمز جديد بعد :seconds ثانية',
    'auth.login.resend_wait' => 'انتظر :seconds ثانية أخرى قبل طلب رمز جديد.',
    'auth.login.code_spam_hint' => 'لم يصل بعد؟ قد يستغرق دقيقة — تحقّق أيضاً من مجلد البريد المزعج.',

    // ── استئناف تسجيل لم يكتمل ─────────────────────────────────────────
    'auth.login.resume_title' => 'لديك تسجيل قيد الإنجاز',
    'auth.login.resume_body' => 'سبق أن أرسلنا رمزاً إلى :email.',
    'auth.login.resume_cta' => 'أكمل من حيث توقفت',

    // ── صفحة Hirfaty Business ──────────────────────────────────────────
    'auth.login.placeholder_soon' => 'قريباً',
    'auth.login.placeholder_business_title' => 'حسابات الأعمال تُفتح يدوياً.',
    'auth.login.placeholder_business_body' => 'تُراجَع أسعار الجملة قبل منحها، لذلك لا تتوفّر حسابات الأعمال ذاتياً بعد. تواصل معنا وسننشئ حسابك. تشتري معنا بالفعل؟ يمكنك طلب صلاحية الأعمال من حسابك.',
    'auth.login.placeholder_contact' => 'تواصل معنا',
    'auth.login.placeholder_back' => 'أنا مشترٍ — أعدني إلى تسجيل الدخول',

    // ── شاشة تفعيل البريد الإلكتروني ─────────────────────────────────────
    'auth.verify.eyebrow' => 'أمان الحساب',
    'auth.verify.title' => 'تحقق من',
    'auth.verify.title_accent' => 'بريدك الإلكتروني',
    'auth.verify.subtitle' => 'شكرًا لانضمامك إلى حرفتي! اضغط على الرابط الذي أرسلناه للتو إلى',
    'auth.verify.sent_banner' => 'تم إرسال رابط تحقق جديد إلى عنوان بريدك الإلكتروني الذي قدمته عند التسجيل.',
    'auth.verify.resend' => 'إعادة إرسال رابط التحقق',
    'auth.verify.spam_hint' => 'لم يصلك بعد؟ تحقق من مجلد الرسائل غير المرغوب فيها.',
    'auth.verify.logout' => 'تسجيل الخروج',
    'auth.verify.quote' => 'الحساب الموثّق يحافظ على ثقة السوق — للحرفيين والمشترين على حد سواء.',

    // ── حقول البيانات (الخطوة 3 من التسجيل) ────────────────────────────
    'auth.login.first_name' => 'الاسم الشخصي',
    'auth.login.last_name' => 'الاسم العائلي',
    'auth.login.phone' => 'رقم الهاتف',
    'auth.login.password_confirm' => 'تأكيد كلمة المرور',
    'auth.login.password_hint' => '12 حرفاً على الأقل، مع أحرف كبيرة وصغيرة ورقم ورمز.',
    // Law 09-08: shown under the buyer sign-up form.
    'auth.login.cndp_notice' => 'المعلومات المجمعة تخضع لمعالجة مصرح بها لدى اللجنة الوطنية لمراقبة حماية المعطيات ذات الطابع الشخصي بموجب الوصل رقم D-W-6-2026. وطبقاً للقانون 09-08، تتمتعون بحق الولوج إلى معطياتكم الشخصية وتصحيحها.',
    'auth.login.password_error_rules' => 'يجب أن تتكون كلمة المرور من 12 حرفاً على الأقل، مع أحرف كبيرة وصغيرة ورقم ورمز.',
    'auth.login.password_error_mismatch' => 'كلمتا المرور غير متطابقتين.',
    'auth.login.details_title' => 'أوشكت على',
    'auth.login.details_title_accent' => 'الانتهاء',
    'auth.login.details_subtitle' => 'كلمة مرور فقط. يمكنك إكمال بياناتك لاحقاً من حسابك.',
    'auth.login.details_submit' => 'إنشاء حسابي',
    'auth.login.email_confirmed' => 'تم تأكيد البريد',

    // ── مؤشّر قوة كلمة المرور — تسمية لكل شريط ممتلئ ───────────────────
    'auth.login.password_strength_very_weak' => 'ضعيفة جداً',
    'auth.login.password_strength_weak' => 'ضعيفة',
    'auth.login.password_strength_fair' => 'متوسطة',
    'auth.login.password_strength_good' => 'جيدة',
    'auth.login.password_strength_strong' => 'قوية',

    'auth.login.terms_prefix' => 'أوافق على',
    'auth.login.terms_privacy' => 'سياسة الخصوصية',
    'auth.login.terms_and' => 'و',
    'auth.login.terms_conditions' => 'شروط الاستخدام',

    // ── صفحات الخطأ ──────────────────────────────────────────────────────
    'errors.eyebrow' => 'خطأ :status',

    'errors.404.title' => 'هذه الصفحة غادرت الورشة',
    'errors.404.body' => 'الرابط الذي اتّبعته لا يؤدي إلى شيء. ربما أُزيلت القطعة، أو أن العنوان به خطأ.',

    'errors.403.title' => 'هذا الباب يبقى مغلقاً',
    'errors.403.body' => 'لا تملك صلاحية الوصول إلى هذه الصفحة بهذا الحساب.',

    'errors.500.title' => 'حدث خلل ما',
    'errors.500.body' => 'وقع خطأ من جهتنا. لقد أُبلغنا به — أعد المحاولة بعد لحظات.',

    'errors.503.title' => 'نعود بعد قليل',
    'errors.503.body' => 'الموقع في صيانة لبضع دقائق. شكراً لصبرك.',

    'errors.back_home' => 'العودة إلى الرئيسية',
    'errors.browse_market' => 'اكتشف السوق',

    // حساب أوقفه أحد المشرفين — pages/auth/account-blocked.tsx.
    'account_blocked.eyebrow' => 'حساب موقوف',
    'account_blocked.title' => 'لم يعد بإمكانك استخدام حرفتي',
    'account_blocked.body' => 'تم إيقاف حسابك وتسجيل خروجك. لمعرفة السبب، أو إن كنت ترى أن هناك خطأ، تواصل مع فريق حرفتي.',
    'account_blocked.contact_page' => 'صفحة التواصل',

    // توقيع تذييل الصفحة — نفس القيمة في الملفات الثلاثة، انظر en/common.php.
    'footer.ar_line' => 'حرفتي — صنع بفخر في المغرب',

    'footer.newsletter.title' => 'رسائل من السوق',
    'footer.newsletter.blurb' => 'قطع جديدة، ورشات جديدة، والحكايات وراءها. مرة واحدة في الشهر، لا أكثر.',
    'footer.newsletter.label' => 'عنوان بريدك الإلكتروني',
    'footer.newsletter.placeholder' => 'you@example.com',
    'footer.newsletter.submit' => 'اشترك',
    'footer.newsletter.success' => 'تم تسجيلك. ترقّب رسالة السوق القادمة.',
    'footer.newsletter.invalid' => 'أدخل عنوان بريد إلكتروني صالحاً.',

    // ── بقية التذييل ─────────────────────────────────────────────────────
    // كان كل هذا مكتوباً بالفرنسية داخل `SiteFooter.tsx` مباشرة، فكان يظهر
    // بالفرنسية حتى في النسختين العربية والإنجليزية من الموقع.
    'footer.blurb' => 'سوق الصناعة التقليدية المغربية. قطع مصنوعة يدوياً، من حرفيي فاس ومراكش وشفشاون وما بعدها.',

    'footer.buyers.title' => 'المشترون',
    'footer.buyers.explore' => 'استكشف',
    'footer.buyers.how' => 'كيف يعمل',

    'footer.artisans.title' => 'الحرفيون',
    'footer.artisans.sell' => 'بِع على حرفتي',

    'footer.company.title' => 'الشركة',
    'footer.company.contact' => 'اتصل بنا',

    'footer.follow.title' => 'تابعنا',

    // `:year` يُستبدل في React بالسنة الجارية.
    'footer.copyright' => '© :year Hirfaty.com — جميع الحقوق محفوظة.',
    'footer.shipping' => 'التوصيل إلى جميع أنحاء المغرب.',
    'footer.payments_secure' => 'دفع آمن بتقنية 3D Secure عبر Payzone — Visa وMastercard',
    'footer.gtc' => 'الشروط العامة للبيع',
    'footer.cndp' => 'معالجة مصرح بها لدى اللجنة الوطنية لمراقبة حماية المعطيات ذات الطابع الشخصي — الوصل رقم D-W-6-2026',
    'footer.cndp_alt' => 'اللجنة الوطنية لمراقبة حماية المعطيات ذات الطابع الشخصي',
    // ── تسجيل الدخول عبر Google / Facebook ──────────────────────────────
    'auth.login.social_divider' => 'أو',
    'auth.login.social_google' => 'المتابعة عبر Google',
    'auth.login.social_facebook' => 'المتابعة عبر Facebook',
    'auth.login.social_failed' => 'تعذّر تسجيل الدخول. يرجى المحاولة مرة أخرى.',
    'auth.login.social_unusable' => 'لم يزوّدنا هذا الحساب بعنوان بريد إلكتروني مؤكَّد. استخدم بريدك أعلاه بدلاً من ذلك.',

    // ── العملة ───────────────────────────────────────────────────────────
    // تُحفظ الأسعار وتُحتسب بالدرهم؛ أما العملة المعروضة فتتبع عنوان IP الخاص
    // بالزائر ما لم يخترها بنفسه من الترويسة.
    'currency_label' => 'العملة المعروضة',
    'language_label' => 'اللغة',
    'region_label' => 'اللغة والعملة',
    'currency_note' => 'الأسعار معروضة بـ:currency للاسترشاد فقط. تُحتسب الطلبات بالدرهم المغربي (MAD).',
    'currency_charged' => 'المبلغ المحتسب: :amount',

    // ── أعمدة تذييل الصفحة (استكشف / من نحن / خدمة العملاء) ──────────────
    'footer.explore.title' => 'استكشف',
    'footer.explore.affiliate' => 'برنامج الإحالة',
    'footer.explore.blog' => 'المدوّنة',
    'footer.explore.rewards' => 'المكافآت',

    'footer.about.title' => 'من نحن',
    'footer.about.about' => 'من نحن',

    'footer.care.title' => 'خدمة العملاء',
    'footer.care.privacy' => 'سياسة الخصوصية',
    'footer.care.terms' => 'شروط الاستخدام',
    'footer.care.shipping' => 'سياسة التوصيل',
    'footer.care.bulk' => 'الشراء بالجملة',

    // ── ملاحظة بجانب أسعار المنتجات ────────────────────────────────────
    'tax_included' => '(شامل الضرائب)',
];
