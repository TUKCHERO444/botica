# Funcionalidades del Sistema — Botica

> **Última actualización:** 20/11/2026
> **Alcance:** estado funcional vigente del sitio público y del panel administrativo. Verificado contra controladores, rutas, vistas y seeders.

---

## Visión general

La aplicación combina:

- Una **tienda pública** con portada editable, catálogo, detalle de producto, combos, carrito-cotización y área de cliente.
- Un **panel administrativo SPA** para inventario, compras, ventas, atenciones, caja, clientes, contenido de portada, auditoría y reportes.

Hay dos tipos de usuario totalmente separados: **administradores** (`users`, guard `web`) y **clientes** (`cliente`, guard `clientes`).

---

# 1. Sitio público

## 1.1. Layout y navegación

- **Marquee superior** con los textos activos de `header_text` (ordenables desde el panel). Si no hay textos, muestra un mensaje por defecto.
- **Navbar** con:
  - Logo/enlace a inicio.
  - **Buscador global** con sugerencias en vivo (mínimo 2 caracteres, hasta 10 productos: nombre, precio, categoría, imagen).
  - Enlace "Nuestros productos".
  - Login/registro o, si el cliente está autenticado, "Mi Perfil", saludo y cerrar sesión.
  - Icono de carrito con contador (persistente).
  - Menú hamburguesa en móvil.
- **Footer** con mapa Leaflet y datos de contacto.

## 1.2. Página de inicio (`/`)

Se compone, en este orden, de:

1. **Categorías de portada**: píldoras con las categorías seleccionadas y ordenadas en el panel; enlazan al catálogo filtrado.
2. **Carrusel de banners**: imágenes activas ordenadas, con título/subtítulo opcional y enlace; rotación automática cada 5 s, flechas, puntos y teclado.
3. **Bloques de promociones**: grid responsivo de 12 columnas con tres tamaños (`principal`, `secundaria`, `terciaria`). Cada promoción puede ser:
   - Imagen con enlace manual.
   - **Descuento** sobre producto, marca o categoría (el enlace se resuelve automáticamente).
   - **Combo** (enlaza al detalle del combo si está activo).
   - Cada clic se registra mediante `POST /promociones/{promocion}/click` (contador `clicks`).
4. **Tres sliders de productos**: "Más buscados", "Más vendidos" y "En descuento". Se administran desde el panel; solo se muestran productos activos y en el orden definido.

> Los sliders, banners, promociones y categorías de portada **no** se generan automáticamente a partir de ventas: su contenido se asigna manualmente en el panel.

## 1.3. Catálogo (`/productos`)

- Grilla responsive de 2 a 5 columnas, 20 productos por página.
- **Filtros** (sidebar en escritorio, panel deslizante en móvil):
  - Categorías (multiselección, con conteo).
  - Marcas (multiselección, con conteo).
  - Precio máximo (slider con el rango real del catálogo).
- Filtrado **AJAX** sin recargar: se actualiza la grilla y la URL (`categorias[]`, `marcas[]`, `precio_max`), por lo que los filtros son compartibles y funcionan con atrás/adelante.
- **Buscador con autocompletado** dentro del catálogo y en el navbar. Las sugerencias enlazan al detalle; no filtran la grilla por texto.
- Los productos con varias presentaciones muestran “Desde S/ …” con el menor precio activo.
- Solo se muestran productos con `estado = 1`.

## 1.4. Detalle de producto (`/productos/{producto}`)

- Migas de pan: Inicio > Productos > Categoría > Producto.
- **Galería** de hasta 4 imágenes: imagen principal, miniaturas, flechas, rotación automática cada 5 s y zoom con el mouse.
- Precio de venta, marca, categoría y disponibilidad (`stock > 0` o "sin stock").
- **Selector de presentación** (Caja, Blíster, Tableta, etc.) con precio, stock y paso; la cantidad permite fracciones según la presentación.
- **Botón de WhatsApp** con mensaje predefinido (solo si `WHATSAPP_PHONE_NUMBER` está configurado).
- **Añadir al carrito** (requiere sesión de cliente; si no, invita a iniciar sesión) con la presentación y cantidad elegidas.
- Descripción.
- Hasta **8 productos relacionados** de la misma categoría, en orden aleatorio.

## 1.5. Combos (`/combos/{combo}`)

