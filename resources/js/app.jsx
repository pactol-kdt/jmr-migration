import '../css/app.css';
import './bootstrap';
import './lib/routes';

import { BrowserRouter, Navigate, useLocation } from 'react-router-dom';
import { createRoot } from 'react-dom/client';
import { PageProvider, useLoadedPage } from './inertia';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

function pageName(pathname) {
    const table = [
        [/^\/login$/, 'Auth/Login'],
        [/^\/forgot-password$/, 'Auth/ForgotPassword'],
        [/^\/reset-password\/[^/]+$/, 'Auth/ResetPassword'],
        [/^\/confirm-password$/, 'Auth/ConfirmPassword'],
        [/^\/verify-email$/, 'Auth/VerifyEmail'],
        [/^\/records\/\d+$/, 'Records/Show'],
        [/^\/records$/, 'Records/Index'],
        [/^\/projects\/breakdown$/, 'Projects/Show'],
        [/^\/projects$/, 'Projects/Index'],
        [/^\/employees\/[^/]+$/, 'Employees/Show'],
        [/^\/employees$/, 'Employees/Index'],
        [/^\/control$/, 'Control/Index'],
        [/^\/years\/\d+\/uploads$/, 'Years/History'],
        [/^\/years\/\d+\/imports\/\d+$/, 'Imports/Show'],
        [/^\/years$/, 'Years/Index'],
        [/^\/import\/confirm$/, 'Imports/Confirm'],
        [/^\/import\/summary$/, 'Imports/Summary'],
        [/^\/import$/, 'Imports/Create'],
        [/^\/profile$/, 'Profile/Edit'],
    ];

    return table.find(([pattern]) => pattern.test(pathname))?.[1] ?? null;
}

function Screen() {
    const location = useLocation();
    const { props, loadedPath } = useLoadedPage();

    if (location.pathname === '/' || location.pathname === '/dashboard') {
        return <Navigate to="/records" replace />;
    }

    const name = pageName(location.pathname);
    if (!name) {
        return (
            <div className="grid min-h-screen place-items-center text-sm text-slate-500">
                Page not found.
            </div>
        );
    }

    if (!props || pageName(loadedPath) !== name) {
        return (
            <div className="grid min-h-screen place-items-center text-sm text-slate-500">
                Loading…
            </div>
        );
    }

    const Component = pages[`./Pages/${name}.jsx`]?.default;
    if (!Component) {
        return (
            <div className="grid min-h-screen place-items-center text-sm text-slate-500">
                Page not found.
            </div>
        );
    }

    return <Component {...props} />;
}

function App() {
    return (
        <BrowserRouter>
            <PageProvider>
                <Screen />
            </PageProvider>
        </BrowserRouter>
    );
}

createRoot(document.getElementById('app')).render(<App />);
