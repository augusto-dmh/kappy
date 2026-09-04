export type InboxGroup =
    | 'in_progress'
    | 'completed'
    | 'failed'
    | 'skipped';

export type FindingsSeverityMix = {
    critical: number;
    high: number;
    medium: number;
    low: number;
    nit: number;
};

export type ReviewListRow = {
    id: string;
    status: string;
    inbox_group: InboxGroup;
    repository_full_name: string;
    pull_request_number: number;
    pull_request_title: string;
    summary_risk_level: string | null;
    findings_count: number;
    findings_severity: FindingsSeverityMix;
    timestamp: string | null;
};

export type ReviewFinding = {
    id: string;
    category: string;
    severity: string;
    path: string;
    line: number | null;
    title: string;
    message: string;
    suggestion: string | null;
    agent_prompt: string | null;
};

export type ReviewShow = ReviewListRow & {
    summary_overview: string | null;
    summary_walkthrough: string | null;
    failure_reason: string | null;
    failure_reason_label: string | null;
    findings: ReviewFinding[];
};
