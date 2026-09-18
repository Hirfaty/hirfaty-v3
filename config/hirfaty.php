<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operations inbox
    |--------------------------------------------------------------------------
    |
    | Where the desk is told about work it has to do — today, that a basket has
    | been bought and needs collecting from the workshops and delivering.
    |
    | Deliberately not the super-admin User's own email: this is a role, not a
    | person, and whoever runs fulfilment may never log in. Set ADMIN_EMAIL to
    | an empty string to silence these notifications entirely.
    |
    */

    'admin_email' => env('ADMIN_EMAIL', 'creati.maroc@gmail.com'),

];