- Página pública del combo activo.
- Comparativa: precio regular (suma de productos), precio del combo y ahorro.
- Lista de productos incluidos y cantidades.
- Botón de WhatsApp y "Añadir al carrito".
- Un combo inactivo devuelve 404.

## 1.6. Carrito de compras (requiere sesión de cliente)

Es un **carrito de cotización**, no un checkout con pago:

- Se guarda en base de datos (`carrito` / `carrito_item`), un carrito por cliente.
- Admite productos con **presentación** y combos; la edición de cantidades permite valores de 1 a 99 y respeta el paso de la presentación (por ejemplo, 0.5 tabletas).
- Guarda el precio unitario al agregar, para que el carrito no cambie si el precio del producto se modifica después.
- Actualización de cantidades y eliminación vía AJAX, con recálculo de subtotales, total y contador del navbar.
- **Descargar PDF** con el detalle (`carrito-YYYYMMDD-HHMMSS.pdf`).
- **Cotizar por WhatsApp**: genera un mensaje con el detalle, total y datos del cliente, y abre `wa.me`.
- No revalida stock ni estado del producto al renderizar; la compra real se gestiona en el panel.
- No descuenta stock ni crea una venta; la venta real se registra en el panel.

## 1.7. Registro, login y perfil de cliente

| Ruta | Función |
|---|---|
| `GET/POST /registro` | Crear cuenta de cliente (`registro_web = true`) e iniciar sesión |
| `GET/POST /iniciar-sesion` | Login con email/contraseña; registra `ultimo_login` |
| `POST /cerrar-sesion` | Cerrar sesión del guard `clientes` |
| `GET /mi-perfil` | Ver datos e historial |
| `PUT /mi-perfil` | Editar datos personales |
| `PUT /mi-perfil/password` | Cambiar contraseña (valida la actual, mínimo 8 caracteres) |

- El perfil muestra las **últimas 10 ventas** y **últimas 10 atenciones** del cliente.
- El DNI no es editable desde el perfil.
- Los clientes web se distinguen en el panel con el indicador "Registrado".

---

# 2. Panel administrativo

El panel es una **SPA sobre Blade**: el layout se carga una vez y cada módulo se inyecta vía AJAX en `#panel-content`. Los formularios de crear/editar se abren en modales. Detalle técnico en [`panel-administrativo.md`](panel-administrativo.md).

Funcionamiento general:

- Validación en el modal; los errores 422 se muestran junto a cada campo.
- Al guardar, el modal se cierra, el listado se recarga y aparece un toast.
- Eliminar/desactivar pide confirmación.
- Cada módulo está protegido por `permission:<modulo>`.
- Las operaciones de escritura de la mayoría de módulos quedan registradas en **Auditoría**.

## 2.1. Dashboard (`/admin/dashboard`)

- **Tarjetas**: ingresos de hoy (ventas + atenciones), ventas de hoy (cantidad), atenciones de hoy (cantidad), stock bajo (stock vendible ≤ mínimo, con contador de agotados) y lotes por vencer (30 días, con contador de vencidos con stock). Las dos tarjetas de inventario enlazan al listado filtrado.
- **Gráfico de los últimos 7 días**: ingresos por ventas vs. atenciones.
- **Distribución del mes**: proporción ventas vs. atenciones.
- **Top 5 productos más vendidos del mes** (por unidades).
- **Productos en riesgo**: hasta 10 productos activos con stock vendible ≤ `stock_minimo` (incluye agotados). Solo considera productos con historial de lotes, para no alertar altas pendientes de inventario inicial.
- **Vencimientos próximos**: lotes con stock que vencen en los próximos 30 días, con días restantes y cantidad disponible. Si hay lotes vencidos con stock, se muestra un aviso para darlos de baja.

## 2.2. Inventario

### Productos (`/admin/productos`)

