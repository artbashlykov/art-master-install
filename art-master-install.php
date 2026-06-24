<?php
/**
 * Plugin Name:       ART Master Install
 * Description:       Мастер установщик плагинов, тем и дополнения Арта в один клик
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Арт Башлыков
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       art-master-install
 * Domain Path:       /languages
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

define( 'ART_MASTER_INSTALL_VERSION', '1.4.0' );
define( 'ART_MASTER_INSTALL_ADMIN_MENU_SLUG', 'art-master-install' );
define( 'ART_MASTER_INSTALL_AUTHOR_URL', 'https://forge.artbashlykov.ru' );
define( 'ART_MASTER_INSTALL_PLUGIN_FILE', __FILE__ );
define( 'ART_MASTER_INSTALL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ART_MASTER_INSTALL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ART_MASTER_INSTALL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-activator.php';
require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( ART_MASTER_INSTALL_PLUGIN_FILE, array( 'Art_Master_Install_Activator', 'activate' ) );
register_deactivation_hook( ART_MASTER_INSTALL_PLUGIN_FILE, array( 'Art_Master_Install_Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return Art_Master_Install_Plugin
 */
function art_master_install() {
	return Art_Master_Install_Plugin::instance();
}

art_master_install()->run();
