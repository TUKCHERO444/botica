# Documentación — Botica

> **Última actualización del índice:** 23/11/2026

Esta carpeta contiene las especificaciones y registros de decisiones del sistema. Antes de modificar un módulo, leer su documento y contrastarlo con el código: algunos documentos son **históricos** y describen lógica que ya fue eliminada.

---

## Documentación general (vigente)

| Documento | Contenido |
|---|---|
| [`arquitectura-y-stack.md`](arquitectura-y-stack.md) | Stack, estructura, guards, permisos, datos, stock/kardex, servicios, testing, deuda técnica |
| [`flujo-inventario-lotes-fefo.md`](flujo-inventario-lotes-fefo.md) | Flujo operativo: producto, ingreso, lotes, FEFO, venta fraccionada, campos y CRUD |
| [`que-hace-el-sistema-para-el-cliente.md`](que-hace-el-sistema-para-el-cliente.md) | Explicación simple para el cliente: funcionalidades, beneficios y alcance |
| [`funcionalidades-del-sistema.md`](funcionalidades-del-sistema.md) | Funcionalidades actuales del sitio público y del panel, roles y datos demo |
| [`panel-administrativo.md`](panel-administrativo.md) | SPA sobre Blade: router, modales, toasts, Vite, caché y flujos |
| [`mapeo-paginacion-modulos.md`](mapeo-paginacion-modulos.md) | Módulos, tablas y estado de paginación; parcial global y criterio de 10 registros |
| [`mapeo-modales-ui.md`](mapeo-modales-ui.md) | Tamaños de modales por módulo y reglas de ancho |

**Empezar por estos tres documentos.**

---

## Especificaciones por módulo

| Documento | Módulo | Notas |
|---|---|---|
| [`modulo-catalogo-publico.md`](modulo-catalogo-publico.md) | Catálogo público | El criterio de visibilidad descrito (`estado = 1` o `stock > 0`) no coincide con el código: hoy solo `estado = 1` |
| [`modulo-carrito-compras.md`](modulo-carrito-compras.md) | Carrito | Carrito persistente + PDF + WhatsApp (no checkout) |
| [`modulo-registro-clientes-web.md`](modulo-registro-clientes-web.md) | Registro de clientes | Guard `clientes`, `registro_web` |
| [`modulo-perfil-cliente-web.md`](modulo-perfil-cliente-web.md) | Perfil de cliente | Datos, contraseña e historial |
| [`modulo-multi-imagen-producto.md`](modulo-multi-imagen-producto.md) | Imágenes de producto | Hasta 4, Cloudinary, orden drag-and-drop |
| [`modulo-banners.md`](modulo-banners.md) | Banners de portada | CRUD + orden + Cloudinary |
| [`sliders-publicos.md`](sliders-publicos.md) | Sliders de productos | "Más buscados", "Más vendidos", "En descuento" |
| [`modulo-portada-categorias.md`](modulo-portada-categorias.md) | Categorías en portada | Selección y orden |
| [`modulo-promociones.md`](modulo-promociones.md) | Promociones | Layouts, descuentos, combos y clics |
| [`modulo-whatsapp-contacto.md`](modulo-whatsapp-contacto.md) | WhatsApp | Botón de consulta y cotización |
| [`modulo-caja.md`](modulo-caja.md) | Caja | Apertura, efectivo vs. digital, arqueo, categorías, cierre e historial |
| [`modulo-metodos-pago.md`](modulo-metodos-pago.md) | Métodos de pago | Entidad dinámica, pagos mixtos, `afecta_efectivo` y desglose `pago` |
| [`modulo-datos-sistema.md`](modulo-datos-sistema.md) | Datos del sistema | Panel de configuración; nombre del negocio en sidebar, tickets y reportes |
| [`modulo-ventas.md`](modulo-ventas.md) | Ventas | Venta confirmada, anulación con motivo, reversión de stock y caja |
| [`modulo-atenciones.md`](modulo-atenciones.md) | Atenciones | Productos + servicios, ticket imprimible, anulación con motivo y reversión |
| [`modulo-devoluciones-cliente.md`](modulo-devoluciones-cliente.md) | Devoluciones de cliente (C1) | Devolución total por anulación, parcial con ajustes, reembolso opcional y criterios de reingreso |
| [`modulo-compras-proveedores.md`](modulo-compras-proveedores.md) | Compras y devoluciones a proveedores | Documento, estados, recepción, pago, conciliación y devolución por lote |
| [`modulo-ajustes-inventario.md`](modulo-ajustes-inventario.md) | Ajustes de inventario | Conteo físico, daño, vencimiento, consumo interno y ajuste positivo por lote |
| [`modulo-conciliaciones.md`](modulo-conciliaciones.md) | Conciliaciones de inventario | Comparación stock vs. lotes, historial persistido y comando `inventario:conciliar` |
| [`kardex-append-only.md`](kardex-append-only.md) | Kardex append-only | Reversiones con `REVERSION`, qué se conserva y cómo leerlo |
| [`modulo-reportes.md`](modulo-reportes.md) | Reportes PDF/CSV | 30 reportes operativos, de gestión y gerenciales, con exportación y filtros |
| [`analisis-reportes-y-recomendaciones.md`](analisis-reportes-y-recomendaciones.md) | Análisis de reportes | Diagnóstico base, hallazgos, KPIs y catálogo propuesto |
| [`analisis-caja-y-recomendaciones.md`](analisis-caja-y-recomendaciones.md) | Análisis de caja | Diagnóstico, arqueo, flujo de efectivo real y roadmap |
| [`roadmap-mejora-reportes.md`](roadmap-mejora-reportes.md) | Roadmap de reportes | Secuencia de implementación, esfuerzo, dependencias y estado de avance |

