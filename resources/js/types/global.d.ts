import { Config, RouteParam, RouteParamsWithQueryOverload } from 'ziggy-js';
import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

// 2. Add the Ziggy route helper to the global scope
declare global {
    function route(
        name?: string,
        params?: RouteParamsWithQueryOverload | RouteParam,
        absolute?: boolean,
        config?: Config,
    ): string;
}

// This export is necessary to treat this file as a module
export {};
