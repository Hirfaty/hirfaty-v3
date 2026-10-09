import { Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Archive,
    CheckCircle2,
    Eye,
    FileText,
    FileUp,
    Globe,
    ImageOff,
    ImagePlus,
    Loader2,
    Save,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { importDocx, MAX_DOCX_BYTES } from '@/lib/docx-import';
import type { Blog, BlogStatus } from '@/types';

/** Human labels for Blog::STATUSES, which arrives as raw strings. */
const STATUS_LABELS: Record<BlogStatus, string> = {
    published: 'Publié',
    archived: 'Archivé',
};

const STATUS_HINTS: Record<BlogStatus, string> = {
    published: 'En ligne sur le journal public dès l’enregistrement.',
    archived: 'Invisible pour le public, mais conservé ici.',
};

const STATUS_ICONS: Record<BlogStatus, typeof Globe> = {
    published: Globe,
    archived: Archive,
};

/** What the last Word import produced, for the notice under the import card. */
type ImportNotice =
    | { kind: 'success'; fileName: string; skippedImages: number }
    | { kind: 'error'; message: string };

/** True when the HTML holds no text and no image — an untouched editor. */
function isBlankHtml(html: string): boolean {
    return (
        !/<img\b/i.test(html) &&
        html
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/g, ' ')
            .trim() === ''
    );
}

