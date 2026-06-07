<?php
// Core loader + central registry for modules.

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

require_once WP_SECURED_DIR . 'includes/core/class-hardening.php';
require_once WP_SECURED_DIR . 'includes/core/class-scoring.php';
require_once WP_SECURED_DIR . 'includes/core/class-dashboard.php';
require_once WP_SECURED_DIR . 'includes/helpers/utils.php';

class WP_Secured_Loader {
    private static $instance = null;

    /** @var WP_Secured_Module[] */
    private $modules = [];

    /** @var WP_Secured_Scoring */
    private $scoring;

    /** @var WP_Secured_Dashboard */
    private $dashboard;

    private function __construct() {}

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        $this->scoring = new WP_Secured_Scoring( $this );
        $this->dashboard = new WP_Secured_Dashboard( $this, $this->scoring );

        $this->load_helpers();
        $this->load_modules();
        $this->register_admin();
        // Allow modules to register hooks (each module can add its own hooks when active).
        foreach ( $this->modules as $module ) {
            $module->maybe_register_hooks();
            // scoring registers contributors automatically
            $this->scoring->register_contributor( $module );
        }
    }

    private function load_helpers() {
        // Already required above; left for future helpers.
    }

    private function load_modules() {
        $modules_dir = WP_SECURED_DIR . 'includes/modules/';
        if ( ! is_dir( $modules_dir ) ) {
            return;
        }

        foreach ( glob( $modules_dir . '*.php' ) as $file ) {
            // Each module file should return an instance of WP_Secured_Module
            $module = include $file;
            if ( $module instanceof WP_Secured_Module ) {
                $this->register_module( $module );
            }
        }
    }

    public function register_module( WP_Secured_Module $module ) {
        $this->modules[ $module->get_id() ] = $module;
    }

    /**
     * @return WP_Secured_Module[]
     */
    public function get_modules() {
        return $this->modules;
    }

    /**
     * @return WP_Secured_Module[]
     */
    public function get_active_modules() {
        $active = [];
        foreach ( $this->modules as $mod ) {
            if ( $mod->is_active() ) {
                $active[ $mod->get_id() ] = $mod;
            }
        }
        return $active;
    }

    public function get_scoring() {
        return $this->scoring;
    }

    public function get_dashboard() {
        return $this->dashboard;
    }

    private function register_admin() {
        require_once WP_SECURED_DIR . 'admin/admin-page.php';
        // admin-page will call functions that use this loader via WP_Secured_Loader::get_instance()
    }
}