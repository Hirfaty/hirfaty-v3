import { Link, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import SiteHeader from '@/components/SiteHeader';
import { Checkbox as CustomCheckbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Category = { id: number; name: string };
type City = { id: number; name: string };

export default function SetupStore({
    categories,
    cities,
}: {
    categories: Category[];
    cities: City[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        store_name: '',
        category_id: categories[0]?.id ?? null,
        city_id: cities[0]?.id ?? null,
        description: '',
        terms_accepted: false,
    });

    const bioMax = 240;

    const canSubmit = useMemo(() => {
        return (
            data.store_name.trim().length > 1 &&
            data.description.trim().length > 10 &&
            data.terms_accepted
        );
    }, [data.store_name, data.description, data.terms_accepted]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('seller.setup.store'));
    };

    return (
        <div className="min-h-screen bg-cream paper-grain">
            <SiteHeader />
            <section className="container pt-12 pb-6 md:pt-16">
                <p className="mb-3 eyebrow">Bienvenue sur Hirfaty</p>
                <div className="grid grid-cols-12 items-end gap-6">
                    <h1 className="col-span-12 h-editorial text-4xl text-ink md:col-span-8 md:text-6xl">
                        Installons votre{' '}
                        <span className="text-terracotta italic">atelier</span>.
                        <br />
                        Ça prend quelques minutes.
                    </h1>
                    <p className="col-span-12 text-base leading-relaxed text-ink-soft md:col-span-4 md:text-right">
                        Complétez les informations de votre boutique pour
                        accéder à votre tableau de bord et commencer à créer vos
                        produits.
                    </p>
                </div>
            </section>

            <form
                onSubmit={handleSubmit}
                className="container grid grid-cols-12 gap-10 py-12 md:gap-16 md:py-16"
            >
                {/* Form column */}
                <div className="col-span-12 space-y-10">
                    <div className="space-y-10">
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
                                    onChange={(id) => setData('city_id', id)}
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

                        <div className="flex items-start gap-3 border-t border-ink/10 py-4">
                            <CustomCheckbox
                                id="terms_accepted"
                                checked={data.terms_accepted}
                                onCheckedChange={(checked) =>
                                    setData('terms_accepted', checked === true)
                                }
                            />
                            <div className="grid gap-1.5 leading-none">
                                <Label
                                    htmlFor="terms_accepted"
                                    className="mb-0 cursor-pointer text-sm leading-normal font-normal tracking-normal text-ink-soft normal-case"
                                >
                                    J'accepte la{' '}
                                    <Link
                                        href="/privacy"
                                        className="text-ink underline underline-offset-4"
                                    >
                                        politique de confidentialité
                                    </Link>{' '}
                                    et les{' '}
                                    <Link
                                        href="/terms"
                                        className="text-ink underline underline-offset-4"
                                    >
                                        conditions d'utilisation
                                    </Link>
                                    .
                                </Label>
                                <InputError message={errors.terms_accepted} />
                            </div>
                        </div>
                    </div>

                    {/* ── Action ── */}
                    <div className="flex items-center justify-end border-t border-ink/15 pt-6">
                        <button
                            type="submit"
                            disabled={!canSubmit || processing}
                            className="inline-flex items-center gap-2 bg-terracotta px-7 py-3.5 text-sm text-cream transition-colors hover:bg-terracotta-deep disabled:opacity-50"
                        >
                            {processing ? 'Envoi…' : 'Lancer ma boutique'}
                            <ArrowRight className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </form>
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
        <label className="mb-3 block eyebrow">{label}</label>
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
