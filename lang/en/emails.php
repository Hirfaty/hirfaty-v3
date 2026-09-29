<?php

/*
 * Email copy. See the note in lang/fr/emails.php on why nesting is safe here
 * but not in the locale files shipped to the browser.
 */

return [
    'verify' => [
        // Shared chrome
        'cta' => 'Verify my email address',
        'expires' => 'This link expires in :minutes minutes.',
        'fallback' => 'Button not working? Copy this link into your browser:',
        'ignore' => 'Didn’t create a Hirfaty account? Ignore this email — no account will be activated.',
        'tagline' => 'The home of Moroccan artisans.',
        'privacy' => 'Privacy',
        'terms' => 'Terms of use',
        'greeting' => 'Hello :name,',

        'buyer' => [
            'subject' => 'Verify your email address — Hirfaty',
            'preheader' => 'One last step to activate your Hirfaty account.',
            'eyebrow' => 'Buyer account',
            'headline' => 'welcome to Hirfaty.',
            'body' => 'Thanks for signing up. Verify your email address to discover pieces made by hand by artisans across Morocco.',
            'next_title' => 'What comes next',
            'steps' => [
                [
                    'title' => 'Explore the market',
                    'body' => 'Pottery, leather, textiles, jewellery and much more.',
                ],
                [
                    'title' => 'Order with confidence',
                    'body' => 'Secure Amana delivery anywhere in Morocco.',
                ],
                [
                    'title' => 'Follow your orders',
                    'body' => 'A tracking number is sent the moment your parcel ships.',
                ],
            ],
        ],

        'seller' => [
            'subject' => 'Activate your workshop — Hirfaty',
            'preheader' => 'One last step to open your Hirfaty shop.',
            'eyebrow' => 'Seller account',
            'headline' => 'activate your workshop.',
            'body' => 'You are one click from joining the community of artisans. Verify your email address to start setting up your shop.',
            'next_title' => 'What comes next',
            'steps' => [
                [
                    'title' => 'Set up your shop',
                    'body' => 'Name, category, city and a description of your workshop.',
                ],
                [
                    'title' => 'Add your pieces',
                    'body' => 'Photos, prices and description. Each piece is reviewed before it goes live.',
                ],
                [
                    'title' => 'Start selling',
                    'body' => 'Take orders, ship through Amana and track your earnings.',
                ],
            ],
        ],
    ],

    /*
     * The 6-digit signup code. No greeting by name here — unlike verify.*,
     * this email is sent before the account exists, so there is no first name
     * to greet with.
     */
    'registration_code' => [
        'subject' => 'Your Hirfaty code: :code',
        'preheader' => ':code is your Hirfaty confirmation code.',
        'eyebrow' => 'Create an account',
        'headline' => 'Here is your code.',
        'body' => 'Click the button to confirm your email address and finish creating your account — or enter the code below on the Hirfaty page you just left.',
        'button' => 'Confirm my email',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore' => 'Didn’t ask to join Hirfaty? Ignore this email — no account will be created, and the code stops working on its own.',
        'tagline' => 'The home of Moroccan artisans.',
        'privacy' => 'Privacy',
        'terms' => 'Terms of use',
    ],

    /*
     * The 6-digit sign-in code, for someone who has an account but chose a
     * code over their password. Same template as registration_code (see
     * emails/auth-code.blade.php) — only the words differ, so the key set
     * must stay identical to the block above.
     *
     * The `ignore` line is worded as a warning rather than a shrug: an
     * unexpected sign-in code means somebody typed this address into the
     * login screen, which is worth noticing.
     */
    'login_code' => [
        'subject' => 'Your Hirfaty sign-in code: :code',
        'preheader' => ':code is your Hirfaty sign-in code.',
        'eyebrow' => 'Sign in',
        'headline' => 'Here is your sign-in code.',
        'body' => 'Enter this code on the Hirfaty sign-in page to get into your account. You will not need your password.',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore' => 'Didn’t try to sign in? You can ignore this email — the code expires on its own and nothing has changed on your account. If it keeps happening, change your password.',
        'tagline' => 'The home of Moroccan artisans.',
        'privacy' => 'Privacy',
        'terms' => 'Terms of use',
    ],
];
