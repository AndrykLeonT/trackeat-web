// Navegación por rol (espejo de AuthenticatedSessionController::store en el backend).
// Los cuatro roles del personal se listan uno por uno; cada rol de operación tiene su propia página de inicio.
export const rolesPersonal = ['administrador', 'cocinero', 'mesero', 'cajero'];

export function inicioPorRol(rol) {
    switch (rol) {
        case 'administrador':
            return '/admin';
        case 'cocinero':
            return '/comandas';
        case 'cajero':
            return '/caja';
        case 'mesero':
            return '/mesero/pedidos';
        case 'cliente':
            return '/menu/';
        default:
            return '/';
    }
}
