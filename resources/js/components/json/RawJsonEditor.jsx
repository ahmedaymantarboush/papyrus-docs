import React, { useState, useMemo } from 'react';
import { JsonEditor, githubDarkTheme } from 'json-edit-react';
import { compileTreeToPayload, treeHasFiles } from '../../helpers/request';
import { hydrateFormTreeFromJson } from '../../helpers/schema';
import { btnSm } from '../../constants';

/**
 * RawJsonEditor — Read-Only JSON preview and Two-Way Sync Editor
 *
 * File Guard: If any formTree node contains a File value, the entire
 * JSON tab is disabled with a tooltip explanation.
 */
export default function RawJsonEditor({ formTree, setFormTree, activeTab, setActiveTab }) {
    const [localJson, setLocalJson] = useState(null);
    const [jsonError, setJsonError] = useState(null);
    const [dirty, setDirty] = useState(false);

    // Derive JSON from formTree (source of truth)
    const derivedJson = useMemo(() => compileTreeToPayload(formTree), [formTree]);

    // File guard check
    const hasFiles = useMemo(() => treeHasFiles(formTree), [formTree]);

    // When switching to JSON tab, snapshot the current derived state
    const handleTabSwitch = (tab) => {
        if (tab === 'json') {
            if (hasFiles) return; // Guard: don't switch
            setLocalJson(derivedJson);
            setJsonError(null);
            setDirty(false);
        }
        if (tab === 'form' && dirty && localJson !== null) {
            // Only apply JSON changes back to formTree if user actually edited
            applyJsonToTree();
        }
        setActiveTab(tab);
    };

    // Apply JSON edits → formTree directly
    const applyJsonToTree = (newData) => {
        if (!newData) return;
        if (typeof newData === 'object' && Object.keys(newData).length === 0 && formTree.length > 0) return;
        try {
            const hydrated = hydrateFormTreeFromJson(newData, formTree);
            setFormTree(hydrated);
            setJsonError(null);
            setDirty(false);
            setLocalJson(newData);
        } catch (err) {
            setJsonError('Failed to apply JSON: ' + err.message);
        }
    };

    return (
        <div className="mt-4">
            {/* Tab switcher */}
            <div className="flex items-center gap-2 mb-3">
                <div className="flex bg-slate-100 dark:bg-slate-900/60 p-0.5 rounded-lg border border-slate-200 dark:border-slate-800/60">
                    <button
                        onClick={() => handleTabSwitch('form')}
                        className={`px-3 py-1 text-[11px] font-medium rounded-md transition-all ${activeTab === 'form' ? 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}
                    >
                        Visual Form
                    </button>
                    <button
                        onClick={() => handleTabSwitch('json')}
                        disabled={hasFiles}
                        className={`px-3 py-1 text-[11px] font-medium rounded-md transition-all ${hasFiles ? 'text-slate-400 dark:text-slate-600 cursor-not-allowed' : activeTab === 'json' ? 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}
                        title={hasFiles ? 'JSON editing is disabled when the payload contains file uploads' : 'Edit raw JSON'}
                    >
                        Raw JSON
                        {hasFiles && <span className="ml-1 text-[8px]">🔒</span>}
                    </button>
                </div>

                {/* Actions (visible only in JSON mode) */}
                {activeTab === 'json' && (
                    <div className="flex items-center gap-2">
                        <button
                            onClick={(e) => {
                                navigator.clipboard.writeText(JSON.stringify(localJson !== null ? localJson : derivedJson, null, 2));
                                const btn = e.currentTarget;
                                const originalHTML = btn.innerHTML;
                                btn.innerHTML = `<svg class="w-4 h-4 text-emerald-500 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Copied`;
                                setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
                            }}
                            className={`${btnSm} text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-800`}
                        >
                            <svg className="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                            Copy JSON
                        </button>
                    </div>
                )}
            </div>

            {/* JSON Editor */}
            {activeTab === 'json' && (
                <div className="bg-slate-50 dark:bg-[#0F172A] p-3 rounded-lg border border-slate-200 dark:border-slate-700/60 max-h-[400px] overflow-y-auto custom-scrollbar">
                    {jsonError && (
                        <div className="mb-3 px-3 py-2 bg-rose-500/10 border border-rose-500/30 rounded-lg text-rose-500 dark:text-rose-400 text-xs font-mono">
                            {jsonError}
                        </div>
                    )}
                    <JsonEditor
                        data={localJson ?? derivedJson}
                        onUpdate={({ newData }) => {
                            applyJsonToTree(newData);
                        }}
                        theme={document.documentElement.classList.contains('dark') ? githubDarkTheme : undefined}
                        restrictAdd={false}
                        restrictEdit={false}
                        restrictDelete={false}
                        rootName=""
                        className="text-[11px] font-mono leading-relaxed"
                    />
                </div>
            )}
        </div>
    );
}
