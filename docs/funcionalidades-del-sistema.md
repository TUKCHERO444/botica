# Funcionalidades del Sistema — Botica

## Visión General

El sistema es una plataforma de gestión farmacéutica que combina un sitio web público para mostrar productos al cliente y un panel administrativo completo para gestionar el inventario, las ventas, las atenciones y el contenido de la página principal.

---

## 1. Sitio Web Público

### Página de Inicio (`/`)
La puerta de entrada del negocio. Muestra:

- **Carrusel de banners**: Imágenes promocionales rotativas que pueden activarse, desactivarse y reordenarse desde el panel administrativo.
- **Categorías destacadas**: Píldoras con las categorías seleccionadas para aparecer en portada. Al hacer clic, se filtra el catálogo por esa categoría.
- **Sliders de productos**: Tres carruseles horizontales deslizables:
  - **Más buscados**
  - **Más vendidos**
  - **En descuento**
  Cada slider se administra desde el panel, asignando productos y definiendo su orden de aparición.
- **Bloques promocionales**: Espacios de imagen promocional estática.

### Catálogo de Productos (`/productos`)
Escaparate virtual completo:

- Cuadrícula de productos responsive (2 a 5 columnas según la pantalla).
- **Filtros por categoría**: Sidebar en escritorio, panel deslizante en móvil. Muestra el conteo de productos por categoría.
- **Búsqueda por nombre**: Filtro en tiempo real mientras el usuario escribe.
- **Filtro por precio máximo**: Deslizador para acotar resultados.
- **Filtros vía AJAX**: La cuadrícula se actualiza sin recargar la página. La URL guarda los filtros activos.
- **Paginación**: 20 productos por página.

### Página de Detalle de Producto (`/productos/{id}`)
Vista detallada de cada producto:

- **Galería de imágenes**: Hasta 4 fotos con miniaturas seleccionables y rotación automática.
- **Migas de pan**: Navegación Inicio > Productos > Categoría > Producto.
- **Precio y stock**: Muestra el precio de venta y la disponibilidad (en stock o agotado).
- **Botón de WhatsApp**: Enlace directo a WhatsApp con un mensaje predefinido preguntando por el producto. Se activa configurando el número en el archivo de entorno.
- **Productos relacionados**: Hasta 8 productos aleatorios de la misma categoría.

### Búsqueda Global en la Barra de Navegación
Al escribir en el buscador del menú superior, aparecen sugerencias en vivo con hasta 10 productos (nombre, precio, categoría, imagen).

### Autenticación
- Página de inicio de sesión para acceder al panel administrativo.
- Solo los usuarios activos pueden ingresar.

---

## 2. Panel Administrativo

El panel funciona como una aplicación de una sola página (SPA): la estructura se carga una vez y la navegación entre módulos ocurre vía AJAX, sin recargar la página completa. El historial del navegador y los botones de retroceso/avance funcionan correctamente.

### Mecánica General
- Los formularios de crear y editar se abren en ventanas modales cargadas vía AJAX.
- Al enviar un formulario, la validación muestra errores en el mismo modal.
- Al completar una operación, el modal se cierra y el listado se actualiza automáticamente.
- Notificaciones flotantes (toasts) informan del resultado de cada operación.
- Toda funcionalidad está protegida por permisos asignados a roles.

---

### 2.1. Dashboard (`/admin/dashboard`)
**Panel de control** con un resumen visual del negocio:

- **Tarjetas de resumen**: Ingresos del día, cantidad de ventas del día, cantidad de atenciones del día, productos con stock bajo (5 o menos).
- **Gráfico de ingresos semanales**: Barras o líneas que comparan ingresos por ventas y atenciones en los últimos 7 días.
- **Distribución mensual**: Gráfico circular que muestra la proporción de ingresos entre ventas y atenciones en el mes actual.
- **Top 5 productos más vendidos del mes**: Ranking con barras de porcentaje.
- **Productos con stock crítico**: Lista de productos cuyo stock está por debajo del 20% del inventario inicial.

---

### 2.2. Módulos de Inventario

#### Productos (`/admin/productos`)
**Gestión completa del catálogo de productos**:

