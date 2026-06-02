export type AccountType = 'personal' | 'organization';

export type MembershipRole = 'owner' | 'admin' | 'member';

export type Account = {
    id: number;
    name: string;
    type: AccountType;
    github_login: string;
    role: MembershipRole;
};
