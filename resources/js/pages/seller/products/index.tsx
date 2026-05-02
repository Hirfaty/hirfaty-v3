import SellerLayout from '@/layouts/seller/layout';
import React from 'react';
import { Link } from '@inertiajs/react';
import { Products } from '@/types';
import { Product, PaginatedData } from '@/types';

const Index = ({ pageProps }: { pageProps: PaginatedData<Product> }) => {
    console.log('products: ', pageProps);
    return (
        <div>
            {pageProps.data.map((e) => {
                return (
                    <div key={e.id} className="grid grid-cols-2">
                        <img src={e?.images[0]?.url} className="h-24 w-24" />
                        <Link
                            href={route('seller.products.edit', e.id)}
                            className="text-blue-600 hover:text-blue-900"
                        >
                            Edit Product
                        </Link>
                        <Link href={route('seller.products.destroy', e.id)}>
                            Delete
                        </Link>
                    </div>
                );
            })}
        </div>
      
    );
};

Index.layout = (page: React.ReactNode) => <SellerLayout children={page} />;

export default Index;
