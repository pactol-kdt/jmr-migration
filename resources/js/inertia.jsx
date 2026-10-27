import { createContext, useContext, useEffect, useRef, useState } from 'react';
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom';

const PageContext = createContext({
    props: null,
    loadedPath: '',
});

let bridge = {
    load: async () => null,
    navigate: () => {},
    path: () => '/',
};

function apiPath(url) {
    if (url.startsWith('/api/') || url.startsWith('http')) {
        return url;
    }

    return `/api${url.startsWith('/') ? url : `/${url}`}`;
}

function withQuery(url, data) {
    if (!data || data instanceof FormData) {
        return url;
    }

    const [path, existing] = url.split('?');
    const params = new URLSearchParams(existing || '');
    Object.entries(data).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            params.delete(key);
        } else {
            params.set(key, String(value));
        }
    });
    const qs = params.toString();

    return qs ? `${path}?${qs}` : path;
}

function flattenErrors(errors = {}) {
    return Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [
            key,
            Array.isArray(value) ? value[0] : String(value),
        ]),
    );
}

async function readJson(response) {
    try {
        return await response.json();
    } catch {
        return {};
    }
}

export function usePage() {
    const { props } = useContext(PageContext);
    const location = useLocation();

    return {
        props: props ?? {},
        url: location.pathname + location.search,
    };
}

export function PageProvider({ children }) {
    const location = useLocation();
    const navigate = useNavigate();
    const [props, setProps] = useState(null);
    const [loadedPath, setLoadedPath] = useState('');

    const load = async (pathWithQuery, { only } = {}) => {
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (only?.length) {
            headers['X-Partial-Data'] = only.join(',');
        }

        const response = await fetch(apiPath(pathWithQuery), {
            headers,
            credentials: 'same-origin',
        });
        const data = await readJson(response);
        if (data.csrf) {
            window.__csrf = data.csrf;
        }

        if (response.status === 401) {
            if (window.location.pathname !== '/login') {
                navigate('/login', { replace: true });
            }
            return null;
        }

        if (data.redirect) {
            navigate(data.redirect, { replace: true });
            return data;
        }

        setProps((current) => (only?.length && current ? { ...current, ...data } : data));
        if (!only?.length) {
            setLoadedPath(pathWithQuery.split('?')[0]);
        }
        if (window.__preserveScroll != null) {
            const top = window.__preserveScroll;
            window.__preserveScroll = null;
            requestAnimationFrame(() => window.scrollTo(0, top));
        }

        return data;
    };

    useEffect(() => {
        load(location.pathname + location.search);
        // Reloading when the address changes is the page data source.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [location.pathname, location.search]);

    bridge = {
        load,
        navigate,
        path: () => location.pathname + location.search,
    };

    return (
        <PageContext.Provider value={{ props, loadedPath }}>
            {children}
        </PageContext.Provider>
    );
}

export function useLoadedPage() {
    return useContext(PageContext);
}

export const router = {
    get(url, data, options = {}) {
        if (options.preserveScroll) {
            window.__preserveScroll = window.scrollY;
        }
        bridge.navigate(withQuery(url, data), { replace: Boolean(options.replace) });
    },
    visit(url) {
        bridge.navigate(url);
    },
    reload(options = {}) {
        const path = bridge.path();
        bridge.load(path, { only: options.only }).finally(() => options.onFinish?.());
    },
    async submit(method, url, data, options = {}) {
        const scroll = options.preserveScroll ? window.scrollY : null;
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': window.__csrf || '',
        };
        let body;
        if (data instanceof FormData || options.forceFormData) {
            body = data instanceof FormData ? data : objectToForm(data);
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(data ?? {});
        }

        let response;
        let json = {};
        try {
            response = await fetch(apiPath(url), {
                method: method.toUpperCase(),
                headers,
                body,
                credentials: 'same-origin',
            });
            json = await readJson(response);
        } catch (error) {
            options.onError?.({ message: 'Request failed' });
            options.onFinish?.();
            return;
        }
        if (json.csrf) {
            window.__csrf = json.csrf;
        }

        if (response.status === 422) {
            options.onError?.(flattenErrors(json.errors));
            options.onFinish?.();
            return;
        }

        if (!response.ok) {
            options.onError?.({ message: json.message || 'Request failed' });
            options.onFinish?.();
            return;
        }

        options.onSuccess?.(json);
        if (json.redirect) {
            const current = bridge.path();
            if (json.redirect === current || json.redirect === window.location.pathname) {
                await bridge.load(json.redirect);
            } else {
                bridge.navigate(json.redirect);
            }
        } else {
            await bridge.load(bridge.path());
        }

        if (scroll != null) {
            requestAnimationFrame(() => window.scrollTo(0, scroll));
        }
        options.onFinish?.();
    },
    post(url, data, options) {
        return this.submit('POST', url, data, options);
    },
    patch(url, data, options) {
        return this.submit('PATCH', url, data, options);
    },
    put(url, data, options) {
        return this.submit('PUT', url, data, options);
    },
    delete(url, options) {
        return this.submit('DELETE', url, {}, options);
    },
};

function objectToForm(data = {}) {
    const form = new FormData();
    Object.entries(data).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            return;
        }
        form.append(key, value);
    });

    return form;
}

