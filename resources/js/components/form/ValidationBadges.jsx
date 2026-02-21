import React, { useState } from 'react';

/**
 * ValidationBadges — Displays non-truncated validation rule badges.
 *
 * Rules are displayed as wrapping badges. If rules exceed a threshold,
 * an ⓘ tooltip/popover shows the full list. Rules are NEVER truncated.
 */
export default function ValidationBadges({ rules = [], required = false, nullable = false, conditionals = [] }) {
    const [showAll, setShowAll] = useState(false);

    if (!rules.length && !conditionals?.length) return null;

    const allBadges = [
        ...(required ? [{ text: 'required', color: 'text-rose-600 dark:text-rose-400 bg-rose-500/10 border-rose-500/20' }] : []),
        ...(nullable ? [{ text: 'nullable', color: 'text-violet-600 dark:text-violet-400 bg-violet-500/10 border-violet-500/20' }] : []),
        ...rules.filter(r => r !== 'required' && r !== 'nullable').map(r => ({
            text: r,
            color: 'text-slate-600 dark:text-slate-400 bg-slate-200/50 dark:bg-slate-700/40 border-slate-300 dark:border-slate-600/40'
        })),
        ...(conditionals || []).map(c => ({
            text: c.rule + (c.field ? `:${c.field}` : '') + (c.value ? `,${c.value}` : ''),
            color: 'text-amber-600 dark:text-amber-400 bg-amber-500/10 border-amber-500/20'
        })),
    ];

    const VISIBLE_LIMIT = 4;
    const visible = showAll ? allBadges : allBadges.slice(0, VISIBLE_LIMIT);
    const hasMore = allBadges.length > VISIBLE_LIMIT;

    return (
        <div className="flex flex-wrap items-center gap-1 mt-1">
            {visible.map((badge, i) => (
                <span key={i} className={`inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono border ${badge.color}`}>
                    {badge.text}
                </span>
            ))}
            {hasMore && !showAll && (
                <button
                    onClick={() => setShowAll(true)}
                    className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono text-amber-400 bg-amber-500/10 border border-amber-500/20 hover:bg-amber-500/20 transition-colors cursor-pointer"
                    title="Show all rules"
                >
                    ⓘ +{allBadges.length - VISIBLE_LIMIT} more
                </button>
            )}
            {hasMore && showAll && (
                <button
                    onClick={() => setShowAll(false)}
                    className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono text-slate-500 hover:text-slate-300 transition-colors cursor-pointer"
                >
                    collapse
                </button>
            )}
        </div>
    );
}
