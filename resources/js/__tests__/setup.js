/**
 * Vitest global test setup.
 * Configures jsdom environment defaults for React component/helper tests.
 */

// Mock window.PapyrusConfig so PC() doesn't crash in tests
window.PapyrusConfig = {
    base_url: 'http://localhost',
    headers: { 'Accept': 'application/json' },
    defaultResponses: ['200', '422'],
};

// Mock localStorage
const store = {};
const mockLocalStorage = {
    getItem: (key) => store[key] || null,
    setItem: (key, value) => { store[key] = value; },
    removeItem: (key) => { delete store[key]; },
    clear: () => { Object.keys(store).forEach(k => delete store[k]); },
};
Object.defineProperty(window, 'localStorage', { value: mockLocalStorage });
