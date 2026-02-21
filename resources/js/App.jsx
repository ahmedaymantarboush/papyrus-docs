/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  PAPYRUS — Enterprise API Documentation & Testing Client
 *  App.jsx — Root Orchestrator (Slim ~250 lines)
 *
 *  This file composes all modular components and manages:
 *    - Schema fetch & processing (sort, group, filter)
 *    - Route selection & hash routing
 *    - FormTree state lifecycle (hydration, persistence, sync)
 *    - Custom headers (global vs per-route)
 *    - Settings persistence via localStorage
 *
 *  All heavy rendering logic has been extracted into:
 *    components/layout/   → Sidebar, SettingsModal
 *    components/doc/      → DocSection
 *    components/playground/→ Playground
 *    components/form/     → DynamicField, ObjectBuilder, ArrayBuilder, etc.
 *    components/json/     → RawJsonEditor
 *    components/headers/  → HeadersEditor
 *    helpers/             → request, snippets, schema
 * ═══════════════════════════════════════════════════════════════════════════
 */
import React, { useEffect, useState, useMemo, useCallback, useRef } from 'react';
import ReactDOM from 'react-dom/client';
import '../css/app.css';

import { rid, pathParams, PC, METHOD_SORT_ORDER } from './constants';
import useLocalStorage from './hooks/useLocalStorage';
import { compileTreeToPayload } from './helpers/request';
import { buildInitialTree, sanitizeTree, resetTreeToDefaults } from './helpers/schema';

import Sidebar from './components/layout/Sidebar';
import SettingsModal from './components/layout/SettingsModal';
import DocSection from './components/doc/DocSection';
import Playground from './components/playground/Playground';
import useTheme from './hooks/useTheme';

