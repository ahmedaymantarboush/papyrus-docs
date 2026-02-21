import React, { useState, useEffect } from 'react';
import InlineKeyEditor from './InlineKeyEditor';
import TypeSelector from './TypeSelector';
import ValidationBadges from './ValidationBadges';
import SmartInput from './SmartInput';
import ObjectBuilder from './ObjectBuilder';
import ArrayBuilder from './ArrayBuilder';
import SelectOptionsModal from '../common/SelectOptionsModal';
import { compileTreeToPayload } from '../../helpers/request';
import { btnSm } from '../../constants';

/**
 * DynamicField — The recursive engine for both Body and Headers.
 *
 * Enterprise features at each node:
 *   ✅ Postman Toggle — enabled/disabled toggle, dims row + excludes from payload
 *   ✅ InlineKeyEditor — click-to-edit key names for object properties
 *   ✅ TypeSelector — dynamic type dropdown with smart morphing
 *   ✅ ValidationBadges — non-truncated rule display with expand/collapse
 *   ✅ Required indicator — red asterisk for required fields
 *   ✅ Recursive children — infinite depth for objects and arrays
 *   ✅ Remove button — always-visible red pill for dynamic properties
 *   ✅ SelectOptionsModal — auto-opens when switching to 'select' type
 */
export default function DynamicField({ node, onChange, onRemove, hideKey = false, depth = 0 }) {
    const [showOptionsModal, setShowOptionsModal] = useState(false);
    const update = (props) => onChange({ ...node, ...props });

    const isDisabled = node.enabled === false;
    const schema = node.schema || {};

    // ── Type change morphing logic ────────────────────────────────
    const handleTypeChange = (newType) => {
        const oldType = node.type || 'text';
        const isOldComplex = oldType === 'object' || oldType === 'array';
        const isNewComplex = newType === 'object' || newType === 'array';

        const updates = { type: newType };

        // Complex → Scalar: serialize children to JSON string
        if (isOldComplex && !isNewComplex && node.children) {
            try {
                const compiled = compileTreeToPayload(node.children);
                updates.value = JSON.stringify(compiled, null, 2);
            } catch (err) {}
            updates.children = undefined;
        }
        // Scalar → Complex: try to parse value as JSON
        else if (!isOldComplex && isNewComplex) {
            let parsed = null;
            try { parsed = JSON.parse(node.value || '{}'); } catch (err) {}

            if (parsed && typeof parsed === 'object') {
                const hydrate = (data) => {
                    if (Array.isArray(data)) {
                        return data.map(v => ({
                            key: '', value: (typeof v !== 'object') ? v : undefined,
                            type: Array.isArray(v) ? 'array' : (typeof v === 'object' && v !== null ? 'object' : 'text'),
                            children: typeof v === 'object' && v !== null ? hydrate(v) : undefined,
                            enabled: true,
                        }));
                    } else {
                        return Object.entries(data).map(([k, v]) => ({
                            key: k, value: (typeof v !== 'object') ? v : undefined,
                            type: Array.isArray(v) ? 'array' : (typeof v === 'object' && v !== null ? 'object' : 'text'),
                            children: typeof v === 'object' && v !== null ? hydrate(v) : undefined,
                            enabled: true,
                        }));
                    }
                };
                updates.children = hydrate(parsed);
                updates.value = undefined;
            } else {
                updates.children = [];
                updates.value = undefined;
            }
        }
        // Staying complex but no children yet
        else if (isNewComplex && !node.children) {
            updates.children = [];
        }

        // Auto-initialize options array for select type
        if (newType === 'select' && !node.options?.length) {
            updates.options = [];
        }

        update(updates);

        // Auto-open options modal when switching TO select type
        if (newType === 'select') {
            setTimeout(() => setShowOptionsModal(true), 100);
        }
    };

    const isComplex = node.type === 'object' || node.type === 'array';

    return (
        <div className={`group/field relative py-2 ${depth > 0 ? 'pl-4 border-l border-slate-700/50 mt-1 mb-2' : ''} ${isDisabled ? 'opacity-40' : ''}`}>
            <div className="flex flex-wrap items-center gap-2 mb-2">
                {/* ── Postman Toggle ──────────────────────────────── */}
                <label className="relative inline-flex items-center shrink-0 cursor-pointer">
                    <input type="checkbox" className="sr-only peer" checked={node.enabled !== false} onChange={e => update({ enabled: e.target.checked })} />
                    <div className="w-7 h-4 bg-slate-300 dark:bg-slate-700 rounded-full peer peer-checked:bg-amber-500 peer-focus:ring-2 peer-focus:ring-amber-500/20 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3 transition-colors"></div>
                </label>

                {/* ── Key Editor ──────────────────────────────────── */}
                {!hideKey && (
                    <InlineKeyEditor
                        value={node.key || ''}
                        onChange={newKey => update({ key: newKey })}
                        disabled={isDisabled}
                    />
                )}

                {/* ── Type Selector ───────────────────────────────── */}
                <TypeSelector value={node.type || 'text'} recommendedType={schema.type} onChange={handleTypeChange} disabled={isDisabled} />

                {/* ── Required Indicator ──────────────────────────── */}
                {(schema.required || node.required) && <span className="text-rose-400 text-xs font-bold shrink-0">*</span>}

                {/* ── Remove Button (always visible, red pill) ─────── */}
                {onRemove && (
                    <button
                        onClick={onRemove}
                        className="inline-flex items-center gap-1 ml-auto px-2 py-0.5 rounded-full text-[10px] font-mono font-medium text-rose-400 bg-rose-500/10 border border-rose-500/25 hover:bg-rose-500/20 hover:border-rose-500/40 transition-all"
                        title="Remove field"
                    >
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                )}
            </div>

            {/* ── Validation Badges ───────────────────────────────── */}
            <ValidationBadges
                rules={schema.rules || node.rules || []}
                required={schema.required || node.required}
                nullable={schema.nullable || node.nullable}
                conditionals={schema.conditionals || node.conditionals}
            />

            {/* ── Value Input / Children ──────────────────────────── */}
            <div className="mt-1">
                {node.type === 'object' && <ObjectBuilder node={node} onChange={update} depth={depth + 1} disabled={isDisabled} />}
                {node.type === 'array' && <ArrayBuilder node={node} onChange={update} depth={depth + 1} disabled={isDisabled} />}
                {!isComplex && (
                    <SmartInput
                        field={{ ...schema, ...node }}
                        value={node.value}
                        onChange={v => update({ value: v })}
                        onOptionsClick={() => setShowOptionsModal(true)}
                        disabled={isDisabled}
                    />
                )}
            </div>

            {/* ── Select Options Modal ────────────────────────────── */}
            <SelectOptionsModal
                open={showOptionsModal}
                onClose={() => setShowOptionsModal(false)}
                fieldName={node.key || 'field'}
                initialOptions={node.options || schema.options || []}
                onSave={(newOptions) => update({ options: newOptions })}
            />
        </div>
    );
}
