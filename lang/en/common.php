<?php

return [
    'app_name' => 'Hirfaty',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'delete' => 'Delete',
    'edit' => 'Edit',
    'view' => 'View',
    'search' => 'Search',
    'filter' => 'Filter',
    'expand' => 'Expand',
    'collapse' => 'Collapse',
    'loading' => 'Loading...',
    'confirm' => 'Are you sure?',
    'back' => 'Back',
    'next' => 'Next',
    'submit' => 'Submit',
    'total' => 'Total',
    'status' => 'Status',
    'date' => 'Date',
    'actions' => 'Actions',
    'yes' => 'Yes',
    'no' => 'No',
    'welcome' => 'Welcome',
    'logout' => 'Logout',
    'login' => 'Login',
    'register' => 'Register',

    /*
     * ── The one front door: /login ───────────────────────────────────────
     *
     * Signing in and signing up share a screen and therefore share a key
     * namespace. `auth.register.*` is gone — there is no second screen for it
     * to belong to. Copy for the whole flow lives here, in the order a
     * visitor meets it: identify → password (or login code) → signup code →
     * profile completion.
     */
    'auth.login.page_title' => 'Sign in or create your account',
    'auth.login.back_home' => 'Back to the market',

    // Step chrome. Shown only on the signup branch — a returning visitor's
    // journey is two screens, so claiming "step 1 of 3" would be a lie.
    'auth.login.step_of' => 'Step :current of :total',
    'auth.login.step_email' => 'Email',
    'auth.login.step_code' => 'Code',
    'auth.login.step_details' => 'Details',

    // ── Identify: the email field ────────────────────────────────────────
    'auth.login.identify_title' => 'Sign in or',
    'auth.login.identify_title_accent' => 'create your account',
    'auth.login.identify_subtitle' => 'Not sure if you already have an account? Enter your email and we will check for you.',
    'auth.login.email_required' => 'Email address (required)',
    'auth.login.identify_submit' => 'Continue',
    'auth.login.privacy_note' => 'Securing your personal information is our priority. We only use your email to confirm the account.',
    'auth.login.identify_again' => 'That took too long — enter your email again to pick up where you were.',

    // The artisan and trade doors, shown as cards below the form. "Hirfaty
    // Seller" and "Hirfaty Business" are product names and stay in Latin
    // script in every locale, the way brand names normally do.
    'auth.login.card_seller_brand' => 'Seller',
    'auth.login.card_seller_body' => 'Sell your craft to buyers across Morocco.',
    'auth.login.card_seller_cta' => 'Create a seller account',
    'auth.login.card_business_body' => 'Buying for work? Order at trade prices.',
    'auth.login.card_business_cta' => 'Create a business account',

    // ── Password: for an account that exists ─────────────────────────────
    'auth.login.password_title' => 'Welcome',
    'auth.login.password_title_accent' => 'back',
    'auth.login.password_subtitle' => 'You already have an account with this address. Enter your password to sign in.',
    'auth.login.password' => 'Password',
    'auth.login.remember' => 'Keep me signed in',
    'auth.login.submit' => 'Sign in',
    'auth.login.forgot' => 'Forgot your password?',
    'auth.login.use_another_email' => 'Use a different email',
    'auth.login.use_code_instead' => 'Email me a code instead',
    'auth.login.use_password_instead' => 'Use my password instead',

    // ── Login code: passwordless sign-in ────────────────────────────────
    'auth.login.code_title' => 'Check your',
    'auth.login.code_title_accent' => 'inbox',
    'auth.login.code_subtitle' => 'We sent a 6-digit sign-in code to :email. Enter it below and you are in — no password needed.',
    'auth.login.code_submit' => 'Sign me in',
    'auth.login.code_sent' => 'A sign-in code is on its way.',
    'auth.login.code_unavailable' => 'This account needs its password. Codes are not available for accounts with two-step verification.',

    // ── Signup code: confirming a new address ───────────────────────────
    'auth.login.verify_title' => 'Check your',
    'auth.login.verify_title_accent' => 'inbox',
    'auth.login.verify_subtitle' => 'You are new here — we sent a 6-digit code to :email. Enter it below to confirm the address.',
    'auth.login.verify_submit' => 'Confirm my email',
    'auth.login.wrong_email' => 'Wrong address? Start over',
    'auth.login.session_expired' => 'That signup link has expired. Enter your email to start again.',

    // The "Confirm my email" link in the signup email.
    'auth.login.link_invalid' => 'That link is no longer valid. If your account is already set up, sign in; otherwise enter your email to get a new code.',
    'auth.login.link_title' => 'Almost',
    'auth.login.link_title_accent' => 'there',
    'auth.login.link_subtitle' => 'You opened the link sent to :email. Continue here to finish signing up.',
    'auth.login.link_submit' => 'Continue signing up',

    // Shared by both code screens (see CodeEntry.tsx).
    'auth.login.code_label' => 'Confirmation code',
    'auth.login.code_invalid' => 'That code is not right. Check the email and try again.',
    'auth.login.code_locked' => 'Too many attempts on this code. Send yourself a new one.',
    'auth.login.code_resent' => 'A new code is on its way.',
    'auth.login.code_resend' => 'Send a new code',
    'auth.login.code_resend_in' => 'You can ask for a new code in :seconds s',
    'auth.login.resend_wait' => 'Please wait :seconds more seconds before asking for another code.',
    'auth.login.code_spam_hint' => 'Nothing yet? It can take a minute — check your spam folder too.',

    // ── Resuming an unfinished signup ───────────────────────────────────
    'auth.login.resume_title' => 'You have a signup in progress',
    'auth.login.resume_body' => 'We already sent a code to :email.',
    'auth.login.resume_cta' => 'Continue where I left off',

    // ── Hirfaty Business holding page ───────────────────────────────────
    'auth.login.placeholder_soon' => 'Coming soon',
    'auth.login.placeholder_business_title' => 'Trade accounts are opened by hand.',
    'auth.login.placeholder_business_body' => 'Wholesale pricing is reviewed before it is granted, so business accounts are not self-serve yet. Get in touch and we will set yours up. Already shopping with us? You can request trade access from your account.',
    'auth.login.placeholder_contact' => 'Get in touch',
    'auth.login.placeholder_back' => 'I am shopping — take me back to sign in',

    // ── Verify-email screen ──────────────────────────────────────────────
    'auth.verify.eyebrow' => 'Account security',
    'auth.verify.title' => 'Verify your',
    'auth.verify.title_accent' => 'email',
    'auth.verify.subtitle' => 'Thanks for joining Hirfaty! Click the link we just sent to',
    'auth.verify.sent_banner' => 'A new verification link has been sent to the email address you provided when registering.',
    'auth.verify.resend' => 'Resend verification email',
    'auth.verify.spam_hint' => 'Haven\'t received it? Check your spam folder.',
    'auth.verify.logout' => 'Sign out',
    'auth.verify.quote' => 'A verified account keeps the souk trustworthy — for artisans and buyers alike.',

    // ── Profile completion fields (signup step 3) ───────────────────────
    'auth.login.first_name' => 'First name',
    'auth.login.last_name' => 'Last name',
    'auth.login.phone' => 'Phone number',
    'auth.login.password_confirm' => 'Confirm password',
    'auth.login.password_hint' => 'At least 12 characters, with upper and lower case, a number and a symbol.',
    'auth.login.password_error_rules' => 'Your password needs at least 12 characters, with upper and lower case, a number and a symbol.',
    'auth.login.password_error_mismatch' => 'The two passwords do not match.',
    'auth.login.details_title' => 'Almost',
    'auth.login.details_title_accent' => 'there',
    'auth.login.details_subtitle' => 'Just a password. You can fill in the rest later, from your account.',
    'auth.login.details_submit' => 'Create my account',
    'auth.login.email_confirmed' => 'Email confirmed',

    // ── Strength meter — one label per bar filled ───────────────────────
    'auth.login.password_strength_very_weak' => 'Very weak',
    'auth.login.password_strength_weak' => 'Weak',
    'auth.login.password_strength_fair' => 'Fair',
    'auth.login.password_strength_good' => 'Good',
    'auth.login.password_strength_strong' => 'Strong',

    'auth.login.terms_prefix' => 'I accept the',
    'auth.login.terms_privacy' => 'privacy policy',
    'auth.login.terms_and' => 'and the',
    'auth.login.terms_conditions' => 'terms of use',

    // ── Error pages ──────────────────────────────────────────────────────
    // Rendered through Inertia by the handler in bootstrap/app.php.
    'errors.eyebrow' => 'Error :status',

    'errors.404.title' => 'This page has left the workshop',
    'errors.404.body' => 'The link you followed leads nowhere. The piece may have been taken down, or the address has a typo in it.',

    'errors.403.title' => 'This door stays shut',
    'errors.403.body' => 'This account does not have access to that page.',

    'errors.500.title' => 'Something broke',
    'errors.500.body' => 'Something went wrong on our side. We have been notified — do try again in a moment.',

    'errors.503.title' => 'Back very shortly',
    'errors.503.body' => 'The site is down for maintenance for a few minutes. Thank you for your patience.',

    'errors.back_home' => 'Back to home',
    'errors.browse_market' => 'Explore the market',

    /*
     * The site footer's Arabic signature line, under the tagline paragraph.
     * Kept as the same Arabic string in all three locale files rather than
     * translated into English/French — same convention as
     * `welcome.hero.badge_script` and `welcome.seller_cta.ar_line`: an
     * ornamental flourish in the brand's own language, not copy that changes
     * with the active locale.
     */
    'footer.ar_line' => 'حرفتي — صنع بفخر في المغرب',

    'footer.newsletter.title' => 'Letters from the souk',
    'footer.newsletter.blurb' => 'New pieces, new workshops, and the stories behind them. Once a month, never more.',
    'footer.newsletter.label' => 'Your email address',
    'footer.newsletter.placeholder' => 'you@example.com',
    'footer.newsletter.submit' => 'Subscribe',
    'footer.newsletter.success' => 'You are on the list. Watch out for the next letter from the souk.',
    'footer.newsletter.invalid' => 'Enter a valid email address.',

    // ── The rest of the footer ───────────────────────────────────────────
    // All of this was hardcoded in French in `SiteFooter.tsx`, and so stayed
    // French on the Arabic and English versions of the site.
    'footer.blurb' => 'The marketplace for Moroccan craft. Handmade pieces, by artisans from Fès, Marrakech, Chefchaouen and beyond.',

    'footer.buyers.title' => 'Buyers',
    'footer.buyers.explore' => 'Browse',
    'footer.buyers.how' => 'How it works',

    'footer.artisans.title' => 'Artisans',
    'footer.artisans.sell' => 'Sell on Hirfaty',

    'footer.company.title' => 'Company',
    'footer.company.contact' => 'Contact',
    // ── Footer columns (Explore / About us / Customer care) ──────────────
    'footer.explore.title' => 'Explore',
    'footer.explore.affiliate' => 'Affiliate program',
    'footer.explore.blog' => 'Blog',
    'footer.explore.rewards' => 'Rewards',

    'footer.about.title' => 'About us',
    'footer.about.about' => 'About us',

    'footer.care.title' => 'Customer care',
    'footer.care.privacy' => 'Privacy policy',
    'footer.care.terms' => 'Terms of Service',
    'footer.care.shipping' => 'Shipping policy',
    'footer.care.bulk' => 'Buy in bulk',

    'footer.follow.title' => 'Follow',

    // `:year` is substituted on the React side with the current year.
    'footer.copyright' => '© :year Hirfaty.com — All rights reserved.',
    'footer.shipping' => 'Delivery throughout Morocco.',
    // ── Google / Facebook sign-in ───────────────────────────────────────
    'auth.login.social_divider' => 'or',
    'auth.login.social_google' => 'Continue with Google',
    'auth.login.social_facebook' => 'Continue with Facebook',
    'auth.login.social_failed' => 'Sign-in failed. Please try again.',
    'auth.login.social_unusable' => 'That account did not give us a confirmed email address. Use your email above instead.',
    'auth.login.social_banned' => 'This account is suspended. Contact us if you believe this is a mistake.',

    // ── Currency ─────────────────────────────────────────────────────────
    // Prices are stored and charged in MAD; the display currency follows the
    // visitor's IP unless they pick one from the header.
    'currency_label' => 'Display currency',
    'language_label' => 'Language',
    'region_label' => 'Language and currency',
    'currency_note' => 'Prices shown in :currency as a guide. Orders are charged in dirhams (MAD).',
    'currency_charged' => 'Charged: :amount',
];
