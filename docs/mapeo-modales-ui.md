# Mapeo de modales y anchos

> **Fecha:** 20/11/2026
> **Tipo de cambio:** solo visual (ancho del modal). No modifica campos, validaciones ni lógica.

---

## 1. Escala de tamaños

| Tamaño | Clase | Ancho aproximado | Uso |
|---|---|---:|---|
| `sm` | `max-w-lg` | 32 rem | Confirmaciones y formularios de 1–2 campos |
| `md` | `max-w-2xl` | 42 rem | Formularios simples de 2–4 campos |
| `lg` | `max-w-3xl` | 48 rem | Formularios medianos o con checklist |
| `xl` | `max-w-4xl` | 56 rem | Tamaño por defecto |
| `2xl` | `max-w-5xl` | 64 rem | Formularios con listas o imágenes |
| `3xl` | `max-w-6xl` | 72 rem | Formularios con tablas/líneas |
| `4xl` | `max-w-7xl` | 80 rem | Inventario inicial y tablas anchas |

El tamaño se declara en la raíz del modal:

```blade
<form data-modal-size="3xl" ...>
<div data-modal-size="4xl" ...>
```

`resources/js/admin/modal.js` aplica la clase al abrir y restaura `xl` al cerrar.
Los módulos que inyectan modales manualmente llaman a:

```js
window.AdminModal?.applySizeFromContent();
```

---

## 2. Mapeo por módulo

| Módulo | Formulario/modal | Tamaño |
|---|---|---|
| Ajustes | Crear ajuste | `3xl` |
| Ajustes | Ver detalle | `2xl` |
| Atenciones | Crear | `3xl` |
| Atenciones | Editar | `3xl` |
| Atenciones | Ver detalle | `2xl` |
| Auditoría | Ver detalle | `lg` |
| Banners | Crear/editar | `md` |
| Caja | Apertura | `sm` |
| Caja | Egreso | `sm` |
| Categorías | Crear/editar | `md` |
| Clientes | Crear/editar | `lg` |
| Combos | Crear/editar | `2xl` |
| Conciliaciones | Ver detalle | `3xl` |
| Devoluciones | Anular | `sm` |
| Devoluciones | Crear | `3xl` |
| Devoluciones | Ver detalle | `2xl` |
| Header textos | Crear/editar | `md` |
| Compras | Anular | `sm` |
| Compras | Crear/editar | `3xl` |
| Compras | Recibir | `lg` |
| Compras | Ver detalle | `2xl` |
| Inventario inicial | Crear | `4xl` |
| Inventario inicial | Editar | `4xl` |
| Inventario inicial | Ver detalle | `4xl` |
| Kardex | Ver kardex por producto | `3xl` |
| Marcas | Crear/editar | `md` |
| Presentaciones | Crear/editar | `md` |
| Productos | Crear/editar | `3xl` |
| Promociones | Crear/editar | `2xl` |
| Proveedores | Crear/editar | `lg` |
| Roles | Crear/editar | `lg` |
| Servicios | Crear/editar | `md` |
| Unidades de medida | Crear/editar | `md` |
| Usuarios | Crear/editar | `lg` |
| Ventas | Anular | `sm` |
| Ventas | Crear | `3xl` |
| Ventas | Ver detalle | `2xl` |

---

## 3. Criterios

- Formularios con muchos campos se amplían a los lados, no se hacen más altos.
- Formularios pequeños no se estiran innecesariamente: usan `sm`/`md`.
- Tablas de líneas o lotes usan `3xl`.
- Inventario inicial conserva el ancho que ya funcionaba: `4xl`.
- Los modales de confirmación no crecen.
- Los cambios son solo de ancho y no alteran el envío, validación ni estructura de datos.
