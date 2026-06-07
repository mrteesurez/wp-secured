<?php
/**
 * Base class for all modules (hardening, UI, reporting).
 * Modules must return an instance at the end of their file.
 */

abstract class WP_Secured_Module {
    protected $id;
    protected $label;
    protected $description;
    protected $type = 'hardening'; // 'hardening'|'ui'|'scoring'|'reporting'
    protected $weight = 10; // contribution to score
    protected $default_active = false;

    public function __construct() {}

    // Identity
    public function get_id() {
        return $this->id;
    }
    public function get_label() {
        return $this->label;
    }
    public function get_description() {
        return $this->description;
    }
    public function get_type() {
        return $this->type;
    }
    public function get_weight() {
        return (int) $this->weight;
    }

    // Option storage
    protected function option_name() {
        return 'wp_secured_module_' . $this->id;
    }

    public function is_active() {
        $value = get_option( $this->option_name(), null );
        if ( null === $value ) {
            return (bool) $this->default_active;
        }
        return (bool) $value;
    }

    public function set_active( $active ) {
        update_option( $this->option_name(), (bool) $active );
    }

    /**
     * Called by loader after registration. Modules should use maybe_register_hooks to
     * attach WP filters/actions only when active.
     */
    public function maybe_register_hooks() {
        if ( $this->is_active() ) {
            $this->register_hooks();
        }
    }

    /**
     * Register runtime hooks (filters, actions) to apply protections.
     */
    abstract public function register_hooks();

    /**
     * Optional: revert protections when disabled. Not always possible for runtime-only toggles.
     */
    public function unregister_hooks() {
        // default: no-op
    }

    /**
     * Admin UI snippet for the admin page settings.
     * Return HTML string (escaped) or string of description; admin page wraps in label/checkbox.
     */
    public function admin_description() {
        return $this->get_description();
    }
}