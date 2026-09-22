<?php

/*
 * The transactional-email palette and type stack, in one place.
 *
 * Lives in config rather than a stylesheet because email has no stylesheet:
 * every colour below is written *inline* on the element that uses it (see
 * resources/views/components/mail/). Gmail strips <style> blocks in several
 * common cases — forwarded mail, the mobile app on a non-Gmail account — and a
 * layout that depends on CSS classes arrives as unstyled text when it does.
 *
 * Deliberately small, and deliberately flat. There is one accent (terracotta)
 * and one negative tone; everything else is a neutral. Earlier templates each
 * carried their own palette, which drifted into greens, ambers and a dozen
 * rgba() values — and rgba() is itself unsupported by Outlook's Word renderer,
 * so those simply vanished there.
 */
return [
    /*
     * Websafe only. Mail clients do not reliably load webfonts, so the brand's
     * Didot is not an option here; Georgia keeps the editorial tone everywhere.
     * Arabic has no Georgia glyphs — mirrors the [lang='ar'] overrides in
     * app.css.
     */
    'font' => "Georgia, 'Times New Roman', serif",
    'font_rtl' => 'Tahoma, Arial, sans-serif',

    // Text
    'ink' => '#2B2118',   // headings and emphasis
    'soft' => '#6B6259',   // body copy
    'muted' => '#9A9086',   // footer, captions

    // The single brand accent: rules, the logo stop, buttons.
    'accent' => '#C1522A',

    /*
     * Negative states only (cancelled, rejected). A muted brick rather than a
     * signal red — it has to sit beside the terracotta without shouting.
     */
    'danger' => '#9C3B28',

    // Surfaces
    'line' => '#E6DFD4',   // hairlines and borders
    'paper' => '#FFFFFF',   // the card
    'page' => '#F5F1EA',   // behind the card, and filled panels
];
