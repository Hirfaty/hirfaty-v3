import { Form, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/site/SiteHeader';
import WarmFill from '@/components/WarmFill';
import { store } from '@/routes/login';

const Login = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(true);
    const [touched, setTouched] = useState(false);

    const emailValid = /\S+@\S+\.\S+/.test(email);
    const canSubmit = emailValid && password.length >= 8;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setTouched(true);

        if (!canSubmit) {
            return;
        }

        // No backend yet — route to browse as a placeholder.
        window.location.href = '/browse';
    };

    return (
        <div className="paper-grain min-h-screen bg-cream">
            <SiteHeader />

            <section className="container pt-12 pb-10 md:pt-16">
                <div className="grid grid-cols-12 items-end gap-6">
                    <div className="col-span-12 md:col-span-8">
                        <p className="eyebrow mb-3">Se connecter</p>
                        <h1 className="h-editorial text-4xl leading-[1.05] text-ink md:text-6xl">
                            Bon retour parmi
                            <br />
                            <span className="text-terracotta italic">
                                les artisans
                            </span>
                            .
                        </h1>
                    </div>
                    <p className="col-span-12 text-base leading-relaxed text-ink-soft md:col-span-4 md:text-right">
                        Retrouvez vos commandes, vos pièces sauvegardées et vos
                        ateliers favoris.
                    </p>
                </div>
            </section>

            <section className="container pb-24">
                <div className="grid grid-cols-12 gap-8 border-t border-ink/15 pt-10 lg:gap-12">
                    <aside className="hidden lg:col-span-5 lg:block">
                        <div className="sticky top-24">
                            <div className="aspect-[4/5] overflow-hidden">
                                <WarmFill
                                    variant="textile"
                                    className="h-full w-full"
                                />
                            </div>
                            <p className="mt-6 max-w-sm text-sm leading-relaxed text-ink-soft">
                                « Une connexion, mille mains. Reprenez là où
                                vous vous êtes arrêté. »
                            </p>
                        </div>
                    </aside>

                    <div className="col-span-12 lg:col-span-7">
                        {/* <form
                            onSubmit={handleSubmit}
                            className="space-y-8"
                            noValidate
                        >
                            <Field
                                label="Email"
                                type="email"
                                value={email}
                                onChange={setEmail}
                                placeholder="vous@exemple.com"
                                error={
                                    touched && !emailValid
                                        ? 'Email invalide.'
                                        : null
                                }
                                autoComplete="email"
                            />

                            <Field
                                label="Mot de passe"
                                type="password"
                                value={password}
                                onChange={setPassword}
                                placeholder="Votre mot de passe"
                                error={
                                    touched && password.length < 8
                                        ? 'Au moins 8 caractères.'
                                        : null
                                }
                                autoComplete="current-password"
                            />

                            <div className="flex flex-wrap items-center justify-between gap-4 text-sm">
                                <label className="inline-flex cursor-pointer items-center gap-3 text-ink-soft select-none">
                                    <input
                                        type="checkbox"
                                        checked={remember}
                                        onChange={(e) =>
                                            setRemember(e.target.checked)
                                        }
                                        className="h-4 w-4 border-ink/30 accent-ink"
                                    />
                                    Se souvenir de moi
                                </label>
                                <Link
                                    href="/forgot-password"
                                    className="text-ink underline underline-offset-4 hover:text-terracotta"
                                >
                                    Mot de passe oublié ?
                                </Link>
                            </div>

                            <div className="flex flex-col-reverse gap-4 pt-2 md:flex-row md:items-center md:justify-between">
                                <p className="text-sm text-ink-soft">
                                    Pas encore de compte ?{' '}
                                    <Link
                                        href="/register"
                                        className="text-ink underline underline-offset-4"
                                    >
                                        Créer un compte
                                    </Link>
                                </p>

                                <button
                                    type="submit"
                                    disabled={!canSubmit && touched}
                                    className="group inline-flex items-center justify-between gap-6 bg-ink px-6 py-4 text-cream transition-colors hover:bg-terracotta disabled:opacity-60"
                                >
                                    <span className="font-display text-base">
                                        Se connecter
                                    </span>
                                    <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" />
                                </button>
                            </div>
                        </form> */}
                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        name="email"
                                        label="Email"
                                        type="email"
                                        placeholder="vous@exemple.com"
                                        errorMessage={errors.email}
                                        autoComplete="email"
                                    />
                                    <Field
                                        name="password"
                                        label="Password"
                                        type="password"
                                        placeholder="Your password"
                                        errorMessage={errors.password}
                                        autoComplete="password"
                                    />

                                    <div className="flex flex-wrap items-center justify-between gap-4 text-sm">
                                        <label className="inline-flex cursor-pointer items-center gap-3 text-ink-soft select-none">
                                            <input
                                                type="checkbox"
                                                checked={remember}
                                                onChange={(e) =>
                                                    setRemember(
                                                        e.target.checked,
                                                    )
                                                }
                                                className="h-4 w-4 border-ink/30 accent-ink"
                                            />
                                            Se souvenir de moi
                                        </label>
                                        <Link
                                            href="/forgot-password"
                                            className="text-ink underline underline-offset-4 hover:text-terracotta"
                                        >
                                            Mot de passe oublié ?
                                        </Link>
                                    </div>
                                    <div className="flex flex-col-reverse gap-4 pt-2 md:flex-row md:items-center md:justify-between">
                                        <p className="text-sm text-ink-soft">
                                            Pas encore de compte ?{' '}
                                            <Link
                                                href="/register"
                                                className="text-ink underline underline-offset-4"
                                            >
                                                Créer un compte
                                            </Link>
                                        </p>

                                        <button
                                            type="submit"
                                            disabled={!canSubmit && touched}
                                            className="group inline-flex items-center justify-between gap-6 bg-ink px-6 py-4 text-cream transition-colors hover:bg-terracotta disabled:opacity-60"
                                        >
                                            <span className="font-display text-base">
                                                Se connecter
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

            <SiteFooter />
        </div>
    );
};

