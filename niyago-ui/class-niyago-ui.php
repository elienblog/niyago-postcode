<?php
/**
 * Niyago UI — shared admin components for the Niyago plugin suite.
 *
 * These deliberately emit WordPress' and WooCommerce's own markup rather than a
 * house style: .form-table rows with th.titledesc/td.forminp, core .button
 * classes, core .notice, core .nav-tab. A WooCommerce shop owner should not have
 * to learn a new set of controls to change a setting, and native markup keeps
 * the accessibility behaviour and the user's chosen admin colour scheme for free.
 *
 * What little CSS ships alongside is for the few things core has no equivalent
 * for, and it is written against --wp-admin-theme-color and WooCommerce's own
 * radius tokens so it follows the host rather than fighting it.
 *
 * Loaded via loader.php, which picks the newest bundled copy.
 *
 * @package NiyagoUI
 */

defined('ABSPATH') || exit;

if (class_exists('Niyago_UI')) {
    return;
}

class Niyago_UI {

    const VERSION = '1.2.1';

    /**
     * Top-level menu slug shared by the whole suite.
     */
    const MENU_SLUG = 'niyago';

    /**
     * @var array<int, array<string, mixed>>
     */
    private static $pages = [];

    /**
     * @var bool
     */
    private static $menu_added = false;

    /**
     * Register a plugin's settings page under the shared "Niyago" menu.
     *
     * The menu is created on demand by whichever plugin registers first, so each
     * plugin stays installable on its own.
     *
     * @param array $args slug, title, menu_title, callback, capability, position, plugin, version
     */
    public static function register_page(array $args): void {
        $args = wp_parse_args($args, [
            'slug' => '',
            'title' => '',
            'menu_title' => '',
            'callback' => null,
            'capability' => 'manage_options',
            'position' => 10,
            'plugin' => '',
            'version' => '',
        ]);

        if ($args['slug'] === '' || !is_callable($args['callback'])) {
            return;
        }

        if ($args['menu_title'] === '') {
            $args['menu_title'] = $args['title'];
        }

        // A slug can only appear once. Registering twice — a plugin calling this
        // directly as well as on the hook, or two copies of the same plugin —
        // would otherwise put the same page in the menu twice.
        foreach (self::$pages as $existing) {
            if ($existing['slug'] === $args['slug']) {
                return;
            }
        }

        self::$pages[] = $args;

        if (!has_action('admin_menu', [__CLASS__, 'build_menu'])) {
            add_action('admin_menu', [__CLASS__, 'build_menu'], 20);
        }

        if (!has_action('admin_enqueue_scripts', [__CLASS__, 'enqueue'])) {
            add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        }
    }

