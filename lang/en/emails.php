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
];
