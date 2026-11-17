/**
 * Pure access rule for a route. Returns the route name to redirect to,
 * or null when the navigation may continue.
 *
 * meta.requiresAuth - signed-in users only
 * meta.guestOnly    - signed-out users only (the login page)
 * meta.roles        - list of role names allowed to view the route
 */
export function resolveAccess(meta = {}, { isAuthenticated = false, role = null } = {}) {
    if (meta.requiresAuth && !isAuthenticated) {
        return { name: 'login' };
    }

    if (meta.guestOnly && isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (meta.roles?.length && !meta.roles.includes(role)) {
        return { name: 'forbidden' };
    }

    return null;
}
