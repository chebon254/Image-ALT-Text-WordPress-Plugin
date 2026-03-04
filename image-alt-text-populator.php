<?php
/**
 * Plugin Name: Image Alt Text Populator
 * Plugin URI: https://github.com/yourusername/image-alt-text-populator
 * Description: Automatically populates all images with website name as alt text. Includes bulk update for existing images and automatic alt text for new uploads.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: image-alt-populator
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IATP_VERSION', '1.0.0');
define('IATP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IATP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IATP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Image Alt Text Populator Class
 */
class Image_Alt_Text_Populator {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of the class
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Auto-populate alt text for new uploads
        add_filter('wp_generate_attachment_metadata', [$this, 'auto_populate_alt_text'], 10, 2);
        
        // Add AJAX handlers
        add_action('wp_ajax_iatp_bulk_update', [$this, 'ajax_bulk_update']);
        add_action('wp_ajax_iatp_get_progress', [$this, 'ajax_get_progress']);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void {
        add_management_page(
            __('Image Alt Text Populator', 'image-alt-populator'),
            __('Alt Text Populator', 'image-alt-populator'),
            'manage_options',
            'image-alt-text-populator',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings(): void {
        register_setting('iatp_settings_group', 'iatp_auto_populate', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('iatp_settings_group', 'iatp_overwrite_existing', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('iatp_settings_group', 'iatp_alt_text_format', [
            'type' => 'string',
            'default' => 'sitename',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts(string $hook): void {
        if ($hook !== 'tools_page_image-alt-text-populator') {
            return;
        }
        
        wp_enqueue_style(
            'iatp-admin-css',
            IATP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            IATP_VERSION
        );
        
        wp_enqueue_script(
            'iatp-admin-js',
            IATP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            IATP_VERSION,
            true
        );
        
        wp_localize_script('iatp-admin-js', 'iatpData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iatp_nonce'),
            'strings' => [
                'processing' => __('Processing...', 'image-alt-populator'),
                'complete' => __('Complete!', 'image-alt-populator'),
                'error' => __('An error occurred. Please try again.', 'image-alt-populator')
            ]
        ]);
    }
    
    /**
     * Get alt text based on settings
     */
    private function get_alt_text(int $attachment_id = 0): string {
        $format = get_option('iatp_alt_text_format', 'sitename');
        $site_name = get_bloginfo('name');
        
        switch ($format) {
            case 'sitename':
                return $site_name;
            
            case 'sitename_filename':
                if ($attachment_id) {
                    $filename = basename(get_attached_file($attachment_id));
                    $filename = preg_replace('/\.[^.]+$/', '', $filename); // Remove extension
                    $filename = str_replace(['-', '_'], ' ', $filename);
                    return $site_name . ' - ' . ucwords($filename);
                }
                return $site_name;
            
            case 'custom':
                $custom_text = get_option('iatp_custom_alt_text', $site_name);
                return $custom_text;
            
            default:
                return $site_name;
        }
    }
    
    /**
     * Auto-populate alt text for newly uploaded images
     */
    public function auto_populate_alt_text(array $metadata, int $attachment_id): array {
        // Check if auto-populate is enabled
        if (!get_option('iatp_auto_populate', true)) {
            return $metadata;
        }
        
        // Check if this is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return $metadata;
        }
        
        // Get current alt text
        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        // Check if we should overwrite existing alt text
        $overwrite = get_option('iatp_overwrite_existing', false);
        
        if (empty($current_alt) || $overwrite) {
            $alt_text = $this->get_alt_text($attachment_id);
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
        
        return $metadata;
    }
    
    /**
     * AJAX handler for bulk update
     */
    public function ajax_bulk_update(): void {
        // Verify nonce
        check_ajax_referer('iatp_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'image-alt-populator')]);
        }
        
        $batch = isset($_POST['batch']) ? intval($_POST['batch']) : 0;
        $per_batch = 50; // Process 50 images per batch
        
        // Get all image attachments
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $per_batch,
            'offset' => $batch * $per_batch,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC'
        ];
        
        $images = get_posts($args);
        $overwrite = get_option('iatp_overwrite_existing', false);
        $updated = 0;
        $skipped = 0;
        
        foreach ($images as $image_id) {
            $current_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            
            if (empty($current_alt) || $overwrite) {
                $alt_text = $this->get_alt_text($image_id);
                update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);
                $updated++;
            } else {
                $skipped++;
            }
        }
        
        // Get total count
        $total_args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        $total_images = count(get_posts($total_args));
        
        $has_more = count($images) === $per_batch;
        
        wp_send_json_success([
            'updated' => $updated,
            'skipped' => $skipped,
            'hasMore' => $has_more,
            'total' => $total_images,
            'processed' => ($batch + 1) * $per_batch,
            'nextBatch' => $batch + 1
        ]);
    }
    
    /**
     * AJAX handler to get progress
     */
    public function ajax_get_progress(): void {
        check_ajax_referer('iatp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'image-alt-populator')]);
        }
        
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $all_images = get_posts($args);
        $total = count($all_images);
        $with_alt = 0;
        $without_alt = 0;
        
        foreach ($all_images as $image_id) {
            $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            if (!empty($alt_text)) {
                $with_alt++;
            } else {
                $without_alt++;
            }
        }
        
        wp_send_json_success([
            'total' => $total,
            'withAlt' => $with_alt,
            'withoutAlt' => $without_alt
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        require_once IATP_PLUGIN_DIR . 'includes/admin-page.php';
    }
}

// Initialize the plugin
function iatp_init(): void {
    Image_Alt_Text_Populator::get_instance();
}
add_action('plugins_loaded', 'iatp_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options
    add_option('iatp_auto_populate', true);
    add_option('iatp_overwrite_existing', false);
    add_option('iatp_alt_text_format', 'sitename');
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});
