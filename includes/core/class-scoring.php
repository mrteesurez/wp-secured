<?php
/**
 * includes/core/class-scoring.php
 *
 * Extended Security scoring engine for WP Secured.
 * - Maintains scoring rules (extensible)
 * - Maintains penalty rules (extensible)
 * - Calculates score (calculate_score)
 * - Provides visualization-friendly output (get_visualization_data)
 * - Hooks a small dashboard widget into WP admin
 *
 * Note: Penalties subtract from earned points (not from denominator). This is configurable
 * by adjusting how penalties are computed or by overriding calculate_score if you prefer
 * a different model.
 */

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

class WP_Secured_Scoring {
    /** @var WP_Secured_Loader */
    protected $loader;

    /** @var array rule_id => rule_def */
    protected $rules = [];

    /** @var array penalty_id => penalty_def */
    protected $penalties = [];

    /** @var int|null fixed denominator (optional) */
    protected $fixed_total = null;

    public function __construct( WP_Secured_Loader $loader ) {
        $this->loader = $loader;

        // register the default ruleset based on the specification
        $this->register_default_rules();

        // register a few conservative default penalties (examples — can be removed)
        $this->register_default_penalties();

        // Hook dashboard widget in admin
        if ( is_admin() ) {
            add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
        }
    }
    
    /**
 * Backwards-compatible: register a module as a contributor.
 *
 * The loader calls this for every module during init. We store the module
 * in the contributors map for compatibility with earlier code that used this list.
 *
 * @param WP_Secured_Module $module
 */
public function register_contributor( $module ) {
    if ( ! is_object( $module ) ) {
        return;
    }
    if ( method_exists( $module, 'get_id' ) ) {
        $id = (string) $module->get_id();
        if ( $id !== '' ) {
            $this->contributors[ $id ] = $module;
        }
    }
}

    /* -------------------------
     * RULES: positive scoring
     * ------------------------- */

    protected function register_default_rules() {
        // Critical (10)
        $this->register_rule( 'xmlrpc', 'XML-RPC disabled', 10, 'critical', 'xmlrpc' );
        $this->register_rule( 'rest_api', 'REST API disabled/restricted', 10, 'critical', 'rest_api' );
        $this->register_rule( 'php_execution', 'PHP execution in uploads disabled', 10, 'critical', 'disable_php_execution' );
        $this->register_rule( 'file_editor', 'File editor disabled', 10, 'critical', 'file_protection' );
        $this->register_rule( 'login_security', 'Login Security enabled', 10, 'critical', 'login_security' );
        $this->register_rule( 'file_protection', 'File Protection enabled', 10, 'critical', 'file_protection' );

        // High (8)
        $this->register_rule( 'app_passwords', 'Application Passwords disabled', 8, 'high', 'disable_app_passwords' );
        $this->register_rule( 'directory_browsing', 'Directory Browsing disabled', 8, 'high', 'disable_directory_browsing' );
        $this->register_rule( 'login_hints', 'Login Hints disabled', 8, 'high', 'disable_login_hints' );
        $this->register_rule( 'login_email', 'Login by Email disabled', 8, 'high', 'disable_login_email' );
        $this->register_rule( 'version_hidden', 'WordPress Version hidden', 8, 'high', 'disable_version' );

        // Medium (5)
        $this->register_rule( 'comments_disabled', 'Comments disabled', 5, 'medium', 'comments' );
        $this->register_rule( 'comment_url_removed', 'Comment URL field disabled', 5, 'medium', 'disable_comments_url' );
        $this->register_rule( 'rss_disabled', 'RSS feeds disabled', 5, 'medium', 'disable_rss' );
        $this->register_rule( 'trackbacks_disabled', 'Trackbacks disabled', 5, 'medium', 'disable_trackbacks' );
        $this->register_rule( 'self_pinging_disabled', 'Self-Pinging disabled', 5, 'medium', 'disable_self_pinging' );

        // Low (3)
        $this->register_rule( 'admin_bar_hidden', 'Admin Bar disabled', 3, 'low', 'disable_admin_bar' );
    }

