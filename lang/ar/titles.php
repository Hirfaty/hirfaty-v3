<?php

/*
 * Browser-tab titles (<Head title>) for pages that have no better key of
 * their own. The brand is appended once, by the `title` callback in app.tsx
 * ("Page — Hirfaty") — never put "Hirfaty" in a value here, or it shows
 * twice.
 *
 * Pages that already carry a translated title elsewhere (pages.privacy.title,
 * buyer.cart.title, …) keep using it; this file only fills the gaps.
 */
return [
    'home' => 'الصناعة التقليدية المغربية، مباشرة من الحرفيين',
    'about' => 'قصتنا',
    'blog' => 'المجلة',
    'catalogue' => 'الكتالوج',

    'auth.forgot_password' => 'نسيت كلمة المرور',
    'auth.reset_password' => 'إعادة تعيين كلمة المرور',
    'auth.confirm_password' => 'تأكيد كلمة المرور',
    'auth.two_factor' => 'المصادقة الثنائية',

    'settings.profile' => 'الملف الشخصي',
    'settings.security' => 'الأمان',
    'settings.appearance' => 'المظهر',

    'seller.dashboard' => 'لوحة التحكم',
    'seller.orders' => 'الطلبات',
    'seller.order' => 'طلب',
    'seller.products' => 'المنتجات',
    'seller.sales' => 'المبيعات',
    'seller.settings' => 'الإعدادات',

    'admin.dashboard' => 'الإدارة',
    'admin.blogs' => 'المجلة',
    'admin.blog_create' => 'مقال جديد',
    'admin.blog_edit' => 'تعديل المقال',
    'admin.brochure_leads' => 'طلبات الكتيّب',
    'admin.categories' => 'الفئات',
    'admin.cities' => 'المدن',
    'admin.delivery_rates' => 'أسعار التوصيل',
    'admin.newsletter' => 'مشتركو النشرة البريدية',
    'admin.orders' => 'المشتريات',
    'admin.order' => 'عملية شراء',
    'admin.products' => 'المنتجات',
    'admin.product_create' => 'منتج جديد',
    'admin.product_edit' => 'تعديل المنتج',
    'admin.stores' => 'الورشات',
    'admin.users' => 'المستخدمون',
    'admin.user_create' => 'مستخدم جديد',
    'admin.user_edit' => 'تعديل المستخدم',
];
