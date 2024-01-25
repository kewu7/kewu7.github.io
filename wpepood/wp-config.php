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
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'mento' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost:8889' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         '>Z+wmE;t_+=q6btT&T3aEV(|;RBN(>Ld^N4xH=yQQ/Dl~(@e9x<qSHy|f1_a&Vk)' );
define( 'SECURE_AUTH_KEY',  'GG^etNl>|]=X%-4Q[4g_IJMdktAHdJt5f&~)puWqg%1Vsg9Qwa18XSLh.B?ga8-,' );
define( 'LOGGED_IN_KEY',    'p)euTq<I<e*Q{e26Q/z<wV^7xYRWKyRNgxG3*Z%vfM)92:+lDfYsLQAA8p{;:[3L' );
define( 'NONCE_KEY',        '6=S&5>|7[%?fnT$[>ZvlHfmN5%a3U4I?0P<c?!+uSy&uxa_TIvgs8PEVUloLNFc6' );
define( 'AUTH_SALT',        'jH.1pRJycAHM*:N5h8>7$I;qBR@p!7rsXfc +iqvC?5nPe1EZ^_In4)A.T,y(1`B' );
define( 'SECURE_AUTH_SALT', 'fPKq}qvOzOPb<5hBh(E=cy@Ny4w%si6ho!^d~Wuu1f {mYg$8se9s~A)HsT*$/*z' );
define( 'LOGGED_IN_SALT',   '~`P5Yst4>=q7^Y&w+yZd+jN>nDM$U}Ma<ALcx-;jT*:G>QjepGlRZZNT5q<CJ/S?' );
define( 'NONCE_SALT',       '[N[/&c.ln|Fbv>ib:by_j(m=3[@+Lns]zPT{?dM qR#))dWeGwyRK06IuBSHaVCu' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
