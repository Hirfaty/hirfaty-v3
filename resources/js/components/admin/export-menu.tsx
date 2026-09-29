import { ChevronDown, Download, FileSpreadsheet, FileText } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * The "Exporter" button on the admin list pages: CSV or Excel.
 *
 * Plain <a> links, not Inertia's <Link>: the response is a file, which only a
 * real browser navigation saves. `filters` should be the ones the server last
 * applied (the page's `filters` prop), so the file matches the table on
 * screen — every page of it.
 */
export default function ExportMenu({
    routeName,
    filters = {},
}: {
    /** e.g. 'admin.users.export' — takes `format` plus the list's filters. */
    routeName: string;
    /** The page's own `filters` prop, passed straight through as query params. */
    filters?: object;
}) {
    const href = (format: 'csv' | 'xlsx') =>
        route(routeName, { ...filters, format });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-semibold text-ink shadow-sm transition hover:border-ink/30"
                >
                    <Download className="h-4 w-4" />
                    Exporter
                    <ChevronDown className="h-3.5 w-3.5 text-ink-soft" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <a
                        href={href('csv')}
                        download
                        className="flex items-center gap-2"
                    >
                        <FileText className="h-4 w-4" />
                        CSV (.csv)
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={href('xlsx')}
                        download
                        className="flex items-center gap-2"
                    >
                        <FileSpreadsheet className="h-4 w-4" />
                        Excel (.xlsx)
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
