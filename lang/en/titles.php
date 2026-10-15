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
    'home' => 'Moroccan craft, direct from the artisans',
    'about' => 'Our story',
    'blog' => 'The Journal',
    'catalogue' => 'Catalogue',

    'auth.forgot_password' => 'Forgot password',
    'auth.reset_password' => 'Reset password',
    'auth.confirm_password' => 'Confirm password',
    'auth.two_factor' => 'Two-factor authentication',

    'settings.profile' => 'Profile',
    'settings.security' => 'Security',
    'settings.appearance' => 'Appearance',

    'seller.dashboard' => 'Dashboard',
    'seller.orders' => 'Orders',
    'seller.order' => 'Order',
    'seller.products' => 'Products',
    'seller.sales' => 'Sales',
    'seller.settings' => 'Settings',

    'admin.dashboard' => 'Admin',
    'admin.blogs' => 'Journal',
    'admin.blog_create' => 'New article',
    'admin.blog_edit' => 'Edit article',
    'admin.brochure_leads' => 'Brochure requests',
    'admin.categories' => 'Categories',
    'admin.cities' => 'Cities',
    'admin.delivery_rates' => 'Delivery rates',
    'admin.invoices' => 'Invoices',
    'admin.newsletter' => 'Newsletter subscribers',
    'admin.orders' => 'Purchases',
    'admin.notifications' => 'Notifications',
    'admin.order' => 'Purchase',
    'admin.products' => 'Products',
    'admin.product_create' => 'New product',
    'admin.product_edit' => 'Edit product',
    'admin.stores' => 'Workshops',
    'admin.users' => 'Users',
    'admin.user_create' => 'New user',
    'admin.user_edit' => 'Edit user',
];