function escapeHtml(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;

    return div.innerHTML;
}

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
    const docxInputRef = useRef<HTMLInputElement>(null);

    // Bumped by a Word import to remount the editor on the new content: the
    // editor only reads `value` when it mounts.
    const [editorKey, setEditorKey] = useState(0);
    const [importing, setImporting] = useState(false);
    const [importNotice, setImportNotice] = useState<ImportNotice | null>(null);

    // The existing cover, until the admin swaps or clears it. A local preview
    // URL replaces it the moment a new file is chosen.
    const [coverPreview, setCoverPreview] = useState<string | null>(
        blog?.cover_url ?? null,
    );

    const form = useForm({
        title: blog?.title ?? '',
        excerpt: blog?.excerpt ?? '',
        content: blog?.content ?? '',
        status: blog?.status ?? ('published' as BlogStatus),
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

    const importWord = async (file: File | undefined) => {
        if (docxInputRef.current) {
            docxInputRef.current.value = '';
        }

        if (!file) {
            return;
        }

        if (!file.name.toLowerCase().endsWith('.docx')) {
            setImportNotice({
                kind: 'error',
                message:
                    'Seuls les fichiers Word .docx sont acceptés. Un ancien .doc doit d’abord être réenregistré au format .docx depuis Word.',
            });

            return;
        }

        if (file.size > MAX_DOCX_BYTES) {
            setImportNotice({
                kind: 'error',
                message: 'Fichier trop lourd — 20 Mo maximum.',
            });

            return;
        }

        if (
            !isBlankHtml(form.data.content) &&
            !confirm(
                'Remplacer le contenu actuel de l’article par celui du document Word ?',
            )
        ) {
            return;
        }

        setImporting(true);
        setImportNotice(null);

        try {
            const imported = await importDocx(file, uploadUrl);
            const takeTitle =
                imported.title !== null && form.data.title.trim() === '';

            // The opening heading only moves to the title field while that is
            // still empty; otherwise it stays at the top of the body, so a
            // title the admin already typed is never overwritten.
            const html =
                imported.title !== null && !takeTitle
                    ? `<h2>${escapeHtml(imported.title)}</h2>${imported.html}`
                    : imported.html;

            if (isBlankHtml(html)) {
                setImportNotice({
                    kind: 'error',
                    message: 'Ce document ne contient aucun texte à importer.',
                });

                return;
            }

            form.setData((data) => ({
                ...data,
                content: html,
                title: takeTitle ? (imported.title ?? '') : data.title,
            }));
            setEditorKey((key) => key + 1);
            setImportNotice({
                kind: 'success',
                fileName: file.name,
                skippedImages: imported.skippedImages,
            });
        } catch {
            setImportNotice({
                kind: 'error',
                message:
                    'Ce fichier n’a pas pu être lu. Vérifiez qu’il s’agit bien d’un document Word (.docx) non protégé.',
            });
        } finally {
            setImporting(false);
        }
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
            {/* ── Word import ── */}
            <section className="rounded-xl border border-dashed border-border bg-card p-5 shadow-sm md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-ink-soft">
                            <FileText className="h-4 w-4" />
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold text-ink">
                                Partir d’un document Word
                            </h2>
                            <p className="mt-0.5 max-w-md text-xs text-ink-soft">
                                Le texte, les intertitres, les listes, les liens
                                et les images du .docx arrivent dans l’éditeur
                                ci-dessous, où vous pouvez tout relire et
                                corriger avant d’enregistrer.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        disabled={importing}
                        onClick={() => docxInputRef.current?.click()}
                        className="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-medium text-ink transition hover:border-ink/30 disabled:opacity-50"
                    >
                        {importing ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <FileUp className="h-4 w-4" />
                        )}
                        {importing ? 'Import en cours…' : 'Importer un .docx'}
                    </button>
                    <input
                        ref={docxInputRef}
                        type="file"
                        accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        className="hidden"
                        onChange={(e) => void importWord(e.target.files?.[0])}
                    />
                </div>

                {importNotice?.kind === 'success' && (
                    <div className="mt-4 flex items-start gap-2 rounded-lg bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-800">
                        <CheckCircle2 className="mt-px h-4 w-4 shrink-0" />
                        <div>
                            <p>
                                « {importNotice.fileName} » importé. Relisez le
                                contenu ci-dessous, puis enregistrez.
                            </p>
                            {importNotice.skippedImages > 0 && (
                                <p className="mt-1 text-amber-800">
                                    {importNotice.skippedImages === 1
                                        ? '1 image n’a pas pu être reprise'
                                        : `${importNotice.skippedImages} images n’ont pas pu être reprises`}{' '}
                                    (format non pris en charge ou plus de 4 Mo)
                                    — ajoutez-les depuis la barre d’outils de
                                    l’éditeur.
                                </p>
                            )}
                        </div>
                    </div>
                )}
                {importNotice?.kind === 'error' && (
                    <div className="mt-4 flex items-start gap-2 rounded-lg bg-destructive/5 px-3.5 py-2.5 text-xs text-destructive">
                        <AlertTriangle className="mt-px h-4 w-4 shrink-0" />
                        <p>{importNotice.message}</p>
                    </div>
                )}
            </section>

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
                    key={editorKey}
                    value={form.data.content}
                    onChange={(html) => form.setData('content', html)}
                    uploadUrl={uploadUrl}
                    reportInitialHtml={editorKey > 0}
                />
                {form.errors.content && (
                    <FieldError>{form.errors.content}</FieldError>
                )}
            </Section>

            {/* ── 5. Publication ── */}
            <Section
                title="Publication"
                step={5}
                hint="Pas de programmation : l’article est en ligne ou archivé."
            >
                <div
                    role="radiogroup"
                    aria-label="Statut"
                    className="grid gap-3 sm:grid-cols-2"
                >
                    {statuses.map((status) => {
                        const Icon = STATUS_ICONS[status];
                        const active = form.data.status === status;

                        return (
                            <button
                                key={status}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                onClick={() => form.setData('status', status)}
                                className={`flex items-start gap-3 rounded-lg border p-4 text-left transition ${
                                    active
                                        ? 'border-ink bg-ink/[0.03] ring-2 ring-ink/10'
                                        : 'border-border hover:border-ink/30'
                                }`}
                            >
                                <Icon
                                    className={`mt-0.5 h-4 w-4 shrink-0 ${active ? 'text-ink' : 'text-ink-soft'}`}
                                />
                                <span>
                                    <span className="block text-sm font-medium text-ink">
                                        {STATUS_LABELS[status]}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        {STATUS_HINTS[status]}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>
                {form.errors.status && (
                    <FieldError>{form.errors.status}</FieldError>
                )}
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
                    {isEdit
                        ? 'Enregistrer'
                        : form.data.status === 'published'
                          ? 'Publier l’article'
                          : 'Enregistrer dans les archives'}
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
