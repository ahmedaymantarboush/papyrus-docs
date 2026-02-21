import { useState } from 'react';

/**
 * useLocalStorage — Persists state in localStorage with JSON serialization.
 * Gracefully falls back to initialValue on read/write failure.
 */
export default function useLocalStorage(key, initialValue) {
    const [storedValue, setStoredValue] = useState(() => {
        try {
            const item = window.localStorage.getItem(key);
            if (item) return JSON.parse(item);
        } catch (error) { console.warn(error); }
        return initialValue;
    });

    const setValue = (value) => {
        try {
            const valueToStore = value instanceof Function ? value(storedValue) : value;
            setStoredValue(valueToStore);
            window.localStorage.setItem(key, JSON.stringify(valueToStore));
        } catch (error) { console.warn(error); }
    };
    return [storedValue, setValue];
}
