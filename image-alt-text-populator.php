<?php
/**
 * Plugin Name: Image Alt Text Populator
 * Plugin URI: https://github.com/chebon254/Image-ALT-Text-WordPress-Plugin
 * Description: Automatically populates image alt text for SEO. Covers all product images (featured + gallery), uses the image/product title plus your website name, and includes bulk update for existing images.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Kelvin Chebon
 * Author URI: https://chebonkelvin.com
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
define('IATP_VERSION', '2.0.0');
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
     * Cached map of attachment ID => product title (featured + gallery images)
     *
     * @var array<int, string>|null
     */
    private ?array $product_image_map = null;

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

        // Keep product images (featured + gallery) populated when a product is saved
        add_action('save_post_product', [$this, 'populate_product_images_on_save'], 20, 2);

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
            'default' => 'title_sitename',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('iatp_settings_group', 'iatp_custom_alt_text', [
            'type' => 'string',
            'default' => get_bloginfo('name'),
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
            'siteName' => get_bloginfo('name'),
            'customAltText' => get_option('iatp_custom_alt_text', get_bloginfo('name')),
            'strings' => [
                'processing' => __('Processing...', 'image-alt-populator'),
                'complete' => __('Complete!', 'image-alt-populator'),
                'error' => __('An error occurred. Please try again.', 'image-alt-populator')
            ]
        ]);
    }

    /**
     * Build a map of attachment ID => product title for every image used by a
     * product (featured image and product gallery), including images that are
     * not "attached" to the product in the media library.
     *
     * @return array<int, string>
     */
    public function get_product_image_map(): array {
        if (null !== $this->product_image_map) {
            return $this->product_image_map;
        }

        $map = [];

        if (post_type_exists('product')) {
            global $wpdb;

            $rows = $wpdb->get_results(
                "SELECT pm.meta_key, pm.meta_value, p.post_title
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = 'product'
                   AND p.post_status NOT IN ('trash', 'auto-draft')
                   AND pm.meta_key IN ('_thumbnail_id', '_product_image_gallery')
                   AND pm.meta_value != ''"
            );

            foreach ((array) $rows as $row) {
                $ids = ('_thumbnail_id' === $row->meta_key)
                    ? [(int) $row->meta_value]
                    : array_map('intval', explode(',', (string) $row->meta_value));

                foreach ($ids as $id) {
                    // Featured images win over gallery references from other products
                    if ($id > 0 && (!isset($map[$id]) || '_thumbnail_id' === $row->meta_key)) {
                        $map[$id] = $row->post_title;
                    }
                }
            }
        }

        $this->product_image_map = $map;

        return $map;
    }

    /**
     * Resolve the best available title for an image:
     * product title > parent post title > attachment title > cleaned filename.
     */
    private function get_image_title(int $attachment_id): string {
        if (!$attachment_id) {
            return '';
        }

        // 1. Product that uses this image as featured or gallery image
        $product_map = $this->get_product_image_map();
        if (!empty($product_map[$attachment_id])) {
            return $product_map[$attachment_id];
        }

        // 2. Post/page/product the image is attached to
        $parent_id = wp_get_post_parent_id($attachment_id);
        if ($parent_id) {
            $parent_title = get_the_title($parent_id);
            if ('' !== trim((string) $parent_title)) {
                return $parent_title;
            }
        }

        // 3. The attachment's own title
        $attachment_title = get_the_title($attachment_id);
        if ('' !== trim((string) $attachment_title)) {
            return $attachment_title;
        }

        // 4. Humanized filename as last resort
        return $this->get_clean_filename($attachment_id);
    }

    /**
     * Get a human-readable name from the attachment file name
     */
    private function get_clean_filename(int $attachment_id): string {
        $file = get_attached_file($attachment_id);
        if (!$file) {
            return '';
        }

        $filename = preg_replace('/\.[^.]+$/', '', basename($file)); // Remove extension
        $filename = str_replace(['-', '_'], ' ', $filename);
        $filename = preg_replace('/\s+\d+x\d+$/', '', $filename); // Strip trailing size suffix
        $filename = trim((string) preg_replace('/\s+/', ' ', $filename));

        return ucwords($filename);
    }

    /**
     * Get alt text based on settings
     */
    private function get_alt_text(int $attachment_id = 0): string {
        $format = get_option('iatp_alt_text_format', 'title_sitename');
        $site_name = get_bloginfo('name');

        switch ($format) {
            case 'title_sitename':
                $title = $this->get_image_title($attachment_id);
                if ('' !== $title && strcasecmp($title, $site_name) !== 0) {
                    return $title . ' - ' . $site_name;
                }
                return $site_name;

            case 'sitename':
                return $site_name;

            case 'sitename_filename':
                if ($attachment_id) {
                    $filename = $this->get_clean_filename($attachment_id);
                    if ('' !== $filename) {
                        return $site_name . ' - ' . $filename;
                    }
                }
                return $site_name;

            case 'custom':
                $custom_text = get_option('iatp_custom_alt_text', $site_name);

                // Support dynamic placeholders in custom text
                $custom_text = str_replace(
                    ['{title}', '{site}', '{filename}'],
                    [
                        $this->get_image_title($attachment_id),
                        $site_name,
                        $this->get_clean_filename($attachment_id)
                    ],
                    $custom_text
                );

                $custom_text = trim((string) preg_replace('/\s+/', ' ', $custom_text));

                return '' !== $custom_text ? $custom_text : $site_name;

            default:
                return $site_name;
        }
    }

    /**
     * Set alt text on an attachment, respecting the overwrite setting.
     * Returns true when the alt text was written.
     */
    private function maybe_set_alt_text(int $attachment_id, ?bool $force_overwrite = null): bool {
        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $overwrite = $force_overwrite ?? (bool) get_option('iatp_overwrite_existing', false);

        if (!empty($current_alt) && !$overwrite) {
            return false;
        }

        $alt_text = $this->get_alt_text($attachment_id);

        if ($alt_text === $current_alt) {
            return false;
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

        return true;
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

        $this->maybe_set_alt_text($attachment_id);

        return $metadata;
    }

    /**
     * When a product is saved, (re)populate alt text for its featured image
     * and every gallery image so all product images stay SEO-friendly.
     */
    public function populate_product_images_on_save(int $post_id, \WP_Post $post): void {
        if (!get_option('iatp_auto_populate', true)) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Product meta just changed — rebuild the map on next use
        $this->product_image_map = null;

        foreach ($this->get_product_image_ids($post_id) as $image_id) {
            if (wp_attachment_is_image($image_id)) {
                $this->maybe_set_alt_text($image_id);
            }
        }
    }

    /**
     * Get all image IDs used by a product (featured + gallery)
     *
     * @return int[]
     */
    private function get_product_image_ids(int $product_id): array {
        $ids = [];

        $thumbnail_id = (int) get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            $ids[] = $thumbnail_id;
        }

        $gallery = (string) get_post_meta($product_id, '_product_image_gallery', true);
        if ('' !== $gallery) {
            foreach (explode(',', $gallery) as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Get image statistics using direct queries (fast even with huge libraries)
     *
     * @return array{total: int, withAlt: int, withoutAlt: int, productImages: int}
     */
    public function get_image_stats(): array {
        global $wpdb;

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );

        $with_alt = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
               AND p.post_mime_type LIKE 'image/%'
               AND pm.meta_value != ''"
        );

        return [
            'total' => $total,
            'withAlt' => $with_alt,
            'withoutAlt' => max(0, $total - $with_alt),
            'productImages' => count($this->get_product_image_map())
        ];
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

        // Get all image attachments (covers the whole media library, including
        // every product featured/gallery image)
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
        $updated = 0;
        $skipped = 0;

        foreach ($images as $image_id) {
            if ($this->maybe_set_alt_text($image_id)) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $stats = $this->get_image_stats();
        $has_more = count($images) === $per_batch;

        wp_send_json_success([
            'updated' => $updated,
            'skipped' => $skipped,
            'hasMore' => $has_more,
            'total' => $stats['total'],
            'processed' => $batch * $per_batch + count($images),
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

        wp_send_json_success($this->get_image_stats());
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
    add_option('iatp_alt_text_format', 'title_sitename');
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});
