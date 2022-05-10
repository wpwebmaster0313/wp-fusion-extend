<?php
/**
 * Settings class
 * 
 * @package wp-fusion-extend
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class containing settings hooks
 */
class FX_Settings {

    /**
     * Setup the plugin
     * 
     * @since 1.0
     */
    public function setup() {
        
        add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts_styles' ) );

        require_once FX_PATH . '/includes/integrations/class-modern-events-calendar-extend.php';
        Modern_Events_Calendar_Extend::factory();

    }

    /**
     * Enqueue wpf settings screen js/css 
     * 
     * @since 1.0
     */
    public function action_admin_enqueue_scripts_styles() {

        global $pagenow;

        if ( ( 'options-general.php' === $pagenow || 'settings.php' === $pagenow ) && ! empty( $_GET['page'] ) && 'wpf-settings' === $_GET['page'] ) {
            wp_enqueue_style( 'fx-settings-style', plugins_url( 'assets/css/style.css', dirname( __FILE__ ) ), array(), FX_VERSION, true );
            wp_enqueue_script( 'fx-settings-script', plugins_url( 'assets/js/script.js', dirname( __FILE__ ) ), array( 'jquery' ), FX_VERSION, true );
        }
    }

    /**
     * Return an instance of the current class, create one if it doesn't exist
     * 
     * @since 1.0
     */
    public static function factory() {

        static $instance;

        if ( ! $instance ) {
            $instance = new self();
            $instance->setup();
        }

        return $instance;
    }
}
