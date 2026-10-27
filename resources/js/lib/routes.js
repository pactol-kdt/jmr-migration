const templates = {
    'records.index': '/records',
    'records.show': '/records/{record}',
    'records.export': '/api/records/export',
    'projects.index': '/projects',
    'projects.show': '/projects/breakdown',
    'projects.export': '/api/projects/export',
    'employees.index': '/employees',
    'employees.show': '/employees/{employee}',
    'employees.import': '/employees/import',
    'employees.records.update': '/employees/{employee}/records/{record}',
    'control.index': '/control',
    'control.update': '/control/{person}',
    'years.index': '/years',
    'years.show': '/years/{year}',
    'years.history': '/years/{year}/uploads',
    'imports.create': '/import',
    'imports.analyze': '/api/import/analyze',
    'imports.confirm': '/import/confirm',
    'imports.store': '/import/confirm',
    'imports.cancel': '/import/confirm',
    'imports.summary': '/import/summary',
    'imports.show': '/years/{year}/imports/{batch}',
    'profile.edit': '/profile',
    'profile.update': '/profile',
    'profile.destroy': '/profile',
    login: '/login',
    logout: '/logout',
    'password.request': '/forgot-password',
    'password.email': '/forgot-password',
    'password.reset': '/reset-password/{token}',
    'password.store': '/reset-password',
    'password.update': '/password',
    'password.confirm': '/confirm-password',
    'verification.send': '/email/verification-notification',
};

const patterns = [
    ['records.export', /^\/records\/export$/],
    ['records.show', /^\/records\/\d+$/],
    ['records.index', /^\/records$/],
    ['projects.export', /^\/projects\/export$/],
    ['projects.show', /^\/projects\/breakdown$/],
    ['projects.index', /^\/projects$/],
    ['employees.show', /^\/employees\/[^/]+$/],
    ['employees.index', /^\/employees$/],
    ['control.update', /^\/control\/\d+$/],
    ['control.index', /^\/control$/],
    ['years.history', /^\/years\/\d+\/uploads$/],
    ['imports.show', /^\/years\/\d+\/imports\/\d+$/],
    ['years.index', /^\/years$/],
    ['imports.confirm', /^\/import\/confirm$/],
    ['imports.summary', /^\/import\/summary$/],
    ['imports.create', /^\/import$/],
    ['profile.edit', /^\/profile$/],
    ['login', /^\/login$/],
    ['password.request', /^\/forgot-password$/],
];

function current(name) {
    const path = window.location.pathname;

    if (name.endsWith('.*')) {
        const prefix = name.slice(0, -2);

        return patterns.some(
            ([routeName, pattern]) =>
                routeName.startsWith(`${prefix}.`) && pattern.test(path),
        );
    }

    const found = patterns.find(([routeName]) => routeName === name);

    return found ? found[1].test(path) : false;
}

function fill(template, params) {
    let path = template;
    const query = new URLSearchParams();

    if (Array.isArray(params)) {
        const names = [...path.matchAll(/\{([^}]+)\}/g)].map((match) => match[1]);
        params.forEach((value, index) => {
            path = path.replace(`{${names[index]}}`, encodeURIComponent(value));
        });
    } else if (params !== undefined && params !== null && typeof params !== 'object') {
        const name = path.match(/\{([^}]+)\}/)?.[1];
        if (name) {
            path = path.replace(`{${name}}`, encodeURIComponent(params));
        }
    } else if (params && typeof params === 'object') {
        const names = [...path.matchAll(/\{([^}]+)\}/g)].map((match) => match[1]);
        const used = new Set();
        names.forEach((name) => {
            if (params[name] !== undefined && params[name] !== null) {
                path = path.replace(`{${name}}`, encodeURIComponent(params[name]));
                used.add(name);
            }
        });
        Object.entries(params).forEach(([key, value]) => {
            if (used.has(key) || value === undefined || value === null) {
                return;
            }
            query.set(key, String(value));
        });
    }

    const qs = query.toString();

    return qs ? `${path}?${qs}` : path;
}

export function route(name, params) {
    if (typeof name !== 'string') {
        return { current };
    }

    const template = templates[name];
    if (!template) {
        return '/';
    }

    return fill(template, params);
}

window.route = route;
