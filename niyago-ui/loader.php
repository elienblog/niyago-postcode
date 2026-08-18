<?php
/**
 * Niyago UI — bundled loader.
 *
 * Every Niyago plugin ships its own copy of this library and includes this file.
 * Whichever copy is newest is the one that runs, so the plugins stay
 * independently installable: no "install Niyago Core first", nothing to break
 * when a customer deactivates one plugin.
 *
 * Same approach Action Scheduler uses to ship inside dozens of plugins at once.
 *
 * Usage, near the top of the main plugin file:
 *
 *     require_once __DIR__ . '/niyago-ui/loader.php';
 *
 * @package NiyagoUI
 */

defined('ABSPATH') || exit;

/**
 * Register this copy. Keyed by version so the newest wins regardless of which
 * plugin loaded first — plugin load order is alphabetical and not something we
 * control.
 */
global $niyago_ui_versions;

if (!is_array($niyago_ui_versions)) {
    $niyago_ui_versions = [];
}

$niyago_ui_versions['1.2.0'] = __DIR__ . '/class-niyago-ui.php';

// Only the first copy to be included defines the boot function; the rest just
// add themselves to the list above.
if (!function_exists('niyago_ui_boot')) {

    /**
     * Load the newest registered copy, once, before any plugin renders admin UI.
     */
    function niyago_ui_boot(): void {
        global $niyago_ui_versions;

        if (empty($niyago_ui_versions)) {
            return;
        }

        uksort($niyago_ui_versions, 'version_compare');

        $newest = end($niyago_ui_versions);

        if (is_string($newest) && file_exists($newest)) {
            require_once $newest;
        }
    }

    // Priority 0: plugins register their admin pages on admin_menu, which fires
    // later, but a plugin may also want the class during its own plugins_loaded.
    add_action('plugins_loaded', 'niyago_ui_boot', 0);
}
