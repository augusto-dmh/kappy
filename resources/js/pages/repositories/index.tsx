import { Head } from '@inertiajs/react';
import { index } from '@/routes/repositories';

export default function Repositories() {
    return (
        <>
            <Head title="Repositories" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Repositories</h1>
                </div>
            </div>
        </>
    );
}

Repositories.layout = {
    breadcrumbs: [
        {
            title: 'Repositories',
            href: index(),
        },
    ],
};
