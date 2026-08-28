import { Form, Head, Link, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import SiteHeader from '@/components/site/SiteHeader';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function AcceptTerms() {
    return (
        <>
            <Head title="Accepter les conditions" />
            <div className="paper-grain flex min-h-screen flex-col bg-cream">
                <SiteHeader />
                <section className="container flex flex-1 items-center py-16">
                    <div className="mx-auto w-full max-w-lg">
                        <div className="mb-8 flex h-12 w-12 items-center justify-center rounded-full bg-terracotta/10">
                            <ShieldCheck className="h-6 w-6 text-terracotta" />
                        </div>
                        <p className="eyebrow mb-3">Avant de continuer</p>
                        <h1 className="h-editorial text-3xl text-ink md:text-4xl">
                            Acceptez nos conditions pour accéder à Hirfaty.
                        </h1>
                        <p className="mt-4 text-ink-soft">
                            Pour utiliser votre espace et l'ensemble des
                            fonctionnalités de la Plateforme, merci de prendre
                            connaissance de nos Conditions d'utilisation et de
                            notre Politique de confidentialité, puis de les
                            accepter ci-dessous.
                        </p>

                        <Form
                            action={route('terms.accept.store')}
                            method="post"
                            className="mt-8 flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex items-start gap-3 border border-ink/10 bg-paper p-4">
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
                                                J'ai lu et j'accepte la{' '}
                                                <Link
                                                    href="/privacy"
                                                    target="_blank"
                                                    className="text-ink underline underline-offset-4"
                                                >
                                                    politique de confidentialité
                                                </Link>{' '}
                                                et les{' '}
                                                <Link
                                                    href="/terms"
                                                    target="_blank"
                                                    className="text-ink underline underline-offset-4"
                                                >
                                                    conditions d'utilisation
                                                </Link>{' '}
                                                de Hirfaty.
                                            </Label>
                                            <InputError
                                                message={errors.terms_accepted}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(route('logout'))
                                            }
                                            className="text-sm text-ink-soft transition-colors hover:text-terracotta"
                                        >
                                            Se déconnecter
                                        </button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="gap-2"
                                        >
                                            {processing && (
                                                <Spinner className="h-4 w-4" />
                                            )}
                                            Continuer
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </section>
            </div>
        </>
    );
}