    /**
     * Create the shared menu and hang every registered page off it.
     */
    public static function build_menu(): void {
        if (self::$menu_added || empty(self::$pages)) {
            return;
        }

        self::$menu_added = true;

        usort(self::$pages, static function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        $first = self::$pages[0];

        // The parent slug renders whichever page sorts first, so ?page=niyago is
        // a working entry point. It is only an alias — every page is registered
        // under its own slug below.
        add_menu_page(
            __('Niyago', 'niyago-ui'),
            __('Niyago', 'niyago-ui'),
            'manage_options',
            self::MENU_SLUG,
            static function () use ($first) {
                self::render_page($first);
            },
            'dashicons-cart',
            56 // just under WooCommerce
        );

        // Every page keeps its own slug. Binding the first page to the parent
        // slug instead would make a page's URL depend on which plugin happened
        // to register first, breaking bookmarks and "Settings" links.
        foreach (self::$pages as $page) {
            add_submenu_page(
                self::MENU_SLUG,
                $page['title'],
                $page['menu_title'],
                $page['capability'],
                $page['slug'],
                static function () use ($page) {
                    self::render_page($page);
                }
            );
        }

        // WordPress adds a duplicate of the parent as the first submenu item.
        remove_submenu_page(self::MENU_SLUG, self::MENU_SLUG);
    }

    private static function render_page(array $page): void {
        self::page_open([
            'title' => $page['title'],
            'plugin' => $page['plugin'],
            'version' => $page['version'],
        ]);

        call_user_func($page['callback']);

        self::page_close();
    }

    /**
     * Load only on the suite's own screens.
     */
    public static function enqueue(string $hook): void {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        self::enqueue_assets();
    }

    /**
     * Load the kit's stylesheet on a screen that is not one of ours.
     *
     * A plugin rendering a dashboard widget needs these styles on index.php,
     * which the screen check above will never match. Safe to call repeatedly —
     * wp_enqueue_style ignores a handle that is already registered.
     */
    public static function enqueue_assets(): void {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style(
            'niyago-ui',
            plugin_dir_url(__FILE__) . 'assets/niyago-ui' . $suffix . '.css',
            [],
            self::VERSION
        );

        // WordPress' own colour picker, so a colour field behaves the way it
        // does everywhere else in the admin.
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script(
            'wp-color-picker',
            "jQuery(function($){ $('.niyago-colorpick').wpColorPicker(); });"
        );
    }

    // ============================================================
    // PAGE CHROME
    // ============================================================

    /**
     * Standard admin page wrapper. The <h1> stays where WordPress expects it,
     * because that is where core injects admin notices.
     */
    public static function page_open(array $args = []): void {
        $args = wp_parse_args($args, [
            'title' => '',
            'plugin' => '',
            'version' => '',
            'description' => '',
        ]);

        echo '<div class="wrap niyago-ui">';

        printf('<h1 class="wp-heading-inline">%s</h1>', esc_html($args['title']));

        if ($args['version'] !== '') {
            printf(
                '<span class="niyago-version" title="%s">%s</span>',
                esc_attr($args['plugin']),
                esc_html('v' . $args['version'])
            );
        }

        echo '<hr class="wp-header-end">';

        if ($args['description'] !== '') {
            printf('<p class="description niyago-page-desc">%s</p>', wp_kses_post($args['description']));
        }
    }

    public static function page_close(): void {
        echo '</div>';
    }

    /**
     * Core's tab strip — the same one used by WooCommerce settings.
     *
     * @param array<string, string> $tabs slug => label
     */
    public static function tabs(array $tabs, string $current, string $page_slug): void {
        if (count($tabs) < 2) {
            return;
        }

        echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';

        foreach ($tabs as $slug => $label) {
            printf(
                '<a class="nav-tab%s" href="%s">%s</a>',
                $slug === $current ? ' nav-tab-active' : '',
                esc_url(add_query_arg(['page' => $page_slug, 'tab' => $slug], admin_url('admin.php'))),
                esc_html($label)
            );
        }

        echo '</nav>';
    }

    // ============================================================
    // SECTIONS AND ROWS
    // ============================================================

    /**
     * A settings section: heading, optional description, then the form table —
     * exactly how WooCommerce lays out its own settings pages.
     */
    public static function section_open(string $title = '', string $description = ''): void {
        if ($title !== '') {
            printf('<h2>%s</h2>', esc_html($title));
        }

        if ($description !== '') {
            printf('<p>%s</p>', wp_kses_post($description));
        }

        echo '<table class="form-table" role="presentation"><tbody>';
    }

    public static function section_close(): void {
        echo '</tbody></table>';
    }

    /**
     * One settings row.
     *
     * @param string $type appended to forminp- as WooCommerce does (text, checkbox, select…)
     */
    public static function row_open(string $label, string $for = '', string $type = 'text'): void {
        echo '<tr valign="top">';
        printf(
            '<th scope="row" class="titledesc"><label for="%s">%s</label></th>',
            esc_attr($for),
            esc_html($label)
        );
        printf('<td class="forminp forminp-%s">', esc_attr($type));
    }

    public static function row_close(): void {
        echo '</td></tr>';
    }

    /**
     * Core's description paragraph — the same grey helper text used sitewide.
     */
    public static function help(string $text): void {
        printf('<p class="description">%s</p>', wp_kses_post($text));
    }

    // ============================================================
    // FIELDS
    // ============================================================

    /**
     * A checkbox in a fieldset with its label — WooCommerce's own pattern for a
     * boolean setting. Deliberately not a custom toggle: the point is that this
     * looks like every other WooCommerce checkbox.
     */
    public static function checkbox(string $name, bool $checked, string $label = '', string $value = '1'): void {
        printf(
            '<fieldset><label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="%3$s"%4$s> %5$s</label></fieldset>',
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            checked($checked, true, false),
            wp_kses_post($label)
        );
    }

    public static function text(string $name, string $value, array $args = []): void {
        $args = wp_parse_args($args, [
            'type' => 'text',
            'placeholder' => '',
            'id' => $name,
            'class' => 'regular-text', // core width class
            'attrs' => '',
        ]);

        printf(
            '<input type="%s" id="%s" name="%s" value="%s" placeholder="%s" class="%s" %s>',
            esc_attr($args['type']),
            esc_attr($args['id']),
            esc_attr($name),
            esc_attr($value),
            esc_attr($args['placeholder']),
            esc_attr($args['class']),
            $args['attrs']
        );
    }

    public static function textarea(string $name, string $value, array $args = []): void {
        $args = wp_parse_args($args, [
            'rows' => 6,
            'id' => $name,
            'placeholder' => '',
            'class' => 'large-text code', // core classes
        ]);

        printf(
            '<textarea id="%s" name="%s" rows="%d" placeholder="%s" class="%s">%s</textarea>',
            esc_attr($args['id']),
            esc_attr($name),
            (int) $args['rows'],
            esc_attr($args['placeholder']),
            esc_attr($args['class']),
            esc_textarea($value)
        );
    }

    /**
     * @param array<string, string> $options value => label
     */
    public static function select(string $name, string $value, array $options, array $args = []): void {
        $args = wp_parse_args($args, ['id' => $name, 'class' => '']);

        printf('<select id="%s" name="%s" class="%s">', esc_attr($args['id']), esc_attr($name), esc_attr($args['class']));

        foreach ($options as $key => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($key),
                selected((string) $key, (string) $value, false),
                esc_html($label)
            );
        }

        echo '</select>';
    }

