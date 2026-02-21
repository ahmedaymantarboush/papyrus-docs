import React from 'react';
import DynamicField from './DynamicField';
import { btnSm } from '../../constants';

/**
 * ObjectBuilder — Renders children as key-value pairs with add/remove.
 * Used for 'object' type fields. Supports infinite nesting via DynamicField.
 */
export default function ObjectBuilder({ node, onChange, depth, disabled = false }) {
    const children = node.children || [];
    const updateChild = (idx, newChild) => {
        const c = [...children];
        c[idx] = newChild;
        onChange({ children: c });
    };
    const removeChild = (idx) => onChange({ children: children.filter((_, i) => i !== idx) });
    const addProperty = () => onChange({ children: [...children, { key: `prop_${children.length}`, type: 'string', value: '', enabled: true }] });

    return (
        <div className="space-y-1">
            {children.map((child, i) => (
                <DynamicField key={i} node={child} onChange={c => updateChild(i, c)} onRemove={() => removeChild(i)} depth={depth} />
            ))}
            {!disabled && (
                <button onClick={addProperty} className={`${btnSm} mt-2 text-amber-500/70 border-amber-500/30 hover:bg-amber-500/10`}>+ Add Property</button>
            )}
        </div>
    );
}
