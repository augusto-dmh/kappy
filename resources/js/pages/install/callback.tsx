import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { callback } from '@/routes/install';
import { index as repositoriesIndex } from '@/routes/repositories';

export default function InstallCallback() {
    return (
        <>
            <Head title="GitHub App Installed" />
            <div className="flex h-full flex-1 flex-col items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="items-center text-center">
                        <div className="mb-2 flex size-12 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                            <CheckCircle2 className="size-6" />
                        </div>
                        <CardTitle>GitHub App Installed</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col items-center gap-4 text-center">
                        <p className="text-sm text-muted-foreground">
                            Kappy is now installed on your repositories. Pull
                            requests will be ingested automatically.
                        </p>
                        <Button asChild>
                            <Link href={repositoriesIndex()}>
                                View your repositories
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InstallCallback.layout = {
    breadcrumbs: [
        {
            title: 'GitHub App Installed',
            href: callback(),
        },
    ],
};
