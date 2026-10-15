<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNote;

/**
 * The desk's private notes about an account, shown on /admin/users/{id}.
 *
 * Admin-only by construction: nothing outside Admin\UserController and
 * Admin\UserNoteController calls this, and the notes are handed to the page
 * as their own prop rather than loaded onto the serialised `user`, so no
 * buyer- or seller-facing response that happens to serialise a User can
 * carry them.
 */
class UserNoteService
{
    /**
     * Newest first, shaped for the notes card.
     *
     * @return list<array{id: int, body: string, author: string|null, created_at: string}>
     */
    public function forUser(User $user): array
    {
        return $user->adminNotes()
            ->with('author:id,first_name,last_name')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (UserNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author
                    ? trim("{$note->author->first_name} {$note->author->last_name}")
                    : null,
                'created_at' => $note->created_at->toIso8601String(),
            ])
            ->all();
    }

    public function add(User $user, User $author, string $body): UserNote
    {
        return $user->adminNotes()->create([
            'author_id' => $author->id,
            'body' => trim($body),
        ]);
    }

    public function delete(UserNote $note): void
    {
        $note->delete();
    }
}
