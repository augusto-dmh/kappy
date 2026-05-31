import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const rootPages = import.meta.glob('./pages/**/*.tsx');
const modulePages = import.meta.glob(
    '../../app-modules/*/resources/js/pages/**/*.tsx',
);

createInertiaApp({
    resolve: (name) => {
        if (name.includes('::')) {
            const [module, page] = name.split('::');
            const path = `../../app-modules/${module}/resources/js/pages/${page}.tsx`;
            const loader = modulePages[path];

            if (!loader) {
                throw new Error('Inertia module page not found: ' + path);
            }

            return loader() as Promise<ResolvedComponent>;
        }

        const path = `./pages/${name}.tsx`;
        const loader = rootPages[path];

        if (!loader) {
            throw new Error('Inertia page not found: ' + path);
        }

        return loader() as Promise<ResolvedComponent>;
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
