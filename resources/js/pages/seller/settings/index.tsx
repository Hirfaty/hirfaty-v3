import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Check,
    Clock,
    Camera,
    ExternalLink,
    Globe,
    KeyRound,
    Loader2,
    Mail,
    Phone,
    Store as StoreIcon,
    UserRound,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import SellerLayout from '@/layouts/seller/layout';
import { validateMediaFile } from '@/lib/media-limits';
import { formatDate } from '@/lib/utils';
import type { Category, City, SellerStoreStatus, Store } from '@/types';
import type { User } from '@/types/auth';

interface SellerSettingsProps {
    user: User & {
        store: Store & {
            verification_status: SellerStoreStatus['verification_status'];
            approved_at: string | null;
        };
    };
    categories: Category[];
    /** Named in the seller's language — CityService::options(). */
    cities: Pick<City, 'id' | 'name'>[];
}

/** The fields Store::REQUIRED_PROFILE_FIELDS asks for before a review. */
const REQUIRED_FIELDS: { key: string; label: string }[] = [
    { key: 'store_name', label: 'Nom de l’atelier' },
    { key: 'category_id', label: 'Métier' },
    { key: 'city_id', label: 'Ville' },
    { key: 'description', label: 'Présentation' },
];

// One boxed look for inputs, selects and textareas — the shared Input is
// underline-only, which sits oddly beside a boxed select.
const fieldClass =
    'rounded-lg border border-input bg-card px-3 text-sm text-ink transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none';
const inputClass = `h-11 py-2 ${fieldClass}`;
const selectClass = `h-11 w-full ${fieldClass}`;

