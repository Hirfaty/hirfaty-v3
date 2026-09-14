import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Check,
    Hammer,
    MapPin,
    Store as StoreIcon,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import heroMain from '@/images/hero-main.jpg';
import heroTextile from '@/images/hero-textile.jpg';
import heroZellige from '@/images/hero-zellige.jpg';
import logo from '@/images/logo.png';
import zellij from '@/images/zellij-pattern.png';

type Option = { id: number; name: string };
/** Mirrors Store::TYPES. */
type StoreType = 'artist' | 'company' | 'cooperative';

interface Props {
    categories: Option[];
    cities: Option[];
    types: StoreType[];
}

type StepId = 'identity' | 'craft' | 'story';

const STEPS: StepId[] = ['identity', 'craft', 'story'];

/** Artwork per step, so the panel moves with the story rather than sitting still. */
const STEP_IMAGE: Record<StepId, string> = {
    identity: heroMain,
    craft: heroZellige,
    story: heroTextile,
};

const TYPE_ICON: Record<StoreType, ComponentType<{ className?: string }>> = {
    artist: Hammer,
    company: Building2,
    cooperative: Users,
};

/**
 * Which step owns which field, so a server-side error can pull the seller back
 * to the step that caused it. Without this a rejected `store_name` (the unique
 * rule fires only on the server) would render its message on a step that is no
 * longer on screen, and the submit button would just look broken.
 */
const FIELD_STEP: Record<string, StepId> = {
    store_name: 'identity',
    type: 'identity',
    // Consent lives on step one because it is what gates opening the shop at
    // all, and the shop can now be opened from step one.
    terms_accepted: 'identity',
    category_id: 'craft',
    city_id: 'craft',
    description: 'story',
};

/** Kept in step with the server's max:2500 in StoreSetupController. */
const DESCRIPTION_MAX = 2500;

