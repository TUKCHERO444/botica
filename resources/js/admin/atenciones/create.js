import { initAtencionForm } from './atencion-form.js';
import { initClientSearch } from '../client-search.js';
import { initPagos } from '../pagos.js';

initAtencionForm({ productStartIndex: 0, serviceStartIndex: 1 });
initClientSearch();
initPagos();
