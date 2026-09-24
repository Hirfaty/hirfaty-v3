import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ProductForm from '@/components/admin/product-form';
import type { StoreOption } from '@/components/admin/product-form';
import StagingWarning from '@/components/admin/staging-warning';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import type { Category, PageProps } from '@/types';

interface Props extends PageProps {
    categories: Category[];
    /** Set when the admin arrived via ?store_id= from a seller's page. */
    store?: StoreOption | null;
    /** All sellers, offered when no store was pre-selected. */
    stores?: StoreOption[] | null;
}

const AdminProductCreate = ({ categories, store, stores }: Props) => {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('titles.admin.product_create')} />
            <div className="px-6 pt-6 empty:hidden md:px-8 md:pt-8">
                <StagingWarning path="/admin/products/create" />
            </div>
            <ProductForm
                mode="create"
                categories={categories}
                store={store}
                stores={stores}
                submitUrl={route('admin.products.store')}
                backUrl={
                    store?.user
                        ? route('admin.users.show', store.user.id)
                        : route('admin.products.index')
                }
            />
        </>
    );
};

AdminProductCreate.layout = (page: ReactNode) => (
    <AdminLayout children={page} />
);

export default AdminProductCreate;
