import React from 'react';
import { inputCls, selectCls } from '../../constants';

/**
 * SmartInput — Renders the appropriate input widget based on field type.
 *
 * Supports: text (textarea), string (input), email, url, number, date,
 * password, color, boolean (toggle), file (file picker), json (textarea),
 * select (with options modal trigger), and dynamic types (uuid, ip, etc.)
 */
export default function SmartInput({ field, value, onChange, onOptionsClick, disabled = false }) {
    const baseDisabled = disabled ? 'opacity-50 pointer-events-none' : '';

    // ── Select: trigger options modal ──────────────────────────────
    if (field.type === 'select' && field.options) {
        const opts = Array.isArray(field.options) ? field.options : (typeof field.options === 'object' ? Object.values(field.options) : []);
        return (
            <div className={`flex gap-2 ${baseDisabled}`}>
                <select className={selectCls} value={value || ''} onChange={e => onChange(e.target.value)}>
                    <option value="">— Select —</option>
                    {opts.map(o => <option key={o} value={o}>{o}</option>)}
                </select>
                {onOptionsClick && (
                    <button onClick={onOptionsClick} className="shrink-0 text-amber-500/60 hover:text-amber-400 transition-colors" title="Edit options">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </button>
                )}
            </div>
        );
    }

    // ── Boolean: toggle switch ─────────────────────────────────────
    if (field.type === 'boolean') {
        const isChecked = value === true || value === 'true';
        return (
            <label className={`relative inline-flex items-center cursor-pointer mt-1 ${baseDisabled}`}>
                <input type="checkbox" className="sr-only peer" checked={isChecked} onChange={e => onChange(e.target.checked)} />
                <div className="w-9 h-5 bg-slate-300 dark:bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-500 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                <span className="ml-3 text-sm font-mono text-slate-600 dark:text-slate-300">{isChecked ? 'true' : 'false'}</span>
            </label>
        );
    }

    // ── File: file picker with filename display ────────────────────
    if (field.type === 'file') {
        return (
            <label className={`block ${baseDisabled}`}>
                <input
                    type="file"
                    accept={field.accept || undefined}
                    className="block w-full text-sm text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-slate-300 dark:file:border-slate-600 file:text-xs file:font-medium file:bg-slate-100 dark:file:bg-slate-800 file:text-amber-600 dark:file:text-amber-400 hover:file:bg-slate-200 dark:hover:file:bg-slate-700 cursor-pointer"
                    onChange={e => onChange(e.target.files[0] || null)}
                />
                {value instanceof File && <span className="text-[11px] text-emerald-400 mt-1 block">✓ {value.name}</span>}
            </label>
        );
    }

    // ── Color: native color picker ─────────────────────────────────
    if (field.type === 'color') {
        return (
            <div className={`flex items-center gap-2 ${baseDisabled}`}>
                <input type="color" value={value || '#000000'} onChange={e => onChange(e.target.value)} className="w-8 h-8 rounded border border-slate-700 cursor-pointer bg-transparent" />
                <input type="text" className={inputCls} value={value || ''} onChange={e => onChange(e.target.value)} placeholder="#000000" />
            </div>
        );
    }

    // ── Number ─────────────────────────────────────────────────────
    if (field.type === 'number') {
        return <input type="number" className={`${inputCls} ${baseDisabled}`} placeholder={`Enter ${field.key}...`} value={value ?? ''} onChange={e => onChange(e.target.value)} min={field.min ?? undefined} max={field.max ?? undefined} />;
    }

    // ── Email ──────────────────────────────────────────────────────
    if (field.type === 'email') {
        return <input type="email" className={`${inputCls} ${baseDisabled}`} placeholder={`Enter email...`} value={value ?? ''} onChange={e => onChange(e.target.value)} />;
    }

    // ── URL ────────────────────────────────────────────────────────
    if (field.type === 'url') {
        return <input type="url" className={`${inputCls} ${baseDisabled}`} placeholder={`https://...`} value={value ?? ''} onChange={e => onChange(e.target.value)} />;
    }

    // ── Date ───────────────────────────────────────────────────────
    if (field.type === 'date') {
        return <input type="date" className={`${inputCls} ${baseDisabled}`} value={value ?? ''} onChange={e => onChange(e.target.value)} />;
    }

    // ── Password ──────────────────────────────────────────────────
    if (field.type === 'password') {
        return <input type="password" className={`${inputCls} ${baseDisabled}`} placeholder="••••••••" value={value ?? ''} onChange={e => onChange(e.target.value)} />;
    }

    // ── String (single-line input) ─────────────────────────────────
    if (field.type === 'string') {
        const placeholder = field.type === 'uuid' ? 'xxxxxxxx-xxxx-...'
            : field.type === 'ip' ? '192.168.1.1'
            : `Enter ${field.key}...`;
        return <input type="text" className={`${inputCls} ${baseDisabled}`} placeholder={placeholder} value={value ?? ''} onChange={e => onChange(e.target.value)} maxLength={field.max ?? undefined} />;
    }

    // ── JSON (textarea) ────────────────────────────────────────────
    if (field.type === 'json') {
        return (
            <textarea
                className={`${inputCls} min-h-[60px] py-2 resize-y font-mono text-xs ${baseDisabled}`}
                placeholder='{"key": "value"}'
                value={value ?? ''}
                onChange={e => onChange(e.target.value)}
                rows={3}
            />
        );
    }

    // ── Dynamic types (uuid, ulid, ip, mac_address, etc.) ──────────
    // Use string input with format-specific placeholder
    const dynamicTypes = ['uuid', 'ulid', 'ip', 'ipv4', 'ipv6', 'mac_address'];
    if (dynamicTypes.includes(field.type)) {
        const placeholders = {
            uuid: 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx',
            ulid: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ip: '192.168.1.1',
            ipv4: '192.168.1.1',
            ipv6: '2001:0db8:85a3::8a2e:0370:7334',
            mac_address: '00:1B:44:11:3A:B7',
        };
        return <input type="text" className={`${inputCls} ${baseDisabled}`} placeholder={placeholders[field.type] || `Enter ${field.key}...`} value={value ?? ''} onChange={e => onChange(e.target.value)} />;
    }

    // ── Default: Text (auto-resizing textarea) ─────────────────────
    return (
        <textarea
            className={`${inputCls} min-h-[40px] py-2 resize-y ${baseDisabled}`}
            placeholder={`Enter ${field.key}...`}
            value={value ?? ''}
            onChange={e => onChange(e.target.value)}
            rows={1}
            onInput={(e) => {
                e.target.style.height = 'auto';
                e.target.style.height = (e.target.scrollHeight) + 'px';
            }}
        />
    );
}