    /**
     * Register a positive scoring rule.
     *
     * @param string $id
     * @param string $label
     * @param int $points
     * @param string $severity
     * @param string $module_id
     */
    public function register_rule( $id, $label, $points, $severity = 'medium', $module_id = '' ) {
        $this->rules[ (string) $id ] = [
            'id' => (string) $id,
            'label' => (string) $label,
            'points' => (int) $points,
            'severity' => (string) $severity,
            'module_id' => (string) $module_id,
        ];
    }

    public function unregister_rule( $id ) {
        unset( $this->rules[ (string) $id ] );
    }

    public function get_rules() {
        return $this->rules;
    }

    public function get_raw_total() {
        $sum = 0;
        foreach ( $this->rules as $r ) {
            $sum += (int) $r['points'];
        }
        return $sum;
    }

    /* -------------------------
     * PENALTIES: insecure settings
     * ------------------------- */

    /**
     * Register a penalty.
     *
     * $check can be:
     *  - callable():bool — returns true if penalty should apply
     *  - string (module_id) — shorthand to apply penalty when module is active (or inactive if you invert)
     *
     * Penalty definition:
     * - id, label, points (positive int), severity, check (callable or module_id), invert(bool)
     *
     * @param string $id
     * @param string $label
     * @param int $points points to subtract if penalty applies
     * @param string $severity
     * @param callable|string $check
     * @param bool $invert If check is module id, invert=false means penalty applies when module is active. If invert=true penalty applies when module NOT active.
     */
    public function register_penalty( $id, $label, $points, $severity = 'high', $check = null, $invert = false ) {
        $this->penalties[ (string) $id ] = [
            'id' => (string) $id,
            'label' => (string) $label,
            'points' => max( 0, (int) $points ),
            'severity' => (string) $severity,
            'check' => $check,
            'invert' => (bool) $invert,
        ];
    }

    public function unregister_penalty( $id ) {
        unset( $this->penalties[ (string) $id ] );
    }

    public function get_penalties() {
        return $this->penalties;
    }

    protected function register_default_penalties() {
        // Example: penalize DEBUG mode
        $this->register_penalty(
            'debug_mode',
            'WP_DEBUG enabled',
            10,
            'critical',
            function () {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    return true;
                }
                return false;
            }
        );

        // Penalty if XML-RPC is available (opposite of disabling rule) — applies when xmlrpc module is NOT active
        $this->register_penalty(
            'xmlrpc_enabled',
            'XML-RPC is enabled',
            10,
            'critical',
            'xmlrpc',
            true // invert: apply penalty when module xmlrpc is not active/disabled protection
        );

