import SellerLayout from '@/layouts/seller/layout';
import { Order } from '@/types';

const Show = ({ order }: { order: Order }) => {
    console.log('order: ', order);
    return (
        <div>
            <h1>Order #{order.id}</h1>
        </div>
    );
};

Show.layout = (page: React.ReactNode) => <SellerLayout children={page} />;
export default Show;
