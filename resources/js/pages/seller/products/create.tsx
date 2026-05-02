import SellerLayout from '@/layouts/seller/layout';
import { useForm } from '@inertiajs/react';

const Create = () => {
    const { data, setData, post, processing, errors } = useForm({
        name: 'Product name',
        description: 'Hello description',
        base_price: 10,
        sale_price: 9,
        stock: 2,
        category_id: 2,
        status: 'draft',
        media: [] as File[],
    });
    const handleSubmit = () => {
        post(route('seller.products.store'));
    };
    console.log('erroras: ', errors);
    return (
        <div>
            Create
            <input
                type="file"
                multiple
                onChange={(e) => {
                    if (e.target.files) {
                        // Convert FileList to an Array for the state
                        setData('media', Array.from(e.target.files));
                    }
                }}
            />
            {errors.media && <div className="text-red-500">{errors.media}</div>}
            <button
                onClick={handleSubmit}
                className="cursor-pointer rounded-2xl bg-blue-500"
            >
                Handle Submit
            </button>
        </div>
    );
};

Create.layout = (page: React.ReactNode) => <SellerLayout children={page} />;

export default Create;
