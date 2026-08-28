import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import {
    Bold,
    Italic,
    Heading1,
    Heading2,
    List,
    ListOrdered,
} from 'lucide-react';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

interface RichEditorProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}

export function RichEditor({
    value,
    onChange,
    placeholder = "Racontez l'histoire derrière cette pièce...",
    className,
}: RichEditorProps) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2],
                },
            }),
            Placeholder.configure({
                placeholder,
            }),
        ],
        content: value,
        onUpdate: ({ editor }) => {
            const html = editor.getHTML();
            onChange(html);
        },
        editorProps: {
            attributes: {
                class: cn(
                    'prose prose-sm dark:prose-invert max-w-none focus:outline-none min-h-[200px] p-4 font-sans',
                    className,
                ),
            },
        },
        immediatelyRender: false,
    });

    if (!editor) {
        return null;
    }

    return (
        <div className="flex flex-col overflow-hidden rounded-lg border border-border/60 bg-background/50 transition-all focus-within:ring-2 focus-within:ring-primary/20">
            <div className="flex items-center gap-1 border-b border-border/60 bg-muted/30 p-1">
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bold')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBold().run()
                    }
                    className="h-8 w-8 p-0"
                    aria-label="Bold"
                >
                    <Bold className="h-4 w-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('italic')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleItalic().run()
                    }
                    className="h-8 w-8 p-0"
                    aria-label="Italic"
                >
                    <Italic className="h-4 w-4" />
                </Toggle>
                <div className="mx-1 h-4 w-px bg-border/60" />
                <Toggle
                    size="sm"
                    pressed={editor.isActive('heading', { level: 1 })}
                    onPressedChange={() =>
                        editor.chain().focus().toggleHeading({ level: 1 }).run()
                    }
                    className="h-8 w-8 p-0"
                    aria-label="Heading 1"
                >
                    <Heading1 className="h-4 w-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('heading', { level: 2 })}
                    onPressedChange={() =>
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                    className="h-8 w-8 p-0"
                    aria-label="Heading 2"
                >
                    <Heading2 className="h-4 w-4" />
                </Toggle>
                <div className="mx-1 h-4 w-px bg-border/60" />
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bulletList')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                    className="h-8 w-8 p-0"
                    aria-label="Bullet List"
                >
                    <List className="h-4 w-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('orderedList')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                    className="h-8 w-8 p-0"
                    aria-label="Ordered List"
                >
                    <ListOrdered className="h-4 w-4" />
                </Toggle>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
