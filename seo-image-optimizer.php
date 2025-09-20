<?php
/**
 * Plugin Name: SEO Image Optimizer Pro
 * Description: Optimiza automáticamente nombres, compresión y formato de imágenes para SEO
 * Version: 1.2
 * Author: David Gimenez
 * Author URI: https://kreamedia.com
 * Plugin URI: https://github.com/aleph2u/seo-image-optimizer
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Sistema de actualización automática desde GitHub
require_once plugin_dir_path(__FILE__) . 'updater.php';
new SEO_Image_Optimizer_Updater(__FILE__);

// Definir constantes del plugin
define('SEO_IMG_VERSION', '1.2');
define('SEO_IMG_PATH', plugin_dir_path(__FILE__));
define('SEO_IMG_URL', plugin_dir_url(__FILE__));

/**
 * Activación del plugin - crear opciones por defecto
 */
function seo_img_activate() {
    $default_options = array(
        'compression_enabled' => true,
        'jpeg_quality' => 85,
        'png_compression' => 6,
        'webp_enabled' => false,
        'webp_quality' => 80,
        'max_width' => 2000,
        'max_height' => 2000,
        'auto_resize' => true,
        'preserve_metadata' => false,
        'backup_originals' => false
    );

    add_option('seo_img_options', $default_options);
}
register_activation_hook(__FILE__, 'seo_img_activate');

/**
 * Obtener opciones del plugin
 */
function seo_img_get_options() {
    return get_option('seo_img_options', array());
}

/**
 * Sanitiza nombres de archivos al subir imágenes
 */
function seo_sanitize_image_filename($filename) {
    // Separar nombre y extensión
    $info = pathinfo($filename);
    $ext = empty($info['extension']) ? '' : '.' . $info['extension'];
    $name = basename($filename, $ext);

    // Convertir a minúsculas
    $name = mb_strtolower($name, 'UTF-8');

    // Reemplazar caracteres especiales españoles
    $special_chars = array(
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
        'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Å' => 'a',
        'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e',
        'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i',
        'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o', 'Õ' => 'o',
        'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u', 'Û' => 'u',
        'Ñ' => 'n', 'Ç' => 'c'
    );

    $name = strtr($name, $special_chars);

    // Reemplazar espacios y guiones bajos por guiones medios
    $name = str_replace(array(' ', '_'), '-', $name);

    // Eliminar caracteres no alfanuméricos excepto guiones
    $name = preg_replace('/[^a-z0-9\-]/', '', $name);

    // Eliminar guiones múltiples
    $name = preg_replace('/-+/', '-', $name);

    // Eliminar guiones al inicio y final
    $name = trim($name, '-');

    // Si el nombre queda vacío, usar un nombre por defecto
    if (empty($name)) {
        $name = 'imagen-' . time();
    }

    // Limitar longitud (60 caracteres es un buen límite)
    if (strlen($name) > 60) {
        $name = substr($name, 0, 60);
        $name = rtrim($name, '-');
    }

    return $name . $ext;
}

// Aplicar filtro al subir archivos SOLO para imágenes
function seo_should_process_filename($filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    return in_array(strtolower($extension), $image_extensions);
}

function seo_sanitize_filename_wrapper($filename) {
    // Solo procesar si es una imagen
    if (seo_should_process_filename($filename)) {
        return seo_sanitize_image_filename($filename);
    }
    return $filename;
}
add_filter('sanitize_file_name', 'seo_sanitize_filename_wrapper', 10);

/**
 * Optimizar y comprimir imagen al subirla
 */
