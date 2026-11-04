import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ImagePlus, Info, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { frenchName } from '@/lib/category';
import type { Category, City, Store, StoreType, User, UserRole } from '@/types';

const INPUT =
    'w-full rounded-lg border border-border bg-card px-3.5 py-2.5 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/70 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink/10';
const LABEL = 'mb-1.5 block text-sm font-medium text-ink';
const ERROR = 'mt-1.5 text-xs text-red-600';

const ROLES: { value: UserRole; label: string; hint: string }[] = [
    { value: 'buyer', label: 'Acheteur', hint: 'Achète à l’unité.' },
    {
        value: 'professional_buyer',
        label: 'Acheteur pro',
        hint: 'Tarifs de gros une fois vérifié.',
    },
    { value: 'seller', label: 'Vendeur', hint: 'Tient une boutique.' },
];

const STORE_TYPES: { value: StoreType; label: string }[] = [
    { value: 'artist', label: 'Artiste' },
    { value: 'company', label: 'Entreprise' },
    { value: 'cooperative', label: 'Coopérative' },
];

function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className={LABEL}>{label}</label>
            {children}
            {hint && !error && (
                <p className="mt-1.5 text-xs text-ink-soft">{hint}</p>
            )}
            {error && <p className={ERROR}>{error}</p>}
        </div>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h2 className="text-base font-semibold text-ink">{title}</h2>
            {description && (
                <p className="mt-1 mb-5 text-sm text-ink-soft">{description}</p>
            )}
            <div className={description ? '' : 'mt-5'}>{children}</div>
        </section>
    );
}

/**
 * Shop image slot: shows whatever is already saved until a new file is
 * picked, then previews that instead. Leaving it untouched keeps the existing
 * image — the server only overwrites when a file actually arrives.
 */
function ImageSlot({
    label,
    hint,
    current,
    file,
    onPick,
    onClear,
    error,
    aspect,
}: {
    label: string;
    hint: string;
    current: string | null;
    file: File | null;
    onPick: (file: File | null) => void;
    onClear: () => void;
    error?: string;
    aspect: string;
}) {
    // Derived from the file, revoked on change/unmount — never state-synced.
    const preview = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );
    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const shown = preview ?? current;

    return (
        <Field label={label} hint={hint} error={error}>
            <div className="flex items-center gap-4">
                <div
                    className={`${aspect} shrink-0 overflow-hidden rounded-lg border border-border bg-muted/40`}
                >
                    {shown ? (
                        <img
                            src={shown}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center text-ink-soft">
                            <ImagePlus className="h-4 w-4" />
                        </div>
                    )}
                </div>
                <div className="flex flex-col gap-2">
                    <input
                        type="file"
                        accept="image/*"
                        onChange={(e) => onPick(e.target.files?.[0] ?? null)}
                        className="text-xs text-ink-soft file:mr-3 file:rounded-lg file:border file:border-border file:bg-card file:px-3 file:py-1.5 file:text-xs file:text-ink"
                    />
                    {file && (
                        <button
                            type="button"
                            onClick={onClear}
                            className="inline-flex w-fit items-center gap-1.5 text-xs text-ink-soft transition hover:text-red-600"
                        >
                            <Trash2 className="h-3 w-3" /> Annuler ce choix
                        </button>
                    )}
                </div>
            </div>
        </Field>
    );
}

type EditableUser = User & {
    phone_number: string | null;
    bio: string | null;
    store?: (Store & { city_id?: number; category_id?: number }) | null;
};