- Listado con imagen principal, nombre, categoría, marca, precio, stock en unidad base y estado.
- Búsqueda por nombre y autocompletado.
- **Filtros de alerta**: Todos, Riesgo (bajo mínimo + agotados), Bajo mínimo, Agotados y Por vencer (lotes con stock que vencen en ≤30 días). Los filtros solo consideran productos activos con historial de lotes.
- La celda de stock muestra el badge **Bajo mínimo**, **Agotado** o **Por vencer** y, si el stock físico incluye lotes vencidos, una línea con el stock vendible.
- Crear/editar con: nombre, descripción, categoría, marca, **unidad base**, precio de compra (por unidad base), precio de venta base, **stock mínimo**, estado y **hasta 4 imágenes** (JPG/JPEG/PNG/WEBP, máx. 2 MB cada una) subidas a Cloudinary, reordenables por drag-and-drop. El campo legado `inventario` ya no se muestra y el producto nace con **stock 0**; las existencias se cargan desde **Inventario inicial**.
- **Presentaciones adicionales**: se asignan presentaciones globales con factor (unidades base), precio, paso mínimo y estado. El `paso` se oculta en el formulario normal y solo se muestra en **Configuración avanzada**; por defecto es `1`. La presentación base se crea automáticamente con factor 1 y el precio de venta del producto, y su paso también es editable.
- Al editar se pueden agregar, reordenar y eliminar imágenes; la eliminación borra también el asset de Cloudinary. Las presentaciones quitadas se desactivan para conservar el historial.
- Cambio rápido de estado (activar/desactivar).
- Eliminar = **desactivar** (no borra el registro ni el historial).
- Botón "Ver Kardex" por producto.
- Mantiene el contador `cantidad` de categoría y marca.
- **Precisión decimal:** precios y costos aceptan hasta 2 decimales; cantidades, factores, pasos y stock mínimo hasta 3 decimales. La validación se aplica en navegador y servidor.

### Inventario inicial (`/admin/inventario-inicial`)

- Carga existencias reales de una botica que ya opera, por **lote, vencimiento y costo**, eligiendo la **presentación** de carga (unidad base, caja, blíster, etc.). El sistema convierte cantidad y costo a unidad base para el stock y el kardex.
- Flujo en dos pasos: **borrador** (no mueve stock) y **confirmado** (crea lote, capa, stock y kardex `INGRESO`).
- Carga manual para pocos productos, con **buscador dinámico** por nombre; muestra stock actual y advierte si el producto ya tiene stock/lotes.
- **Borradores editables:** se pueden corregir líneas, cantidades, costos, lotes y vencimientos mientras el inventario esté en borrador. Al confirmar queda inmutable.
- **Importación CSV**: valida, permite dry-run, reporta errores por fila y puede crear productos, categorías, marcas y presentaciones base.
- Plantilla CSV descargable y exportación de productos para conteo físico.
- Solo acepta productos sin stock ni lotes previos; si ya hay stock, se usa un ajuste.
- El inventario confirmado no se edita ni elimina; las correcciones se hacen con ajustes.

### Combos (`/admin/combos`, permiso `combos`)

- CRUD de combos: nombre, descripción, imagen (Cloudinary), precio sugerido, estado activo y productos con cantidades.
- Búsqueda de productos y combos para autocompletado.
- El detalle público compara el precio regular con el precio del combo.

### Categorías (`/admin/categorias`)

- CRUD con nombre, descripción, cantidad de productos y estado.
- Activar/desactivar y eliminar.

### Marcas (`/admin/marcas`)

- CRUD con nombre, descripción, cantidad de productos y estado.
- Activar/desactivar y eliminar.

### Unidades de medida (`/admin/unidades-medida`)

- Catálogo global de unidades base: código, nombre, si permite fracción y presentación base.
- Se usan al crear productos; el stock se controla en esa unidad.
- No se puede eliminar una unidad asignada a productos.

### Presentaciones (`/admin/presentaciones`)

- Catálogo global de presentaciones: Unidad, Tableta, Cápsula, Mililitro, Gramo, Caja, Blíster, Frasco, Sobre, Ampolla, Tubo.
- Se asignan a cada producto con factor, precio, paso configurable (avanzado) y estado.
- No se puede eliminar una presentación asignada a productos o usada como base de una unidad.

### Compras a proveedores (`/admin/ingresos`)

- Registro de compras con correlativo **CMP-XXXXXXXX**, estado y usuario que registra.
- **Borrador:** proveedor, fecha, tipo y número de documento, observaciones, pago desde caja o externo, y múltiples productos con presentación, cantidad, **código de lote**, **fecha de vencimiento** y costo unitario. No mueve stock.
- **Recibir:** crea/actualiza el lote, crea una capa `lote_ingreso`, convierte la cantidad a unidad base, incrementa `producto.stock`, registra `INGRESO` en el kardex y, si corresponde, el egreso de caja.
- **Anular:** revierte stock, lotes, kardex y caja; solo si el lote no tuvo consumo.
- Editar y eliminar solo mientras la compra está en borrador.
- Reporte PDF de compras con filtro por forma de pago y resumen de pagos desde caja vs. fuera de caja.
- Advertencia visual en el formulario si el costo supera el precio de venta de la presentación.

