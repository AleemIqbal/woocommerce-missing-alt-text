<?php
/*
Plugin Name: WooCommerce Alt Text
Plugin URI: https://example.com
Description: Adds missing alt text to WooCommerce product images
Version: 1.0
Author: Your Name
Author URI: https://yourwebsite.com
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt
*/

// Check if WordPress and WooCommerce are active
if (!defined('ABSPATH') || !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

class WooCommerce_Alt_Text
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'wcat_create_settings_page'));
        add_action('admin_init', array($this, 'wcat_register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'wcat_enqueue_scripts'));
        add_action('wp_ajax_wcat_update_alt_text', array($this, 'wcat_update_alt_text'));
        add_filter('wp_get_attachment_image_attributes', array($this, 'wcat_filter_image_attributes'), 10, 3);
    }

    public function wcat_create_settings_page()
    {
        add_submenu_page('woocommerce', 'WooCommerce Alt Text', 'Alt Text', 'manage_options', 'wcat-settings', array($this, 'wcat_settings_page'));
    }

    public function wcat_register_settings()
    {
        register_setting('wcat-settings-group', 'wcat_alt_text_source');
        register_setting('wcat-settings-group', 'wcat_apply_update');
    }

    public function wcat_enqueue_scripts($hook)
    {
        if ('woocommerce_page_wcat-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('wcat-styles', plugin_dir_url(__FILE__) . 'css/wcat-styles.css');
        wp_enqueue_script('wcat-scripts', plugin_dir_url(__FILE__) . 'js/wcat-scripts.js', array('jquery'));
        wp_localize_script('wcat-scripts', 'wcat_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    }

        public function wcat_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce Alt Text Settings', 'woocommerce-alt-text'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('wcat-settings-group');
                do_settings_sections('wcat-settings-group');
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Alt Text Source', 'woocommerce-alt-text'); ?></th>
                        <td>
                            <select name="wcat_alt_text_source">
                                <option value="filename" <?php selected(get_option('wcat_alt_text_source'), 'filename'); ?>><?php _e('File Name', 'woocommerce-alt-text'); ?></option>
                                <option value="title" <?php selected(get_option('wcat_alt_text_source'), 'title'); ?>><?php _e('H1 Title', 'woocommerce-alt-text'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Apply Alt Text Update', 'woocommerce-alt-text'); ?></th>
                        <td>
                            <select name="wcat_apply_update">
                                <option value="all" <?php selected(get_option('wcat_apply_update'), 'all'); ?>><?php _e('All Images', 'woocommerce-alt-text'); ?></option>
                                <option value="missing" <?php selected(get_option('wcat_apply_update'), 'missing'); ?>><?php _e('Missing Alt Text Only', 'woocommerce-alt-text'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <button class="button button-primary" id="wcat-update-alt-text"><?php _e('Update Alt Text', 'woocommerce-alt-text'); ?></button>
            <div id="wcat-progress-wrap" style="display:none;" data-total-images="<?php echo esc_attr($this->wcat_get_total_images()); ?>">
                <div id="wcat-progress-bar"></div>
            </div>
        </div>
        <?php
    }
    public function wcat_get_total_images() {
    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
    );

    $query = new WP_Query($args);

    return $query->found_posts;
}

public function wcat_update_alt_text()
{
    if (!current_user_can('manage_options')) {
        wp_die();
    }

    $attachment_id = intval($_POST['attachment_id']);
    $source = get_option('wcat_alt_text_source', 'filename');
    $apply_update = get_option('wcat_apply_update', 'all');

    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'offset' => $attachment_id,
        'orderby' => 'ID',
        'order' => 'ASC',
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $attachment = get_post();

            $alt_text = '';

            if ('filename' === $source) {
                $alt_text = pathinfo($attachment->guid, PATHINFO_FILENAME);
            } elseif ('title' === $source) {
                $parent_id = $attachment->post_parent;

                if ($parent_id) {
                    $parent_post = get_post($parent_id);
                    $alt_text = get_the_title($parent_post);
                }
            }

            $current_alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);

            // If applying only to missing alt text, skip if the alt text is already set
            if ('missing' === $apply_update && !empty($current_alt_text)) {
                $response = array(
                    'status' => 'success',
                    'next_attachment_id' => $attachment_id + 1,
                );
            } else {
                update_post_meta($attachment->ID, '_wp_attachment_image_alt', sanitize_text_field($alt_text));

                $response = array(
                    'status' => 'success',
                    'next_attachment_id' => $attachment_id + 1,
                );
            }
        }
    } else {
        $response = array(
            'status' => 'complete',
        );
    }

    wp_reset_postdata();
    wp_send_json($response);
}
public function wcat_filter_image_attributes($attr, $attachment, $size)
    {
        $alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);

        if ($alt_text) {
            $attr['alt'] = $alt_text;
        }

        return $attr;
    }
}

new WooCommerce_Alt_Text();