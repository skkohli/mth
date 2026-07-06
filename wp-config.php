<?php
if ( ! function_exists( 'mth_env' ) ) {
	function mth_env( $key, $default = null ) {
		$value = getenv( $key );

		return false === $value ? $default : $value;
	}
}

define( 'WP_CACHE', filter_var( mth_env( 'WP_CACHE', 'true' ), FILTER_VALIDATE_BOOLEAN ) );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', mth_env( 'WORDPRESS_DB_NAME', mth_env( 'DB_NAME', 'u813440994_J22Y4' ) ) );

/** Database username */
define( 'DB_USER', mth_env( 'WORDPRESS_DB_USER', mth_env( 'DB_USER', 'u813440994_P1Nbv' ) ) );

/** Database password */
define( 'DB_PASSWORD', mth_env( 'WORDPRESS_DB_PASSWORD', mth_env( 'DB_PASSWORD', '8rxzxMvl0O' ) ) );

/** Database hostname */
define( 'DB_HOST', mth_env( 'WORDPRESS_DB_HOST', mth_env( 'DB_HOST', '127.0.0.1' ) ) );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'R)]>CcNAWsfU;JP8SmS.4KGI^iz!W!(k}?1xM5Mky[at<0TK9ZV[NhlaXlYOv4l*' );
define( 'SECURE_AUTH_KEY',   '`=2oDA;`B]S$l8~SY]?_PK^UO8s.F`I>i0869Yd(.EBKdpbDB~mUK{@:y5<Q:*Sa' );
define( 'LOGGED_IN_KEY',     '#E>c5@GofwGb=vV]X*)0=jP`Slp)>CpV^iS2lr^wTo>|5jl$4c$=/QQ;sYBzu?m`' );
define( 'NONCE_KEY',         'J@d__zNg?glq*W5b-fd$W;IPCpUR%T]{nTimX/VqFNYtU-E0bkfxe+kSD2B C/5y' );
define( 'AUTH_SALT',         '%,HTy>*fK7R)35iNGt;#dltE_,_c.)8$WMX!1d_~v<bw&!j17k6*2+A18!1g.`PU' );
define( 'SECURE_AUTH_SALT',  'g}N8)/n bJUwwo@m<<Z?R;fB}%3s^7&uUAfYi*Vh`ms11j1 @(7h+9R3]0k0-@d%' );
define( 'LOGGED_IN_SALT',    'CDZ`BgR;,VxBb9OU)Lq3p>z091;_KO.|={pm<v /TJ/v1yGwqfqv6*DUB@#C^hhx' );
define( 'NONCE_SALT',        '=3O^03dEjIIjO{$MGN6CLrG/8[1uX10d4HDROR=x>/xe0V9H>oJWn*},&; !o`HZ' );
define( 'WP_CACHE_KEY_SALT', '/;+N7Tz:C[uPwyjF&v>M.oud]|y@-do7e.Nb^+<qjS:3e=XSLq@kE3?1exBc5f%=' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */

if ( mth_env( 'WP_HOME' ) ) {
	define( 'WP_HOME', mth_env( 'WP_HOME' ) );
}

if ( mth_env( 'WP_SITEURL' ) ) {
	define( 'WP_SITEURL', mth_env( 'WP_SITEURL' ) );
}

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', filter_var( mth_env( 'WP_DEBUG', 'false' ), FILTER_VALIDATE_BOOLEAN ) );
}
@ini_set( 'display_errors', 0 );

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '5c1c5dce7993c3b396703869483d6770' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );

// WordPress Heartbeat API Settings
define( 'WP_HEARTBEAT_INTERVAL', 360 );


// WordPress Memory Limits
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');


// Debug Mode Settings
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
