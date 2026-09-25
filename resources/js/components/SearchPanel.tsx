import { Link, router } from '@inertiajs/react';
import { Loader2, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import { useTranslation } from '@/hooks/use-translation';

/** Below this the panel shows a hint rather than querying — see ProductSearchService::MIN_TERM_LENGTH. */
const MIN_TERM_LENGTH = 2;

/** Keystrokes settle before the request goes out. */
const DEBOUNCE_MS = 250;

type Suggestion = {
    id: number;
    name: string;
    slug: string;
    /** Null for a product whose price has not been agreed yet. */
    price: number | null;
    image: string | null;
    store_name: string | null;
    category_name: string | null;
};

type SearchPanelProps = {
    onClose: () => void;
};

/**
 * The header's search: a sheet that drops out of the bar, with live results
 * under the field.
 *
 * Results come from a plain `fetch` rather than an Inertia visit — this fires
 * every few keystrokes and only repaints the dropdown, so it must not push a
 * history entry or re-render the page underneath. Committing the search (Enter
 * on the field, or "see all results") *is* an Inertia visit, to the listing.
 *
 * Mounted only while open, by the header — closing therefore drops the term
 * and its results with the component, rather than needing them cleared.
 */
export const SearchPanel = ({ onClose }: SearchPanelProps) => {
    const { t, isRTL } = useTranslation();

    const [term, setTerm] = useState('');
    const [results, setResults] = useState<Suggestion[]>([]);
    const [total, setTotal] = useState(0);
    /**
     * The term the rows on screen belong to. Loading is derived from it
     * rather than kept as a flag of its own, so there is no state to set
     * synchronously as the shopper types.
     */
    const [loadedTerm, setLoadedTerm] = useState<string | null>(null);
    /** Index into `results` for keyboard navigation; -1 = the field itself. */
    const [highlighted, setHighlighted] = useState(-1);

    const trimmed = term.trim();
    const tooShort = trimmed.length < MIN_TERM_LENGTH;

    // Below the threshold nothing was ever fetched for this term, so the
    // previous term's rows must not linger under the field.
    const suggestions = tooShort ? [] : results;
    const loading = !tooShort && trimmed !== loadedTerm;

    useEffect(() => {
        if (tooShort) {
            return;
        }

        /*
         * Aborted on the next keystroke: without it a slow request for "taj"
         * can land after the one for "tajine" and overwrite the newer list.
         */
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(
                `${route('search.suggestions')}?q=${encodeURIComponent(trimmed)}`,
                {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                },
            )
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.json();
                })
                .then((data: { products: Suggestion[]; total: number }) => {
                    setResults(data.products);
                    setTotal(data.total);
                    setHighlighted(-1);
                    setLoadedTerm(trimmed);
                })
                .catch((error: unknown) => {
                    // An abort is the expected path on every keystroke, not a
                    // failure — only a real error should settle the panel.
                    if ((error as Error)?.name !== 'AbortError') {
                        setResults([]);
                        setTotal(0);
                        // Marked loaded regardless, otherwise a failed request
                        // leaves the spinner turning for ever.
                        setLoadedTerm(trimmed);
                    }
                });
        }, DEBOUNCE_MS);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [trimmed, tooShort]);

    // Escape closes from anywhere, including while the results list has focus.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [onClose]);

    /** Hand the term over to the listing, which owns the full result set. */
    const submit = (value: string) => {
        const query = value.trim();

        if (!query) {
            return;
        }

        onClose();
        router.get(route('products.index'), { search: query });
    };

    const openProduct = (product: Suggestion) => {
        onClose();
        router.visit(route('products.show', product.slug));
    };

    const onFieldKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown' && suggestions.length > 0) {
            event.preventDefault();
            setHighlighted((index) => (index + 1) % suggestions.length);

            return;
        }

        if (event.key === 'ArrowUp' && suggestions.length > 0) {
            event.preventDefault();
            setHighlighted((index) =>
                index <= 0 ? suggestions.length - 1 : index - 1,
            );

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();

            // Enter on a highlighted row opens that piece; Enter on the field
            // itself runs the full search.
            if (highlighted >= 0 && suggestions[highlighted]) {
                openProduct(suggestions[highlighted]);
            } else {
                submit(term);
            }
        }
    };

    return (
        <>
            {/*
             * Dims the page and closes on click. Sits under the panel but over
             * everything else, so the bar's own controls can't be hit blind
             * while the sheet is open.
             */}
            <button
                type="button"
                aria-label={t('header.search_close')}
                onClick={onClose}
                className="fixed inset-0 z-40 cursor-default bg-ink/25 backdrop-blur-[2px]"
            />

            <div className="absolute inset-x-0 top-full z-50 border-t border-ink/10 bg-cream shadow-lg shadow-ink/10">
                <div className="container py-6">
                    <div className="flex items-center gap-3 border-b border-ink/15 pb-3">
                        <Search
                            className="h-5 w-5 shrink-0 text-ink/40"
                            strokeWidth={1.5}
                            aria-hidden
                        />
                        <input
                            // Safe as an attribute rather than an effect: the
                            // panel only exists while it is open, so this runs
                            // exactly once, on the mount that opened it.
                            autoFocus
                            /*
                             * `text`, not `search`: Chrome decorates a search
                             * field with a clear "✕" of its own, which lands
                             * right beside the panel's close button and reads
                             * as two identical controls doing different things.
                             */
                            type="text"
                            inputMode="search"
                            autoComplete="off"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            onKeyDown={onFieldKeyDown}
                            placeholder={t('header.search_placeholder')}
                            aria-label={t('header.search')}
                            role="combobox"
                            aria-expanded={suggestions.length > 0}
                            aria-controls="search-suggestions"
                            className="w-full bg-transparent font-display text-lg text-ink placeholder:text-ink/35 focus:outline-none"
                        />
                        {loading && (
                            <Loader2
                                className="h-4 w-4 shrink-0 animate-spin text-ink/40"
                                aria-label={t('header.search_searching')}
                            />
                        )}
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label={t('header.search_close')}
                            className="shrink-0 rounded-t-full rounded-b-md p-2 text-ink/60 transition-colors hover:bg-sand/40 hover:text-ink"
                        >
                            <X className="h-4 w-4" strokeWidth={1.5} />
                        </button>
                    </div>

                    <div
                        className="pt-4"
                        id="search-suggestions"
                        role="listbox"
                    >
                        {tooShort ? (
                            <p className="py-6 text-center text-sm text-ink-soft">
                                {t('header.search_hint')}
                            </p>
                        ) : suggestions.length === 0 ? (
                            // The spinner in the field already says "working";
                            // don't flash "no results" underneath it.
                            !loading && (
                                <p className="py-6 text-center text-sm text-ink-soft">
                                    {t('header.search_empty').replace(
                                        ':term',
                                        trimmed,
                                    )}
                                </p>
                            )
                        ) : (
                            <>
                                <ul className="divide-y divide-ink/10">
                                    {suggestions.map((product, index) => (
                                        <li key={product.id}>
                                            <Link
                                                href={route(
                                                    'products.show',
                                                    product.slug,
                                                )}
                                                onClick={onClose}
                                                onMouseEnter={() =>
                                                    setHighlighted(index)
                                                }
                                                role="option"
                                                aria-selected={
                                                    highlighted === index
                                                }
                                                className={`flex items-center gap-4 px-2 py-3 transition-colors ${
                                                    highlighted === index
                                                        ? 'bg-sand/40'
                                                        : 'hover:bg-sand/25'
                                                }`}
                                            >
                                                <div className="h-14 w-14 shrink-0 overflow-hidden rounded-t-3xl rounded-b-lg border border-ink/10 bg-cream-deep/60">
                                                    {product.image ? (
                                                        <img
                                                            src={product.image}
                                                            alt={product.name}
                                                            loading="lazy"
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center">
                                                            <ZellijStarIcon className="h-4 w-4 text-terracotta/40" />
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate font-display text-base leading-tight text-ink">
                                                        {product.name}
                                                    </p>
                                                    <p className="mt-0.5 truncate text-xs text-ink-soft">
                                                        {[
                                                            product.store_name,
                                                            product.category_name,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                </div>

                                                {product.price !== null && (
                                                    <span className="shrink-0 font-display text-sm text-ink">
                                                        {product.price}{' '}
                                                        <span className="text-[10px]">
                                                            MAD
                                                        </span>
                                                    </span>
                                                )}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    type="button"
                                    onClick={() => submit(term)}
                                    className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-ink py-2.5 font-display text-sm text-cream transition-colors hover:bg-ink/90"
                                >
                                    {/* A single hit would otherwise read
                                        "see all 1 results". */}
                                    {(total === 1
                                        ? t('header.search_all_one')
                                        : t('header.search_all')
                                    ).replace(':count', String(total))}
                                    <span aria-hidden>{isRTL ? '←' : '→'}</span>
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
};

export default SearchPanel;
