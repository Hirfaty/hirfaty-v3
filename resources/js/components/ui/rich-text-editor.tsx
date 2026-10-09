import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Code2,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Loader2,
    Minus,
    Quote,
    Redo2,
    Strikethrough,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { MAX_EDITOR_IMAGE_BYTES, uploadEditorImage } from '@/lib/editor-upload';
import { cn } from '@/lib/utils';

interface RichTextEditorProps {
    value: string;
    onChange: (html: string) => void;
    /**
     * Endpoint that accepts an `image` file and answers `{ url }`. Images are
     * uploaded the moment they are dropped rather than with the form, because
     * the document needs a real src to render immediately — see
     * Admin\BlogController::uploadImage.
     */
    uploadUrl: string;
    placeholder?: string;
    className?: string;
    /**
     * Report the editor's own HTML for `value` as soon as it mounts. For
     * content that did not come from this editor (a Word import): Tiptap drops
     * whatever it cannot represent, and without this the form would still hold
     * — and save — the original markup rather than what the admin sees.
     */
    reportInitialHtml?: boolean;
}

export function RichTextEditor({
    value,
    onChange,
    uploadUrl,
    placeholder = 'Écrivez votre article…',
    className,
    reportInitialHtml = false,
}: RichTextEditorProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                // Reserved for the post's own title field, so the body starts
                // at h2 and the page keeps a single h1.
                heading: { levels: [2, 3, 4] },
                link: {
                    openOnClick: false,
                    autolink: true,
                    // Mirrors the server-side allowlist in HtmlSanitizer: a
                    // javascript: href pasted in here would be stripped on save
                    // anyway, so refusing it now avoids a confusing round-trip.
                    protocols: ['http', 'https', 'mailto'],
                },
            }),
            Image.configure({
                // Uploaded files only. Base64 would inline megabytes of image
                // data into the content column on every paste.
                allowBase64: false,
            }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder }),
        ],
        content: value,
        // Vite pre-renders pages for SSR (npm run build:ssr); without this
        // Tiptap warns about a hydration mismatch on every mount.
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: cn('blog-article px-5 py-4'),
            },
            handlePaste: (_view, event) => handleImageEvent(event.clipboardData),
            handleDrop: (_view, event) => handleImageEvent(event.dataTransfer),
        },
        onCreate: ({ editor }) => {
            if (reportInitialHtml) {
                onChange(editor.getHTML());
            }
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    /**
     * Intercepts images arriving by paste or drop. Returns true only when an
     * image was found, so ProseMirror keeps its default handling for text.
     */
    function handleImageEvent(data: DataTransfer | null): boolean {
        const file = Array.from(data?.files ?? []).find((f) =>
            f.type.startsWith('image/'),
        );

        if (!file) {
            return false;
        }

        void upload(file);

        return true;
    }

    async function upload(file: File) {
        if (file.size > MAX_EDITOR_IMAGE_BYTES) {
            setError('Image trop lourde — 4 Mo maximum.');

            return;
        }

        setError(null);
        setUploading(true);

        try {
            const url = await uploadEditorImage(uploadUrl, file);

            editor?.chain().focus().setImage({ src: url, alt: file.name }).run();
        } catch {
            setError("L'image n'a pas pu être envoyée. Réessayez.");
        } finally {
            setUploading(false);
        }
    }

    function promptForLink() {
        if (!editor) {
            return;
        }

        const current = editor.getAttributes('link').href as string | undefined;
        const href = window.prompt('Adresse du lien', current ?? 'https://');

        // null means the dialog was dismissed — leave the document alone.
        if (href === null) {
            return;
        }

        if (href.trim() === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: href.trim() })
            .run();
    }

    if (!editor) {
        return null;
    }

    return (
        /*
         * No `overflow-hidden` here, deliberately: it would make the sticky
         * toolbar below scroll away with the document instead of pinning. The
         * corners are rounded on the toolbar and the content edges instead.
         */
        <div
            className={cn(
                'rounded-xl border border-border bg-white',
                className,
            )}
        >
            <Toolbar
                editor={editor}
                uploading={uploading}
                onPickImage={() => fileInputRef.current?.click()}
                onLink={promptForLink}
            />

            <EditorContent editor={editor} />

            <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file) {
                        void upload(file);
                    }

                    // Reset, or picking the same file twice in a row fires no
                    // change event the second time.
                    event.target.value = '';
                }}
            />

            {error && (
                <p className="border-t border-border bg-red-50 px-5 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}
        </div>
    );
}

