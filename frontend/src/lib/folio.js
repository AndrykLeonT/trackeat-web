// Folio visible de un pedido: TE-20261124-007 se muestra como #007.
// Los folios anteriores (TE- + código largo, DEMO-001) se muestran tal cual.
export function formatoFolio(numero) {
    const coincidencia = /^TE-\d{8}-(\d+)$/.exec(numero ?? '');
    return coincidencia ? `#${coincidencia[1]}` : (numero ?? '');
}
