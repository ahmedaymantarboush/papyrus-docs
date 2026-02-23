import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import Playground from '../Playground';
import React from 'react';

// Mock json-edit-react to prevent complex rendering issues in jsdom.
vi.mock('json-edit-react', () => ({
    JsonEditor: () => <div data-testid="json-editor-mock">JSON Editor Mock</div>
}));

describe('Playground Component', () => {
    const mockRoute = {
        uri: 'users/{id}',
        methods: ['POST'],
        title: 'Create User',
        description: 'Mock route'
    };

    const mockSettings = { saveResponses: false };
    const mockCustomHeaders = [];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Add Query Parameter button even when queryTree is empty', () => {
        const setQueryTreeMock = vi.fn();
        
        render(
            <Playground 
                route={mockRoute}
                open={true}
                settings={mockSettings}
                customHeaders={mockCustomHeaders}
                formValues={{}}
                pathVals={{}}
                queryValues={{}}
                formTree={[]}
                queryTree={[]} // EMPTY! We want to ensure the ADD button renders.
                setQueryTree={setQueryTreeMock}
            />
        );

        // Click the TRY IT / SNIPPETS tab switch to see if we can get to params
        // Click the 'params' tab using its text
        const paramsTab = screen.getByText('Params');
        fireEvent.click(paramsTab);

        // Look for the "ADD QUERY PARAM" text
        const addBtn = screen.getByText(/ADD QUERY PARAM/i);
        expect(addBtn).toBeInTheDocument();

        // Click it and verify the mock was called with a new empty parameter object
        fireEvent.click(addBtn);
        expect(setQueryTreeMock).toHaveBeenCalledTimes(1);
        
        // Verify it adds a param
        const callArgs = setQueryTreeMock.mock.calls[0][0];
        expect(Array.isArray(callArgs)).toBe(true);
        expect(callArgs.length).toBe(1);
        expect(callArgs[0]).toHaveProperty('key', 'param_0');
        expect(callArgs[0]).toHaveProperty('enabled', true);
    });

    it('renders Add Body Property button even when formTree is empty', () => {
        const setFormTreeMock = vi.fn();
        
        render(
            <Playground 
                route={mockRoute}
                open={true}
                settings={mockSettings}
                customHeaders={mockCustomHeaders}
                formValues={{}}
                pathVals={{}}
                queryValues={{}}
                queryTree={undefined}
                formTree={[]} // EMPTY
                setFormTree={setFormTreeMock}
            />
        );

        // Click the 'params' tab
        const paramsTab = screen.getByText('Params');
        fireEvent.click(paramsTab);

        // Look for the "ADD BODY PROPERTY" text
        const addBtn = screen.getByText(/ADD BODY PROPERTY/i);
        expect(addBtn).toBeInTheDocument();

        // Click it and verify the mock was called with a new empty property object
        fireEvent.click(addBtn);
        expect(setFormTreeMock).toHaveBeenCalledTimes(1);
        
        // Verify it adds a body property
        const callArgs = setFormTreeMock.mock.calls[0][0];
        expect(Array.isArray(callArgs)).toBe(true);
        expect(callArgs.length).toBe(1);
        expect(callArgs[0]).toHaveProperty('key', 'new_prop_0');
        expect(callArgs[0]).toHaveProperty('enabled', true);
    });
});
