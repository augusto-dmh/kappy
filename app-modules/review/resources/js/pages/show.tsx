import { Head } from '@inertiajs/react';

export default function ReviewsShow() {
    return <Head title="Review" />;
}

ReviewsShow.layout = {
    breadcrumbs: [
        {
            title: 'Reviews',
            href: '/reviews',
        },
    ],
};
