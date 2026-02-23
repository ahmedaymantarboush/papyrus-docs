import { pathParams, PC } from '../constants';

/**
 * generateSnippet — Produces code snippets for curl, PHP, JS, and Python.
 *
 * Handles both JSON and multipart/form-data payloads.
 * File values are represented as placeholders.
 */
export function generateSnippet(lang, route, formValues, pathVals, headerObj = {}, queryValues = {}) {
    const method = route.methods[0];
    const isGet = method === 'GET' || method === 'HEAD';
    let p = route.uri;

    const pp = pathParams(route.uri);
    pp.forEach(({ name, optional }) => {
        const val = pathVals[name];
        if (optional && !val) {
            p = p.replace(new RegExp(`/?\\{${name}\\?\\}`), '');
        } else {
            p = p.replace(new RegExp(`\\{${name}\\??\\}`, 'g'), val || `{${name}}`);
        }
    });

    let baseUrl = PC().baseUrl || '{{base_url}}';
    let url = `${baseUrl}/${p.replace(/^\//, '')}`;

    // ── Build query string ──
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

    if (queryValues && typeof queryValues === 'object' && Object.keys(queryValues).length > 0) {
        flatQ(queryValues);
    }

    if (isGet) flatQ(formValues);

    const qs = qp.toString();
    if (qs) url += '?' + qs;

    let hasFile = false;
    Object.values(formValues).forEach(v => { if (v instanceof File) hasFile = true; });

    const safe = {};
    Object.entries(formValues).forEach(([k, v]) => {
        if (v instanceof File) safe[k] = `@${v.name}`;
        else if (v !== '' && v != null) safe[k] = v;
    });
    const json = JSON.stringify(safe, null, 2);

    switch (lang) {
        case 'curl': {
            let c = `curl -X ${method} "${url}"`;
            let hdrStr = '';
            Object.entries(headerObj).forEach(([k,v]) => { hdrStr += ` \\\n  -H "${k}: ${v}"`; });
            c += hdrStr;

            if (hasFile) Object.entries(formValues).forEach(([k, v]) => { c += v instanceof File ? ` \\\n  -F "${k}=@${v.name}"` : (v !== '' && v != null) ? ` \\\n  -F "${k}=${v}"` : ''; });
            else if (!isGet && Object.keys(safe).length) c += ` \\\n  -d '${json}'`;
            return c;
        }
        case 'php': {
            let hdrStr = Object.keys(headerObj).length ? `\n    'headers' => ${JSON.stringify(headerObj)},` : '';
            if (hasFile) {
                let c = `$client = new \\GuzzleHttp\\Client();\n$response = $client->request('${method}', '${url}', [${hdrStr}\n    'multipart' => [\n`;
                Object.entries(formValues).forEach(([k, v]) => { c += v instanceof File ? `        ['name' => '${k}', 'contents' => fopen('${v.name}', 'r')],\n` : (v !== '' && v != null) ? `        ['name' => '${k}', 'contents' => '${v}'],\n` : ''; });
                return c + `    ],\n]);\necho $response->getBody();`;
            }
            return `$client = new \\GuzzleHttp\\Client();\n$response = $client->request('${method}', '${url}', [${hdrStr}\n    'json' => ${json}\n]);\necho $response->getBody();`;
        }
        case 'js': {
            let hdrStr = Object.keys(headerObj).length ? `\n  headers: ${JSON.stringify(headerObj, null, 2)},` : '';
            if (hasFile) {
                let c = `const form = new FormData();\n`;
                Object.entries(formValues).forEach(([k, v]) => { c += v instanceof File ? `form.append('${k}', fileInput.files[0]);\n` : (v !== '' && v != null) ? `form.append('${k}', '${v}');\n` : ''; });
                return c + `\nfetch('${url}', {\n  method: '${method}',${hdrStr}\n  body: form\n}).then(r => r.json()).then(console.log);`;
            }
            return `fetch('${url}', {\n  method: '${method}',${hdrStr}\n  body: JSON.stringify(${json})\n}).then(r => r.json()).then(console.log);`;
        }
        case 'python': {
            let hdrStr = Object.keys(headerObj).length ? `headers=${JSON.stringify(headerObj)},` : '';
            if (hasFile) return `import requests\n\nfiles = {${Object.entries(formValues).filter(([, v]) => v instanceof File).map(([k, v]) => `\n    '${k}': open('${v.name}', 'rb')`).join(',')}}\ndata = ${json}\n\nresponse = requests.${method.toLowerCase()}('${url}', ${hdrStr} files=files, data=data)\nprint(response.json())`;
            return `import requests\n\nresponse = requests.${method.toLowerCase()}(\n    '${url}',\n    ${hdrStr}\n    json=${json}\n)\nprint(response.json())`;
        }
        default: return '';
    }
}
