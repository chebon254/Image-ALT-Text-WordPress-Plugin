<?php
/**
 * Admin Page Template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current statistics
$stats = $this->get_image_stats();
$total_images = $stats['total'];
$images_with_alt = $stats['withAlt'];
$images_without_alt = $stats['withoutAlt'];
$product_images = $stats['productImages'];
$woocommerce_active = post_type_exists('product');

$site_name = get_bloginfo('name');

// Get current preview text based on format
$current_format = get_option('iatp_alt_text_format', 'title_sitename');
$preview_text = $site_name;

switch ($current_format) {
    case 'title_sitename':
        $preview_text = __('Example Product', 'image-alt-populator') . ' - ' . $site_name;
        break;
    case 'sitename':
        $preview_text = $site_name;
        break;
    case 'sitename_filename':
        $preview_text = $site_name . ' - ' . __('Example Image', 'image-alt-populator');
        break;
    case 'custom':
        $preview_text = get_option('iatp_custom_alt_text', $site_name);
        break;
    default:
        $preview_text = $site_name;
}
?>

<div class="wrap iatp-admin-wrap">
    <h1><?php echo esc_html__('Image Alt Text Populator', 'image-alt-populator'); ?></h1>

    <div class="iatp-container">
        <!-- Statistics Card -->
        <div class="iatp-card">
            <h2><?php echo esc_html__('Image Statistics', 'image-alt-populator'); ?></h2>
            <div class="iatp-stats">
                <div class="iatp-stat-item">
                    <span class="iatp-stat-label"><?php echo esc_html__('Total Images:', 'image-alt-populator'); ?></span>
                    <span class="iatp-stat-value" id="iatp-total-images"><?php echo esc_html($total_images); ?></span>
                </div>
                <div class="iatp-stat-item">
                    <span class="iatp-stat-label"><?php echo esc_html__('With Alt Text:', 'image-alt-populator'); ?></span>
                    <span class="iatp-stat-value iatp-stat-success" id="iatp-with-alt"><?php echo esc_html($images_with_alt); ?></span>
                </div>
                <div class="iatp-stat-item">
                    <span class="iatp-stat-label"><?php echo esc_html__('Without Alt Text:', 'image-alt-populator'); ?></span>
                    <span class="iatp-stat-value iatp-stat-warning" id="iatp-without-alt"><?php echo esc_html($images_without_alt); ?></span>
                </div>
                <?php if ($woocommerce_active) : ?>
                <div class="iatp-stat-item">
                    <span class="iatp-stat-label"><?php echo esc_html__('Product Images (Featured + Gallery):', 'image-alt-populator'); ?></span>
                    <span class="iatp-stat-value" id="iatp-product-images"><?php echo esc_html($product_images); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bulk Update Card -->
        <div class="iatp-card">
            <h2><?php echo esc_html__('Bulk Update Images', 'image-alt-populator'); ?></h2>
            <p><?php echo esc_html__('Update alt text for all existing images in your media library, including every product featured and gallery image.', 'image-alt-populator'); ?></p>

            <div class="iatp-preview">
                <strong><?php echo esc_html__('Preview Alt Text:', 'image-alt-populator'); ?></strong>
                <code id="iatp-alt-preview"><?php echo esc_html($preview_text); ?></code>
            </div>

            <div class="iatp-progress-container" id="iatp-progress-container" style="display: none;">
                <div class="iatp-progress-bar">
                    <div class="iatp-progress-fill" id="iatp-progress-fill"></div>
                </div>
                <div class="iatp-progress-text" id="iatp-progress-text">0%</div>
                <div class="iatp-progress-info" id="iatp-progress-info"></div>
            </div>

            <button type="button" class="button button-primary button-large" id="iatp-bulk-update">
                <?php echo esc_html__('Update All Images', 'image-alt-populator'); ?>
            </button>

            <div class="iatp-result" id="iatp-result" style="display: none;"></div>
        </div>

        <!-- Settings Card -->
        <div class="iatp-card">
            <h2><?php echo esc_html__('Settings', 'image-alt-populator'); ?></h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('iatp_settings_group');
                ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="iatp_auto_populate">
                                    <?php echo esc_html__('Auto-Populate New Uploads', 'image-alt-populator'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="iatp_auto_populate"
                                           id="iatp_auto_populate"
                                           value="1"
                                           <?php checked(get_option('iatp_auto_populate', true)); ?>>
                                    <?php echo esc_html__('Automatically add alt text to newly uploaded images and to product images when a product is saved', 'image-alt-populator'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="iatp_overwrite_existing">
                                    <?php echo esc_html__('Overwrite Existing Alt Text', 'image-alt-populator'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="iatp_overwrite_existing"
                                           id="iatp_overwrite_existing"
                                           value="1"
                                           <?php checked(get_option('iatp_overwrite_existing', false)); ?>>
                                    <?php echo esc_html__('Replace existing alt text during bulk update', 'image-alt-populator'); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('If unchecked, only images without alt text will be updated.', 'image-alt-populator'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="iatp_alt_text_format">
                                    <?php echo esc_html__('Alt Text Format', 'image-alt-populator'); ?>
                                </label>
                            </th>
                            <td>
                                <select name="iatp_alt_text_format" id="iatp_alt_text_format" class="regular-text">
                                    <option value="title_sitename" <?php selected($current_format, 'title_sitename'); ?>>
                                        <?php echo esc_html__('Title + Site Name (recommended for SEO)', 'image-alt-populator'); ?>
                                    </option>
                                    <option value="sitename" <?php selected($current_format, 'sitename'); ?>>
                                        <?php echo esc_html__('Site Name Only', 'image-alt-populator'); ?>
                                    </option>
                                    <option value="sitename_filename" <?php selected($current_format, 'sitename_filename'); ?>>
                                        <?php echo esc_html__('Site Name + File Name', 'image-alt-populator'); ?>
                                    </option>
                                    <option value="custom" <?php selected($current_format, 'custom'); ?>>
                                        <?php echo esc_html__('Custom Text', 'image-alt-populator'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php echo esc_html__('"Title + Site Name" uses the product title for product images, the parent page title for attached images, or the image title/filename otherwise.', 'image-alt-populator'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr id="iatp_custom_alt_text_row" style="display: <?php echo ($current_format === 'custom') ? 'table-row' : 'none'; ?>;">
                            <th scope="row">
                                <label for="iatp_custom_alt_text">
                                    <?php echo esc_html__('Custom Alt Text', 'image-alt-populator'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       name="iatp_custom_alt_text"
                                       id="iatp_custom_alt_text"
                                       value="<?php echo esc_attr(get_option('iatp_custom_alt_text', $site_name)); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php echo esc_html__('Enter custom text to use as alt text for all images. Placeholders: {title} = product/image title, {site} = website name, {filename} = image file name.', 'image-alt-populator'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'image-alt-populator')); ?>
            </form>
        </div>

        <!-- Information Card -->
        <div class="iatp-card iatp-info-card">
            <h2><?php echo esc_html__('How It Works', 'image-alt-populator'); ?></h2>
            <ul>
                <li><?php echo esc_html__('Enable "Auto-Populate" to automatically add alt text to all newly uploaded images and to product images when products are saved.', 'image-alt-populator'); ?></li>
                <li><?php echo esc_html__('Use "Bulk Update" to add alt text to all existing images — every product featured and gallery image is included.', 'image-alt-populator'); ?></li>
                <li><?php echo esc_html__('Choose whether to overwrite existing alt text or only update empty ones.', 'image-alt-populator'); ?></li>
                <li><?php echo esc_html__('Select your preferred alt text format from the available options.', 'image-alt-populator'); ?></li>
            </ul>

            <h3><?php echo esc_html__('Alt Text Formats', 'image-alt-populator'); ?></h3>
            <ul>
                <li><strong><?php echo esc_html__('Title + Site Name:', 'image-alt-populator'); ?></strong> <?php echo esc_html(__('Example Product', 'image-alt-populator') . ' - ' . $site_name); ?></li>
                <li><strong><?php echo esc_html__('Site Name Only:', 'image-alt-populator'); ?></strong> <?php echo esc_html($site_name); ?></li>
                <li><strong><?php echo esc_html__('Site Name + File Name:', 'image-alt-populator'); ?></strong> <?php echo esc_html($site_name . ' - ' . __('Example Image', 'image-alt-populator')); ?></li>
                <li><strong><?php echo esc_html__('Custom Text:', 'image-alt-populator'); ?></strong> <?php echo esc_html__('Your custom text, with optional {title}, {site} and {filename} placeholders', 'image-alt-populator'); ?></li>
            </ul>
        </div>
    </div>
</div>
