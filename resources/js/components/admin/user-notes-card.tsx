import { router, useForm } from '@inertiajs/react';
import { Lock, StickyNote, Trash2 } from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

/** One note as UserNoteService::forUser() shapes it. */
export type AdminUserNote = {
    id: number;
    body: string;
    /** Null once the admin who wrote it has been removed. */
    author: string | null;
    created_at: string;
};

const MAX_LENGTH = 2000;

const formatNoteDate = (iso: string) =>
    new Intl.DateTimeFormat('fr-MA', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));

/**
 * The desk's private notes on an account, on both admin user pages. Never
 * shown to the account holder — the notes only reach the page as this card's
 * own prop, from the admin-only show action.
 */
export default function UserNotesCard({
    userId,
    notes,
}: {
    userId: number;
    notes: AdminUserNote[];
}) {
    const form = useForm({ body: '' });
    /** The note awaiting delete confirmation, or null when none is. */
    const [deleteTarget, setDeleteTarget] = useState<AdminUserNote | null>(
        null,
    );
    const [deleting, setDeleting] = useState(false);

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        if (form.processing || form.data.body.trim() === '') {
            return;
        }

        form.post(route('admin.users.notes.store', userId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                toast.success('Note ajoutée.');
            },
        });
    };

    // Ctrl/Cmd+Enter saves, so a quick note never needs the mouse.
    const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
            submit();
        }
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(
            route('admin.users.notes.destroy', {
                user: userId,
                adminNote: deleteTarget.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDeleteTarget(null);
                    toast.success('Note supprimée.');
                },
                onFinish: () => setDeleting(false),
            },
        );
    };

    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <StickyNote className="h-4 w-4 text-ink-soft" />
                <h2 className="text-sm font-semibold whitespace-nowrap text-ink">
                    Notes internes
                </h2>
                {notes.length > 0 && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-ink-soft">
                        {notes.length}
                    </span>
                )}
            </div>

            <form onSubmit={submit} className="space-y-2 p-5">
                <Textarea
                    aria-label="Nouvelle note"
                    rows={3}
                    maxLength={MAX_LENGTH}
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                    onKeyDown={handleKeyDown}
                    aria-invalid={Boolean(form.errors.body)}
                    placeholder="Ex : rappelé le 12/10, envoie les photos lundi. Préfère WhatsApp."
                    className="text-sm"
                />
                {form.errors.body && (
                    <p className="text-xs text-destructive">
                        {form.errors.body}
                    </p>
                )}
                {/* Wraps in the narrow sidebar column (lg at ~1024px), the
                    button dropping under the hint rather than overflowing. */}
                <div className="flex flex-wrap items-center gap-2">
                    <span className="inline-flex items-center gap-1 text-[11px] whitespace-nowrap text-ink-soft">
                        <Lock className="h-3 w-3" />
                        Visible par l’équipe uniquement
                    </span>
                    <Button
                        type="submit"
                        size="sm"
                        className="ml-auto rounded-lg"
                        title="Ctrl + Entrée"
                        disabled={
                            form.processing || form.data.body.trim() === ''
                        }
                    >
                        {form.processing ? 'Ajout…' : 'Ajouter'}
                    </Button>
                </div>
            </form>

            {notes.length === 0 ? (
                <p className="border-t border-border px-5 py-6 text-center text-sm text-ink-soft">
                    Aucune note pour l’instant.
                </p>
            ) : (
                <ul className="divide-y divide-border border-t border-border">
                    {notes.map((note) => (
                        <li key={note.id} className="group px-5 py-4">
                            <p className="text-sm leading-relaxed break-words whitespace-pre-line text-ink">
                                {note.body}
                            </p>
                            <div className="mt-2 flex items-center justify-between gap-2">
                                <p className="text-xs text-ink-soft">
                                    {note.author ?? 'Admin supprimé'} ·{' '}
                                    {formatNoteDate(note.created_at)}
                                </p>
                                <button
                                    type="button"
                                    onClick={() => setDeleteTarget(note)}
                                    aria-label="Supprimer la note"
                                    className="rounded-md p-1 text-ink-soft opacity-60 transition group-hover:opacity-100 hover:bg-muted hover:text-terracotta focus-visible:opacity-100"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <AlertDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Supprimer cette note ?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Elle sera retirée pour toute l’équipe. Cette action
                            est irréversible.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            disabled={deleting}
                            className="bg-terracotta text-white hover:bg-terracotta/90"
                        >
                            {deleting ? 'Suppression…' : 'Supprimer'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
