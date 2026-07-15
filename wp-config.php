<?php
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
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',          '[G%Wdfy.&Y@Y7S@F]Qrc@#emN`[`CY,U!7MTNc?oAnzXNr&9Yg|?Z<fYM&X}c>As' );
define( 'SECURE_AUTH_KEY',   ',=a|OhHs3] <<M|ygR9r}K/?d$wG4H^?KQAKeB^8rT[AxJQ}kSiMk$}tF04^ mx-' );
define( 'LOGGED_IN_KEY',     '%n7gPLr)8Q>m;&2<!p#a{Qf/D&DuiM>8~kcQ5E*;;]t/b(7h ,D!)[Dt^q_a=T9u' );
define( 'NONCE_KEY',         'C_I@0-x|R/( }-!.8PiIXR5ks%$|{-:nsY$iZ$kHe=U_~_B!]rAoh{;z!84R,BJ@' );
define( 'AUTH_SALT',         '@S:# Wp/25Fnh4|cco,/q`DHj0Q.[IJo&c9id8+bT;6wUZ=:6hA 9$wzSw#qZ,@N' );
define( 'SECURE_AUTH_SALT',  '+5vaBR6zw?,fDI#!I@gWml<,wPQdtmE>r8DY,Wh[!%93.e3^q:PT$3h)</]T#A*.' );
define( 'LOGGED_IN_SALT',    '8TN/ Q GcZHq-Dm[c8Zo(x{T2M2liAL,hMh#@Wbu+pRO&tC.Bppd,.z0^Jx8W@ c' );
define( 'NONCE_SALT',        '<(*([HURPd7kXT)TQrH& &YMP%zCn-D#Tld(?SXTxhf!~3dQTFO`<x${0#63Fs*9' );
define( 'WP_CACHE_KEY_SALT', 'uSc%d7H%)Gl=k~JE5%+-C|l-J>!Dt2X&NzAX$}MRHeJ%pWa&di}5]K14TR#;xEC-' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
