jQuery(function($){
    // Admin dashboard functionality
    // Initialize DataTables on our plugin tables if available
    if ($.fn.DataTable) {
        $('.smp-datatable').each(function(){
            var table = $(this);
            if (!$.fn.dataTable.isDataTable(table)) {
                table.DataTable({
                    pageLength: 10,
                    order: [],
                    autoWidth: false,
                    language: { search: 'Search:' },
                    responsive: true,
                    paging: true,
                    pagingType: "simple_numbers",
                    ordering: false,
                    layout: {
                        topStart: 'pageLength',
                        topEnd: 'search',
                        bottomStart: 'info',
                        bottomEnd: 'paging'
                    },
                });
            }
        });
    }

    // Plan Modal handlers
    var smpPlanModalEl = document.getElementById('smpPlanModal');
    var smpPlanModal = smpPlanModalEl ? new bootstrap.Modal(smpPlanModalEl) : null;

    $(document).on('click', '#smpAddPlanBtn', function(e){
        if (!smpPlanModal) return;
        resetPlanForm();
        $('#smpPlanModalLabel').text('Add Plan');
        smpPlanModal.show();
    });

    $(document).on('click', '.smp-edit-plan', function(e){
        e.preventDefault();
        if (!smpPlanModal) return;
        var plan = $(this).data('plan');
        try { if (typeof plan === 'string') plan = JSON.parse(plan); } catch(e) {}
        fillPlanForm(plan);
        $('#smpPlanModalLabel').text('Edit Plan');
        smpPlanModal.show();
    });

    $(document).on('click', '#smpSavePlanBtn', function(){
        // Ensure wp_editor content is synced
        if (typeof tinymce !== 'undefined' && tinymce.get && tinymce.get('smp_plan_description')) {
            tinymce.triggerSave();
        }
        var form = $('#smp-plan-form');
        var data = form.serializeArray().reduce(function(o, x){ o[x.name] = x.value; return o; }, {});
        // Client-side validation
        var errors = [];
        if (!data.name || data.name.trim().length < 3) errors.push('Name must be at least 3 characters'); // Name is required
        if (data.slug && !/^[-a-z0-9]+$/i.test(data.slug)) errors.push('Slug must be alphanumeric/dashes only'); // Slug is required
        var amount = parseFloat(data.amount || '0');
        if (isNaN(amount) || amount < 0) errors.push('Amount must be zero or positive'); // Amount is required
        var expiry = parseInt(data.expiry_in_months || '1', 10);
        if (isNaN(expiry) || expiry <= 0) errors.push('Expiry must be a positive integer'); // Expiry is required
        var sequence = parseInt(data.sequence || '0', 10);
        if (isNaN(sequence) || sequence < 0) errors.push('Sequence must be zero or greater'); // Sequence is required
        if (!data.status || ['active','inactive'].indexOf(data.status) === -1) errors.push('Select a valid status'); // Status is required
        if (errors.length) { alert(errors.join('\n')); return; }
        data.action = 'smp_save_plan';
        data.nonce = subscription_ajax.nonce;
        $.post(subscription_ajax.ajax_url, data, function(resp){
            if (resp && resp.success) {
                location.reload();
            } else {
                alert(resp && resp.data && resp.data.message ? resp.data.message : 'Save failed');
            }
        });
    });

    $(document).on('click', '.smp-delete-plan', function(e){
        e.preventDefault();
        if (!confirm('Delete this plan?')) return;
        var id = $(this).data('id');
        $.post(subscription_ajax.ajax_url, { action: 'smp_delete_plan', id: id, nonce: subscription_ajax.nonce }, function(resp){
            if (resp && resp.success) {
                location.reload();
            } else {
                alert(resp && resp.data && resp.data.message ? resp.data.message : 'Delete failed');
            }
        });
    });

    function resetPlanForm(){
        $('#smp_plan_id').val('');
        $('#smp_plan_name').val('');
        $('#smp_plan_slug').val('');
        $('#smp_plan_description').val('');
        $('#smp_plan_amount').val('0');
        $('#smp_plan_expiry').val('1');
        $('#smp_plan_sequence').val('0');
        $('#smp_plan_status').val('active');
    }
    function fillPlanForm(p){
        $('#smp_plan_id').val(p.id || '');
        $('#smp_plan_name').val(p.name || '');
        $('#smp_plan_slug').val(p.slug || '');
        if (typeof tinymce !== 'undefined' && tinymce.get && tinymce.get('smp_plan_description')) {
            tinymce.get('smp_plan_description').setContent(p.description || '');
        } else {
            $('#smp_plan_description').val(p.description || '');
        }
        $('#smp_plan_amount').val(p.amount || '0');
        $('#smp_plan_expiry').val(p.expiry_in_months || '1');
        $('#smp_plan_sequence').val(p.sequence || '0');
        $('#smp_plan_status').val(p.status || 'active');
    }
    
    // View subscription details
    $(document).on('click', '.view-subscription', function(e) {
        e.preventDefault();
        var subscriptionId = $(this).data('id');
        
        $.ajax({
            url: subscription_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_subscription_details',
                subscription_id: subscriptionId,
                nonce: subscription_ajax.nonce
            },
            success: function(response) {
                if(response.success) {
                    showSubscriptionModal(response.data);
                } else {
                    alert('Error loading subscription details');
                }
            }
        });
    });
    
    // Show subscription modal
    function showSubscriptionModal(subscription) {
        var modalHtml = '<div class="subscription-modal">' +
            '<div class="modal-content">' +
                '<div class="modal-header">' +
                    '<h3>Subscription Details</h3>' +
                    '<span class="close">&times;</span>' +
                '</div>' +
                '<div class="modal-body">' +
                    '<table class="subscription-details">' +
                        '<tr><td><strong>User:</strong></td><td>' + subscription.user_name + '</td></tr>' +
                        '<tr><td><strong>Email:</strong></td><td>' + subscription.user_email + '</td></tr>' +
                        '<tr><td><strong>Plan:</strong></td><td>' + subscription.plan_name + '</td></tr>' +
                        '<tr><td><strong>Amount:</strong></td><td>' + subscription.plan_amount + '</td></tr>' +
                        '<tr><td><strong>Status:</strong></td><td>' + subscription.status + '</td></tr>' +
                        '<tr><td><strong>Created:</strong></td><td>' + subscription.created_at + '</td></tr>' +
                        '<tr><td><strong>Payment ID:</strong></td><td>' + (subscription.payment_id || 'N/A') + '</td></tr>' +
                    '</table>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        $('body').append(modalHtml);
        $('.subscription-modal').fadeIn();
        
        // Close modal
        $('.subscription-modal .close, .subscription-modal').click(function(e) {
            if(e.target === this) {
                $('.subscription-modal').fadeOut(function() {
                    $(this).remove();
                });
            }
        });
    }
    
    // Export subscriptions
    $(document).on('click', '.export-subscriptions', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: subscription_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'export_subscriptions',
                nonce: subscription_ajax.nonce
            },
            success: function(response) {
                if(response.success) {
                    window.open(response.data.download_url, '_blank');
                } else {
                    alert('Error exporting subscriptions');
                }
            }
        });
    });
    
    // Export transactions
    $(document).on('click', '.export-transactions', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: subscription_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'export_transactions',
                nonce: subscription_ajax.nonce
            },
            success: function(response) {
                if(response.success) {
                    window.open(response.data.download_url, '_blank');
                } else {
                    alert('Error exporting transactions');
                }
            }
        });
    });
    
    // Bulk actions
    $(document).on('click', '.bulk-action-button', function(e) {
        e.preventDefault();
        
        var action = $(this).data('action');
        var selectedItems = $('.subscription-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if(selectedItems.length === 0) {
            alert('Please select items to perform bulk action');
            return;
        }
        
        if(confirm('Are you sure you want to perform this action on ' + selectedItems.length + ' items?')) {
            $.ajax({
                url: subscription_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bulk_action_subscriptions',
                    bulk_action: action,
                    subscription_ids: selectedItems,
                    nonce: subscription_ajax.nonce
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('Error performing bulk action');
                    }
                }
            });
        }
    });
    
    // Select all checkbox
    $(document).on('change', '.select-all-checkbox', function() {
        var isChecked = $(this).is(':checked');
        $('.subscription-checkbox').prop('checked', isChecked);
    });
    
    // Individual checkbox change
    $(document).on('change', '.subscription-checkbox', function() {
        var totalCheckboxes = $('.subscription-checkbox').length;
        var checkedCheckboxes = $('.subscription-checkbox:checked').length;
        
        $('.select-all-checkbox').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
    
    // Search functionality
    $(document).on('keyup', '.subscription-search', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.subscription-row').each(function() {
            var rowText = $(this).text().toLowerCase();
            if(rowText.indexOf(searchTerm) === -1) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
    });
    
    // Filter by status
    $(document).on('change', '.status-filter', function() {
        var status = $(this).val();
        
        if(status === '') {
            $('.subscription-row').show();
        } else {
            $('.subscription-row').hide();
            $('.subscription-row[data-status="' + status + '"]').show();
        }
    });
    
    // Date range filter
    $(document).on('change', '.date-range-filter', function() {
        var startDate = $('.start-date').val();
        var endDate = $('.end-date').val();
        
        if(startDate && endDate) {
            $('.subscription-row').each(function() {
                var rowDate = new Date($(this).data('date'));
                var start = new Date(startDate);
                var end = new Date(endDate);
                
                if(rowDate >= start && rowDate <= end) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            $('.subscription-row').show();
        }
    });
    
    // Refresh dashboard stats
    $(document).on('click', '.refresh-stats', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: subscription_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'refresh_dashboard_stats',
                nonce: subscription_ajax.nonce
            },
            success: function(response) {
                if(response.success) {
                    $('.stat-card h3').each(function(index) {
                        $(this).text(response.data.stats[index]);
                    });
                }
            }
        });
    });
    
    // Test payment gateway connection
    $(document).on('click', '.test-gateway-connection', function(e) {
        e.preventDefault();
        
        var gateway = $('#payment_gateway').val();
        
        $.ajax({
            url: subscription_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'test_gateway_connection',
                gateway: gateway,
                nonce: subscription_ajax.nonce
            },
            beforeSend: function() {
                $(this).text('Testing...').prop('disabled', true);
            },
            success: function(response) {
                if(response.success) {
                    alert('Connection successful!');
                } else {
                    alert('Connection failed: ' + response.data.message);
                }
            },
            complete: function() {
                $('.test-gateway-connection').text('Test Connection').prop('disabled', false);
            }
        });
    });
});