export default function App() {
    /* ── Theme ───────────────────────────────────────────────────── */
    const { theme, toggle, isDark } = useTheme();

    /* ── Core State ──────────────────────────────────────────────── */
    const [rawSchema, setRawSchema] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [activeRoute, setActiveRoute] = useState(null);

    const [formTree, setFormTree] = useState([]);
    const [pathVals, setPathVals] = useState({});

    /* ── Custom Headers (initialized from PapyrusConfig) ─────────── */
    const [customHeaders, setCustomHeaders] = useState(() => {
        const defaultHdrs = PC().headers || {};
        const arr = Object.entries(defaultHdrs).map(([k, v]) => ({ key: k, value: String(v), active: true }));
        if (arr.length === 0) return [];
        return arr;
    });

    const [fieldConfig, setFieldConfig] = useLocalStorage('papyrus_field_types', {});

    /* ── UI State ────────────────────────────────────────────────── */
    const [menuOpen, setMenuOpen] = useState(false);
    const [playgroundOpen, setPlaygroundOpen] = useState(false);
    const [settingsOpen, setSettingsOpen] = useState(false);
    /* ── Execution Trigger ───────────────────────────────────────── */
    const executeRef = useRef(null);
    const [executing, setExecuting] = useState(false);

    /* ── Persisted Sizes & Settings ──────────────────────────────── */
    const [sidebarWidth, setSidebarWidth] = useLocalStorage('papyrus_sidebar_w', 280);
    const [playgroundWidth, setPlaygroundWidth] = useLocalStorage('papyrus_playground_w', 450);

    const [settings, setSettings] = useLocalStorage('papyrus_settings', {
        sortBy: 'default',
        groupBy: 'default',
        filterMethods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        filterNameRegex: '',
        filterControllerRegex: '',
        saveResponses: false,
        globalHeaders: false,
        headerEditorMode: 'form'
    });

    /* ── Migration: old filterMethod string → filterMethods array ── */
    useEffect(() => {
        if (settings.filterMethod !== undefined) {
            setSettings(s => {
                const ns = { ...s };
                ns.filterMethods = ['ANY', ''].includes(ns.filterMethod)
                    ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
                    : [ns.filterMethod];
                delete ns.filterMethod;
                return ns;
            });
        }
    }, []);

    /* ══════════════════════════════════════════════════════════════
       SCHEMA FETCH & PROCESSING
       ══════════════════════════════════════════════════════════════ */
    useEffect(() => {
        const docUrl = PC().fetchUrl || '/papyrus-docs/api/schema';
        fetch(docUrl)
            .then(r => { if (!r.ok) throw new Error('Schema load failed'); return r.json(); })
            .then(data => { setRawSchema(data); setLoading(false); })
            .catch(e => { setError(e.message); setLoading(false); });
    }, []);

    const schema = useMemo(() => {
        if (!rawSchema.length) return [];

        let allRoutes = rawSchema.flatMap(g => g.routes);

        // Filter: regex + method
        let nameRe = null, ctrlRe = null;
        try { if (settings.filterNameRegex?.trim()) nameRe = new RegExp(settings.filterNameRegex.trim(), 'i'); } catch (e) {}
        try { if (settings.filterControllerRegex?.trim()) ctrlRe = new RegExp(settings.filterControllerRegex.trim(), 'i'); } catch (e) {}

        const allowedMethods = Array.isArray(settings.filterMethods) ? settings.filterMethods : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        allRoutes = allRoutes.filter(r => {
            if (r.methods && !r.methods.some(m => allowedMethods.includes(m))) return false;
            if (nameRe && (!r.routeName || !nameRe.test(r.routeName))) return false;
            if (ctrlRe && (!r.controllerName || !ctrlRe.test(r.controllerName))) return false;
            return true;
        });

        // Sort
        allRoutes.sort((a, b) => {
            if (settings.sortBy === 'routeName') return (a.routeName || '').localeCompare(b.routeName || '');
            if (settings.sortBy === 'method') {
                const aIdx = METHOD_SORT_ORDER[a.methods?.[0]] ?? 99;
                const bIdx = METHOD_SORT_ORDER[b.methods?.[0]] ?? 99;
                if (aIdx !== bIdx) return aIdx - bIdx;
                return (a.title || a.uri || '').localeCompare(b.title || b.uri || '');
            }
            return (a.title || a.uri || '').localeCompare(b.title || b.uri || '');
        });

        // Group
        const groupMap = {};
        const uriPatterns = PC().groupByPatterns || [];

        allRoutes.forEach(route => {
            let grpName, grpMeta = null;

            if (settings.groupBy === 'apiName') {
                grpName = (route.routeName && route.routeName.includes('.')) ? route.routeName.split('.')[0] : 'Other';
            } else if (settings.groupBy === 'controllerName') {
                grpName = route.controllerName || 'Closures / Unknown';
                grpMeta = route.controllerNamespace || '';
            } else if (settings.groupBy === 'uriPatterns' && uriPatterns.length) {
                // Config-driven URI pattern grouping
                let matched = false;
                for (const pattern of uriPatterns) {
                    try {
                        if (new RegExp(pattern.pattern || pattern, 'i').test(route.uri)) {
                            grpName = pattern.name || pattern;
                            matched = true;
                            break;
                        }
                    } catch (e) {}
                }
                if (!matched) grpName = 'Other';
            } else {
                grpName = '__all__';
            }

            if (!groupMap[grpName]) groupMap[grpName] = { name: grpName, namespace: grpMeta, routes: [] };
            if (grpMeta && !groupMap[grpName].namespace) groupMap[grpName].namespace = grpMeta;
            groupMap[grpName].routes.push(route);
        });

        return Object.values(groupMap).sort((a, b) => a.name.localeCompare(b.name));
    }, [rawSchema, settings]);

    /* ══════════════════════════════════════════════════════════════
       ROUTE SELECTION & HASH ROUTING
       ══════════════════════════════════════════════════════════════ */
    const handleRouteSelect = useCallback((route) => {
        setActiveRoute(route);
        window.history.pushState(null, '', '#' + rid(route));
    }, []);

    useEffect(() => {
        if (schema.length === 0) return;
        const hash = window.location.hash.replace(/^#/, '');
        let targetRoute = null;
        if (hash) {
            for (const group of schema) {
                targetRoute = group.routes.find(r => rid(r) === hash);
                if (targetRoute) break;
            }
        }
        if (!targetRoute && schema[0].routes.length) targetRoute = schema[0].routes[0];
        if (targetRoute && (!activeRoute || rid(activeRoute) !== rid(targetRoute))) {
            setActiveRoute(targetRoute);
            if (!hash) window.history.replaceState(null, '', '#' + rid(targetRoute));
        }
    }, [schema, activeRoute]);

    useEffect(() => {
        const onHashChange = () => {
            const hash = window.location.hash.replace(/^#/, '');
            if (hash && schema.length) {
                for (const group of schema) {
                    const found = group.routes.find(r => rid(r) === hash);
                    if (found) { setActiveRoute(found); break; }
                }
            }
        };
        window.addEventListener('hashchange', onHashChange);
        return () => window.removeEventListener('hashchange', onHashChange);
    }, [schema]);

    /* ══════════════════════════════════════════════════════════════
       FORM TREE LIFECYCLE (Hydration, Persistence)
       ══════════════════════════════════════════════════════════════ */
    useEffect(() => {
        if (!activeRoute) { setFormTree([]); setPathVals({}); return; }

        const routeKey = rid(activeRoute);
        let loadedState = null;
        try { const raw = window.localStorage.getItem(`papyrus_state_${routeKey}`); if (raw) loadedState = JSON.parse(raw); } catch (e) {}

        if (loadedState?.tree) {
            setFormTree(buildInitialTree(activeRoute.bodyParams || [], loadedState.tree));
        } else if (activeRoute.bodyParams && typeof activeRoute.bodyParams === 'object') {
            setFormTree(buildInitialTree(activeRoute.bodyParams, []));
        } else {
            setFormTree([]);
        }

        const initialPaths = {};
        pathParams(activeRoute.uri).forEach(({ name }) => {
            initialPaths[name] = (loadedState?.paths && loadedState.paths[name] !== undefined) ? loadedState.paths[name] : '';
        });
        setPathVals(initialPaths);

        // Header restoration
        if (settings.globalHeaders) {
            try {
                const globalRaw = window.localStorage.getItem('papyrus_global_headers');
                if (globalRaw) setCustomHeaders(JSON.parse(globalRaw));
                else if (loadedState?.headers !== undefined) setCustomHeaders(loadedState.headers);
                else resetHeaders();
            } catch (e) { console.warn(e); }
        } else {
            if (loadedState?.headers !== undefined) setCustomHeaders(loadedState.headers);
            else resetHeaders();
        }
    }, [activeRoute, settings.globalHeaders]);

    const resetHeaders = useCallback(() => {
        const defaultHdrs = PC().headers || {};
        const arr = Object.entries(defaultHdrs).map(([k, v]) => ({ key: k, value: String(v), active: true }));
        setCustomHeaders(arr);
    }, []);

    /* ── Reset State Handler ─────────────────────────────────────── */
    const resetFormState = useCallback(() => {
        if (!activeRoute) return;

        // 1. Deep clone the initial schema for Body Params
        if (activeRoute.bodyParams && typeof activeRoute.bodyParams === 'object') {
            const freshParams = structuredClone(activeRoute.bodyParams);
            setFormTree(resetTreeToDefaults(freshParams));
        } else {
            setFormTree([]);
        }

        // 2. Reset Path Params
        const initialPaths = {};
        pathParams(activeRoute.uri).forEach(({ name }) => {
            initialPaths[name] = '';
        });
        setPathVals(initialPaths);

        // 3. Reset Headers
        resetHeaders();
    }, [activeRoute, resetHeaders]);

    /* ── Save State Effect ───────────────────────────────────────── */
    useEffect(() => {
        if (!activeRoute) return;
        const routeKey = rid(activeRoute);
        const stateToSave = { tree: sanitizeTree(formTree), paths: pathVals };
        if (!settings.globalHeaders) {
            stateToSave.headers = customHeaders;
        } else {
            window.localStorage.setItem('papyrus_global_headers', JSON.stringify(customHeaders));
        }
        window.localStorage.setItem(`papyrus_state_${routeKey}`, JSON.stringify(stateToSave));
    }, [formTree, pathVals, customHeaders, activeRoute, settings.globalHeaders]);

    const onPathChange = useCallback((k, v) => setPathVals(p => ({ ...p, [k]: v })), []);
    const preparedForm = useMemo(() => compileTreeToPayload(formTree), [formTree]);

    /* ══════════════════════════════════════════════════════════════
       RENDER
       ══════════════════════════════════════════════════════════════ */
    if (loading) return (
        <div className="h-screen w-screen bg-[#0B1120] dark:bg-[#0B1120] bg-slate-50 flex items-center justify-center">
            <div className="text-center"><span className="text-amber-500 text-4xl font-brand animate-pulse block mb-3">¶</span><span className="text-slate-500 font-mono text-sm">Loading Papyrus…</span></div>
        </div>
    );

    if (error) return (
        <div className="h-screen w-screen bg-[#0B1120] dark:bg-[#0B1120] bg-slate-50 flex items-center justify-center">
            <div className="text-center text-rose-400 font-mono text-sm">Error: {error}</div>
        </div>
    );

    const activeId = activeRoute ? rid(activeRoute) : null;
    const config = PC();

    return (
        <div className="h-[100dvh] max-h-[100dvh] w-screen overflow-hidden bg-slate-50 dark:bg-[#0B1120] text-slate-800 dark:text-slate-300 flex flex-col transition-colors duration-200">
            {/* ── TOP NAVBAR (Fixed) ── */}
            <header className="fixed top-0 inset-x-0 h-14 bg-white dark:bg-[#0B1120] border-b border-slate-200 dark:border-slate-800/60 flex items-center justify-between px-4 z-50">
                <div className="flex items-center gap-3">
                    <button onClick={() => setMenuOpen(true)} className="lg:hidden text-slate-500 hover:text-amber-500 transition-colors">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <h1 className="font-brand font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        {config.faviconUrl ? <img src={config.faviconUrl} alt="Papyrus" className="w-5 h-5" /> : <span className="text-amber-500 text-lg">¶</span>} 
                        <span className="hidden sm:inline">{config.title || 'Papyrus'}</span>
                    </h1>
                </div>

                <div className="flex items-center gap-2 sm:gap-4">
                    {/* Export Buttons */}
                    <div className="hidden md:flex items-center gap-2 mr-2">
                        <a href="/papyrus-docs/export/openapi" download className="px-3 py-1.5 text-[11px] font-semibold tracking-wide bg-slate-100 dark:bg-slate-800/50 hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 rounded border border-slate-200 dark:border-slate-700/50 transition-colors flex items-center gap-1.5">
                            <svg className="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            OpenAPI / Swagger
                        </a>
                        <a href="/papyrus-docs/export/postman" download className="px-3 py-1.5 text-[11px] font-semibold tracking-wide bg-slate-100 dark:bg-slate-800/50 hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 rounded border border-slate-200 dark:border-slate-700/50 transition-colors flex items-center gap-1.5">
                            <svg className="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            Postman
                        </a>
                    </div>

                    {/* Theme Toggle */}
                    <button onClick={toggle} className="p-1.5 text-slate-500 hover:text-amber-500 dark:hover:text-amber-400 transition-colors rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title={`Switch to ${isDark ? 'Light' : 'Dark'} Mode`}>
                        {isDark ? (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        ) : (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        )}
                    </button>

                    {/* Settings Button */}
                    <button onClick={() => setSettingsOpen(true)} className="p-1.5 text-slate-500 hover:text-amber-500 dark:hover:text-amber-400 transition-colors rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Settings">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </button>

                    {/* Mobile Playground Toggle */}
                    <button onClick={() => setPlaygroundOpen(true)} className="lg:hidden text-amber-500 dark:text-amber-400 font-mono text-xs font-bold hover:text-amber-600 dark:hover:text-amber-300 transition-colors ml-2">TRY IT</button>
                </div>
            </header>

            {/* ── MAIN CONTENT AREA (Pushed down by pt-14) ── */}
            <div className="flex-1 flex overflow-hidden pt-14">
                <Sidebar schema={schema} activeId={activeId} onSelect={handleRouteSelect} open={menuOpen} onClose={() => setMenuOpen(false)} width={sidebarWidth} setWidth={setSidebarWidth} />
                <main className="flex-1 overflow-y-auto scroll-smooth custom-scrollbar relative bg-white dark:bg-[#0B1120]">
                    <div className="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-amber-500/40 to-transparent z-10" />
                    <DocSection route={activeRoute} formTree={formTree} setFormTree={setFormTree} pathVals={pathVals} onPathChange={onPathChange} schema={schema} onSelect={handleRouteSelect} onExecuteRequest={() => { setPlaygroundOpen(true); if (executeRef.current) executeRef.current(); }} executing={executing} onReset={resetFormState} />
                </main>
                <Playground route={activeRoute} formValues={preparedForm} pathVals={pathVals} open={playgroundOpen} onClose={() => setPlaygroundOpen(false)} customHeaders={customHeaders} setCustomHeaders={setCustomHeaders} settings={settings} setSettings={setSettings} width={playgroundWidth} setWidth={setPlaygroundWidth} onExecuteRef={executeRef} executing={executing} setExecuting={setExecuting} />
            </div>

            <SettingsModal open={settingsOpen} onClose={() => setSettingsOpen(false)} settings={settings} setSettings={setSettings} />
        </div>
    );
}

const root = ReactDOM.createRoot(document.getElementById('papyrus-app'));
root.render(<App />);
