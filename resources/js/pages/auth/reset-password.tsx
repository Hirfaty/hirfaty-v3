import { Form, Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import WarmFill from '@/components/WarmFill';
import { update } from '@/routes/password';
import { Input } from '@/components/ui/input';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    return (
        <>
            <Head title="Reset password" />
            <div className="paper-grain min-h-screen bg-cream">
                <SiteHeader />

                <section className="container pt-12 pb-10 md:pt-16">
                    <div className="grid grid-cols-12 items-end gap-6">
                        <div className="col-span-12 md:col-span-8">
                            <p className="eyebrow mb-3">Nouveau mot de passe</p>
                            <h1 className="h-editorial text-4xl leading-[1.05] text-ink md:text-6xl">
                                Sécurisez votre
                                <br />
                                <span className="text-terracotta italic">
                                    accès
                                </span>
                                .
                            </h1>
                        </div>
                        <p className="col-span-12 text-base leading-relaxed text-ink-soft md:col-span-4 md:text-right">
                            Créez un mot de passe fort pour protéger votre
                            compte et continuer vos achats.
                        </p>
                    </div>
                </section>

                <section className="contasiner pb-24">
                    <div className="mx-auto max-w-xl">
                        <Form
                            {...update.form()}
                            transform={(data) => ({
                                ...data,
                                token,
                                email,
                            })}
                            resetOnSuccess={[
                                'password',
                                'password_confirmation',
                            ]}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <label className="block text-[11px] tracking-[0.18em] text-ink-soft uppercase">
                                            Adresse email
                                        </label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            autoComplete="email"
                                            value={email}
                                            className="border border-ink/15 bg-cream px-4 py-3 text-ink placeholder:text-ink-soft focus:ring-2 focus:ring-terracotta focus:outline-none"
                                            readOnly
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <label className="block text-[11px] tracking-[0.18em] text-ink-soft uppercase">
                                            Nouveau mot de passe
                                        </label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            autoComplete="new-password"
                                            className="border border-ink/15 bg-cream px-4 py-3 text-ink placeholder:text-ink-soft focus:ring-2 focus:ring-terracotta focus:outline-none"
                                            autoFocus
                                            placeholder="Votre nouveau mot de passe"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <label className="block text-[11px] tracking-[0.18em] text-ink-soft uppercase">
                                            Confirmer le mot de passe
                                        </label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            className="border border-ink/15 bg-cream px-4 py-3 text-ink placeholder:text-ink-soft focus:ring-2 focus:ring-terracotta focus:outline-none"
                                            placeholder="Confirmer votre mot de passe"
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
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
                                                    ? 'Mise à jour...'
                                                    : 'Mettre à jour le mot de passe'}
                                            </span>
                                            <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" />
                                        </button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
