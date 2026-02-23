import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import HeadersEditor from '../headers/HeadersEditor';
import { generateSnippet } from '../../helpers/snippets';
import { prepareRequest } from '../../helpers/request';
import { JsonEditor, githubLightTheme, githubDarkTheme } from 'json-edit-react';
import { rid, pathParams, SNIPPET_LANGS, PC } from '../../constants';

/**
 * Playground — Right-column panel for API testing.
 *
 * Tabs: Headers | Snippet | Response
 * Features: Resizable width, code snippets (4 languages), request execution,
 * response viewer with collapsible headers, default response badges.
 *
 * Config consumption: defaultResponses from PapyrusConfig.
 * 
 */
export default function Playground({ route, formValues, queryValues, pathVals, open, onClose, customHeaders, setCustomHeaders, settings, setSettings, width, setWidth, onExecuteRef, executing, setExecuting }) {
    const [tab, setTab] = useState('snippet');
    const [lang, setLang] = useState('curl');
    const [response, setResponse] = useState(null);
    const [isFromCache, setIsFromCache] = useState(false);
    const [showRH, setShowRH] = useState(false);
    const pgRef = useRef(null);
    const iframeRef = useRef(null);

    const handleIframeLoad = () => {
        if (!iframeRef.current) return;
        try {
            const iframe = iframeRef.current;
            const doc = iframe.contentDocument || iframe.contentWindow?.document;
            if (doc?.body) {
                const height = Math.max(doc.body.scrollHeight, doc.documentElement?.scrollHeight || 0);
                iframe.style.height = `${Math.min(Math.max(height + 20, 200), 800)}px`;
            }
        } catch (err) {
            console.warn('Could not auto-resize iframe due to cross-origin policies.');
        }
    };

    const startResizing = useCallback((mouseDownEvent) => {
        mouseDownEvent.preventDefault();
        const startWidth = pgRef.current.getBoundingClientRect().width;
        const startX = mouseDownEvent.clientX;
        const isDesktop = window.innerWidth >= 1024;

        const onMouseMove = (moveEvent) => {
            const delta = startX - moveEvent.clientX;
            let maxW;
            if (isDesktop) {
                const sidebarEl = document.querySelector('[data-panel="sidebar"]');
                const sidebarW = sidebarEl ? sidebarEl.getBoundingClientRect().width : 0;
                maxW = Math.max(300, window.innerWidth - sidebarW - 300);
            } else {
                maxW = Math.floor(window.innerWidth * 0.9);
            }
            const newWidth = Math.max(300, Math.min(maxW, startWidth + delta));
            setWidth(newWidth);
        };
        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            document.body.style.cursor = 'default';
            document.body.style.userSelect = '';
        };
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    }, [setWidth]);

    useEffect(() => {
        if (route) {
            const routeKey = rid(route);
            if (settings.saveResponses) {
                try {
                    const saved = window.localStorage.getItem('papyrus_res_' + routeKey);
                    if (saved) {
                        setResponse(JSON.parse(saved));
                        setIsFromCache(true);
                        setTab('response');
                        return;
                    }
                } catch (e) { console.warn(e); }
            }
        }
        setResponse(null);
        setIsFromCache(false);
        setTab('snippet');
    }, [route, settings.saveResponses]);

    // moved down to prevent TDZ error

    const headerObj = useMemo(() => {
        const o = {};
        customHeaders.forEach(({ key, value, active }) => {
            if (key.trim() && active !== false) {
                o[key.trim()] = (typeof value === 'object' && value !== null) ? JSON.stringify(value) : value;
            }
        });
        return o;
    }, [customHeaders]);

    const execute = useCallback(async () => {
        if (!route) return;
        const missing = pathParams(route.uri).filter(p => !p.optional && !pathVals[p.name]).map(p => p.name);
        if (missing.length) { alert(`Missing path param(s): ${missing.join(', ')}`); return; }

        setExecuting(true);
        setIsFromCache(false);
        setTab('response');

        try {
            const { url, method: reqMethod, hdrs, body } = prepareRequest(route, formValues, pathVals, headerObj, queryValues);
            const finalHdrs = { ...hdrs };
            Object.keys(headerObj).forEach(k => finalHdrs[k] = headerObj[k]);

            const reqInit = { method: reqMethod, headers: finalHdrs };
            if (body) reqInit.body = body;

            const start = performance.now();
            const res = await fetch(url, reqInit);
            const elapsed = Math.round(performance.now() - start);

            let data;
            const text = await res.text();
            try { data = JSON.parse(text); } catch { data = text; }

            const rh = {};
            res.headers.forEach((v, k) => { rh[k] = v; });

            const resultObj = { status: res.status, statusText: res.statusText, data, headers: rh, time: elapsed };
            setResponse(resultObj);

            if (settings.saveResponses) {
                try { window.localStorage.setItem('papyrus_res_' + rid(route), JSON.stringify(resultObj)); } catch(e) { console.warn('Failed to save response', e); }
            }
        } catch (e) {
            setResponse({ status: 0, statusText: 'Network Error', data: { error: e.message }, headers: {}, time: 0 });
        } finally {
            setExecuting(false);
        }
    }, [route, formValues, queryValues, pathVals, headerObj, settings.saveResponses]);

    useEffect(() => {
        if (onExecuteRef) {
            onExecuteRef.current = () => execute();
        }
    }, [onExecuteRef, execute]);

    const tabs = [
        { id: 'headers', label: 'Headers' },
        { id: 'snippet', label: 'Snippet' },
        { id: 'response', label: 'Response', dot: response ? (response.status >= 200 && response.status < 300 ? 'bg-emerald-400' : 'bg-rose-400') : null },
    ];

    const defaultResponses = PC().defaultResponses || [];

    return (
        <>
            {open && <div className="fixed inset-0 bg-black/60 z-40 lg:hidden backdrop-blur-sm" onClick={onClose} />}
            <aside data-panel="playground" ref={pgRef} style={{ width: width + 'px' }} className={`fixed top-14 right-0 h-[calc(100vh-3.5rem)] shrink-0 max-w-full z-40 bg-slate-50 dark:bg-[#0F172A] border-l border-slate-200 dark:border-slate-800/60 flex flex-col transform transition-transform duration-300 ease-out lg:static lg:translate-x-0 ${open ? 'translate-x-0 shadow-2xl shadow-black/30' : 'translate-x-full lg:translate-x-0'}`}>
                <div onMouseDown={startResizing} className="absolute top-0 left-0 bottom-0 w-2 cursor-col-resize hover:bg-amber-500/20 active:bg-amber-500/40 z-50 transition-colors -ml-1" />

                <div className="shrink-0 border-b border-slate-200 dark:border-slate-800/40 flex items-center gap-2 px-3 py-3 object-top">
                    <button className="lg:hidden text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 mr-1" onClick={onClose}>
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div className="flex-1 flex bg-slate-100 dark:bg-slate-900/60 p-0.5 rounded-lg border border-slate-200 dark:border-slate-800/60">
                        {tabs.map(t => (
                            <button key={t.id} onClick={() => setTab(t.id)} className={`flex-1 py-1.5 text-[11px] font-medium rounded-md transition-all flex items-center justify-center gap-1.5 ${tab === t.id ? 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 shadow-sm border border-slate-200/50 dark:border-transparent' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}>
                                {t.dot && <span className={`w-1.5 h-1.5 rounded-full ${t.dot}`} />}
                                {t.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-5 custom-scrollbar">
                    {!route ? (
                        <div className="h-full flex items-center justify-center opacity-20"><span className="text-6xl text-slate-700 font-brand">¶</span></div>
                    ) : (
                        <>
                            {tab === 'snippet' && (
                                <div className="space-y-4">
                                    <div className="flex gap-3 border-b border-slate-200 dark:border-slate-800/60 pb-2 overflow-x-auto">
                                        {SNIPPET_LANGS.map(l => (
                                            <button key={l} onClick={() => setLang(l)} className={`text-[11px] font-mono uppercase tracking-wider pb-1 border-b-2 transition-colors whitespace-nowrap ${lang === l ? 'text-amber-600 dark:text-amber-400 border-amber-500' : 'text-slate-500 border-transparent hover:text-slate-700 dark:hover:text-slate-300'}`}>{l}</button>
                                        ))}
                                    </div>
                                    <div className="bg-slate-50 dark:bg-[#1E293B] rounded-lg border border-slate-200 dark:border-slate-700/40 p-4 overflow-x-auto shadow-inner relative group/snippet">
                                        <button 
                                            onClick={(e) => {
                                                navigator.clipboard.writeText(generateSnippet(lang, route, formValues, pathVals, headerObj, queryValues));
                                                const btn = e.currentTarget;
                                                const originalHTML = btn.innerHTML;
                                                btn.innerHTML = `<svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
                                                setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
                                            }}
                                            className="absolute top-2 right-2 p-1.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-amber-500 dark:hover:text-amber-400 opacity-0 group-hover/snippet:opacity-100 transition-opacity" 
                                            title="Copy Snippet"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                        </button>
                                        <pre className="text-[12px] font-mono text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap break-all">{generateSnippet(lang, route, formValues, pathVals, headerObj, queryValues)}</pre>
                                    </div>
                                </div>
                            )}

                            {tab === 'headers' && <HeadersEditor headers={customHeaders} setHeaders={setCustomHeaders} rawMode={settings.headerEditorMode === 'json'} setRawMode={(isJson) => setSettings(s => ({...s, headerEditorMode: isJson ? 'json' : 'form'}))} />}

                            {tab === 'response' && (
                                <div className="space-y-4">
                                    {/* Default Response Badges (from config) */}
                                    {defaultResponses.length > 0 && !response && (
                                        <div className="flex flex-wrap gap-1.5 mb-4">
                                            <span className="text-[10px] font-mono text-slate-500 dark:text-slate-600 mr-1">Expected:</span>
                                            {defaultResponses.map(code => (
                                                <span key={code} className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono border ${parseInt(code) >= 200 && parseInt(code) < 300 ? 'text-emerald-600 dark:text-emerald-400 border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/5' : parseInt(code) >= 400 ? 'text-rose-600 dark:text-rose-400 border-rose-500/20 bg-rose-50 dark:bg-rose-500/5' : 'text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-600/20 bg-slate-100 dark:bg-slate-700/20'}`}>
                                                    {code}
                                                </span>
                                            ))}
                                        </div>
                                    )}

                                    {response ? (
                                        <>
                                            {/* Cached response indicator */}
                                            {isFromCache && (
                                                <div className="flex items-center gap-2 px-3 py-2 mb-3 rounded-lg border border-amber-500/20 bg-amber-50 dark:bg-amber-500/5 text-amber-700 dark:text-amber-400">
                                                    <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                                    <span className="text-[11px] font-medium">Loaded from local storage — send a new request to refresh.</span>
                                                </div>
                                            )}

                                            <div className="flex items-center justify-between">
                                                <div className={`flex items-center gap-2 text-sm font-mono font-bold ${response.status >= 200 && response.status < 300 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
                                                    <span className={`w-2 h-2 rounded-full ${isFromCache ? '' : 'animate-pulse'} ${response.status >= 200 && response.status < 300 ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                                                    {response.status} {response.statusText}
                                                </div>
                                                <span className="text-[11px] text-slate-500 font-mono">{isFromCache ? 'cached' : `${response.time}ms`}</span>
                                            </div>

                                            {Object.keys(response.headers).length > 0 && (
                                                <div className="border border-slate-200 dark:border-slate-800/40 rounded-lg overflow-hidden">
                                                    <button onClick={() => setShowRH(!showRH)} className="w-full flex items-center justify-between px-3 py-2 bg-slate-100/50 dark:bg-slate-900/40 text-[11px] font-mono text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                                                        <span>Response Headers ({Object.keys(response.headers).length})</span>
                                                        <svg className={`w-3.5 h-3.5 transition-transform ${showRH ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                                                    </button>
                                                    {showRH && (
                                                        <div className="px-3 py-2 max-h-40 overflow-y-auto border-t border-slate-200 dark:border-slate-800/40 bg-slate-50 dark:bg-[#1E293B]/50">
                                                            {Object.entries(response.headers).map(([k, v]) => (
                                                                <div key={k} className="flex gap-2 py-0.5 text-[11px] font-mono"><span className="text-amber-600 dark:text-amber-400/70 shrink-0">{k}:</span><span className="text-slate-600 dark:text-slate-400 break-all">{v}</span></div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            <div className="w-full bg-slate-50 dark:bg-[#1E293B] rounded-lg border border-slate-200 dark:border-slate-700/40 overflow-hidden shadow-inner">
                                                <div className="w-full px-4 py-2 bg-slate-100/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700/40"><span className="text-[10px] text-slate-500 font-mono">Response Body</span></div>
                                                <div className="w-full p-2 custom-scrollbar overflow-x-auto min-h-[100px]">
                                                    {typeof response.data === 'string' ? (() => {
                                                        const ct = (response.headers['content-type'] || '').toLowerCase();
                                                        const isHtml = ct.includes('text/html') || /^\s*<!doctype\s+html/i.test(response.data) || /^\s*<html/i.test(response.data);
                                                        if (isHtml) {
                                                            return (
                                                                <iframe
                                                                    ref={iframeRef}
                                                                    srcDoc={response.data}
                                                                    sandbox="allow-same-origin allow-scripts"
                                                                    title="HTML Response Preview"
                                                                    className="w-full border-0 rounded bg-white"
                                                                    style={{ width: '100%', minHeight: '200px' }}
                                                                    onLoad={handleIframeLoad}
                                                                />
                                                            );
                                                        }
                                                        return (
                                                            <pre className="p-2 text-[12px] font-mono text-slate-700 dark:text-slate-300 whitespace-pre-wrap break-all leading-relaxed">
                                                                {response.data}
                                                            </pre>
                                                        );
                                                    })() : (
                                                        <JsonEditor
                                                            data={response.data}
                                                            theme={document.documentElement.classList.contains('dark') ? githubDarkTheme : githubLightTheme}
                                                            restrictEdit={true}
                                                            restrictAdd={true}
                                                            restrictDelete={true}
                                                            restrictDrag={true}
                                                            restrictTypeSelection={true}
                                                            rootName=""
                                                            collapse={2}
                                                            className="w-full text-[13px] font-mono bg-transparent !font-mono"
                                                        />
                                                    )}
                                                </div>
                                            </div>
                                        </>
                                    ) : (
                                        <div className="text-center py-16 text-slate-600 italic text-sm">Send a request to see the response.</div>
                                    )}
                                </div>
                            )}
                        </>
                    )}
                </div>

                {route && (
                    <div className="shrink-0 p-4 border-t border-slate-200 dark:border-slate-800/40 bg-slate-50 dark:bg-[#0F172A]">
                        <button onClick={execute} disabled={executing} className={`w-full py-3 font-bold rounded-xl transition-all duration-200 shadow-lg flex items-center justify-center gap-2 ${executing ? 'bg-slate-200 dark:bg-slate-700 text-slate-400 cursor-wait' : 'bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 dark:from-amber-600 dark:to-amber-500 dark:hover:from-amber-500 dark:hover:to-amber-400 text-slate-900 shadow-amber-500/20 dark:shadow-amber-800/20 active:scale-[0.98]'}`}>
                            {executing ? (
                                <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                            ) : (
                                <><svg className="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clipRule="evenodd" /></svg><span>Send Request</span></>
                            )}
                        </button>
                    </div>
                )}
            </aside>
        </>
    );
}
