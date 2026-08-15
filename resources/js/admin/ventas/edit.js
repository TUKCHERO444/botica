import { initVentaForm } from './venta-form.js';
import { initClientSearch } from '../client-search.js';

const container = document.getElementById('productos-container');
const startIndex = parseInt(container?.dataset.startIndex, 10) || 1;

initVentaForm({ startIndex });
initClientSearch();
