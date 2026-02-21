import React from 'react';

/**
 * ToggleSwitch — A styled toggle switch (checkbox replacement).
 * Used for boolean values and settings toggles.
 */
export default function ToggleSwitch({ checked, onChange, label }) {
    return (
        <label className="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" className="sr-only peer" checked={checked} onChange={e => onChange(e.target.checked)} />
            <div className="w-9 h-5 bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
            {label && <span className="ml-3 text-sm font-mono text-slate-300">{label}</span>}
        </label>
    );
}
