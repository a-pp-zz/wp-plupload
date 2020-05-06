<?php
/*
  Plugin Name: WP Plupload
  Description: Pluploader Admin Handler
  Author: CoolSwitcher
  Version: 1.6.1
  License: MIT
*/

require_once __DIR__ . '/Autoloader.php';
\WP_Plupload\Autoloader::register();

add_action('plugins_loaded', function () {
	\WP_Plupload\Uploader::factory();
});

if ( ! function_exists ('wp_plupload')) :
    function wp_plupload (array $params = array ()) {
        return \WP_Plupload\Uploader::insert_file ($params);
    }
endif;
