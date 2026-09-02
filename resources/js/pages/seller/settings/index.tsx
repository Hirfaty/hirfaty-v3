import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import SellerLayout from '@/layouts/seller/layout';
import type { Category, City, Store } from '@/types';
import type { User } from '@/types/auth';

interface SellerSettingsProps {
    user: User & { store: Store };
    categories: Category[];
    cities: City[];
}

export default function SellerSettings({
    user,
    categories,
    cities,
}: SellerSettingsProps) {
    const profileForm = useForm({
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        phone_number: user.phone_number ?? '',
        bio: user.bio ?? '',
    });

    const storeForm = useForm({
        store_name: user.store?.store_name ?? '',
        category_id: String(user.store?.category_id ?? categories[0]?.id ?? ''),
        city_id: String(user.store?.city_id ?? cities[0]?.id ?? ''),
        description: user.store?.description ?? '',
        logo: null as File | null,
        banner: null as File | null,
    });

    const localeForm = useForm({
        locale: user.locale ?? 'fr',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updateProfile = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        profileForm.patch(route('seller.settings.profile.update'));
    };

    const updateStore = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        storeForm.patch(route('seller.settings.store.update'), {
            forceFormData: true,
        });
    };

    const updateLocale = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        localeForm.patch(route('seller.settings.locale.update'));
    };

    const updatePassword = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        passwordForm.patch(route('seller.settings.password.update'));
    };

    return (
        <>
            <Head title="Seller settings" />

            <div className="mx-auto max-w-[1080px] px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8 space-y-3">
                    <p className="text-xs font-semibold tracking-[0.3em] text-muted-foreground uppercase">
                        Paramètres
                    </p>
                    <h1 className="font-display text-3xl tracking-tight text-foreground sm:text-4xl">
                        Gérez les informations de votre boutique
                    </h1>
                    <p className="max-w-2xl text-sm leading-7 text-muted-foreground">
                        Modifiez vos informations personnelles, les données de
                        votre boutique, la langue ou le mot de passe.
                    </p>
                </div>

                <div className="grid gap-8 xl:grid-cols-[1fr_320px]">
                    <div className="space-y-8">
                        <section className="border border-border bg-card/50 p-6">
                            <div className="mb-6 flex items-center justify-between gap-4">
                                <div>
                                    <h2 className="text-xl font-medium">
                                        Profil vendeur
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Informations publiques et coordonnées.
                                    </p>
                                </div>
                            </div>

                            <form
                                onSubmit={updateProfile}
                                className="space-y-6"
                            >
                                <div className="grid gap-6 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="first_name">
                                            Prénom
                                        </Label>
                                        <Input
                                            id="first_name"
                                            value={profileForm.data.first_name}
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'first_name',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-12 border-border/60 bg-background/50"
                                        />
                                        <p className="text-xs text-destructive">
                                            {profileForm.errors.first_name}
                                        </p>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="last_name">Nom</Label>
                                        <Input
                                            id="last_name"
                                            value={profileForm.data.last_name}
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'last_name',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-12 border-border/60 bg-background/50"
                                        />
                                        <p className="text-xs text-destructive">
                                            {profileForm.errors.last_name}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Téléphone</Label>
                                    <Input
                                        id="phone"
                                        value={profileForm.data.phone_number}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                'phone_number',
                                                event.target.value,
                                            )
                                        }
                                        className="h-12 border-border/60 bg-background/50"
                                    />
                                    <p className="text-xs text-destructive">
                                        {profileForm.errors.phone_number}
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="bio">Bio</Label>
                                    <Textarea
                                        id="bio"
                                        rows={4}
                                        value={profileForm?.data?.bio || ''}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                'bio',
                                                event.target.value,
                                            )
                                        }
                                        className="border-border/60 bg-background/50"
                                    />
                                    <p className="text-xs text-destructive">
                                        {profileForm.errors.bio}
                                    </p>
                                </div>

                                <div className="flex justify-end">
                                    <Button disabled={profileForm.processing}>
                                        Enregistrer le profil
                                    </Button>
                                </div>
                            </form>
                        </section>

                        <section className="border border-border bg-card/50 p-6">
                            <div className="mb-6 flex items-center justify-between gap-4">
                                <div>
                                    <h2 className="text-xl font-medium">
                                        Informations boutique
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Mettre à jour le nom de la boutique, la
                                        ville, la catégorie et la description.
                                    </p>
                                </div>
                            </div>

                            <form onSubmit={updateStore} className="space-y-6">
                                <div className="grid gap-6 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="store_name">
                                            Nom de la boutique
                                        </Label>
                                        <Input
                                            id="store_name"
                                            value={storeForm.data.store_name}
                                            onChange={(event) =>
                                                storeForm.setData(
                                                    'store_name',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-12 border-border/60 bg-background/50"
                                        />
                                        <p className="text-xs text-destructive">
                                            {storeForm.errors.store_name}
                                        </p>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="category_id">
                                            Catégorie
                                        </Label>
                                        <select
                                            id="category_id"
                                            value={storeForm.data.category_id}
                                            onChange={(event) =>
                                                storeForm.setData(
                                                    'category_id',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-12 w-full border border-border/70 bg-background/50 px-3 text-sm"
                                        >
                                            {categories.map((category) => (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.translated_name}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-destructive">
                                            {storeForm.errors.category_id}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="city_id">Ville</Label>
                                        <select
                                            id="city_id"
                                            value={storeForm.data.city_id}
                                            onChange={(event) =>
                                                storeForm.setData(
                                                    'city_id',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-12 w-full border border-border/70 bg-background/50 px-3 text-sm"
                                        >
                                            {cities.map((city) => (
                                                <option
                                                    key={city.id}
                                                    value={city.id}
                                                >
                                                    {city.name}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-destructive">
                                            {storeForm.errors.city_id}
                                        </p>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="description"
                                            rows={8}
                                            value={storeForm.data.description}
                                            onChange={(event) =>
                                                storeForm.setData(
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                            className="border-border/60 bg-background/50"
                                        />
                                        <p className="text-xs text-destructive">
                                            {storeForm.errors.description}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <Button disabled={storeForm.processing}>
                                        Enregistrer la boutique
                                    </Button>
                                </div>
                            </form>
                        </section>

                        <section className="border border-border bg-card/50 p-6">
                            <div className="mb-6">
                                <h2 className="text-xl font-medium">
                                    Changer le mot de passe
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Mettez à jour le mot de passe de votre
                                    compte.
                                </p>
                            </div>

                            <form
                                onSubmit={updatePassword}
                                className="space-y-6"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        Mot de passe actuel
                                    </Label>
                                    <Input
                                        id="current_password"
                                        type="password"
                                        value={
                                            passwordForm.data.current_password
                                        }
                                        onChange={(event) =>
                                            passwordForm.setData(
                                                'current_password',
                                                event.target.value,
                                            )
                                        }
                                        className="h-12 border-border/60 bg-background/50"
                                    />
                                    <p className="text-xs text-destructive">
                                        {passwordForm.errors.current_password}
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Nouveau mot de passe
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={passwordForm.data.password}
                                        onChange={(event) =>
                                            passwordForm.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        className="h-12 border-border/60 bg-background/50"
                                    />
                                    <p className="text-xs text-destructive">
                                        {passwordForm.errors.password}
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirmer le mot de passe
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={
                                            passwordForm.data
                                                .password_confirmation
                                        }
                                        onChange={(event) =>
                                            passwordForm.setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        className="h-12 border-border/60 bg-background/50"
                                    />
                                </div>

                                <div className="flex justify-end">
                                    <Button disabled={passwordForm.processing}>
                                        Mettre à jour le mot de passe
                                    </Button>
                                </div>
                            </form>
                        </section>
                    </div>

                    <aside className="space-y-6">
                        <section className="border border-border bg-card/50 p-6">
                            <h2 className="text-xl font-medium">Préférences</h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Choisissez la langue de votre espace vendeur.
                            </p>

                            <form
                                onSubmit={updateLocale}
                                className="mt-6 space-y-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="locale">Langue</Label>
                                    <select
                                        id="locale"
                                        value={localeForm.data.locale}
                                        onChange={(event) =>
                                            localeForm.setData(
                                                'locale',
                                                event.target.value,
                                            )
                                        }
                                        className="h-12 w-full border border-border/70 bg-background/50 px-3 text-sm"
                                    >
                                        <option value="fr">Français</option>
                                        <option value="en">English</option>
                                        <option value="ar">العربية</option>
                                    </select>
                                </div>
                                <Button disabled={localeForm.processing}>
                                    Enregistrer la langue
                                </Button>
                            </form>
                        </section>

                        <section className="border border-border bg-muted/20 p-6 text-sm leading-7 text-muted-foreground">
                            <p className="font-medium text-foreground">
                                Conseil
                            </p>
                            <p>
                                Assurez-vous que vos informations de boutique
                                sont à jour pour améliorer votre visibilité et
                                l’expérience client.
                            </p>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

SellerSettings.layout = (page: React.ReactNode) => (
    <SellerLayout>{page}</SellerLayout>
);
