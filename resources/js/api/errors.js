/**
 * Helpers for reading API error responses.
 * Every error body is { message, errors? }.
 */

/**
 * Field errors from a 422 response, as { field: 'first message' }.
 * Returns an empty object for any other error.
 */
export function fieldErrors(error) {
    const errors = error?.response?.status === 422 ? error.response.data?.errors ?? {} : {};

    return Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
    );
}

/**
 * A readable message for any error, with a fallback.
 */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
    return error?.response?.data?.message ?? fallback;
}
