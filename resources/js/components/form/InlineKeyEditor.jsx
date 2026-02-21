import React, { useState, useRef, useEffect } from 'react';

/**
 * InlineKeyEditor — Click-to-edit key names for object properties.
 *
 * States: VIEW (displays key with pencil icon) ↔ EDIT (input with check icon)
 * Transitions: Click pencil → EDIT, Enter/Check → VIEW (save), Esc/Blur → VIEW (revert)
 */
export default function InlineKeyEditor({ value, onChange, disabled = false }) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value);
    const inputRef = useRef(null);

    useEffect(() => {
        if (editing && inputRef.current) {
            inputRef.current.focus();
            inputRef.current.select();
        }
    }, [editing]);

    const startEdit = () => {
        if (disabled) return;
        setDraft(value);
        setEditing(true);
    };

    const commit = () => {
        const trimmed = draft.trim();
        if (trimmed && trimmed !== value) {
            onChange(trimmed);
        }
        setEditing(false);
    };

    const cancel = () => {
        setDraft(value);
        setEditing(false);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') { e.preventDefault(); commit(); }
        if (e.key === 'Escape') { e.preventDefault(); cancel(); }
    };

    if (editing) {
        return (
            <div className="inline-flex items-center gap-1">
                <input
                    ref={inputRef}
                    type="text"
                    value={draft}
                    onChange={e => setDraft(e.target.value)}
                    onBlur={commit}
                    onKeyDown={handleKeyDown}
                    className="bg-[#0F172A] border border-amber-500/50 text-amber-400 font-mono text-sm px-1.5 py-0.5 rounded outline-none w-32"
                    placeholder="key"
                />
                <button onClick={commit} className="text-emerald-400 hover:text-emerald-300 transition-colors" title="Save">
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                </button>
            </div>
        );
    }

    return (
        <div className="inline-flex items-center gap-1 group/key">
            <span className={`text-amber-400 font-mono text-sm px-1 py-0.5 ${disabled ? 'opacity-50' : 'cursor-pointer hover:underline'}`} onClick={startEdit}>
                {value || '—'}
            </span>
            {!disabled && (
                <button onClick={startEdit} className="text-slate-600 hover:text-amber-400 opacity-0 group-hover/key:opacity-100 transition-all" title="Edit key">
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                </button>
            )}
        </div>
    );
}
