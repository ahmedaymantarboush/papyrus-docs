import React, { useState } from 'react';
import MethodBadge from '../common/MethodBadge';
import DynamicField from '../form/DynamicField';
import RawJsonEditor from '../json/RawJsonEditor';
import { inputCls, pathParams, rid, btnSm } from '../../constants';

/**
 * DocSection — Center documentation panel for the selected endpoint.
 *
 * Displays: method badge, title, description, URL box, path parameters,
 * request payload (Visual Form with DynamicField engine + Raw JSON toggle),
 * Reset to Default button, and Prev/Next navigation.
 */
export default function DocSection({ route, formTree, setFormTree, queryTree, setQueryTree, pathVals, onPathChange, schema, onSelect, onExecuteRequest, executing, onReset }) {
    const [formTab, setFormTab] = useState('form'); // 'form' | 'json'
    const [queryTab, setQueryTab] = useState('form'); // 'form' | 'json'

    if (!route) return <div className="flex-1 flex items-center justify-center text-slate-600"><p className="font-brand italic text-lg opacity-40">Select an endpoint to explore…</p></div>;

    const pp = pathParams(route.uri);

    // Reset to Default handler
    const handleReset = () => {
        if (onReset) onReset();
        setFormTab('form');
    };

    return (
        <div className="max-w-3xl mx-auto px-5 lg:px-10 py-10 pb-24 relative">
            <div className="mb-8 flex items-start justify-between gap-6">
                <div>
                    <div className="flex items-center gap-3 mb-3"><MethodBadge method={route.methods[0]} /></div>
                    <h2 className="text-2xl lg:text-3xl font-brand font-bold text-slate-800 dark:text-slate-50 leading-tight mb-3">{route.title}</h2>
                    <p className="text-slate-600 dark:text-slate-400 leading-relaxed">{route.description || 'No description available.'}</p>
                </div>
                <button 
                    onClick={onExecuteRequest}
                    disabled={executing}
                    className={`shrink-0 hidden sm:flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl shadow-lg transition-all ${executing ? 'bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-wait shadow-none w-[150px]' : 'bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 dark:from-amber-600 dark:to-amber-500 dark:hover:from-amber-500 dark:hover:to-amber-400 text-slate-900 font-bold shadow-amber-500/20 dark:shadow-amber-800/20 active:scale-95'}`}
                    title="Open Playground and Send Request"
                >
                    {executing ? (
                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                    ) : (
                        <><svg className="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clipRule="evenodd" /></svg>
                        <span>Send Request</span></>
                    )}
                </button>
            </div>

            {/* URL box */}
            <div className="mb-10 group relative">
                <div className="absolute -inset-0.5 bg-gradient-to-r from-amber-500/20 to-transparent rounded-xl blur opacity-0 group-hover:opacity-100 transition duration-700" />
                <div className="relative flex items-center bg-white dark:bg-[#0F172A] rounded-xl border border-slate-200 dark:border-slate-800/60 px-4 py-3.5 font-mono text-sm shadow-xl overflow-x-auto justify-between group/url">
                    <div className="flex items-center">
                        <MethodBadge method={route.methods[0]} />
                        <span className="text-slate-300 dark:text-slate-500 ml-3 select-none">/</span>
                        <span className="text-amber-600 dark:text-amber-400 ml-0.5">{route.uri}</span>
                    </div>
                    <button 
                        onClick={(e) => {
                            navigator.clipboard.writeText(`/${route.uri}`);
                            const btn = e.currentTarget;
                            const originalHTML = btn.innerHTML;
                            btn.innerHTML = `<svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
                            setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
                        }} 
                        className="ml-4 text-slate-400 hover:text-amber-500 transition-colors opacity-0 group-hover/url:opacity-100" 
                        title="Copy Endpoint"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    </button>
                </div>
            </div>

            {/* Path params */}
            {pp.length > 0 && (
                <div className="mb-10">
                    <h3 className="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-[0.15em] mb-5 pb-2 border-b border-slate-200 dark:border-slate-800/60">Path Parameters</h3>
                    <div className="space-y-4 text-sm font-mono text-slate-600 dark:text-slate-400">
                        {pp.map(({ name, optional }) => (
                            <div key={name} className="flex flex-col gap-1.5 border-l-2 border-slate-200 dark:border-slate-700/30 pl-3">
                                <div><span className="text-amber-600 dark:text-amber-400">{name}</span> {optional ? '' : <span className="text-rose-500 dark:text-rose-400">*</span>}</div>
                                <input type="text" className={inputCls} placeholder={`{${name}${optional ? '?' : ''}}`} value={pathVals[name] || ''} onChange={e => onPathChange(name, e.target.value)} />
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Query Parameters */}
            <div className="mb-10">
                <div className="flex items-center justify-between mb-5 pb-2 border-b border-slate-200 dark:border-slate-800/60">
                    <h3 className="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-[0.15em]">Query Parameters</h3>
                </div>

                <RawJsonEditor formTree={queryTree} setFormTree={setQueryTree} activeTab={queryTab} setActiveTab={setQueryTab} />

                {queryTab === 'form' && (
                    <div className="space-y-1 mt-4">
                        {queryTree.map((node, i) => (
                            <DynamicField
                                key={node.key || i}
                                node={node}
                                onChange={n => {
                                    const nt = [...queryTree];
                                    nt[i] = n;
                                    setQueryTree(nt);
                                }}
                                onRemove={() => setQueryTree(queryTree.filter((_, idx) => idx !== i))}
                                excludeTypes={['file']}
                            />
                        ))}
                        <button
                            onClick={() => setQueryTree([...queryTree, { key: `param_${queryTree.length}`, type: 'text', value: '', enabled: true }])}
                            className="text-[11px] text-amber-500/80 font-mono font-bold hover:text-amber-400 transition-colors flex items-center gap-1.5 border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 rounded-md w-max mt-3"
                        >
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" /></svg>
                            ADD PARAM
                        </button>
                    </div>
                )}
            </div>

            {/* Request Payload */}
            {formTree && formTree.length > 0 && (
                <div className="mb-10">
                    <div className="flex items-center justify-between mb-5 pb-2 border-b border-slate-800/60">
                        <h3 className="text-xs font-bold text-slate-300 uppercase tracking-[0.15em]">Request Payload</h3>
                        <button onClick={handleReset} className={`${btnSm} text-slate-500 border-slate-700 hover:text-amber-400 hover:border-amber-500/30 flex items-center gap-1`} title="Reset all fields to schema defaults">
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            Reset
                        </button>
                    </div>

                    {/* Two-Way Sync: Visual Form ↔ Raw JSON */}
                    <RawJsonEditor formTree={formTree} setFormTree={setFormTree} activeTab={formTab} setActiveTab={setFormTab} />

                    {/* Visual Form (shown when formTab === 'form') */}
                    {formTab === 'form' && (
                        <div className="space-y-1 mt-4">
                            {formTree.map((node, i) => (
                                <DynamicField
                                    key={node.key || i}
                                    node={node}
                                    onChange={n => {
                                        const nt = [...formTree];
                                        nt[i] = n;
                                        setFormTree(nt);
                                    }}
                                    onRemove={() => setFormTree(formTree.filter((_, idx) => idx !== i))}
                                />
                            ))}
                            <button
                                onClick={() => setFormTree([...formTree, { key: `new_prop_${formTree.length}`, type: 'text', value: '', enabled: true }])}
                                className="text-[11px] text-amber-500/80 font-mono font-bold hover:text-amber-400 transition-colors flex items-center gap-1.5 border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 rounded-md w-max mt-3"
                            >
                                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" /></svg>
                                ADD PROPERTY
                            </button>
                        </div>
                    )}
                </div>
            )}

            {/* Previous / Next Navigation */}
            {schema && (() => {
                const allRoutes = schema.flatMap(g => g.routes);
                const currentIdx = allRoutes.findIndex(r => rid(r) === rid(route));
                const prev = currentIdx > 0 ? allRoutes[currentIdx - 1] : null;
                const next = currentIdx < allRoutes.length - 1 ? allRoutes[currentIdx + 1] : null;
                return (prev || next) ? (
                    <div className={`mt-12 pt-8 border-t border-slate-200 dark:border-slate-800/50 grid gap-4 ${prev && next ? 'grid-cols-2' : 'grid-cols-1'}`}>
                        {prev && (
                            <button onClick={() => onSelect(prev)} className="group text-left p-4 rounded-xl border border-slate-200 dark:border-slate-800/60 bg-slate-50 dark:bg-slate-900/30 hover:bg-slate-100 dark:hover:bg-slate-800/40 hover:border-slate-300 dark:hover:border-slate-700 transition-all duration-200">
                                <span className="text-[10px] font-mono text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-1.5 mb-2">
                                    <svg className="w-3 h-3 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>Previous
                                </span>
                                <div className="flex items-center gap-2 mb-1"><MethodBadge method={prev.methods[0]} /></div>
                                <p className="text-sm text-slate-700 dark:text-slate-300 group-hover:text-amber-500 dark:group-hover:text-amber-400 font-medium transition-colors truncate">{prev.title || prev.uri}</p>
                                <p className="text-[11px] font-mono text-slate-400 dark:text-slate-600 truncate mt-0.5">/{prev.uri}</p>
                            </button>
                        )}
                        {next && (
                            <button onClick={() => onSelect(next)} className={`group text-right p-4 rounded-xl border border-slate-200 dark:border-slate-800/60 bg-slate-50 dark:bg-slate-900/30 hover:bg-slate-100 dark:hover:bg-slate-800/40 hover:border-slate-300 dark:hover:border-slate-700 transition-all duration-200 ${!prev ? 'col-start-2' : ''}`}>
                                <span className="text-[10px] font-mono text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-end gap-1.5 mb-2">
                                    Next<svg className="w-3 h-3 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                                </span>
                                <div className="flex items-center justify-end gap-2 mb-1"><MethodBadge method={next.methods[0]} /></div>
                                <p className="text-sm text-slate-700 dark:text-slate-300 group-hover:text-amber-500 dark:group-hover:text-amber-400 font-medium transition-colors truncate">{next.title || next.uri}</p>
                                <p className="text-[11px] font-mono text-slate-400 dark:text-slate-600 truncate mt-0.5">/{next.uri}</p>
                            </button>
                        )}
                    </div>
                ) : null;
            })()}
        </div>
    );
}
