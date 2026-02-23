import React from 'react';
import { selectCls, inputCls, btnSm, METHOD_GLOW } from '../../constants';

/**
 * SettingsModal — Configuration modal for sorting, grouping, filtering,
 * and preferences.
 *
 * Adds 'Group By: URI Patterns' option using PapyrusConfig.groupByPatterns.
 */
export default function SettingsModal({ open, onClose, settings, setSettings }) {
    if (!open) return null;

    const clearStorage = () => {
        if (confirm('Are you sure you want to clear all storage? This disables save responses, resets configurations, and wipes cached data.')) {
            window.localStorage.clear();
            window.location.reload();
        }
    };

    const update = (k, v) => setSettings(s => ({ ...s, [k]: v }));

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 backdrop-blur-sm bg-black/60">
            <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-700/60 rounded-xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-full animate-in fade-in zoom-in-95 duration-200">
                <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-[#0B1120]">
                    <h2 className="font-brand font-bold text-slate-800 dark:text-slate-100 text-lg">Papyrus Settings</h2>
                    <button onClick={onClose} className="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div className="p-5 overflow-y-auto space-y-6">
                    <div className="space-y-4">
                        <h3 className="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest border-b border-slate-200 dark:border-slate-800 pb-2">Sorting & Grouping</h3>
                        <div className="flex flex-col gap-6">
                            <div>
                                <label className="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-2.5 uppercase tracking-wide">Group By</label>
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        { value: 'default', label: 'Default' },
                                        { value: 'apiName', label: 'API Name' },
                                        { value: 'controllerName', label: 'Controller' },
                                        { value: 'uriPatterns', label: 'URI Patterns' }
                                    ].map(opt => (
                                        <label key={opt.value} className={`flex items-center gap-2 px-3.5 py-2 rounded-full border cursor-pointer transition-all ${settings.groupBy === opt.value ? 'bg-amber-500 border-amber-500 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-amber-500/50 hover:text-amber-600 dark:hover:text-amber-400'}`}>
                                            <input type="radio" name="groupBy" value={opt.value} checked={settings.groupBy === opt.value} onChange={e => update('groupBy', e.target.value)} className="sr-only" />
                                            <span className="text-[12px] font-medium">{opt.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <label className="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-2.5 uppercase tracking-wide">Sort By</label>
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        { value: 'default', label: 'Name (Title)' },
                                        { value: 'routeName', label: 'Route Name' },
                                        { value: 'method', label: 'HTTP Method' }
                                    ].map(opt => (
                                        <label key={opt.value} className={`flex items-center gap-2 px-3.5 py-2 rounded-full border cursor-pointer transition-all ${settings.sortBy === opt.value ? 'bg-amber-500 border-amber-500 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-amber-500/50 hover:text-amber-600 dark:hover:text-amber-400'}`}>
                                            <input type="radio" name="sortBy" value={opt.value} checked={settings.sortBy === opt.value} onChange={e => update('sortBy', e.target.value)} className="sr-only" />
                                            <span className="text-[12px] font-medium">{opt.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h3 className="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest border-b border-slate-200 dark:border-slate-800 pb-2">Filters</h3>
                        <div>
                            <label className="block text-xs font-mono text-slate-500 dark:text-slate-400 mb-2">HTTP Methods</label>
                            <div className="flex flex-wrap gap-2">
                                {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map(m => {
                                    const isActive = (settings.filterMethods || []).includes(m);
                                    const badgeColorClass = isActive
                                        ? (METHOD_GLOW[m] || 'text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800')
                                        : 'text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 hover:border-slate-400 dark:hover:border-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700';
                                    return (
                                        <button
                                            key={m}
                                            onClick={() => {
                                                const current = settings.filterMethods || [];
                                                if (isActive) update('filterMethods', current.filter(x => x !== m));
                                                else update('filterMethods', [...current, m]);
                                            }}
                                            className={`px-3 py-1.5 rounded-md text-[11px] font-mono font-bold tracking-wider border transition-all duration-200 ${badgeColorClass}`}
                                        >{m}</button>
                                    );
                                })}
                            </div>
                        </div>
                        <div>
                            <label className="block text-xs font-mono text-slate-500 dark:text-slate-400 mb-1.5">Route Name Convention (Regex)</label>
                            <input type="text" className={inputCls} placeholder="e.g. ^users\." value={settings.filterNameRegex} onChange={e => update('filterNameRegex', e.target.value)} />
                        </div>
                        <div>
                            <label className="block text-xs font-mono text-slate-500 dark:text-slate-400 mb-1.5">Controller Name (Regex)</label>
                            <input type="text" className={inputCls} placeholder="e.g. UserController" value={settings.filterControllerRegex} onChange={e => update('filterControllerRegex', e.target.value)} />
                        </div>
                        <div>
                            <label className="block text-xs font-mono text-slate-500 dark:text-slate-400 mb-1.5">Route URI (Regex)</label>
                            <input type="text" className={inputCls} placeholder="e.g. ^api/v1/" value={settings.filterUriRegex || ''} onChange={e => update('filterUriRegex', e.target.value)} />
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h3 className="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest border-b border-slate-200 dark:border-slate-800 pb-2">Preferences</h3>
                        <label className="flex items-center justify-between cursor-pointer group">
                            <span className="text-sm font-mono text-slate-700 dark:text-slate-300 group-hover:text-amber-500 dark:group-hover:text-amber-400 transition-colors">Apply custom headers globally to all endpoints</span>
                            <div className="relative inline-flex items-center">
                                <input type="checkbox" className="sr-only peer" checked={settings.globalHeaders} onChange={e => update('globalHeaders', e.target.checked)} />
                                <div className="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                            </div>
                        </label>
                        <label className="flex items-center justify-between cursor-pointer group">
                            <span className="text-sm font-mono text-slate-700 dark:text-slate-300 group-hover:text-amber-500 dark:group-hover:text-amber-400 transition-colors">Save Response locally to preserve state</span>
                            <div className="relative inline-flex items-center">
                                <input type="checkbox" className="sr-only peer" checked={settings.saveResponses} onChange={e => update('saveResponses', e.target.checked)} />
                                <div className="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                            </div>
                        </label>
                    </div>
                </div>

                <div className="px-5 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-[#0B1120] flex justify-between items-center">
                    <button onClick={clearStorage} className={`${btnSm} font-mono px-4 py-2 border-rose-500/30 text-rose-500 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10`}>Clear Local Storage</button>
                    <button onClick={onClose} className={`${btnSm} font-mono px-4 py-2 border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800`}>Done</button>
                </div>
            </div>
        </div>
    );
}
