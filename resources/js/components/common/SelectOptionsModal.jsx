import React, { useState, useEffect } from 'react';
import { inputCls, btnSm } from '../../constants';

/**
 * SelectOptionsModal — Modal for viewing and editing select field options.
 * Opens when clicking the ⚙ icon on a select field.
 *
 * Features: Add, edit (click to inline edit), remove, Escape to close,
 * click outside to close.
 */
export default function SelectOptionsModal({ open, onClose, fieldName, initialOptions, onSave }) {
    const [options, setOptions] = useState([]);
    const [newOpt, setNewOpt] = useState('');
    const [editingIdx, setEditingIdx] = useState(null);
    const [editVal, setEditVal] = useState('');

    useEffect(() => {
        if (open) {
            setOptions(Array.isArray(initialOptions) ? [...initialOptions] : []);
            setNewOpt('');
            setEditingIdx(null);
        }
    }, [open, initialOptions]);

    useEffect(() => {
        if (!open) return;
        const handleKeyDown = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open, onClose]);

    if (!open) return null;

    const commit = (newArr) => { setOptions(newArr); onSave(newArr); };
    const handleAdd = (e) => { e.preventDefault(); const v = newOpt.trim(); if (v && !options.includes(v)) { commit([...options, v]); setNewOpt(''); } };
    const removeOpt = (idx) => commit(options.filter((_, i) => i !== idx));
    const startEdit = (idx, val) => { setEditingIdx(idx); setEditVal(val); };
    const finishEdit = (e, idx) => { e.preventDefault(); const v = editVal.trim(); if (v) { const arr = [...options]; arr[idx] = v; commit(arr); } setEditingIdx(null); };

    return (
        <div className="fixed inset-0 z-[110] flex items-center justify-center p-4 backdrop-blur-sm bg-black/60" onClick={onClose}>
            <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-700/60 rounded-xl w-full max-w-sm shadow-2xl overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-200" onClick={e => e.stopPropagation()}>
                <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-[#0B1120]">
                    <h3 className="font-brand font-bold text-slate-800 dark:text-slate-100 text-sm">Options for `{fieldName}`</h3>
                    <button onClick={onClose} className="text-slate-500 hover:text-slate-300">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div className="p-4 space-y-4">
                    <form onSubmit={handleAdd} className="flex gap-2">
                        <input type="text" className={inputCls} placeholder="Type option and hit Enter..." value={newOpt} onChange={e => setNewOpt(e.target.value)} />
                        <button type="submit" className={`${btnSm} border-amber-500/30 text-amber-400 hover:bg-amber-500/10 px-3 shrink-0`}>Add</button>
                    </form>

                    <div className="space-y-1.5 max-h-48 overflow-y-auto custom-scrollbar">
                        {options.length === 0 ? (
                            <div className="text-xs text-slate-500 italic text-center py-4">No custom options defined.</div>
                        ) : (
                            options.map((opt, i) => (
                                <div key={i} className="flex items-center justify-between bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50 rounded-md px-3 py-1.5 group">
                                    {editingIdx === i ? (
                                        <form onSubmit={(e) => finishEdit(e, i)} className="flex gap-2 w-full">
                                            <input autoFocus type="text" className="w-full bg-white dark:bg-[#0B1120] border border-amber-500/50 rounded flex-1 px-2 py-0.5 text-sm text-slate-800 dark:text-slate-200 outline-none" value={editVal} onChange={e => setEditVal(e.target.value)} onBlur={(e) => finishEdit(e, i)} />
                                        </form>
                                    ) : (
                                        <>
                                            <span onClick={() => startEdit(i, opt)} className="text-sm font-mono text-slate-600 dark:text-slate-300 truncate pr-2 cursor-pointer hover:text-amber-500 dark:hover:text-amber-400 transition-colors w-full" title="Click to edit">{opt}</span>
                                            <button onClick={() => removeOpt(i)} className="text-rose-400/50 hover:text-rose-400 opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
                                        </>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>

                <div className="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-[#0B1120] flex justify-end gap-2">
                    <button onClick={onClose} className={`${btnSm} font-mono px-4 py-1.5 bg-amber-500/10 dark:bg-amber-500/20 border-amber-500/50 text-amber-500 dark:text-amber-400 hover:bg-amber-500/20 dark:hover:bg-amber-500/30`}>Done</button>
                </div>
            </div>
        </div>
    );
}
