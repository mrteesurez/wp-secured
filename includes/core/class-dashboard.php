<?php
/**
 * IMPORTANT: Dashboard / Reporting system.
 * Uses the scoring engine to render score summaries and provides render helpers for admin pages.
 */

class WP_Secured_Dashboard {
    /** @var WP_Secured_Loader */
    protected $loader;

    /** @var WP_Secured_Scoring */
    protected $scoring;

    public function __construct( WP_Secured_Loader $loader, WP_Secured_Scoring $scoring ) {
        $this->loader = $loader;
        $this->scoring = $scoring;
    }

    public function render_score_card() {
        $card = $this->scoring->get_score_card();
        ob_start();
        ?>
        <div class="wp-secured-score-card">
            <h2>WP Secured Score: <?php echo esc_html( $card['score'] ); ?>%</h2>
            <ul>
                <?php foreach ( $card['breakdown'] as $id => $row ) : ?>
                    <li>
                        <?php echo esc_html( $row['label'] ); ?>:
                        <?php echo $row['active'] ? '<strong style="color:green">ON</strong>' : '<strong style="color:#999">OFF</strong>'; ?>
                        (weight <?php echo (int) $row['weight']; ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Convenience for admin page to output both score and module list.
     */
    public function render_admin_overview() {
        echo $this->render_score_card();
    }
}