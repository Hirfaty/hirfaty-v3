import { Star } from 'lucide-react';
import { useState } from 'react';

/**
 * A 1–5 star input.
 *
 * Real radio inputs behind the stars rather than a row of buttons: this is a
 * single choice from a fixed set, which is what a radio group *is*. That buys
 * arrow-key navigation, correct announcement as "2 of 5", and form semantics
 * for free — all of which a div-with-click-handlers would have to reimplement
 * badly.
 *
 * The stars themselves are drawn on the label, and the input is visually
 * hidden rather than `display: none` so it can still take focus.
 */
export default function StarRating({
    value,
    onChange,
    name,
    label,
    starLabel,
}: {
    value: number;
    onChange: (rating: number) => void;
    /** Must be unique per group on the page — it's the radio `name`. */
    name: string;
    /** Accessible name for the whole group, e.g. "Rate this piece". */
    label: string;
    /** Builds each star's label, e.g. (3) => "3 stars". */
    starLabel: (rating: number) => string;
}) {
    // Preview on hover, without committing until the click actually lands.
    const [preview, setPreview] = useState<number | null>(null);
    const shown = preview ?? value;

    return (
        <fieldset
            className="flex items-center gap-1"
            onMouseLeave={() => setPreview(null)}
        >
            <legend className="sr-only">{label}</legend>

            {[1, 2, 3, 4, 5].map((rating) => (
                <label
                    key={rating}
                    onMouseEnter={() => setPreview(rating)}
                    className="cursor-pointer p-0.5"
                >
                    <input
                        type="radio"
                        name={name}
                        value={rating}
                        checked={value === rating}
                        onChange={() => onChange(rating)}
                        className="peer sr-only"
                    />
                    <Star
                        // `fill` rather than colour alone: on a small star the
                        // difference between a filled and an outlined glyph
                        // survives being resized and reprinted in a way a
                        // colour swap on its own does not.
                        className={`h-6 w-6 transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-souk-brown peer-focus-visible:ring-offset-2 ${
                            rating <= shown
                                ? 'fill-terracotta text-terracotta'
                                : 'fill-transparent text-souk-brown/30'
                        }`}
                        strokeWidth={1.5}
                    />
                    <span className="sr-only">{starLabel(rating)}</span>
                </label>
            ))}
        </fieldset>
    );
}