### Devoluciones a proveedor (`/admin/devoluciones`)

- Devolución por **capa de lote** de una compra recibida, no por FEFO.
- Correlativo **DVP-XXXXXXXX**.
- Campos: compra, fecha, motivo, reembolso a caja o externo, método, referencia y líneas con cantidad.
- Descuenta la capa exacta, `producto_lote`, `producto.stock`, registra `lote_consumo` y kardex `DEVOLUCION`.
- Reembolso opcional a caja mediante ingreso.
- Anulación restaura stock, lote y kardex; revierte caja si hubo reembolso.
- No se puede devolver más de lo disponible, ni líneas de otra compra, ni desde compras en borrador o anuladas.

### Ajustes de inventario (`/admin/ajustes`)

- Documento **inmutable** por lote, en unidad base.
- Correlativo **AJU-XXXXXXXX**.
- Tipos: **conteo físico**, **daño**, **vencimiento**, **consumo interno**, **ajuste positivo** y **otro**.
- **Conteo físico:** se ingresa el stock final contado y el sistema calcula la diferencia.
- **Ajuste positivo:** crea una capa nueva, suma stock y lote.
- **Salidas:** descuentan las capas del lote, registran `lote_consumo` y kardex `AJUSTE` negativo.
- No se edita, elimina ni anula: un error se corrige con otro ajuste.
- No mueve caja.

### Kardex (`/admin/kardex`)

- Módulo **solo lectura** de `kardex_movimientos`.
- **Append-only:** no se borra ni regenera; las reversiones se registran como `REVERSION`.
- Filtros por producto, tipo (`INGRESO`/`VENTA`/`ATENCION`/`AJUSTE`/`BAJA`/`DEVOLUCION`/`REVERSION`), usuario y rango de fechas.
- Tabla: fecha, producto, presentación, lote, tipo, referencia, cantidad (+/-), stock anterior → stock nuevo, usuario.
- Vista por producto desde el botón "Ver Kardex" del listado de productos.
- No se puede crear, editar ni eliminar manualmente.

### Lotes y vencimientos (`/admin/lotes`)

- Listado de lotes con **correlativo autogenerado** (`LOTE-00000001`), producto, código, vencimiento, cantidad disponible y estado visual (`vigente`, `próximo`, `crítico`, `vencido`, `agotado`, `baja`).
- Filtros por estado y código, incluido **Próximos 30 días** (0–30) y **Críticos** (0–7).
- **Dar de baja** un lote vencido o bloqueado: descuenta el stock disponible y registra un kardex `BAJA`.
- El FEFO de ventas y atenciones consume primero el lote que vence antes y excluye vencidos.

### Servicios (`/admin/servicios`)

- CRUD de servicios facturables (nombre, descripción, precio y **costo opcional** para calcular margen).
- Se usan en las atenciones.

## 2.3. Clientes (`/admin/clientes`)

- Listado con DNI, nombre completo, contacto, estado de registro web y origen.
- CRUD de clientes con DNI, nombres, email, teléfono, **fecha de nacimiento** y **dirección**.
- **Cliente genérico**: crea/reutiliza el DNI `00000001` con nombre "SIN IDENTIFICAR" para ventas rápidas.
- **Consulta RENIEC** por DNI para autocompletar nombres y apellidos.
- Búsqueda por DNI o nombre usada en ventas y atenciones.
- Los clientes creados desde ventas/atenciones con datos RENIEC se guardan con `registro_web = false`.

## 2.4. Operaciones

### Ventas (`/admin/ventas`)

- Listado con correlativo **T001-XXXXXXXX**, cliente, usuario, total y fecha.
- **Requiere una caja abierta** para registrar.
- Crear: cliente existente, cliente genérico o alta rápida con RENIEC; productos con **presentación**, cantidad y precio; total automático.
- **Pago obligatorio** con métodos activos mostrados como **botones seleccionables**. En pago único se elige un método y se cobra el total sin pedir monto; con **Pago mixto** se despliegan los importes para repartir el total, se bloquean los botones y se exige que la suma coincida exactamente.
- Al guardar:
  - Convierte la cantidad de cada presentación a unidad base y descuenta `producto.stock`.
  - Aplica **FEFO**: consume primero los lotes que vencen antes y registra `lote_consumo` por capa.
  - Registra movimientos `VENTA` en kardex con el lote consumido.
  - Crea un ingreso en la caja abierta y actualiza `total_ingresos`.
