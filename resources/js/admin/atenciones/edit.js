import { initAtencionForm } from './atencion-form.js';
import { initClientSearch } from '../client-search.js';

const prodContainer = document.getElementById('productos-container');
const servContainer = document.getElementById('servicios-container');
const productStartIndex = parseInt(prodContainer?.dataset.startIndex, 10) || 0;
const serviceStartIndex = parseInt(servContainer?.dataset.startIndex, 10) || 1;

initAtencionForm({ productStartIndex, serviceStartIndex });
initClientSearch();
