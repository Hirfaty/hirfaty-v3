import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, Mail, LogOut } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import WarmFill from '@/components/WarmFill';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const isLinkSent = status === 'verification-link-sent';

    return (
        <>
            <Head title="Vérification de l'e-mail" />
            <div className="paper-grain flex min-h-screen flex-col justify-center bg-cream">
                <section className="container py-12 md:py-24">
                    <div className="mx-auto grid max-w-6xl grid-cols-12 items-center gap-8">
                        {/* Left: Visual */}
                        {/* <aside className="hidden lg:col-span-5 lg:block">
                            <div className="aspect-[4/5] overflow-hidden shadow-sm border border-ink/5">
                                <WarmFill
                                    variant="pottery"
                                    className="h-full w-full"
                                />
                            </div>
                        </aside> */}

                        {/* Right: Content */}
                        <div className="col-span-12 space-y-10 lg:col-span-7 lg:pl-12">
                            <div className="space-y-4">
                                <p className="eyebrow">Sécurité du compte</p>
                                <h1 className="h-editorial text-4xl leading-[1.05] text-ink md:text-6xl">
                                    Vérifiez votre{' '}
                                    <span className="text-terracotta italic">
                                        e-mail
                                    </span>
                                    .
                                </h1>
                                <p className="max-w-xl text-base leading-relaxed text-ink-soft md:text-lg">
                                    Merci de nous avoir rejoints ! Avant de
                                    commencer, pourriez-vous vérifier votre
                                    adresse e-mail en cliquant sur le lien que
                                    nous venons de vous envoyer ?
                                </p>
                            </div>

                            {isLinkSent && (
                                <div className="flex items-start gap-3 rounded-sm border border-emerald-100 bg-emerald-50 p-4">
                                    <div className="mt-0.5">
                                        <div className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" />
                                    </div>
                                    <p className="text-sm leading-relaxed font-medium text-emerald-800">
                                        Un nouveau lien de vérification a été
                                        envoyé à l'adresse e-mail que vous avez
                                        fournie lors de l'inscription.
                                    </p>
                                </div>
                            )}

                            <div className="flex flex-col gap-6">
                                <Form {...send.form()} disableWhileProcessing>
                                    {({ processing }) => (
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="group inline-flex items-center justify-between gap-6 bg-ink px-8 py-5 text-cream transition-colors hover:bg-terracotta disabled:opacity-60"
                                        >
                                            <div className="flex items-center gap-3">
                                                {processing ? (
                                                    <Spinner className="h-5 w-5" />
                                                ) : (
                                                    <Mail className="h-5 w-5" />
                                                )}
                                                <span className="font-display text-lg">
                                                    Renvoyer l'e-mail de
                                                    vérification
                                                </span>
                                            </div>
                                            <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" />
                                        </button>
                                    )}
                                </Form>

                                <div className="flex items-center gap-8 border-t border-ink/10 pt-4">
                                    <Link
                                        href={logout()}
                                        method="post"
                                        as="button"
                                        className="group flex items-center gap-2 text-sm text-ink-soft transition-colors hover:text-ink"
                                    >
                                        <LogOut className="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                                        <span>Se déconnecter</span>
                                    </Link>

                                    <p className="text-sm text-ink-soft/60 italic">
                                        Pas encore reçu ? Pensez à vérifier vos
                                        spams.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

VerifyEmail.layout = (page: React.ReactNode) => page;
