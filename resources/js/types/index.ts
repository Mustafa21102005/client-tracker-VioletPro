import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
    flash: {
        success?: string | null;
        error?: string | null;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Client {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    last_follow_up: string | null;
    companies_count: number;
    projects_count: number;
}

export interface Company {
    id: number;
    client_id: number;
    name: string;
    description: string | null;
    projects_count: number;
    created_at: string;
}

export interface CompanyWithClient extends Omit<Company, 'projects_count'> {
    client: Pick<Client, 'id' | 'name'>;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
}

export interface Project {
    id: number;
    company_id: number;
    name: string;
    description: string | null;
    change_requests_count: number;
    created_at: string;
}

export interface ProjectStats {
    total: number;
    pending: number;
    done: number;
    active: number;
    total_value: number;
    unpaid_value: number;
}

export type Priority = 'low' | 'medium' | 'high' | 'urgent';

export type ChangeRequestStatus = 'pending' | 'done' | 'cancelled';

export interface ChangeRequest {
    id: number;
    project_id: number;
    title: string;
    description: string | null;
    priority: Priority;
    status: ChangeRequestStatus;
    price: string | null;
    paid_at: string | null;
    attachments?: Attachment[];
    created_at: string;
}

export interface ProjectWithCompany extends Omit<Project, 'change_requests_count'> {
    company: Pick<Company, 'id' | 'name'> & { client: Pick<Client, 'id' | 'name'> };
}

export interface Attachment {
    id: number;
    change_request_id: number;
    note: string | null;
    file: { name: string; size: number; mime_type: string } | null;
    created_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface FollowUpClient {
    id: number;
    name: string;
    phone: string;
    last_follow_up: string | null;
}

export interface DashboardRequest {
    id: number;
    title: string;
    price: number | null;
    status: ChangeRequestStatus;
    priority: Priority;
    project_id: number;
    project: string | null;
    company: string | null;
    client: string | null;
}

export interface PickerOption {
    id: number;
    title: string;
    subtitle: string | null;
}

export interface ChangeRequestWithProject extends ChangeRequest {
    project: {
        id: number;
        name: string;
        company: {
            id: number;
            name: string;
            client: { id: number; name: string } | null;
        } | null;
    } | null;
}

export interface EditFilters {
    search: string;
    status: ChangeRequestStatus | null;
    priority: Priority | null;
    payment: string | null;
    sort: string;
}

export interface AttachmentWithContext extends Attachment {
    change_request: {
        id: number;
        title: string;
        project: {
            id: number;
            name: string;
            company: {
                id: number;
                name: string;
                client: { id: number; name: string } | null;
            } | null;
        } | null;
    } | null;
}

export interface AttachmentFilters {
    search: string;
    type: 'pdf' | 'word' | 'image' | null;
    sort: string;
}

export type ReportTab = 'edits' | 'clients' | 'companies' | 'projects';

export interface ReportColumn {
    key: string;
    label: string;
    type: 'text' | 'number' | 'money' | 'date' | 'priority' | 'status';
    link: boolean;
}

export type ReportRow = Record<string, string | number | null>;

export interface ReportFilterValues {
    clients: number[];
    companies: number[];
    projects: number[];
    edits: number[];
    from: string | null;
    to: string | null;
}
