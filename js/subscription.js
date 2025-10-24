jQuery(function($){
    var defaultNotes = {};
    $(document).ready(function(){
        defaultNotes = $('.proceedSubscriptionPayment').attr('data-notes') ? JSON.parse($('.proceedSubscriptionPayment').attr('data-notes')) : {};
    });

    // Initialize Swiper for plans
    if (typeof Swiper !== 'undefined') {
        // Count total plans to determine if navigation is needed
        var totalPlans = $('#smpPlansSwiper .swiper-slide').length;
        var showNavigation = totalPlans > 4;
        
        var swiper = new Swiper('#smpPlansSwiper', {
            slidesPerView: 4,
            spaceBetween: 16,
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { 
                nextEl: '.swiper-button-next', 
                prevEl: '.swiper-button-prev',
                enabled: showNavigation
            },
            breakpoints: {
                320: { slidesPerView: 1 },
                576: { slidesPerView: 2 },
                768: { slidesPerView: 3 },
                992: { slidesPerView: 4 }
            },
            centerInsufficientSlides: true,
        });
        
        // Show/hide navigation buttons based on plan count
        if (!showNavigation) {
            $('.swiper-button-prev, .swiper-button-next').hide();
        }
    }
    
    // Handle plan selection
    $(document).on("click", ".selectPlanButton", function(e){
        e.preventDefault();
        
        var planId = $(this).attr('data-plan-id');
        
        if(parseInt(planId) > 0){
            // Remove selected state from all cards
            $('.plan-card').removeClass('selected');
            $('.selected-indicator').addClass('d-none');
            
            // Add selected state to current card
            var currentCard = $(this).closest('.plan-card');
            currentCard.addClass('selected');
            currentCard.find('.selected-indicator').removeClass('d-none');
            
            // Update the main continue button with selected plan data
            var planAmount = $(this).attr('data-plan-amount');
            var planExpiryInMonths = $(this).attr('data-plan-expiry-in-months');
            
            $('.proceedSubscriptionPayment').attr('data-plan-id', planId);
            $('.proceedSubscriptionPayment').attr('data-plan-amount', planAmount);
            $('.proceedSubscriptionPayment').attr('data-plan-expiry-in-months', planExpiryInMonths);
            // If the plan/button has any notes (JSON string), forward it to the main button so it can be sent to server
            // Before that first fetch existing notes (if any) on proceed button then append/merge new notes
            var notes = {};
            // var storedNotes = $('.proceedSubscriptionPayment').attr('data-notes');
            if (defaultNotes) {
                // var existingNotes = JSON.parse(storedNotes);
                notes = defaultNotes || {};
            }
            var notesAttr = $(this).attr('data-notes');
            // Some attributes may be undefined; check safely
            if(typeof notesAttr !== 'undefined' && notesAttr !== null && String(notesAttr).length > 0){
                // convert notesAttr to object
                var newNotes = JSON.parse(notesAttr);
                // Merge existing notes with new notes
                notes = Object.assign({}, notes, newNotes);
                // Set merged notes back as JSON string on proceed button
                $('.proceedSubscriptionPayment').attr('data-notes', JSON.stringify(notes));
            }else{
                $('.proceedSubscriptionPayment').attr('data-notes', JSON.stringify(defaultNotes));
            }
        }
    });
    
    /** 
     * On click event for main continue to pay btn 
     */
    $(document).on("click", ".proceedSubscriptionPayment", function(e){
        var currentUserId = $("#current_user_id").val();
        var redirectTo = $("#redirect_to").val();
        var planId = $(this).attr('data-plan-id');
        
        if(parseInt(planId) > 0){
            var planAmount = $(this).attr('data-plan-amount');
            var planExpiryInMonths = $(this).attr('data-plan-expiry-in-months');
            // Try to read notes (if any) that were set on the proceed button
            var notesData = $('.proceedSubscriptionPayment').attr('data-notes');
            var requestParams = { 
                'user_id': currentUserId,
                'plan_id': planId,
                'plan_amount': planAmount,
                'plan_expiry_in_months': planExpiryInMonths,
                'redirect_to': redirectTo,
                'notes': notesData || null
            };
            // Referral is handled by theme override; plugin JS does not send referral data
            $.ajax({
                url: subscription_ajax.rest_url+"/subscription/store-user-subscription",
                type: 'POST',
                data: requestParams,
                dataType: 'json',
                headers: {
                    'X-API-Call-Type': 'current-site',
                },
                beforeSend: function(){
                    Swal.fire({
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        allowOutsideClick: false,
                    });
                },
                success: function(response){
                    Swal.close();
                    if( response.hasOwnProperty("status") ){
                        var status = response.status;
                        if( status == 1 ){
                            // Trial path: show success and complete locally
                            if (response.trial === true) {
                                // Send completion call for trial to create a zero-amount transaction and fire hooks
                                var trialNotes = response.notes || null;
                                completeTrial(response.subscription_id, trialNotes, function(){
                                    $("#subscription_successful_section").removeClass('d-none');
                                    $("#subscription_plans_section").addClass('d-none');
                                });
                                return;
                            }

                            // Build notes for checkout (take from proceed button if available)
                            var notes = {};
                            try {
                                var storedNotes = $('.proceedSubscriptionPayment').attr('data-notes');
                                if (storedNotes) {
                                    var existingNotes = JSON.parse(storedNotes);
                                    notes = existingNotes || {};
                                }
                            } catch (e) {
                                notes = {};
                            }
                            if (response.subscription_id !== undefined && response.subscription_id !== null) {
                                notes.subscription_id = response.subscription_id;
                            }
                            // Initialize Razorpay payment
                            var options = {
                                "key": response.payment_data.key_id,
                                "amount": response.payment_data.amount,
                                "currency": response.payment_data.currency,
                                "name": "TownSol Sports",
                                "description": "Subscription Payment",
                                "order_id": response.payment_data.order_id,
                                "notes": notes,
                                "handler": function (paymentResponse){
                                    // Complete payment
                                    completePayment(response.subscription_id, paymentResponse);
                                },
                                "prefill": {
                                    "name": "",
                                    "email": "",
                                    "contact": ""
                                },
                                "notes": notes,
                                "theme": {
                                    "color": "#3399cc"
                                }
                            };
                            var rzp = new Razorpay(options);
                            rzp.open();
                        }
                        else{
                            Swal.fire({
                                icon: 'warning',
                                text: 'Something went wrong while purchasing the subscription. Please try again.',
                                showCancelButton: false,
                                allowOutsideClick: false,
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.reload();
                                }
                            });
                        }
                    }
                    else{
                        Swal.fire({
                            icon: 'warning',
                            text: 'Something went wrong while purchasing the subscription. Please try again.',
                            showCancelButton: false,
                            allowOutsideClick: false,
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.reload();
                            }
                        });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({
                        icon: 'warning',
                        text: 'Something went wrong while purchasing the subscription. Please try again.',
                        showCancelButton: false,
                        allowOutsideClick: false,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.reload();
                        }   
                    });
                }
            });
        }else{
            $(this).removeClass("disabled"); // Make btn enabled
            Swal.fire({
                icon: 'warning',
                text: 'Please select plan.',
                allowOutsideClick: false,
            });
            return;
        }
    });
    
    // Complete payment function
    function completePayment(subscriptionId, paymentResponse) {
        $.ajax({
            url: subscription_ajax.rest_url+"/subscription/complete-payment",
            type: 'POST',
            data: {
                'subscription_id': subscriptionId,
                'payment_id': paymentResponse.razorpay_payment_id,
                'order_id': paymentResponse.razorpay_order_id,
                'signature': paymentResponse.razorpay_signature
            },
            dataType: 'json',
            beforeSend: function(){
                Swal.fire({
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                });
            },
            success: function(response){
                Swal.close();
                if(response.status == 1) {
                    $("#subscription_successful_section").removeClass('d-none'); // Show successful subscription section
                    $("#subscription_plans_section").addClass('d-none'); // Hide subscription plans section
                } else {
                    Swal.fire({
                        icon: 'error',
                        text: 'Payment verification failed. Please contact support.',
                        allowOutsideClick: false,
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    text: 'Payment verification failed. Please contact support.',
                    allowOutsideClick: false,
                });
            }
        });
    }

    // Complete trial subscription function
    function completeTrial(subscriptionId, notes, onDone) {
        $.ajax({
            url: subscription_ajax.rest_url+"/subscription/complete-payment",
            type: 'POST',
            data: {
                'subscription_id': subscriptionId,
                'trial': 1,
                'notes': notes || null
            },
            dataType: 'json',
            beforeSend: function(){
                Swal.fire({
                    didOpen: () => { Swal.showLoading(); },
                    allowOutsideClick: false,
                });
            },
            success: function(response){
                Swal.close();
                if(response.status == 1) {
                    if (typeof onDone === 'function') onDone();
                } else {
                    Swal.fire({
                        icon: 'error',
                        text: 'Trial activation failed. Please contact support.',
                        allowOutsideClick: false,
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    text: 'Trial activation failed. Please contact support.',
                    allowOutsideClick: false,
                });
            }
        });
    }
});
