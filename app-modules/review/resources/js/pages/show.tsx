import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useClipboard } from '@/hooks/use-clipboard';
import { index } from '@/routes/reviews';
import type { ReviewFinding, ReviewShow } from '@/types';

const severityOrder = [
    'critical',
    'high',
    'medium',
    'low',
    'nit',
] as const;

const groupLabels: Record<ReviewShow['inbox_group'], string> = {
    in_progress: 'In progress',
    completed: 'Completed',
    failed: 'Failed',
    skipped: 'Skipped',
};

type Props = {
    review: ReviewShow;
};

export default function ReviewsShow({ review }: Props) {
    const isTerminalFailure =
        review.inbox_group === 'failed' || review.inbox_group === 'skipped';

    return (
        <>
            <Head
                title={`${review.repository_full_name} #${review.pull_request_number}`}
            />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-2">
                    <p className="text-sm text-muted-foreground">
                        <Link
                            href={index()}
                            className="underline underline-offset-4"
                        >
                            Reviews
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold">
                        {review.repository_full_name} #{review.pull_request_number}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {review.pull_request_title}
                    </p>
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <Badge variant="outline">{review.status}</Badge>
                        <span>{groupLabels[review.inbox_group]}</span>
                        <span>{review.summary_risk_level ?? '—'}</span>
                        <span>
                            {review.timestamp
                                ? new Date(review.timestamp).toLocaleString()
                                : '—'}
                        </span>
                    </div>
                </div>

                {isTerminalFailure ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>What happened</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm">
                                {review.failure_reason_label ??
                                    'This review could not be completed.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : review.inbox_group === 'in_progress' ? (
                    <p className="text-sm text-muted-foreground">
                        This review is still running.
                    </p>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Overview</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm whitespace-pre-wrap">
                                    {review.summary_overview}
                                </p>
                                {review.summary_walkthrough ? (
                                    <div className="space-y-2">
                                        <h2 className="text-sm font-medium">
                                            Walkthrough
                                        </h2>
                                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                            {review.summary_walkthrough}
                                        </p>
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>

                        <FindingsList findings={review.findings} />
                    </>
                )}
            </div>
        </>
    );
}

function FindingsList({ findings }: { findings: ReviewFinding[] }) {
    const groups = severityOrder
        .map((severity) => ({
            severity,
            findings: findings.filter((finding) => finding.severity === severity),
        }))
        .filter((group) => group.findings.length > 0);

    if (groups.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No findings.</p>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {groups.map((group) => (
                <section key={group.severity} className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold capitalize">
                        {group.severity}
                    </h2>
                    {group.findings.map((finding) => (
                        <FindingCard key={finding.id} finding={finding} />
                    ))}
                </section>
            ))}
        </div>
    );
}

function FindingCard({ finding }: { finding: ReviewFinding }) {
    const [copiedText, copy] = useClipboard();
    const location =
        finding.line === null
            ? finding.path
            : `${finding.path}:${finding.line}`;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{finding.title}</CardTitle>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{finding.category}</Badge>
                    <span className="font-mono text-xs text-muted-foreground">
                        {location}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                <p className="text-sm">{finding.message}</p>
                {finding.suggestion ? (
                    <p className="text-sm text-muted-foreground">
                        {finding.suggestion}
                    </p>
                ) : null}
                {finding.agent_prompt ? (
                    <div className="space-y-2 rounded-md border bg-muted/40 p-3">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Agent prompt
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => copy(finding.agent_prompt ?? '')}
                            >
                                {copiedText === finding.agent_prompt
                                    ? 'Copied'
                                    : 'Copy'}
                            </Button>
                        </div>
                        <p className="text-sm whitespace-pre-wrap">
                            {finding.agent_prompt}
                        </p>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

ReviewsShow.layout = {
    breadcrumbs: [
        {
            title: 'Reviews',
            href: index(),
        },
    ],
};
