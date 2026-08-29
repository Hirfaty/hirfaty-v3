import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ProductForm from '@/components/admin/product-form';
import AdminLayout from '@/layouts/admin/layout';
import type { Category, PageProps, Product } from '@/types';

interface Props extends PageProps {
    product: Product;
    categories: Category[];
}

const AdminProductEdit = ({ product, categories }: Props) => (
    <>
        <Head title={`Modifier — ${product.name}`} />
        <ProductForm
            mode="edit"
            product={product}
            categories={categories}
            // The owner never changes on edit, so the seller picker is hidden.
            store={
                product.store
                    ? {
                          id: product.store.id,
                          store_name: product.store.store_name,
                          user: product.store.user,
                      }
                    : null
            }
            submitUrl={route('admin.products.update', product.id)}
            backUrl={route('admin.products.show', product.id)}
        />
    </>
);

AdminProductEdit.layout = (page: ReactNode) => <AdminLayout children={page} />;

export default AdminProductEdit;
