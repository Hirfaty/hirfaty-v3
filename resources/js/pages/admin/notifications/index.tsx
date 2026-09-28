import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    notificationIcon,
    openNotification,
} from '@/components/admin/notification-bell';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { cn, formatRelativeTime } from '@/lib/utils';
import type { AdminNotification, PaginatedData } from '@/types';

interface Props {
    notifications: PaginatedData<AdminNotification>;
    counts: { all: number; unread: number };
    filters: { filter: 'unread' | null };
}

const TABS = [
    { value: null, label: 'Toutes', countKey: 'all' },
    { value: 'unread', label: 'Non lues', countKey: 'unread' },
] as const;

const Index = ({ notifications, counts, filters }: Props) => {
    const { t } = useTranslation();

    const setFilter = (filter: string | null) => {
        router.get(
            route('admin.notifications.index'),
            { filter: filter ?? undefined },
            { preserveState: true },
        );
    };

    const markAllRead = () => {
        router.post(
            route('admin.notifications.read-all'),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.admin.notifications')} />
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Notifications
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {counts.unread > 0
                            ? `${counts.unread} non lue${counts.unread > 1 ? 's' : ''} sur ${counts.all}.`
                            : 'Tout est lu.'}
                    </p>
                </div>
                {counts.unread > 0 && (
                    <button
                        type="button"
                        onClick={markAllRead}
                        className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3.5 py-2 text-sm font-medium text-ink-soft shadow-sm transition hover:border-ink/30 hover:text-ink"
                    >
                        <CheckCheck className="h-4 w-4" />
                        Tout marquer comme lu
                    </button>
                )}
            </header>

            {/* ── Filter tabs ──────────────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {TABS.map((tab) => {
                    const active = (filters.filter ?? null) === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setFilter(tab.value)}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition ${
                                active
                                    ? 'border-ink bg-ink text-white'
                                    : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-xs ${
                                    active
                                        ? 'bg-white/20 text-white'
                                        : 'bg-muted text-ink-soft'
                                }`}
                            >
                                {counts[tab.countKey]}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* ── List ─────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <ul className="divide-y divide-border">
                    {notifications.data.map((notification) => {
                        const Icon = notificationIcon(notification.kind);
                        const isUnread = notification.read_at === null;

                        return (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        openNotification(notification)
                                    }
                                    className={cn(
                                        'flex w-full items-start gap-4 px-5 py-4 text-start transition hover:bg-muted/30',
                                        isUnread && 'bg-muted/20',
                                    )}
                                >
                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                        <Icon className="h-4 w-4 text-ink-soft" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className={cn(
                                                'text-sm text-ink',
                                                isUnread && 'font-semibold',
                                            )}
                                        >
                                            {notification.title}
                                        </p>
                                        {notification.body && (
                                            <p className="mt-0.5 text-sm text-ink-soft">
                                                {notification.body}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3">
                                        <span className="text-xs text-ink-soft">
                                            {formatRelativeTime(
                                                notification.created_at,
                                            )}
                                        </span>
                                        {isUnread && (
                                            <span
                                                aria-label="Non lue"
                                                className="h-2 w-2 rounded-full bg-red-600"
                                            />
                                        )}
                                    </div>
                                </button>
                            </li>
                        );
                    })}

                    {notifications.data.length === 0 && (
                        <li className="px-5 py-10 text-center text-sm text-ink-soft">
                            {filters.filter === 'unread'
                                ? 'Aucune notification non lue.'
                                : 'Aucune notification pour l’instant.'}
                        </li>
                    )}
                </ul>

                {/* Pagination */}
                {notifications.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {notifications.from}–{notifications.to} sur{' '}
                            {notifications.total}
                        </p>
                        <div className="flex items-center gap-1">
                            {notifications.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition ${
                                        link.active
                                            ? 'bg-ink font-medium text-white'
                                            : 'text-ink-soft hover:bg-muted'
                                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default Index;