        // Penalty if file editor is allowed (DISALLOW_FILE_EDIT not true)
        $this->register_penalty(
            'file_editor_allowed',
            'File editor is enabled',
            10,
            'critical',
            function () {
                if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
                    return false;
                }
                // If not defined or false -> penalty applies
                return true;
            }
        );

        // Penalty if application passwords are available
        $this->register_penalty(
            'app_passwords_enabled',
            'Application Passwords enabled',
            8,
            'high',
            function () {
                if ( function_exists( 'wp_is_application_passwords_available' ) ) {
                    return (bool) wp_is_application_passwords_available();
                }
                return false;
            }
        );
    }

    /* -------------------------
     * CALCULATION
     * ------------------------- */

    /**
     * Evaluate penalty check (internal).
     *
     * @param array $penaltyDef
     * @return bool
     */
    protected function penalty_applies( $penaltyDef ) {
        $check = $penaltyDef['check'];
        $invert = ! empty( $penaltyDef['invert'] );

        // If check is callable
        if ( is_callable( $check ) ) {
            $applies = (bool) call_user_func( $check );
            return $invert ? ! $applies : $applies;
        }

        // If check is a module ID string
        if ( is_string( $check ) && $check !== '' ) {
            $modules = $this->loader->get_modules();
            $module_id = $check;
            $applies = false;
            if ( isset( $modules[ $module_id ] ) ) {
                $applies = (bool) $modules[ $module_id ]->is_active();
            }
            return $invert ? ! $applies : $applies;
        }

        // No check means penalty cannot be evaluated -> no penalty
        return false;
    }

    /**
     * Central calculation method.
     *
     * Returns:
     * - score (0..100 int)
     * - earned (int)
     * - penalties_total (int)
     * - possible (denominator used)
     * - raw_possible (sum of rule points)
     * - risk (string)
     * - breakdown (per-rule)
     * - penalties (per-penalty)
     *
     * @return array
     */
    public function calculate_score() {
        $modules = $this->loader->get_modules();
        $earned = 0;
        $breakdown = [];
        $raw_possible = 0;

        foreach ( $this->rules as $rule_id => $r ) {
            $raw_possible += (int) $r['points'];

            $module_id = $r['module_id'];
            $active = false;

            if ( ! empty( $module_id ) && isset( $modules[ $module_id ] ) ) {
                $active = (bool) $modules[ $module_id ]->is_active();
            } else {
                $active = false;
            }

            $earned_points = $active ? (int) $r['points'] : 0;

            $breakdown[ $rule_id ] = [
                'label' => $r['label'],
                'points' => (int) $r['points'],
                'earned' => $earned_points,
                'active' => $active,
                'severity' => $r['severity'],
                'module_id' => $module_id,
            ];

            $earned += $earned_points;
        }

        // Evaluate penalties
        $penalties_total = 0;
        $penalty_details = [];
        foreach ( $this->penalties as $pid => $p ) {
            $applies = $this->penalty_applies( $p );
            $deduct = ( $applies ? (int) $p['points'] : 0 );
            $penalty_details[ $pid ] = [
                'label' => $p['label'],
                'points' => (int) $p['points'],
                'applies' => (bool) $applies,
                'severity' => $p['severity'],
            ];
            $penalties_total += $deduct;
        }

        // Apply penalties (subtract from earned points)
        $effective_earned = max( 0, $earned - $penalties_total );

        // Determine denominator: computed or fixed
        $denominator = $raw_possible;
        if ( $raw_possible === 124 ) {
            $denominator = 124;
        }
        if ( is_int( $this->fixed_total ) && $this->fixed_total > 0 ) {
            $denominator = $this->fixed_total;
        }

        // Final score (0..100)
        $score = 0;
        if ( $denominator > 0 ) {
            $score = (int) round( ( $effective_earned / $denominator ) * 100 );
            $score = max( 0, min( 100, $score ) );
        }

        $risk = $this->get_risk_label_from_score( $score );

        return [
            'score' => $score,
            'earned' => $earned,
            'effective_earned' => $effective_earned,
            'penalties_total' => $penalties_total,
            'possible' => $denominator,
            'raw_possible' => $raw_possible,
            'risk' => $risk,
            'breakdown' => $breakdown,
            'penalties' => $penalty_details,
        ];
    }

    public function get_score_card() {
        return $this->calculate_score();
    }

    /* -------------------------
     * VISUALIZATION-FRIENDLY OUTPUT
     * ------------------------- */

    /**
     * Map severities to colors for charting/visualization.
     *
     * @param string $severity
     * @return string hex color
     */
    protected function map_severity_color( $severity ) {
        switch ( strtolower( $severity ) ) {
            case 'critical':
                return '#d73a49'; // red
            case 'high':
                return '#e67e22'; // orange
            case 'medium':
                return '#f1c40f'; // yellow
            case 'low':
            default:
                return '#2ecc71'; // green
        }
    }

    /**
     * Produce a visualization-friendly structure suitable for chart libraries or UI cards.
     *
     * Structure:
     * [
     *   score: int,
     *   percentage: float,
     *   earned: int,
     *   penalties_total: int,
     *   possible: int,
     *   raw_possible: int,
     *   risk: string,
     *   segments: [
     *     { id, label, points, earned, color, severity, active }
     *   ],
     *   penalty_segments: [
     *     { id, label, points, applies, color, severity }
     *   ]
     * ]
     *
     * @return array
     */
    public function get_visualization_data() {
        $card = $this->calculate_score();

        $segments = [];
        foreach ( $card['breakdown'] as $id => $row ) {
            $segments[] = [
                'id' => $id,
                'label' => $row['label'],
                'points' => (int) $row['points'],
                'earned' => (int) $row['earned'],
                'active' => (bool) $row['active'],
                'severity' => $row['severity'],
                'color' => $this->map_severity_color( $row['severity'] ),
            ];
        }

        $penalty_segments = [];
        foreach ( $card['penalties'] as $pid => $p ) {
            $penalty_segments[] = [
                'id' => $pid,
                'label' => $p['label'],
                'points' => (int) $p['points'],
                'applies' => (bool) $p['applies'],
                'severity' => $p['severity'],
                'color' => $this->map_severity_color( $p['severity'] ),
            ];
        }

        // Provide a simple donut series: earned (after penalties) vs remainder
        $effective = (int) $card['effective_earned'];
        $possible = (int) $card['possible'];
        $remainder = max( 0, $possible - $effective );

        $donut = [
            [ 'label' => 'Hardened', 'value' => $effective, 'color' => '#2ecc71' ],
            [ 'label' => 'Remaining', 'value' => $remainder, 'color' => '#eaeaea' ],
        ];

        return [
            'score' => (int) $card['score'],
            'percentage' => ( $possible > 0 ) ? round( ( $effective / $possible ) * 100, 2 ) : 0.0,
            'earned' => (int) $card['earned'],
            'effective_earned' => (int) $card['effective_earned'],
            'penalties_total' => (int) $card['penalties_total'],
            'possible' => $possible,
            'raw_possible' => (int) $card['raw_possible'],
            'risk' => $card['risk'],
            'segments' => $segments,
            'penalty_segments' => $penalty_segments,
            'donut' => $donut,
            'raw' => $card,
        ];
    }

    /* -------------------------
     * UTILITIES & ADMIN WIDGET
     * ------------------------- */

    protected function get_risk_label_from_score( $score ) {
        $s = (int) $score;
        if ( $s <= 39 ) {
            return 'High Risk';
        } elseif ( $s <= 69 ) {
            return 'Medium Risk';
        } elseif ( $s <= 89 ) {
            return 'Secure';
        }
        return 'Hardened';
    }

    public function set_fixed_total( $n ) {
        $this->fixed_total = is_null( $n ) ? null : (int) $n;
    }

    public function register_dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'wp_secured_score_widget',
            'WP Secured Score',
            [ $this, 'render_dashboard_widget' ]
        );
    }

    public function render_dashboard_widget() {
        $viz = $this->get_visualization_data();
        $json = wp_json_encode( $viz );

        // A simple inline bar visual and the JSON payload for extensibility.
        $score = esc_html( $viz['score'] );
        $risk = esc_html( $viz['risk'] );
        $pct = esc_attr( $viz['percentage'] );

        // Color by risk
        $bg = '#d73a49';
        if ( $viz['score'] >= 90 ) {
            $bg = '#2ecc71';
        } elseif ( $viz['score'] >= 70 ) {
            $bg = '#27ae60';
        } elseif ( $viz['score'] >= 40 ) {
            $bg = '#f39c12';
        }

        echo '<div class="wp-secured-dashboard-widget" data-wp-secured-viz=\'' . esc_attr( $json ) . '\'>';
        echo '<p><strong>Security Score:</strong> ' . $score . '%</p>';
        echo '<p><strong>Risk Level:</strong> ' . $risk . '</p>';
        echo '<div style="background:#e9e9e9;border-radius:4px;height:14px;width:100%;overflow:hidden;margin:6px 0;">';
        echo '<div style="width:' . $pct . '%;height:14px;background:' . esc_attr( $bg ) . ';"></div>';
        echo '</div>';
        echo '<p style="margin:6px 0 0 0;font-size:90%;color:#666">Points: ' . intval( $viz['effective_earned'] ) . ' (earned) — Penalties: ' . intval( $viz['penalties_total'] ) . ' — Possible: ' . intval( $viz['possible'] ) . '</p>';
        echo '<p style="margin:6px 0 0 0"><a href="' . esc_url( admin_url( 'admin.php?page=wp-secured' ) ) . '">View WP Secured Settings</a></p>';
        echo '</div>';
    }
}