import { Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    CirclePlay,
    CircleX,
    Eye,
    History,
    Images,
    Library,
    Package,
    Play,
    PlayCircle,
    Search,
    SortAscIcon,
    SquareArrowOutUpRight,
    Verified,
    ZoomIn,
} from 'lucide-react';
import type { ReactNode} from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { WarmFill } from '@/components/WarmFill';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type {
    Category,
    Media,
    PaginatedData,
    Product,
    ProductStatus,
} from '@/types';

const TABS = ['All', 'New', 'Flagged'] as const;

const statusPill: Record<ProductStatus, string> = {
    pending_review: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    archived: 'bg-rose-100 text-rose-800',
};

const statusLabel: Record<ProductStatus, string> = {
    pending_review: 'Review',
    published: 'Approved',
    rejected: 'Rejected',
    archived: 'Archived',
};

export const REJECTION_REASONS = [
    'Photos de mauvaise qualité',
    'Description incomplète ou trompeuse',
    'Prix non conforme aux standards',
    'Produit non artisanal',
    'Catégorie incorrecte',
    'Contenu interdit ou inapproprié',
    "Doublon d'une annonce existante",
    'Autre',
] as const;

interface AdminProductsProps {
    products: PaginatedData<Product & { media: Media[]; category: Category }>;
    categories: Category[];
    filters: {
        search?: string;
        category_id?: string;
        status?: string;
    };
    pending_count: number;
}