- Editar y eliminar: **no disponibles**. Una venta confirmada conserva su detalle original.
- **Anular venta** con motivo obligatorio: restaura stock y lotes, registra `REVERSION` en kardex y un egreso compensatorio en la caja de la venta. Si esa caja ya está cerrada, la anulación se bloquea.
- **Devolución de cliente**: no hay documento aparte. Devolución total = anular la venta; producto no apto = ajuste negativo; devolución parcial = ajuste positivo + egreso manual opcional (o anular + nueva venta). Detalle en `docs/modulo-devoluciones-cliente.md`.
- Las ventas anuladas se excluyen de reportes y dashboard; en caja quedan el ingreso original y el egreso compensatorio.
- Ver detalle y reporte PDF de ventas.
- **Ticket imprimible**: al concretar una venta se abre automáticamente la pantalla del ticket y se lanza el diálogo de impresión. Incluye los botones **Imprimir ticket** y **Volver al panel**. Desde el listado, el botón de impresora abre el mismo ticket en una pestaña nueva.
- El ticket es una página independiente (`admin.ventas.ticket`) sin el layout del panel, optimizada para impresoras térmicas (58/80 mm). Los datos del negocio, el ancho de papel, la moneda y el pie se configuran en `config/ticket.php` mediante las variables `TICKET_*`.

### Atenciones (`/admin/atenciones`)

- Similar a ventas, pero combina **servicios y productos**.
- **Requiere caja abierta** y al menos un servicio.
- **Pago obligatorio**: botones de método con pago único (cobra el total sin monto) o **pago mixto** con desglose de importes; se valida contra el total de productos + servicios.
- Campo de observaciones (máx. 50 caracteres).
- Los productos se agregan con presentación y cantidad; descuenta stock base aplicando **FEFO** y registra movimientos `ATENCION` en kardex con los lotes consumidos.
- Registra el total (productos + servicios) como ingreso de caja, con un movimiento por método de pago.
- La atención nace **confirmada** con correlativo `A001-XXXXXXXX` y **no se edita ni elimina**; si hubo un error se anula con motivo, revirtiendo stock, kardex y caja.
- Las atenciones anuladas se excluyen de reportes y dashboard.
- **Ticket imprimible**: al guardar se abre automáticamente el ticket (dispara el diálogo de impresión) con el desglose de productos, servicios y pagos. Desde el listado y el detalle se puede reimprimir.
- Ver detalle y reporte PDF.

## 2.5. Finanzas

### Métodos de pago (`/admin/metodos-pago`)

- Entidad dinámica: Efectivo, Yape, Plin, BCP y los que se agreguen.
- Crear/editar con nombre único y descripción; activar/desactivar.
- Solo los activos se muestran en ventas y atenciones; los inactivos conservan el historial de pagos ya registrados.
- Ver `docs/modulo-metodos-pago.md`.

### Caja (`/admin/caja`)

- Solo puede existir **una caja abierta** a la vez.
- **Apertura**: monto inicial obligatorio; registra usuario y fecha/hora.
- **Egreso manual**: monto y descripción; actualiza `total_egresos`.
- **Ingresos automáticos**: cada venta y atención suman a `total_ingresos`.
- **Cierre**: calcula `monto_final = monto_inicial + total_ingresos - total_egresos`, marca la caja como `cerrada` y registra quién y cuándo cerró.
- **Historial** de cajas cerradas y detalle inmutable de movimientos.
- Reporte PDF de caja.

## 2.6. Administración

### Usuarios (`/admin/usuarios`)

- Listado con nombre, email, rol y estado activo.
- Crear/editar con asignación de rol; la contraseña es opcional al editar.
- Activar/desactivar.
- Un usuario no puede cambiarse su propio rol ni desactivarse a sí mismo.
- Solo usuarios activos pueden iniciar sesión.

### Roles (`/admin/roles`)

- Listado de roles con sus permisos.
- Crear/editar rol seleccionando módulos completos.
- El rol **Administrador** no se puede editar ni eliminar.
- Permisos disponibles: los módulos del panel + `portada.banners`, `portada.header-text`, `portada.categorias`, `portada.promociones` y `combos`.

