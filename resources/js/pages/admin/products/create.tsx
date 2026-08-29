import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ProductForm from '@/components/admin/product-form';
import type { StoreOption } from '@/components/admin/product-form';
import AdminLayout from '@/layouts/admin/layout';
import type { Category, PageProps } from '@/types';

interface Props extends PageProps {
    categories: Category[];
    /** Set when the admin arrived via ?store_id= from a seller's page. */
    store?: StoreOption | null;
    /** All sellers, offered when no store was pre-selected. */
    stores?: StoreOption[] | null;
}

const AdminProductCreate = ({ categories, store, stores }: Props) => (
    <>
        <Head title="Nouveau produit" />
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

AdminProductCreate.layout = (page: ReactNode) => (
    <AdminLayout children={page} />
);

export default AdminProductCreate;
