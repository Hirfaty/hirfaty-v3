import { Instagram, Facebook, type LucideIcon } from 'lucide-react';

function PinterestIcon({ size = 17 }: { size?: number }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.4"
            aria-hidden="true"
        >
            <path d="M9 19c-.4 1.4-1 2.8-1 2.8M12 2a9 9 0 0 0-3.3 17.4c-.1-.8-.2-2 0-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.4 1.8-2.4.9 0 1.3.6 1.3 1.4 0 .9-.6 2.2-.9 3.4-.2 1 .5 1.9 1.5 1.9 1.9 0 3.2-2.4 3.2-5.2 0-2.1-1.5-3.7-4.1-3.7-3 0-4.8 2.2-4.8 4.6 0 .9.3 1.5.7 2 .2.2.2.3.1.6l-.2.8c-.1.3-.3.4-.5.2-1.2-.5-1.8-2-1.8-3.6 0-2.7 2.3-6 6.8-6 3.6 0 6 2.6 6 5.4 0 3.7-2 6.4-5 6.4-1 0-2-.5-2.3-1.2" />
        </svg>
    );
}

const SOCIALS: { label: string; Icon: LucideIcon | typeof PinterestIcon }[] = [
    { label: 'Instagram', Icon: Instagram },
    { label: 'Pinterest', Icon: PinterestIcon },
    { label: 'Facebook', Icon: Facebook },
];

export function Footer() {
    return (
        <footer className="bg-ink text-background/85">
            <div className="mx-auto grid max-w-[1560px] gap-10 px-5 py-14 sm:px-8 lg:grid-cols-3 lg:gap-8 lg:px-14 lg:py-16">
                <nav aria-label="Footer">
                    <ul className="space-y-3 text-[0.78rem]">
                        {['Help', 'Shipping', 'Contact', 'Terms'].map((l) => (
                            <li key={l}>
                                <a
                                    href="#"
                                    className="transition-opacity hover:opacity-70"
                                >
                                    {l}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="lg:text-center">
                    <p className="eyebrow opacity-60">Social Us</p>
                    <div className="mt-4 flex gap-5 lg:justify-center">
                        {SOCIALS.map(({ label, Icon }) => (
                            <a
                                key={label}
                                href="#"
                                aria-label={label}
                                className="transition-opacity hover:opacity-70"
                            >
                                <Icon size={17} />
                            </a>
                        ))}
                    </div>
                </div>

                <div>
                    <p className="eyebrow opacity-60">Stay Connected</p>
                    <form
                        className="mt-4 flex max-w-sm items-center gap-2"
                        onSubmit={(e) => e.preventDefault()}
                    >
                        <label htmlFor="footer-email" className="sr-only">
                            Email address
                        </label>
                        <input
                            id="footer-email"
                            type="email"
                            required
                            placeholder="Enter your email"
                            className="min-w-0 flex-1 border-b border-background/30 bg-transparent pb-2 text-[0.8rem] placeholder:text-background/45 focus:border-secondary focus:outline-none"
                        />
                        <button
                            type="submit"
                            className="shrink-0 rounded-md bg-secondary px-4 py-2.5 eyebrow text-ink transition-colors hover:bg-primary hover:text-primary-foreground"
                        >
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>

            <div className="border-t border-background/10">
                <p className="mx-auto max-w-[1560px] px-5 py-5 text-[0.7rem] opacity-50 sm:px-8 lg:px-14">
                    © {new Date().getFullYear()} Aura Studios — made slowly, by
                    hand.
                </p>
            </div>
        </footer>
    );
}
