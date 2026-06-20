export type InstallationTarget = 'User' | 'Organization';

export type RepositorySelection = 'all' | 'selected';

export type PullRequestState = 'open' | 'closed' | 'merged';

export type Installation = {
    id: number;
    account_id: number;
    github_installation_id: number;
    target_type: InstallationTarget;
    repositories_selection: RepositorySelection;
    suspended_at: string | null;
};

export type Repository = {
    id: number;
    installation_id: number;
    github_repo_id: number;
    full_name: string;
    private: boolean;
    default_branch: string;
    review_enabled: boolean;
};

export type PullRequest = {
    id: number;
    repository_id: number;
    github_pr_number: number;
    title: string;
    author_login: string;
    base_sha: string;
    head_sha: string;
    state: PullRequestState;
    linked_issue_ref: string | null;
};
