/**
 * schema.js — Hydration, deep-clone, and default-generation utilities
 * for the formTree state.
 */

/**
 * deepCloneTree — Creates a complete deep clone of the formTree,
 * preserving all values and children recursively.
 * Used for "Reset to Default" functionality.
 */
export function deepCloneTree(nodes) {
    if (!Array.isArray(nodes)) return [];
    return nodes.map(n => ({
        ...n,
        value: n.value instanceof File ? null : n.value,
        children: n.children ? deepCloneTree(n.children) : undefined,
    }));
}

/**
 * normalizeParamsToArray — Converts backend schema (object or array) to
 * a flat array with `key` populated from object keys.
 *
 * Backend sends: { email: { type, required... }, password: { type... } }
 * We need:       [{ key: 'email', type, required... }, { key: 'password', type... }]
 *
 * If params is already an array, ensure each item has a key.
 */
function normalizeParamsToArray(params) {
    if (Array.isArray(params)) return params;
    if (typeof params === 'object' && params !== null) {
        return Object.entries(params).map(([key, val]) => {
            if (typeof val === 'object' && val !== null) {
                return { ...val, key: val.key || key };
            }
            return { key, type: 'text', value: val ?? '' };
        });
    }
    return [];
}

/**
 * buildInitialTree — Hydrates formTree from schema params + optional saved state.
 *
 * Priority: saved state > schema defaults > empty values
 * Recursively handles nested objects and arrays.
 */
export function buildInitialTree(params, savedNodes = []) {
    const pArr = normalizeParamsToArray(params);
    const sArr = Array.isArray(savedNodes) ? savedNodes : (typeof savedNodes === 'object' && savedNodes ? Object.values(savedNodes) : []);

    // If we have saved state, the saved state is the source of truth for the structure
    // (meaning added nodes stay, and deleted nodes stay deleted).
    // Otherwise, we strictly use the schema.
    const baseArray = sArr.length > 0 ? sArr : pArr;

    return baseArray.map(baseItem => {
        // Find corresponding schema or saved data
        let schemaItem = sArr.length > 0 ? pArr.find(p => p.key === baseItem.key) : baseItem;
        
        // If no exact match, try matching pattern keys
        if (!schemaItem && sArr.length > 0) {
            schemaItem = pArr.find(p => {
                if (!p.isPattern) return false;
                const escaped = p.key.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
                const regexStr = '^' + escaped.replace(/\\\*/g, '.*') + '$';
                return new RegExp(regexStr).test(baseItem.key);
            });
        }

        let savedItem = sArr.length > 0 ? baseItem : sArr.find(sn => sn.key === baseItem.key && sn.key !== undefined);

        // Anti-stale mechanism: if the structural type changed in the backend schema, discard the saved item
        if (savedItem && schemaItem) {
            let schemaType = schemaItem.type;
            if (schemaType === 'object' && schemaItem.isList) schemaType = 'array';
            if (savedItem.type !== schemaType && (savedItem.type === 'array' || savedItem.type === 'object' || schemaType === 'array' || schemaType === 'object')) {
                savedItem = undefined;
            }
        }

        let type = savedItem ? savedItem.type : (schemaItem ? schemaItem.type : 'text');
        let cDef = schemaItem ? schemaItem.childDef : undefined;

        if (!savedItem && schemaItem) {
            if (type === 'object' && schemaItem.isList) { type = 'array'; cDef = { type: 'object', schema: schemaItem.schema }; }
            else if (type === 'array') { cDef = { type: schemaItem.childType || 'string' }; }
        } else if (savedItem) { 
            cDef = savedItem.childDef || cDef; 
        }

        let children = [];
        if (type === 'object') {
            children = buildInitialTree(schemaItem ? (schemaItem.schema || []) : [], savedItem ? (savedItem.children || []) : []);
        } else if (type === 'array') {
            children = savedItem ? (savedItem.children || []) : [];
        }

        return {
            ...(schemaItem || {}),
            key: baseItem.key,
            type,
            childDef: cDef,
            children,
            value: savedItem !== undefined ? savedItem.value : '',
            enabled: savedItem?.enabled !== undefined ? savedItem.enabled : true,
            schema: schemaItem || undefined, // Preserve immutable schema reference
        };
    });
}

/**
 * resetTreeToDefaults — Resets the entire formTree back to its
 * schema defaults. Values become empty, enabled becomes true,
 * children are rebuilt from schema.
 */
export function resetTreeToDefaults(params) {
    const pArr = normalizeParamsToArray(params);

    return pArr.map(p => {
        let type = p.type;
        let cDef = p.childDef;

        if (type === 'object' && p.isList) { type = 'array'; cDef = { type: 'object', schema: p.schema }; }
        else if (type === 'array') { cDef = { type: p.childType || 'string' }; }

        let children = [];
        if (type === 'object') {
            children = resetTreeToDefaults(p.schema || []);
        }

        return {
            ...p,
            type,
            childDef: cDef,
            children,
            value: '',
            enabled: true,
            schema: p,
        };
    });
}

/**
 * hydrateFormTreeFromJson — Converts a parsed JSON object back into
 * formTree nodes. Used by the two-way sync when applying raw JSON edits.
 */
export function hydrateFormTreeFromJson(jsonObj, existingTree = []) {
    if (!jsonObj || typeof jsonObj !== 'object') return existingTree;

    return Object.entries(jsonObj).map(([key, val]) => {
        let existing = existingTree.find(n => n.key === key);
        
        // Anti-stale mechanism for pattern keys during JSON hydrate
        if (!existing) {
            existing = existingTree.find(n => {
                const schemaItem = n.schema || n;
                if (!schemaItem.isPattern) return false;
                const escaped = (schemaItem.key || n.key).replace(/[.+?^${}()|[\]\\]/g, '\\$&');
                const regexStr = '^' + escaped.replace(/\\\*/g, '.*') + '$';
                return new RegExp(regexStr).test(key);
            });
        }

        if (val === null || val === undefined) {
            return { key, type: 'text', value: '', enabled: true, schema: existing?.schema, children: undefined };
        }

        if (Array.isArray(val)) {
            const children = val.map(item => {
                if (typeof item === 'object' && item !== null) {
                    return { key: '', type: 'object', value: undefined, enabled: true, children: hydrateFormTreeFromJson(item, []) };
                }
                return { key: '', type: typeof item === 'number' ? 'number' : typeof item === 'boolean' ? 'boolean' : 'text', value: item, enabled: true };
            });
            return { key, type: 'array', value: undefined, enabled: true, schema: existing?.schema, children, childDef: existing?.childDef };
        }

        if (typeof val === 'object') {
            return { key, type: 'object', value: undefined, enabled: true, schema: existing?.schema, children: hydrateFormTreeFromJson(val, existing?.children || []) };
        }

        // Scalar
        let type = 'text';
        if (typeof val === 'number') type = 'number';
        else if (typeof val === 'boolean') type = 'boolean';
        else if (existing?.type) type = existing.type;

        return { key, type, value: val, enabled: existing?.enabled ?? true, schema: existing?.schema, children: undefined };
    });
}

/**
 * sanitizeTree — Prepares the formTree for localStorage serialization.
 * Strips File objects (can't be serialized) and keeps everything else.
 */
export function sanitizeTree(nodes) {
    if (!Array.isArray(nodes)) return [];
    return nodes.map(n => {
        const copy = { ...n };
        if (copy.value instanceof File) copy.value = null;
        if (copy.children) copy.children = sanitizeTree(copy.children);
        // Strip circular schema reference before serialization
        delete copy.schema;
        return copy;
    });
}
