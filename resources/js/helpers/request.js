import { pathParams, PC } from '../constants';

/* ─── Cast scalar values for JSON body ──────────────────────────────────── */
function castScalar(v, type) {
    if (v === '' || v == null) return v;
    
    // Explicit string types shouldn't be cast to booleans or numbers
    if (type === 'string' || type === 'text' || type === 'password' || type === 'email' || type === 'url' || type === 'color' || type === 'date' || type === 'uuid' || type === 'ulid' || type === 'ip' || type === 'ipv4' || type === 'ipv6' || type === 'mac_address') {
        return String(v);
    }

    if (type === 'boolean') {
        if (v === 'true' || v === true) return true;
        if (v === 'false' || v === false) return false;
        return Boolean(v);
    }
    
    if (type === 'number' || type === 'integer' || type === 'numeric') {
        if (!isNaN(v) && v !== '') return Number(v);
        return v;
    }

    // Default fallback behaviour if type is unspecified or unknown
    if (v === 'true') return true;
    if (v === 'false') return false;
    if (!isNaN(v) && v.trim?.() !== '') return Number(v);
    
    return v;
}

/**
 * compileTreeToPayload — Recursively compiles the visual formTree into
 * a flat key/value object suitable for the request body.
 *
 * IMPORTANT: Only includes nodes where `enabled !== false`.
 * Disabled nodes are preserved in state but excluded from the payload.
 */
export function compileTreeToPayload(nodes) {
    if (!Array.isArray(nodes)) return {};
    const obj = {};
    nodes.forEach(n => {
        if (!n || !n.key) return;
        if (n.enabled === false) return; // Postman checkbox: skip disabled
        
        if (n.type === 'object') {
            obj[n.key] = compileTreeToPayload(Array.isArray(n.children) ? n.children : []);
        } else if (n.type === 'array') {
            obj[n.key] = (Array.isArray(n.children) ? n.children : [])
                .filter(c => c.enabled !== false)
                .map(c => {
                    if (c.type === 'object') return compileTreeToPayload(Array.isArray(c.children) ? c.children : []);
                    return castScalar(c.value, c.type);
                });
        } else {
            obj[n.key] = castScalar(n.value, n.type);
        }
    });
    return obj;
}

/**
 * prepareRequest — Builds the final fetch config from route, form values,
 * path values, and custom headers.
 *
 * CRITICAL: When the payload contains files (FormData), the Content-Type
 * header is DYNAMICALLY STRIPPED so the browser sets the correct
 * multipart/form-data boundary automatically.
 */
export function prepareRequest(route, formValues, pathVals, customHeaders, queryValues = {}) {
    const method = route.methods[0];
    const isGet = method === 'GET' || method === 'HEAD';

    let urlPath = route.uri;
    const pp = pathParams(route.uri);
    pp.forEach(({ name, optional }) => {
        const val = pathVals[name];
        if (optional && !val) {
            urlPath = urlPath.replace(new RegExp(`/?\\{${name}\\?\\}`), '');
        } else {
            urlPath = urlPath.replace(new RegExp(`\\{${name}\\??\\}`, 'g'), encodeURIComponent(val || ''));
        }
    });

    let baseUrl = PC().baseUrl || '';
    let url = baseUrl + '/' + urlPath.replace(/^\//, '');

    // ── Build query string from explicit query params + GET body ──
    const qp = new URLSearchParams();
    const flatQ = (obj, pre = '') => {
        Object.entries(obj).forEach(([k, v]) => {
            const key = pre ? `${pre}[${k}]` : k;
            if (v == null) return;
            if (Array.isArray(v)) v.forEach((it, i) => typeof it === 'object' && it && !(it instanceof File) ? flatQ(it, `${key}[${i}]`) : qp.append(`${key}[${i}]`, it));
            else if (typeof v === 'object' && !(v instanceof File)) flatQ(v, key);
            else qp.append(key, v);
        });
    };

    // Always add explicit query params
    if (queryValues && typeof queryValues === 'object' && Object.keys(queryValues).length > 0) {
        flatQ(queryValues);
    }

    // Walk the form values tree to detect any File instances
    let hasFile = false;
    const walkFiles = (o) => {
        if (!o) return;
        Object.values(o).forEach(v => {
            if (v instanceof File) { hasFile = true; return; }
            if (Array.isArray(v)) v.forEach(item => { if (item instanceof File) hasFile = true; else if (typeof item === 'object' && item) walkFiles(item); });
            else if (v && typeof v === 'object' && !(v instanceof File)) walkFiles(v);
        });
    };
    walkFiles(formValues);

    const hdrs = { ...customHeaders };
    let body;

    if (isGet) {
        // For GET: body params also go as query string
        flatQ(formValues);
    } else if (hasFile) {
        const fd = new FormData();
        const append = (obj, pre = '') => {
            Object.entries(obj).forEach(([k, v]) => {
                const key = pre ? `${pre}[${k}]` : k;
                if (v == null || v === '') return;
                if (v instanceof File) {
                    fd.append(key, v);
                } else if (Array.isArray(v)) {
                    v.forEach((it, i) => {
                        if (it instanceof File) fd.append(`${key}[${i}]`, it);
                        else if (typeof it === 'object' && it) append(it, `${key}[${i}]`);
                        else fd.append(`${key}[${i}]`, it);
                    });
                } else if (typeof v === 'object') {
                    append(v, key);
                } else {
                    fd.append(key, v);
                }
            });
        };
        append(formValues);
        body = fd;

        // ╔═════════════════════════════════════════════════════════════╗
        // ║  CRITICAL: Strip Content-Type entirely for FormData.       ║
        // ║  The browser MUST set it with the correct boundary.        ║
        // ║  Hardcoding multipart/form-data will BREAK the request.    ║
        // ╚═════════════════════════════════════════════════════════════╝
        delete hdrs['Content-Type'];
        delete hdrs['content-type'];
    } else {
        // Don't auto-set Content-Type: application/json if the user didn't want headers, 
        // unless you absolutely need it to make the backend parse JSON correctly. 
        // We will add it by default to avoid broken JSON parsing.
        if (!hdrs['Content-Type'] && !hdrs['content-type']) {
            hdrs['Content-Type'] = 'application/json';
        }
        body = JSON.stringify(formValues);
    }

    // Append query string to URL
    const qs = qp.toString();
    if (qs) url += '?' + qs;

    return { url, method, hdrs, body };
}

/**
 * treeHasFiles — Recursively checks if any node in the formTree
 * contains a File value. Used for the file guard on the JSON tab.
 */
export function treeHasFiles(nodes) {
    if (!Array.isArray(nodes)) return false;
    return nodes.some(n => {
        if (n.value instanceof File) return true;
        if (Array.isArray(n.children)) return treeHasFiles(n.children);
        return false;
    });
}
