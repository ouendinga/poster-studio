# Poster Studio 🎨

**Poster Studio** es un potente plugin de WordPress diseñado para transformar tus entradas y páginas en carteles profesionales de alta calidad listos para imprimir. Con una interfaz de edición a pantalla completa y controles intuitivos, permite a los organizadores de eventos crear materiales promocionales impactantes en cuestión segundos.

---

## ✨ Características Principales

### 🖥️ Editor en Tiempo Real (WYSIWYG)
Olvídate de las conjeturas. Nuestra interfaz a pantalla completa te permite ver exactamente cómo quedará tu cartel mientras ajustas cada detalle.
- **Previsualización Instantánea**: Cambios inmediatos en texto, fechas y dimensiones.
- **Control de Zoom**: Ajusta la vista para trabajar con comodidad en cualquier monitor.

### 📐 Control Dimensional Avanzado
- **Mida del Paper**: Soporte nativo para formatos **A4** y **A3**.
- **Dimensiones Dinámicas**: Controla la altura y anchura de la imagen principal con sliders de precisión. Crea márgenes blancos elegantes manteniendo el tamaño de la hoja.
- **Encuadre Interactivo**: Arrastra la imagen dentro de su contenedor para lograr el encuadre perfecto sin necesidad de editar la foto original.

### 🔗 Integración Inteligente con WordPress
- **Contenido Automático**: Recupera automáticamente el título, fecha y metadatos (Lugar, Dificultad, Plazas) de tus noticias.
- **Código QR Robusto**: Generación automática de códigos QR que apuntan a la URL pública amigable del evento, reconstruida inteligentemente para evitar enlaces administrativos.
- **Identidad Visual**: Inserción automática del logo corporativo y detalles de contacto.

### 🖨️ Generación de PDF Profesional
- Basado en la librería **TCPDF**, garantiza que el archivo final sea una representación exacta de tu diseño con colores y tipografías consistentes.

---

## 🚀 Instalación y Requisitos

### Requisitos
- WordPress 5.2 o superior.
- PHP 7.4 o superior.
- **Composer** (para gestionar la librería TCPDF).

### Instalación Manual
1. Clona el repositorio en tu carpeta de plugins:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/ouendinga/poster-studio.git
   ```
2. Instala las dependencias:
   ```bash
   cd poster-studio
   composer install
   ```
3. Activa el plugin desde el panel de administración de WordPress.

---

## 🛠️ Desarrollo y Tecnologías

El plugin está construido siguiendo las mejores prácticas de desarrollo en WordPress:
- **Backend**: Arquitectura modular con Namespaces (`PosterStudio`).
- **Frontend**: Vanila JS para una manipulación del DOM fluida y CSS moderno para la interfaz del editor.
- **PDF Engine**: Integración con TCPDF para alta fidelidad de impresión.

---

## 📝 Uso
1. Ve a cualquier Entrada o Página en tu panel de WordPress.
2. En la barra lateral, encontrarás el metabox **Generate PDF**.
3. Haz clic en **Dissenyar Cartell**.
4. Ajusta los parámetros en la barra lateral derecha y haz clic en **Exportar a PDF**.

---

## 📄 Licencia
Este proyecto está bajo la licencia GPLv2 o posterior. 

---

Developed with ❤️ by **Álvaro Solís Pascual**
