import { Link, router, usePage, usePoll } from '@inertiajs/react';
import { Bell, CheckCheck, CreditCard, ShoppingBag } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn, formatRelativeTime } from '@/lib/utils';
import type { AdminNotification, PageProps } from '@/types';

/** Picks the icon for a notification's `kind`; unknown kinds get the bell. */
const KIND_ICON: Record<string, LucideIcon> = {
    'purchase.placed': ShoppingBag,
    'payment.refund_needed': CreditCard,
};

export function notificationIcon(kind: string): LucideIcon {
    return KIND_ICON[kind] ?? Bell;
}

/** Marks it read server-side, then follows the redirect to what it is about. */
export function openNotification(notification: AdminNotification) {
    router.post(route('admin.notifications.open', notification.id));
}

/**
 * How often the bell re-reads its prop. A minute is quick enough for the desk
 * to see an order land without watching the page, and Inertia slows polling
 * right down while the tab is in the background.
 */
const POLL_INTERVAL_MS = 60_000;

export default function NotificationBell() {
    const { adminNotifications } = usePage<PageProps>().props;

    usePoll(POLL_INTERVAL_MS, { only: ['adminNotifications'] });

    if (!adminNotifications) {
        return null;
    }

    const { unread_count: unread, latest } = adminNotifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={
                        unread > 0
                            ? `Notifications (${unread} non lue${unread > 1 ? 's' : ''})`
                            : 'Notifications'
                    }
                    className="relative flex h-9 w-9 items-center justify-center rounded-lg text-ink-soft transition hover:bg-muted hover:text-ink"
                >
                    <Bell className="h-4.5 w-4.5" />
                    {unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-semibold text-white">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between border-b border-border px-4 py-3">
                    <p className="text-sm font-semibold text-ink">
                        Notifications
                    </p>
                    {unread > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    route('admin.notifications.read-all'),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="inline-flex items-center gap-1 text-xs text-ink-soft transition hover:text-ink"
                        >
                            <CheckCheck className="h-3.5 w-3.5" />
                            Tout marquer comme lu
                        </button>
                    )}
                </div>

                <div className="max-h-96 overflow-y-auto">
                    {latest.length === 0 && (
                        <p className="px-4 py-8 text-center text-sm text-ink-soft">
                            Aucune notification pour l’instant.
                        </p>
                    )}

                    {latest.map((notification) => {
                        const Icon = notificationIcon(notification.kind);
                        const isUnread = notification.read_at === null;

                        return (
                            <DropdownMenuItem
                                key={notification.id}
                                onSelect={() => openNotification(notification)}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-none border-b border-border px-4 py-3 last:border-b-0',
                                    isUnread && 'bg-muted/40',
                                )}
                            >
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <Icon className="h-4 w-4 text-ink-soft" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p
                                        className={cn(
                                            'text-sm break-words text-ink',
                                            isUnread && 'font-semibold',
                                        )}
                                    >
                                        {notification.title}
                                    </p>
                                    {notification.body && (
                                        <p className="line-clamp-2 text-xs text-ink-soft">
                                            {notification.body}
                                        </p>
                                    )}
                                    <p className="mt-0.5 text-[11px] text-ink-soft/80">
                                        {formatRelativeTime(
                                            notification.created_at,
                                        )}
                                    </p>
                                </div>
                                {isUnread && (
                                    <span
                                        aria-hidden
                                        className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-red-600"
                                    />
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                </div>

                <Link
                    href={route('admin.notifications.index')}
                    className="block border-t border-border px-4 py-2.5 text-center text-xs font-medium text-ink-soft transition hover:bg-muted hover:text-ink"
                >
                    Voir toutes les notifications
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
