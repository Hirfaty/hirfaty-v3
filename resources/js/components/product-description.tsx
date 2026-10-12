import { cn } from '@/lib/utils';

/**
 * A product description as the admin wrote it in the rich-text editor — lines,
 * blank lines, spaces and formatting included (see `.product-description` in
 * app.css). The HTML is safe to inject: Admin\ProductController runs every
 * description through HtmlSanitizer's allowlist before it is stored.
 */
export function ProductDescription({
    html,
    className,
}: {
    html: string;
    className?: string;
}) {
    return (
        <div
            className={cn('product-description', className)}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

export default ProductDescription;
