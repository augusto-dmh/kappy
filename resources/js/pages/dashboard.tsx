import { Head } from '@inertiajs/react';
import { Building2, Github, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { Account, AccountType, MembershipRole } from '@/types';

const accountTypeLabels: Record<AccountType, string> = {
    personal: 'Personal',
    organization: 'Organization',
};

const roleLabels: Record<MembershipRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
};

type DashboardProps = {
    accounts: Account[];
};

export default function Dashboard({ accounts }: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Your accounts</h1>
                    <p className="text-sm text-muted-foreground">
                        Accounts you belong to and your role on each.
                    </p>
                </div>

                {accounts.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {accounts.map((account) => (
                            <AccountCard key={account.id} account={account} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function AccountCard({ account }: { account: Account }) {
    const TypeIcon = account.type === 'organization' ? Building2 : User;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <TypeIcon className="size-5" />
                    </div>
                    <div className="flex min-w-0 flex-col">
                        <CardTitle className="truncate">
                            {account.name}
                        </CardTitle>
                        <span className="flex items-center gap-1 truncate text-sm text-muted-foreground">
                            <Github className="size-3.5 shrink-0" />
                            {account.github_login}
                        </span>
                    </div>
                </div>
                <Badge variant="secondary" className="shrink-0">
                    {accountTypeLabels[account.type]}
                </Badge>
            </CardHeader>
            <CardContent>
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">Your role</span>
                    <Badge variant="outline">{roleLabels[account.role]}</Badge>
                </div>
            </CardContent>
        </Card>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center dark:border-sidebar-border">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Github className="size-6" />
            </div>
            <div className="space-y-1">
                <h2 className="text-lg font-medium">No accounts yet</h2>
                <p className="text-sm text-muted-foreground">
                    Accounts you belong to will show up here.
                </p>
            </div>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
