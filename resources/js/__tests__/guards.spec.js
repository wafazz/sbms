import { describe, it, expect } from 'vitest';
import { resolveAccess } from '../router/guards.js';

describe('resolveAccess', () => {
    it('sends signed-out users from protected pages to login', () => {
        expect(resolveAccess({ requiresAuth: true }, { isAuthenticated: false })).toEqual({ name: 'login' });
    });

    it('lets signed-in users through protected pages', () => {
        expect(resolveAccess({ requiresAuth: true }, { isAuthenticated: true, role: 'staff' })).toBeNull();
    });

    it('sends signed-in users away from the login page', () => {
        expect(resolveAccess({ guestOnly: true }, { isAuthenticated: true })).toEqual({ name: 'dashboard' });
    });

    it('lets signed-out users see the login page', () => {
        expect(resolveAccess({ guestOnly: true }, { isAuthenticated: false })).toBeNull();
    });

    it('blocks staff from admin-only pages', () => {
        expect(
            resolveAccess({ requiresAuth: true, roles: ['admin'] }, { isAuthenticated: true, role: 'staff' }),
        ).toEqual({ name: 'forbidden' });
    });

    it('allows admins onto admin-only pages', () => {
        expect(
            resolveAccess({ requiresAuth: true, roles: ['admin'] }, { isAuthenticated: true, role: 'admin' }),
        ).toBeNull();
    });

    it('allows any route with no meta', () => {
        expect(resolveAccess({}, {})).toBeNull();
        expect(resolveAccess(undefined, undefined)).toBeNull();
    });
});
