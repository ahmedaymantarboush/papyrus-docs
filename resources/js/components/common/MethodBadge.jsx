import React from 'react';
import { METHOD_COLORS } from '../../constants';

/**
 * MethodBadge — Renders an HTTP method label with color-coded styling.
 * GET=emerald, POST=sky, PUT=amber, PATCH=violet, DELETE=rose.
 */
export default function MethodBadge({ method }) {
    return (
        <span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider border ${METHOD_COLORS[method] || 'text-slate-400 border-slate-600 bg-slate-800'}`}>
            {method}
        </span>
    );
}
