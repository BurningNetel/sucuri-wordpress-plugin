<?php
/*
Plugin Name: Sucuri Security - Auditing, Malware Scanner and Hardening
Plugin URI: https://wordpress.sucuri.net/
Description: The <a href="https://sucuri.net/" target="_blank">Sucuri</a> plugin provides the website owner the best Activity Auditing, SiteCheck Remote Malware Scanning, Effective Security Hardening and Post-Hack features. SiteCheck will check for malware, spam, blacklisting and other security issues like .htaccess redirects, hidden eval code, etc. The best thing about it is it's completely free.
Author: Sucuri, INC
Version: 1.8.3
Author URI: https://sucuri.net
*/


/**
 * Main file to control the plugin.
 *
 * The constant will be used in the additional PHP files to determine if the
 * code is being called from a legitimate interface or not. It is expected that
 * during the direct access of any of the extra PHP files the interpreter will
 * return a 403/Forbidden response and immediately exit the execution, this will
 * prevent unwanted access to code with unmet dependencies.
 *
 * @package   Sucuri Security
 * @author    Daniel Cid   <dcid@sucuri.net>
 * @copyright Since 2010-2015 Sucuri Inc.
 * @license   Released under the GPL - see LICENSE file for details.
 * @link      https://wordpress.sucuri.net/
 * @since     File available since Release 0.1
 */
define('SUCURISCAN_INIT', true);

/**
 * Plugin dependencies.
 *
 * List of required functions for the execution of this plugin, we are assuming
 * that this site was built on top of the WordPress project, and that it is
 * being loaded through a pluggable system, these functions most be defined
 * before to continue.
 *
 * @var array
 */
$sucuriscan_dependencies = array(
    'wp',
    'wp_die',
    'add_action',
    'remove_action',
    'wp_remote_get',
    'wp_remote_post',
);

// Terminate execution if any of the functions mentioned above is not defined.
foreach ($sucuriscan_dependencies as $dependency) {
    if (!function_exists($dependency)) {
        exit(0);
    }
}

/**
 * Plugin's constants.
 *
 * These constants will hold the basic information of the plugin, file/folder
 * paths, version numbers, read-only variables that will affect the functioning
 * of the rest of the code. The conditional will act as a container helping in
 * the readability of the code considering the total number of lines that this
 * file will have.
 */

/**
 * Unique name of the plugin through out all the code.
 */
define('SUCURISCAN', 'sucuriscan');

/**
 * Current version of the plugin's code.
 */
define('SUCURISCAN_VERSION', '1.8.3');

/**
 * The name of the Sucuri plugin main file.
 */
define('SUCURISCAN_PLUGIN_FILE', 'sucuri.php');

/**
 * The name of the folder where the plugin's files will be located.
 *
 * Note that we are using the constant FILE instead of DIR because some
 * installations of PHP are either outdated or are not supporting the access to
 * that definition, to keep things simple we will select the name of the
 * directory name of the current file, then select the base name of that
 * directory.
 */
define('SUCURISCAN_PLUGIN_FOLDER', basename(dirname(__FILE__)));

/**
 * The fullpath where the plugin's files will be located.
 */
define('SUCURISCAN_PLUGIN_PATH', WP_PLUGIN_DIR.'/'.SUCURISCAN_PLUGIN_FOLDER);

/**
 * The fullpath of the main plugin file.
 */
define('SUCURISCAN_PLUGIN_FILEPATH', SUCURISCAN_PLUGIN_PATH.'/'.SUCURISCAN_PLUGIN_FILE);

/**
 * The local URL where the plugin's files and assets are served.
 */
define('SUCURISCAN_URL', rtrim(plugin_dir_url(SUCURISCAN_PLUGIN_FILEPATH), '/'));

/**
 * Checksum of this file to check the integrity of the plugin.
 */
define('SUCURISCAN_PLUGIN_CHECKSUM', @md5_file(SUCURISCAN_PLUGIN_FILEPATH));

/**
 * Remote URL where the public Sucuri API service is running.
 */
define('SUCURISCAN_API', 'sucuri://wordpress.sucuri.net/api/');

/**
 * Latest version of the public Sucuri API.
 */
define('SUCURISCAN_API_VERSION', 'v1');

/**
 * Remote URL where the CloudProxy API service is running.
 */
define('SUCURISCAN_CLOUDPROXY_API', 'sucuri://waf.sucuri.net/api');

/**
 * Latest version of the CloudProxy API.
 */
define('SUCURISCAN_CLOUDPROXY_API_VERSION', 'v2');

/**
 * The maximum quantity of entries that will be displayed in the last login page.
 */
define('SUCURISCAN_LASTLOGINS_USERSLIMIT', 25);

/**
 * The maximum quantity of entries that will be displayed in the audit logs page.
 */
define('SUCURISCAN_AUDITLOGS_PER_PAGE', 50);

/**
 * The maximum quantity of buttons in the paginations.
 */
define('SUCURISCAN_MAX_PAGINATION_BUTTONS', 20);

/**
 * The minimum quantity of seconds to wait before each filesystem scan.
 */
define('SUCURISCAN_MINIMUM_RUNTIME', 10800);

/**
 * The life time of the cache for the results of the SiteCheck scans.
 */
define('SUCURISCAN_SITECHECK_LIFETIME', 1200);

/**
 * The life time of the cache for the results of the get_plugins function.
 */
define('SUCURISCAN_GET_PLUGINS_LIFETIME', 1800);

/**
 * The maximum execution time of a HTTP request before timeout.
 */
define('SUCURISCAN_MAX_REQUEST_TIMEOUT', 15);

/**
 * The maximum execution time for SiteCheck requests before timeout.
 */
define('SUCURISCAN_MAX_SITECHECK_TIMEOUT', 60);

/* Load all classes before anything else. */
require_once('src/sucuriscan.lib.php');
require_once('src/request.lib.php');
require_once('src/fileinfo.lib.php');
require_once('src/cache.lib.php');
require_once('src/option.lib.php');
require_once('src/event.lib.php');
require_once('src/hook.lib.php');
require_once('src/api.lib.php');
require_once('src/mail.lib.php');
require_once('src/template.lib.php');
require_once('src/fsscanner.lib.php');
require_once('src/heartbeat.lib.php');
require_once('src/hardening.lib.php');
require_once('src/interface.lib.php');

/* Load global variables and triggers */
require_once('src/globals.php');

/* Load handlers for main pages. */
require_once('src/modfiles.php');
require_once('src/sitecheck.php');
require_once('src/firewall.php');
require_once('src/hardening.php');
require_once('src/homepage.php');
require_once('src/auditlogs.php');
require_once('src/outdated.php');
require_once('src/corefiles.php');
require_once('src/posthack.php');

/* Load handlers for main pages (lastlogins). */
require_once('src/lastlogins.php');
require_once('src/lastlogins-loggedin.php');
require_once('src/lastlogins-failed.php');
require_once('src/lastlogins-blocked.php');

/* Load handlers for main pages (settings). */
require_once('src/settings.php');
require_once('src/settings-handler.php');
require_once('src/settings-general.php');
require_once('src/settings-scanner.php');
require_once('src/settings-corefiles.php');
require_once('src/settings-sitecheck.php');
require_once('src/settings-ignorescan.php');
require_once('src/settings-alerts.php');
require_once('src/settings-ignorealerts.php');
require_once('src/settings-apiservice.php');
require_once('src/settings-selfhosting.php');
require_once('src/settings-trustip.php');
require_once('src/settings-heartbeat.php');

/* Load handlers for main pages (infosys). */
require_once('src/infosys.php');
