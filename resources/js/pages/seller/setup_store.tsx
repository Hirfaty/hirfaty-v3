import { ArrowRight, Check, ImageIcon, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import WarmFill from '@/components/WarmFill';
import { useForm } from '@inertiajs/react';

const steps = [
    'Identité de la boutique',
    'Image & bannière',
    'Lancement',
] as const;

type Category = { id: number; name: string };
type City = { id: number; name: string };

export default function SetupStore({
    categories,
    cities,
}: {
    categories: Category[];
    cities: City[];
}) {
    const [step, setStep] = useState(0);
    const [logoLoaded, setLogoLoaded] = useState(false);
    const [bannerLoaded, setBannerLoaded] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        store_name: '',
        category_id: categories[0]?.id ?? null,
        city_id: cities[0]?.id ?? null,
        description: '',
    });

    const bioMax = 240;

    const canNext = useMemo(() => {
        if (step === 0)
            return (
                data.store_name.trim().length > 1 &&
                data.description.trim().length > 10
            );
        if (step === 1) return logoLoaded && bannerLoaded;
        return true;
    }, [step, data.store_name, data.description, logoLoaded, bannerLoaded]);

    const handleSubmit = () => {
        post(route('seller.setup.store'));
    };

    // helpers for the preview
    const selectedCategory = categories.find((c) => c.id === data.category_id);
    const selectedCity = cities.find((c) => c.id === data.city_id);

    return (
        <div className="paper-grain min-h-screen bg-cream">
            <section className="container pt-12 pb-6 md:pt-16">
                <p className="eyebrow mb-3">Bienvenue sur Hirfaty</p>
                <div className="grid grid-cols-12 items-end gap-6">
                    <h1 className="h-editorial col-span-12 text-4xl text-ink md:col-span-8 md:text-6xl">
                        Installons votre{' '}
                        <span className="text-terracotta italic">atelier</span>.
                        <br />
                        Ça prend quelques minutes.
                    </h1>
                    <p className="col-span-12 text-base leading-relaxed text-ink-soft md:col-span-4 md:text-right">
                        Pas de pression, pas de validation administrative. Vous
                        pourrez tout modifier plus tard depuis vos paramètres.
                    </p>
                </div>
            </section>

            {/* Progress */}
            <section className="container mt-10">
                <ol className="grid grid-cols-3 border-y border-ink/15">
                    {steps.map((s, i) => {
                        const done = i < step;
                        const active = i === step;
                        return (
                            <li
                                key={s}
                                className={`flex items-center gap-3 border-l border-ink/15 px-4 py-5 first:border-l-0 md:px-6 ${active ? 'bg-cream-deep/60' : ''}`}
                            >
                                <span
                                    className={`flex h-7 w-7 items-center justify-center border font-display text-xs ${
                                        done
                                            ? 'border-ink bg-ink text-cream'
                                            : active
                                              ? 'border-terracotta text-terracotta'
                                              : 'border-ink/30 text-ink-soft'
                                    }`}
                                >
                                    {done ? (
                                        <Check className="h-3.5 w-3.5" />
                                    ) : (
                                        i + 1
                                    )}
                                </span>
                                <div className="leading-tight">
                                    <p
                                        className={`text-[11px] tracking-[0.18em] uppercase ${active || done ? 'text-ink' : 'text-ink-soft'}`}
                                    >
                                        Étape {i + 1}
                                    </p>
                                    <p
                                        className={`text-sm ${active || done ? 'text-ink' : 'text-ink-soft'}`}
                                    >
                                        {s}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </section>

            <section className="container grid grid-cols-12 gap-10 py-12 md:gap-16 md:py-16">
                {/* Form column */}
                <div className="col-span-12 space-y-10 md:col-span-7">
                    {/* ── Step 0 — Store identity ── */}
                    {step === 0 && (
                        <>
                            <Field label="Nom de la boutique">
                                <input
                                    value={data.store_name}
                                    onChange={(e) =>
                                        setData('store_name', e.target.value)
                                    }
                                    placeholder="ex. Atelier Bab Boujloud"
                                    className="w-full border-0 border-b border-ink/30 bg-transparent pb-2 font-display text-2xl text-ink focus:border-ink focus:outline-none md:text-3xl"
                                />
                                {errors.store_name && (
                                    <Error>{errors.store_name}</Error>
                                )}
                            </Field>

                            <div className="grid grid-cols-1 gap-10 md:grid-cols-2">
                                <Field label="Métier principal">
                                    <Select
                                        value={data.category_id}
                                        onChange={(id) =>
                                            setData('category_id', id)
                                        }
                                        options={categories}
                                    />
                                    {errors.category_id && (
                                        <Error>{errors.category_id}</Error>
                                    )}
                                </Field>
                                <Field label="Ville de l'atelier">
                                    <Select
                                        value={data.city_id}
                                        onChange={(id) =>
                                            setData('city_id', id)
                                        }
                                        options={cities}
                                    />
                                    {errors.city_id && (
                                        <Error>{errors.city_id}</Error>
                                    )}
                                </Field>
                            </div>

                            <Field label="Quelques mots sur votre travail">
                                <textarea
                                    value={data.description}
                                    onChange={(e) =>
                                        setData(
                                            'description',
                                            e.target.value.slice(0, bioMax),
                                        )
                                    }
                                    rows={4}
                                    className="w-full resize-none border border-ink/20 bg-transparent p-4 text-base leading-relaxed text-ink focus:border-ink focus:outline-none"
                                />
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-xs text-ink-soft tabular-nums">
                                        {data.description.length}/{bioMax}
                                    </span>
                                </div>
                                {errors.description && (
                                    <Error>{errors.description}</Error>
                                )}
                            </Field>
                        </>
                    )}

                    {/* ── Step 1 — Media ── */}
                    {step === 1 && (
                        <div className="space-y-12">
                            <Uploader
                                label="Logo de la boutique"
                                hint="Un carré, idéalement 800×800. PNG ou JPG."
                                aspect="aspect-square w-44"
                                loaded={logoLoaded}
                                onUpload={() => setLogoLoaded(true)}
                            />
                            <Uploader
                                label="Bannière de la vitrine"
                                hint="Format large, 3:1 ou 16:9. Préférez une vraie photo de matière, d'atelier ou d'objet."
                                aspect="aspect-[3/1] w-full"
                                loaded={bannerLoaded}
                                onUpload={() => setBannerLoaded(true)}
                                wide
                            />
                        </div>
                    )}

                    {/* ── Step 2 — Review ── */}
                    {step === 2 && (
                        <div className="space-y-8">
                            <p className="font-display text-2xl leading-snug text-ink">
                                Tout est prêt. Vérifiez et lancez votre boutique
                                — vous pourrez ajouter vos premières pièces
                                juste après.
                            </p>
                            <ul className="divide-y divide-ink/15 border-y border-ink/15">
                                {[
                                    ['Boutique', data.store_name],
                                    ['Métier', selectedCategory?.name ?? '—'],
                                    ['Ville', selectedCity?.name ?? '—'],
                                    ['Description', data.description],
                                    [
                                        'Logo',
                                        logoLoaded ? 'Téléversé' : 'Manquant',
                                    ],
                                    [
                                        'Bannière',
                                        bannerLoaded
                                            ? 'Téléversée'
                                            : 'Manquante',
                                    ],
                                ].map(([k, v]) => (
                                    <li
                                        key={k}
                                        className="grid grid-cols-12 gap-4 py-4"
                                    >
                                        <span className="eyebrow col-span-4 md:col-span-3">
                                            {k}
                                        </span>
                                        <span className="col-span-8 text-ink md:col-span-9">
                                            {v}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <p className="text-sm text-ink-soft">
                                Vous pourrez toujours modifier ces informations
                                depuis vos paramètres de boutique.
                            </p>
                        </div>
                    )}

                    {/* ── Navigation ── */}
                    <div className="flex items-center justify-between border-t border-ink/15 pt-6">
                        <button
                            onClick={() => setStep((s) => Math.max(0, s - 1))}
                            disabled={step === 0}
                            className="text-sm text-ink-soft hover:text-ink disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            ← Retour
                        </button>

                        {step < steps.length - 1 ? (
                            <button
                                onClick={() => setStep((s) => s + 1)}
                                disabled={!canNext}
                                className="inline-flex items-center gap-2 bg-ink px-6 py-3 text-sm text-cream transition-colors hover:bg-terracotta disabled:opacity-40 disabled:hover:bg-ink"
                            >
                                Continuer <ArrowRight className="h-4 w-4" />
                            </button>
                        ) : (
                            <button
                                onClick={handleSubmit}
                                disabled={processing}
                                className="inline-flex items-center gap-2 bg-terracotta px-7 py-3.5 text-sm text-cream transition-colors hover:bg-terracotta-deep disabled:opacity-50"
                            >
                                {processing ? 'Envoi…' : 'Lancer ma boutique'}
                                <ArrowRight className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                </div>

                {/* ── Live preview ── */}
                <aside className="col-span-12 md:col-span-5">
                    <div className="md:sticky md:top-8">
                        <p className="eyebrow mb-4">Aperçu en direct</p>
                        <div className="overflow-hidden border border-ink/15 bg-paper">
                            {bannerLoaded ? (
                                <WarmFill
                                    variant="textile"
                                    className="aspect-[3/1] w-full"
                                />
                            ) : (
                                <div className="flex aspect-[3/1] w-full items-center justify-center bg-cream-deep text-xs tracking-[0.18em] text-ink-soft uppercase">
                                    Bannière à venir
                                </div>
                            )}

                            <div className="relative -mt-10 p-6">
                                <div className="flex items-end gap-4">
                                    {logoLoaded ? (
                                        <WarmFill
                                            variant="ink"
                                            className="flex h-20 w-20 items-center justify-center border-4 border-paper"
                                        >
                                            <span className="font-display text-2xl text-cream">
                                                {data.store_name.charAt(0) ||
                                                    '?'}
                                            </span>
                                        </WarmFill>
                                    ) : (
                                        <div className="flex h-20 w-20 items-center justify-center border-4 border-paper bg-cream-deep text-ink-soft">
                                            <ImageIcon
                                                className="h-5 w-5"
                                                strokeWidth={1.5}
                                            />
                                        </div>
                                    )}
                                </div>
                                <h3 className="mt-4 font-display text-2xl text-ink">
                                    {data.store_name || 'Nom de votre boutique'}
                                </h3>
                                <p className="text-sm text-ink-soft">
                                    {selectedCategory?.name ?? '—'} ·{' '}
                                    {selectedCity?.name ?? '—'}
                                </p>
                                <p className="mt-4 text-sm leading-relaxed text-ink/85">
                                    {data.description ||
                                        'Votre description apparaîtra ici.'}
                                </p>
                            </div>
                        </div>
                        <p className="mt-4 text-xs text-ink-soft">
                            C'est ce que les acheteurs verront en haut de votre
                            vitrine.
                        </p>
                    </div>
                </aside>
            </section>
        </div>
    );
}

// ── Sub-components ────────────────────────────────────────────────────────────

const Field = ({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) => (
    <div>
        <label className="eyebrow mb-3 block">{label}</label>
        {children}
    </div>
);

const Error = ({ children }: { children: React.ReactNode }) => (
    <p className="mt-1 text-xs text-red-500">{children}</p>
);

const Select = ({
    value,
    onChange,
    options,
}: {
    value: number | null;
    onChange: (id: number) => void;
    options: { id: number; name: string }[];
}) => (
    <div className="relative">
        <select
            value={value ?? ''}
            onChange={(e) => onChange(Number(e.target.value))}
            className="w-full appearance-none border-0 border-b border-ink/30 bg-transparent pr-8 pb-2 font-display text-lg text-ink focus:border-ink focus:outline-none"
        >
            {options.map((o) => (
                <option key={o.id} value={o.id}>
                    {o.name}
                </option>
            ))}
        </select>
        <span className="pointer-events-none absolute right-1 bottom-3 text-ink-soft">
            ▾
        </span>
    </div>
);

const Uploader = ({
    label,
    hint,
    aspect,
    loaded,
    onUpload,
    wide,
}: {
    label: string;
    hint: string;
    aspect: string;
    loaded: boolean;
    onUpload: () => void;
    wide?: boolean;
}) => (
    <div>
        <p className="eyebrow mb-3">{label}</p>
        <button
            onClick={onUpload}
            className={`${aspect} group flex flex-col items-center justify-center gap-2 border border-dashed border-ink/30 text-ink-soft transition-colors hover:border-ink hover:bg-cream-deep/40`}
        >
            {loaded ? (
                <WarmFill
                    variant={wide ? 'textile' : 'pottery'}
                    className="flex h-full w-full items-end p-3"
                >
                    <span className="text-xs tracking-[0.18em] text-cream uppercase">
                        Téléversé · cliquer pour remplacer
                    </span>
                </WarmFill>
            ) : (
                <>
                    <Upload
                        className="h-5 w-5 group-hover:text-ink"
                        strokeWidth={1.5}
                    />
                    <span className="text-sm group-hover:text-ink">
                        Cliquer pour téléverser
                    </span>
                </>
            )}
        </button>
        <p className="mt-2 text-xs text-ink-soft">{hint}</p>
    </div>
);