- Listado de todos los productos con foto miniatura, nombre, categoría, marca, precio, stock y estado.
- **Crear producto**: Formulario con nombre, descripción, categoría, marca, precio de compra, precio de venta, stock, inventario, estado activo y **subida de múltiples imágenes** (hasta 4 fotos subidas a Cloudinary, reordenables con arrastrar y soltar).
- **Editar producto**: Misma información precargada. Las imágenes existentes se muestran y pueden reordenarse o eliminarse.
- **Activar/Desactivar**: Cambio rápido de estado.
- **Eliminar**: Desactivación suave (el producto se oculta pero no se borra).

#### Categorías (`/admin/categorias`)
Clasificación de productos:

- Listado de categorías con nombre, descripción, cantidad de productos asociados y estado.
- Crear, editar, activar/desactivar y eliminar categorías.

#### Marcas (`/admin/marcas`)
Fabricantes o marcas de productos:

- Listado de marcas con nombre, descripción, cantidad de productos y estado.
- Crear, editar, activar/desactivar y eliminar marcas.

#### Proveedores (`/admin/proveedores`)
Empresas que abastecen el inventario:

- Listado con RUC, razón social, condición y estado.
- Crear, editar, activar/desactivar y eliminar proveedores.

#### Ingresos de Inventario (`/admin/ingresos`)
**Registro de compras y entrada de stock**:

- Listado correlativo de ingresos con proveedor, fecha y usuario que registró.
- **Crear ingreso**: Seleccionar proveedor, fecha y agregar múltiples productos con cantidades. El sistema genera automáticamente un número correlativo (ING-XXXXXXXX) y actualiza el stock de cada producto.
- **Ver detalle**: Muestra los productos recibidos con sus cantidades.
- **Editar**: Permite modificar proveedor, fecha y cantidades. El stock se recalcula.
- **Eliminar**: Revierte el ingreso y restaura el stock anterior.

#### Servicios (`/admin/servicios`)
Servicios facturables que ofrece la farmacia (toma de presión, vacunación, etc.):

- Listado con nombre, descripción y precio.
- Crear, editar y eliminar servicios.

---

### 2.3. Módulo de Clientes

#### Clientes (`/admin/clientes`)
**Gestión de la base de clientes**:

- Listado con DNI y nombre completo.
- Crear y editar clientes.
- **Cliente genérico**: Botón para crear instantáneamente un cliente "SIN IDENTIFICAR" para ventas rápidas sin registro.
- **Búsqueda por DNI o nombre**: Utilizada al crear ventas o atenciones.

---

### 2.4. Módulos de Operaciones

#### Ventas (`/admin/ventas`)
**Registro de transacciones de venta**:

- Listado de todas las ventas con número correlativo, cliente, usuario que registró, total y fecha.
- **Crear venta**: Requiere una caja abierta. Seleccionar cliente (búsqueda o cliente genérico), agregar productos con cantidades y precios. El sistema calcula el total automáticamente, descuenta del stock y registra el movimiento en la caja.
- **Ver detalle**: Información completa del cliente, productos vendidos y total.
- **Editar**: Modificar productos y cantidades. El stock y la caja se ajustan automáticamente.
- **Eliminar**: Restaura el stock, revierte el movimiento de caja y elimina el registro.

#### Atenciones (`/admin/atenciones`)
**Registro de consultas o servicios prestados**:

- Similar a ventas, pero permite agregar tanto productos como servicios facturables.
- Incluye un campo de observaciones.
- Requiere caja abierta. Descuenta stock de productos y registra el ingreso en caja.
- Crear, ver detalle, editar y eliminar atenciones.

---

### 2.5. Módulo Financiero

#### Caja (`/admin/caja`)
**Control del efectivo diario**:

