import {
    AlertTriangle,
    CheckCircle2,
    HelpCircle,
    MinusCircle,
    XCircle,
} from 'lucide-react';
import { cn } from '@/lib/utils';

/** Mirrors App\Services\HealthReportService::report(). */
export interface HealthCheck {
    name: string;
    label: string;
    status: string;
    summary: string;
    message: string | null;
    meta: Record<string, unknown>;
}

export interface HealthReport {
    status: string;
    checked_at: string | null;
    stale: boolean;
    checks: HealthCheck[];
    counts: Record<string, number>;
}

const STATUS_STYLE: Record<
    string,
    { icon: typeof CheckCircle2; dot: string; text: string; label: string }
> = {
    ok: {
        icon: CheckCircle2,
        dot: 'bg-emerald-500',
        text: 'text-emerald-700',
        label: 'OK',
    },
    warning: {
        icon: AlertTriangle,
        dot: 'bg-amber-500',
        text: 'text-amber-700',
        label: 'Avertissement',
    },
    failed: {
        icon: XCircle,
        dot: 'bg-red-500',
        text: 'text-red-700',
        label: 'Échec',
    },
    crashed: {
        icon: XCircle,
        dot: 'bg-red-500',
        text: 'text-red-700',
        label: 'Erreur',
    },
    skipped: {
        icon: MinusCircle,
        dot: 'bg-slate-300',
        text: 'text-slate-500',
        label: 'Ignoré',
    },
    unknown: {
        icon: HelpCircle,
        dot: 'bg-slate-300',
        text: 'text-slate-500',
        label: 'Inconnu',
    },
};

function styleFor(status: string) {
    return STATUS_STYLE[status] ?? STATUS_STYLE.unknown;
}

/** Banner summarising the whole run — the bit an admin reads at a glance. */
const OVERALL_BANNER: Record<string, { wrap: string; text: string }> = {
    ok: {
        wrap: 'border-emerald-200 bg-emerald-50',
        text: 'Tous les systèmes sont opérationnels.',
    },
    warning: {
        wrap: 'border-amber-200 bg-amber-50',
        text: 'Certains contrôles signalent un avertissement.',
    },
    failed: {
        wrap: 'border-red-200 bg-red-50',
        text: 'Un ou plusieurs contrôles ont échoué.',
    },
    unknown: {
        wrap: 'border-slate-200 bg-slate-50',
        text: 'Aucun contrôle n’a encore été exécuté.',
    },
};

/** Render the meta payload a check attaches (disk percentages, timings…). */
function MetaList({ meta }: { meta: Record<string, unknown> }) {
    const entries = Object.entries(meta).filter(
        ([, value]) => value !== null && typeof value !== 'object',
    );

    if (entries.length === 0) {
        return null;
    }

    return (
        <dl className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1">
            {entries.map(([key, value]) => (
                <div key={key} className="flex items-center gap-1.5">
                    <dt className="text-[11px] text-ink-soft">
                        {key.replace(/_/g, ' ')}
                    </dt>
                    <dd className="text-[11px] font-medium text-ink">
                        {String(value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function HealthPanel({ report }: { report: HealthReport }) {
    const banner = OVERALL_BANNER[report.status] ?? OVERALL_BANNER.unknown;
    const OverallIcon = styleFor(report.status).icon;

    const checkedAt = report.checked_at
        ? new Intl.DateTimeFormat('fr-MA', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(report.checked_at))
        : null;

    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                <div className="flex items-center gap-2">
                    <OverallIcon
                        className={cn('h-4 w-4', styleFor(report.status).text)}
                    />
                    <h2 className="text-sm font-semibold text-ink">
                        État du système
                    </h2>
                </div>

                <div className="flex items-center gap-3 text-xs text-ink-soft">
                    {report.counts.failed + report.counts.crashed > 0 && (
                        <span className="font-medium text-red-700">
                            {report.counts.failed + report.counts.crashed} en
                            échec
                        </span>
                    )}
                    {report.counts.warning > 0 && (
                        <span className="font-medium text-amber-700">
                            {report.counts.warning} avertissement
                            {report.counts.warning > 1 ? 's' : ''}
                        </span>
                    )}
                    <span>{report.counts.ok} OK</span>
                </div>
            </div>

            <div className="p-5">
                <div
                    className={cn(
                        'rounded-lg border px-4 py-3 text-sm text-ink',
                        banner.wrap,
                    )}
                >
                    <p className="font-medium">{banner.text}</p>
                    <p className="mt-0.5 text-xs text-ink-soft">
                        {checkedAt
                            ? `Dernière vérification : ${checkedAt}`
                            : 'Lancez « php artisan health:check » ou le planificateur pour obtenir des résultats.'}
                        {report.stale && report.checked_at && (
                            <span className="text-amber-700">
                                {' '}
                                — résultats obsolètes, le planificateur ne
                                tourne peut-être pas.
                            </span>
                        )}
                    </p>
                </div>

                {report.checks.length > 0 && (
                    <ul className="mt-4 divide-y divide-border">
                        {report.checks.map((check) => {
                            const style = styleFor(check.status);

                            return (
                                <li
                                    key={check.name}
                                    className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                >
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'h-2 w-2 shrink-0 rounded-full',
                                                    style.dot,
                                                )}
                                            />
                                            <span className="text-sm font-medium text-ink">
                                                {check.label}
                                            </span>
                                        </div>

                                        {check.message && (
                                            <p
                                                className={cn(
                                                    'mt-1 text-xs',
                                                    style.text,
                                                )}
                                            >
                                                {check.message}
                                            </p>
                                        )}

                                        <MetaList meta={check.meta} />
                                    </div>

                                    <span
                                        className={cn(
                                            'shrink-0 text-xs font-medium',
                                            style.text,
                                        )}
                                    >
                                        {check.summary || style.label}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </div>
    );
}
