import axios from 'axios';
import { Copy, KeyRound } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface InviteLinkButtonProps {
    userId: number;
}

export function InviteLinkButton({ userId }: InviteLinkButtonProps) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [inviteUrl, setInviteUrl] = useState<string | null>(null);

    const fetchLink = async () => {
        setOpen(true);
        setLoading(true);
        try {
            const response = await axios.post(
                route('admin.users.invite-link', userId),
            );
            setInviteUrl(response.data.invite_url);
        } catch {
            toast.error('Failed to generate the set-password link.');
            setOpen(false);
        } finally {
            setLoading(false);
        }
    };

    const copyLink = () => {
        if (!inviteUrl) {
            return;
        }
        navigator.clipboard.writeText(inviteUrl);
        toast.success('Link copied to clipboard.');
    };

    return (
        <>
            <Button variant="outline" onClick={fetchLink}>
                <KeyRound className="h-4 w-4" />
                Set-password link
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Set-password link</DialogTitle>
                        <DialogDescription>
                            Send this link to the user so they can set their
                            own password. It can be regenerated at any time.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex items-center gap-2 rounded-[5px] border border-ink/10 bg-cream/30 p-2 pl-3">
                        <input
                            readOnly
                            value={loading ? 'Generating link…' : (inviteUrl ?? '')}
                            onFocus={(e) => e.target.select()}
                            className="flex-1 truncate bg-transparent text-sm text-ink focus:outline-none"
                        />
                        <Button
                            size="sm"
                            className="shrink-0 gap-2"
                            onClick={copyLink}
                            disabled={loading || !inviteUrl}
                        >
                            <Copy className="h-3.5 w-3.5" />
                            Copy
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