- **Apertura de caja**: Iniciar un turno con un monto inicial obligatorio. Solo puede haber una caja abierta a la vez.
- **Resumen de caja abierta**: Muestra el saldo actual (monto inicial + ingresos - egresos).
- **Registrar egreso manual**: Ingresar un gasto con monto y descripción. Se actualiza el total de egresos.
- **Cierre de caja**: Finaliza el turno, calcula el monto final (inicial + ingresos - egresos) y marca la caja como cerrada (inmutable).
- **Registro automático de ingresos**: Cada venta o atención registrada se refleja automáticamente como ingreso en la caja abierta.
- **Historial**: Listado de cajas cerradas con resumen de movimientos.
- **Detalle de caja cerrada**: Vista inmutable con todos los movimientos (ingresos por ventas/atenciones, egresos manuales), totales y quién abrió/cerró.

---

### 2.6. Módulos de Administración de Usuarios

#### Usuarios (`/admin/usuarios`)
**Gestión de usuarios del sistema**:

- Listado con nombre, email, rol asignado y estado (activo/inactivo).
- Crear y editar usuarios con asignación de rol.
- Al editar, la contraseña es opcional (solo se actualiza si se escribe una nueva).
- **Auto-protección**: Un usuario no puede cambiarse su propio rol ni desactivarse a sí mismo.
- Activar/Desactivar usuarios.

#### Roles (`/admin/roles`)
**Gestión de roles y permisos**:

- Listado de roles con los permisos asignados.
- Crear rol: Nombre y selección de módulos a los que tendrá acceso.
- Editar rol: Modificar nombre y permisos. El rol "Administrador" no puede editarse ni eliminarse.
- **Permisos disponibles**: Acceso completo a cada módulo (dashboard, productos, categorías, marcas, proveedores, clientes, ventas, servicios, atenciones, ingresos, usuarios, roles, caja, sliders, banners, categorías en portada).
- **Roles por defecto**:
  - **Administrador**: Acceso a todos los módulos.
  - **Vendedor**: Acceso solo a ventas y atenciones.

---

### 2.7. Módulos de Gestión de Portada

#### Sliders (`/admin/sliders`)
**Productos destacados en la página principal**:

- Tres pestañas para gestionar los sliders "Más buscados", "Más vendidos" y "En descuento".
- Asignar productos a cada slider mediante búsqueda con autocompletado.
- Quitar productos individualmente.
- Reordenar productos dentro de cada slider.

#### Banners (`/admin/banners`)
**Carrusel de imágenes de la portada**:

- Listado ordenable de banners con imagen en miniatura, título, subtítulo, enlace opcional y estado.
- Crear banner: Subir imagen (almacenada en Cloudinary), título, subtítulo y enlace.
- Editar: Modificar cualquier campo o reemplazar la imagen.
- Activar/Desactivar banners sin eliminarlos.
- Reordenar mediante entrada numérica con guardado automático.

#### Categorías en Portada (`/admin/portada-categorias`)
**Selección de categorías visibles en la página principal**:

- Cuadrícula con todas las categorías activas y un interruptor para incluirlas o no en la portada.
- Orden de aparición configurable.
- Activación y desactivación con un clic.

---

## 3. Roles y Permisos

El sistema maneja dos roles predefinidos:

| Rol | Acceso |
|---|---|
| **Administrador** | Todos los módulos del panel administrativo |
| **Vendedor** | Ventas y Atenciones (operaciones del día a día) |

Cada rol tiene acceso completo a los módulos que se le asignan. Si un rol tiene acceso a "Productos", puede ver, crear, editar y eliminar productos dentro de ese módulo.

---

## 4. Datos de Demostración

Al iniciar el sistema por primera vez, se crean automáticamente:

- **2 usuarios de prueba**:
  - `admin@sistema.com` / `admin123` (Administrador)
  - `vendedor@sistema.com` / `vendedor123` (Vendedor)
- 6 categorías, 6 marcas, 4 proveedores, 6 clientes
- 10 productos con imágenes, precios y niveles de stock variados
- 4 servicios facturables
- 3 órdenes de compra con productos
- 4 ventas y 2 atenciones con registros históricos

---

## 5. Tecnología de Imágenes

Las imágenes de productos y banners se almacenan en **Cloudinary**, un servicio en la nube especializado en imágenes. Esto evita que el servidor local almacene archivos pesados y permite cargar las imágenes de forma óptima según el dispositivo del visitante.
