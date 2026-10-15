<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNote;
use App\Services\UserNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Private notes on an account, from the notes card on /admin/users/{id}.
 * Any admin can add one or remove one — it is the desk's shared memory, not
 * a per-admin notebook.
 */
class UserNoteController extends Controller
{
    public function __construct(private UserNoteService $notes) {}

    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->notes->add($user, Auth::user(), $data['body']);

        return back()->with('success', 'Note ajoutée.');
    }

    /**
     * `{adminNote}` is scoped to `{user}` in the route, so a note id that
     * belongs to another account 404s instead of being deleted from the
     * wrong page.
     */
    public function destroy(User $user, UserNote $adminNote)
    {
        $this->notes->delete($adminNote);

        return back()->with('success', 'Note supprimée.');
    }
}
