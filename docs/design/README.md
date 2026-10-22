# Handoff: bambudev — portal de apps

## Overview

bambudev agrupa varias apps de uso diario detrás de una sola web. Hay un menú de solapas arriba (Inicio, Herramientas, Acerca de, Ayuda) y un botón **Admin**. El **Inicio** funciona como el launcher de un sistema operativo: ocupa el 100% del viewport, **sin scroll vertical ni horizontal**, y muestra las apps como iconos paginados. Las demás solapas son páginas normales con scroll.

## About the Design Files

`bambudev.dc.html` es una **referencia de diseño hecha en HTML** (un prototipo que muestra el aspecto y el comportamiento esperados). **No es código de producción para copiar.** La tarea es recrear este diseño en el entorno del proyecto destino, con sus patrones y librerías. Si todavía no hay proyecto, elegir un framework adecuado (se sugiere React + Vite o Next.js, más un backend o BaaS para el área admin).
Para verlo, abrir `bambudev.dc.html` en un navegador servido por HTTP local (p. ej. `npx serve .`), junto a `support.js`, `styles.css` y `assets/`. El template (marcado con `{{ }}`) y la lógica (`class Component`, con `renderVals()`) están dentro del mismo archivo: son la fuente de verdad para medidas, textos y comportamiento.

## Fidelity

**High-fidelity.** Los colores, la tipografía, los espacios, los textos y las interacciones son finales. Recrearlo pixel-perfect.

**Configuración elegida:** layout `launcher` (grilla centrada), tema claro, acento `bambu` (verde), fondo de Inicio **Bosque** por defecto (verde oscuro con franjas verticales tipo caña de bambú). El prototipo también incluye los layouts `escritorio` y `portada`, el modo oscuro y los fondos Papel, Bambú y Trama, todos opcionales.

## Screens / Views

### Header (global, 68px de alto)

- Flex row, padding `0 32px`, gap 36px, `border-bottom: 1px solid var(--color-divider)`, fondo `--color-bg`.
- **Marca:** logo "b" de 38×38 (radio 4px, fondo blanco, imagen recortada con `object-fit:cover`) + wordmark de texto "bambu**dev**": Source Serif 4, 600, 21px, letter-spacing -0.02em, con "dev" en `--color-accent`. Al hacer click lleva a Inicio.
- **Solapas:** botones de 100% de alto, padding `0 16px`, Source Serif 4 600 15px. Cada uno lleva un número "01…04" (11px, tracking 0.08em, color muted) antes del label. Solapa activa: texto `--color-text` y `border-bottom 3px solid --color-accent`. Inactiva: texto `--color-muted`.
- **Derecha:** botón de icono para cambiar el tema (luna/sol) + botón "Admin" con icono de engranaje (36px de alto, borde de divider, radio 2px). Cuando está activo, el fondo es `--color-text` y el texto `--color-bg`.

### Inicio (launcher, sin scroll)

- Ocupa `height: 100vh - header` y lleva `overflow:hidden`. El fondo es el wallpaper elegido.
- **Wallpaper Bosque:** `repeating-linear-gradient(90deg, transparent 0 58px, rgba(140,200,120,.07) 58px 66px, transparent 66px 72px), #10291c`. Texto `#eef2ec`, texto muted `#a9c0b0`. Sobre fondos oscuros, los tiles de los iconos usan su variante oscura.
- **Columna central** (flex column, centrada en ambos ejes, gap `clamp(8px,2.2vh,30px)`):
    1. **Reloj** HH:MM en 24h: Source Serif 600, `clamp(40px,8.5vh,104px)`, letter-spacing -0.04em, cifras tabulares. Se actualiza cada 15 s.
    2. **Fecha:** "Martes, 21 de octubre", 16px, color muted.
    3. **Saludo** en itálica, `clamp(17px,2.6vh,22px)`: "Buen día / Buenas tardes / Buenas noches, {nombre}." (antes de las 12, antes de las 20, después de las 20), seguido de "¿Qué resolvemos hoy?" en muted.
    4. **Buscador:** ancho `min(480px,100%)`, 48px de alto, fondo `--color-surface`, borde de divider, radio 4px, `--shadow-sm`. Icono de lupa en el color de acento. Placeholder: "Busca una app… prueba con «pelis»". A la derecha, un badge con la tecla "/".
    5. **Grilla de apps:** ancho `min(760px,100%)`, 6 columnas × 2 filas = **12 apps por página**. Cada item es un tile (`clamp(54px,10vh,84px)`, radio 4px, icono al 50%) con el nombre debajo (14px, centrado). En hover, `translateY(-4px)` con transición de .18s.
    6. **Puntos de paginación:** solo aparecen si hay más de una página. Punto de 8×8 con opacidad 0.3; el activo mide 22×8 y usa el color de acento. Transición de .25s.
