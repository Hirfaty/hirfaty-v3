import SellerLayout from '@/layouts/seller/layout';
import { useForm } from '@inertiajs/react';

type Media = {
    id: number;
    url: string;
    type: 'image' | 'video';
    order: number;
};

type ProductType = {
    id: number;
    name: string;
    description: string;
    base_price: number;
    sale_price: number | null;
    stock: number;
    category_id: number;
    status: 'draft' | 'published' | 'archived';
    images: Media[];
    videos: Media[];
};

const Edit = ({ product }: { product: ProductType }) => {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT', // ← Inertia method spoofing
        name: product.name + ' edited',
        description: product.description ?? '',
        base_price: product.base_price,
        sale_price: product.sale_price ?? '',
        stock: product.stock,
        category_id: product.category_id,
        action: 'draft',
        media: [] as File[],
        status: product.status ?? 'published',
        deleted_media: [] as number[],
    });
    console.log('errors: ', errors);

    const handleEdit = () => {
        post(route('seller.products.update', product.id));
    };
    return (
        <div>
            <button onClick={handleEdit}>editoo</button>
            <div>Product name: {product.name}</div>
            <div>Product base_price: {product.base_price}</div>
            {product.images.map((e) => {
                return <img src={e.url} />;
            })}
        </div>
    );
};

Edit.layout = (page: React.ReactNode) => <SellerLayout children={page} />;

export default Edit;