function Toolbar({
    editor,
    uploading,
    onPickImage,
    onLink,
}: {
    editor: Editor;
    uploading: boolean;
    onPickImage: () => void;
    onLink: () => void;
}) {
    /*
     * Tiptap v3's useEditor does not re-render on every transaction, so the
     * pressed states below have to be pulled through useEditorState — reading
     * editor.isActive() straight in render would leave the buttons stuck on
     * whatever they showed at mount.
     */
    const state = useEditorState({
        editor,
        selector: ({ editor }) => ({
            bold: editor.isActive('bold'),
            italic: editor.isActive('italic'),
            underline: editor.isActive('underline'),
            strike: editor.isActive('strike'),
            h2: editor.isActive('heading', { level: 2 }),
            h3: editor.isActive('heading', { level: 3 }),
            bulletList: editor.isActive('bulletList'),
            orderedList: editor.isActive('orderedList'),
            blockquote: editor.isActive('blockquote'),
            codeBlock: editor.isActive('codeBlock'),
            link: editor.isActive('link'),
            alignLeft: editor.isActive({ textAlign: 'left' }),
            alignCenter: editor.isActive({ textAlign: 'center' }),
            alignRight: editor.isActive({ textAlign: 'right' }),
            canUndo: editor.can().undo(),
            canRedo: editor.can().redo(),
        }),
    });

    /*
     * Pinned while the body scrolls, so the formatting controls stay reachable
     * in a long article instead of being left at the top of the page.
     *
     * `top-14` clears the admin layout's own sticky header (h-14), and the
     * background is fully opaque rather than the previous cream/40 — a
     * translucent bar would show the article text sliding underneath it.
     */
    return (
        <div className="sticky top-14 z-20 flex flex-wrap items-center gap-0.5 rounded-t-xl border-b border-border bg-cream px-2 py-1.5">
            <ToolbarButton
                label="Gras"
                active={state.bold}
                onClick={() => editor.chain().focus().toggleBold().run()}
            >
                <Bold className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Italique"
                active={state.italic}
                onClick={() => editor.chain().focus().toggleItalic().run()}
            >
                <Italic className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Souligné"
                active={state.underline}
                onClick={() => editor.chain().focus().toggleUnderline().run()}
            >
                <UnderlineIcon className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Barré"
                active={state.strike}
                onClick={() => editor.chain().focus().toggleStrike().run()}
            >
                <Strikethrough className="h-4 w-4" />
            </ToolbarButton>

            <Divider />

            <ToolbarButton
                label="Titre"
                active={state.h2}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                }
            >
                <Heading2 className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Sous-titre"
                active={state.h3}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                }
            >
                <Heading3 className="h-4 w-4" />
            </ToolbarButton>

            <Divider />

            <ToolbarButton
                label="Liste à puces"
                active={state.bulletList}
                onClick={() => editor.chain().focus().toggleBulletList().run()}
            >
                <List className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Liste numérotée"
                active={state.orderedList}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
            >
                <ListOrdered className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Citation"
                active={state.blockquote}
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
            >
                <Quote className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Bloc de code"
                active={state.codeBlock}
                onClick={() => editor.chain().focus().toggleCodeBlock().run()}
            >
                <Code2 className="h-4 w-4" />
            </ToolbarButton>

            <Divider />

            <ToolbarButton
                label="Aligner à gauche"
                active={state.alignLeft}
                onClick={() => editor.chain().focus().setTextAlign('left').run()}
            >
                <AlignLeft className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Centrer"
                active={state.alignCenter}
                onClick={() =>
                    editor.chain().focus().setTextAlign('center').run()
                }
            >
                <AlignCenter className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Aligner à droite"
                active={state.alignRight}
                onClick={() =>
                    editor.chain().focus().setTextAlign('right').run()
                }
            >
                <AlignRight className="h-4 w-4" />
            </ToolbarButton>

            <Divider />

            <ToolbarButton
                label={state.link ? 'Modifier le lien' : 'Ajouter un lien'}
                active={state.link}
                onClick={onLink}
            >
                <Link2 className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Retirer le lien"
                disabled={!state.link}
                onClick={() => editor.chain().focus().unsetLink().run()}
            >
                <Link2Off className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Insérer une image"
                disabled={uploading}
                onClick={onPickImage}
            >
                {uploading ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                    <ImagePlus className="h-4 w-4" />
                )}
            </ToolbarButton>
            <ToolbarButton
                label="Séparateur"
                onClick={() => editor.chain().focus().setHorizontalRule().run()}
            >
                <Minus className="h-4 w-4" />
            </ToolbarButton>

            <Divider />

            <ToolbarButton
                label="Annuler"
                disabled={!state.canUndo}
                onClick={() => editor.chain().focus().undo().run()}
            >
                <Undo2 className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Rétablir"
                disabled={!state.canRedo}
                onClick={() => editor.chain().focus().redo().run()}
            >
                <Redo2 className="h-4 w-4" />
            </ToolbarButton>
        </div>
    );
}

function ToolbarButton({
    label,
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            // Inside a <form>: without this the button submits the post on
            // every formatting click.
            type="button"
            title={label}
            aria-label={label}
            aria-pressed={active}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'rounded-md p-1.5 text-ink-soft transition-colors',
                'hover:bg-souk-brown/10 hover:text-souk-brown',
                'disabled:pointer-events-none disabled:opacity-35',
                active && 'bg-souk-brown/15 text-souk-brown',
            )}
        >
            {children}
        </button>
    );
}

function Divider() {
    return <span className="mx-1 h-5 w-px bg-border" aria-hidden="true" />;
}

export default RichTextEditor;
