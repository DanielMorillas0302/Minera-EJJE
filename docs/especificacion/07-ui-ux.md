# 07 – Lineamientos UI/UX

## Principios
Claro, sobrio y rápido de usar por personal administrativo. Menos clics, formularios cortos, mensajes en español.

## Layout
- Barra lateral colapsable con módulos según rol; barra superior con usuario, sede y cerrar sesión.
- Breadcrumbs en todas las páginas internas.
- Contenido en tarjetas; tablas con encabezado fijo.

## Estilo
- Bootstrap 5, tipografía Inter (con fallback system-ui).
- Paleta: primario azul petróleo `#0F4C5C`, acento ámbar `#E09F3E`, éxito `#2E7D32`, peligro `#C62828`, fondo `#F5F7F8`.
- Soporte de modo claro (oscuro opcional).

## Componentes
- **Listados**: buscador, filtros en panel, paginación (20 por fila), botones "Exportar Excel", "Nuevo".
- **Formularios**: etiquetas claras, validación en línea (HTMX), errores bajo cada campo, botones Guardar / Cancelar.
- **Estados**: badges de color (Pendiente=ámbar, Aprobado=verde, Rechazado=rojo, En tránsito=azul).
- **Confirmaciones**: modal para aprobar, rechazar, anular.
- **Toasts** para éxito/error; páginas 403/404/500 personalizadas.
- **Detalle de trabajador**: cabecera con foto, badge de aptitud y pestañas.
- **Dashboard**: tarjetas KPI arriba, 4 gráficos Chart.js debajo, filtros arriba a la derecha.

## Accesibilidad y responsive
Contraste AA, foco visible, tablas con scroll horizontal en móvil, botones ≥ 40 px.

## Textos
Fechas `dd/mm/aaaa`, moneda `S/ 1,234.50`, DNI 8 dígitos, RUC 11 dígitos.
