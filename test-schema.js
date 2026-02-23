import { buildInitialTree } from './resources/js/helpers/schema.js';

const backendSchema = [
    { key: 'course_ids', type: 'array', childType: 'number', rules: ['array', 'nullable'] }
];

const savedCache = [
    { key: 'course_ids', type: 'object', value: '', children: [
        { key: '*', type: 'number', value: '' }
    ]}
];

const hydrated = buildInitialTree(backendSchema, savedCache);
console.log(JSON.stringify(hydrated, null, 2));