- **Barra inferior** (padding `12px 32px 20px`, space-between):
    - Izquierda: "RECIENTES" (11px, uppercase, tracking 0.1em) + hasta 4 chips de apps recientes (tile de 28px + nombre, borde de divider, fondo `currentColor` al 5%).
    - Derecha: "FONDO" + 4 swatches de 24×24 (Papel, Bambú, Trama, Bosque). El seleccionado lleva un anillo de acento: `0 0 0 2px bg, 0 0 0 4px accent`.

### Herramientas (con scroll)

- Contenedor de max 1180px, padding `56px 40px 96px`.
- Kicker "CATÁLOGO", H1 "Herramientas" de 56px y el texto "{n} apps (y contando) para que el día a día pese un poco menos. Filtra, curiosea, abre." A la derecha, un input "Filtrar herramientas" de 340px.
- **Chips de categoría:** "Todas" + las categorías existentes, cada una con su contador. El chip activo tiene fondo de acento.
- **Grilla** `repeat(auto-fill, minmax(250px,1fr))`, gap 20px. Cada card (fondo surface, radio 4px, padding 22px) muestra: tile de 52px, tag "Reciente" si aplica, categoría (kicker de 10px), nombre (Serif 600 21px), descripción (14px, muted), botón primario "Abrir ↗" y el texto "En tu Inicio". En hover, `--shadow-md` + `translateY(-2px)`.
- Estado vacío: "Ninguna herramienta coincide. ¿Y si la inventamos? Sugiérela." (link a Ayuda).

### Acerca de (con scroll)

- Hero en grid 7fr/4fr: el H1 "Apps pequeñas para que los días grandes salgan bien." (`clamp(40px,5.4vw,72px)`) más un párrafo de 20px. Al lado, el wordmark sobre un cuadrado blanco.
- Tres columnas numeradas 01/02/03: "Una sola puerta", "Sin manual de instrucciones", "Crece contigo".
- Cita en itálica y botón primario "¿Tienes una idea? Cuéntanosla →" que lleva a Ayuda.

### Ayuda (con scroll)

- Grid de `1fr / 380px`.
- **Izquierda:** H1 "¿Te trabaste?", acordeón de FAQ con 5 preguntas (solo una abierta a la vez, icono +/−) y una tabla de atajos (`/`, `← →`, `Esc`).
- **Derecha:** formulario sticky "Escríbenos" (email, app en un select, mensaje). Al enviar se muestra: "¡Recibido! Te respondemos antes de que se enfríe el café."

### Admin (con scroll)

- Tag "Área administrativa", H1 "Tus apps". El orden de la lista define el orden de los iconos en el Inicio.
- **Tabla:** columnas Orden (flechas ↑/↓), App (tile de 40px + nombre + descripción truncada), Categoría (tag), En Inicio (toggle de 40×22 que oculta o muestra la app) y Eliminar.
- **Panel "Nueva app"** (sticky, 380px): vista previa en vivo del tile, Nombre, Descripción corta, Categoría (select), Icono (picker de 16 iconos Phosphor) y Color (8 tonos). El botón "Publicar en el Inicio" está deshabilitado si el nombre está vacío. Al publicar aparece el mensaje "¡Listo! «X» ya está en el Inicio." durante 3.5 s.

### Modal "abrir app"

- Al hacer click en cualquier app se abre un overlay `rgba(18,20,18,.5)` con una card de 440px: tile de 64px, categoría, nombre de 26px, descripción y los botones "Ahora no" y "Abrir {app}". Se cierra con Esc o con un click fuera.
- En producción, este modal se reemplaza por la navegación real a la app (ruta o URL externa).

## Interactions & Behavior

