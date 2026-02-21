import React, { useMemo } from 'react';
import { FIELD_TYPES } from '../../constants';

/**
 * TypeSelector — Dropdown for dynamically changing the type of a field.
 *
 * DYNAMIC TYPE DISCOVERY: If the field's current type is a dynamically
 * discovered type from the backend (e.g., 'uuid', 'mac_address', 'ulid')
 * that isn't in the standard FIELD_TYPES list, it is automatically
 * appended to the dropdown so the value is always visible and selectable.
 */
export default function TypeSelector({ value, recommendedType, onChange, disabled = false }) {
    const currentType = value || 'text';

    // Dynamically append the current type if it's not in the standard list
    const types = useMemo(() => {
        const known = FIELD_TYPES.map(ft => ft.value);
        let list = [...FIELD_TYPES];
        
        if (!known.includes(currentType)) {
            list.push({ value: currentType, label: `${currentType} (dynamic)` });
        }
        
        return list.map(ft => ({
            ...ft,
            label: ft.value === recommendedType ? `${ft.label} (Auto)` : ft.label
        }));
    }, [currentType, recommendedType]);

    return (
        <select
            value={currentType}
            onChange={e => onChange(e.target.value)}
            disabled={disabled}
            className="text-[9px] font-mono tracking-wider uppercase bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded px-1.5 py-0.5 text-slate-700 dark:text-slate-300 outline-none cursor-pointer hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            {types.map(ft => (
                <option key={ft.value} value={ft.value}>{ft.label}</option>
            ))}
        </select>
    );
}
