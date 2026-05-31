import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Product = {
    id: number;
    name: string;
    price: number;
    created_at: string;
    updated_at: string;
};

type Props = {
    products: Product[];
};

export default function CatalogIndex({ products }: Props) {
    return (
        <>
            <Head title="Catalog" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {products.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No products available.</p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {products.map((product) => (
                            <Card key={product.id}>
                                <CardHeader>
                                    <CardTitle>{product.name}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-sm">
                                        ${(product.price / 100).toFixed(2)}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CatalogIndex.layout = {
    breadcrumbs: [
        {
            title: 'Catalog',
            href: '/catalog',
        },
    ],
};
