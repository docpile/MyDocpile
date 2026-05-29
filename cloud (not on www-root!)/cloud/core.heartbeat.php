<?php
/**
 * Outputs the heartbeat JavaScript code.
 * Injects interaction listeners and pings the controller router directly.
 */
    global $timeout_in_minutes; 
    $timeout_duration = (isset($timeout_in_minutes) ? $timeout_in_minutes : 15) * 60;
    ?>
    <script>
    (function() {
        if (window.__heartbeatInitialized === true) return;
        window.__heartbeatInitialized = true;

        var inactivityLimit = <?php echo $timeout_duration * 1000; ?>;
        var lastActivity = Date.now();
        var userInteracted = false;
        var lastHeartbeatTime = Date.now();

        function recordInteraction() {
            lastActivity = Date.now();
            userInteracted = true;
            if (Date.now() - lastHeartbeatTime > 120000) {
                sendHeartbeat();
            }
        }

        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart', 'touchmove'].forEach(function(eventName) {
            document.addEventListener(eventName, recordInteraction, true);
        });

        function sendHeartbeat() {
            lastHeartbeatTime = Date.now();
            var updateParam = userInteracted ? '&update=1' : '';
            userInteracted = false;
            var url = window.location.protocol + '//' + window.location.host + window.location.pathname + '?heartbeat=1' + updateParam;

            fetch(url, {
                method: 'GET',
                credentials: 'include',
                cache: 'no-store'
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.status === 'expired') {
                    handleExpiredSession();
                }
            })
            .catch(function(error) {
                console.error("Heartbeat error:", error);
            });
        }

        function checkClientInactivity() {
            if (Date.now() - lastActivity >= inactivityLimit) {
                handleExpiredSession();
            }
        }

        function handleExpiredSession() {
            if (window.self !== window.top) {
                if (window.parent && typeof window.parent.closeModal === 'function') {
                    window.parent.closeModal();
                }
                window.parent.location.reload();
            } else {
                location.reload();
            }
        }

        window.__heartbeatIntervalID = window.setInterval(sendHeartbeat, 180 * 1000);
        window.__clientInactivityIntervalID = window.setInterval(checkClientInactivity, 40 * 1000);
    })();
    </script>
