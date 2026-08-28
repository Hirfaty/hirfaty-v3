import { Link, router, usePage } from '@inertiajs/react';
import { Search, User, ShoppingBag, Menu, X } from 'lucide-react';
import { useState, useEffect } from 'react';
import CartSync from '@/components/CartSync';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';

import { useGuestCart } from '@/hooks/useGuestCart';
import logo from '@/images/logo.png';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

const accountLinkClass =
    'block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60';

const NAV = ['Shop All', 'New Arrivals', 'Journal', 'About'];

export function SiteHeader() {
    const [open, setOpen] = useState(false);

    return (
        <header className="sticky top-0 z-50 h-20 shrink-0 border-b border-border/70 bg-background/95 backdrop-blur-sm">
            <div className="mx-auto grid h-[76px] max-w-[1560px] grid-cols-[auto_1fr_auto] items-center gap-6 px-5 sm:px-8 lg:h-[88px] lg:px-14">
                <a
                    href="/"
                    className="flex min-w-0 items-center"
                    aria-label="Aura Studios home"
                >
                    <span className="display-title text-[1.35rem] leading-[0.86] tracking-[0.14em] text-foreground sm:text-[1.6rem]">
                        Hirfaty
                    </span>
                </a>

                <nav
                    className="hidden justify-center gap-10 lg:flex"
                    aria-label="Primary"
                >
                    {NAV.map((item) => (
                        <a
                            key={item}
                            href="#"
                            className="relative py-1 eyebrow text-foreground/80 transition-colors duration-300 after:absolute after:bottom-0 after:left-0 after:h-px after:w-0 after:bg-primary after:transition-all after:duration-300 hover:text-primary hover:after:w-full"
                        >
                            {item}
                        </a>
                    ))}
                </nav>
                <span className="lg:hidden" />

                <div className="flex shrink-0 items-center gap-4 sm:gap-6">
                    <button
                        type="button"
                        aria-label="Search"
                        className="text-foreground/75 transition-colors hover:text-primary"
                    >
                        <Search size={19} strokeWidth={1.3} />
                    </button>
                    <button
                        type="button"
                        aria-label="Account"
                        className="hidden text-foreground/75 transition-colors hover:text-primary sm:block"
                    >
                        <User size={19} strokeWidth={1.3} />
                    </button>
                    <button
                        type="button"
                        aria-label="Shopping bag, 2 items"
                        className="relative text-foreground/75 transition-colors hover:text-primary"
                    >
                        <ShoppingBag size={19} strokeWidth={1.3} />
                        <span className="absolute -top-2 -right-2 grid h-[17px] w-[17px] place-items-center rounded-full bg-accent text-[10px] font-medium text-accent-foreground">
                            2
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        aria-label={open ? 'Close menu' : 'Open menu'}
                        aria-expanded={open}
                        className="text-foreground/75 transition-colors hover:text-primary lg:hidden"
                    >
                        {open ? (
                            <X size={20} strokeWidth={1.3} />
                        ) : (
                            <Menu size={20} strokeWidth={1.3} />
                        )}
                    </button>
                </div>
            </div>

            {open && (
                <nav
                    className="border-t border-border/70 bg-background px-6 py-6 lg:hidden"
                    aria-label="Mobile"
                >
                    <ul className="flex flex-col gap-5">
                        {NAV.map((item) => (
                            <li key={item}>
                                <a
                                    href="#"
                                    className="eyebrow text-foreground/85"
                                >
                                    {item}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>
            )}
        </header>
    );
}

export default SiteHeader;
