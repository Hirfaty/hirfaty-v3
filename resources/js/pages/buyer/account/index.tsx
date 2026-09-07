import AddressCard from '@/components/buyer/account/AddressCard';
import IdentitySection from '@/components/buyer/account/IdentitySection';
import PasswordSection from '@/components/buyer/account/PasswordSection';
import type { AddressRow, Buyer } from '@/components/buyer/account/types';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';

interface Props {
    buyer: Buyer;
    defaultAddress: AddressRow | null;
    cities: { id: number; name: string }[];
}

export default function BuyerAccount({ buyer, defaultAddress, cities }: Props) {
    const { t } = useTranslation();

    return (
        <BuyerAccountLayout
            active="overview"
            title={t('buyer.account.title')}
            description={t('buyer.account.subtitle')}
        >
            {/*
             * Identity and password read as one flow on the left; the address
             * sits apart as a card on the right, rather than another item in
             * that same flow — it's a place, not a setting.
             */}
            <div className="grid gap-10 lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-14">
                <div>
                    <IdentitySection buyer={buyer} />
                    <PasswordSection />
                </div>

                <AddressCard address={defaultAddress} cities={cities} />
            </div>
        </BuyerAccountLayout>
    );
}
