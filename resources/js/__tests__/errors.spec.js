import { describe, it, expect } from 'vitest';
import { errorMessage, fieldErrors } from '../api/errors.js';

const apiError = (status, data) => ({ response: { status, data } });

describe('fieldErrors', () => {
    it('returns the first message per field for a 422', () => {
        const error = apiError(422, {
            message: 'The given data was invalid.',
            errors: { email: ['Email is required.', 'Email must be valid.'] },
        });

        expect(fieldErrors(error)).toEqual({ email: 'Email is required.' });
    });

    it('returns an empty object for non-validation errors', () => {
        expect(fieldErrors(apiError(500, { message: 'Server error.' }))).toEqual({});
        expect(fieldErrors(undefined)).toEqual({});
    });
});

describe('errorMessage', () => {
    it('prefers the API message', () => {
        expect(errorMessage(apiError(403, { message: 'This action is unauthorized.' }))).toBe(
            'This action is unauthorized.',
        );
    });

    it('falls back when there is no API message', () => {
        expect(errorMessage(new Error('Network down'), 'Try again.')).toBe('Try again.');
    });
});
