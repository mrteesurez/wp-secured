<?php
/**
 * admin/admin-page.php
 * Modern WP Secured admin dashboard UI + stepwise One Click Hardening endpoints.
 */

if ( ! defined( 'WP_SECURED_DIR' ) ) {
    return;
}

/**
 * Add the admin menu page
 */
add_action( 'admin_menu', function () {
    add_menu_page(
        'WP Secured',
        'WP Secured',
        'manage_options',
        'wp-secured',
        'wp_secured_admin_page',
        'dashicons-shield',
        80
    );
} );

/**
 * Enqueue admin assets for our dashboard page
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    // Only enqueue on our plugin page
    if ( 'toplevel_page_wp-secured' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'wp-secured-admin',
        WP_SECURED_URL . 'assets/css/admin.css',
        [],
        '1.1.0'
    );

    wp_enqueue_script(
        'wp-secured-admin',
        WP_SECURED_URL . 'assets/js/admin.js',
        [ 'jquery' ],
        '1.1.0',
        true
    );

    // Localize data: initial visualization data and ajax endpoints
    $loader = WP_Secured_Loader::get_instance();
    $viz = [];
    if ( $loader && $loader->get_scoring() ) {
        $viz = $loader->get_scoring()->get_visualization_data();
    }

    wp_localize_script( 'wp-secured-admin', 'WP_SECURED_ADMIN', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wp_secured_admin_action' ),
        'viz'      => $viz,
        'strings'  => [
            'secure_confirm' => __( 'This will enable recommended protections. Continue?', 'wp-secured' ),
            'secure_start'   => __( 'Starting One Click Hardening…', 'wp-secured' ),
            'secure_done'    => __( 'Hardening complete', 'wp-secured' ),
        ],
    ] );
} );

/**
 * AJAX: Run a security scan (return visualization payload)
 */
