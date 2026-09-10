import { Link, useForm } from '@inertiajs/react';
import { Eye, ImageOff, ImagePlus, Loader2, Save } from 'lucide-react';
import { useRef, useState } from 'react';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import type { Blog, BlogStatus } from '@/types';

/** Human labels for Blog::STATUSES, which arrives as raw strings. */
const STATUS_LABELS: Record<BlogStatus, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    archived: 'Archivé',
};

const STATUS_HINTS: Record<BlogStatus, string> = {
    draft: 'Visible seulement ici. Personne d’autre ne peut le lire.',
    published: 'En ligne sur le journal public.',
    archived: 'Retiré du journal, mais conservé.',
};

interface BlogFormProps {
    /** Absent when writing a new article. */
    blog?: Blog;
    statuses: BlogStatus[];
    /** Where the form posts — create or update. */
    action: string;
    /** Endpoint the editor uploads inline images to. */
    uploadUrl: string;
}

/**
 * The write screen, shared by create and edit so the two can never drift.
 *
 * Always POSTs (never PUT): the cover image travels as multipart, which
 * browsers only send on a POST — the update route is declared POST to match.
 */
export function BlogForm({ blog, statuses, action, uploadUrl }: BlogFormProps) {
    const isEdit = blog !== undefined;
    const coverInputRef = useRef<HTMLInputElement>(null);

    // The existing cover, until the admin swaps or clears it. A local preview
    // URL replaces it the moment a new file is chosen.
    const [coverPreview, setCoverPreview] = useState<string | null>(
        blog?.cover_url ?? null,
    );

    const form = useForm({
        title: blog?.title ?? '',
        excerpt: blog?.excerpt ?? '',
        content: blog?.content ?? '',
        status: blog?.status ?? ('draft' as BlogStatus),
        // <input type="datetime-local"> wants "YYYY-MM-DDTHH:mm", not ISO 8601.
        published_at: blog?.published_at ? blog.published_at.slice(0, 16) : '',
        cover: null as File | null,
        remove_cover: false as boolean,
    });

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        // forceFormData: a File only survives the request as multipart, and
        // Inertia otherwise sends JSON when no file is present — which would
        // silently drop `remove_cover` on the clear-cover path.
        form.post(action, { forceFormData: true, preserveScroll: true });
    };

    const pickCover = (file: File | undefined) => {
        if (!file) {
            return;
        }

        form.setData((data) => ({ ...data, cover: file, remove_cover: false }));
        setCoverPreview(URL.createObjectURL(file));
    };

    const clearCover = () => {
        form.setData((data) => ({ ...data, cover: null, remove_cover: true }));
        setCoverPreview(null);

        if (coverInputRef.current) {
            coverInputRef.current.value = '';
        }
    };

    return (
        <form onSubmit={handleSubmit} className="grid gap-8 lg:grid-cols-3">
            {/* ── Main column: title, body ── */}
            <div className="space-y-6 lg:col-span-2">
                <div className="border border-ink/5 bg-white p-6 shadow-sm">
                    <label htmlFor="title">Titre de l’article</label>
                    <input
                        id="title"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder="La poterie de Safi, de la terre au four"
                        className="w-full border border-ink/10 bg-cream/5 px-3 py-2.5 font-display text-xl text-ink focus:border-terracotta focus:outline-none"
                    />
                    {form.errors.title && (
                        <FieldError>{form.errors.title}</FieldError>
                    )}

                    <div className="mt-5">
                        <label htmlFor="excerpt">
                            Chapô — résumé affiché sur la liste
                        </label>
                        <textarea
                            id="excerpt"
                            rows={3}
                            maxLength={500}
                            value={form.data.excerpt}
                            onChange={(e) =>
                                form.setData('excerpt', e.target.value)
                            }
                            placeholder="Deux phrases qui donnent envie de lire la suite."
                            className="w-full resize-y border border-ink/10 bg-cream/5 px-3 py-2 text-sm text-ink focus:border-terracotta focus:outline-none"
                        />
                        <p className="mt-1 text-right text-[11px] text-ink-soft">
                            {form.data.excerpt.length}/500
                        </p>
                        {form.errors.excerpt && (
                            <FieldError>{form.errors.excerpt}</FieldError>
                        )}
                    </div>
                </div>

                <div>
                    <label htmlFor="content">Contenu</label>
                    <RichTextEditor
                        value={blog?.content ?? ''}
                        onChange={(html) => form.setData('content', html)}
                        uploadUrl={uploadUrl}
                    />
                    {form.errors.content && (
                        <FieldError>{form.errors.content}</FieldError>
                    )}
                </div>
            </div>

            {/* ── Sidebar: publication, cover ── */}
            <aside className="space-y-6">
                <div className="border border-ink/5 bg-white p-6 shadow-sm">
                    <h2 className="font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                        Publication
                    </h2>

                    <div className="mt-5">
                        <label htmlFor="status">Statut</label>
                        <select
                            id="status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as BlogStatus,
                                )
                            }
                            className="w-full border border-ink/10 bg-cream/5 px-3 py-2 text-sm text-ink focus:border-terracotta focus:outline-none"
                        >
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {STATUS_LABELS[status]}
                                </option>
                            ))}
                        </select>
                        <p className="mt-2 text-xs text-ink-soft">
                            {STATUS_HINTS[form.data.status]}
                        </p>
                    </div>

                    <div className="mt-5">
                        <label htmlFor="published_at">Date de parution</label>
                        <input
                            id="published_at"
                            type="datetime-local"
                            value={form.data.published_at}
                            onChange={(e) =>
                                form.setData('published_at', e.target.value)
                            }
                            className="w-full border border-ink/10 bg-cream/5 px-3 py-2 text-sm text-ink focus:border-terracotta focus:outline-none"
                        />
                        <p className="mt-2 text-xs text-ink-soft">
                            Laissez vide pour dater à la publication. Une date
                            future programme l’article.
                        </p>
                        {form.errors.published_at && (
                            <FieldError>{form.errors.published_at}</FieldError>
                        )}
                    </div>
                </div>

                <div className="border border-ink/5 bg-white p-6 shadow-sm">
                    <h2 className="font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                        Image de couverture
                    </h2>

                    <div className="mt-4">
                        {coverPreview ? (
                            <div className="relative">
                                <img
                                    src={coverPreview}
                                    alt=""
                                    className="aspect-16/10 w-full object-cover"
                                />
                                <button
                                    type="button"
                                    onClick={clearCover}
                                    className="absolute top-2 right-2 flex items-center gap-1.5 bg-ink/80 px-2.5 py-1.5 text-[11px] font-medium text-cream backdrop-blur transition-colors hover:bg-terracotta"
                                >
                                    <ImageOff className="h-3.5 w-3.5" />
                                    Retirer
                                </button>
                            </div>
                        ) : (
                            <button
                                type="button"
                                onClick={() => coverInputRef.current?.click()}
                                className="flex aspect-16/10 w-full flex-col items-center justify-center gap-2 border border-dashed border-ink/20 bg-cream/20 text-ink-soft transition-colors hover:border-terracotta hover:text-terracotta"
                            >
                                <ImagePlus className="h-6 w-6" />
                                <span className="text-xs">
                                    Choisir une image
                                </span>
                            </button>
                        )}

                        <input
                            ref={coverInputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="hidden"
                            onChange={(e) => pickCover(e.target.files?.[0])}
                        />

                        {coverPreview && (
                            <button
                                type="button"
                                onClick={() => coverInputRef.current?.click()}
                                className="mt-3 w-full border border-ink/10 py-2 text-xs tracking-wider text-ink-soft uppercase transition-colors hover:border-terracotta hover:text-terracotta"
                            >
                                Remplacer
                            </button>
                        )}

                        {form.errors.cover && (
                            <FieldError>{form.errors.cover}</FieldError>
                        )}
                    </div>
                </div>

                <div className="space-y-3">
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.title.trim()}
                        className="flex w-full items-center justify-center gap-2 bg-ink py-4 text-xs font-bold tracking-widest text-cream uppercase transition-colors hover:bg-terracotta disabled:opacity-50"
                    >
                        {form.processing ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="h-4 w-4" />
                        )}
                        {isEdit ? 'Enregistrer' : 'Créer l’article'}
                    </button>

                    {isEdit && (
                        <Link
                            href={route('admin.blogs.preview', blog.id)}
                            className="flex w-full items-center justify-center gap-2 border border-ink/10 py-3.5 text-xs font-bold tracking-widest text-ink-soft uppercase transition-colors hover:border-terracotta hover:text-terracotta"
                        >
                            <Eye className="h-4 w-4" />
                            Aperçu
                        </Link>
                    )}

                    {isEdit && form.isDirty && (
                        <p className="text-center text-[11px] text-terracotta">
                            Enregistrez avant d’ouvrir l’aperçu — il affiche la
                            version sauvegardée.
                        </p>
                    )}
                </div>
            </aside>
        </form>
    );
}

function FieldError({ children }: { children: React.ReactNode }) {
    return <p className="mt-1.5 text-xs text-red-600">{children}</p>;
}

export default BlogForm;
