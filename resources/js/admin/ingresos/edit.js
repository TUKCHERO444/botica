import { initIngresoForm } from './ingreso-form.js';

const container = document.getElementById('productos-container');
const startIndex = parseInt(container?.dataset.startIndex, 10) || 1;

initIngresoForm({ startIndex });
