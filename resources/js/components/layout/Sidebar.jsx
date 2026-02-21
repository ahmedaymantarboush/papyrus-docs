import React, { useState, useMemo, useRef, useCallback } from 'react';
import MethodBadge from '../common/MethodBadge';
import { inputCls, rid, PC } from '../../constants';

/**
 * Sidebar — Left navigation panel with search, grouping, and resizable width.
 * Consumes PapyrusConfig.title for the header display.
 */
export default function Sidebar({ schema, activeId, onSelect, open, onClose, width, setWidth }) {
    const [searchTerm, setSearchTerm] = useState('');
    const sidebarRef = useRef(null);

    const startResizing = useCallback((mouseDownEvent) => {
        mouseDownEvent.preventDefault();
        const startWidth = sidebarRef.current.getBoundingClientRect().width;
        const startX = mouseDownEvent.clientX;
        const isDesktop = window.innerWidth >= 1024;

        const onMouseMove = (moveEvent) => {
            const delta = moveEvent.clientX - startX;
            let maxW;
            if (isDesktop) {
                const playgroundEl = document.querySelector('[data-panel="playground"]');
                const playgroundW = playgroundEl ? playgroundEl.getBoundingClientRect().width : 0;
                maxW = Math.max(200, window.innerWidth - playgroundW - 300);
            } else {
                maxW = Math.floor(window.innerWidth * 0.85);
            }
            const newWidth = Math.max(200, Math.min(maxW, startWidth + delta));
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

    const filteredSchema = useMemo(() => {
        if (!searchTerm) return schema;
        const lower = searchTerm.toLowerCase();
        return schema.map(group => {
            const routes = group.routes.filter(r => {
                if (r.uri && r.uri.toLowerCase().includes(lower)) return true;
                if (r.title && r.title.toLowerCase().includes(lower)) return true;
                if (r.methods && r.methods[0].toLowerCase().includes(lower)) return true;
                if (r.bodyParams) {
                    return Object.entries(r.bodyParams).some(([key, meta]) =>
                        key.toLowerCase().includes(lower) || (meta?.type && String(meta.type).toLowerCase().includes(lower))
                    );
                }
                return false;
            });
            return { ...group, routes };
        }).filter(group => group.routes.length > 0);
    }, [schema, searchTerm]);

    const config = PC();

    return (
        <>
            {/* Backdrop: always in DOM on mobile, animated via opacity */}
            <div
                className={`fixed inset-0 bg-black/60 z-40 lg:hidden backdrop-blur-sm transition-opacity duration-300 ${open ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'}`}
                onClick={onClose}
            />
            {/* Sidebar positioning: Below navbar (top-14), height calc to bottom. */}
            <aside data-panel="sidebar" ref={sidebarRef} style={{ width: width + 'px' }} className={`fixed top-14 left-0 h-[calc(100vh-3.5rem)] z-40 bg-slate-50 dark:bg-[#0B1120] border-r border-slate-200 dark:border-slate-800/60 flex flex-col transition-transform duration-300 ease-out lg:static lg:translate-x-0 lg:flex ${open ? 'translate-x-0 shadow-2xl shadow-black/40' : '-translate-x-full lg:translate-x-0'}`}>
                <div onMouseDown={startResizing} className="absolute top-0 right-0 bottom-0 w-2 cursor-col-resize hover:bg-amber-500/20 active:bg-amber-500/40 z-50 transition-colors" />
                
                <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-800/40 shrink-0">
                    <input type="text" placeholder="Search endpoints..." className={inputCls} value={searchTerm} onChange={e => setSearchTerm(e.target.value)} />
                </div>
                <div className="px-4 py-1.5 border-b border-slate-200 dark:border-slate-800/30 flex items-center justify-between shrink-0 bg-slate-100/50 dark:bg-[#0B1120]">
                    <span className="text-[10px] font-mono text-slate-500 dark:text-slate-500">
                        {(() => { const c = filteredSchema.reduce((sum, g) => sum + g.routes.length, 0); return `${c} endpoint${c !== 1 ? 's' : ''}`; })()}
                    </span>
                    {searchTerm && <button onClick={() => setSearchTerm('')} className="text-[9px] font-mono text-slate-500 hover:text-amber-500 dark:hover:text-amber-400 transition-colors">Clear</button>}
                </div>
                <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-6 custom-scrollbar">
                    {filteredSchema.map((group, gi) => {
                        const isFlat = group.name === '__all__';
                        return (
                            <div key={gi}>
                                {!isFlat && (
                                    <div className="px-2 mb-3">
                                        <h3 className="text-[11px] font-semibold text-amber-400/80 tracking-wide">{group.name}</h3>
                                        {group.namespace && <p className="text-[9px] font-mono text-slate-500 mt-0.5 truncate" title={group.namespace}>{group.namespace}</p>}
                                    </div>
                                )}
                                <ul className="space-y-px">
                                    {group.routes.map(route => {
                                        const id = rid(route);
                                        const active = activeId === id;
                                        return (
                                            <li key={id}>
                                                <button
                                                    onClick={() => { onSelect(route); onClose(); }}
                                                    className={`w-full text-left px-3 py-2 rounded-md transition-all duration-150 flex flex-col items-start gap-1.5 border-l-2 ${active ? 'bg-amber-500/10 dark:bg-amber-500/[0.08] text-amber-600 dark:text-amber-400 border-amber-500' : 'text-slate-600 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200 hover:bg-black/[0.03] dark:hover:bg-white/[0.03]'}`}
                                                >
                                                    <div className="flex items-start gap-2 w-full">
                                                        <div className="mt-0.5 shrink-0"><MethodBadge method={route.methods[0]} /></div>
                                                        <span className={`text-[13px] font-mono whitespace-normal break-all leading-snug ${active ? 'font-medium' : ''}`}>
                                                            {route.uri.split('/').map((part, index, array) => (
                                                                <React.Fragment key={index}>{part}{index < array.length - 1 && <>&zwj;/&zwj;</>}</React.Fragment>
                                                            ))}
                                                        </span>
                                                    </div>
                                                    <span className="text-[11px] text-slate-500 truncate w-full pl-1">{route.title}</span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        );
                    })}
                </nav>
            </aside>
        </>
    );
}
