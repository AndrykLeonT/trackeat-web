import { router } from '@inertiajs/svelte';

// Utilidades del módulo de caja. Los montos llegan como texto ("60.00"); las cuentas
// se hacen en centavos enteros para no arrastrar errores de punto flotante.

const formato = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

export function dinero(valor) {
    return formato.format(Number(valor ?? 0));
}

// Igual que App\Support\Money::parse: hasta 10 enteros y 2 decimales. Regresa null si no es válido.
export function centavos(valor) {
    const m = String(valor ?? '')
        .trim()
        .match(/^(\d{1,10})(?:\.(\d{1,2}))?$/);
    if (!m) return null;
    return Number(m[1]) * 100 + Number((m[2] ?? '').padEnd(2, '0'));
}

// Deja solo lo que puede ser un importe: dígitos y un punto, hasta 10 enteros y 2 decimales.
// Las comas se quitan porque son separador de miles ("$1,500.00" → "1500.00").
export function limpiarDinero(texto) {
    let s = String(texto ?? '').replace(/[^\d.]/g, '');
    const punto = s.indexOf('.');
    if (punto !== -1) s = s.slice(0, punto + 1) + s.slice(punto + 1).replace(/\./g, '');
    const [enteros, decimales] = s.split('.');
    return decimales === undefined ? enteros.slice(0, 10) : `${enteros.slice(0, 10)}.${decimales.slice(0, 2)}`;
}

export function deCentavos(c) {
    const signo = c < 0 ? '-' : '';
    const abs = Math.abs(c);
    return `${signo}${Math.floor(abs / 100)}.${String(abs % 100).padStart(2, '0')}`;
}

export const metodos = {
    efectivo: 'Efectivo',
    tarjeta: 'Tarjeta',
    transferencia: 'Transferencia',
};

export const estadosPedido = {
    recibido: 'Recibido',
    preparacion: 'En preparación',
    listo: 'Listo',
    entregado: 'Entregado',
    cancelado: 'Cancelado',
};

export function entrega(pedido) {
    return pedido.tipo_entrega === 'comer_aqui' ? `Mesa ${pedido.num_mesa}` : 'Para llevar';
}

export function fecha(iso) {
    return iso ? new Date(iso).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

// Diferencia de un corte: positiva = faltante, negativa = sobrante (misma convención que CajaService).
// Acepta el texto del servidor ("10.00", "-5.00") o centavos ya calculados.
export function diferencia(valor) {
    let c = null;
    if (typeof valor === 'number') {
        c = valor;
    } else if (valor !== null && valor !== undefined) {
        const texto = String(valor);
        const abs = centavos(texto.replace(/^-/, ''));
        c = abs === null ? null : texto.startsWith('-') ? -abs : abs;
    }
    if (c === null) return { texto: '—', tono: '' };
    if (c === 0) return { texto: 'Cuadra', tono: 'text-emerald-600 dark:text-emerald-400' };
    return c < 0
        ? { texto: `Sobrante ${dinero(-c / 100)}`, tono: 'text-amber-600 dark:text-amber-400' }
        : { texto: `Faltante ${dinero(c / 100)}`, tono: 'text-destructive' };
}

// "En vivo": vuelve a pedir solo las props indicadas cada pocos segundos mientras la pestaña está visible.
export function recargarCada(only, ms = 5000) {
    const id = setInterval(() => {
        if (document.visibilityState === 'visible') {
            router.reload({ only, preserveScroll: true, preserveState: true });
        }
    }, ms);
    return () => clearInterval(id);
}
