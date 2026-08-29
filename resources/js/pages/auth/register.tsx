import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState, useMemo } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import SiteHeader from '@/components/SiteHeader';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import buyerImage from '@/images/cat-beauty.jpg';
import sellerImage from '@/images/cat-pottery.jpg';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Role = 'buyer' | 'seller';

export default function Register() {
    const [role, setRole] = useState<Role>(() => {
        if (typeof window === 'undefined') {
            return 'buyer';
        }

        const type = new URLSearchParams(window.location.search)
            .get('type')
            ?.toLowerCase();

        return type === 'seller' ? 'seller' : 'buyer';
    });

    const copy = useMemo(
        () =>
            role === 'buyer'
                ? {
                      eyebrow: 'Créer un compte',
                      title: (
                          <>
                              Découvrez l’artisanat
                              <br />
                              <span className="text-terracotta italic">
                                  qui vous ressemble
                              </span>
                              .
                          </>
                      ),
                      sub: 'Suivez vos artisans préférés, sauvegardez des pièces, commandez en quelques clics.',
                      cta: 'Créer mon compte',
                      fillVariant: 'textile' as const,
                  }
                : {
                      eyebrow: 'Devenir vendeur',
                      title: (
                          <>
                              Ouvrez votre{' '}
                              <span className="text-terracotta italic">
                                  atelier
                              </span>
                              <br />
                              sur Hirfaty.
                          </>
                      ),
                      sub: 'Présentez votre savoir-faire à une audience qui cherche l’authentique.',
                      cta: 'Continuer vers ma boutique',
                      fillVariant: 'pottery' as const,
                  },
        [role],
    );

    return (
        <>
            <SiteHeader />
            <Head title="Register" />
            <div className="paper-grain min-h-screen bg-cream">
                <section className="container pt-12 pb-10 md:pt-16">
                    <div className="grid grid-cols-12 items-end gap-6">
                        <div className="col-span-12 md:col-span-8">
                            <p className="eyebrow mb-3">{copy.eyebrow}</p>
                            <h1 className="h-editorial text-4xl leading-[1.05] text-ink md:text-6xl">
                                {copy.title}
                            </h1>
                        </div>
                        <p className="col-span-12 text-base leading-relaxed text-ink-soft md:col-span-4 md:text-right">
                            {copy.sub}
                        </p>
                    </div>
                </section>

                <section className="container pb-24">
                    <div className="grid grid-cols-12 gap-8 border-t border-ink/15 pt-10 lg:gap-12">
                        {/* Left: editorial side panel */}
                        <aside className="hidden lg:col-span-5 lg:block">
                            <div className="sticky top-24">
                                <div className="relative h-[calc(100vh-7rem)] overflow-hidden rounded-[2rem] shadow-xl shadow-ink/10">
                                    <img
                                        src={
                                            role === 'buyer'
                                                ? buyerImage
                                                : sellerImage
                                        }
                                        alt={
                                            role === 'buyer'
                                                ? 'Buyer registration artwork'
                                                : 'Seller registration artwork'
                                        }
                                        className="absolute inset-0 h-full w-full object-cover"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-ink/90 via-ink/10 to-transparent" />
                                </div>
                                <p className="mt-6 max-w-sm text-sm leading-relaxed text-ink-soft">
                                    {role === 'buyer' ? (
                                        <>
                                            « Chaque pièce a une main, une
                                            ville, une histoire. Notre rôle est
                                            juste de vous mettre en relation. »
                                        </>
                                    ) : (
                                        <>
                                            « Hirfaty appartient aux artisans.
                                            Aucun frais d’ouverture, vous gardez
                                            le contrôle de votre boutique. »
                                        </>
                                    )}
                                </p>
                            </div>
                        </aside>

                        {/* Right: form */}
                        <div className="col-span-12 mt-10 lg:col-span-7">
                            {/* Role toggle — typographic, segmented, no pill */}
                            <div
                                role="tablist"
                                aria-label="Type de compte"
                                className="mb-10 grid grid-cols-2 border border-ink/20 bg-paper"
                            >
                                {(['buyer', 'seller'] as Role[]).map((r) => {
                                    const active = role === r;

                                    return (
                                        <button
                                            key={r}
                                            role="tab"
                                            aria-selected={active}
                                            onClick={() => setRole(r)}
                                            className={`relative px-5 py-4 text-left transition-colors ${
                                                active
                                                    ? 'bg-ink text-cream'
                                                    : 'text-ink hover:bg-cream-deep/60'
                                            }`}
                                        >
                                            <span className="block text-[10px] tracking-[0.18em] uppercase opacity-70">
                                                {r === 'buyer'
                                                    ? '01 — Particulier'
                                                    : '02 — Artisan'}
                                            </span>
                                            <span className="mt-1 block font-display text-xl">
                                                {r === 'buyer'
                                                    ? 'S’inscrire comme acheteur'
                                                    : 'S’inscrire comme vendeur'}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            <Form
                                {...store.form()}
                                resetOnSuccess={[
                                    'password',
                                    'password_confirmation',
                                ]}
                                disableWhileProcessing
                                className="flex flex-col gap-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="role"
                                            value={role}
                                        />
                                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <div>
                                                <Label htmlFor="first_name">
                                                    First Name
                                                </Label>
                                                <Input
                                                    id="first_name"
                                                    type="text"
                                                    required
                                                    autoFocus
                                                    tabIndex={1}
                                                    autoComplete="given-name"
                                                    name="first_name"
                                                    placeholder="First name"
                                                />
                                                <InputError
                                                    message={errors.first_name}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="last_name">
                                                    Last Name
                                                </Label>
                                                <Input
                                                    id="last_name"
                                                    type="text"
                                                    required
                                                    tabIndex={2}
                                                    autoComplete="family-name"
                                                    name="last_name"
                                                    placeholder="Last name"
                                                />
                                                <InputError
                                                    message={errors.last_name}
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <div>
                                                <Label htmlFor="phone_number">
                                                    Phone number
                                                </Label>
                                                <Input
                                                    id="phone_number"
                                                    type="tel"
                                                    required
                                                    tabIndex={3}
                                                    autoComplete="tel"
                                                    name="phone_number"
                                                    placeholder="+212 6 12 34 56 78"
                                                />
                                                <InputError
                                                    message={
                                                        errors.phone_number
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="email">
                                                    Email address
                                                </Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    required
                                                    tabIndex={4}
                                                    autoComplete="email"
                                                    name="email"
                                                    placeholder="email@example.com"
                                                />
                                                <InputError
                                                    message={errors.email}
                                                />
                                            </div>
                                        </div>
                                        {/* <Input
                                            id="email"
                                            type="email"
                                            required
                                            tabIndex={3}
                                            autoComplete="email"
                                            name="email"
                                            placeholder="email@example.com"
                                        />
                                        <InputError message={errors.email} /> */}

                                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <div>
                                                <Label htmlFor="password">
                                                    Password
                                                </Label>
                                                <PasswordInput
                                                    id="password"
                                                    required
                                                    tabIndex={4}
                                                    autoComplete="new-password"
                                                    name="password"
                                                    placeholder="Password"
                                                />
                                                <InputError
                                                    message={errors.password}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="password_confirmation">
                                                    Confirm password
                                                </Label>
                                                <PasswordInput
                                                    id="password_confirmation"
                                                    required
                                                    tabIndex={5}
                                                    autoComplete="new-password"
                                                    name="password_confirmation"
                                                    placeholder="Confirm password"
                                                />
                                                <InputError
                                                    message={
                                                        errors.password_confirmation
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="flex items-start gap-3 py-2">
                                            <Checkbox
                                                id="terms_accepted"
                                                name="terms_accepted"
                                                required
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
                                                        politique de
                                                        confidentialité
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
                                                <InputError
                                                    message={
                                                        errors.terms_accepted
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="flex flex-col-reverse gap-4 pt-2 md:flex-row md:items-center md:justify-between">
                                            <p className="text-sm text-ink-soft">
                                                Vous avez déjà un compte ?{' '}
                                                <Link
                                                    href="/login"
                                                    className="text-ink underline underline-offset-4"
                                                >
                                                    Se connecter
                                                </Link>
                                            </p>

                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="group inline-flex items-center justify-between gap-6 bg-ink px-6 py-4 text-cream transition-colors hover:bg-terracotta disabled:opacity-60"
                                            >
                                                <span className="font-display text-base">
                                                    {processing ? (
                                                        <Spinner className="mr-2 inline-block h-4 w-4" />
                                                    ) : null}
                                                    {copy.cta}
                                                </span>
                                                <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" />
                                            </button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>
                    </div>
                </section>
            </div>

            {/* <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="first_name">First Name</Label>
                                <Input
                                    id="first_name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="first_name"
                                    placeholder="First name"
                                />
                                <InputError
                                    message={errors.first_name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="last_name">Last Name</Label>
                                <Input
                                    id="last_name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="last_name"
                                    placeholder="Last name"
                                />
                                <InputError
                                    message={errors.last_name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>
                        <input type="text" value={'seller'} name="role" />

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={6}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form> */}
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
