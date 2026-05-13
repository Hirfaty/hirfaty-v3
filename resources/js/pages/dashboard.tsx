import { Head, Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import React from 'react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import SellerLayout from '@/layouts/seller/layout';
import { dashboard } from '@/routes';
import { logout } from '@/routes';

const Dashboard = () => {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="text-black">Hello Dashboard</div>
            <Link
                className="block w-full cursor-pointer border-2 border-red-500"
                href={logout()}
                as="button"
                onClick={handleLogout}
                data-test="logout-button"
            >
                <LogOut className="mr-2" />
                Log out
            </Link>
        </>
    );
};

export default Dashboard;

// Dashboard.layout = {
//     breadcrumbs: [
//         {
//             title: 'Dashboard',
//             href: dashboard(),
//         },
//     ],
// };
