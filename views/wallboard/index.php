<?php $this->section('content'); ?>

<!-- Today's Stats -->
<div class="row mb-4">
    <div class="col-12">
        <div class="wallboard-card">
            <div class="wallboard-card-header">
                <span class="wallboard-card-title">Today's Statistics</span>
                <span class="text-muted" id="last-update">Loading...</span>
            </div>
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-value" id="stat-total">--</div>
                    <div class="stat-label">Total Calls</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value good" id="stat-answered">--</div>
                    <div class="stat-label">Answered</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value bad" id="stat-abandoned">--</div>
                    <div class="stat-label">Abandoned</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="stat-avg-wait">--</div>
                    <div class="stat-label">Avg Wait</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="stat-avg-talk">--</div>
                    <div class="stat-label">Avg Talk</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="stat-sla">--%</div>
                    <div class="stat-label">SLA</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Queue Status -->
<div class="row mb-4">
    <div class="col-12">
        <div class="wallboard-card">
            <div class="wallboard-card-header">
                <span class="wallboard-card-title">Queue Status</span>
            </div>
            <div class="queue-grid" id="queue-grid">
                <!-- Queues will be populated by JavaScript -->
                <div class="text-center py-5">
                    <div class="loader-spinner mx-auto mb-3"></div>
                    <p class="text-muted">Loading queue data...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Agent Status -->
<div class="row">
    <div class="col-md-4">
        <div class="wallboard-card">
            <div class="wallboard-card-header">
                <span class="wallboard-card-title">Agent Summary</span>
            </div>
            <div class="stats-row flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Available</span>
                    <span class="badge bg-success fs-5" id="agents-available">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>On Call</span>
                    <span class="badge bg-primary fs-5" id="agents-oncall">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Paused</span>
                    <span class="badge bg-warning fs-5" id="agents-paused">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Offline</span>
                    <span class="badge bg-secondary fs-5" id="agents-offline">0</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="wallboard-card">
            <div class="wallboard-card-header">
                <span class="wallboard-card-title">Agents</span>
            </div>
            <div class="agent-grid" id="agent-grid">
                <!-- Agents will be populated by JavaScript -->
                <div class="text-center py-4">
                    <div class="loader-spinner mx-auto"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
(function() {
    // Update clock
    function updateClock() {
        var now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleTimeString('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Format duration
    function formatDuration(seconds) {
        if (!seconds || seconds < 0) return '0:00';
        var mins = Math.floor(seconds / 60);
        var secs = seconds % 60;
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    // Update wallboard data
    function updateWallboard() {
        $.get('/areports/wallboard/data', function(data) {
            // Update stats
            $('#stat-total').text(data.stats.total_calls);
            $('#stat-answered').text(data.stats.answered_calls);
            $('#stat-abandoned').text(data.stats.abandoned_calls);
            $('#stat-avg-wait').text(formatDuration(data.stats.avg_wait_time));
            $('#stat-avg-talk').text(formatDuration(data.stats.avg_talk_time));

            var slaVal = data.stats.sla_percentage;
            var $sla = $('#stat-sla');
            $sla.text(slaVal + '%');
            $sla.removeClass('good warning bad');
            if (slaVal >= 80) $sla.addClass('good');
            else if (slaVal >= 60) $sla.addClass('warning');
            else $sla.addClass('bad');

            // Update queues
            var queueHtml = '';
            data.queues.forEach(function(queue) {
                queueHtml += '<div class="queue-box ' + queue.status_class + '">' +
                    '<div class="queue-name">' + escapeHtml(queue.display_name) + '</div>' +
                    '<div class="queue-stats">' +
                        '<div class="queue-stat">' +
                            '<div class="queue-stat-value">' + queue.answered + '</div>' +
                            '<div class="queue-stat-label">Answered</div>' +
                        '</div>' +
                        '<div class="queue-stat">' +
                            '<div class="queue-stat-value">' + queue.abandoned + '</div>' +
                            '<div class="queue-stat-label">Abandoned</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="sla-display">' +
                        '<div class="sla-circle ' + queue.status_class + '">' + queue.sla_percentage + '%</div>' +
                    '</div>' +
                '</div>';
            });
            $('#queue-grid').html(queueHtml || '<p class="text-muted text-center py-4">No queues configured</p>');

            // Update agent summary
            $('#agents-available').text(data.agents.available);
            $('#agents-oncall').text(data.agents.oncall);
            $('#agents-paused').text(data.agents.paused);
            $('#agents-offline').text(data.agents.offline);

            // Update agent list
            var agentHtml = '';
            data.agents.list.forEach(function(agent) {
                agentHtml += '<div class="agent-box">' +
                    '<div class="agent-status-dot ' + agent.status + '"></div>' +
                    '<div class="agent-info">' +
                        '<div class="agent-name">' + escapeHtml(agent.name) + '</div>' +
                        '<div class="agent-status-text">' + escapeHtml(agent.status_text) + '</div>' +
                    '</div>' +
                '</div>';
            });
            $('#agent-grid').html(agentHtml || '<p class="text-muted text-center py-4">No agents configured</p>');

            // Update last update time
            $('#last-update').text('Updated: ' + data.timestamp);

        }).fail(function() {
            console.error('Failed to fetch wallboard data');
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }

    // Initial load
    updateWallboard();

    // Refresh every 10 seconds
    setInterval(updateWallboard, 10000);

    // Fullscreen toggle with F11
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F11') {
            e.preventDefault();
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }
    });
})();
</script>
<?php $this->endSection(); ?>
