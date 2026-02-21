/* ═══════════════════════════════════════════════════════════════════════════
   CONSTANTS — CSS classes, method colors, type lists, config accessor
   ═══════════════════════════════════════════════════════════════════════════ */

/** Papyrus config injected via window.PapyrusConfig by the blade template */
export const PC = () => window.PapyrusConfig || {};

/** Shared CSS classes - Updated for Dark Mode */
export const inputCls = 'w-full bg-slate-100 dark:bg-[#0F172A] border border-slate-300 dark:border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-600 focus:border-amber-500/70 focus:ring-1 focus:ring-amber-500/30 outline-none transition-all';
export const selectCls = `${inputCls} appearance-none bg-white dark:bg-[#0F172A]`;
export const btnSm = 'px-2.5 py-1 text-[11px] rounded-md border transition-colors';

/** Method → color class map - Updated for Light/Dark contrast */
export const METHOD_COLORS = {
    GET: 'text-emerald-600 dark:text-emerald-400 border-emerald-500/30 bg-emerald-500/10',
    POST: 'text-sky-600 dark:text-sky-400 border-sky-500/30 bg-sky-500/10',
    PUT: 'text-amber-600 dark:text-amber-400 border-amber-500/30 bg-amber-500/10',
    PATCH: 'text-violet-600 dark:text-violet-400 border-violet-500/30 bg-violet-500/10',
    DELETE: 'text-rose-600 dark:text-rose-400 border-rose-500/30 bg-rose-500/10',
};

/** Active filter badge colors (with glow) */
export const METHOD_GLOW = {
    GET: 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10 shadow-[0_0_10px_rgba(16,185,129,0.15)]',
    POST: 'text-sky-400 border-sky-500/30 bg-sky-500/10 shadow-[0_0_10px_rgba(14,165,233,0.15)]',
    PUT: 'text-amber-400 border-amber-500/30 bg-amber-500/10 shadow-[0_0_10px_rgba(245,158,11,0.15)]',
    PATCH: 'text-violet-400 border-violet-500/30 bg-violet-500/10 shadow-[0_0_10px_rgba(139,92,246,0.15)]',
    DELETE: 'text-rose-400 border-rose-500/30 bg-rose-500/10 shadow-[0_0_10px_rgba(244,63,94,0.15)]',
};

/** All selectable field types */
export const FIELD_TYPES = [
    { value: 'text', label: 'Text (Area)' },
    { value: 'string', label: 'String (Input)' },
    { value: 'email', label: 'Email' },
    { value: 'url', label: 'URL' },
    { value: 'number', label: 'Number' },
    { value: 'boolean', label: 'Boolean' },
    { value: 'file', label: 'File' },
    { value: 'date', label: 'Date' },
    { value: 'password', label: 'Password' },
    { value: 'color', label: 'Color' },
    { value: 'json', label: 'JSON' },
    { value: 'object', label: 'Object (Key-Value)' },
    { value: 'array', label: 'Array (List)' },
    { value: 'select', label: 'Select' },
];

/** Priority order for method sorting */
export const METHOD_SORT_ORDER = { GET: 0, POST: 1, PUT: 2, PATCH: 3, DELETE: 4 };

/** Snippet languages */
export const SNIPPET_LANGS = ['curl', 'php', 'js', 'python'];

/** Unique route identifier */
export const rid = (r) => r.methods[0] + '|' + r.uri;

/** Extract path parameters from URI template */
export const pathParams = (uri) =>
    (uri.match(/\{([^}?]+)(\??)\}/g) || []).map(s => {
        const raw = s.replace(/[{}?]/g, '');
        const optional = s.includes('?');
        return { name: raw, optional };
    });
