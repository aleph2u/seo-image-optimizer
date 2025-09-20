<?php
/**
 * Sistema de actualización para plugin privado vía GitHub
 * Añadir este código al archivo principal del plugin
 */

class SEO_Image_Optimizer_Updater {

    private $github_user = 'aleph2u';
    private $github_repo = 'seo-image-optimizer';
    private $plugin_file;
    private $plugin_data;
    private $github_response;

    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'rename_folder'), 10, 3);
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->get_plugin_data();
        $this->get_github_release();

        // Eliminar 'v' del tag name para comparación correcta
        $github_version = str_replace('v', '', $this->github_response['tag_name']);

        if (version_compare($github_version, $this->plugin_data['Version'], '>')) {
            $plugin_slug = plugin_basename($this->plugin_file);

            $update = array(
                'slug' => $plugin_slug,
                'new_version' => $github_version,
                'url' => $this->plugin_data['PluginURI'],
                'package' => $this->github_response['zipball_url'],
                'icons' => array(),
                'banners' => array(),
                'tested' => '6.4',
                'requires_php' => '7.0',
            );

            $transient->response[$plugin_slug] = (object) $update;
        }

        return $transient;
    }

    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return false;
        }

        if (empty($response->slug) || $response->slug != plugin_basename($this->plugin_file)) {
            return false;
        }

        $this->get_plugin_data();
        $this->get_github_release();

        $response = new stdClass();
        $response->last_updated = $this->github_response['published_at'];
        $response->slug = plugin_basename($this->plugin_file);
        $response->name = $this->plugin_data['Name'];
        $response->version = str_replace('v', '', $this->github_response['tag_name']);
        $response->author = $this->plugin_data['Author'];
        $response->homepage = $this->plugin_data['PluginURI'];
        $response->download_link = $this->github_response['zipball_url'];
        $response->sections = array(
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->github_response['body']
        );

        return $response;
    }

    public function rename_folder($source, $remote_source, $upgrader) {
        global $wp_filesystem;

        $plugin_slug = plugin_basename($this->plugin_file);
        $plugin_slug = str_replace('.php', '', $plugin_slug);

        $corrected_source = str_replace(
            basename($source),
            $plugin_slug,
            $source
        );

        if ($wp_filesystem->move($source, $corrected_source, true)) {
            return $corrected_source;
        } else {
            return new WP_Error('rename_failed', 'Unable to rename plugin folder');
        }
    }

    private function get_plugin_data() {
        $this->plugin_data = get_plugin_data($this->plugin_file);
    }

    private function get_github_release() {
        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_user,
            $this->github_repo
        );

        $response = wp_remote_get($request_uri, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        ));

        if (!is_wp_error($response)) {
            $this->github_response = json_decode(wp_remote_retrieve_body($response), true);
        }
    }
}

// Inicializar el updater (añadir al archivo principal del plugin)
// new SEO_Image_Optimizer_Updater(__FILE__);