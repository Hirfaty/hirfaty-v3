import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import BlogForm from '@/components/admin/BlogForm';
import AdminLayout from '@/layouts/admin/layout';
import type { BlogStatus, PageProps } from '@/types';

const Create = ({ statuses }: PageProps & { statuses: BlogStatus[] }) => (
    <div className="min-h-screen bg-cream/30 p-6 md:p-10">
        <Head title="Nouvel article" />

        <div className="mx-auto max-w-7xl">
            <header className="mb-10">
                <Link
                    href={route('admin.blogs.index')}
                    className="inline-flex items-center gap-1.5 text-xs tracking-wider text-ink-soft uppercase transition-colors hover:text-terracotta"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Journal
                </Link>
                <h1 className="mt-3 h-editorial text-4xl text-ink md:text-5xl">
                    Nouvel <span className="italic">article</span>
                </h1>
            </header>

            <BlogForm
                statuses={statuses}
                action={route('admin.blogs.store')}
                uploadUrl={route('admin.blogs.images.store')}
            />
        </div>
    </div>
);

Create.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Create;