    /**
     * WordPress' own colour picker (Iris), initialised in enqueue().
     */
    public static function color(string $name, string $value, string $fallback = '#25D366'): void {
        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="niyago-colorpick" data-default-color="%3$s">',
            esc_attr($name),
            esc_attr($value !== '' ? $value : $fallback),
            esc_attr($fallback)
        );
    }

    /**
     * Placeholder reference for message templates. No core equivalent.
     *
     * @param array<string, string> $tokens token => description
     */
    public static function token_list(array $tokens): void {
        echo '<ul class="niyago-tokens">';

        foreach ($tokens as $token => $description) {
            printf('<li><code>%s</code> <span>%s</span></li>', esc_html($token), esc_html($description));
        }

        echo '</ul>';
    }

    // ============================================================
    // FEEDBACK
    // ============================================================

    /**
     * Core's notice. "inline" keeps it in the flow rather than jumping to the
     * top of the screen, which is what core does when a notice is contextual.
     *
     * @param string $type info|success|warning|error
     */
    public static function notice(string $type, string $message): void {
        printf(
            '<div class="notice notice-%s inline"><p>%s</p></div>',
            esc_attr($type),
            wp_kses_post($message)
        );
    }

    /**
     * @param string $tone default|success|warning|danger|muted
     */
    public static function badge(string $text, string $tone = 'default'): void {
        printf(
            '<span class="niyago-badge niyago-badge--%s">%s</span>',
            esc_attr($tone),
            esc_html($text)
        );
    }

    // ============================================================
    // PANELS — for status dashboards, which core has no pattern for
    // ============================================================
    //
    // WordPress renders dashboards (Site Health, the dashboard widgets) with
    // custom layout rather than form-tables, so a metrics screen is legitimately
    // custom. What it should not be is custom *per plugin* — these live here so
    // every Niyago dashboard is the same dashboard.

    public static function panels_open(): void {
        echo '<div class="niyago-panels">';
    }

    public static function panels_close(): void {
        echo '</div>';
    }

    public static function panel_open(string $title = ''): void {
        echo '<section class="niyago-panel">';

        if ($title !== '') {
            printf('<h3 class="niyago-panel__title">%s</h3>', esc_html($title));
        }

        echo '<div class="niyago-panel__body">';
    }

    public static function panel_close(): void {
        echo '</div></section>';
    }

    /**
     * The headline number in a panel.
     *
     * @param string $tone default|success|warning|danger
     */
    public static function metric(string $value, string $label, string $tone = 'default'): void {
        printf(
            '<div class="niyago-metric niyago-metric--%s"><span class="niyago-metric__value">%s</span><span class="niyago-metric__label">%s</span></div>',
            esc_attr($tone),
            esc_html($value),
            esc_html($label)
        );
    }

    /**
     * A label/value line under a metric.
     */
    public static function metric_row(string $label, string $value): void {
        printf(
            '<div class="niyago-metric-row"><span>%s</span><strong>%s</strong></div>',
            esc_html($label),
            esc_html($value)
        );
    }

    /**
     * Simple bar chart, for hourly figures. Values are scaled against the
     * largest bar, so an empty series renders flat rather than dividing by zero.
     *
     * @param array<int, array{label: string, value: float, title?: string}> $bars
     */
    public static function bar_chart(array $bars): void {
        $max = 0.0;

        foreach ($bars as $bar) {
            $max = max($max, (float) $bar['value']);
        }

        echo '<div class="niyago-chart">';

        foreach ($bars as $bar) {
            $height = $max > 0 ? round(((float) $bar['value'] / $max) * 100) : 0;

            printf(
                '<div class="niyago-chart__col" title="%s"><div class="niyago-chart__bar" style="height:%d%%"></div><span class="niyago-chart__label">%s</span></div>',
                esc_attr($bar['title'] ?? ($bar['label'] . ': ' . $bar['value'])),
                (int) $height,
                esc_html($bar['label'])
            );
        }

        echo '</div>';
    }

    /**
     * Key figures — cache hit rate, orders routed. No core equivalent, so this
     * borrows core's .card look rather than inventing one.
     *
     * @param array<int, array{label: string, value: string, tone?: string}> $stats
     */
    public static function stats(array $stats): void {
        echo '<div class="niyago-stats">';

        foreach ($stats as $stat) {
            printf(
                '<div class="niyago-stat niyago-stat--%s"><span class="niyago-stat__value">%s</span><span class="niyago-stat__label">%s</span></div>',
                esc_attr($stat['tone'] ?? 'default'),
                esc_html($stat['value']),
                esc_html($stat['label'])
            );
        }

        echo '</div>';
    }

    // ============================================================
    // ACTIONS
    // ============================================================

    /**
     * Core button classes, so it is the same button the shop owner clicks
     * everywhere else.
     *
     * @param string $variant primary|secondary|danger
     */
    public static function button(string $text, array $args = []): void {
        $args = wp_parse_args($args, [
            'variant' => 'primary',
            'type' => 'button',
            'href' => '',
            'class' => '',
            'attrs' => '',
        ]);

        $variants = [
            'primary' => 'button button-primary',
            'secondary' => 'button',
            'danger' => 'button niyago-button-danger',
        ];

        $class = trim(($variants[$args['variant']] ?? $variants['secondary']) . ' ' . $args['class']);

        if ($args['href'] !== '') {
            printf(
                '<a href="%s" class="%s" %s>%s</a>',
                esc_url($args['href']),
                esc_attr($class),
                $args['attrs'],
                esc_html($text)
            );
            return;
        }

        printf(
            '<button type="%s" class="%s" %s>%s</button>',
            esc_attr($args['type']),
            esc_attr($class),
            $args['attrs'],
            esc_html($text)
        );
    }

    /**
     * Core's submit row.
     */
    public static function actions_open(): void {
        echo '<p class="submit niyago-actions">';
    }

    public static function actions_close(): void {
        echo '</p>';
    }
}
