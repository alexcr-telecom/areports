/**
 * aReports Main Application JavaScript
 */

(function($) {
    'use strict';

    // CSRF Token setup for AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize application
    $(document).ready(function() {
        initSidebar();
        initTooltips();
        initSelect2();
        initDateRangePicker();
        initDataTables();
        initServerTime();
        initAlerts();
    });

    /**
     * Sidebar functionality
     */
    function initSidebar() {
        // Toggle sidebar
        $('.sidebar-toggle').on('click', function(e) {
            e.preventDefault();
            $('#sidebar').toggleClass('collapsed');

            // Save state to localStorage
            localStorage.setItem('sidebar-collapsed', $('#sidebar').hasClass('collapsed'));
        });

        // Restore sidebar state
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            $('#sidebar').addClass('collapsed');
        }

        // Mobile sidebar toggle
        if ($(window).width() < 992) {
            $('#sidebar').removeClass('collapsed');
            $('.sidebar-toggle').on('click', function(e) {
                e.preventDefault();
                $('#sidebar').toggleClass('show');
            });
        }

        // Submenu toggle
        $('.sidebar-submenu').each(function() {
            var $submenu = $(this);
            var $link = $submenu.prev('.sidebar-link');

            // Check if any child is active
            if ($submenu.find('a.active').length > 0) {
                $submenu.addClass('show');
                $link.attr('aria-expanded', 'true');
            }
        });
    }

    /**
     * Initialize Bootstrap tooltips
     */
    function initTooltips() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    /**
     * Initialize Select2 dropdowns
     */
    function initSelect2() {
        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                width: '100%',
                closeOnSelect: false
            });
        }
    }

    /**
     * Initialize Date Range Picker
     */
    function initDateRangePicker() {
        if ($.fn.daterangepicker) {
            $('.daterangepicker-input').daterangepicker({
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear',
                    format: 'DD/MM/YYYY'
                },
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            });

            $('.daterangepicker-input').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
            });

            $('.daterangepicker-input').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });
        }
    }

    /**
     * Initialize DataTables with default settings
     */
    function initDataTables() {
        if ($.fn.DataTable) {
            $.extend(true, $.fn.dataTable.defaults, {
                language: {
                    search: '',
                    searchPlaceholder: 'Search...',
                    lengthMenu: 'Show _MENU_ entries',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        previous: '<i class="fas fa-angle-left"></i>'
                    }
                },
                pageLength: 25,
                responsive: true,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
        }
    }

    /**
     * Server time display
     */
    function initServerTime() {
        var $serverTime = $('#server-time');
        if ($serverTime.length) {
            function updateTime() {
                var now = new Date();
                $serverTime.text(now.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }));
            }
            updateTime();
            setInterval(updateTime, 1000);
        }
    }

    /**
     * Real-time alerts
     */
    function initAlerts() {
        // Check for new alerts every 30 seconds
        setInterval(checkAlerts, 30000);
    }

    function checkAlerts() {
        $.get('/areports/api/realtime/stats', function(data) {
            if (data.alerts && data.alerts.length > 0) {
                updateAlertBadge(data.alerts.length);
                updateAlertDropdown(data.alerts);
            }
        }).fail(function() {
            // Silently fail
        });
    }

    function updateAlertBadge(count) {
        var $badge = $('.alert-badge');
        if (count > 0) {
            $badge.text(count).removeClass('d-none');
        } else {
            $badge.addClass('d-none');
        }
    }

    function updateAlertDropdown(alerts) {
        var $list = $('.alert-list');
        $list.empty();

        alerts.forEach(function(alert) {
            var html = '<a href="/areports/alerts/' + alert.id + '" class="dropdown-item">' +
                       '<div class="d-flex align-items-center">' +
                       '<i class="fas fa-exclamation-circle text-danger me-2"></i>' +
                       '<div>' +
                       '<div class="small text-muted">' + alert.time + '</div>' +
                       '<div>' + escapeHtml(alert.message) + '</div>' +
                       '</div></div></a>';
            $list.append(html);
        });
    }

    /**
     * Utility Functions
     */
    window.aReports = {
        // Show toast notification
        toast: function(message, type) {
            type = type || 'info';
            if (typeof toastr !== 'undefined') {
                toastr[type](message);
            } else {
                alert(message);
            }
        },

        // Show loading overlay
        showLoading: function($element) {
            $element = $element || $('body');
            $element.append('<div class="loading-overlay"><div class="loader-spinner"></div></div>');
        },

        // Hide loading overlay
        hideLoading: function($element) {
            $element = $element || $('body');
            $element.find('.loading-overlay').remove();
        },

        // Format duration
        formatDuration: function(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var secs = seconds % 60;

            if (hours > 0) {
                return hours + ':' + padZero(minutes) + ':' + padZero(secs);
            }
            return minutes + ':' + padZero(secs);
        },

        // Confirm action
        confirm: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },

        // Refresh data
        refreshData: function(url, $container, callback) {
            $.get(url, function(data) {
                if (typeof callback === 'function') {
                    callback(data);
                }
            }).fail(function(xhr) {
                aReports.toast('Failed to refresh data', 'error');
            });
        }
    };

    function padZero(num) {
        return num < 10 ? '0' + num : num;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Configure Toastr
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 5000
        };
    }

})(jQuery);