export default function SetupStore({ categories, cities, types }: Props) {
    const { t } = useTranslation();
    const [stepIndex, setStepIndex] = useState(0);
    const current = STEPS[stepIndex];
    const isLast = stepIndex === STEPS.length - 1;

    /*
     * Craft and city start empty rather than pre-selected. They are optional
     * now, and defaulting them would file a shop under "Poterie / Agadir"
     * purely because those sort first — wrong data in the buyer-facing
     * filters, and invisible to the seller who skipped the step.
     */
    const { data, setData, post, processing, errors } = useForm({
        store_name: '',
        type: (types[0] ?? 'artist') as StoreType,
        category_id: null as number | null,
        city_id: null as number | null,
        description: '',
        terms_accepted: false,
    });

    // Jump to whichever step holds the first rejected field — see FIELD_STEP.
    useEffect(() => {
        const firstBadField = Object.keys(errors)[0];
        const target = firstBadField ? FIELD_STEP[firstBadField] : undefined;

        if (target) {
            setStepIndex(STEPS.indexOf(target));
        }
    }, [errors]);

    /*
     * Everything the server insists on: a name, a type (always set), and
     * consent. Once these hold the shop can be created from any step, which
     * is what makes "finish later" possible rather than a dead end.
     */
    const canCreate = data.store_name.trim().length > 1 && data.terms_accepted;

    /*
     * Per-step gate for the primary button. Craft and city are optional, so
     * step two never blocks — a seller with no idea which category fits can
     * still move on.
     */
    const canContinue = {
        identity: canCreate,
        craft: true,
        story: true,
    }[current];

    const submit = () => post(route('seller.setup.store'));

    return (
        <>
            <Head title={t('seller.store_setup')} />

            <div className="min-h-screen bg-souk-paper lg:grid lg:h-screen lg:grid-cols-[0.85fr_1.15fr]">
                {/* ── Artwork panel (desktop only) ───────────────────── */}
                <aside className="relative hidden overflow-hidden bg-souk-brown lg:flex lg:h-screen lg:flex-col">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.08] mix-blend-screen"
                        style={{
                            backgroundImage: `url(${zellij})`,
                            backgroundSize: '320px',
                        }}
                    />

                    <div className="relative shrink-0 px-10 pt-10">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-3"
                        >
                            <img
                                src={logo}
                                alt=""
                                className="h-8 w-8 object-contain"
                            />
                            <span className="font-display text-xl text-white">
                                Hirfaty
                                <span className="text-souk-cream">.</span>
                            </span>
                        </Link>
                    </div>

                    <div className="relative flex min-h-0 flex-1 items-center justify-center px-10 py-8">
                        <div className="relative h-full max-h-[24rem]">
                            <div
                                aria-hidden="true"
                                className="absolute -top-4 -right-4 h-full w-full rounded-t-full rounded-bl-2xl border-2 border-souk-cream/40"
                            />
                            <div className="relative aspect-[4/5] h-full overflow-hidden rounded-t-full rounded-bl-2xl shadow-2xl shadow-black/30">
                                <img
                                    key={current}
                                    src={STEP_IMAGE[current]}
                                    alt=""
                                    className="h-full w-full object-cover motion-safe:animate-fade-up"
                                />
                                <div
                                    aria-hidden="true"
                                    className="absolute inset-0 bg-gradient-to-t from-souk-brown/50 via-transparent to-transparent"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="relative shrink-0 px-10 pb-10">
                        <p
                            key={current}
                            className="max-w-md font-display text-xl leading-snug text-souk-cream motion-safe:animate-fade-up"
                        >
                            {t(`seller.setup.${current}.quote`)}
                        </p>
                    </div>
                </aside>

                {/* ── Content panel ─────────────────────────────────── */}
                <main className="flex min-h-screen flex-col px-6 py-8 sm:px-10 lg:h-screen lg:min-h-0 lg:overflow-y-auto lg:px-16 lg:py-10">
                    {/* Progress */}
                    <div className="flex items-center justify-between gap-6">
                        <div className="flex items-center gap-2">
                            {STEPS.map((s, i) => (
                                <span
                                    key={s}
                                    className={`h-1 rounded-full transition-all duration-300 ${
                                        i <= stepIndex
                                            ? 'w-8 bg-terracotta'
                                            : 'w-4 bg-souk-brown/20'
                                    }`}
                                />
                            ))}
                        </div>

                        <p className="text-xs text-souk-brown-deep/50">
                            {t('seller.setup.skip_hint')}
                        </p>
                    </div>

                    <div className="flex flex-1 flex-col justify-center py-10">
                        <div
                            key={current}
                            className="motion-safe:animate-fade-up"
                        >
                            <p className="text-[11px] tracking-[0.2em] text-souk-brown/70 uppercase">
                                {t('seller.setup.eyebrow')}
                            </p>

                            <h1 className="mt-3 font-display text-[clamp(1.7rem,3.2vw,2.6rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                                {t(`seller.setup.${current}.title`)}
                            </h1>

                            <p className="mt-3 max-w-lg text-[15px] leading-relaxed text-souk-brown-deep/70">
                                {t(`seller.setup.${current}.body`)}
                            </p>

                            {/* ── Step 1: identity ──────────────────── */}
                            {current === 'identity' && (
                                <section className="mt-8 space-y-7">
                                    <div>
                                        <label
                                            htmlFor="store_name"
                                            className="mb-1.5 block text-xs text-souk-brown-deep/60"
                                        >
                                            {t(
                                                'seller.setup.identity.name_label',
                                            )}
                                        </label>
                                        <div className="relative">
                                            <StoreIcon
                                                className="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-souk-brown-deep/35"
                                                strokeWidth={1.6}
                                            />
                                            <input
                                                id="store_name"
                                                autoFocus
                                                value={data.store_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'store_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'seller.setup.identity.name_placeholder',
                                                )}
                                                className="w-full rounded-xl border border-souk-brown/25 bg-white py-3 ps-11 pe-4 text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown focus:ring-2 focus:ring-souk-brown/10 focus:outline-none"
                                            />
                                        </div>
                                        <p className="mt-1.5 text-[11px] text-souk-brown-deep/45">
                                            {t(
                                                'seller.setup.identity.name_hint',
                                            )}
                                        </p>
                                        <InputError
                                            message={errors.store_name}
                                            className="mt-1.5"
                                        />
                                    </div>

                                    <fieldset>
                                        <legend className="mb-2 text-xs text-souk-brown-deep/60">
                                            {t(
                                                'seller.setup.identity.type_label',
                                            )}
                                        </legend>
                                        <div className="grid gap-2.5 sm:grid-cols-3">
                                            {types.map((type) => {
                                                const Icon = TYPE_ICON[type];
                                                const active =
                                                    data.type === type;

                                                return (
                                                    <label
                                                        key={type}
                                                        className={`flex cursor-pointer flex-col gap-2 rounded-xl border p-4 transition-colors ${
                                                            active
                                                                ? 'border-terracotta bg-terracotta/5'
                                                                : 'border-souk-brown/20 hover:border-souk-brown/45'
                                                        }`}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="type"
                                                            value={type}
                                                            checked={active}
                                                            onChange={() =>
                                                                setData(
                                                                    'type',
                                                                    type,
                                                                )
                                                            }
                                                            className="sr-only"
                                                        />
                                                        <span className="flex items-center justify-between">
                                                            <Icon
                                                                className={`h-5 w-5 ${active ? 'text-terracotta' : 'text-souk-brown-deep/40'}`}
                                                            />
                                                            {active && (
                                                                <Check
                                                                    className="h-4 w-4 text-terracotta"
                                                                    strokeWidth={
                                                                        2.5
                                                                    }
                                                                />
                                                            )}
                                                        </span>
                                                        <span className="leading-tight">
                                                            <span
                                                                className={`block text-sm ${active ? 'text-souk-title' : 'text-souk-brown-deep/80'}`}
                                                            >
                                                                {t(
                                                                    `seller.setup.type_${type}`,
                                                                )}
                                                            </span>
                                                            <span className="mt-0.5 block text-[11px] text-souk-brown-deep/50">
                                                                {t(
                                                                    `seller.setup.type_${type}_hint`,
                                                                )}
                                                            </span>
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                        <InputError
                                            message={errors.type}
                                            className="mt-1.5"
                                        />
                                    </fieldset>

                                    {/* Consent sits here, not on the last
                                        step: it gates creating the shop, and
                                        the shop can be created from here. */}
                                    <div className="rounded-2xl border border-souk-brown/15 bg-souk-cream/40 p-5">
                                        <label className="flex cursor-pointer items-start gap-3 select-none">
                                            <input
                                                type="checkbox"
                                                checked={data.terms_accepted}
                                                onChange={(e) =>
                                                    setData(
                                                        'terms_accepted',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="mt-0.5 h-4 w-4 shrink-0 accent-terracotta"
                                            />
                                            <span className="text-sm leading-snug text-souk-brown-deep/80">
                                                {t('seller.setup.terms_prefix')}{' '}
                                                <Link
                                                    href="/privacy"
                                                    className="text-souk-brown underline underline-offset-4"
                                                >
                                                    {t(
                                                        'seller.setup.terms_privacy',
                                                    )}
                                                </Link>{' '}
                                                {t('seller.setup.terms_and')}{' '}
                                                <Link
                                                    href="/terms"
                                                    className="text-souk-brown underline underline-offset-4"
                                                >
                                                    {t(
                                                        'seller.setup.terms_conditions',
                                                    )}
                                                </Link>
                                                .
                                            </span>
                                        </label>
                                        <InputError
                                            message={errors.terms_accepted}
                                            className="mt-2"
                                        />
                                    </div>
                                </section>
                            )}

                            {/* ── Step 2: craft & city ──────────────── */}
                            {current === 'craft' && (
                                <section className="mt-8 grid gap-6 sm:grid-cols-2">
                                    <div>
                                        <label
                                            htmlFor="category_id"
                                            className="mb-1.5 block text-xs text-souk-brown-deep/60"
                                        >
                                            {t(
                                                'seller.setup.craft.category_label',
                                            )}
                                        </label>
                                        <SelectField
                                            id="category_id"
                                            Icon={Hammer}
                                            value={data.category_id}
                                            onChange={(id) =>
                                                setData('category_id', id)
                                            }
                                            options={categories}
                                            unsetLabel={t(
                                                'seller.setup.craft.unset',
                                            )}
                                        />
                                        <InputError
                                            message={errors.category_id}
                                            className="mt-1.5"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="city_id"
                                            className="mb-1.5 block text-xs text-souk-brown-deep/60"
                                        >
                                            {t('seller.setup.craft.city_label')}
                                        </label>
                                        <SelectField
                                            id="city_id"
                                            Icon={MapPin}
                                            value={data.city_id}
                                            onChange={(id) =>
                                                setData('city_id', id)
                                            }
                                            options={cities}
                                            unsetLabel={t(
                                                'seller.setup.craft.unset',
                                            )}
                                        />
                                        <InputError
                                            message={errors.city_id}
                                            className="mt-1.5"
                                        />
                                    </div>
                                </section>
                            )}

                            {/* ── Step 3: story ─────────────────────── */}
                            {current === 'story' && (
                                <section className="mt-8 space-y-6">
                                    <div>
                                        <label
                                            htmlFor="description"
                                            className="mb-1.5 block text-xs text-souk-brown-deep/60"
                                        >
                                            {t('seller.setup.story.label')}
                                        </label>
                                        <textarea
                                            id="description"
                                            rows={7}
                                            autoFocus
                                            value={data.description}
                                            onChange={(e) =>
                                                setData(
                                                    'description',
                                                    e.target.value.slice(
                                                        0,
                                                        DESCRIPTION_MAX,
                                                    ),
                                                )
                                            }
                                            placeholder={t(
                                                'seller.setup.story.placeholder',
                                            )}
                                            className="w-full resize-y rounded-xl border border-souk-brown/25 bg-white px-4 py-3 text-sm leading-relaxed text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown focus:ring-2 focus:ring-souk-brown/10 focus:outline-none"
                                        />
                                        <div className="mt-1.5 flex items-start justify-between gap-4">
                                            <p className="text-[11px] text-souk-brown-deep/45">
                                                {t('seller.setup.story.hint')}
                                            </p>
                                            <span className="shrink-0 text-[11px] text-souk-brown-deep/45 tabular-nums">
                                                {data.description.length}/
                                                {DESCRIPTION_MAX}
                                            </span>
                                        </div>
                                        <InputError
                                            message={errors.description}
                                            className="mt-1.5"
                                        />
                                    </div>
                                </section>
                            )}
                        </div>
                    </div>

                    {/* ── Nav ───────────────────────────────────────── */}
                    <div className="flex items-center justify-between gap-4 border-t border-souk-brown/12 pt-6">
                        {stepIndex > 0 ? (
                            <button
                                type="button"
                                onClick={() => setStepIndex((i) => i - 1)}
                                className="group inline-flex items-center gap-2 text-sm text-souk-brown-deep/65 transition-colors hover:text-souk-brown"
                            >
                                <ArrowLeft
                                    className="h-4 w-4 transition-transform group-hover:-translate-x-0.5 rtl:rotate-180"
                                    strokeWidth={1.5}
                                />
                                {t('seller.setup.back')}
                            </button>
                        ) : (
                            <span />
                        )}

                        <div className="flex flex-wrap items-center justify-end gap-x-5 gap-y-3">
                            {/*
                                The way out. Creates the shop from whatever is
                                filled in and hands the seller their dashboard —
                                the rest is editable from settings. Hidden on
                                the final step, where the primary button already
                                does exactly this.
                             */}
                            {!isLast && (
                                <button
                                    type="button"
                                    disabled={processing || !canCreate}
                                    onClick={submit}
                                    className="text-sm text-souk-brown-deep/60 underline-offset-4 transition-colors hover:text-souk-brown hover:underline disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:no-underline"
                                >
                                    {t('seller.setup.finish_later')}
                                </button>
                            )}

                            <button
                                type="button"
                                disabled={processing || !canContinue}
                                onClick={() =>
                                    isLast
                                        ? submit()
                                        : setStepIndex((i) => i + 1)
                                }
                                className="group inline-flex items-center gap-2 rounded-full bg-souk-brown px-7 py-3 text-sm text-souk-paper transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {processing
                                    ? t('seller.setup.submitting')
                                    : isLast
                                      ? t('seller.setup.finish')
                                      : t('seller.setup.next')}
                                <ArrowRight
                                    className="h-4 w-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180"
                                    strokeWidth={1.6}
                                />
                            </button>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}

function SelectField({
    id,
    Icon,
    value,
    onChange,
    options,
    unsetLabel,
}: {
    id: string;
    Icon: ComponentType<{ className?: string; strokeWidth?: number }>;
    value: number | null;
    onChange: (id: number | null) => void;
    options: Option[];
    /** Copy for the empty choice, which maps back to a null column. */
    unsetLabel: string;
}) {
    return (
        <div className="relative">
            <Icon
                className="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-souk-brown-deep/35"
                strokeWidth={1.6}
            />
            <select
                id={id}
                value={value ?? ''}
                onChange={(e) =>
                    onChange(
                        e.target.value === '' ? null : Number(e.target.value),
                    )
                }
                className="w-full appearance-none rounded-xl border border-souk-brown/25 bg-white py-3 ps-11 pe-10 text-sm text-souk-title focus:border-souk-brown focus:ring-2 focus:ring-souk-brown/10 focus:outline-none"
            >
                <option value="">{unsetLabel}</option>
                {options.map((o) => (
                    <option key={o.id} value={o.id}>
                        {o.name}
                    </option>
                ))}
            </select>
            <span
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-souk-brown-deep/40"
            >
                ▾
            </span>
        </div>
    );
}