- **Paginación del Inicio:** una tira horizontal de páginas que se mueve con `translateX(-page*100%)`, transición `.45s cubic-bezier(.2,.8,.2,1)`. Se avanza con un swipe o drag del puntero de más de 50px, con las flechas ← → del teclado (cuando no se está escribiendo) o con click en los puntos.
- **Búsqueda:** `/` enfoca el buscador. Filtra sin distinguir acentos ni mayúsculas, sobre nombre, categoría, tags y descripción, y vuelve a la página 0. Enter abre el primer resultado. Esc limpia el buscador.
- **Recientes:** al abrir una app, esta pasa al frente de la lista (máximo 4).
- **Tema:** el botón del header alterna entre claro y oscuro.
- **Admin:** los cambios se reflejan al instante en el Inicio y en Herramientas.

## State Management

- `apps[]`: `{ id, name, cat, icon, hue, tags, desc, visible, order }`. Debe persistirse en un backend; el admin hace CRUD sobre esta lista.
- Preferencias por usuario (localStorage o perfil): `wallpaper` (default `bosque`), `theme`, `recent[]`, nombre del usuario para el saludo.
- UI: `tab` (idealmente una ruta: `/`, `/herramientas`, `/acerca`, `/ayuda`, `/admin`), `query`, `page`, `openId`, `cat`, `toolQuery`.
- Las apps por página dependen del layout: launcher 12, escritorio 16, portada 12.

## Design Tokens

Están en `styles.css` (sistema "Broadsheet"), con estos overrides de marca:

- **Tipografía:** Source Serif 4 en todo (headings y body), con itálica real. No usar sans-serif.
- **Claro:** bg `#f3f2f2`, surface `#eae9e9`, text `#201e1d`, muted `#5f5b5a`, divider `rgba(32,30,29,.16)`.
- **Oscuro:** bg `#161716`, surface `#222423`, text `#ecebea`, muted `#a6a3a1`.
- **Acento bambú** (reemplaza al cian del sistema):
    - Claro: accent `#3b8a2e`, 600 `#2f7526`, 700 `#245d1d`, 100 `#e6f4e0`.
    - Oscuro: accent `#6cbf55`, 600 `#84cf6d`, 700 `#a5e08f`.
- **Acento secundario** (solo en el tag de Admin y en eliminar): `--color-accent-2` `#d6006c`.
- **Tiles de apps:** cada app tiene un `hue`.
    - Claro: fondo `oklch(0.92 0.055 hue)`, icono `oklch(0.44 0.13 hue)`.
    - Oscuro: fondo `oklch(0.34 0.07 hue)`, icono `oklch(0.86 0.11 hue)`.
- **Radios:** tiles y cards 4px; botones, chips y swatches 2px.
- **Sombras:** `--shadow-sm/md/lg` de `styles.css`.

## Assets

- `assets/bambudev-b.jpg`: logo "b" (sube el usuario).
- `assets/bambudev-wordmark.png`: wordmark completo.
- Iconos: **Phosphor Icons, peso duotone** (`@phosphor-icons/web` o `@phosphor-icons/react`).

| App           | Categoría    | Icono          | Hue |
| ------------- | ------------ | -------------- | --- |
| MiBilletera   | Finanzas     | wallet         | 150 |
| MiSalud       | Salud        | heartbeat      | 20  |
| AlquilerFácil | Hogar        | house-line     | 60  |
| Huella        | Mascotas     | paw-print      | 85  |
| Movieboxd     | Ocio         | film-slate     | 330 |
| Escríbelo     | Escritura    | pen-nib        | 275 |
| Docbrainer    | Documentos   | brain          | 235 |
| AgendaFlex    | Organización | calendar-check | 195 |

## Files

- `bambudev.dc.html`: prototipo completo (template + lógica).
- `styles.css`: tokens y clases del sistema (`.btn`, `.tag`, `.field`, `.input`, `.table`).
- `support.js`: runtime para abrir el prototipo en el navegador (no forma parte de la implementación).
- `assets/`: logos.
- `screenshots/`: capturas de referencia (~925px de ancho): 01-inicio (fondo Bosque), 02-herramientas, 03-acerca-de, 04-ayuda, 05-admin, 06-modal-app. Ante cualquier duda, manda `bambudev.dc.html`.
