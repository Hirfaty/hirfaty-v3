Design System Overview

Hirfaty’s visual identity is inspired by:

Moroccan craftsmanship
Editorial luxury layouts
Warm paper textures
Terracotta & clay materials
Minimalist artisan marketplaces

The UI combines:

soft organic colors
serif editorial typography
restrained ornamentation
modern accessibility principles

The goal is to make products feel:

handmade
premium
authentic
cultural without becoming visually noisy
Typography
Primary Sans Font
--font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;

Used for:

UI
buttons
forms
navigation
dashboards
small labels

Why:

clean readability
modern SaaS feel
balances decorative Moroccan visuals
Editorial Display Font
--font-display: 'Fraunces', 'Times New Roman', serif;

Used for:

hero titles
artisan storytelling
section headings
luxury product presentation

Supporting classes:

.font-display
.h-editorial

Characteristics:

elegant
slightly dramatic
high-end magazine aesthetic
Arabic Font
--font-arabic: 'Noto Naskh Arabic', serif;

Used for:

Arabic product names
cultural quotes
bilingual UI sections

Why:

traditional calligraphic feel
highly readable
culturally aligned
Color Philosophy

The palette is intentionally warm and earthy.

Inspired by:

terracotta pottery
Moroccan clay walls
sand
olive tones
aged paper
Core Brand Colors
Cream / Paper Backgrounds
--cream
--cream-deep
--paper

Purpose:

soft reading experience
avoids harsh pure white
resembles artisan paper textures
Ink Colors
--ink
--ink-soft

Purpose:

readable typography
softer than pure black
editorial appearance
Terracotta
--terracotta
--terracotta-deep

Primary brand identity.

Used for:

CTA buttons
highlights
active states
branding

Represents:

pottery
handcrafted clay
warmth
Moroccan architecture
Supporting Earth Tones
--olive
--clay
--sand

Used subtly for:

decorative backgrounds
cards
section accents
artisan visual atmosphere
Semantic Tokens

The design system maps brand colors into semantic UI tokens:

--background
--foreground
--primary
--secondary
--muted
--accent
--card
--border

Benefits:

consistent theming
easier dark mode support
reusable component styling
compatibility with shadcn/ui
Dark Mode

Dark mode uses:

deep ink backgrounds
warm cream text
muted borders

Goal:

maintain artisan warmth
avoid cold blue/gray dark themes
preserve premium editorial feel

Defined in:

.dark { ... }
Layout & Spacing
Container
@utility container

Applies:

horizontal breathing room
large editorial spacing
premium layout rhythm

The design intentionally avoids cramped layouts.

Decorative Language

Decoration is restrained.

The platform avoids:

excessive gradients
heavy shadows
over-ornamentation

Instead it uses:

texture
typography
spacing
subtle geometric references
Paper Grain Effect
.paper-grain

Creates:

warm atmospheric background
subtle artisan feel
layered radial color wash

Purpose:

simulate paper/craft surfaces
add depth without distraction
Zellige Inspiration

Moroccan zellige patterns are integrated carefully.

Background Pattern
.zellige-bg

Uses:

very low opacity
blend mode
large scale repetition

Purpose:

cultural atmosphere
not visual dominance
Decorative Divider
.zellige-divider

Creates:

geometric artisan separators
subtle handcrafted motif

Used sparingly between sections.

Utility Fill Classes
Clay Fill
.clay-fill

Soft earthy backgrounds.

Sand Fill
.sand-fill

Warm neutral sections.

Ink Fill
.ink-fill

Dark editorial blocks.

Terracotta Fill
.terracotta-fill

Primary branded emphasis areas.

Animations

Animations are intentionally subtle and elegant.

Defined:

accordion-down
accordion-up
fade-up

Purpose:

smooth interactions
preserve premium feeling
avoid aggressive motion

Example:

--animate-fade-up
Borders & Rules

Rules imitate magazine/editorial separators.

.rule
.rule-strong

Purpose:

visual hierarchy
section separation
elegant structure
Label Styling

Labels use:

uppercase typography
increased tracking
tiny editorial sizing
label {
@apply text-[11px] tracking-[0.18em] uppercase;
}

Inspired by:

luxury packaging
museum captions
artisan catalog systems
Overall Design Principles

1. Warm Minimalism

Minimal but never sterile.

2. Editorial Commerce

The marketplace should feel like:

an artisan journal
a curated gallery
not a generic ecommerce dashboard 3. Cultural Subtlety

Moroccan identity should be:

elegant
atmospheric
modern

Never:

cliché
overloaded
touristy 4. Handmade Feeling

Spacing, textures, and colors should evoke:

craftsmanship
patience
authenticity 5. Calm Interface

The UI should feel:

breathable
slow
intentional

Avoid:

aggressive contrast
excessive animations
crowded interfaces
