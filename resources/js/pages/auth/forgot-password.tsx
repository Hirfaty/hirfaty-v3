import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('titles.auth.forgot_password')} />
            <div className="min-h-screen bg-cream paper-grain">
                <SiteHeader />

                <section className="container pt-12 pb-10 md:pt-16">
                    <div className="grid grid-cols-12 items-end gap-6">
                        <div className="col-span-12 md:col-span-8">
                            <p className="mb-3 eyebrow">
                                Réinitialiser votre mot de passe
                            </p>
                            <h1 className="h-editorial text-4xl leading-[1.05] text-ink md:text-6xl">
                                Accédez à votre
                                <br />
                                <span className="text-terracotta italic">
                                    compte
                                </span>
                                .
                            </h1>
                        </div>
                        <p className="col-span-12 text-base leading-relaxed text-ink-soft md:col-span-4 md:text-right">
                            Entrez votre adresse email pour recevoir un lien de
                            réinitialisation sécurisé.
                        </p>
                    </div>
                </section>

                <section className="container flex justify-center">
                    <div className="gap-8 border-t border-ink/15 pt-10 lg:gap-12">
                        <div className="col-span-12 lg:col-span-7">
                            {status && (
                                <div className="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                                    {status}
                                </div>
                            )}

                            <Form
                                {...email.form()}
                                className="flex flex-col gap-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <label className="block text-[11px] tracking-[0.18em] text-ink-soft uppercase">
                                                Adresse email
                                            </label>
                                            <input
                                                id="email"
                                                type="email"
                                                name="email"
                                                autoComplete="email"
                                                autoFocus
                                                placeholder="vous@exemple.com"
                                                className="border border-ink/15 bg-cream px-4 py-3 text-ink placeholder:text-ink-soft focus:ring-2 focus:ring-terracotta focus:outline-none"
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="flex flex-col gap-4 pt-2">
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="group inline-flex items-center justify-between gap-6 bg-ink px-6 py-4 text-cream transition-colors hover:bg-terracotta disabled:opacity-60"
                                            >
                                                <span className="font-display text-base">
                                                    {processing
                                                        ? 'Envoi en cours...'
                                                        : 'Envoyer le lien'}
                                                </span>
                                                <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" />
                                            </button>
                                        </div>

                                        <p className="text-sm text-ink-soft">
                                            Vous avez retrouvé votre mot de
                                            passe ?{' '}
                                            <Link
                                                href={login()}
                                                className="text-ink underline underline-offset-4 hover:text-terracotta"
                                            >
                                                Retourner à la connexion
                                            </Link>
                                        </p>
                                    </>
                                )}
                            </Form>
                        </div>
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
