import { Check, Copy, KeyRound } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { ResetLinkPayload } from '@/types';

/**
 * Shows a freshly generated password reset link for copying. Shared by the
 * users list and both user detail pages so the copy/expiry wording can't
 * drift between them.
 *
 * `link === null` closes the dialog, so the caller only has to track one
 * piece of state rather than a separate open flag.
 */
export default function ResetLinkDialog({
    link,
    onClose,
}: {
    link: ResetLinkPayload | null;
    onClose: () => void;
}) {
    const [copied, setCopied] = useState(false);
    const [copyFailed, setCopyFailed] = useState(false);

    const copy = async () => {
        if (!link) {
            return;
        }

        try {
            await navigator.clipboard.writeText(link.url);
            setCopyFailed(false);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // navigator.clipboard requires a secure context (https, or
            // localhost in dev). Rather than appearing to do nothing, tell
            // the admin to copy the field manually — it's already selectable.
            setCopyFailed(true);
        }
    };

    return (
        <Dialog
            open={link !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setCopied(false);
                    setCopyFailed(false);
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <KeyRound className="h-4 w-4" />
                        Lien de réinitialisation
                    </DialogTitle>
                    <DialogDescription>
                        Aucun e-mail n'a été envoyé. Copiez ce lien et
                        transmettez-le vous-même à {link?.email}.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-center gap-2">
                    <Input
                        readOnly
                        value={link?.url ?? ''}
                        onFocus={(e) => e.currentTarget.select()}
                        className="font-mono text-xs"
                    />
                    <Button
                        type="button"
                        onClick={copy}
                        className="shrink-0 rounded-lg"
                    >
                        {copied ? (
                            <>
                                <Check className="h-4 w-4" /> Copié
                            </>
                        ) : (
                            <>
                                <Copy className="h-4 w-4" /> Copier
                            </>
                        )}
                    </Button>
                </div>

                {copyFailed && (
                    <p className="text-xs text-red-600">
                        Copie automatique impossible. Sélectionnez le lien
                        ci-dessus et copiez-le manuellement.
                    </p>
                )}

                <p className="text-xs text-ink-soft">
                    Ce lien expire dans {link?.expires_in_minutes} minutes et ne
                    fonctionne qu'une seule fois. En générer un nouveau annulera
                    celui-ci.
                </p>
            </DialogContent>
        </Dialog>
    );
}
