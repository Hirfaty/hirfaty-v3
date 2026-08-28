import axios from 'axios';
import { Check, KeyRound, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';

interface CopyInviteLinkButtonProps {
    userId: number;
}

/**
 * One-click "copy the set-password link" action for a table row — fetches a
 * fresh token and writes it straight to the clipboard, no dialog detour.
 */
export function CopyInviteLinkButton({ userId }: CopyInviteLinkButtonProps) {
    const [status, setStatus] = useState<'idle' | 'loading' | 'copied'>(
        'idle',
    );

    const copy = async () => {
        if (status === 'loading') return;
        setStatus('loading');
        try {
            const { data } = await axios.post(
                route('admin.users.invite-link', userId),
            );
            await navigator.clipboard.writeText(data.invite_url);
            setStatus('copied');
            toast.success('Lien copié dans le presse-papiers.');
            setTimeout(() => setStatus('idle'), 1800);
        } catch {
            setStatus('idle');
            toast.error('Impossible de générer le lien.');
        }
    };

    return (
        <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={copy}
            disabled={status === 'loading'}
            title="Copier le lien de connexion"
        >
            {status === 'loading' ? (
                <Loader2 className="h-4 w-4 animate-spin" />
            ) : status === 'copied' ? (
                <Check className="h-4 w-4 text-olive" />
            ) : (
                <KeyRound className="h-4 w-4" />
            )}
        </Button>
    );
}

export default CopyInviteLinkButton;