export default function EditUser({
    user,
    cities,
    categories,
    canLeaveSellerRole,
}: {
    user: EditableUser;
    cities: City[];
    categories: Category[];
    canLeaveSellerRole: boolean;
}) {
    const { t } = useTranslation();
    const [data, setData] = useState({
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        email: user.email ?? '',
        phone_number: user.phone_number ?? '',
        role: user.role as UserRole,
        store_name: user.store?.store_name ?? '',
        store_type: user.store?.type ?? 'artist',
        category_id: user.store?.category_id ?? categories[0]?.id ?? '',
        city_id: user.store?.city_id ?? cities[0]?.id ?? '',
        // One field, two destinations — the shop's blurb for a seller, the
        // person's bio for everyone else, exactly as on the create screen.
        description: (user.store?.description ?? user.bio ?? '') as string,
    });
    const [logo, setLogo] = useState<File | null>(null);
    const [banner, setBanner] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const set = <K extends keyof typeof data>(
        key: K,
        value: (typeof data)[K],
    ) => setData((d) => ({ ...d, [key]: value }));

    const isSeller = data.role === 'seller';
    const wasSeller = user.role === 'seller';
    const roleLocked = wasSeller && !canLeaveSellerRole;

    const submit = () => {
        setProcessing(true);
        setErrors({});

        router.post(
            route('admin.users.update', user.id),
            {
                ...data,
                // Only send files that were actually picked; the server keeps
                // the saved image when the key is absent.
                ...(logo ? { logo } : {}),
                ...(banner ? { banner } : {}),
            },
            {
                forceFormData: true,
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="min-h-screen px-6 py-10">
            <Head title={t('titles.admin.user_edit')} />
            <div className="mx-auto max-w-3xl">
                <Link
                    href={route('admin.users.show', user.id)}
                    className="mb-6 inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour
                </Link>

                <header className="mb-8">
                    <h1 className="text-3xl font-semibold tracking-tight text-ink">
                        Modifier {user.first_name} {user.last_name}
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        Le mot de passe ne se modifie pas ici — utilisez « Lien
                        de réinitialisation » depuis la fiche.
                    </p>
                </header>

                <div className="space-y-6">
                    <Section title="Identité">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Prénom" error={errors.first_name}>
                                <input
                                    value={data.first_name}
                                    onChange={(e) =>
                                        set('first_name', e.target.value)
                                    }
                                    className={INPUT}
                                />
                            </Field>
                            <Field label="Nom" error={errors.last_name}>
                                <input
                                    value={data.last_name}
                                    onChange={(e) =>
                                        set('last_name', e.target.value)
                                    }
                                    className={INPUT}
                                />
                            </Field>
                            {/* Optional, like phone: a user created without
                                contact details gets them filled in here. The
                                account simply can't log in until it has an
                                address, which is what the warning says. */}
                            <Field
                                label="Email"
                                error={errors.email}
                                hint={
                                    data.email.trim() === ''
                                        ? 'Sans email, ce compte ne peut pas se connecter.'
                                        : 'Optionnel.'
                                }
                            >
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        set('email', e.target.value)
                                    }
                                    className={INPUT}
                                />
                            </Field>
                            <Field
                                label="Téléphone"
                                error={errors.phone_number}
                                hint="Optionnel."
                            >
                                <input
                                    value={data.phone_number}
                                    onChange={(e) =>
                                        set('phone_number', e.target.value)
                                    }
                                    className={INPUT}
                                />
                            </Field>
                        </div>
                    </Section>

                    <Section
                        title="Rôle"
                        description="Détermine la grille tarifaire et l’espace auquel le compte accède."
                    >
                        {roleLocked && (
                            <div className="mb-4 flex items-start gap-3 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-ink">
                                <Info className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                <span>
                                    Ce vendeur a une boutique active. Changer
                                    son rôle lui couperait l’accès aux commandes
                                    qu’il doit encore honorer — bloquez le
                                    compte à la place.
                                </span>
                            </div>
                        )}

                        <div className="grid gap-3 sm:grid-cols-3">
                            {ROLES.map((r) => {
                                const active = data.role === r.value;
                                // A seller with a live shop can only stay one.
                                const disabled =
                                    roleLocked && r.value !== 'seller';

                                return (
                                    <button
                                        key={r.value}
                                        type="button"
                                        disabled={disabled}
                                        onClick={() => set('role', r.value)}
                                        className={`rounded-lg border p-4 text-left transition ${
                                            active
                                                ? 'border-ink bg-ink/[0.03] ring-1 ring-ink/10'
                                                : 'border-border hover:border-ink/40'
                                        } ${disabled ? 'cursor-not-allowed opacity-40' : ''}`}
                                    >
                                        <span className="block text-sm font-medium text-ink">
                                            {r.label}
                                        </span>
                                        <span className="mt-1 block text-xs text-ink-soft">
                                            {r.hint}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        {errors.role && <p className={ERROR}>{errors.role}</p>}
                    </Section>

                    {isSeller && (
                        <Section
                            title="Boutique"
                            description={
                                wasSeller
                                    ? 'Les informations publiques de l’atelier.'
                                    : 'Ce compte devient vendeur : une boutique sera créée avec ces informations.'
                            }
                        >
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <Field
                                        label="Nom de la boutique"
                                        error={errors.store_name}
                                    >
                                        <input
                                            value={data.store_name}
                                            onChange={(e) =>
                                                set(
                                                    'store_name',
                                                    e.target.value,
                                                )
                                            }
                                            className={INPUT}
                                        />
                                    </Field>
                                </div>

                                <Field label="Type" error={errors.store_type}>
                                    <select
                                        value={data.store_type}
                                        onChange={(e) =>
                                            set(
                                                'store_type',
                                                e.target.value as StoreType,
                                            )
                                        }
                                        className={INPUT}
                                    >
                                        {STORE_TYPES.map((t) => (
                                            <option
                                                key={t.value}
                                                value={t.value}
                                            >
                                                {t.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <Field label="Ville" error={errors.city_id}>
                                    <select
                                        value={data.city_id}
                                        onChange={(e) =>
                                            set(
                                                'city_id',
                                                Number(e.target.value),
                                            )
                                        }
                                        className={INPUT}
                                    >
                                        {cities.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <div className="sm:col-span-2">
                                    <Field
                                        label="Catégorie"
                                        error={errors.category_id}
                                    >
                                        <select
                                            value={data.category_id}
                                            onChange={(e) =>
                                                set(
                                                    'category_id',
                                                    Number(e.target.value),
                                                )
                                            }
                                            className={INPUT}
                                        >
                                            {categories.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {frenchName(c)}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                </div>

                                <ImageSlot
                                    label="Logo"
                                    hint="Laissez vide pour conserver l’actuel."
                                    current={user.store?.logo ?? null}
                                    file={logo}
                                    onPick={setLogo}
                                    onClear={() => setLogo(null)}
                                    error={errors.logo}
                                    aspect="h-16 w-16"
                                />
                                <ImageSlot
                                    label="Bannière"
                                    hint="Laissez vide pour conserver l’actuelle."
                                    current={user.store?.banner ?? null}
                                    file={banner}
                                    onPick={setBanner}
                                    onClear={() => setBanner(null)}
                                    error={errors.banner}
                                    aspect="h-16 w-28"
                                />
                            </div>
                        </Section>
                    )}

                    <Section
                        title={
                            isSeller ? 'Description de la boutique' : 'Notes'
                        }
                        description={
                            isSeller
                                ? 'Présentée aux acheteurs sur les pages produit.'
                                : 'Visible uniquement par l’équipe Hirfaty.'
                        }
                    >
                        <Field label="Description" error={errors.description}>
                            <textarea
                                rows={8}
                                value={data.description}
                                onChange={(e) =>
                                    set('description', e.target.value)
                                }
                                className={INPUT}
                            />
                        </Field>
                    </Section>
                </div>

                <div className="sticky bottom-0 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card/95 px-5 py-4 shadow-sm backdrop-blur">
                    <p className="text-xs text-ink-soft">
                        Les modifications sont appliquées immédiatement.
                    </p>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.users.show', user.id)}
                            className="rounded-lg border border-border px-4 py-2.5 text-sm text-ink transition hover:bg-muted"
                        >
                            Annuler
                        </Link>
                        <button
                            type="button"
                            onClick={submit}
                            disabled={processing}
                            className="rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-ink/90 disabled:opacity-50"
                        >
                            {processing ? 'Enregistrement…' : 'Enregistrer'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

EditUser.layout = (page: ReactNode) => <AdminLayout children={page} />;
