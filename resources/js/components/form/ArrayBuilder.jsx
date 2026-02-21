import React from 'react';
import DynamicField from './DynamicField';
import { btnSm } from '../../constants';

/**
 * ArrayBuilder — Renders children as indexed list items with add/remove.
 * Used for 'array' type fields. Supports infinite nesting via DynamicField.
 */
export default function ArrayBuilder({ node, onChange, depth, disabled = false }) {
    const children = node.children || [];
    const updateChild = (idx, newChild) => {
        const c = [...children];
        c[idx] = newChild;
        onChange({ children: c });
    };
    const removeChild = (idx) => onChange({ children: children.filter((_, i) => i !== idx) });
    const addItem = () => {
        const cType = node.childType || 'string';
        const newItem = { type: cType, enabled: true };
        if (cType === 'object') {
            newItem.children = Array.isArray(node.childDef?.schema) ? node.childDef.schema.map(s => ({ ...s, value: '', enabled: true })) : [];
        } else {
            newItem.value = '';
        }
        onChange({ children: [...children, newItem] });
    };

    return (
        <div className="space-y-1 bg-slate-50 dark:bg-slate-900/40 rounded-lg p-2 border border-slate-200 dark:border-slate-800/50">
            {children.length === 0 && <p className="text-xs text-slate-600 font-mono italic">Empty array...</p>}
            {children.map((child, i) => (
                <div key={i} className="relative group/arr pt-1">
                    <span className="absolute -left-[5px] top-4 text-[9px] font-mono text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-[#0B1120] px-1 group-hover/arr:text-amber-500 transition-colors z-10">[{i}]</span>
                    <DynamicField node={child} onChange={c => updateChild(i, c)} onRemove={() => removeChild(i)} hideKey={true} depth={depth} />
                </div>
            ))}
            {!disabled && (
                <button onClick={addItem} className={`${btnSm} mt-2 text-sky-500/70 border-sky-500/30 hover:bg-sky-500/10`}>+ Add Item</button>
            )}
        </div>
    );
}