export function Head({ title }) {
    useEffect(() => {
        const appName = import.meta.env.VITE_APP_NAME || 'JMR Migration';
        document.title = title ? `${title} - ${appName}` : appName;
    }, [title]);

    return null;
}

export function Link({
    href,
    method = 'get',
    as,
    children,
    onClick,
    preserveScroll,
    preserveState,
    ...rest
}) {
    const url = typeof href === 'string' ? href : '/';

    if (method.toLowerCase() !== 'get') {
        const Tag = as === 'button' ? 'button' : 'a';

        return (
            <Tag
                {...rest}
                href={Tag === 'a' ? url : undefined}
                type={Tag === 'button' ? 'button' : undefined}
                onClick={(event) => {
                    event.preventDefault();
                    onClick?.(event);
                    router.submit(method, url);
                }}
            >
                {children}
            </Tag>
        );
    }

    if (url.startsWith('http')) {
        return (
            <a href={url} onClick={onClick} {...rest}>
                {children}
            </a>
        );
    }

    return (
        <RouterLink
            to={url}
            {...rest}
            onClick={(event) => {
                if (preserveScroll) {
                    window.__preserveScroll = window.scrollY;
                }
                onClick?.(event);
            }}
        >
            {children}
        </RouterLink>
    );
}

export function useForm(initial) {
    const initialRef = useRef(initial);
    const [data, setDataState] = useState(initial);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);

    const setData = (key, value) => {
        if (typeof key === 'string') {
            setDataState((current) => ({ ...current, [key]: value }));
            return;
        }
        setDataState(key);
    };

    const reset = (...fields) => {
        if (fields.length === 0) {
            setDataState(initialRef.current);
            return;
        }

        setDataState((current) => {
            const next = { ...current };
            fields.forEach((field) => {
                next[field] = initialRef.current[field] ?? '';
            });
            return next;
        });
    };

    const send = (method, url, options = {}) => {
        setProcessing(true);
        setErrors({});
        router.submit(method, url, data, {
            ...options,
            onSuccess: (page) => {
                setRecentlySuccessful(true);
                window.setTimeout(() => setRecentlySuccessful(false), 2000);
                options.onSuccess?.(page);
            },
            onError: (nextErrors) => {
                setErrors(nextErrors);
                options.onError?.(nextErrors);
            },
            onFinish: () => {
                setProcessing(false);
                options.onFinish?.();
            },
        });
    };

    return {
        data,
        setData,
        errors,
        processing,
        recentlySuccessful,
        reset,
        post: (url, options) => send('POST', url, options),
        patch: (url, options) => send('PATCH', url, options),
        put: (url, options) => send('PUT', url, options),
        delete: (url, options) => send('DELETE', url, options),
    };
}
