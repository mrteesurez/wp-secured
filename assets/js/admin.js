// assets/js/admin.js
// Handles animated counter, donut drawing, AJAX quick actions, tooltips, before/after display,
// and One Click Hardening step-by-step progress UI.

(function (window, document, $) {
    'use strict';

    const cfg = window.WP_SECURED_ADMIN || {};
    const vizInitial = cfg.viz || null;

    // Helpers
    function lerp(a, b, t) { return a + (b - a) * t; }

    function animateCount( el, from, to, duration ) {
        const start = performance.now();
        const diff = to - from;
        function step(now) {
            const t = Math.min(1, (now - start) / duration);
            const ease = (1 - Math.cos(Math.PI * t)) / 2;
            const val = Math.round( from + diff * ease );
            el.textContent = val;
            if ( t < 1 ) {
                requestAnimationFrame(step);
            }
        }
        requestAnimationFrame(step);
    }

    function createDonut( container, percent, color ) {
        container.innerHTML = '';
        const size = 160;
        const stroke = 14;
        const radius = (size - stroke) / 2;
        const c = document.createElementNS('http://www.w3.org/2000/svg','svg');
        c.setAttribute('viewBox', `0 0 ${size} ${size}`);
        c.setAttribute('width', size);
        c.setAttribute('height', size);

        const bg = document.createElementNS('http://www.w3.org/2000/svg','circle');
        bg.setAttribute('cx', size/2);
        bg.setAttribute('cy', size/2);
        bg.setAttribute('r', radius);
        bg.setAttribute('stroke', '#e9e9e9');
        bg.setAttribute('stroke-width', stroke);
        bg.setAttribute('fill', 'transparent');
        c.appendChild(bg);

        const fg = document.createElementNS('http://www.w3.org/2000/svg','circle');
        fg.setAttribute('cx', size/2);
        fg.setAttribute('cy', size/2);
        fg.setAttribute('r', radius);
        fg.setAttribute('stroke', color || '#2ecc71');
        fg.setAttribute('stroke-width', stroke);
        fg.setAttribute('fill', 'transparent');
        fg.setAttribute('stroke-linecap', 'round');
        fg.setAttribute('transform', `rotate(-90 ${size/2} ${size/2})`);

        const circumference = 2 * Math.PI * radius;
        fg.setAttribute('stroke-dasharray', circumference);
        fg.setAttribute('stroke-dashoffset', circumference);
        c.appendChild(fg);

        const text = document.createElementNS('http://www.w3.org/2000/svg','text');
        text.setAttribute('x', '50%');
        text.setAttribute('y', '52%');
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('font-size', '18');
        text.setAttribute('font-weight', '700');
        text.setAttribute('fill', '#222');
        text.textContent = percent + '%';
        c.appendChild(text);

        container.appendChild(c);

        // animate stroke
        const toOffset = circumference * (1 - percent / 100);
        const initial = circumference;
        const duration = 1000;
        const start = performance.now();

        function tick(now) {
            const t = Math.min(1, (now - start) / duration);
            const ease = (1 - Math.cos(Math.PI * t)) / 2;
            const current = initial + (toOffset - initial) * ease;
            fg.setAttribute('stroke-dashoffset', current);
            text.textContent = Math.round(percent * ease) + '%';
            if ( t < 1 ) {
                requestAnimationFrame(tick);
            } else {
                text.textContent = percent + '%';
            }
        }
        requestAnimationFrame(tick);

        return { svg: c, fg: fg, text: text };
    }

    function riskColor(score) {
        if ( score >= 90 ) return '#2ecc71';
        if ( score >= 70 ) return '#27ae60';
        if ( score >= 40 ) return '#f39c12';
        return '#d73a49';
    }

    function renderInitial() {
        const donutContainer = document.getElementById('wp-secured-donut');
        const scoreNumber = document.getElementById('wp-secured-score-number');
        const scoreRisk = document.getElementById('wp-secured-score-risk');
        const earnedEl = document.getElementById('wp-secured-earned');
        const possibleEl = document.getElementById('wp-secured-possible');

        if ( vizInitial ) {
            const percent = vizInitial.score;
            const color = riskColor( percent );
            createDonut( donutContainer, percent, color );
            scoreRisk.textContent = vizInitial.risk;
            scoreRisk.setAttribute('data-risk', vizInitial.risk);
            scoreRisk.style.background = color;
            animateCount( scoreNumber, 0, percent, 1200 );
            animateCount( earnedEl, 0, vizInitial.effective_earned, 800 );
            possibleEl.textContent = vizInitial.possible;
        }
    }

    // AJAX helpers
    function ajaxPost( action, data, cb ) {
        data = data || {};
        data.action = action;
        data.nonce = cfg.nonce;
        $.post( cfg.ajax_url, data )
        .done( function (resp) {
            if ( resp && resp.success ) {
                cb( null, resp.data );
            } else {
                cb( resp && resp.data ? resp.data : 'Error' );
            }
        } )
        .fail( function (jq, status, err) {
            cb( err || status || 'Ajax failed' );
        } );
    }

    // Run security scan and animate
    function runScan() {
        const btn = document.getElementById('wp-secured-scan-btn');
        btn.setAttribute('disabled', 'disabled');
        ajaxPost( 'wp_secured_scan', {}, function ( err, data ) {
            btn.removeAttribute('disabled');
            if ( err ) {
                alert( 'Scan failed: ' + err );
                return;
            }
            applyViz( data );
        } );
    }

    // Existing bulk secure (kept for compatibility)
    function secureSiteBulk() {
        if ( ! confirm( cfg.strings.secure_confirm ) ) {
            return;
        }
        const btn = document.getElementById('wp-secured-secure-btn');
        btn.setAttribute('disabled', 'disabled');
        ajaxPost( 'wp_secured_secure_site', {}, function ( err, data ) {
            btn.removeAttribute('disabled');
            if ( err ) {
                alert( 'Secure action failed: ' + err );
                return;
            }
            if ( data.before && data.after ) {
                showCompare( data.before, data.after );
                applyViz( data.after );
            }
        } );
    }

    // NEW: Stepwise One Click Hardening
    function secureSiteStepwise() {
        if ( ! confirm( cfg.strings.secure_confirm ) ) {
            return;
        }

        // fetch recommended list
        ajaxPost( 'wp_secured_list_recommended', {}, function ( err, recs ) {
            if ( err ) {
                alert( 'Could not get recommended modules: ' + err );
                return;
            }
            // show modal with steps
            openHardeningModal( recs );
        } );
    }

    // Generate report: request JSON payload and download as file
    function generateReport() {
        const btn = document.getElementById('wp-secured-report-btn');
        btn.setAttribute('disabled', 'disabled');
        ajaxPost( 'wp_secured_generate_report', {}, function ( err, data ) {
            btn.removeAttribute('disabled');
            if ( err ) {
                alert( 'Report failed: ' + err );
                return;
            }
            const filename = 'wp-secured-report-' + (new Date()).toISOString().replace(/[:]/g,'-') + '.json';
            const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
            const url = URL.createObjectURL( blob );
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL( url );
        } );
    }

    // Apply visualization payload to UI (score, donut, list)
    function applyViz( viz ) {
        const donutContainer = document.getElementById('wp-secured-donut');
        const scoreNumber = document.getElementById('wp-secured-score-number');
        const scoreRisk = document.getElementById('wp-secured-score-risk');
        const earnedEl = document.getElementById('wp-secured-earned');
        const possibleEl = document.getElementById('wp-secured-possible');

        const percent = viz.score;
        const color = riskColor( percent );

        createDonut( donutContainer, percent, color );
        const old = parseInt( scoreNumber.getAttribute('data-score') || 0, 10 );
        animateCount( scoreNumber, old, percent, 1000 );
        scoreNumber.setAttribute('data-score', percent );
        scoreRisk.textContent = viz.risk;
        scoreRisk.style.background = color;
        scoreRisk.setAttribute('data-risk', viz.risk );
        animateCount( earnedEl, parseInt(earnedEl.textContent || 0,10), viz.effective_earned, 700 );
        possibleEl.textContent = viz.possible;

        // update breakdown list: state toggles
        const list = document.getElementById('wp-secured-breakdown-list');
        if ( list && viz.segments ) {
            list.innerHTML = '';
            viz.segments.forEach( function (seg) {
                const div = document.createElement('div');
                div.className = 'breakdown-row';
                div.setAttribute('data-tooltip', seg.label );
                div.innerHTML = '<span style="display:flex;align-items:center"><span class="badge" style="background:' + seg.color + ';"></span><strong>' + seg.label + '</strong></span>'
                    + '<span class="points">' + seg.points + ' pts</span>'
                    + '<span class="state ' + ( seg.active ? 'on' : 'off' ) + '">' + ( seg.active ? 'ON' : 'OFF' ) + '</span>';
                list.appendChild( div );
            } );
        }

        // update risk breakdown counts
        document.getElementById('wp-secured-critical-count').textContent = viz.segments.filter(s=>s.severity==='critical').length;
        document.getElementById('wp-secured-high-count').textContent = viz.segments.filter(s=>s.severity==='high').length;
        document.getElementById('wp-secured-medium-count').textContent = viz.segments.filter(s=>s.severity==='medium').length;
        document.getElementById('wp-secured-low-count').textContent = viz.segments.filter(s=>s.severity==='low').length;
    }

    // Show Before vs After comparison
    function showCompare( before, after ) {
        document.getElementById('compare-before-score').textContent = before.score + '%';
        document.getElementById('compare-before-details').textContent = 'Earned ' + before.effective_earned + ' / ' + before.possible;
        document.getElementById('compare-after-score').textContent = after.score + '%';
        document.getElementById('compare-after-details').textContent = 'Earned ' + after.effective_earned + ' / ' + after.possible;
        // add a small animation highlight
        const afterCard = document.getElementById('compare-after-score');
        afterCard.style.transition = 'transform 400ms ease';
        afterCard.style.transform = 'scale(1.06)';
        setTimeout(function(){ afterCard.style.transform = ''; }, 420);
    }

    /* -----------------------
     * Hardening modal UI
     * ----------------------- */

    function openHardeningModal( recs ) {
        // capture current viz as before
        ajaxPost( 'wp_secured_scan', {}, function ( err, beforeViz ) {
            if ( err ) {
                alert( 'Could not fetch baseline: ' + err );
                return;
            }
            buildModal( recs, beforeViz );
        } );
    }

    function buildModal( recs, beforeViz ) {
        // create backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'wp-secured-modal-backdrop';
        backdrop.id = 'wp-secured-modal-backdrop';

        const modal = document.createElement('div');
        modal.className = 'wp-secured-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        // header
        const h = document.createElement('h2');
        h.textContent = cfg.strings.secure_start || 'Starting One Click Hardening…';
        modal.appendChild(h);

        const stepsWrap = document.createElement('div');
        stepsWrap.className = 'wp-secured-steps';

        // left column: step list
        const left = document.createElement('div');
        left.className = 'wp-secured-step-list';

        // build step items
        recs.forEach(function (r, i) {
            const step = document.createElement('div');
            step.className = 'wp-secured-step';
            step.id = 'wp-secured-step-' + r.module_id;

            const leftPart = document.createElement('div');
            leftPart.className = 'step-left';
            const label = document.createElement('div');
            label.className = 'step-label';
            label.textContent = r.label;
            const desc = document.createElement('div');
            desc.className = 'step-desc';
            desc.textContent = r.description || (r.points + ' pts');

            leftPart.appendChild(label);
            leftPart.appendChild(desc);

            const rightPart = document.createElement('div');
            rightPart.className = 'step-state pending';
            rightPart.id = 'wp-secured-step-state-' + r.module_id;
            if ( r.active ) {
                rightPart.textContent = 'already';
            } else {
                // spinner placeholder
                rightPart.innerHTML = '<div class="wp-secured-spinner" aria-hidden="true"></div>';
            }

            step.appendChild(leftPart);
            step.appendChild(rightPart);
            left.appendChild(step);
        } );

        // right column: logs + final
        const right = document.createElement('div');

        const log = document.createElement('div');
        log.className = 'wp-secured-log';
        log.id = 'wp-secured-log';
        log.textContent = 'Log started: ' + (new Date()).toLocaleString() + '\n';

        const final = document.createElement('div');
        final.className = 'wp-secured-final';
        final.id = 'wp-secured-final';
        final.style.display = 'none';

        right.appendChild(log);
        right.appendChild(final);

        stepsWrap.appendChild(left);
        stepsWrap.appendChild(right);

        // footer actions
        const footer = document.createElement('div');
        footer.style.marginTop = '12px';
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'button';
        cancelBtn.textContent = 'Close';
        cancelBtn.addEventListener('click', function () {
            document.body.removeChild(backdrop);
        } );
        footer.appendChild(cancelBtn);

        modal.appendChild(stepsWrap);
        modal.appendChild(footer);

        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        // run steps sequentially
        runStepsSequentially( recs, log, beforeViz, final, backdrop );
    }

    function runStepsSequentially( recs, logEl, beforeViz, finalEl, backdrop ) {
        const total = recs.length;
        let idx = 0;
        let lastViz = beforeViz;

        function appendLog( msg ) {
            const time = (new Date()).toLocaleTimeString();
            logEl.textContent += '[' + time + '] ' + msg + '\n';
            logEl.scrollTop = logEl.scrollHeight;
        }

        function processNext() {
            if ( idx >= total ) {
                // finished
                appendLog( 'All steps complete.' );
                finishFlow( beforeViz, lastViz, finalEl );
                return;
            }

            const r = recs[ idx ];
            const stateEl = document.getElementById( 'wp-secured-step-state-' + r.module_id );

            // If already active, mark success and skip AJAX
            if ( r.active ) {
                stateEl.className = 'step-state success';
                stateEl.textContent = 'already';
                appendLog( 'Skipped ' + r.label + ' (already enabled).' );
                idx++;
                setTimeout(processNext, 300);
                return;
            }

            // mark spinner
            stateEl.innerHTML = '<div class="wp-secured-spinner" aria-hidden="true"></div>';
            appendLog( 'Enabling ' + r.label + ' ...' );

            // Call enable_module ajax
            ajaxPost( 'wp_secured_enable_module', { module_id: r.module_id }, function ( err, data ) {
                if ( err ) {
                    stateEl.className = 'step-state failed';
                    stateEl.textContent = 'failed';
                    appendLog( 'Failed to enable ' + r.label + ': ' + err );
                    // continue to next step despite failure
                    idx++;
                    setTimeout(processNext, 400);
                    return;
                }

                // success or already_enabled
                if ( data.status === 'enabled' || data.status === 'already_enabled' ) {
                    stateEl.className = 'step-state success';
                    stateEl.textContent = 'done';
                    appendLog( r.label + ' enabled. New score: ' + data.viz.score + '%' );
                    // update UI to intermediate visualization
                    applyViz( data.viz );
                    lastViz = data.viz;
                } else {
                    stateEl.className = 'step-state failed';
                    stateEl.textContent = 'failed';
                    appendLog( r.label + ' unexpected status: ' + data.status );
                }

                idx++;
                setTimeout(processNext, 350);
            } );
        }

        // start processing
        setTimeout(processNext, 500);
    }

    function finishFlow( beforeViz, afterViz, finalEl ) {
        // show final panel: improvement
        const beforeScore = beforeViz.score || 0;
        const afterScore = afterViz.score || 0;
        const delta = afterScore - beforeScore;

        finalEl.style.display = 'block';
        finalEl.innerHTML = '<div class="big">' + afterScore + '%</div>'
            + '<div>Before: ' + beforeScore + '%</div>'
            + '<div class="delta">Improvement: ' + (delta >= 0 ? '+' + delta : delta) + ' pts</div>'
            + '<div style="margin-top:10px;"><button class="button button-primary" id="wp-secured-final-close">Close</button></div>';

        const closeBtn = document.getElementById( 'wp-secured-final-close' );
        closeBtn.addEventListener('click', function () {
            const backdrop = document.getElementById('wp-secured-modal-backdrop');
            if ( backdrop ) document.body.removeChild( backdrop );
        } );
    }

    /* -----------------------
     * Bind events
     * ----------------------- */

    function bindUI() {
        const scanBtn = document.getElementById('wp-secured-scan-btn');
        if ( scanBtn ) scanBtn.addEventListener( 'click', runScan );

        const secureBtn = document.getElementById('wp-secured-secure-btn');
        if ( secureBtn ) {
            // Wire new stepwise flow (comment out bulk if you prefer)
            secureBtn.addEventListener( 'click', secureSiteStepwise );
            // If you want bulk: secureBtn.addEventListener('click', secureSiteBulk);
        }

        const reportBtn = document.getElementById('wp-secured-report-btn');
        if ( reportBtn ) reportBtn.addEventListener( 'click', generateReport );
    }

    // Init
    document.addEventListener('DOMContentLoaded', function () {
        renderInitial();
        bindUI();
    });

})( window, document, jQuery );