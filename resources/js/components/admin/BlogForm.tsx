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
 * A single column on purpose: the fields read as one ordered checklist —
 * title, chapô, cover, body, publication — rather than a form split across
 * a main pane and a sidebar, which hid the publication controls off to the
 * side while the admin was still writing.
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
        <form onSubmit={handleSubmit} className="mx-auto max-w-4xl space-y-6">
            {/* ── 1. Title ── */}
            <Section title="Titre" step={1}>
                <input
                    id="title"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="La poterie de Safi, de la terre au four"
                    className="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-lg font-medium text-ink transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
                {form.errors.title && (
                    <FieldError>{form.errors.title}</FieldError>
                )}
            </Section>

            {/* ── 2. Excerpt ── */}
            <Section
                title="Chapô"
                step={2}
                hint="Le résumé affiché sur la liste des articles."
            >
                <textarea
                    id="excerpt"
                    rows={3}
                    maxLength={500}
                    value={form.data.excerpt}
                    onChange={(e) => form.setData('excerpt', e.target.value)}
                    placeholder="Deux phrases qui donnent envie de lire la suite."
                    className="w-full resize-y rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-ink transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
                <p className="mt-1 text-right text-[11px] text-ink-soft">
                    {form.data.excerpt.length}/500
                </p>
                {form.errors.excerpt && (
                    <FieldError>{form.errors.excerpt}</FieldError>
                )}
            </Section>

            {/* ── 3. Cover ── */}
            <Section
                title="Image de couverture"
                step={3}
                hint="JPEG, PNG ou WebP — 4 Mo maximum."
            >
                {coverPreview ? (
                    <div className="relative overflow-hidden rounded-lg">
                        <img
                            src={coverPreview}
                            alt=""
                            className="aspect-16/6 w-full object-cover"
                        />
                        <button
                            type="button"
                            onClick={clearCover}
                            className="absolute top-2 right-2 flex items-center gap-1.5 rounded-md bg-ink/80 px-2.5 py-1.5 text-[11px] font-medium text-white backdrop-blur transition-colors hover:bg-destructive"
                        >
                            <ImageOff className="h-3.5 w-3.5" />
                            Retirer
                        </button>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => coverInputRef.current?.click()}
                        className="flex aspect-16/6 w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-muted/40 text-ink-soft transition-colors hover:border-ink/40 hover:text-ink"
                    >
                        <ImagePlus className="h-6 w-6" />
                        <span className="text-xs">Choisir une image</span>
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
                        className="mt-3 w-full rounded-lg border border-border py-2 text-xs font-medium text-ink-soft transition-colors hover:border-ink/30 hover:text-ink"
                    >
                        Remplacer l’image
                    </button>
                )}

                {form.errors.cover && (
                    <FieldError>{form.errors.cover}</FieldError>
                )}
            </Section>

            {/* ── 4. Body ── */}
            <Section
                title="Contenu"
                step={4}
                hint="La barre d’outils reste visible pendant que vous écrivez."
            >
                <RichTextEditor
                    value={blog?.content ?? ''}
                    onChange={(html) => form.setData('content', html)}
                    uploadUrl={uploadUrl}
                />
                {form.errors.content && (
                    <FieldError>{form.errors.content}</FieldError>
                )}
            </Section>

            {/* ── 5. Publication ── */}
            <Section title="Publication" step={5}>
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            htmlFor="status"
                            className="mb-1.5 block text-sm font-medium text-ink"
                        >
                            Statut
                        </label>
                        <select
                            id="status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as BlogStatus,
                                )
                            }
                            className="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-ink transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
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

                    <div>
                        <label
                            htmlFor="published_at"
                            className="mb-1.5 block text-sm font-medium text-ink"
                        >
                            Date de parution
                        </label>
                        <input
                            id="published_at"
                            type="datetime-local"
                            value={form.data.published_at}
                            onChange={(e) =>
                                form.setData('published_at', e.target.value)
                            }
                            className="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-ink transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
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
            </Section>

            {/* ── 6. Actions ── */}
            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-5 shadow-sm">
                <button
                    type="submit"
                    disabled={form.processing || !form.data.title.trim()}
                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90 disabled:opacity-50"
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
                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-5 py-2.5 text-sm font-medium text-ink-soft transition hover:border-ink/30 hover:text-ink"
                    >
                        <Eye className="h-4 w-4" />
                        Aperçu
                    </Link>
                )}

                {isEdit && form.isDirty && (
                    <p className="text-xs text-amber-700">
                        Enregistrez avant d’ouvrir l’aperçu — il affiche la
                        version sauvegardée.
                    </p>
                )}
            </div>
        </form>
    );
}

/**
 * One numbered block of the form. The step number is what makes the single
 * column read as an ordered sequence rather than a wall of inputs.
 */
function Section({
    title,
    step,
    hint,
    children,
}: {
    title: string;
    step: number;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-5 shadow-sm md:p-6">
            <div className="mb-4 flex items-baseline gap-2.5">
                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-ink-soft">
                    {step}
                </span>
                <div>
                    <h2 className="text-sm font-semibold text-ink">{title}</h2>
                    {hint && (
                        <p className="mt-0.5 text-xs text-ink-soft">{hint}</p>
                    )}
                </div>
            </div>
            {children}
        </section>
    );
}

function FieldError({ children }: { children: React.ReactNode }) {
    return <p className="mt-1.5 text-xs text-destructive">{children}</p>;
}

export default BlogForm;
