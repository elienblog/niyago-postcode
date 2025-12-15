<?php
/**
 * Plugin Name: Niyago Postcodes
 * Plugin URI: https://gengbiz.my
 * Description: Auto-fill city and state when postcode is entered in WooCommerce checkout.
 * Version: 1.0.4
 * Author: Gengbiz
 * Author URI: https://gengbiz.my
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: niyago-postcodes
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NIYAGO_POSTCODES_VERSION', '1.0.4');
define('NIYAGO_POSTCODES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NIYAGO_POSTCODES_PLUGIN_URL', plugin_dir_url(__FILE__));

class Niyago_Postcodes {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        // Admin settings
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // Frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Reorder checkout fields if enabled
        if ($this->get_option('reorder_fields', 'yes') === 'yes') {
            add_filter('woocommerce_checkout_fields', [$this, 'reorder_checkout_fields'], 99);
            add_filter('woocommerce_default_address_fields', [$this, 'reorder_default_address_fields'], 99);
        }

        // Declare HPOS compatibility
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
    }

    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Niyago Postcodes needs WooCommerce to work.', 'niyago-postcodes' ); ?></p>
        </div>
        <?php
    }

    /**
     * Get plugin option
     */
    public function get_option($key, $default = '') {
        $options = get_option('niyago_postcodes_options', []);
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Postcodes',
            'Postcodes',
            'manage_woocommerce',
            'niyago-postcodes',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('niyago_postcodes_options', 'niyago_postcodes_options', [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);

        add_settings_section(
            'niyago_postcodes_main',
            'Settings',
            null,
            'niyago-postcodes'
        );

        add_settings_field(
            'reorder_fields',
            'Field Order',
            [$this, 'render_reorder_field'],
            'niyago-postcodes',
            'niyago_postcodes_main'
        );

        add_settings_field(
            'enabled_countries',
            'Countries',
            [$this, 'render_countries_field'],
            'niyago-postcodes',
            'niyago_postcodes_main'
        );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = [];
        $sanitized['reorder_fields'] = isset($input['reorder_fields']) ? 'yes' : 'no';
        $sanitized['enabled_countries'] = isset($input['enabled_countries']) && is_array($input['enabled_countries'])
            ? array_map('sanitize_text_field', $input['enabled_countries'])
            : ['MY'];
        return $sanitized;
    }

    /**
     * Render reorder field setting
     */
    public function render_reorder_field() {
        $value = $this->get_option('reorder_fields', 'yes');
        ?>
        <label>
            <input type="checkbox" name="niyago_postcodes_options[reorder_fields]" value="yes" <?php checked($value, 'yes'); ?>>
            <?php esc_html_e( 'Postcode -> City -> State (default is City -> State -> Postcode)', 'niyago-postcodes' ); ?>
        </label>
        <?php
    }

    /**
     * Render countries field setting
     */
    public function render_countries_field() {
        $value = $this->get_option('enabled_countries', ['MY']);
        $available_countries = $this->get_available_countries();
        ?>
        <fieldset>
            <?php foreach ($available_countries as $code => $name): ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="niyago_postcodes_options[enabled_countries][]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, (array)$value)); ?>>
                <?php echo esc_html($name); ?>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    /**
     * Get available countries (based on data files)
     */
    private function get_available_countries() {
        $countries = [];
        $data_dir = NIYAGO_POSTCODES_PLUGIN_DIR . 'assets/data/';

        if (is_dir($data_dir)) {
            $files = glob($data_dir . '*.json');
            foreach ($files as $file) {
                $code = strtoupper(basename($file, '.json'));
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['country'])) {
                    // Get country name from WooCommerce
                    $wc_countries = WC()->countries->get_countries();
                    $countries[$code] = isset($wc_countries[$code]) ? $wc_countries[$code] : $code;
                }
            }
        }

        return $countries;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('niyago_postcodes_options');
                do_settings_sections('niyago-postcodes');
                submit_button('Save');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (!is_checkout() && !is_account_page()) {
            return;
        }

        wp_enqueue_style(
            'niyago-postcodes',
            NIYAGO_POSTCODES_PLUGIN_URL . 'assets/css/niyago-postcodes.css',
            [],
            NIYAGO_POSTCODES_VERSION
        );

        wp_enqueue_script(
            'niyago-postcodes',
            NIYAGO_POSTCODES_PLUGIN_URL . 'assets/js/niyago-postcodes.js',
            ['jquery'],
            NIYAGO_POSTCODES_VERSION,
            true
        );

        wp_localize_script('niyago-postcodes', 'niyagoPostcodesConfig', [
            'pluginUrl' => NIYAGO_POSTCODES_PLUGIN_URL,
            'enabledCountries' => $this->get_option('enabled_countries', ['MY']),
        ]);
    }

    /**
     * Reorder checkout fields - put postcode before city and state
     */
    public function reorder_checkout_fields($fields) {
        // Billing fields
        if (isset($fields['billing'])) {
            $fields['billing'] = $this->reorder_address_group($fields['billing'], 'billing');
        }

        // Shipping fields
        if (isset($fields['shipping'])) {
            $fields['shipping'] = $this->reorder_address_group($fields['shipping'], 'shipping');
        }

        return $fields;
    }

    /**
     * Reorder default address fields (used in My Account)
     */
    public function reorder_default_address_fields($fields) {
        // New order: country, postcode, city, state
        $priorities = [
            'country'    => 40,
            'postcode'   => 65,
            'city'       => 70,
            'state'      => 80,
        ];

        foreach ($priorities as $key => $priority) {
            if (isset($fields[$key])) {
                $fields[$key]['priority'] = $priority;
            }
        }

        return $fields;
    }

    /**
     * Reorder address fields within a group
     */
    private function reorder_address_group($fields, $prefix) {
        // Set priorities: postcode before city and state
        $priority_map = [
            $prefix . '_postcode' => 65,
            $prefix . '_city'     => 70,
            $prefix . '_state'    => 80,
        ];

        foreach ($priority_map as $key => $priority) {
            if (isset($fields[$key])) {
                $fields[$key]['priority'] = $priority;
            }
        }

        return $fields;
    }
}

// Initialize plugin
Niyago_Postcodes::instance();