function seo_optimize_uploaded_image($file) {
    // CRITICAL FIX: Solo procesar cuando es una subida de medios, NO plugins/temas
    // Verificar que estamos en el contexto correcto
    if (isset($_POST['action']) && in_array($_POST['action'], ['upload-theme', 'upload-plugin'])) {
        return $file; // No procesar archivos de temas o plugins
    }

    // Verificar que es realmente una imagen por su extensión
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($file_extension), $image_extensions)) {
        return $file;
    }

    $options = seo_img_get_options();

    // Solo procesar imágenes
    if (!in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
        return $file;
    }

    // Si la compresión está deshabilitada, retornar
    if (!$options['compression_enabled']) {
        return $file;
    }

    $image_path = $file['tmp_name'];

    // Obtener dimensiones de la imagen
    list($width, $height, $type) = getimagesize($image_path);

    // Verificar si necesita redimensionarse
    $max_width = $options['max_width'];
    $max_height = $options['max_height'];
    $needs_resize = false;

    if ($options['auto_resize'] && ($width > $max_width || $height > $max_height)) {
        $needs_resize = true;

        // Calcular nuevas dimensiones manteniendo proporción
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    // Procesar según tipo de imagen
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($image_path);

            if ($needs_resize) {
                $resized = imagecreatetruecolor($new_width, $new_height);
                imagecopyresampled($resized, $image, 0, 0, 0, 0,
                                  $new_width, $new_height, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            // Aplicar compresión
            imagejpeg($image, $image_path, $options['jpeg_quality']);

            // Convertir a WebP si está habilitado
            if ($options['webp_enabled'] && function_exists('imagewebp')) {
                $webp_path = str_replace('.jpg', '.webp', $image_path);
                $webp_path = str_replace('.jpeg', '.webp', $webp_path);
                imagewebp($image, $webp_path, $options['webp_quality']);
            }

            imagedestroy($image);
            break;

        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($image_path);

            if ($needs_resize) {
                $resized = imagecreatetruecolor($new_width, $new_height);

                // Preservar transparencia
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);

                imagecopyresampled($resized, $image, 0, 0, 0, 0,
                                  $new_width, $new_height, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            // Preservar transparencia
            imagealphablending($image, false);
            imagesavealpha($image, true);

            // Aplicar compresión
            imagepng($image, $image_path, $options['png_compression']);

            // Convertir a WebP si está habilitado
            if ($options['webp_enabled'] && function_exists('imagewebp')) {
                $webp_path = str_replace('.png', '.webp', $image_path);
                imagewebp($image, $webp_path, $options['webp_quality']);
            }

            imagedestroy($image);
            break;
    }

    // Actualizar tamaño del archivo
    $file['size'] = filesize($image_path);

    return $file;
}
add_filter('wp_handle_upload_prefilter', 'seo_optimize_uploaded_image');

/**
 * Generar versión WebP para imágenes existentes
 */
function seo_generate_webp_on_upload($metadata, $attachment_id) {
    $options = seo_img_get_options();

    if (!$options['webp_enabled'] || !function_exists('imagewebp')) {
        return $metadata;
    }

    $file = get_attached_file($attachment_id);
    $type = wp_check_filetype($file);

    if (!in_array($type['type'], ['image/jpeg', 'image/jpg', 'image/png'])) {
        return $metadata;
    }

    // Crear versión WebP del archivo principal
    $webp_file = str_replace('.' . $type['ext'], '.webp', $file);

    switch ($type['type']) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($file);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file);
            break;
    }

    if ($image) {
        imagewebp($image, $webp_file, $options['webp_quality']);
        imagedestroy($image);

        // Agregar información WebP a metadata
        $metadata['webp'] = basename($webp_file);
    }

    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'seo_generate_webp_on_upload', 10, 2);

/**
 * Servir imágenes WebP cuando sea posible
 */
function seo_serve_webp_images($image_url, $attachment_id, $size) {
    $options = seo_img_get_options();

    if (!$options['webp_enabled']) {
        return $image_url;
    }

    // Verificar si el navegador soporta WebP
    if (!isset($_SERVER['HTTP_ACCEPT']) || strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') === false) {
        return $image_url;
    }

    // Intentar obtener la versión WebP
    $webp_url = str_replace(['.jpg', '.jpeg', '.png'], '.webp', $image_url);

    // Verificar si el archivo WebP existe
    $upload_dir = wp_upload_dir();
    $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);

    if (file_exists($webp_path)) {
        return $webp_url;
    }

    return $image_url;
}
add_filter('wp_get_attachment_image_src', 'seo_serve_webp_images', 10, 3);

/**
 * Añadir página de herramientas en el admin
 */
function seo_add_admin_menu() {
    add_management_page(
        'SEO Image Optimizer Pro',
        'SEO Image Optimizer',
        'manage_options',
        'seo-image-optimizer',
        'seo_admin_page'
    );
}
add_action('admin_menu', 'seo_add_admin_menu');

/**
 * Página de administración mejorada
 */
