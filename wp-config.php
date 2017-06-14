<?php
/** 
 * Configuración básica de WordPress.
 *
 * Este archivo contiene las siguientes configuraciones: ajustes de MySQL, prefijo de tablas,
 * claves secretas, idioma de WordPress y ABSPATH. Para obtener más información,
 * visita la página del Codex{@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} . Los ajustes de MySQL te los proporcionará tu proveedor de alojamiento web.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** Ajustes de MySQL. Solicita estos datos a tu proveedor de alojamiento web. ** //
/** El nombre de tu base de datos de WordPress */
define('DB_NAME', 'wordpress');

/** Tu nombre de usuario de MySQL */
define('DB_USER', 'root');

/** Tu contraseña de MySQL */
define('DB_PASSWORD', 'root');

/** Host de MySQL (es muy probable que no necesites cambiarlo) */
define('DB_HOST', 'localhost');

/** Codificación de caracteres para la base de datos. */
define('DB_CHARSET', 'utf8mb4');

/** Cotejamiento de la base de datos. No lo modifiques si tienes dudas. */
define('DB_COLLATE', '');

define('FS_METHOD', 'direct');


/**#@+
 * Claves únicas de autentificación.
 *
 * Define cada clave secreta con una frase aleatoria distinta.
 * Puedes generarlas usando el {@link https://api.wordpress.org/secret-key/1.1/salt/ servicio de claves secretas de WordPress}
 * Puedes cambiar las claves en cualquier momento para invalidar todas las cookies existentes. Esto forzará a todos los usuarios a volver a hacer login.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', '4knaK#Kh#ov(vBg_wV>LwZ^(@@jrF4*.<Y#!BQ$P^;>4y O9(wQyggICelJ8>s2f');
define('SECURE_AUTH_KEY', 'Oj?P<m`e:{9?6x)t4[VQfx7XL9$Hb6TGe?$_yh0v[y#7;?n[JF4rZk@7+Z-UJHkc');
define('LOGGED_IN_KEY', 'DR{sE8leOxQ83|`OQGb*^yp~m7QBAgt[4x|B1GCRM#We/#8u/&^uA{o6<KZY22J.');
define('NONCE_KEY', 'CWE5S0e*fpEvXb?@UIv|[K8a7LWo?8yWBwcw8SFCfYTBIU0Tq.7~U2jjS4 [90|s');
define('AUTH_SALT', '-yfO2q+W2A%AC?Bv24Cqf4sp|Wu@dLTp=FLuXm?F)3mezv?:+r4$rS&Q-n<vgXCO');
define('SECURE_AUTH_SALT', '3sDh]$1d;V%/a{$+qF.Oo9DAf&rCu23o}sk}23[2tEbPCF*h<fm_)_wk1W1l#:HJ');
define('LOGGED_IN_SALT', 'IF:9w$UM$_g[@jlSbhlm>hO]ZE@UwuAhudjh/f,8Xv8@2k,U7.:>5|Q[StP9aBX^');
define('NONCE_SALT', 'b+VX|_QaZ]F,b]k`Uym@AO{eK ~uH]1Y5KJlZGT9_ro+R:H9zrzk`t/ EFqiH]bZ');

/**#@-*/

/**
 * Prefijo de la base de datos de WordPress.
 *
 * Cambia el prefijo si deseas instalar multiples blogs en una sola base de datos.
 * Emplea solo números, letras y guión bajo.
 */
$table_prefix  = 'wp_';


/**
 * Para desarrolladores: modo debug de WordPress.
 *
 * Cambia esto a true para activar la muestra de avisos durante el desarrollo.
 * Se recomienda encarecidamente a los desarrolladores de temas y plugins que usen WP_DEBUG
 * en sus entornos de desarrollo.
 */
define('WP_DEBUG', false);

/* ¡Eso es todo, deja de editar! Feliz blogging */

/** WordPress absolute path to the Wordpress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

