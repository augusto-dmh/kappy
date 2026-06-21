import { Head, router } from '@inertiajs/react';
import { update } from '@/actions/Modules/GitHubApp/Http/Controllers/RepositoryController';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/repositories';
import type { Repository } from '@/types';

type RepositoryRow = Pick<
    Repository,
    'id' | 'full_name' | 'private' | 'review_enabled'
>;

interface Props {
    repositories: RepositoryRow[];
}

export default function Repositories({ repositories }: Props) {
    function toggleReviewEnabled(repo: RepositoryRow) {
        router.patch(
            update.url(repo),
            { review_enabled: !repo.review_enabled },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Repositories" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Repositories</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage which repositories Kappy reviews.
                    </p>
                </div>
                {repositories.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No repositories found. Install the GitHub App on your
                        account to get started.
                    </p>
                ) : (
                    <div className="divide-y rounded-lg border">
                        {repositories.map((repo) => (
                            <div
                                key={repo.id}
                                className="flex items-center justify-between gap-4 px-4 py-3"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">
                                        {repo.full_name}
                                    </span>
                                    <Badge
                                        variant={
                                            repo.private
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {repo.private ? 'Private' : 'Public'}
                                    </Badge>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`review-${repo.id}`}
                                        checked={repo.review_enabled}
                                        onCheckedChange={() =>
                                            toggleReviewEnabled(repo)
                                        }
                                    />
                                    <Label
                                        htmlFor={`review-${repo.id}`}
                                        className="text-sm"
                                    >
                                        Review enabled
                                    </Label>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
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