function seo_admin_page() {
    $options = seo_img_get_options();

    // Guardar configuración si se envió el formulario
    if (isset($_POST['seo_save_settings']) && wp_verify_nonce($_POST['seo_settings_nonce'], 'seo_settings_action')) {
        $options['compression_enabled'] = isset($_POST['compression_enabled']);
        $options['jpeg_quality'] = intval($_POST['jpeg_quality']);
        $options['png_compression'] = intval($_POST['png_compression']);
        $options['webp_enabled'] = isset($_POST['webp_enabled']);
        $options['webp_quality'] = intval($_POST['webp_quality']);
        $options['max_width'] = intval($_POST['max_width']);
        $options['max_height'] = intval($_POST['max_height']);
        $options['auto_resize'] = isset($_POST['auto_resize']);
        $options['preserve_metadata'] = isset($_POST['preserve_metadata']);
        $options['backup_originals'] = isset($_POST['backup_originals']);

        update_option('seo_img_options', $options);

        echo '<div class="notice notice-success"><p>Configuración guardada correctamente.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>SEO Image Optimizer Pro</h1>

        <div style="display: flex; gap: 20px;">
            <!-- Columna izquierda - Configuración -->
            <div style="flex: 1;">
                <form method="post" action="">
                    <?php wp_nonce_field('seo_settings_action', 'seo_settings_nonce'); ?>

                    <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
                        <h2>⚙️ Configuración de Optimización</h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Compresión automática</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="compression_enabled" <?php checked($options['compression_enabled']); ?>>
                                        Activar compresión de imágenes al subir
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Calidad JPEG</th>
                                <td>
                                    <input type="range" name="jpeg_quality" min="50" max="100" value="<?php echo $options['jpeg_quality']; ?>"
                                           oninput="this.nextElementSibling.value = this.value">
                                    <output><?php echo $options['jpeg_quality']; ?></output>%
                                    <p class="description">Recomendado: 85% (balance calidad/peso)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Compresión PNG</th>
                                <td>
                                    <input type="range" name="png_compression" min="0" max="9" value="<?php echo $options['png_compression']; ?>"
                                           oninput="this.nextElementSibling.value = this.value">
                                    <output><?php echo $options['png_compression']; ?></output>
                                    <p class="description">0 = sin compresión, 9 = máxima compresión</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
                        <h2>🚀 WebP (Formato Moderno)</h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Conversión WebP</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="webp_enabled" <?php checked($options['webp_enabled']); ?>>
                                        Generar versión WebP (30-40% menos peso)
                                    </label>
                                    <?php if (!function_exists('imagewebp')): ?>
                                    <p style="color: red;">⚠️ WebP no disponible en tu servidor PHP</p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Calidad WebP</th>
                                <td>
                                    <input type="range" name="webp_quality" min="50" max="100" value="<?php echo $options['webp_quality']; ?>"
                                           oninput="this.nextElementSibling.value = this.value">
                                    <output><?php echo $options['webp_quality']; ?></output>%
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
                        <h2>📐 Redimensionamiento Automático</h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Redimensionar imágenes grandes</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="auto_resize" <?php checked($options['auto_resize']); ?>>
                                        Activar redimensionamiento automático
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Dimensiones máximas</th>
                                <td>
                                    <label>Ancho: <input type="number" name="max_width" value="<?php echo $options['max_width']; ?>" style="width: 100px;"> px</label><br>
                                    <label>Alto: <input type="number" name="max_height" value="<?php echo $options['max_height']; ?>" style="width: 100px;"> px</label>
                                    <p class="description">Las imágenes más grandes se redimensionarán proporcionalmente</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p>
                        <input type="submit" name="seo_save_settings" class="button button-primary" value="Guardar Configuración">
                    </p>
                </form>
            </div>

            <!-- Columna derecha - Información y acciones -->
            <div style="width: 350px;">
                <div style="background: #f0f8ff; padding: 20px; margin-top: 20px; border: 1px solid #0073aa;">
                    <h3>📊 Estado del Sistema</h3>
                    <ul style="list-style: none; padding: 0;">
                        <li>✅ GD Library: <?php echo extension_loaded('gd') ? 'Instalada' : 'No disponible'; ?></li>
                        <li>✅ ImageMagick: <?php echo extension_loaded('imagick') ? 'Instalada' : 'No disponible'; ?></li>
                        <li>✅ WebP: <?php echo function_exists('imagewebp') ? 'Soportado' : 'No soportado'; ?></li>
                        <li>✅ PHP Memory: <?php echo ini_get('memory_limit'); ?></li>
                    </ul>
                </div>

                <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
                    <h3>🔧 Herramientas</h3>

                    <h4>Optimizar Imágenes Existentes</h4>
                    <p>Aplicar optimización a todas las imágenes ya subidas.</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="seo_optimize_existing">
                        <?php wp_nonce_field('seo_optimize_action', 'seo_optimize_nonce'); ?>
                        <button type="submit" class="button" onclick="return confirm('¿Optimizar todas las imágenes existentes?');">
                            Optimizar Biblioteca de Medios
                        </button>
                    </form>

                    <h4 style="margin-top: 20px;">Renombrar Archivos</h4>
                    <p>Aplicar el renombrado SEO a imágenes existentes.</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="seo_rename_images">
                        <?php wp_nonce_field('seo_rename_action', 'seo_rename_nonce'); ?>
                        <button type="submit" class="button" onclick="return confirm('¿Renombrar todas las imágenes?');">
                            Renombrar Imágenes
                        </button>
                    </form>
                </div>

                <div style="background: #e7f7e7; padding: 20px; margin-top: 20px; border: 1px solid #46b450;">
                    <h3>💡 Consejos SEO</h3>
                    <ul style="font-size: 13px;">
                        <li>JPEG al 85% = óptimo para fotos</li>
                        <li>PNG para logos y gráficos</li>
                        <li>WebP reduce 30-40% el peso</li>
                        <li>Máximo 2000px para web normal</li>
                        <li>Nombres descriptivos con guiones</li>
                        <li>Alt text único para cada imagen</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Optimizar imágenes existentes en la biblioteca
 */
function seo_optimize_existing_images() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes');
    }

    if (!isset($_POST['seo_optimize_nonce']) || !wp_verify_nonce($_POST['seo_optimize_nonce'], 'seo_optimize_action')) {
        wp_die('Error de seguridad');
    }

    $options = seo_img_get_options();

    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'post_status' => 'inherit'
    );

    $images = get_posts($args);
    $count = 0;

    foreach ($images as $image) {
        $file_path = get_attached_file($image->ID);

        if (!file_exists($file_path)) {
            continue;
        }

        // Aplicar optimización
        $file_info = array(
            'tmp_name' => $file_path,
            'type' => $image->post_mime_type,
            'size' => filesize($file_path)
        );

        // Simular upload para aplicar optimización
        seo_optimize_uploaded_image($file_info);

        // Generar WebP si está habilitado
        if ($options['webp_enabled']) {
            $metadata = wp_get_attachment_metadata($image->ID);
            seo_generate_webp_on_upload($metadata, $image->ID);
        }

        $count++;
    }

    wp_redirect(admin_url('tools.php?page=seo-image-optimizer&optimized=' . $count));
    exit;
}
add_action('admin_post_seo_optimize_existing', 'seo_optimize_existing_images');