### Auditoría (`/admin/auditoria`)

- Bitácora de acciones administrativas: crear, actualizar, eliminar y cambio de estado.
- Guarda módulo, acción, registro afectado, descripción, valores anteriores/nuevos, usuario, IP y fecha.
- Filtros por módulo, usuario, acción, fechas y texto.
- Vista de detalle con diferencias campo a campo.
- Reporte PDF de auditoría.

### Reportes (`/admin/reportes`)

Genera **vista previa HTML**, **PDF A4 horizontal** o **CSV** de **30 reportes**:

| # | Reporte | Filtros principales |
|---|---|---|
| 1 | Ventas | Fechas, usuario, cliente |
| 2 | Atenciones | Fechas, usuario, cliente |
| 3 | Compras | Fechas, proveedor, usuario, forma de pago |
| 4 | Caja | Caja o rango de fechas |
| 5 | Inventario | Categoría, marca, stock máximo, activos/inactivos |
| 6 | Lotes y costos | Categoría, marca, vencidos o próximos 30/90/180/365 días |
| 7 | Kardex | Producto, fechas, tipo de movimiento real |
| 8 | Movimientos especiales | Fechas, tipo, producto, usuario |
| 9 | Rentabilidad | Fechas, categoría, marca |
| 10 | Clientes | Fechas, top N |
| 11 | General del mes | Mes y año |
| 12 | Auditoría | Módulo, usuario, acción, fechas |
| 13 | Cobros por método de pago | Fechas, método, origen (ventas/atenciones) |
| 14 | Ventas por producto | Fechas, categoría, marca |
| 15 | Resumen diario de ventas | Fechas, usuario |
| 16 | Ventas por vendedor y hora | Fechas |
| 17 | Anulaciones | Fechas, usuario |
| 18 | Compras por proveedor | Fechas |
| 19 | Evolución de costos | Fechas, proveedor, producto |
| 20 | Devoluciones a proveedor | Fechas, proveedor |
| 21 | Ajustes y merma | Fechas, tipo de ajuste |
| 22 | Rotación y cobertura | Categoría, marca, ventana de días |
| 23 | Margen agrupado | Fechas, categoría/marca/vendedor |
| 24 | Servicios | Fechas |
| 25 | Clientes RFM | Fechas, segmento, límite |
| 26 | Caja por origen | Fechas |
| 27 | Resumen gerencial | Fechas |
| 28 | Clientes nuevos y adopción web | Fechas |
| 29 | Sugerido de compra | Categoría, marca, ventana de consumo |
| 30 | Cumpleaños | Próximos 7/30/60/90 días |

## 2.7. Portada

### Sliders (`/admin/sliders`)

- Tres pestañas: **Más buscados**, **Más vendidos** y **En descuento**.
- Asignar productos con búsqueda/autocompletado, quitar y reordenar.
- Un producto no puede repetirse dentro del mismo slider.

### Textos del Header (`/admin/header-text`)

- CRUD de textos cortos (máx. 60 caracteres), activo/inactivo y orden.
- Se muestran en el marquee superior del sitio público, separados por un punto.
- Reordenamiento drag-and-drop.

### Banners (`/admin/banners`)

- CRUD de imágenes del carrusel: imagen (Cloudinary), título, subtítulo, enlace opcional y estado.
- Activar/desactivar y reordenar.
- Reemplazar la imagen elimina el asset anterior de Cloudinary.

### Categorías en Portada (`/admin/portada-categorias`)

- Selección de categorías visibles en la portada y su orden.
- Activar/desactivar con un clic; reordenar.

### Promociones (`/admin/promociones`)

- CRUD de bloques promocionales: imagen, título, subtítulo, enlace, layout (`principal`/`secundaria`/`terciaria`), estado y orden.
- Tipo de promoción:
  - **Descuento**: ámbito producto/marca/categoría, tipo porcentaje/fijo y valor.
  - **Combo**: combo asociado.
  - Sin tipo: enlace manual.
- Buscador de entidades para el ámbito del descuento.
- Reordenamiento drag-and-drop y contador de clics.

---

# 3. Roles y permisos

| Rol | Acceso |
|---|---|
| **Administrador** | Todos los módulos del panel + portada + combos |
| **Vendedor** | Ventas y Atenciones |

