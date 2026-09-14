<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One mailbox on the footer's mailing list.
 *
 * Write through `NewsletterService` rather than here: the address has to be
 * normalised before it reaches the unique index, and doing that at the call
 * site is how duplicates get in.
 */
class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'locale'];
}
