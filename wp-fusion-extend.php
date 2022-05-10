<?php
/**
 * Plugin Name: WP Fusion Extend
 * Plugin URI: 
 * Description: WP Fusion Extend
 * Author: wpwebmaster0313
 * Version: 1.0.0
 * Text Domain: wp-fusion-extend
 * Domain Path: /languages
 * Author URI: 
 *
 * @package  wp-fusion-extend
 */
defined( 'ABSPATH' ) || exit;
    
define( 'FX_VERSION', '1.0.0' );
define( 'FX_PATH', dirname( __FILE__ ) );

require_once FX_PATH . '/inc/class-fx-settings.php';

FX_Settings::factory();
