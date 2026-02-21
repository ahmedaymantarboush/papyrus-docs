import React, { useState, useEffect, useMemo } from 'react';
import { JsonEditor, githubDarkTheme } from 'json-edit-react';
import DynamicField from '../form/DynamicField';
import { inputCls, btnSm } from '../../constants';

/**
 * HeadersEditor — Custom request headers editor.
 *
 * ARCHITECTURE: Uses the EXACT SAME recursive DynamicField engine as
 * the Body tab. This means headers support:
 *   ✅ DynamicField with Toggle, InlineKeyEditor, TypeSelector, ValidationBadges
 *   ✅ Infinite nesting via ObjectBuilder / ArrayBuilder
 *   ✅ Type morphing (string → object → array → etc.)
 *   ✅ Per-header enable/disable (Postman-style toggle)
 *
 * Modes: Form Builder (DynamicField engine) | Raw JSON Editor
 */
export default function HeadersEditor({ headers, setHeaders, rawMode, setRawMode }) {
    const [rawJson, setRawJson] = useState(() => {
        const obj = {};
        headers.forEach(h => { if (h.key?.trim() && h.active !== false) obj[h.key.trim()] = h.value ?? ''; });
        return obj;
    });

    // ── Convert headers array ↔ DynamicField tree ──────────────────
    // Headers are stored as [{key, value, active}] but DynamicField
    // expects [{key, type, value, enabled, children}]
    const headerTree = useMemo(() => {
        return headers.map(h => ({
            key: h.key || '',
            type: h.type || 'string',
            value: h.value ?? '',
            enabled: h.active !== false,
            children: h.children || undefined,
            options: h.options || undefined,
        }));
    }, [headers]);

    const updateHeaderFromTree = (idx, node) => {
        const h = [...headers];
        h[idx] = {
            key: node.key || '',
            value: node.value ?? '',
            active: node.enabled !== false,
            type: node.type || 'string',
            children: node.children || undefined,
            options: node.options || undefined,
        };
        setHeaders(h);
    };

    const removeHeader = (idx) => setHeaders(headers.filter((_, i) => i !== idx));

    const addHeader = () => setHeaders([...headers, { key: '', value: '', active: true, type: 'string' }]);

    // ── Mode switching ──────────────────────────────────────────────
    const toggleMode = () => {
        if (!rawMode) {
            const obj = {};
            headers.forEach(h => { if (h.key?.trim() && h.active !== false) obj[h.key.trim()] = h.value ?? ''; });
            setRawJson(obj);
            setRawMode(true);
        } else {
            const arr = Object.entries(rawJson || {}).map(([k, v]) => ({
                key: k, value: typeof v === 'object' ? JSON.stringify(v) : String(v), active: true, type: 'string'
            }));
            const inactive = headers.filter(h => h.active === false);
            setHeaders([...arr, ...inactive]);
            setRawMode(false);
        }
    };

    useEffect(() => {
        if (rawMode) {
            const arr = Object.entries(rawJson || {}).map(([k, v]) => ({
                key: k, value: typeof v === 'object' ? JSON.stringify(v) : String(v), active: true, type: 'string'
            }));
            const inactive = headers.filter(h => h.active === false);
            setHeaders([...arr, ...inactive]);
        }
    }, [rawJson]);

    return (
        <div className="space-y-4 pt-1">
            <div className="flex justify-between items-center bg-slate-100 dark:bg-slate-800/50 p-2 rounded-lg border border-slate-200 dark:border-slate-700/50">
                <span className="text-[11px] font-mono text-slate-500 dark:text-slate-400 font-medium">HEADERS CONFIGURATION</span>
                <div className="flex gap-2 items-center">
                    {rawMode && (
                        <button
                            onClick={(e) => {
                                navigator.clipboard.writeText(JSON.stringify(rawJson, null, 2));
                                const btn = e.currentTarget;
                                const originalHTML = btn.innerHTML;
                                btn.innerHTML = `<svg class="w-3.5 h-3.5 text-emerald-500 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Copied`;
                                setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
                            }}
                            className="text-[10px] uppercase font-mono tracking-wider px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors flex items-center"
                        >
                            <svg className="w-3.5 h-3.5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                            Copy JSON
                        </button>
                    )}
                    <button onClick={toggleMode} className="text-[10px] uppercase font-mono tracking-wider px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                        {rawMode ? 'Switch to Form Builder' : 'Raw JSON Editor'}
                    </button>
                </div>
            </div>

            {rawMode ? (
                <div className="bg-white dark:bg-[#0F172A] p-3 rounded-lg border border-slate-200 dark:border-slate-700/60 max-h-[300px] overflow-y-auto custom-scrollbar">
                    <JsonEditor data={rawJson} onUpdate={({ newData }) => setRawJson(newData)} theme={document.documentElement.classList.contains('dark') ? githubDarkTheme : undefined} restrictAdd={false} restrictEdit={false} restrictDelete={false} rootName="" className="text-[11px] font-mono leading-relaxed" />
                </div>
            ) : (
                <div className="space-y-1">
                    {headerTree.map((node, i) => (
                        <DynamicField
                            key={i}
                            node={node}
                            onChange={n => updateHeaderFromTree(i, n)}
                            onRemove={() => removeHeader(i)}
                            depth={0}
                        />
                    ))}
                    {headers.length === 0 && <div className="text-center py-6 text-slate-600 italic text-[11px] font-mono">No custom headers configured.</div>}
                    <button onClick={addHeader} className="text-[11px] text-amber-500/80 font-mono font-bold hover:text-amber-400 transition-colors flex items-center gap-1.5 border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 rounded-md w-max mt-3">
                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" /></svg>
                        ADD HEADER
                    </button>
                </div>
            )}
        </div>
    );
}