add_action( 'wp_ajax_wp_secured_scan', function () {
    check_ajax_referer( 'wp_secured_admin_action', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $loader = WP_Secured_Loader::get_instance();
    $viz = $loader->get_scoring()->get_visualization_data();

    wp_send_json_success( $viz );
} );

/**
 * AJAX: Bulk secure (existing behavior kept)
 */
add_action( 'wp_ajax_wp_secured_secure_site', function () {
    check_ajax_referer( 'wp_secured_admin_action', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $loader = WP_Secured_Loader::get_instance();
    $scoring = $loader->get_scoring();

    // compute before
    $before = $scoring->get_visualization_data();

    // Determine recommended rules (severity critical + high)
    $rules = $scoring->get_rules();
    $modules = $loader->get_modules();

    $enabled = [];
    foreach ( $rules as $rid => $r ) {
        if ( in_array( $r['severity'], [ 'critical', 'high' ], true ) && ! empty( $r['module_id'] ) ) {
            $mid = $r['module_id'];
            if ( isset( $modules[ $mid ] ) ) {
                // enable module persistently
                $modules[ $mid ]->set_active( true );
                // re-register hooks for immediate effect (best-effort)
                $modules[ $mid ]->maybe_register_hooks();
                $enabled[] = $mid;
            }
        }
    }

    // recompute after
    $after = $scoring->get_visualization_data();

    wp_send_json_success( [
        'before' => $before,
        'after'  => $after,
        'enabled_modules' => array_values( array_unique( $enabled ) ),
    ] );
} );

/**
 * AJAX: Generate Report (returns downloadable JSON payload)
 */
add_action( 'wp_ajax_wp_secured_generate_report', function () {
    check_ajax_referer( 'wp_secured_admin_action', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $loader = WP_Secured_Loader::get_instance();
    $scoring = $loader->get_scoring();

    $viz = $scoring->get_visualization_data();
    $payload = [
        'generated_at' => gmdate( 'c' ),
        'site_url' => get_home_url(),
        'visualization' => $viz,
    ];

    // Return JSON in response (client will download)
    wp_send_json_success( $payload );
} );

/**
 * NEW AJAX: list recommended modules (for stepwise flow)
 */
add_action( 'wp_ajax_wp_secured_list_recommended', function () {
    check_ajax_referer( 'wp_secured_admin_action', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $loader = WP_Secured_Loader::get_instance();
    $scoring = $loader->get_scoring();
    $rules = $scoring->get_rules();
    $modules = $loader->get_modules();

    $recommended = [];

    foreach ( $rules as $rid => $r ) {
        // pick critical + high severity (ordered by severity then points)
        if ( in_array( $r['severity'], [ 'critical', 'high' ], true ) && ! empty( $r['module_id'] ) ) {
            $mid = $r['module_id'];
            if ( isset( $modules[ $mid ] ) ) {
                $recommended[] = [
                    'module_id' => $mid,
                    'label'     => $modules[ $mid ]->get_label(),
                    'description' => $modules[ $mid ]->get_description(),
                    'severity'  => $r['severity'],
                    'points'    => (int) $r['points'],
                    'active'    => (bool) $modules[ $mid ]->is_active(),
                ];
            }
        }
    }

    // Keep order: critical first (10-point), then high (8-point)
    usort( $recommended, function ( $a, $b ) {
        $order = [ 'critical' => 0, 'high' => 1 ];
        $oa = isset( $order[ $a['severity'] ] ) ? $order[ $a['severity'] ] : 2;
        $ob = isset( $order[ $b['severity'] ] ) ? $order[ $b['severity'] ] : 2;
        if ( $oa === $ob ) {
            return $b['points'] - $a['points'];
        }
        return $oa - $ob;
    } );

    wp_send_json_success( $recommended );
} );

/**
 * NEW AJAX: enable a single module (step)
 * Request: module_id
 * Response: { module_id, status, module_label, viz }
 */
add_action( 'wp_ajax_wp_secured_enable_module', function () {
    check_ajax_referer( 'wp_secured_admin_action', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    if ( empty( $_POST['module_id'] ) ) {
        wp_send_json_error( 'Missing module_id', 400 );
    }

    $module_id = sanitize_text_field( wp_unslash( $_POST['module_id'] ) );
    $loader = WP_Secured_Loader::get_instance();
    $modules = $loader->get_modules();

    if ( ! isset( $modules[ $module_id ] ) ) {
        wp_send_json_error( 'Unknown module: ' . $module_id, 404 );
    }

    $module = $modules[ $module_id ];
    $label = $module->get_label();

    // If already active, return already_enabled
    if ( $module->is_active() ) {
        $scoring = $loader->get_scoring();
        wp_send_json_success( [
            'module_id' => $module_id,
            'status' => 'already_enabled',
            'module_label' => $label,
            'viz' => $scoring->get_visualization_data(),
        ] );
    }

    // Enable persistently
    $module->set_active( true );

    // Re-register hooks for immediate effect (best-effort)
    $module->maybe_register_hooks();

    // Return updated visualization payload
    $scoring = $loader->get_scoring();
    wp_send_json_success( [
        'module_id' => $module_id,
        'status' => 'enabled',
        'module_label' => $label,
        'viz' => $scoring->get_visualization_data(),
    ] );
} );

/**
 * The admin page markup (modern card layout)
 */
function wp_secured_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $loader = WP_Secured_Loader::get_instance();
    $scoring = $loader->get_scoring();
    $viz = $scoring->get_visualization_data();

    // modules for protected features summary
    $modules = $loader->get_modules();

    ?>
    <div class="wp-secured-admin-wrap">
        <header class="wp-secured-header">
            <h1>WP Secured</h1>
            <p class="wp-secured-subtitle">Security hardening and monitoring for your site</p>
        </header>

        <main class="wp-secured-grid">
            <!-- Score Card -->
            <section class="card card-score" aria-labelledby="wp-secured-score-title">
                <div class="score-inner">
                    <div class="donut" id="wp-secured-donut"
                         data-viz='<?php echo esc_attr( wp_json_encode( $viz ) ); ?>'>
                        <!-- SVG will be inserted by JS -->
                    </div>

                    <div class="score-details">
                        <h2 id="wp-secured-score-title">Security Score</h2>
                        <div class="score-number" id="wp-secured-score-number" data-score="<?php echo esc_attr( $viz['score'] ); ?>">0</div>
                        <div class="score-risk" id="wp-secured-score-risk" data-risk="<?php echo esc_attr( $viz['risk'] ); ?>"><?php echo esc_html( $viz['risk'] ); ?></div>
                        <div class="score-meta">
                            <span>Earned: <strong id="wp-secured-earned"><?php echo intval( $viz['effective_earned'] ); ?></strong></span>
                            <span>Possible: <strong id="wp-secured-possible"><?php echo intval( $viz['possible'] ); ?></strong></span>
                        </div>
                    </div>
                </div>

                <div class="card-actions">
                    <button class="button button-primary" id="wp-secured-secure-btn">Secure My Site</button>
                    <button class="button" id="wp-secured-scan-btn">Run Security Scan</button>
                    <button class="button" id="wp-secured-report-btn">Generate Report</button>
                </div>

                <div class="card-smalltext">
                    <small>Tip: Hover metrics for details. Click "Secure My Site" to apply recommended protections (critical + high).</small>
                </div>
            </section>

            <!-- Risk Breakdown -->
            <section class="card card-breakdown" aria-labelledby="wp-secured-breakdown-title">
                <h3 id="wp-secured-breakdown-title">Risk Breakdown</h3>
                <div class="breakdown-grid">
                    <div class="breakdown-item critical">
                        <div class="label">Critical</div>
                        <div class="value" id="wp-secured-critical-count"><?php echo esc_html( count( array_filter( $viz['segments'], function( $s ){ return $s['severity'] === 'critical'; } ) ) ); ?></div>
                    </div>
                    <div class="breakdown-item high">
                        <div class="label">High</div>
                        <div class="value" id="wp-secured-high-count"><?php echo esc_html( count( array_filter( $viz['segments'], function( $s ){ return $s['severity'] === 'high'; } ) ) ); ?></div>
                    </div>
                    <div class="breakdown-item medium">
                        <div class="label">Medium</div>
                        <div class="value" id="wp-secured-medium-count"><?php echo esc_html( count( array_filter( $viz['segments'], function( $s ){ return $s['severity'] === 'medium'; } ) ) ); ?></div>
                    </div>
                    <div class="breakdown-item low">
                        <div class="label">Low</div>
                        <div class="value" id="wp-secured-low-count"><?php echo esc_html( count( array_filter( $viz['segments'], function( $s ){ return $s['severity'] === 'low'; } ) ) ); ?></div>
                    </div>
                </div>

                <div class="breakdown-list" id="wp-secured-breakdown-list">
                    <?php foreach ( $viz['segments'] as $seg ) : ?>
                        <div class="breakdown-row" data-tooltip="<?php echo esc_attr( $seg['label'] ); ?>">
                            <span style="display:flex;align-items:center"><span class="badge" style="background:<?php echo esc_attr( $seg['color'] ); ?>;"></span><strong><?php echo esc_html( $seg['label'] ); ?></strong></span>
                            <div class="row-right">
                                <span class="points"><?php echo intval( $seg['points'] ); ?> pts</span>
                                <span class="state <?php echo $seg['active'] ? 'on' : 'off'; ?>"><?php echo $seg['active'] ? 'ON' : 'OFF'; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Protected Features -->
            <section class="card card-protected" aria-labelledby="wp-secured-protected-title">
                <h3 id="wp-secured-protected-title">Protected Features</h3>
                <div class="protected-list" id="wp-secured-protected-list">
                    <?php
                    foreach ( $modules as $mid => $mod ) :
                        if ( $mod->get_type() !== 'hardening' ) {
                            continue;
                        }
                        $active = $mod->is_active();
                        ?>
                        <div class="protected-item" title="<?php echo esc_attr( $mod->get_description() ); ?>">
                            <div class="protected-left">
                                <span class="protected-label"><?php echo esc_html( $mod->get_label() ); ?></span>
                                <div class="protected-desc"><?php echo esc_html( $mod->get_description() ); ?></div>
                            </div>
                            <div class="protected-right">
                                <span class="state <?php echo $active ? 'on' : 'off'; ?>"><?php echo $active ? 'ENABLED' : 'disabled'; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Before vs After widget -->
            <section class="card card-compare" aria-labelledby="wp-secured-compare-title">
                <h3 id="wp-secured-compare-title">Before vs After</h3>
                <div class="compare-inner" id="wp-secured-compare">
                    <div class="compare-col">
                        <div class="compare-card">
                            <div class="label">Before</div>
                            <div class="big" id="compare-before-score">—</div>
                            <div class="small" id="compare-before-details">Run scan or Secure My Site</div>
                        </div>
                    </div>
                    <div class="compare-col">
                        <div class="compare-card">
                            <div class="label">After</div>
                            <div class="big" id="compare-after-score">—</div>
                            <div class="small" id="compare-after-details">N/A</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <?php
}