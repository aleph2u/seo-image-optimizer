=== SEO Image Optimizer ===
Contributors: davidgimenez
Tags: seo, images, optimization, media, filename
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Optimiza automáticamente los nombres de archivo de las imágenes para mejorar el SEO.

== Description ==

SEO Image Optimizer optimiza automáticamente los nombres de archivo de las imágenes al subirlas a WordPress, mejorando significativamente tu posicionamiento en buscadores.

**Características principales:**

* Conversión automática a minúsculas
* Eliminación de acentos y caracteres especiales
* Reemplazo de espacios y guiones bajos por guiones medios
* Limitación de longitud a 60 caracteres
* Renombrado masivo de imágenes existentes
* Panel de administración intuitivo

**Transformaciones de ejemplo:**

* `Imagen_De_Producto (2024).jpg` → `imagen-de-producto-2024.jpg`
* `Membrana Hermética AMPACOLL.jpg` → `membrana-hermetica-ampacoll.jpg`
* `Fotografía-Ñoño_España.jpg` → `fotografia-nono-espana.jpg`

== Installation ==

1. Sube la carpeta `seo-image-optimizer` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Ve a Herramientas > SEO Image Optimizer para configurar
4. Las nuevas imágenes se optimizarán automáticamente al subirlas

== Frequently Asked Questions ==

= ¿El plugin afecta a las imágenes ya subidas? =

Por defecto solo optimiza nuevas imágenes. Puedes renombrar las existentes desde Herramientas > SEO Image Optimizer.

= ¿Es seguro renombrar imágenes existentes? =

El plugin actualiza tanto los archivos como las referencias en la base de datos. Se recomienda hacer backup antes del renombrado masivo.

= ¿Puedo deshacer los cambios? =

Los cambios son permanentes. Siempre haz una copia de seguridad antes del renombrado masivo.

== Changelog ==

= 1.1 =
* Nueva funcionalidad de compresión automática de imágenes
* Conversión a formato WebP (30-40% menos peso)
* Redimensionamiento automático de imágenes grandes
* Panel de configuración avanzado con controles deslizantes
* Optimización masiva de biblioteca de medios existente
* Detección automática de capacidades del servidor
* Mejor interfaz de usuario con estadísticas del sistema

= 1.0 =
* Versión inicial
* Optimización automática al subir
* Renombrado masivo de imágenes existentes
* Panel de administración

== Upgrade Notice ==

= 1.0 =
Primera versión del plugin.