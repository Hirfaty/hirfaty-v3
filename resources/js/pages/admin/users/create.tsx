import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Check,
    Loader2,
    Palette,
    ShoppingBag,
    Store as StoreIcon,
    User as UserIcon,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import SingleImageInput from '@/components/admin/single-image-input';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import type { Category, City, PageProps, StoreType } from '@/types';

type Role = 'seller' | 'buyer' | 'professional_buyer';

/** Steps differ per role: only sellers get the shop step. */
type StepId = 'role' | 'identity' | 'shop' | 'review';

const ROLES: {
    value: Role;
    label: string;
    description: string;
    icon: typeof UserIcon;
}[] = [
    {
        value: 'seller',
        label: 'Vendeur',
        description:
            'Un artisan qui vend sur la plateforme. Une boutique lui est créée.',
        icon: StoreIcon,
    },
    {
        value: 'buyer',
        label: 'Acheteur',
        description: 'Un client particulier qui achète à l’unité.',
        icon: UserIcon,
    },
    {
        value: 'professional_buyer',
        label: 'Acheteur professionnel',
        description:
            'Achète en gros et voit les tarifs professionnels réservés.',
        icon: ShoppingBag,
    },
];

const STORE_TYPES: {
    value: StoreType;
    label: string;
    icon: typeof UserIcon;
}[] = [
    { value: 'artist', label: 'Artiste', icon: Palette },
    { value: 'company', label: 'Entreprise', icon: Building2 },
    { value: 'cooperative', label: 'Coopérative', icon: Users },
];

const INPUT =
    'w-full rounded-lg border border-border bg-card px-3.5 py-2.5 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/70 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink/10';
const LABEL = 'mb-1.5 block text-sm font-medium text-ink';
const ERROR = 'mt-1.5 text-xs text-red-600';