const Field = ({
    label,
    name,
    placeholder,
    type = 'text',
    error,
    autoComplete,
    errorMessage,
}: {
    name: string;
    label: string;
    placeholder?: string;
    type?: string;
    error?: string | null;
    autoComplete?: string;
    errorMessage?: string;
}) => (
    <label className="block">
        <span className="mb-2 block text-[11px] tracking-[0.18em] text-ink-soft uppercase">
            {label}
        </span>
        <input
            type={type}
            name={name}
            placeholder={placeholder}
            autoComplete={autoComplete}
            className={`w-full border-0 border-b bg-transparent ${
                error ? 'border-destructive' : 'border-ink/30'
            } py-3 font-display text-lg text-ink placeholder:text-ink-soft/50 focus:border-ink focus:outline-none`}
        />
        <InputError message={errorMessage} />
    </label>
);

export default Login;

// import { Form, Head, Link, router } from '@inertiajs/react';
// import InputError from '@/components/input-error';
// import PasswordInput from '@/components/password-input';
// import TextLink from '@/components/text-link';
// import { Button } from '@/components/ui/button';
// import { Checkbox } from '@/components/ui/checkbox';
// import { Input } from '@/components/ui/input';
// import { Label } from '@/components/ui/label';
// import { Spinner } from '@/components/ui/spinner';
// import { register } from '@/routes';
// import { store } from '@/routes/login';
// import { request } from '@/routes/password';
// import { useTranslation } from '@/hooks/use-translation';

// type Props = {
//     status?: string;
//     canResetPassword: boolean;
//     canRegister: boolean;
// };

// export default function Login({
//     status,
//     canResetPassword,
//     canRegister,
// }: Props) {
//     const { t, isRTL, locale } = useTranslation();

//     console.log('t: ', t);
//     console.log('isrtl: ', isRTL);
//     console.log('locale: ', locale);
//     const handleSwitch = () => {
//         router.post(route('locale.switch', 'fr'), {}, { preserveScroll: true });
//     };
//     return (
//         <>
//             <Head title="Log in" />
//             <div>{t('common.welcome')}</div>
//             <button onClick={handleSwitch}>switch me bb</button>

//             <Form
//                 {...store.form()}
//                 resetOnSuccess={['password']}
//                 className="flex flex-col gap-6"
//             >
//                 {({ processing, errors }) => (
//                     <>
//                         <div className="grid gap-6">
//                             <div className="grid gap-2">
//                                 <Label htmlFor="email">Email address</Label>
//                                 <Input
//                                     id="email"
//                                     type="email"
//                                     name="email"
//                                     required
//                                     autoFocus
//                                     tabIndex={1}
//                                     autoComplete="email"
//                                     placeholder="email@example.com"
//                                 />
//                                 <InputError message={errors.email} />
//                             </div>

//                             <div className="grid gap-2">
//                                 <div className="flex items-center">
//                                     <Label htmlFor="password">Password</Label>
//                                     {canResetPassword && (
//                                         <TextLink
//                                             href={request()}
//                                             className="ml-auto text-sm"
//                                             tabIndex={5}
//                                         >
//                                             Forgot password?
//                                         </TextLink>
//                                     )}
//                                 </div>
//                                 <PasswordInput
//                                     id="password"
//                                     name="password"
//                                     required
//                                     tabIndex={2}
//                                     autoComplete="current-password"
//                                     placeholder="Password"
//                                 />
//                                 <InputError message={errors.password} />
//                             </div>

//                             <div className="flex items-center space-x-3">
//                                 <Checkbox
//                                     id="remember"
//                                     name="remember"
//                                     tabIndex={3}
//                                 />
//                                 <Label htmlFor="remember">Remember me</Label>
//                             </div>

//                             <Button
//                                 type="submit"
//                                 className="mt-4 w-full"
//                                 tabIndex={4}
//                                 disabled={processing}
//                                 data-test="login-button"
//                             >
//                                 {processing && <Spinner />}
//                                 Log in
//                             </Button>
//                         </div>

//                         {canRegister && (
//                             <div className="text-center text-sm text-muted-foreground">
//                                 Don't have an account?{' '}
//                                 <TextLink href={register()} tabIndex={5}>
//                                     Sign up
//                                 </TextLink>
//                             </div>
//                         )}
//                     </>
//                 )}
//             </Form>

//             {status && (
//                 <div className="mb-4 text-center text-sm font-medium text-green-600">
//                     {status}
//                 </div>
//             )}
//         </>
//     );
// }

// Login.layout = {
//     title: 'Log in to your account',
//     description: 'Enter your email and password below to log in',
// };
