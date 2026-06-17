import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import React from 'react';
import SettingsModal from '../SettingsModal';

/** Default settings shape matching App.jsx state */
const defaultSettings = {
    groupBy: 'default',
    sortBy: 'default',
    filterMethods: [],
    filterNameRegex: '',
    filterControllerRegex: '',
    filterUriRegex: '',
    globalHeaders: false,
    saveResponses: false,
};

function renderModal(overrides = {}, settingsOverrides = {}) {
    const onClose = vi.fn();
    const setSettings = vi.fn();
    const props = {
        open: true,
        onClose,
        settings: { ...defaultSettings, ...settingsOverrides },
        setSettings,
        ...overrides,
    };
    const result = render(<SettingsModal {...props} />);
    return { ...result, onClose, setSettings };
}

// ---------------------------------------------------------------------------
// Visibility & open/close
// ---------------------------------------------------------------------------
describe('SettingsModal — visibility', () => {
    it('renders nothing when open=false', () => {
        const { container } = render(
            <SettingsModal
                open={false}
                onClose={vi.fn()}
                settings={defaultSettings}
                setSettings={vi.fn()}
            />
        );
        expect(container.firstChild).toBeNull();
    });

    it('renders the modal when open=true', () => {
        renderModal();
        expect(screen.getByText('Papyrus Settings')).toBeInTheDocument();
    });

    it('calls onClose when the × button is clicked', () => {
        const { onClose } = renderModal();
        const closeBtn = screen.getByRole('button', { name: '' }); // SVG button has no text
        // Find close button by its SVG content — it's the first button in the header
        const allButtons = screen.getAllByRole('button');
        // First button after title is the close ×
        fireEvent.click(allButtons[0]);
        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('calls onClose when Done button is clicked', () => {
        const { onClose } = renderModal();
        fireEvent.click(screen.getByText('Done'));
        expect(onClose).toHaveBeenCalledTimes(1);
    });
});

// ---------------------------------------------------------------------------
// Overflow fix — all HTTP method buttons must be present in the DOM
// ---------------------------------------------------------------------------
describe('SettingsModal — overflow fix (all method buttons visible)', () => {
    it('renders all 5 HTTP method filter buttons', () => {
        renderModal();
        for (const method of ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']) {
            expect(screen.getByRole('button', { name: method })).toBeInTheDocument();
        }
    });

    it('scrollable body has overflow-x-hidden to prevent horizontal overflow', () => {
        const { container } = renderModal();
        // The scrollable inner div carries both overflow-y-auto and overflow-x-hidden
        const scrollBody = container.querySelector('.overflow-y-auto');
        expect(scrollBody).not.toBeNull();
        expect(scrollBody.classList.contains('overflow-x-hidden')).toBe(true);
    });

    it('modal wrapper uses max-h-[90vh] to cap viewport height', () => {
        const { container } = renderModal();
        // The modal panel — direct child of the backdrop
        const backdrop = container.querySelector('.fixed');
        const panel = backdrop?.firstElementChild;
        expect(panel?.className).toContain('max-h-[90vh]');
    });

    it('HTTP method buttons carry shrink-0 so they never collapse below readable size', () => {
        const { container } = renderModal();
        const methodButtons = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map(m =>
            screen.getByRole('button', { name: m })
        );
        for (const btn of methodButtons) {
            expect(btn.classList.contains('shrink-0')).toBe(true);
        }
    });

    it('header and footer carry shrink-0 so they never collapse during scroll', () => {
        const { container } = renderModal();
        const backdrop = container.querySelector('.fixed');
        const panel = backdrop?.firstElementChild;
        // First child = header, last child = footer
        const header = panel?.firstElementChild;
        const footer = panel?.lastElementChild;
        expect(header?.classList.contains('shrink-0')).toBe(true);
        expect(footer?.classList.contains('shrink-0')).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// HTTP Method filter toggle
// ---------------------------------------------------------------------------
describe('SettingsModal — HTTP method filter toggle', () => {
    it('toggles a method ON when clicked while inactive', () => {
        const { setSettings } = renderModal();
        fireEvent.click(screen.getByRole('button', { name: 'GET' }));
        expect(setSettings).toHaveBeenCalledTimes(1);
        // setSettings is called with an updater fn; invoke it with current state
        const updater = setSettings.mock.calls[0][0];
        const next = updater(defaultSettings);
        expect(next.filterMethods).toContain('GET');
    });

    it('toggles a method OFF when clicked while active', () => {
        const { setSettings } = renderModal({}, { filterMethods: ['GET'] });
        fireEvent.click(screen.getByRole('button', { name: 'GET' }));
        const updater = setSettings.mock.calls[0][0];
        const next = updater({ ...defaultSettings, filterMethods: ['GET'] });
        expect(next.filterMethods).not.toContain('GET');
    });

    it('can activate multiple methods independently', () => {
        const { setSettings } = renderModal({}, { filterMethods: ['POST'] });
        fireEvent.click(screen.getByRole('button', { name: 'DELETE' }));
        const updater = setSettings.mock.calls[0][0];
        const next = updater({ ...defaultSettings, filterMethods: ['POST'] });
        expect(next.filterMethods).toContain('DELETE');
        expect(next.filterMethods).toContain('POST');
    });
});

// ---------------------------------------------------------------------------
// Group By & Sort By radio options
// ---------------------------------------------------------------------------
describe('SettingsModal — Group By options', () => {
    it('renders all 4 group-by options', () => {
        renderModal();
        expect(screen.getByText('Default')).toBeInTheDocument();
        expect(screen.getByText('API Name')).toBeInTheDocument();
        expect(screen.getByText('Controller')).toBeInTheDocument();
        expect(screen.getByText('URI Patterns')).toBeInTheDocument();
    });

    it('updates groupBy when an option is selected', () => {
        const { setSettings } = renderModal();
        // Clicking the visible label pill triggers the hidden radio's onChange
        fireEvent.click(screen.getByText('API Name'));
        expect(setSettings).toHaveBeenCalled();
        const updater = setSettings.mock.calls[0][0];
        const next = updater(defaultSettings);
        expect(next.groupBy).toBe('apiName');
    });
});

describe('SettingsModal — Sort By options', () => {
    it('renders all 3 sort-by options', () => {
        renderModal();
        expect(screen.getByText('Name (Title)')).toBeInTheDocument();
        expect(screen.getByText('Route Name')).toBeInTheDocument();
        expect(screen.getByText('HTTP Method')).toBeInTheDocument();
    });
});

// ---------------------------------------------------------------------------
// Filter inputs
// ---------------------------------------------------------------------------
describe('SettingsModal — filter inputs', () => {
    it('updates filterNameRegex when typed into', () => {
        const { setSettings } = renderModal();
        const input = screen.getByPlaceholderText('e.g. ^users\\.');
        fireEvent.change(input, { target: { value: '^api' } });
        const updater = setSettings.mock.calls[0][0];
        const next = updater(defaultSettings);
        expect(next.filterNameRegex).toBe('^api');
    });

    it('updates filterControllerRegex when typed into', () => {
        const { setSettings } = renderModal();
        const input = screen.getByPlaceholderText('e.g. UserController');
        fireEvent.change(input, { target: { value: 'Auth' } });
        const updater = setSettings.mock.calls[0][0];
        const next = updater(defaultSettings);
        expect(next.filterControllerRegex).toBe('Auth');
    });

    it('updates filterUriRegex when typed into', () => {
        const { setSettings } = renderModal();
        const input = screen.getByPlaceholderText('e.g. ^api/v1/');
        fireEvent.change(input, { target: { value: '^api/v2' } });
        const updater = setSettings.mock.calls[0][0];
        const next = updater(defaultSettings);
        expect(next.filterUriRegex).toBe('^api/v2');
    });
});

// ---------------------------------------------------------------------------
// Preferences toggles
// ---------------------------------------------------------------------------
describe('SettingsModal — preference toggles', () => {
    it('toggles globalHeaders preference', () => {
        const { setSettings } = renderModal();
        const checkbox = screen.getAllByRole('checkbox')[0];
        fireEvent.click(checkbox);
        const updater = setSettings.mock.calls[0][0];
        const next = updater(defaultSettings);
        expect(next.globalHeaders).toBe(true);
    });

    it('toggles saveResponses preference', () => {
        const { setSettings } = renderModal();
        const checkboxes = screen.getAllByRole('checkbox');
        fireEvent.click(checkboxes[1]);
        const updater = setSettings.mock.calls[0][0];
        const next = updater(defaultSettings);
        expect(next.saveResponses).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// Clear Local Storage
// ---------------------------------------------------------------------------
describe('SettingsModal — Clear Local Storage', () => {
    it('calls confirm before clearing storage', () => {
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
        renderModal();
        fireEvent.click(screen.getByText('Clear Local Storage'));
        expect(confirmSpy).toHaveBeenCalledTimes(1);
        confirmSpy.mockRestore();
    });

    it('does NOT reload if user cancels confirm', () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const reloadSpy = vi.fn();
        Object.defineProperty(window, 'location', {
            value: { reload: reloadSpy },
            writable: true,
        });
        renderModal();
        fireEvent.click(screen.getByText('Clear Local Storage'));
        expect(reloadSpy).not.toHaveBeenCalled();
        vi.restoreAllMocks();
    });
});