/** A labelled field with its validation message. */
function Field({
    label,
    error,
    children,
    hint,
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

/**
 * Placeholder for the artisan's contract type, and by extension Hirfaty's cut.
 *
 * Deliberately inert: it holds no state, is absent from the submitted payload
 * and has no matching column or validation rule server-side. It is disabled
 * and greyed so it reads as "coming, not yet" rather than as a field the admin
 * forgot to fill — the commercial terms are still being decided.
 */
function ContractTypePlaceholder() {
    return (
        <div className="rounded-lg border border-dashed border-border bg-muted/30 p-4">
            <div className="mb-2 flex items-center justify-between gap-3">
                <label className="text-sm font-medium text-ink-soft/80">
                    Type de contrat
                </label>
                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-[11px] font-medium text-ink-soft/70">
                    Bientôt
                </span>
            </div>
            <select
                disabled
                aria-hidden="true"
                tabIndex={-1}
                className="w-full cursor-not-allowed rounded-lg border border-border bg-muted/50 px-3.5 py-2.5 text-sm text-ink-soft/50 shadow-sm"
            >
                <option>À définir</option>
            </select>
            <p className="mt-2 text-xs text-ink-soft/70">
                La commission Hirfaty dépendra de ce choix. Pas encore actif.
            </p>
        </div>
    );
}

/**
 * "I don't have it yet" toggle sitting under an optional contact field.
 * Ticking it clears whatever was typed, so the waiver and the value can never
 * disagree — the server reads the empty field, not the checkbox.
 */
function NotAvailableToggle({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="mt-2 flex cursor-pointer items-center gap-2 text-xs text-ink-soft">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="size-3.5 rounded border-border text-ink focus:ring-2 focus:ring-ink/10"
            />
            {label}
        </label>
    );
}

/**
 * Object URL for a picked file, recreated when the file changes and revoked
 * on cleanup so review-step thumbnails don't leak blob URLs.
 *
 * Derived via useMemo rather than state-synced-via-effect: the URL is a
 * pure function of `file`, so the effect below only needs to *revoke* the
 * previous URL on change/unmount — it never writes state.
 */
function useObjectUrl(file: File | null): string | null {
    const url = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(() => {
        return () => {
            if (url) {
                URL.revokeObjectURL(url);
            }
        };
    }, [url]);

    return url;
}

/** Read-only key/value line used on the review step. */
function Summary({ label, value }: { label: string; value?: string | null }) {
    return (
        <div className="flex items-start justify-between gap-6 py-2.5">
            <dt className="text-sm text-ink-soft">{label}</dt>
            <dd className="text-right text-sm font-medium text-ink">
                {value?.trim() ? (
                    value
                ) : (
                    <span className="text-ink-soft/70">—</span>
                )}
            </dd>
        </div>
    );
}

/**
 * Select values arrive from the DOM as strings but are seeded from numeric ids,
 * so the id fields are widened explicitly rather than inferred.
 */
interface UserWizardForm {
    first_name: string;
    last_name: string;
    email: string;
    phone_number: string;
    role: Role;
    store_name: string;
    store_type: StoreType;
    category_id: number | string;
    city_id: number | string;
    description: string;
    logo: File | null;
    banner: File | null;
}

const Create = ({
    cities,
    categories,
}: PageProps & { cities: City[]; categories: Category[] }) => {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm<UserWizardForm>(
        {
            first_name: '',
            last_name: '',
            email: '',
            phone_number: '',
            role: 'seller',
            store_name: '',
            store_type: 'artist',
            category_id: categories[0]?.id ?? '',
            city_id: cities[0]?.id ?? '',
            description: '',
            logo: null,
            banner: null,
        },
    );

    const isSeller = data.role === 'seller';

    // Sellers get an extra shop step; buyers skip straight to review.
    const steps: { id: StepId; label: string }[] = [
        { id: 'role', label: 'Rôle' },
        { id: 'identity', label: 'Informations' },
        ...(isSeller ? [{ id: 'shop' as StepId, label: 'Boutique' }] : []),
        { id: 'review', label: 'Vérification' },
    ];

    const [stepIndex, setStepIndex] = useState(0);
    // Clamp: switching from seller to buyer removes a step.
    const currentIndex = Math.min(stepIndex, steps.length - 1);
    const current = steps[currentIndex].id;

    /*
     * Contact details are often not to hand when an artisan is signed up in
     * person, so each can be explicitly waived. Kept as local state rather
     * than form data: the server infers "not provided" from the empty field
     * itself, so there is nothing extra to send.
     */
    const [noEmail, setNoEmail] = useState(false);
    const [noPhone, setNoPhone] = useState(false);

    // Client-side gate so the CEO can't reach review with empty fields.
    const identityComplete =
        data.first_name.trim() !== '' &&
        data.last_name.trim() !== '' &&
        (noEmail || data.email.trim() !== '') &&
        (noPhone || data.phone_number.trim() !== '');

    const shopComplete =
        data.store_name.trim() !== '' &&
        data.city_id !== '' &&
        data.category_id !== '';

    const canContinue =
        current === 'role' ||
        (current === 'identity' && identityComplete) ||
        (current === 'shop' && shopComplete) ||
        current === 'review';

    const go = (delta: number) =>
        setStepIndex((i) => Math.min(Math.max(i + delta, 0), steps.length - 1));

    const submit = () => {
        post(route('admin.users.store'), {
            onError: (errs) => {
                // Send the admin back to the step that actually failed.
                // `description` is edited on whichever step renders it: the
                // shop step for sellers, the identity step for everyone else.
                const identityFields = [
                    'first_name',
                    'last_name',
                    'email',
                    'phone_number',
                    'role',
                    ...(isSeller ? [] : ['description']),
                ];
                const shopFields = [
                    'store_name',
                    'store_type',
                    'city_id',
                    'category_id',
                    'description',
                    'logo',
                    'banner',
                ];
                const bad = Object.keys(errs);

                if (bad.some((f) => identityFields.includes(f))) {
                    setStepIndex(1);
                } else if (
                    isSeller &&
                    bad.some((f) => shopFields.includes(f))
                ) {
                    setStepIndex(2);
                }
            },
        });
    };

    const cityName = cities.find(
        (c) => String(c.id) === String(data.city_id),
    )?.name;
    const categoryName = categories.find(
        (c) => String(c.id) === String(data.category_id),
    )?.translated_name;
    const roleLabel = ROLES.find((r) => r.value === data.role)?.label;
    const storeTypeLabel = STORE_TYPES.find(
        (t) => t.value === data.store_type,
    )?.label;
    const bannerPreview = useObjectUrl(data.banner);
    const logoPreview = useObjectUrl(data.logo);

    return (
        <div className="min-h-screen px-6 py-10">
            <Head title={t('titles.admin.user_create')} />

            <div className="mx-auto max-w-5xl">
                <Link
                    href={route('admin.users.index')}
                    className="mb-6 inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour aux utilisateurs
                </Link>

                <header className="mb-8">
                    <h1 className="text-3xl font-semibold tracking-tight text-ink">
                        Créer un utilisateur
                    </h1>
                    <p className="mt-1.5 text-sm text-ink-soft">
                        Renseignez les informations étape par étape, puis
                        validez le récapitulatif.
                    </p>
                </header>

                <div className="grid gap-8 lg:grid-cols-[260px_1fr]">
                    {/* ── Stepper ─────────────────────────────── */}
                    <nav aria-label="Progression" className="lg:pt-2">
                        <ol className="space-y-1">
                            {steps.map((s, i) => {
                                const done = i < currentIndex;
                                const active = i === currentIndex;

                                return (
                                    <li key={s.id}>
                                        <button
                                            type="button"
                                            // Only allow jumping back, never skipping ahead.
                                            disabled={i > currentIndex}
                                            onClick={() => setStepIndex(i)}
                                            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition ${
                                                active
                                                    ? 'bg-ink text-white shadow-sm'
                                                    : done
                                                      ? 'text-ink hover:bg-muted/60'
                                                      : 'text-ink-soft/70'
                                            }`}
                                        >
                                            <span
                                                className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                                    active
                                                        ? 'bg-card text-ink'
                                                        : done
                                                          ? 'bg-emerald-600 text-white'
                                                          : 'bg-muted text-ink-soft'
                                                }`}
                                            >
                                                {done ? (
                                                    <Check className="h-3.5 w-3.5" />
                                                ) : (
                                                    i + 1
                                                )}
                                            </span>
                                            <span className="text-sm font-medium">
                                                {s.label}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>

                    {/* ── Step panel ──────────────────────────── */}
                    <div className="rounded-xl border border-border bg-card shadow-sm">
                        <div className="p-6 md:p-8">
                            {/* Step 1 — role */}
                            {current === 'role' && (
                                <section>
                                    <h2 className="text-lg font-semibold text-ink">
                                        Quel type de compte ?
                                    </h2>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        Ce choix détermine les étapes suivantes.
                                    </p>

                                    <div className="mt-6 space-y-3">
                                        {ROLES.map((r) => {
                                            const Icon = r.icon;
                                            const selected =
                                                data.role === r.value;

                                            return (
                                                <button
                                                    key={r.value}
                                                    type="button"
                                                    onClick={() =>
                                                        setData('role', r.value)
                                                    }
                                                    className={`flex w-full items-start gap-4 rounded-xl border p-4 text-left transition ${
                                                        selected
                                                            ? 'border-ink bg-muted/40 ring-2 ring-ink/10'
                                                            : 'hover:bg-muted/40/60 border-border hover:border-border'
                                                    }`}
                                                >
                                                    <span
                                                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${
                                                            selected
                                                                ? 'bg-ink text-white'
                                                                : 'bg-muted text-ink-soft'
                                                        }`}
                                                    >
                                                        <Icon className="h-5 w-5" />
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block text-sm font-semibold text-ink">
                                                            {r.label}
                                                        </span>
                                                        <span className="mt-0.5 block text-sm text-ink-soft">
                                                            {r.description}
                                                        </span>
                                                    </span>
                                                    {selected && (
                                                        <Check className="h-5 w-5 shrink-0 text-ink" />
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {errors.role && (
                                        <p className={ERROR}>{errors.role}</p>
                                    )}
                                </section>
                            )}

                            {/* Step 2 — identity */}
                            {current === 'identity' && (
                                <section>
                                    <h2 className="text-lg font-semibold text-ink">
                                        Informations personnelles
                                    </h2>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        Coordonnées du titulaire du compte.
                                    </p>

                                    <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                        <Field
                                            label="Prénom"
                                            error={errors.first_name}
                                        >
                                            <input
                                                autoFocus
                                                value={data.first_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'first_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Hassan"
                                                className={INPUT}
                                            />
                                        </Field>

                                        <Field
                                            label="Nom"
                                            error={errors.last_name}
                                        >
                                            <input
                                                value={data.last_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'last_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Alaoui"
                                                className={INPUT}
                                            />
                                        </Field>

                                        <div>
                                            <Field
                                                label="Email"
                                                error={errors.email}
                                            >
                                                <input
                                                    type="email"
                                                    value={data.email}
                                                    disabled={noEmail}
                                                    onChange={(e) =>
                                                        setData(
                                                            'email',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="hassan@exemple.ma"
                                                    className={`${INPUT} disabled:cursor-not-allowed disabled:bg-muted/40 disabled:text-ink-soft/60`}
                                                />
                                            </Field>
                                            <NotAvailableToggle
                                                label="Je n’ai pas son email"
                                                checked={noEmail}
                                                onChange={(checked) => {
                                                    setNoEmail(checked);

                                                    if (checked) {
                                                        setData('email', '');
                                                    }
                                                }}
                                            />
                                            {noEmail && (
                                                <p className="mt-1.5 text-xs text-amber-700">
                                                    Le compte sera créé, mais il
                                                    ne pourra pas se connecter
                                                    tant qu’une adresse email
                                                    n’est pas ajoutée.
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <Field
                                                label="Téléphone"
                                                error={errors.phone_number}
                                            >
                                                <input
                                                    value={data.phone_number}
                                                    disabled={noPhone}
                                                    onChange={(e) =>
                                                        setData(
                                                            'phone_number',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="0612345678"
                                                    className={`${INPUT} disabled:cursor-not-allowed disabled:bg-muted/40 disabled:text-ink-soft/60`}
                                                />
                                            </Field>
                                            <NotAvailableToggle
                                                label="Je n’ai pas son téléphone"
                                                checked={noPhone}
                                                onChange={(checked) => {
                                                    setNoPhone(checked);

                                                    if (checked) {
                                                        setData(
                                                            'phone_number',
                                                            '',
                                                        );
                                                    }
                                                }}
                                            />
                                        </div>

                                        {/* Sellers describe their shop on the
                                            next step instead. */}
                                        {!isSeller && (
                                            <div className="sm:col-span-2">
                                                <Field
                                                    label="Description (optionnel)"
                                                    error={errors.description}
                                                    hint="Notes sur ce client — activité, contexte, ce qu’il achète."
                                                >
                                                    <textarea
                                                        rows={8}
                                                        value={data.description}
                                                        onChange={(e) =>
                                                            setData(
                                                                'description',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Revendeur basé à Casablanca, commande surtout des tapis…"
                                                        className={INPUT}
                                                    />
                                                </Field>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            )}

                            {/* Step 3 — shop (sellers only) */}
                            {current === 'shop' && (
                                <section>
                                    <h2 className="text-lg font-semibold text-ink">
                                        La boutique
                                    </h2>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        Ces informations apparaîtront sur la
                                        fiche publique de l’artisan.
                                    </p>

                                    <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <Field
                                                label="Nom de la boutique"
                                                error={errors.store_name}
                                            >
                                                <input
                                                    autoFocus
                                                    value={data.store_name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'store_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Poterie de Safi"
                                                    className={INPUT}
                                                />
                                            </Field>
                                        </div>

                                        <div className="sm:col-span-2">
                                            <Field
                                                label="Type de vendeur"
                                                error={errors.store_type}
                                            >
                                                <div className="flex flex-col gap-2 sm:flex-row">
                                                    {STORE_TYPES.map((t) => {
                                                        const Icon = t.icon;
                                                        const selected =
                                                            data.store_type ===
                                                            t.value;

                                                        return (
                                                            <button
                                                                key={t.value}
                                                                type="button"
                                                                onClick={() =>
                                                                    setData(
                                                                        'store_type',
                                                                        t.value,
                                                                    )
                                                                }
                                                                className={`flex flex-1 items-center gap-2 rounded-lg border px-3.5 py-2.5 text-sm font-medium transition ${
                                                                    selected
                                                                        ? 'border-ink bg-ink text-white'
                                                                        : 'border-border text-ink-soft hover:border-ink/30 hover:text-ink'
                                                                }`}
                                                            >
                                                                <Icon className="h-4 w-4" />
                                                                {t.label}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </Field>
                                        </div>

                                        {/*
                                            Held space, not a field. There is
                                            no contract column on `stores` and
                                            nothing is submitted from here —
                                            the commercial terms are still
                                            being decided, so the control is
                                            rendered greyed and disabled rather
                                            than half-wired. Give it a real
                                            `data.contract_type` (and a
                                            validation rule) when the terms land.
                                        */}
                                        <div className="sm:col-span-2">
                                            <ContractTypePlaceholder />
                                        </div>

                                        <Field
                                            label="Ville"
                                            error={errors.city_id}
                                        >
                                            <select
                                                value={data.city_id}
                                                onChange={(e) =>
                                                    setData(
                                                        'city_id',
                                                        e.target.value,
                                                    )
                                                }
                                                className={INPUT}
                                            >
                                                {cities.map((c) => (
                                                    <option
                                                        key={c.id}
                                                        value={c.id}
                                                    >
                                                        {c.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>

                                        <Field
                                            label="Type de produits"
                                            error={errors.category_id}
                                            hint="Catégorie principale vendue par l’artisan."
                                        >
                                            <select
                                                value={data.category_id}
                                                onChange={(e) =>
                                                    setData(
                                                        'category_id',
                                                        e.target.value,
                                                    )
                                                }
                                                className={INPUT}
                                            >
                                                {categories.map((c) => (
                                                    <option
                                                        key={c.id}
                                                        value={c.id}
                                                    >
                                                        {/* name is a per-locale object; never render it raw */}
                                                        {c.translated_name}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>

                                        <div className="sm:col-span-2">
                                            <Field
                                                label="Description (optionnel)"
                                                error={errors.description}
                                            >
                                                <textarea
                                                    rows={4}
                                                    value={data.description}
                                                    onChange={(e) =>
                                                        setData(
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Atelier familial spécialisé dans la poterie émaillée…"
                                                    className={INPUT}
                                                />
                                            </Field>
                                        </div>
                                    </div>

                                    {/* ── Visual identity ──────────────── */}
                                    <div className="mt-8 border-t border-border pt-6">
                                        <h3 className="text-sm font-semibold text-ink">
                                            Identité visuelle
                                        </h3>
                                        <p className="mt-1 text-xs text-ink-soft">
                                            Optionnel — peut être ajouté plus
                                            tard. La bannière habille l’en-tête
                                            de la boutique, la photo sert
                                            d’avatar.
                                        </p>

                                        <div className="mt-4 space-y-5">
                                            <SingleImageInput
                                                label="Bannière"
                                                aspect="wide"
                                                value={data.banner}
                                                onChange={(file) =>
                                                    setData('banner', file)
                                                }
                                                error={errors.banner}
                                            />
                                            <SingleImageInput
                                                label="Photo de la boutique"
                                                aspect="square"
                                                value={data.logo}
                                                onChange={(file) =>
                                                    setData('logo', file)
                                                }
                                                error={errors.logo}
                                            />
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Step 4 — review */}
                            {current === 'review' && (
                                <section>
                                    <h2 className="text-lg font-semibold text-ink">
                                        Vérification
                                    </h2>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        Relisez avant de créer le compte.
                                    </p>

                                    <div className="mt-6 space-y-6">
                                        <div className="bg-muted/40/60 rounded-xl border border-border p-5">
                                            <h3 className="mb-2 text-xs font-semibold tracking-wider text-ink-soft uppercase">
                                                Compte
                                            </h3>
                                            <dl className="divide-y divide-border">
                                                <Summary
                                                    label="Rôle"
                                                    value={roleLabel}
                                                />
                                                <Summary
                                                    label="Prénom"
                                                    value={data.first_name}
                                                />
                                                <Summary
                                                    label="Nom"
                                                    value={data.last_name}
                                                />
                                                <Summary
                                                    label="Email"
                                                    value={
                                                        noEmail
                                                            ? 'À compléter plus tard'
                                                            : data.email
                                                    }
                                                />
                                                <Summary
                                                    label="Téléphone"
                                                    value={
                                                        noPhone
                                                            ? 'À compléter plus tard'
                                                            : data.phone_number
                                                    }
                                                />
                                                {!isSeller && (
                                                    <Summary
                                                        label="Description"
                                                        value={data.description}
                                                    />
                                                )}
                                            </dl>
                                        </div>

                                        {isSeller && (
                                            <div className="bg-muted/40/60 rounded-xl border border-border p-5">
                                                <h3 className="mb-2 text-xs font-semibold tracking-wider text-ink-soft uppercase">
                                                    Boutique
                                                </h3>
                                                <dl className="divide-y divide-border">
                                                    <Summary
                                                        label="Nom"
                                                        value={data.store_name}
                                                    />
                                                    <Summary
                                                        label="Type de vendeur"
                                                        value={storeTypeLabel}
                                                    />
                                                    <Summary
                                                        label="Ville"
                                                        value={cityName}
                                                    />
                                                    <Summary
                                                        label="Type de produits"
                                                        value={categoryName}
                                                    />
                                                    <Summary
                                                        label="Description"
                                                        value={data.description}
                                                    />
                                                </dl>

                                                {(bannerPreview ||
                                                    logoPreview) && (
                                                    <div className="mt-4 flex gap-3 border-t border-border pt-4">
                                                        {bannerPreview && (
                                                            <img
                                                                src={
                                                                    bannerPreview
                                                                }
                                                                alt="Bannière"
                                                                className="h-16 flex-1 rounded-lg object-cover"
                                                            />
                                                        )}
                                                        {logoPreview && (
                                                            <img
                                                                src={
                                                                    logoPreview
                                                                }
                                                                alt="Photo"
                                                                className="h-16 w-16 shrink-0 rounded-lg object-cover"
                                                            />
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {Object.keys(errors).length > 0 && (
                                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                                Certaines informations sont
                                                invalides. Revenez à l’étape
                                                concernée pour les corriger.
                                            </div>
                                        )}
                                    </div>
                                </section>
                            )}
                        </div>

                        {/* ── Footer nav ──────────────────────── */}
                        <div className="bg-muted/40/60 flex items-center justify-between gap-4 border-t border-border px-6 py-4 md:px-8">
                            <button
                                type="button"
                                onClick={() => go(-1)}
                                disabled={currentIndex === 0 || processing}
                                className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-ink-soft transition hover:text-ink disabled:invisible"
                            >
                                <ArrowLeft className="h-4 w-4" /> Précédent
                            </button>

                            <span className="text-xs text-ink-soft/70">
                                Étape {currentIndex + 1} sur {steps.length}
                            </span>

                            {current === 'review' ? (
                                <button
                                    type="button"
                                    onClick={submit}
                                    disabled={processing}
                                    className="inline-flex items-center gap-2 rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90 disabled:opacity-60"
                                >
                                    {processing && (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    )}
                                    {processing
                                        ? 'Création…'
                                        : 'Créer le compte'}
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => go(1)}
                                    disabled={!canContinue}
                                    className="inline-flex items-center gap-2 rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    Continuer
                                    <ArrowRight className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

Create.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Create;
