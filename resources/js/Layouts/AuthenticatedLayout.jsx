import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from '@/Components/ui/button';
import { AppToaster } from '@/Components/ui/sonner';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@/inertia';
import {
    CalendarDays,
    ExternalLink,
    FolderKanban,
    List,
    LogOut,
    Menu,
    Shield,
    Upload,
    Users,
} from 'lucide-react';
import { useState } from 'react';

function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function SidebarLink({ href, active, onClick, icon: Icon, children }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                active
                    ? 'bg-white/15 text-white shadow-sm'
                    : 'text-white/70 hover:bg-white/10 hover:text-white',
            )}
        >
            <Icon className={cn('h-4 w-4', active ? 'text-white' : 'text-white/60')} />
            {children}
        </Link>
    );
}

function SidebarExternalLink({ href, onClick, children }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            onClick={onClick}
            className="flex items-center gap-3 rounded-lg border border-white/15 bg-white/5 px-3 py-2.5 text-sm font-medium text-white/80 transition-colors hover:border-white/25 hover:bg-white/10 hover:text-white"
        >
            <span className="min-w-0 flex-1 truncate">{children}</span>
            <ExternalLink className="h-3.5 w-3.5 shrink-0 text-white/45" />
        </a>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const { auth, externalSite } = usePage().props;
    const user = auth.user;
    const canControl = Boolean(auth.can?.control);
    const canUpload = Boolean(auth.can?.upload);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const onRecords =
        route().current('records.index') || route().current('records.show');
    const onProjects = route().current('projects.*');
    const onEmployees = route().current('employees.*');
    const onControl = route().current('control.*');
    const onYears =
        route().current('years.*') || route().current('imports.show');
    const onUpload =
        route().current('imports.create') ||
        route().current('imports.confirm') ||
        route().current('imports.summary');
    const onProfile = route().current('profile.*');

    const closeSidebar = () => setSidebarOpen(false);

    return (
        <div className="min-h-screen bg-background">
            {sidebarOpen && (
                <button
                    type="button"
                    className="fixed inset-0 z-40 bg-primary/40 backdrop-blur-[2px] lg:hidden"
                    aria-label="Close menu"
                    onClick={closeSidebar}
                />
            )}

            <aside
                className={cn(
                    'sidebar-sheen fixed inset-y-0 left-0 z-50 flex w-[17.5rem] flex-col bg-primary text-primary-foreground transition-transform duration-200 ease-out lg:translate-x-0',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="flex h-[4.25rem] items-center px-5">
                    <Link href={route('records.index')} onClick={closeSidebar}>
                        <ApplicationLogo variant="light" />
                    </Link>
                </div>

                <nav className="mt-2 flex-1 space-y-1 px-3">
                    <p className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/40">
                        Workspace
                    </p>
                    <SidebarLink
                        href={route('records.index')}
                        active={onRecords}
                        onClick={closeSidebar}
                        icon={List}
                    >
                        Records
                    </SidebarLink>
                    <SidebarLink
                        href={route('projects.index')}
                        active={onProjects}
                        onClick={closeSidebar}
                        icon={FolderKanban}
                    >
                        Projects
                    </SidebarLink>
                    <SidebarLink
                        href={route('employees.index')}
                        active={onEmployees}
                        onClick={closeSidebar}
                        icon={Users}
                    >
                        Employees
                    </SidebarLink>
                    {canControl && (
                        <SidebarLink
                            href={route('control.index')}
                            active={onControl}
                            onClick={closeSidebar}
                            icon={Shield}
                        >
                            Control
                        </SidebarLink>
                    )}
                    <SidebarLink
                        href={route('years.index')}
                        active={onYears}
                        onClick={closeSidebar}
                        icon={CalendarDays}
                    >
                        Years
                    </SidebarLink>
                    {canUpload && (
                        <SidebarLink
                            href={route('imports.create')}
                            active={onUpload}
                            onClick={closeSidebar}
                            icon={Upload}
                        >
                            Upload
                        </SidebarLink>
                    )}
                    {externalSite?.url && (
                        <div className="pt-6">
                            <p className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/40">
                                Links
                            </p>
                            <SidebarExternalLink
                                href={externalSite.url}
                                onClick={closeSidebar}
                            >
                                {externalSite.label}
                            </SidebarExternalLink>
                        </div>
                    )}
                </nav>

                <div className="border-t border-white/10 p-3">
                    <Link
                        href={route('profile.edit')}
                        onClick={closeSidebar}
                        className={cn(
                            'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors hover:bg-white/10',
                            onProfile && 'bg-white/10',
                        )}
                    >
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-semibold tracking-wide">
                            {initials(user.name)}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium">
                                {user.name}
                            </span>
                            <span className="block truncate text-xs text-white/50">
                                {user.email}
                            </span>
                        </span>
                    </Link>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        onClick={closeSidebar}
                        className="mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <LogOut className="h-4 w-4" />
                        Sign out
                    </Link>
                </div>
            </aside>

            <div className="flex min-h-screen flex-col lg:pl-[17.5rem]">
                {header && (
                    <header className="sticky top-0 z-30 border-b bg-card/90 backdrop-blur">
                        <div className="flex min-h-[4.25rem] items-center gap-3 px-4 sm:px-6 lg:px-8">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="lg:hidden"
                                onClick={() => setSidebarOpen(true)}
                                aria-label="Open menu"
                            >
                                <Menu className="h-4 w-4" />
                            </Button>
                            <div className="min-w-0 flex-1 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:tracking-tight">
                                {header}
                            </div>
                        </div>
                    </header>
                )}

                <main className="flex-1">{children}</main>
            </div>
            <AppToaster />
        </div>
    );
}