Los permisos son **por módulo completo** (no por acción). Quien tiene "Productos" puede ver, crear, editar y desactivar productos. El detalle técnico está en [`arquitectura-y-stack.md`](arquitectura-y-stack.md).

---

# 4. Datos de demostración

`php artisan migrate:fresh --seed` crea:

**Usuarios administrativos**

| Email | Contraseña | Rol |
|---|---|---|
| `admin@sistema.com` | `admin123` | Administrador |
| `vendedor@sistema.com` | `vendedor123` | Vendedor |

**Catálogo y operaciones**

- 6 categorías y 6 marcas.
- 4 proveedores.
- 6 clientes (incluido "Cliente Generico").
- 10 productos con precios y stock variados.
- 5 unidades de medida y 11 presentaciones globales.
- Cada producto con su presentación base; Paracetamol, Ibuprofeno y Amoxicilina con Caja x 100 y Blíster x 10 adicionales.
- Un lote de demostración `LOTE-DEMO-*` por producto, con stock disponible y vencimiento a un año.
- 4 servicios.
- 3 compras (`CMP-00000001` a `CMP-00000003`) con documento y detalle.
- 4 ventas históricas.
- 2 atenciones históricas.
- Movimientos de kardex coherentes con las operaciones anteriores.
- 1 caja **abierta** con S/ 200.00 iniciales y los ingresos de las ventas/atenciones demo.

**No se siembran** banners, promociones, combos, sliders ni textos del header: la portada se configura desde el panel.

---

# 5. Requisitos de configuración

| Variable | Uso |
|---|---|
| `WHATSAPP_PHONE_NUMBER` | Botones de WhatsApp (producto, combo, carrito) |
| `JSONPE_API_TOKEN` / `JSONPE_API_URL` | Consulta DNI y RUC (json.pe) |
| Credenciales de Cloudinary | Imágenes de productos, banners, promociones y combos |

**Imágenes:** Cloudinary almacena los archivos y el sistema guarda `url` + `public_id`. Los productos admiten hasta 4 imágenes (JPG/JPEG/PNG/WEBP, máx. 2 MB); banners, promociones y combos usan una imagen principal. Al reemplazar o eliminar, se borra el asset en Cloudinary.

Si WhatsApp no está configurado, los botones no se renderizan. Si RENIEC/SUNAT fallan, la operación de venta/atención/cliente puede continuar ingresando los datos manualmente (la consulta es opcional).

---

# 6. Limitaciones funcionales conocidas

- El carrito **no genera una venta ni descuenta stock**: es una cotización.
- No hay pasarela de pago ni checkout.
- **Venta fraccionada**: los productos se venden por presentaciones globales asignadas (caja, blíster, tableta, ml, g) con precio, factor y paso configurable (por defecto 1); el stock se descuenta en unidad base.
- **Combos**: todavía usan la presentación base de cada producto; queda pendiente elegir presentación dentro del combo.
- **Código de barras**: la columna existe en `producto_presentacion`, pero no hay lector ni búsqueda por código.
- D2 eliminó la creación automática de `SIN-LOTE` y bloquea compras/ventas si el stock no coincide con los lotes. Los lotes heredados se listan con `php artisan inventario:sin-lote` y se regularizan con conteo físico, ajuste e inventario inicial. La columna legado `inventario` sigue oculta y sincronizada.
- Quedan pendientes los reportes de costo/margen por lote y la bandera de adopción gradual. El reporte de rentabilidad y el de lotes/costos ya están implementados.
- El buscador del catálogo solo ofrece sugerencias; no filtra la grilla por texto.
- Los reportes se generan síncronamente (DomPDF), sin cola.
- La moneda está fijada a **S/** y el formato de fechas a `d/m/Y`.
- El dashboard usa **stock vendible** (capas de lotes disponibles y no vencidos) como base de las alertas de stock y vencimiento. `inventario` es un campo legado y no participa. Los productos sin lotes (altas pendientes de inventario inicial) no generan alerta de agotado.
- **Login del Vendedor**: el sistema redirige a `/admin/dashboard`, ruta para la que ese rol no tiene permiso; requiere ajustar el redirect o el rol. El sidebar tampoco oculta todos los módulos no permitidos.

---

*Para el detalle técnico de cada módulo, consultar los archivos `modulo-*.md` de esta carpeta. La lógica vigente de lotes/FEFO está en [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md); los planes anteriores de lotes son históricos.*
