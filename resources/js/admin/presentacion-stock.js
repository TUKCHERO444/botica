/**
 * Equivalencias visuales de una cantidad en unidad base.
 * No calcula stock ni valida: solo traduce cantidades para mostrarlas.
 */

export function formatearCantidad(valor) {
  const numero = Number(valor);

  if (!Number.isFinite(numero)) return '0';

  return String(Math.round(numero * 1000) / 1000);
}

function pluralizar(nombre) {
  if (!nombre) return nombre;

  const ultima = nombre.slice(-1).toLowerCase();

  if (ultima === 'z') return nombre.slice(0, -1) + 'ces';
  if (ultima === 'd') return nombre + 'es';

  return nombre + 's';
}

export function desglosarCantidad(cantidad, presentaciones = [], unidad = '') {
  const valor = Number(cantidad);

  if (!Number.isFinite(valor) || valor <= 0) return '';

  const lista = [...presentaciones]
    .map((presentacion) => ({
      nombre: presentacion?.nombre || '',
      factor: Number(presentacion?.factor),
    }))
    .filter((presentacion) => presentacion.nombre && presentacion.factor > 0)
    .sort((a, b) => b.factor - a.factor);

  let restante = valor;
  const partes = [];

  for (const presentacion of lista) {
    if (presentacion.factor <= 1) continue;

    const cantidadPresentacion = Math.floor(restante / presentacion.factor);

    if (cantidadPresentacion <= 0) continue;

    partes.push(
      formatearCantidad(cantidadPresentacion) + ' ' + pluralizar(presentacion.nombre)
      + ' x' + formatearCantidad(presentacion.factor)
    );

    restante = Math.round((restante - cantidadPresentacion * presentacion.factor) * 1000) / 1000;
  }

  const base = lista.find((presentacion) => presentacion.factor === 1);

  if (restante > 0) {
    if (base) {
      partes.push(formatearCantidad(restante) + ' ' + pluralizar(base.nombre));
    } else if (unidad) {
      partes.push(formatearCantidad(restante) + ' ' + unidad);
    }
  }

  return partes.join(' + ');
}
