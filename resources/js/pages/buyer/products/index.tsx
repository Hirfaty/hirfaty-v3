import { PaginatedData } from '@/types';
import { Product, Store } from '@/types/models';

const Index = ({
    products,
    filters,
}: {
    products: PaginatedData<Product & { store: Store }>;
    filters: {
        search?: string;
        category_id?: number;
        city_id?: number;
        sort?: string;
        min_price?: number;
        max_price?: number;
        on_sale?: boolean;
    };
}) => {
    console.log('products: ', products);
    console.log('filters: ', filters);
    return (
        <div className="grid grid-cols-4 gap-4">
            {products.data.map((product) => (
                <div
                    key={product.id}
                    className="rounded-md border-2 border-gray-300 p-4"
                >
                    <img
                        alt={product.name}
                        className="h-48 w-full object-cover"
                    />
                    <h2>{product.name}</h2>
                    <p>{product.description}</p>
                    <p>{product.base_price}</p>
                    <p>{product.sale_price}</p>
                    <p>{product.stock}</p>
                    <p>{product.status}</p>
                    <p>{product.average_rating}</p>
                    <p>{product.category?.name}</p>
                    <p>{product.store?.store_name}</p>
                    <p>{product.images.map((image) => image.url)}</p>
                </div>
            ))}
        </div>
    );
};

export default Index;
