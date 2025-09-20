<?php
/**
 * Plugin Name: SEO Image Optimizer
 * Description: Optimiza automáticamente nombres de archivos de imágenes para SEO
 * Version: 1.0
 * Author: Tu Nombre
 * Plugin URI: https://github.com/aleph2u/seo-image-optimizer
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Sistema de actualización automática desde GitHub
require_once plugin_dir_path(__FILE__) . 'updater.php';
new SEO_Image_Optimizer_Updater(__FILE__);

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

// Aplicar filtro al subir archivos
add_filter('sanitize_file_name', 'seo_sanitize_image_filename', 10);

/**
 * Renombrar imágenes existentes en la biblioteca de medios
 */
function seo_bulk_rename_images() {
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes');
    }

    // Verificar nonce
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

            // Renombrar archivo físico
            if (rename($file_path, $new_path)) {
                // Actualizar base de datos
                update_attached_file($image->ID, $new_path);

                // Actualizar metadata
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

/**
 * Añadir página de herramientas en el admin
 */
function seo_add_admin_menu() {
    add_management_page(
        'SEO Image Optimizer',
        'SEO Image Optimizer',
        'manage_options',
        'seo-image-optimizer',
        'seo_admin_page'
    );
}
add_action('admin_menu', 'seo_add_admin_menu');

/**
 * Página de administración
 */
function seo_admin_page() {
    ?>
    <div class="wrap">
        <h1>SEO Image Optimizer</h1>

        <?php if (isset($_GET['renamed'])): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($_GET['renamed']); ?> imágenes renombradas correctamente.</p>
            </div>
        <?php endif; ?>

        <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
            <h2>Configuración Actual</h2>
            <p>✅ Las nuevas imágenes se optimizarán automáticamente al subirlas.</p>
            <p>El plugin realiza las siguientes optimizaciones:</p>
            <ul style="list-style: disc; margin-left: 30px;">
                <li>Convierte nombres a minúsculas</li>
                <li>Elimina caracteres especiales y acentos</li>
                <li>Reemplaza espacios y guiones bajos por guiones medios</li>
                <li>Limita la longitud a 60 caracteres</li>
            </ul>
        </div>

        <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
            <h2>Renombrar Imágenes Existentes</h2>
            <p><strong>⚠️ Advertencia:</strong> Esta acción renombrará permanentemente todos los archivos de imagen en tu biblioteca de medios.</p>
            <p>Se recomienda hacer una copia de seguridad antes de proceder.</p>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="seo_rename_images">
                <?php wp_nonce_field('seo_rename_action', 'seo_rename_nonce'); ?>
                <button type="submit" class="button button-primary" onclick="return confirm('¿Estás seguro? Esta acción no se puede deshacer.');">
                    Renombrar Todas las Imágenes Existentes
                </button>
            </form>
        </div>

        <div style="background: #f0f8ff; padding: 20px; margin-top: 20px; border: 1px solid #0073aa;">
            <h3>Ejemplo de Optimización</h3>
            <p><strong>Antes:</strong> Membrana_Hermética_AMPACOLL Flexx (Passivhaus).jpg</p>
            <p><strong>Después:</strong> membrana-hermetica-ampacoll-flexx-passivhaus.jpg</p>
        </div>
    </div>
    <?php
}

// Manejar la acción de renombrado masivo
add_action('admin_post_seo_rename_images', 'seo_bulk_rename_images');

/**
 * Auto-generar texto alt basado en el nombre del archivo
 * (Opcional - descomenta si quieres esta funcionalidad)
 */
/*
function seo_auto_alt_text($response, $attachment, $meta) {
    if (empty($response['alt'])) {
        $title = get_the_title($attachment->ID);
        $alt = str_replace('-', ' ', pathinfo($attachment->guid, PATHINFO_FILENAME));
        $alt = ucwords($alt);
        $response['alt'] = $alt;
    }
    return $response;
}
add_filter('wp_prepare_attachment_for_js', 'seo_auto_alt_text', 10, 3);
*/