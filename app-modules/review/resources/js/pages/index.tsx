import { Head } from '@inertiajs/react';

export default function ReviewsIndex() {
    return <Head title="Reviews" />;
}

ReviewsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reviews',
            href: '/reviews',
        },
    ],
};
