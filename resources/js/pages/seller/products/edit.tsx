import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Save,
    Trash2,
    Eye,
    Package,
    Tag,
    History,
    AlertCircle,
    Check,
} from 'lucide-react';
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
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
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
import { cn } from '@/lib/utils';
import type { Category, Product } from '@/types';

const statuses = [
    { value: 'published', label: 'Publié' },
    { value: 'draft', label: 'Brouillon' },
] as const;

function SellerProductEdit({
    product,
    categories,
}: {
    product: Product;
    categories: Category[];
}) {
    const [saleEnabled, setSaleEnabled] = useState(Boolean(product.sale_price));

    const {
        data,
        setData,
        post,
        delete: destroy,
        processing,
        errors,
    } = useForm({
        _method: 'PUT',
        name: product.name,
        description: product.description ?? '',
        base_price: String(product.base_price),
        sale_price: product.sale_price ?? '',
        stock: product.stock,
        category_id: String(product.category_id ?? ''),
        media: [] as File[],
        status:
            product.status === 'published' || product.status === 'draft'
                ? product.status
                : 'draft',
        deleted_media: [] as number[],
    });

    const save = () => {
        if (!data.name.trim() || !data.category_id || !data.base_price) {
            toast.error('Champs manquants', {
                description: 'Nom, catégorie et prix sont requis.',
            });

            return;
        }

        if (saleEnabled && Number(data.sale_price) >= Number(data.base_price)) {
            toast.error('Prix soldé invalide', {
                description: 'Doit être inférieur au prix de base.',
            });

            return;
        }

        post(route('seller.products.update', product.id), {
            forceFormData: true,
            onSuccess: () => {
                toast.success('Modifications enregistrées', {
                    description: `« ${data.name} » a été mis à jour.`,
                });
            },
        });
    };

    const remove = () => {
        destroy(route('seller.products.destroy', product.id), {
            onSuccess: () => {
                toast.success('Produit supprimé', {
                    description: `« ${product.name} » retiré du catalogue.`,
                });
            },
        });
    };

    return (
        <div className="mx-auto max-w-[1200px] px-4 py-10 sm:px-6 lg:px-8">
            <div className="mb-8 flex items-center justify-between">
                <Link
                    href={route('seller.products.index')}
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Retour au catalogue
                </Link>
                <div className="flex items-center gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="gap-2 text-muted-foreground"
                        onClick={() =>
                            router.visit(
                                route('seller.products.show', product.id),
                            )
                        }
                    >
                        <Eye className="h-4 w-4" />
                        Aperçu
                    </Button>
                </div>
            </div>

            <header className="mb-12">
                <div className="flex items-center gap-2 text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase">
                    <span className="inline-block h-px w-8 bg-border" />
                    Édition du produit
                </div>
                <h1 className="mt-4 font-display text-4xl leading-tight md:text-5xl lg:text-6xl">
                    Peaufiner <br /> {product.name}
                </h1>
                {product.status === 'rejected' && (
                    <div className="mt-6 flex items-start gap-3 rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <p className="font-bold">
                                Ce produit a été rejeté lors de la révision.
                            </p>
                            <p className="mt-1 opacity-90">
                                {product.rejection_reason ||
                                    'Veuillez corriger les informations et soumettre à nouveau.'}
                            </p>
                        </div>
                    </div>
                )}
            </header>

            <div className="grid gap-10 lg:grid-cols-[1fr_380px]">
                {/* Main Form Area */}
                <div className="space-y-10">
                    {/* Section: Détails */}
                    <section className="rounded-2xl border border-border bg-card/50 p-6 md:p-8">
                        <div className="mb-8 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                                <History className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <h2 className="font-display text-2xl">
                                Détails essentiels
                            </h2>
                        </div>

                        <div className="space-y-6">
                            <div className="space-y-2">
                                <Label
                                    htmlFor="name"
                                    className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Nom de la pièce
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    className="h-12 rounded-lg border-border/60 bg-background/50 focus-visible:ring-primary/20"
                                />
                                {errors.name && (
                                    <p className="text-xs font-medium text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label
                                    htmlFor="desc"
                                    className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Description & Histoire
                                </Label>
                                <Textarea
                                    id="desc"
                                    rows={8}
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    className="resize-none rounded-lg border-border/60 bg-background/50 focus-visible:ring-primary/20"
                                />
                                {errors.description && (
                                    <p className="text-xs font-medium text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-6 md:grid-cols-2">
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
                                        <SelectTrigger className="h-12 rounded-lg border-border/60 bg-background/50">
                                            <SelectValue placeholder="Choisir" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={String(c.id)}
                                                >
                                                    {c.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.category_id && (
                                        <p className="text-xs font-medium text-destructive">
                                            {errors.category_id}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Section: Prix & Stock */}
                    <section className="rounded-2xl border border-border bg-card/50 p-6 md:p-8">
                        <div className="mb-8 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                                <Tag className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <h2 className="font-display text-2xl">
                                Tarification & Inventaire
                            </h2>
                        </div>

                        <div className="grid gap-10 md:grid-cols-2">
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <Label className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Prix de base (MAD)
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            type="number"
                                            min="0"
                                            value={data.base_price}
                                            onChange={(e) =>
                                                setData(
                                                    'base_price',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-14 rounded-lg border-border/60 bg-background/50 pl-12 text-lg font-medium tabular-nums"
                                        />
                                        <span className="absolute top-1/2 left-4 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                                            DH
                                        </span>
                                    </div>
                                    {errors.base_price && (
                                        <p className="text-xs font-medium text-destructive">
                                            {errors.base_price}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Stock disponible
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={data.stock}
                                        onChange={(e) =>
                                            setData(
                                                'stock',
                                                Number(e.target.value),
                                            )
                                        }
                                        className="h-14 rounded-lg border-border/60 bg-background/50 text-lg font-medium tabular-nums"
                                    />
                                    {errors.stock && (
                                        <p className="text-xs font-medium text-destructive">
                                            {errors.stock}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="rounded-xl bg-muted/30 p-6">
                                <div className="mb-6 flex items-center justify-between">
                                    <div className="space-y-1">
                                        <h4 className="text-sm font-medium">
                                            Prix promotionnel
                                        </h4>
                                        <p className="text-xs text-muted-foreground">
                                            Activer une remise sur cet article.
                                        </p>
                                    </div>
                                    <Switch
                                        checked={saleEnabled}
                                        onCheckedChange={setSaleEnabled}
                                    />
                                </div>

                                {saleEnabled && (
                                    <div className="animate-in space-y-4 fade-in slide-in-from-top-2">
                                        <div className="space-y-2">
                                            <Label className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                                Prix soldé (MAD)
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={data.sale_price}
                                                    onChange={(e) =>
                                                        setData(
                                                            'sale_price',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-14 rounded-lg border-border/60 bg-background/50 pl-12 text-lg font-medium tabular-nums"
                                                />
                                                <span className="absolute top-1/2 left-4 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                                                    DH
                                                </span>
                                            </div>
                                            {errors.sale_price && (
                                                <p className="text-xs font-medium text-destructive">
                                                    {errors.sale_price}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                </div>

                {/* Side Actions Panel */}
                <aside className="space-y-6">
                    <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:sticky md:top-8">
                        <div className="mb-6 flex items-center gap-2">
                            <div className="h-2 w-2 rounded-full bg-primary" />
                            <h3 className="text-xs font-bold tracking-[0.1em] text-muted-foreground uppercase">
                                Actions
                            </h3>
                        </div>

                        <div className="space-y-3">
                            <Button
                                onClick={save}
                                disabled={processing}
                                className="h-12 w-full gap-2 rounded-xl bg-primary text-primary-foreground shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <Save className="h-4 w-4" />
                                {processing
                                    ? 'Enregistrement...'
                                    : 'Enregistrer'}
                            </Button>

                            <Button
                                variant="outline"
                                className="h-12 w-full gap-2 rounded-xl"
                                onClick={() =>
                                    router.visit(
                                        route(
                                            'seller.products.show',
                                            product.id,
                                        ),
                                    )
                                }
                            >
                                <Eye className="h-4 w-4" />
                                Voir la fiche
                            </Button>

                            <div className="!my-6 h-px w-full bg-border" />

                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        className="h-12 w-full gap-2 rounded-xl text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Retirer du catalogue
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent className="rounded-2xl border-border">
                                    <AlertDialogHeader>
                                        <AlertDialogTitle className="font-display text-2xl">
                                            Confirmer la suppression ?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription className="text-base">
                                            Cette action est irréversible. «{' '}
                                            {product.name} » sera définitivement
                                            supprimé de votre boutique et du
                                            marché.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter className="mt-6">
                                        <AlertDialogCancel className="h-11 rounded-xl">
                                            Annuler
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={remove}
                                            className="h-11 rounded-xl bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        >
                                            Oui, supprimer
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>

                        <div className="mt-8 rounded-xl bg-muted/30 p-4 text-[11px] leading-relaxed text-muted-foreground italic">
                            Dernière mise à jour le{' '}
                            {new Date(product.updated_at).toLocaleDateString(
                                'fr-FR',
                                {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                },
                            )}
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    );
}

SellerProductEdit.layout = (page: React.ReactNode) => (
    <SellerLayout children={page} />
);

export default SellerProductEdit;
