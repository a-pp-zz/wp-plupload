<?php
/*
  Plugin Name: WP Plupload
  Description: Pluploader Admin Handler
  Author: CoolSwitcher
  Version: 1.1
  License: GPL
*/

require_once __DIR__ . '/Autoloader.php';
\WP_Plupload\Autoloader::register();

add_action('plugins_loaded', function () {
	\WP_Plupload\Uploader::factory();
});