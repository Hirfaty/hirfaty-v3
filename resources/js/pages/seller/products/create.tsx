import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    ImagePlus,
    Video,
    X,
    Check,
    Save,
    Package,
    Tag,
    Image as ImageIcon,
    LayoutDashboard,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import SellerLayout from '@/layouts/seller/layout';
import { validateMediaFile } from '@/lib/media-limits';
import { cn } from '@/lib/utils';
import type { Category } from '@/types';

type MediaItem = { id: string; name: string; url: string; file: File };

const steps = [
    { n: 1, label: 'Détails', icon: LayoutDashboard },
    { n: 2, label: 'Médias', icon: ImageIcon },
    { n: 3, label: 'Prix & Stock', icon: Tag },
    { n: 4, label: 'Livraison', icon: Truck },
    { n: 5, label: 'Révision', icon: Package },
];

function SellerProductNew({ categories }: { categories: Category[] }) {
    const [step, setStep] = useState(1);

    // Step 2
    const [images, setImages] = useState<MediaItem[]>([]);
    const [video, setVideo] = useState<MediaItem | null>(null);

    // Step 3
    const [saleEnabled, setSaleEnabled] = useState(false);
    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});
    const {
        data,
        setData,
        post,
        processing,
        errors: serverErrors,
    } = useForm({
        name: '',
        description: '',
        category_id: '',
        base_price: '',
        sale_price: '',
        stock: 1,
        media: [] as File[],
    });

    const validateStep = (s: number) => {
        const e: Record<string, string> = {};

        if (s === 1) {
            if (!data.name.trim()) {
                e.name = 'Nom requis';
            }

            if (!data.description.trim()) {
                e.description = 'Description requise';
            }

            if (!data.category_id) {
                e.category_id = 'Catégorie requise';
            }
        }

        if (s === 2) {
            if (images.length < 1) {
                e.images = 'Au moins une image requise';
            }

            if (images.length > 5) {
                e.images = 'Maximum 5 images';
            }
        }

        if (s === 3) {
            if (!data.base_price || Number(data.base_price) <= 0) {
                e.basePrice = 'Prix invalide';
            }

            if (data.stock === undefined || data.stock < 0) {
                e.stock = 'Stock invalide';
            }

            if (saleEnabled) {
                if (!data.sale_price || Number(data.sale_price) <= 0) {
                    e.salePrice = 'Prix soldé invalide';
                } else if (Number(data.sale_price) >= Number(data.base_price)) {
                    e.salePrice = 'Doit être inférieur au prix de base';
                }
            }
        }

        if (s === 4) {
            // Step 4 no longer requires delivery option selection.
        }

        setStepErrors(e);

        return Object.keys(e).length === 0;
    };

    const next = () => {
        if (validateStep(step)) {
            setStep((s) => Math.min(5, s + 1));
        }
    };
    const back = () => setStep((s) => Math.max(1, s - 1));

    const handleImages = async (files: FileList | null) => {
        if (!files) {
            return;
        }

        const picked = Array.from(files).slice(0, 5 - images.length);
        const results = await Promise.all(
            picked.map(async (file) => ({
                file,
                rejection: await validateMediaFile(file),
            })),
        );

        const rejectionMessages = results
            .filter((r) => r.rejection)
            .map((r) => r.rejection!);

        setStepErrors((e) => ({
            ...e,
            images: rejectionMessages.join(' '),
        }));

        const newOnes: MediaItem[] = results
            .filter((r) => !r.rejection)
            .map(({ file: f }) => ({
                id: crypto.randomUUID(),
                name: f.name,
                url: URL.createObjectURL(f),
                file: f,
            }));

        if (newOnes.length === 0) {
            return;
        }

        setImages((prev) => {
            const updated = [...prev, ...newOnes];
            setData('media', [
                ...updated.map((item) => item.file),
                ...(video ? [video.file] : []),
            ]);

            return updated;
        });
    };

    const removeImage = (id: string) =>
        setImages((prev) => {
            const updated = prev.filter((i) => i.id !== id);
            setData('media', [
                ...updated.map((item) => item.file),
                ...(video ? [video.file] : []),
            ]);

            return updated;
        });

    const handleVideo = async (files: FileList | null) => {
        if (!files || !files[0]) {
            return;
        }

        const selected = files[0];
        const rejection = await validateMediaFile(selected);

        setStepErrors((e) => ({ ...e, video: rejection ?? '' }));

        if (rejection) {
            return;
        }

        setVideo({
            id: crypto.randomUUID(),
            name: selected.name,
            url: URL.createObjectURL(selected),
            file: selected,
        });
        setData('media', [...images.map((item) => item.file), selected]);
    };

    const publish = () => {
        if (
            !validateStep(1) ||
            !validateStep(2) ||
            !validateStep(3) ||
            !validateStep(4)
        ) {
            toast.error('Champs manquants', {
                description: 'Vérifiez chaque étape.',
            });

            return;
        }

        post(route('seller.products.store'), {
            forceFormData: true,
            onSuccess: () => {
                toast.success('Produit publié', {
                    description: `${data.name} ajouté au catalogue.`,
                });
            },
        });
    };

    // delivery options removed — shipping handled by Amana by default

    return (
        <div className="mx-auto max-w-[1000px] px-4 py-10 sm:px-6 lg:px-8">
            <Link
                href="/seller/products"
                className="mb-8 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft className="h-4 w-4" />
                Retour au catalogue
            </Link>

            <header className="mb-12">
                <div className="flex items-center gap-2 text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase">
                    <span className="inline-block h-px w-8 bg-border" />
                    Nouveau produit
                </div>
                <h1 className="mt-4 font-display text-4xl leading-tight md:text-5xl lg:text-6xl">
                    Donnez vie à <br /> une nouvelle pièce
                </h1>
                <p className="mt-4 max-w-xl text-muted-foreground">
                    Partagez l'histoire, le savoir-faire et les détails de votre
                    création avec notre communauté de passionnés.
                </p>
            </header>

            {/* Stepper */}
            <div className="mb-12 overflow-x-auto pb-4">
                <ol className="flex min-w-[750px] items-center gap-0">
                    {steps.map((s, i) => {
                        const active = step === s.n;
                        const done = step > s.n;
                        const StepIcon = s.icon;

                        return (
                            <li
                                key={s.n}
                                className={cn(
                                    'flex items-center',
                                    i < steps.length - 1 ? 'flex-1' : '',
                                )}
                            >
                                <button
                                    onClick={() => step > s.n && setStep(s.n)}
                                    disabled={step <= s.n}
                                    className={cn(
                                        'flex flex-col items-start gap-2 text-left transition-all',
                                        active
                                            ? 'opacity-100'
                                            : 'opacity-60 hover:opacity-100',
                                        step < s.n && 'cursor-not-allowed',
                                    )}
                                >
                                    <div className="flex items-center gap-3">
                                        <div
                                            className={cn(
                                                'flex h-10 w-10 items-center justify-center border-2 transition-all',
                                                active &&
                                                    'scale-110 border-primary bg-primary text-primary-foreground',
                                                done &&
                                                    'border-foreground bg-foreground text-background',
                                                !active &&
                                                    !done &&
                                                    'border-border',
                                            )}
                                        >
                                            {done ? (
                                                <Check className="h-5 w-5" />
                                            ) : (
                                                <StepIcon className="h-5 w-5" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-[10px] font-bold tracking-[0.1em] text-muted-foreground uppercase">
                                                Étape {s.n}
                                            </p>
                                            <p
                                                className={cn(
                                                    'text-sm font-medium',
                                                    active && 'text-foreground',
                                                )}
                                            >
                                                {s.label}
                                            </p>
                                        </div>
                                    </div>
                                </button>
                                {i < steps.length - 1 && (
                                    <div
                                        className={cn(
                                            'mx-4 h-[2px] flex-1 bg-border',
                                            done && 'bg-foreground',
                                        )}
                                    />
                                )}
                            </li>
                        );
                    })}
                </ol>
            </div>

            <main className="min-h-[400px] border border-border bg-card/50 p-6 md:p-10">
                {step === 1 && (
                    <section className="animate-in space-y-8 duration-500 fade-in slide-in-from-bottom-4">
                        <div className="grid gap-8 md:grid-cols-2">
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="name"
                                        className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Nom du produit
                                    </Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="Ex. Tajine émaillé de Safi"
                                        className="h-12 border-border/60 bg-background/50 focus-visible:ring-primary/20"
                                    />
                                    {(stepErrors.name || serverErrors.name) && (
                                        <p className="text-xs font-medium text-destructive">
                                            {stepErrors.name ||
                                                serverErrors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Catégorie
                                    </Label>
                                    <Select
                                        value={data.category_id}
                                        onValueChange={(value) =>
                                            setData('category_id', value)
                                        }
                                    >
                                        <SelectTrigger className="h-12 border-border/60 bg-background/50 focus:ring-primary/20">
                                            <SelectValue placeholder="Choisir une catégorie" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={String(c.id)}
                                                >
                                                    {c.translated_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {(stepErrors.category_id ||
                                        serverErrors.category_id) && (
                                        <p className="text-xs font-medium text-destructive">
                                            {stepErrors.category_id ||
                                                serverErrors.category_id}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label
                                    htmlFor="desc"
                                    className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Histoire & Description
                                </Label>
                                <Textarea
                                    id="desc"
                                    rows={8}
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Racontez l'histoire derrière cette pièce, les matériaux utilisés, les dimensions..."
                                    className="resize-none border-border/60 bg-background/50 focus-visible:ring-primary/20"
                                />
                                {(stepErrors.description ||
                                    serverErrors.description) && (
                                    <p className="text-xs font-medium text-destructive">
                                        {stepErrors.description ||
                                            serverErrors.description}
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {step === 2 && (
                    <section className="animate-in space-y-10 duration-500 fade-in slide-in-from-bottom-4">
                        <div>
                            <div className="mb-6 flex items-baseline justify-between">
                                <div>
                                    <h3 className="font-display text-2xl">
                                        Galerie d'images
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Téléversez jusqu'à 5 photos haute
                                        résolution. 5 Mo max par image
                                        (4096x4096px).
                                    </p>
                                </div>
                                <span className="text-xs font-medium text-muted-foreground tabular-nums">
                                    {images.length} / 5
                                </span>
                            </div>

                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
                                {images.map((img) => (
                                    <div
                                        key={img.id}
                                        className="group relative aspect-square overflow-hidden border border-border bg-muted"
                                    >
                                        <img
                                            src={img.url}
                                            alt={img.name}
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                        />
                                        <div className="absolute inset-0 bg-black/20 opacity-0 transition-opacity group-hover:opacity-100" />
                                        <button
                                            type="button"
                                            onClick={() => removeImage(img.id)}
                                            className="absolute end-2 top-2 flex h-8 w-8 items-center justify-center bg-white/90 text-destructive shadow-lg transition-transform hover:scale-110"
                                            aria-label="Supprimer"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}

                                {images.length < 5 && (
                                    <label className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-2 border-2 border-dashed border-border bg-background/50 text-muted-foreground transition-all hover:border-primary hover:bg-primary/5 hover:text-primary">
                                        <div className="flex h-10 w-10 items-center justify-center bg-muted transition-colors group-hover:bg-primary/10">
                                            <ImagePlus className="h-5 w-5" />
                                        </div>
                                        <span className="text-[10px] font-bold tracking-wider uppercase">
                                            Ajouter
                                        </span>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            multiple
                                            className="hidden"
                                            onChange={(e) =>
                                                handleImages(e.target.files)
                                            }
                                        />
                                    </label>
                                )}
                            </div>
                            {(stepErrors.images || serverErrors.media) && (
                                <p className="mt-4 text-xs font-medium text-destructive">
                                    {stepErrors.images || serverErrors.media}
                                </p>
                            )}
                        </div>

                        <div className="border-t border-border pt-8">
                            <div className="mb-6">
                                <h3 className="font-display text-2xl">
                                    Vidéo du produit
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Montrez votre travail en mouvement.
                                    Optionnel mais recommandé.
                                </p>
                            </div>

                            {video ? (
                                <div className="group relative max-w-2xl overflow-hidden border border-border bg-black shadow-xl">
                                    <video
                                        src={video.url}
                                        controls
                                        className="w-full"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setVideo(null);
                                            setData(
                                                'media',
                                                images.map((item) => item.file),
                                            );
                                        }}
                                        className="absolute end-4 top-4 flex h-10 w-10 items-center justify-center bg-white/90 text-destructive shadow-lg transition-transform hover:scale-110"
                                        aria-label="Supprimer la vidéo"
                                    >
                                        <X className="h-5 w-5" />
                                    </button>
                                </div>
                            ) : (
                                <label className="flex cursor-pointer flex-col items-center justify-center gap-4 border-2 border-dashed border-border bg-background/50 py-12 text-muted-foreground transition-all hover:border-primary hover:bg-primary/5 hover:text-primary">
                                    <div className="flex h-12 w-12 items-center justify-center bg-muted transition-colors group-hover:bg-primary/10">
                                        <Video className="h-6 w-6" />
                                    </div>
                                    <div className="text-center">
                                        <p className="text-sm font-medium text-foreground">
                                            Téléverser une vidéo
                                        </p>
                                        <p className="text-xs">
                                            MP4 ou MOV, max 50Mo
                                        </p>
                                    </div>
                                    <input
                                        type="file"
                                        accept="video/*"
                                        className="hidden"
                                        onChange={(e) =>
                                            handleVideo(e.target.files)
                                        }
                                    />
                                </label>
                            )}
                            {stepErrors.video && (
                                <p className="mt-4 text-xs font-medium text-destructive">
                                    {stepErrors.video}
                                </p>
                            )}
                        </div>
                    </section>
                )}

                {step === 3 && (
                    <section className="animate-in space-y-10 duration-500 fade-in slide-in-from-bottom-4">
                        <div className="grid gap-10 md:grid-cols-2">
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="base"
                                        className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Prix de base (MAD)
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="base"
                                            type="number"
                                            min="0"
                                            value={data.base_price}
                                            onChange={(e) =>
                                                setData(
                                                    'base_price',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="0.00"
                                            className="h-14 border-border/60 bg-background/50 pl-12 text-lg font-medium tabular-nums"
                                        />
                                        <span className="absolute top-1/2 left-4 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                                            DH
                                        </span>
                                    </div>
                                    {(stepErrors.basePrice ||
                                        serverErrors.base_price) && (
                                        <p className="text-xs font-medium text-destructive">
                                            {stepErrors.basePrice ||
                                                serverErrors.base_price}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label
                                        htmlFor="stock"
                                        className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Stock disponible
                                    </Label>
                                    <Input
                                        id="stock"
                                        type="number"
                                        min="0"
                                        value={data.stock}
                                        onChange={(e) =>
                                            setData(
                                                'stock',
                                                Number(e.target.value),
                                            )
                                        }
                                        placeholder="1"
                                        className="h-14 border-border/60 bg-background/50 text-lg font-medium tabular-nums"
                                    />
                                    {(stepErrors.stock ||
                                        serverErrors.stock) && (
                                        <p className="text-xs font-medium text-destructive">
                                            {stepErrors.stock ||
                                                serverErrors.stock}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground italic">
                                        Combien d'exemplaires sont prêts à être
                                        expédiés ?
                                    </p>
                                </div>
                            </div>

                            <div className="border border-border bg-muted/30 p-6">
                                <div className="mb-6 flex items-center justify-between">
                                    <div className="space-y-1">
                                        <h4 className="font-medium">
                                            Promotion
                                        </h4>
                                        <p className="text-xs text-muted-foreground">
                                            Appliquer une réduction immédiate.
                                        </p>
                                    </div>
                                    <Switch
                                        checked={saleEnabled}
                                        onCheckedChange={setSaleEnabled}
                                    />
                                </div>

                                {saleEnabled && (
                                    <div className="animate-in space-y-4 duration-300 fade-in slide-in-from-top-4">
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor="sale"
                                                className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                            >
                                                Prix soldé (MAD)
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="sale"
                                                    type="number"
                                                    min="0"
                                                    value={data.sale_price}
                                                    onChange={(e) =>
                                                        setData(
                                                            'sale_price',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="0.00"
                                                    className="h-14 border-border/60 bg-background/50 pl-12 text-lg font-medium tabular-nums"
                                                />
                                                <span className="absolute top-1/2 left-4 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                                                    DH
                                                </span>
                                            </div>
                                            {(stepErrors.salePrice ||
                                                serverErrors.sale_price) && (
                                                <p className="text-xs font-medium text-destructive">
                                                    {stepErrors.salePrice ||
                                                        serverErrors.sale_price}
                                                </p>
                                            )}
                                        </div>
                                        <div className="bg-primary/5 p-4 text-xs text-primary">
                                            {data.base_price &&
                                            data.sale_price &&
                                            Number(data.base_price) >
                                                Number(data.sale_price) ? (
                                                <p>
                                                    Réduction de{' '}
                                                    {Math.round(
                                                        (1 -
                                                            Number(
                                                                data.sale_price,
                                                            ) /
                                                                Number(
                                                                    data.base_price,
                                                                )) *
                                                            100,
                                                    )}
                                                    % appliquée.
                                                </p>
                                            ) : (
                                                <p>
                                                    Saisissez un prix inférieur
                                                    au prix de base.
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {step === 4 && (
                    <section className="animate-in space-y-8 duration-500 fade-in slide-in-from-bottom-4">
                        <div>
                            <h3 className="font-display text-2xl">Livraison</h3>
                            <p className="text-sm text-muted-foreground">
                                Toutes les commandes sont expédiées via Amana.
                                Vous n'avez plus besoin de choisir une option de
                                livraison.
                            </p>
                        </div>
                    </section>
                )}

                {step === 5 && (
                    <section className="animate-in space-y-10 duration-500 fade-in slide-in-from-bottom-4">
                        <div className="bg-primary/5 p-6 text-sm text-primary">
                            <p className="flex items-center gap-2 font-medium">
                                <Package className="h-4 w-4" />
                                Presque prêt !
                            </p>
                            <p className="mt-1 text-primary/80">
                                Votre produit sera soumis à une révision par
                                notre équipe artisanale avant d'être visible sur
                                la place de marché.
                            </p>
                        </div>

                        <div className="grid gap-12 md:grid-cols-2">
                            {/* Détails */}
                            <div className="space-y-6">
                                <div className="flex items-center justify-between border-b border-border pb-2">
                                    <h3 className="font-display text-xl">
                                        Détails de la pièce
                                    </h3>
                                    <button
                                        type="button"
                                        onClick={() => setStep(1)}
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        Modifier
                                    </button>
                                </div>
                                <dl className="space-y-4 text-sm">
                                    <div className="flex flex-col gap-1">
                                        <dt className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Nom
                                        </dt>
                                        <dd className="text-lg font-medium">
                                            {data.name || '—'}
                                        </dd>
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <dt className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Catégorie
                                        </dt>
                                        <dd className="font-medium">
                                            {categories.find(
                                                (c) =>
                                                    String(c.id) ===
                                                    data.category_id,
                                            )?.translated_name || '—'}
                                        </dd>
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <dt className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Histoire
                                        </dt>
                                        <dd className="leading-relaxed whitespace-pre-line text-muted-foreground">
                                            {data.description || '—'}
                                        </dd>
                                    </div>
                                </dl>

                                <div className="mt-8">
                                    <div className="mb-4 flex items-center justify-between border-b border-border pb-2">
                                        <h3 className="font-display text-xl">
                                            Livraison
                                        </h3>
                                        <button
                                            type="button"
                                            onClick={() => setStep(4)}
                                            className="text-xs font-medium text-primary hover:underline"
                                        >
                                            Modifier
                                        </button>
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Livraison via Amana. Les frais de
                                        livraison seront calculés
                                        automatiquement lors du passage à la
                                        caisse.
                                    </div>
                                </div>
                            </div>

                            {/* Prix & Stock */}
                            <div className="space-y-6">
                                <div className="flex items-center justify-between border-b border-border pb-2">
                                    <h3 className="font-display text-xl">
                                        Commercialisation
                                    </h3>
                                    <button
                                        type="button"
                                        onClick={() => setStep(3)}
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        Modifier
                                    </button>
                                </div>
                                <dl className="grid grid-cols-2 gap-6 text-sm">
                                    <div className="flex flex-col gap-1">
                                        <dt className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Prix final
                                        </dt>
                                        <dd className="font-display text-2xl tabular-nums">
                                            {saleEnabled
                                                ? data.sale_price
                                                : data.base_price}{' '}
                                            MAD
                                            {saleEnabled && (
                                                <span className="ml-2 text-sm text-muted-foreground line-through decoration-destructive/50">
                                                    {data.base_price} MAD
                                                </span>
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <dt className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Stock
                                        </dt>
                                        <dd className="font-display text-2xl tabular-nums">
                                            {data.stock} unités
                                        </dd>
                                    </div>
                                </dl>

                                <div className="mt-8">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h3 className="text-sm font-medium">
                                            Médias téléversés
                                        </h3>
                                        <button
                                            type="button"
                                            onClick={() => setStep(2)}
                                            className="text-xs font-medium text-primary hover:underline"
                                        >
                                            Modifier
                                        </button>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {images.map((img) => (
                                            <div
                                                key={img.id}
                                                className="h-16 w-16 overflow-hidden border border-border"
                                            >
                                                <img
                                                    src={img.url}
                                                    alt={img.name}
                                                    className="h-full w-full object-cover"
                                                />
                                            </div>
                                        ))}
                                        {video && (
                                            <div className="flex h-16 w-16 items-center justify-center border border-border bg-black">
                                                <Video className="h-4 w-4 text-white" />
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                )}
            </main>

            {/* Nav */}
            <div className="mt-12 flex items-center justify-between gap-4">
                <Button
                    type="button"
                    variant="outline"
                    onClick={back}
                    disabled={step === 1}
                    className="h-12 gap-2 px-6"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Précédent
                </Button>

                <div className="flex items-center gap-3">
                    {step < 5 ? (
                        <Button
                            type="button"
                            onClick={next}
                            className="h-12 gap-2 bg-foreground px-8 text-background hover:bg-foreground/90"
                        >
                            Étape suivante
                            <ArrowRight className="h-4 w-4" />
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            onClick={publish}
                            disabled={processing}
                            className="h-12 gap-2 bg-primary px-10 text-primary-foreground shadow-lg shadow-primary/20 hover:bg-primary/90"
                        >
                            {processing ? (
                                <>
                                    <Save className="h-4 w-4 animate-pulse" />
                                    Publication en cours...
                                </>
                            ) : (
                                <>
                                    <Save className="h-4 w-4" />
                                    Soumettre pour révision
                                </>
                            )}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

SellerProductNew.layout = (page: React.ReactNode) => (
    <SellerLayout children={page} />
);

export default SellerProductNew;

// import SellerLayout from '@/layouts/seller/layout';
// import { useForm } from '@inertiajs/react';

// const Create = () => {
//     const { data, setData, post, processing, errors } = useForm({
//         name: 'Product test a bb',
//         description: 'Hello description',
//         base_price: 10,
//         sale_price: 9,
//         stock: 2,
//         category_id: 2,
//         status: 'published',
//         media: [] as File[],
//     });
//     const handleSubmit = () => {
//         post(route('seller.products.store'));
//     };
//     console.log('erroras: ', errors);
//     return (
//         <div>
//             Create
//             <input
//                 type="file"
//                 multiple
//                 onChange={(e) => {
//                     if (e.target.files) {
//                         // Convert FileList to an Array for the state
//                         setData('media', Array.from(e.target.files));
//                     }
//                 }}
//             />
//             {errors.media && <div className="text-red-500">{errors.media}</div>}
//             <button
//                 onClick={handleSubmit}
//                 className="cursor-pointer rounded-2xl bg-blue-500"
//             >
//                 Handle Submit
//             </button>
//         </div>
//     );
// };

// Create.layout = (page: React.ReactNode) => <SellerLayout children={page} />;

// export default Create;
