import React, { useState } from 'react';
import { JsonEditor, githubLightTheme, githubDarkTheme } from 'json-edit-react';

export default function RouteResponses({ responses }) {
    if (!responses || Object.keys(responses).length === 0) return null;

    const statusCodes = Object.keys(responses).sort((a, b) => parseInt(a) - parseInt(b));
    const [activeStatus, setActiveStatus] = useState(statusCodes[0]);
    const [activeTab, setActiveTab] = useState('schema');

    if (!activeStatus || !responses[activeStatus]) return null;

    const data = responses[activeStatus];
    const hasSchema = data.schema && Array.isArray(data.schema) && data.schema.length > 0;
    const hasExample = data.responseExample !== null && data.responseExample !== undefined;

    // Default to 'example' tab if schema doesn't exist but example does, or auto-switch if navigating
    React.useEffect(() => {
        if (!hasSchema && hasExample) setActiveTab('example');
        if (hasSchema && !hasExample) setActiveTab('schema');
        if (!hasSchema && !hasExample) setActiveTab('schema'); 
    }, [activeStatus, hasSchema, hasExample]);

    // Helper to render schema recursively
    const renderSchemaRow = (node, depth = 0) => {
        return (
            <React.Fragment key={`${node.key}-${depth}-${Math.random()}`}>
                <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 py-3 border-b border-slate-100 dark:border-slate-800/60 transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/40">
                    <div 
                        style={{ paddingLeft: depth > 0 ? `${depth * 1.25}rem` : '0.5rem' }} 
                        className="flex-1 font-mono text-sm flex items-center flex-wrap gap-2"
                    >
                        {depth > 0 && <span className="text-slate-300 dark:text-slate-600 select-none">↳</span>}
                        <span className="text-emerald-600 dark:text-emerald-400 font-bold">{node.key}</span>
                        <span className="text-[11px] px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 uppercase tracking-wider">
                            {node.type}
                            {node.isList && <span className="ml-0.5 font-bold">[]</span>}
                        </span>
                        {node.required && <span className="text-rose-500 font-bold text-lg leading-none" title="Required">*</span>}
                        {node.nullable && <span className="text-[10px] text-slate-400 italic">nullable</span>}
                    </div>
                    <div className="flex-[1.5] text-[13px] text-slate-600 dark:text-slate-400 px-2 sm:px-0">
                        {node.description || <span className="opacity-50 italic">No description</span>}
                    </div>
                </div>
                {node.schema && node.schema.length > 0 && node.schema.map(child => renderSchemaRow(child, depth + 1))}
            </React.Fragment>
        );
    };

    return (
        <div className="mb-12">
            <div className="flex items-center justify-between mb-4 pb-2 border-b border-slate-200 dark:border-slate-800/60">
                <h3 className="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-[0.15em] flex items-center gap-2">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Expected Responses
                </h3>
            </div>

            {/* Status Code Pills */}
            <div className="flex flex-wrap gap-2 mb-6">
                {statusCodes.map(code => {
                    const statusVal = parseInt(code);
                    const isSuccess = statusVal >= 200 && statusVal < 300;
                    const isActive = activeStatus === code;
                    
                    return (
                        <button
                            key={code}
                            onClick={() => setActiveStatus(code)}
                            className={`px-4 py-2 rounded-lg font-mono text-sm font-bold transition-all border duration-200 ${
                                isActive 
                                    ? isSuccess 
                                        ? 'bg-emerald-500 text-white border-emerald-500 shadow-md shadow-emerald-500/20' 
                                        : 'bg-rose-500 text-white border-rose-500 shadow-md shadow-rose-500/20'
                                    : 'bg-white dark:bg-[#0F172A] text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800/50 border-slate-200 dark:border-slate-800/60'
                            }`}
                        >
                            {code}
                        </button>
                    )
                })}
            </div>

            <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-800/60 rounded-xl overflow-hidden shadow-sm">
                
                {/* Internal Tabs for Schema vs Example */}
                <div className="flex border-b border-slate-200 dark:border-slate-800/60 bg-slate-50 dark:bg-[#1E293B]/50">
                    <button 
                        onClick={() => hasSchema && setActiveTab('schema')}
                        disabled={!hasSchema}
                        className={`flex-1 py-3 text-xs font-bold uppercase tracking-wider transition-colors ${
                            activeTab === 'schema' 
                                ? 'text-amber-500 border-b-2 border-amber-500 bg-white dark:bg-[#0F172A]' 
                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed'
                        }`}
                    >
                        Schema Definition
                    </button>
                    <button 
                        onClick={() => hasExample && setActiveTab('example')}
                        disabled={!hasExample}
                        className={`flex-1 py-3 text-xs font-bold uppercase tracking-wider transition-colors ${
                            activeTab === 'example' 
                                ? 'text-amber-500 border-b-2 border-amber-500 bg-white dark:bg-[#0F172A]' 
                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed'
                        }`}
                    >
                        Example Payload
                    </button>
                </div>

                {/* Schema Tab Content */}
                {activeTab === 'schema' && hasSchema && (
                    <div className="px-2 sm:px-4 py-2 max-h-[500px] overflow-y-auto custom-scrollbar">
                        <div className="hidden sm:flex items-center gap-4 py-2 border-b-2 border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-400 uppercase tracking-wider px-2">
                            <div className="flex-1">Property Name & Type</div>
                            <div className="flex-[1.5]">Description</div>
                        </div>
                        {data.schema.map(node => renderSchemaRow(node, 0))}
                    </div>
                )}

                {/* Example Tab Content */}
                {activeTab === 'example' && hasExample && (
                    <div className="bg-[#f8fafc] dark:bg-[#0B1120] relative group/json">
                        <button 
                            onClick={(e) => {
                                const exStr = typeof data.responseExample === 'string' ? data.responseExample : JSON.stringify(data.responseExample, null, 2);
                                navigator.clipboard.writeText(exStr);
                                const t = e.currentTarget;
                                const o = t.innerHTML;
                                t.innerHTML = `<svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
                                setTimeout(() => t.innerHTML = o, 2000);
                            }}
                            title="Copy Example JSON"
                            className="absolute top-3 right-3 p-1.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-amber-500 dark:hover:text-amber-400 opacity-0 group-hover/json:opacity-100 transition-opacity z-10"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        </button>
                        <div className="p-4 max-h-[500px] overflow-auto custom-scrollbar text-[13px]">
                            {typeof data.responseExample === 'object' ? (
                                <JsonEditor
                                    data={data.responseExample}
                                    theme={document.documentElement.classList.contains('dark') ? githubDarkTheme : githubLightTheme}
                                    restrictEdit={true}
                                    restrictAdd={true}
                                    restrictDelete={true}
                                    restrictDrag={true}
                                    restrictTypeSelection={true}
                                    rootName=""
                                    collapse={3}
                                    className="!font-mono !bg-transparent"
                                />
                            ) : (
                                <pre className="font-mono text-slate-700 dark:text-slate-300 whitespace-pre-wrap break-all leading-relaxed">
                                    {data.responseExample}
                                </pre>
                            )}
                        </div>
                    </div>
                )}

                {!hasSchema && !hasExample && (
                    <div className="p-8 text-center text-slate-400 dark:text-slate-600 font-mono text-sm italic">
                        No schema or example defined for this status code.
                    </div>
                )}
            </div>
        </div>
    );
}