---

## Planes, decisiones e integraciones

| Documento | Tipo | Estado |
|---|---|---|
| [`plan-auditoria.md`](plan-auditoria.md) | Diseño del módulo de auditoría | Implementado |
| [`plan-venta-fraccionada.md`](plan-venta-fraccionada.md) | Venta fraccionada, unidades y presentaciones | Implementado parcialmente (fases 1-5; combos y código de barras pendientes) |
| [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md) | Rediseño de lotes, vencimientos y FEFO | Implementado parcialmente (núcleo operativo; reportes de costo y backfill pendientes) |
| [`plan-mejora-compras-proveedores.md`](plan-mejora-compras-proveedores.md) | Mejora de compras a proveedores (contraste carwash → botica) | Fases 1-7 implementadas |
| [`plan-fase-8-mejoras.md`](plan-fase-8-mejoras.md) | Backlog post fase 7 ordenado por dificultad | Propuesta para revisión |
| [`plan-listado-diario-ventas-atenciones.md`](plan-listado-diario-ventas-atenciones.md) | Listado diario por usuario + historial con filtros en ventas y atenciones | Implementado (sin cambios de BD) |
| [`plan-inventario-inicial-d1.md`](plan-inventario-inicial-d1.md) | D1/D2: inventario inicial y migración de existencias | D1.1–D1.4 y D2 núcleo implementados; piloto y legados pendientes |
| [`flujo-productos-compras-inventario-ventas.md`](flujo-productos-compras-inventario-ventas.md) | Documento de referencia externo del sistema carwash | Referencia (no especificación) |
| [`plan-eliminacion-logica-lotes.md`](plan-eliminacion-logica-lotes.md) | Decisión histórica: eliminar lotes/FEFO | Histórico (reemplazado por `plan-lotes-fefo-v2.md`) |
| [`cache-panel-administrativo.md`](cache-panel-administrativo.md) | Bug y decisión: no cachear el panel | Vigente (trait no-op) |
| [`flujo-cloudinary-productos.md`](flujo-cloudinary-productos.md) | Flujo de imágenes con Cloudinary | Implementado |
| [`plan-integracion-cloudinary.md`](plan-integracion-cloudinary.md) | Plan de integración inicial | Implementado (referencia) |
| [`feature_reniec_integracion.md`](feature_reniec_integracion.md) | Integración RENIEC (diseño original con decolecta) | Histórico parcial: DNI y RUC usan json.pe |
| [`integracion-jsonpe.md`](integracion-jsonpe.md) | Integración json.pe para consulta DNI y RUC | Implementado |
| [`feature_sunat_integracion.md`](feature_sunat_integracion.md) | Integración SUNAT (diseño original con decolecta) | Histórico parcial: DNI y RUC usan json.pe |
| [`documentacion_api_reniec.txt`](documentacion_api_reniec.txt) | Referencia de la API externa | Externo |
| [`documentacion_api_sunat.txt`](documentacion_api_sunat.txt) | Referencia de la API externa | Externo |

---

## Datos de prueba

| Archivo | Contenido |
|---|---|
| [`ejemplo-inventario-inicial-100-productos.csv`](ejemplo-inventario-inicial-100-productos.csv) | 100 productos ficticios con lotes, vencimientos, costos y presentaciones para probar la importación CSV |
| [`ejemplo-inventario-inicial-100-productos.md`](ejemplo-inventario-inicial-100-productos.md) | Convenciones, columnas, resultado esperado y pasos de prueba |

---

## Documentos históricos (no usar como especificación)

Estos documentos describen la lógica de **lotes, FEFO y vencimientos**, eliminada del sistema. Se conservan solo como referencia:

- [`plan-kardex.md`](plan-kardex.md)
- [`plan-control-vencimientos-lote.md`](plan-control-vencimientos-lote.md)
- [`kardex.txt`](kardex.txt)

La lógica vigente de stock está en [`arquitectura-y-stack.md`](arquitectura-y-stack.md) y [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md): stock en unidad base con lotes/capas, FEFO y trazabilidad en `kardex_movimientos`.

---

## Regla de mantenimiento

Al cambiar el comportamiento de un módulo:

1. Actualizar su `modulo-*.md` si existe.
2. Actualizar `funcionalidades-del-sistema.md` si cambia la experiencia de usuario.
3. Actualizar `arquitectura-y-stack.md` si cambia el stack, la estructura, los permisos o la lógica de datos.
4. Si el cambio elimina una funcionalidad, marcar el documento afectado como histórico en este índice.