export default function SellerSettings({
    user,
    categories,
    cities,
}: SellerSettingsProps) {
    const { t } = useTranslation();
    const { sellerStore } = usePage<{ sellerStore: SellerStoreStatus | null }>()
        .props;

    const profileForm = useForm({
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        phone_number: user.phone_number ?? '',
        bio: user.bio ?? '',
    });

    const storeForm = useForm({
        store_name: user.store?.store_name ?? '',
        category_id: String(user.store?.category_id ?? ''),
        city_id: String(user.store?.city_id ?? ''),
        description: user.store?.description ?? '',
    });

    const localeForm = useForm({
        locale: user.locale ?? 'fr',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const missing = sellerStore?.missing_fields ?? [];
    // Highlights a required field the review is still waiting on.
    const missingRing = (field: string) =>
        missing.includes(field)
            ? 'border-orange-400 ring-2 ring-orange-100'
            : '';

    return (
        <>
            <Head title={t('titles.seller.settings')} />

            <div className="flex flex-col gap-6 p-6 md:p-8">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Paramètres
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        Votre atelier, votre profil, la langue et la sécurité de
                        votre compte.
                    </p>
                </header>

                <VerificationCard store={user.store} status={sellerStore} />

                <div className="grid gap-6 xl:grid-cols-[1fr_340px]">
                    <div className="flex flex-col gap-6">
                        {/* ── Workshop ─────────────────────────── */}
                        <Panel
                            Icon={StoreIcon}
                            title="Mon atelier"
                            hint="Ce que les clients voient sur votre page boutique."
                        >
                            <ShopPhotoInput
                                current={user.store.logo}
                                storeName={user.store.store_name}
                            />

                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    storeForm.patch(
                                        route('seller.settings.store.update'),
                                        { preserveScroll: true },
                                    );
                                }}
                                className="space-y-5"
                            >
                                <div className="grid gap-5 md:grid-cols-3">
                                    <Field
                                        id="store_name"
                                        label="Nom de l’atelier"
                                        error={storeForm.errors.store_name}
                                    >
                                        <Input
                                            id="store_name"
                                            value={storeForm.data.store_name}
                                            onChange={(event) =>
                                                storeForm.setData(
                                                    'store_name',
                                                    event.target.value,
                                                )
                                            }
                                            className={`${inputClass} ${missingRing('store_name')}`}
                                        />
                                    </Field>
                                    <Field
                                        id="category_id"
                                        label="Métier"
                                        error={storeForm.errors.category_id}
                                    >
                                        <select
                                            id="category_id"
                                            value={storeForm.data.category_id}
                                            onChange={(event) =>
                                                storeForm.setData(
                                                    'category_id',
                                                    event.target.value,
                                                )
                                            }
                                            className={`${selectClass} ${missingRing('category_id')}`}
                                        >
                                            <option value="" disabled>
                                                Choisir un métier
                                            </option>
                                            {categories.map((category) => (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.translated_name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field
                                        id="city_id"
                                        label="Ville"
                                        error={storeForm.errors.city_id}
                                    >
                                        <select
                                            id="city_id"
                                            value={storeForm.data.city_id}
                                            onChange={(event) =>
                                                storeForm.setData(
                                                    'city_id',
                                                    event.target.value,
                                                )
                                            }
                                            className={`${selectClass} ${missingRing('city_id')}`}
                                        >
                                            <option value="" disabled>
                                                Choisir une ville
                                            </option>
                                            {cities.map((city) => (
                                                <option
                                                    key={city.id}
                                                    value={city.id}
                                                >
                                                    {city.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                </div>

                                <Field
                                    id="description"
                                    label="Présentation"
                                    hint={`${storeForm.data.description.length} / 2500`}
                                    error={storeForm.errors.description}
                                >
                                    <Textarea
                                        id="description"
                                        rows={7}
                                        maxLength={2500}
                                        value={storeForm.data.description}
                                        onChange={(event) =>
                                            storeForm.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Votre savoir-faire, vos matières, votre histoire…"
                                        className={`min-h-36 ${missingRing('description')}`}
                                    />
                                </Field>

                                <FormFooter>
                                    <Button disabled={storeForm.processing}>
                                        Enregistrer l’atelier
                                    </Button>
                                </FormFooter>
                            </form>
                        </Panel>

                        {/* ── Profile ──────────────────────────── */}
                        <Panel
                            Icon={UserRound}
                            title="Profil"
                            hint="Vos coordonnées, pour que l’équipe Hirfaty puisse vous joindre."
                        >
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    profileForm.patch(
                                        route('seller.settings.profile.update'),
                                        { preserveScroll: true },
                                    );
                                }}
                                className="space-y-5"
                            >
                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        id="first_name"
                                        label="Prénom"
                                        error={profileForm.errors.first_name}
                                    >
                                        <Input
                                            id="first_name"
                                            value={profileForm.data.first_name}
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'first_name',
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </Field>
                                    <Field
                                        id="last_name"
                                        label="Nom"
                                        error={profileForm.errors.last_name}
                                    >
                                        <Input
                                            id="last_name"
                                            value={profileForm.data.last_name}
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'last_name',
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </Field>
                                </div>

                                <Field
                                    id="phone"
                                    label="Téléphone"
                                    error={profileForm.errors.phone_number}
                                >
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={profileForm.data.phone_number}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                'phone_number',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </Field>

                                <Field
                                    id="bio"
                                    label="Bio"
                                    hint={`${profileForm.data.bio.length} / 500`}
                                    error={profileForm.errors.bio}
                                >
                                    <Textarea
                                        id="bio"
                                        rows={4}
                                        maxLength={500}
                                        value={profileForm.data.bio}
                                        onChange={(event) =>
                                            profileForm.setData(
                                                'bio',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <FormFooter>
                                    <Button disabled={profileForm.processing}>
                                        Enregistrer le profil
                                    </Button>
                                </FormFooter>
                            </form>
                        </Panel>

                        {/* ── Password ─────────────────────────── */}
                        <Panel
                            Icon={KeyRound}
                            title="Mot de passe"
                            hint="Au moins 8 caractères, avec des lettres et des chiffres."
                        >
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    passwordForm.patch(
                                        route(
                                            'seller.settings.password.update',
                                        ),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                passwordForm.reset(),
                                        },
                                    );
                                }}
                                className="space-y-5"
                            >
                                <Field
                                    id="current_password"
                                    label="Mot de passe actuel"
                                    error={passwordForm.errors.current_password}
                                >
                                    <Input
                                        id="current_password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={
                                            passwordForm.data.current_password
                                        }
                                        onChange={(event) =>
                                            passwordForm.setData(
                                                'current_password',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </Field>
                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        id="password"
                                        label="Nouveau mot de passe"
                                        error={passwordForm.errors.password}
                                    >
                                        <Input
                                            id="password"
                                            type="password"
                                            autoComplete="new-password"
                                            value={passwordForm.data.password}
                                            onChange={(event) =>
                                                passwordForm.setData(
                                                    'password',
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </Field>
                                    <Field
                                        id="password_confirmation"
                                        label="Confirmer le mot de passe"
                                    >
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            autoComplete="new-password"
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
                                            className={inputClass}
                                        />
                                    </Field>
                                </div>

                                <FormFooter>
                                    <Button disabled={passwordForm.processing}>
                                        Mettre à jour le mot de passe
                                    </Button>
                                </FormFooter>
                            </form>
                        </Panel>
                    </div>

                    {/* ── Sidebar ──────────────────────────────── */}
                    <aside className="flex flex-col gap-6">
                        <Panel Icon={Mail} title="Compte">
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center gap-2.5">
                                    <Mail className="h-4 w-4 shrink-0 text-ink-soft" />
                                    <span className="min-w-0 truncate text-ink">
                                        {user.email ?? '—'}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <Phone className="h-4 w-4 shrink-0 text-ink-soft" />
                                    <span className="text-ink">
                                        {user.phone_number || '—'}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <Clock className="h-4 w-4 shrink-0 text-ink-soft" />
                                    <span className="text-ink-soft">
                                        Membre depuis le{' '}
                                        {formatDate(user.created_at)}
                                    </span>
                                </div>
                            </div>
                        </Panel>

                        <Panel
                            Icon={Globe}
                            title="Langue"
                            hint="La langue de votre espace vendeur et de vos e-mails."
                        >
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    localeForm.patch(
                                        route('seller.settings.locale.update'),
                                    );
                                }}
                                className="space-y-4"
                            >
                                <div className="grid grid-cols-3 gap-2">
                                    {(
                                        [
                                            ['fr', 'Français'],
                                            ['en', 'English'],
                                            ['ar', 'العربية'],
                                        ] as const
                                    ).map(([code, label]) => {
                                        const active =
                                            localeForm.data.locale === code;

                                        return (
                                            <button
                                                key={code}
                                                type="button"
                                                lang={code}
                                                onClick={() =>
                                                    localeForm.setData(
                                                        'locale',
                                                        code,
                                                    )
                                                }
                                                className={`rounded-lg border px-2 py-2.5 text-sm font-medium transition ${
                                                    active
                                                        ? 'border-ink bg-ink text-white'
                                                        : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                                                }`}
                                            >
                                                {label}
                                            </button>
                                        );
                                    })}
                                </div>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    disabled={
                                        localeForm.processing ||
                                        !localeForm.isDirty
                                    }
                                >
                                    Enregistrer la langue
                                </Button>
                            </form>
                        </Panel>
                    </aside>
                </div>
            </div>
        </>
    );
}

/**
 * The verification state, up top: green once Hirfaty has approved the
 * workshop and its profile is complete, orange for everything short of
 * that — with what is still missing, or why it was turned down.
 */
function VerificationCard({
    store,
    status,
}: {
    store: SellerSettingsProps['user']['store'];
    status: SellerStoreStatus | null;
}) {
    const missing = status?.missing_fields ?? [];
    const verified = status?.can_sell ?? false;

    const { title, body } = verified
        ? {
              title: 'Atelier vérifié par Hirfaty',
              body: store.approved_at
                  ? `Validé le ${formatDate(store.approved_at)}. Le badge « Vérifié par Hirfaty » apparaît sur votre page boutique.`
                  : 'Le badge « Vérifié par Hirfaty » apparaît sur votre page boutique.',
          }
        : missing.length > 0
          ? {
                title: 'Atelier non vérifié — profil incomplet',
                body: 'Complétez les champs en orange ci-dessous puis enregistrez : votre atelier partira automatiquement en validation.',
            }
          : status?.verification_status === 'rejected'
            ? {
                  title: 'Atelier non vérifié — à corriger',
                  body:
                      status.rejection_reason ??
                      'Corrigez les informations de votre atelier puis enregistrez-les pour le renvoyer en validation.',
              }
            : {
                  title: 'Atelier non vérifié — validation en cours',
                  body: 'Notre équipe relit votre atelier, comptez un à deux jours ouvrés. Nous vous contacterons ensuite pour publier vos premières pièces.',
              };

    const Icon = verified ? BadgeCheck : Clock;

    return (
        <section
            role="status"
            className={`overflow-hidden rounded-xl border shadow-sm ${
                verified
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'border-orange-200 bg-orange-50'
            }`}
        >
            <div className="flex flex-col gap-5 p-5 md:flex-row md:items-center md:p-6">
                <div className="flex min-w-0 flex-1 items-start gap-4">
                    <div className="relative shrink-0">
                        {store.logo ? (
                            <img
                                src={store.logo}
                                alt=""
                                className="h-14 w-14 rounded-xl border border-white object-cover shadow-sm"
                            />
                        ) : (
                            <div className="flex h-14 w-14 items-center justify-center rounded-xl border border-white bg-white/70 text-xl font-semibold text-ink shadow-sm">
                                {store.store_name?.charAt(0).toUpperCase()}
                            </div>
                        )}
                        <span
                            className={`absolute -end-1.5 -bottom-1.5 flex h-6 w-6 items-center justify-center rounded-full ring-2 ring-white ${
                                verified ? 'bg-emerald-500' : 'bg-orange-500'
                            }`}
                        >
                            <Icon className="h-3.5 w-3.5 text-white" />
                        </span>
                    </div>

                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="truncate text-lg font-semibold text-ink">
                                {store.store_name}
                            </p>
                            <span
                                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                    verified
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-orange-500 text-white'
                                }`}
                            >
                                <Icon className="h-3 w-3" />
                                {verified ? 'Vérifié' : 'Non vérifié'}
                            </span>
                        </div>
                        <p
                            className={`mt-1 text-sm font-medium ${
                                verified
                                    ? 'text-emerald-800'
                                    : 'text-orange-800'
                            }`}
                        >
                            {title}
                        </p>
                        <p
                            className={`mt-0.5 text-sm ${
                                verified
                                    ? 'text-emerald-700/90'
                                    : 'text-orange-700/90'
                            }`}
                        >
                            {body}
                        </p>
                    </div>
                </div>

                {verified && status?.is_public ? (
                    <Link
                        href={route('stores.show', status.slug)}
                        className="inline-flex shrink-0 items-center gap-2 self-start rounded-lg border border-emerald-300 bg-white px-4 py-2.5 text-sm font-medium text-emerald-800 transition hover:bg-emerald-100 md:self-center"
                    >
                        Voir ma boutique
                        <ExternalLink className="h-3.5 w-3.5" />
                    </Link>
                ) : (
                    !verified && (
                        // The checklist the review waits on — one tick per
                        // Store::REQUIRED_PROFILE_FIELDS entry.
                        <ul className="grid shrink-0 grid-cols-2 gap-x-5 gap-y-1.5 rounded-lg bg-white/70 px-4 py-3 text-sm">
                            {REQUIRED_FIELDS.map((field) => {
                                const done = !missing.includes(field.key);

                                return (
                                    <li
                                        key={field.key}
                                        className={`flex items-center gap-1.5 ${
                                            done
                                                ? 'text-ink'
                                                : 'font-medium text-orange-700'
                                        }`}
                                    >
                                        {done ? (
                                            <Check className="h-3.5 w-3.5 text-emerald-600" />
                                        ) : (
                                            <span className="h-2 w-2 rounded-full bg-orange-500" />
                                        )}
                                        {field.label}
                                    </li>
                                );
                            })}
                        </ul>
                    )
                )}
            </div>
        </section>
    );
}

/**
 * The framed photo on the public shop page. Uploads as soon as a file is
 * picked — there is nothing else to fill in, so no save button to forget.
 * The arch-topped frame is the same one the shop page draws it in.
 */
function ShopPhotoInput({
    current,
    storeName,
}: {
    current: string | null;
    storeName: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = async (file: File) => {
        const problem = await validateMediaFile(file);

        if (problem) {
            setError(problem);

            return;
        }

        setError(null);
        setPreview(URL.createObjectURL(file));

        router.post(
            route('seller.settings.store.logo.update'),
            { logo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onError: (errors) => {
                    setPreview(null);
                    setError(errors.logo ?? 'L’envoi de la photo a échoué.');
                },
                onFinish: () => setUploading(false),
                // The saved URL comes back in props; drop the local preview.
                onSuccess: () => setPreview(null),
            },
        );
    };

    const shown = preview ?? current;

    return (
        <div className="mb-6 flex flex-col gap-4 border-b border-border pb-6 sm:flex-row sm:items-center">
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                disabled={uploading}
                aria-label="Changer la photo de l’atelier"
                className="group relative h-28 w-28 shrink-0 overflow-hidden rounded-t-[3rem] rounded-b-2xl bg-muted ring-1 ring-border transition hover:ring-ink/30"
            >
                {shown ? (
                    <img
                        src={shown}
                        alt={storeName}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <span className="flex h-full w-full items-center justify-center text-ink-soft/50">
                        <StoreIcon className="h-8 w-8" strokeWidth={1.3} />
                    </span>
                )}
                <span
                    className={`absolute inset-0 flex items-center justify-center bg-ink/45 text-white transition ${
                        uploading
                            ? 'opacity-100'
                            : 'opacity-0 group-hover:opacity-100'
                    }`}
                >
                    {uploading ? (
                        <Loader2 className="h-6 w-6 animate-spin" />
                    ) : (
                        <Camera className="h-6 w-6" />
                    )}
                </span>
            </button>

            <div className="min-w-0">
                <p className="text-sm font-medium text-ink">
                    Photo de l’atelier
                </p>
                <p className="mt-0.5 text-sm text-ink-soft">
                    Votre atelier, vos mains au travail ou une pièce phare. JPG,
                    PNG ou WebP, 5 Mo maximum.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-3"
                    disabled={uploading}
                    onClick={() => inputRef.current?.click()}
                >
                    <Camera className="h-4 w-4" />
                    {uploading
                        ? 'Envoi…'
                        : current
                          ? 'Changer la photo'
                          : 'Ajouter une photo'}
                </Button>
                {error && (
                    <p className="mt-2 text-xs text-destructive">{error}</p>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    // Cleared so picking the same file again still fires.
                    event.target.value = '';

                    if (file) {
                        upload(file);
                    }
                }}
            />
        </div>
    );
}

function Panel({
    Icon,
    title,
    hint,
    children,
}: {
    Icon: ComponentType<{ className?: string }>;
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-5 shadow-sm md:p-6">
            <div className="mb-5 flex items-start gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-ink-soft">
                    <Icon className="h-4 w-4" />
                </span>
                <div>
                    <h2 className="text-base font-semibold text-ink">
                        {title}
                    </h2>
                    {hint && (
                        <p className="mt-0.5 text-sm text-ink-soft">{hint}</p>
                    )}
                </div>
            </div>
            {children}
        </section>
    );
}

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <div className="flex items-baseline justify-between gap-2">
                <Label
                    htmlFor={id}
                    className="tracking-normal text-ink normal-case"
                >
                    {label}
                </Label>
                {hint && (
                    <span className="text-xs text-ink-soft tabular-nums">
                        {hint}
                    </span>
                )}
            </div>
            {children}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

function FormFooter({ children }: { children: ReactNode }) {
    return (
        <div className="flex justify-end border-t border-border pt-4">
            {children}
        </div>
    );
}

SellerSettings.layout = (page: ReactNode) => (
    <SellerLayout>{page}</SellerLayout>
);