/**
 * Renombrar imágenes existentes
 */
function seo_bulk_rename_images() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes');
    }

    if (!isset($_POST['seo_rename_nonce']) || !wp_verify_nonce($_POST['seo_rename_nonce'], 'seo_rename_action')) {
        wp_die('Error de seguridad');
    }

    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'post_status' => 'inherit'
    );

    $images = get_posts($args);
    $count = 0;

    foreach ($images as $image) {
        $file_path = get_attached_file($image->ID);
        $file_info = pathinfo($file_path);
        $old_name = $file_info['basename'];
        $new_name = seo_sanitize_image_filename($old_name);

        if ($old_name !== $new_name) {
            $new_path = $file_info['dirname'] . '/' . $new_name;

            if (rename($file_path, $new_path)) {
                update_attached_file($image->ID, $new_path);

                $metadata = wp_get_attachment_metadata($image->ID);
                if ($metadata) {
                    $metadata['file'] = str_replace($old_name, $new_name, $metadata['file']);
                    wp_update_attachment_metadata($image->ID, $metadata);
                }

                $count++;
            }
        }
    }

    wp_redirect(admin_url('tools.php?page=seo-image-optimizer&renamed=' . $count));
    exit;
}
add_action('admin_post_seo_rename_images', 'seo_bulk_rename_images');