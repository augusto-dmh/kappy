import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as repositoriesIndex } from '@/routes/repositories';
import { index, show } from '@/routes/reviews';
import type { InboxGroup, ReviewListRow } from '@/types';

type FilterValue = 'all' | InboxGroup;

const filterLabels: Record<FilterValue, string> = {
    all: 'All',
    completed: 'Completed',
    failed: 'Failed',
    skipped: 'Skipped',
    in_progress: 'In progress',
};

const groupLabels: Record<InboxGroup, string> = {
    in_progress: 'In progress',
    completed: 'Completed',
    failed: 'Failed',
    skipped: 'Skipped',
};

type Props = {
    reviews: ReviewListRow[];
};

export default function ReviewsIndex({ reviews }: Props) {
    const [filter, setFilter] = useState<FilterValue>('all');
    const visible =
        filter === 'all'
            ? reviews
            : reviews.filter((review) => review.inbox_group === filter);

    return (
        <>
            <Head title="Reviews" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-xl font-semibold">Reviews</h1>
                        <p className="text-sm text-muted-foreground">
                            Reviews for pull requests on accounts you belong
                            to.
                        </p>
                    </div>
                    <Select
                        value={filter}
                        onValueChange={(value) =>
                            setFilter(value as FilterValue)
                        }
                    >
                        <SelectTrigger aria-label="Filter by status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {(
                                [
                                    'all',
                                    'completed',
                                    'failed',
                                    'skipped',
                                    'in_progress',
                                ] as const
                            ).map((value) => (
                                <SelectItem key={value} value={value}>
                                    {filterLabels[value]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {reviews.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center dark:border-sidebar-border">
                        <h2 className="text-lg font-medium">No reviews yet</h2>
                        <p className="text-sm text-muted-foreground">
                            Enable review on a repository, then open a pull
                            request.{' '}
                            <Link
                                href={repositoriesIndex()}
                                className="underline underline-offset-4"
                            >
                                Repositories
                            </Link>
                        </p>
                    </div>
                ) : visible.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No reviews match this status.
                    </p>
                ) : (
                    <div className="divide-y rounded-lg border">
                        {visible.map((review) => (
                            <Link
                                key={review.id}
                                href={show.url(review.id)}
                                className="flex flex-col gap-2 px-4 py-3 hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {review.repository_full_name}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            #{review.pull_request_number}
                                        </span>
                                    </p>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {review.pull_request_title}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    <Badge
                                        variant={statusBadgeVariant(
                                            review.inbox_group,
                                        )}
                                    >
                                        {groupLabels[review.inbox_group]}
                                    </Badge>
                                    <span>
                                        {review.summary_risk_level ?? '—'}
                                    </span>
                                    <span>
                                        {review.findings_count}{' '}
                                        {review.findings_count === 1
                                            ? 'finding'
                                            : 'findings'}
                                        {severityMix(review)}
                                    </span>
                                    <span>{formatTimestamp(review.timestamp)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function statusBadgeVariant(
    group: InboxGroup,
): 'secondary' | 'outline' | 'destructive' {
    if (group === 'failed') {
        return 'destructive';
    }

    if (group === 'completed') {
        return 'outline';
    }

    return 'secondary';
}

function severityMix(review: ReviewListRow): string {
    const parts = (
        ['critical', 'high', 'medium', 'low', 'nit'] as const
    )
        .filter((key) => review.findings_severity[key] > 0)
        .map((key) => {
            const label = key === 'medium' ? 'med' : key;

            return `${review.findings_severity[key]} ${label}`;
        });

    return parts.length > 0 ? ` (${parts.join(' · ')})` : '';
}

function formatTimestamp(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString();
}

ReviewsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reviews',
            href: index(),
        },
    ],
};
