<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A private note an admin left on an account. Written and read only through
 * UserNoteService, from the admin user pages — the account holder never sees
 * it.
 */
class UserNote extends Model
{
    protected $fillable = ['user_id', 'author_id', 'body'];

    /** The account the note is about. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The admin who wrote it; null once that admin account is gone. */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