function AdminProducts({
    products,
    categories,
    filters,
    pending_count,
}: AdminProductsProps) {
    const [mediaOpen, setMediaOpen] = useState(false);
    const [mediaIndex, setMediaIndex] = useState(0);
    const [selectedId, setSelectedId] = useState<number | null>(
        products.data.find((p) => p.status === 'pending_review')?.id ??
            products.data[0]?.id ??
            null,
    );

    const [note, setNote] = useState('');
    const [search, setSearch] = useState(filters.search || '');

    const { data, setData, patch, processing } = useForm<{
        rejection_reason: string;
    }>({
        rejection_reason: REJECTION_REASONS[0],
    });

    useEffect(() => {
        if (!selectedId && products.data.length > 0) {
            setSelectedId(products.data[0].id);
        } else if (
            selectedId &&
            !products.data.find((p) => p.id === selectedId)
        ) {
            setSelectedId(products.data[0]?.id ?? null);
        }
    }, [products.data]);

    const tab = useMemo(() => {
        if (filters.status === 'pending_review') {
return 'New';
}

        if (filters.status === 'rejected') {
return 'Flagged';
}

        return 'All';
    }, [filters.status]);

    const handleTabChange = (t: (typeof TABS)[number]) => {
        const status =
            t === 'New'
                ? 'pending_review'
                : t === 'Flagged'
                  ? 'rejected'
                  : undefined;
        router.get(
            route('admin.products.index'),
            { ...filters, status, page: 1 },
            { preserveState: true, replace: true },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('admin.products.index'),
            { ...filters, search, page: 1 },
            { preserveState: true, replace: true },
        );
    };

    const selected: (Product & { media: Media[] }) | undefined =
        products.data.find((p) => p.id === selectedId);
    const pendingCount = products.total; // Using total from pagination if filtered by 'New'

    const approve = () => {
        router.patch(route('admin.products.approve', selected?.id));
    };
    const reject = () => {
        patch(route('admin.products.reject', selected?.id));
    };

    return (
        <div
            className="mx-auto max-w-[1400px] p-6 md:p-8"
            style={{ fontFamily: 'Inter, sans-serif' }}
        >
            {/* Title */}
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="h-editorial text-[30px] font-semibold">
                        Product Approvals
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        <span className="font-semibold text-primary">
                            {pending_count} Pending Approvals
                        </span>
                        <span className="mx-2">•</span>Across{' '}
                        {categories.length}
                        categories
                    </p>
                </div>
            </div>

            {/* Layout */}
            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1fr_420px]">
                {/* Left column: table */}
                <section className="border-line overflow-hidden rounded-lg border bg-paper">
                    {/* Tabs & Search */}
                    <div className="border-line flex flex-wrap items-center justify-between gap-4 border-b px-4 py-3">
                        <div className="flex items-center gap-1 rounded-lg bg-cream p-1">
                            {TABS.map((t) => (
                                <button
                                    key={t}
                                    onClick={() => handleTabChange(t)}
                                    className={`rounded-md px-4 py-1.5 text-[13px] transition-colors ${
                                        tab === t
                                            ? 'bg-white font-semibold text-ink shadow-sm'
                                            : 'text-ink-soft hover:text-ink'
                                    }`}
                                >
                                    {t}
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center gap-3">
                            <form onSubmit={handleSearch} className="relative">
                                <Search className="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                                <input
                                    type="search"
                                    placeholder="Search products..."
                                    className="border-line h-9 w-64 rounded-lg bg-cream pr-4 pl-9 text-[13px] focus:border-terracotta-deep focus:ring-1 focus:ring-terracotta-deep focus:outline-none"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </form>
                            <div className="flex items-center gap-3 text-[12px] text-ink-soft">
                                <button className="inline-flex items-center gap-1 hover:text-ink">
                                    <SortAscIcon className="h-4 w-4" />
                                    Sort
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-primary/30 text-left text-[11px] tracking-wider text-ink-soft uppercase">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">
                                        Product Name
                                    </th>
                                    <th className="px-5 py-3 font-semibold">
                                        Category
                                    </th>
                                    <th className="px-5 py-3 font-semibold">
                                        Submitted By
                                    </th>
                                    <th className="px-5 py-3 font-semibold">
                                        Date
                                    </th>
                                    <th className="px-5 py-3 text-right font-semibold">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-line divide-y">
                                {products.data.map((p) => {
                                    const isSel = p.id === selectedId;

                                    return (
                                        <tr
                                            key={p.id}
                                            onClick={() => setSelectedId(p.id)}
                                            className={`cursor-pointer transition-colors ${isSel ? 'bg-terracotta-deep/10' : 'hover:bg-cream/40'}`}
                                        >
                                            <td className="px-5 py-3">
                                                <p className="font-semibold text-ink">
                                                    {p.name}
                                                </p>
                                                <p className="text-[11px] text-ink-soft">
                                                    ID: #{p.id}
                                                </p>
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {p.category?.name}
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {p.store.store_name}
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {formatDate(p.updated_at)}
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ${statusPill[p.status]}`}
                                                    >
                                                        {isSel &&
                                                        p.status ===
                                                            'pending_review'
                                                            ? 'Reviewing'
                                                            : statusLabel[
                                                                  p.status
                                                              ]}
                                                    </span>
                                                    <Link
                                                        href={route('admin.products.show', p.id)}
                                                        title="View details"
                                                        className="flex h-7 w-7 items-center justify-center rounded text-ink-soft hover:bg-cream hover:text-ink"
                                                    >
                                                        <SquareArrowOutUpRight className="text-[16px]" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {products.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-5 py-10 text-center text-ink-soft"
                                        >
                                            No products found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer / Pagination */}
                    <div className="border-line flex flex-wrap items-center justify-between gap-4 border-t px-5 py-3 text-[12px] text-ink-soft">
                        <p>
                            Showing {products.from || 0} to {products.to || 0}{' '}
                            of {products.total} items
                        </p>
                        <div className="flex items-center gap-1">
                            {products.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    preserveScroll
                                    className={`flex h-8 min-w-[32px] items-center justify-center rounded px-2 transition-colors ${
                                        link.active
                                            ? 'bg-terracotta-deep font-semibold text-white'
                                            : 'text-ink-soft hover:bg-cream'
                                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                </section>

                {/* Right column: review panel */}
                <aside className="space-y-4">
                    {selected ? (
                        <>
                            <div className="border-line overflow-hidden rounded-lg border bg-paper">
                                <div className="border-line flex items-center gap-2 border-b px-5 py-3 text-ink">
                                    <Eye className="text-[18px] text-terracotta-deep" />
                                    <p className="text-[13px] font-semibold">
                                        Reviewing: {selected.name}
                                    </p>
                                </div>

                                {/* Image */}
                                <div className="relative aspect-[4/3] bg-cream">
                                    {selected.media.length > 0 ? (
                                        <img
                                            src={selected.media[0].url}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <WarmFill
                                            variant="cream"
                                            className="h-full w-full"
                                        />
                                    )}

                                    <div className="absolute top-2 right-2 flex gap-1.5">
                                        <button
                                            onClick={() => {
                                                setMediaIndex(0);
                                                setMediaOpen(true);
                                            }}
                                            title="Zoom image"
                                            className="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-ink backdrop-blur hover:bg-white"
                                        >
                                            <ZoomIn className="text-[18px]" />
                                        </button>
                                        <button
                                            onClick={() => {
                                                setMediaIndex(0);
                                                setMediaOpen(true);
                                            }}
                                            title="Browse images & video"
                                            className="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-ink backdrop-blur hover:bg-white"
                                        >
                                            <Images className="!text-[18px]" />
                                        </button>
                                    </div>
                                    {/* {selected?.videos?.length > 0 && (
                                        <button
                                            onClick={() => {
                                                setMediaIndex(
                                                    selected.images.length,
                                                );
                                                setMediaOpen(true);
                                            }}
                                            className="absolute bottom-2 left-2 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-ink backdrop-blur hover:bg-white"
                                        >
                                            <PlayCircle className="text-[14px]" />
                                            Watch video
                                        </button>
                                    )} */}
                                </div>

                                <div className="space-y-4 p-5">
                                    <div>
                                        <p className="text-[11px] font-semibold tracking-wider text-ink-soft uppercase">
                                            Product Summary
                                        </p>
                                        <p className="mt-2 text-[13px] leading-relaxed text-ink">
                                            {selected.description}
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="border-line rounded-lg border p-3">
                                            <p className="text-[11px] tracking-wider text-ink-soft uppercase">
                                                Price
                                            </p>
                                            <div className="mt-1 flex items-baseline gap-2">
                                                {selected.sale_price ? (
                                                    <>
                                                        <p className="text-[20px] font-bold text-terracotta-deep">
                                                            {
                                                                selected.sale_price
                                                            }{' '}
                                                            <span className="text-xs font-medium">
                                                                MAD
                                                            </span>
                                                        </p>
                                                        <p className="text-[14px] text-ink-soft line-through">
                                                            {
                                                                selected.base_price
                                                            }
                                                        </p>
                                                    </>
                                                ) : (
                                                    <p className="text-[20px] font-bold text-ink">
                                                        {selected.base_price}{' '}
                                                        <span className="text-xs font-medium">
                                                            MAD
                                                        </span>
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="border-line rounded-lg border p-3">
                                            <p className="text-[11px] tracking-wider text-ink-soft uppercase">
                                                Stock
                                            </p>
                                            <p className="mt-1 text-[20px] font-bold text-ink">
                                                {selected.stock}{' '}
                                                <span className="text-xs font-medium">
                                                    Units
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    {selected.status === 'pending_review' ? (
                                        <div className="space-y-2">
                                            <button
                                                onClick={approve}
                                                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-terracotta-deep text-[14px] font-semibold text-white transition-opacity hover:opacity-90"
                                            >
                                                <CircleCheck className="text-[18px]" />
                                                Approve Product
                                            </button>
                                            <button
                                                onClick={reject}
                                                className="border-line inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border text-[14px] font-semibold text-ink transition-colors hover:bg-cream"
                                            >
                                                <CircleX className="text-[18px]" />
                                                Reject &amp; Send Feedback
                                            </button>

                                            <div className="space-y-3 pt-3">
                                                <div>
                                                    <label className="text-[11px] font-semibold tracking-wider text-ink-soft uppercase">
                                                        Select Rejection Reason
                                                    </label>
                                                    <div className="mt-2 grid grid-cols-2 gap-1.5">
                                                        {REJECTION_REASONS.slice(
                                                            0,
                                                            4,
                                                        ).map((r) => (
                                                            <button
                                                                key={r}
                                                                onClick={() =>
                                                                    setData(
                                                                        'rejection_reason',
                                                                        r,
                                                                    )
                                                                }
                                                                className={`rounded-full border px-2.5 py-1.5 text-left text-[11px] transition-colors ${
                                                                    data.rejection_reason ===
                                                                    r
                                                                        ? 'border-terracotta-deep bg-terracotta-deep/10 font-semibold text-terracotta-deep'
                                                                        : 'border-line text-ink-soft hover:border-ink'
                                                                }`}
                                                            >
                                                                {r}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                                <div>
                                                    <label className="text-[11px] font-semibold tracking-wider text-ink-soft uppercase">
                                                        Custom Message (Internal
                                                        Only)
                                                    </label>
                                                    <textarea
                                                        value={note}
                                                        onChange={(e) =>
                                                            setNote(
                                                                e.target.value,
                                                            )
                                                        }
                                                        rows={3}
                                                        placeholder="Add a note for the merchant…"
                                                        className="border-line mt-2 w-full resize-none rounded-lg border bg-cream px-3 py-2 text-[13px] focus:border-terracotta-deep focus:ring-1 focus:ring-terracotta-deep focus:outline-none"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="rounded-lg bg-cream p-3 text-[12px] text-ink-soft">
                                            This product has already been{' '}
                                            {statusLabel[
                                                selected.status
                                            ].toLowerCase()}
                                            .
                                            {selected.rejection_reason && (
                                                <p className="mt-1 text-ink">
                                                    Reason:{' '}
                                                    {selected.rejection_reason}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <Link
                                href={route('admin.products.show', selected.id)}
                                className="border-line inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border text-center text-[13px] font-semibold text-ink hover:bg-cream"
                            >
                                <SquareArrowOutUpRight className="text-[16px]" />
                                Open full product detail
                            </Link>
                        </>
                    ) : (
                        <div className="border-line flex h-64 flex-col items-center justify-center rounded-lg border border-dashed bg-paper text-ink-soft">
                            <Package className="mb-2 h-10 w-10 opacity-20" />
                            <p className="text-sm">
                                Select a product to review
                            </p>
                        </div>
                    )}
                </aside>
            </div>

            {/* Media lightbox */}
            {/* <Dialog open={mediaOpen} onOpenChange={setMediaOpen}>
                <DialogContent className="max-w-4xl overflow-hidden bg-[hsl(var(--paper))] p-0">
                    {selected &&
                        (selected.images.length > 0 ||
                            selected.videos.length > 0) &&
                        (() => {
                            const allMedia = [
                                ...selected.images,
                                ...selected.videos,
                            ];
                            const m = allMedia[mediaIndex];
                            if (!m) return null;
                            return (
                                <div>
                                    <div className="flex items-center justify-between border-b border-[hsl(var(--line))] px-5 py-3">
                                        <p className="text-[13px] font-semibold text-[hsl(var(--ink))]">
                                            {selected.name} —{' '}
                                            {m.type === 'video'
                                                ? 'Video'
                                                : `Image ${mediaIndex + 1}`}
                                        </p>
                                        <p className="text-[11px] text-[hsl(var(--ink-soft))]">
                                            {mediaIndex + 1} / {allMedia.length}
                                        </p>
                                    </div>
                                    <div className="relative aspect-[16/10] bg-black">
                                        {m.type === 'image' ? (
                                            <img
                                                src={m.url}
                                                alt=""
                                                className="h-full w-full object-contain"
                                            />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center bg-black">
                                                <Play className="text-[44px] text-white" />
                                                <p className="ml-4 text-white">
                                                    Video Preview
                                                </p>
                                            </div>
                                        )}
                                        <button
                                            onClick={() =>
                                                setMediaIndex(
                                                    (i) =>
                                                        (i -
                                                            1 +
                                                            allMedia.length) %
                                                        allMedia.length,
                                                )
                                            }
                                            className="absolute top-1/2 left-3 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink hover:bg-white"
                                        >
                                            <ChevronLeft className="!text-[22px]" />
                                        </button>
                                        <button
                                            onClick={() =>
                                                setMediaIndex(
                                                    (i) =>
                                                        (i + 1) %
                                                        allMedia.length,
                                                )
                                            }
                                            className="absolute top-1/2 right-3 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[hsl(var(--ink))] hover:bg-white"
                                        >
                                            <ChevronRight className="!text-[22px]" />
                                        </button>
                                    </div>
                                    <div className="grid grid-cols-5 gap-2 bg-[hsl(var(--cream))]/40 p-3">
                                        {allMedia.map((mm, i) => (
                                            <button
                                                key={i}
                                                onClick={() => setMediaIndex(i)}
                                                className={`relative aspect-square overflow-hidden rounded border-2 transition-colors ${
                                                    i === mediaIndex
                                                        ? 'border-[hsl(var(--terracotta-deep))]'
                                                        : 'border-transparent hover:border-[hsl(var(--line))]'
                                                }`}
                                            >
                                                <img
                                                    src={
                                                        mm.type === 'image'
                                                            ? mm.url
                                                            : ''
                                                    }
                                                    className="h-full w-full object-cover"
                                                />
                                                {mm.type === 'video' && (
                                                    <div className="absolute inset-0 flex items-center justify-center bg-black/20">
                                                        <CirclePlay className="text-[20px] text-white" />
                                                    </div>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            );
                        })()}
                </DialogContent>
            </Dialog> */}
        </div>
    );
}

AdminProducts.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default AdminProducts;
