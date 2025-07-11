jQuery(document).ready(function($) {
    // Show popup on page load - FIRST POPUP LOGIC
    setTimeout(function() {
        // Check if popup hasn't been shown in this session
        if (!sessionStorage.getItem('referral_popup_shown')) {
            $('#referral-popup').fadeIn();
            // Mark as shown for this session
            sessionStorage.setItem('referral_popup_shown', 'true');
        }
    }, 2000);

    // Close popup
    $('.close-popup').click(function() {
        $('#referral-popup').fadeOut();
    });

    // Close on outside click
    $(window).click(function(e) {
        if ($(e.target).is('#referral-popup')) {
            $('#referral-popup').fadeOut();
        }
    });

    // Copy referral link
    $('#popup-copy-link').click(function() {
        var linkInput = document.getElementById('popup-referral-link');
        linkInput.select();
        document.execCommand('copy');
        $(this).text('Copied!');
        setTimeout(() => {
            $(this).text('Copy');
        }, 2000);
    });

    document.getElementById('create-account-link').addEventListener('click', function(e) {
        e.preventDefault();
        var signupForm = document.getElementById('customer-signup-form');
        // Toggle the visibility of the registration form
        if (signupForm.style.display === "none") {
            signupForm.style.display = "block";
        } else {
            signupForm.style.display = "none";
        }
    });

    // Handle login form submission
    $('#referral-login-form').submit(function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        
        submitButton.prop('disabled', true).text('Logging in...');
        
        $.ajax({
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'login'),
            type: 'POST',
            data: {
                username: $('#username').val(),
                password: $('#password').val(),
                security: wcReferralPopup.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Get referral content after successful login
                    $.ajax({
                        url: wcReferralPopup.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_referral_content',
                            nonce: wcReferralPopup.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#referral-popup-body').html(response.data.content);
                            }
                        }
                    });
                } else {
                    alert('Login failed. Please check your credentials.');
                    submitButton.prop('disabled', false).text('Login');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                submitButton.prop('disabled', false).text('Login');
            }
        });
    });
});

jQuery(document).ready(function($) {
    
    // Show popup on page load (with delay) - SECOND POPUP LOGIC
    setTimeout(function() {
        if ($('#wc-referral-popup').length && !sessionStorage.getItem('wc_referral_popup_shown')) {
            $('#wc-referral-popup').fadeIn();
            sessionStorage.setItem('wc_referral_popup_shown', 'true');
        }
    }, 3000);
    
    // Show popup when widget is clicked
    $(document).on('click', '.wc-referral-widget', function() {
        $('#wc-referral-popup').fadeIn();
    });
    
    // Close popup
    $(document).on('click', '.wc-referral-close, .wc-referral-popup', function(e) {
        if (e.target === this) {
            $('#wc-referral-popup').fadeOut();
        }
    });
    
    // Check email
    $(document).on('click', '#wc-referral-check-email', function() {
        var email = $('#wc-referral-email').val();
        
        if (!email || !isValidEmail(email)) {
            showMessage('Please enter a valid email address.', 'error');
            return;
        }
        
        $.ajax({
            url: wc_referral_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_referral_check_email',
                email: email,
                nonce: wc_referral_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.exists) {
                        $('#wc-referral-password-section').show();
                        $('#wc-referral-register-section').hide();
                        $('#wc-referral-check-email').hide();
                    } else {
                        $('#wc-referral-register-section').show();
                        $('#wc-referral-password-section').hide();
                        $('#wc-referral-check-email').hide();
                    }
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage(wc_referral_ajax.messages.error, 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Login user
    $(document).on('click', '#wc-referral-submit', function() {
        var email = $('#wc-referral-email').val();
        var password = $('#wc-referral-password').val();
        
        if (!email || !password) {
            showMessage('Please fill in all fields.', 'error');
            return;
        }
        
        $.ajax({
            url: wc_referral_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_referral_login',
                email: email,
                password: password,
                nonce: wc_referral_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wc-referral-auth-section').html(response.data.content);
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage(wc_referral_ajax.messages.error, 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Register user
    $(document).on('click', '#wc-referral-register', function() {
        var email = $('#wc-referral-email').val();
        var password = $('#wc-referral-new-password').val();
        
        if (!email || !password) {
            showMessage('Please fill in all fields.', 'error');
            return;
        }
        
        if (password.length < 6) {
            showMessage('Password must be at least 6 characters long.', 'error');
            return;
        }
        
        $.ajax({
            url: wc_referral_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_referral_register',
                email: email,
                password: password,
                nonce: wc_referral_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wc-referral-auth-section').html(response.data.content);
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage(wc_referral_ajax.messages.error, 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Copy referral link
    $(document).on('click', '#wc-referral-copy, .wc-referral-copy-btn', function() {
        var input = $(this).siblings('input').length ? 
                   $(this).siblings('input') : 
                   $(this).parent().find('input');
        
        if (input.length) {
            input.select();
            input[0].setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                
                var button = $(this);
                var originalText = button.text();
                button.text('Copied!').addClass('copied');
                
                setTimeout(function() {
                    button.text(originalText).removeClass('copied');
                }, 2000);
                
                showMessage(wc_referral_ajax.messages.copied, 'success');
            } catch (err) {
                // Fallback for older browsers
                showMessage('Please manually copy the link', 'error');
            }
        }
    });
    
    // Handle Enter key in forms
    $(document).on('keypress', '#wc-referral-email', function(e) {
        if (e.which === 13) {
            $('#wc-referral-check-email').click();
        }
    });
    
    $(document).on('keypress', '#wc-referral-password', function(e) {
        if (e.which === 13) {
            $('#wc-referral-submit').click();
        }
    });
    
    $(document).on('keypress', '#wc-referral-new-password', function(e) {
        if (e.which === 13) {
            $('#wc-referral-register').click();
        }
    });
    
    // Helper functions
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showMessage(message, type) {
        // Remove existing messages
        $('.wc-referral-message').remove();
        
        var messageHtml = '<div class="wc-referral-message ' + type + '">' + message + '</div>';
        $('.wc-referral-popup-inner').prepend(messageHtml);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $('.wc-referral-message.success').fadeOut();
            }, 3000);
        }
    }
    
    // Track referral link clicks
    $(document).on('click', 'a[href*="?ref="]', function() {
        // You can add analytics tracking here
        console.log('Referral link clicked:', $(this).attr('href'));
    });
    
    // Smooth animations for stat boxes
    if ($('.wc-referral-stat-box').length) {
        $('.wc-referral-stat-box').each(function(index) {
            $(this).delay(index * 100).animate({
                opacity: 1,
                transform: 'translateY(0)'
            }, 500);
        });
    }
    
    // UPDATED: Check if user can use referral coupon before auto-applying
    function checkAndAutoApplyCoupon() {
        if (typeof wc_referral_ajax !== 'undefined' && wc_referral_ajax.has_referral_code) {
            // First check if user has already used this referral coupon
            $.ajax({
                url: wc_referral_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_referral_check_coupon_usage',
                    nonce: wc_referral_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.can_use_coupon) {
                            // User can use the coupon, proceed with auto-apply
                            if ($('body').hasClass('woocommerce-cart') || $('body').hasClass('woocommerce-checkout')) {
                                // Check if coupon is not already applied
                                var appliedCoupons = $('.woocommerce-remove-coupon').length;
                                if (appliedCoupons === 0) {
                                    // Trigger coupon application
                                    $('body').trigger('update_checkout');
                                }
                            }
                        } else {
                            // User has already used this referral coupon
                            console.log('User has already used this referral coupon');
                            
                            // Optionally show a message to user
                            if (response.data.message) {
                                // Only show message on cart/checkout pages to avoid spam
                                if ($('body').hasClass('woocommerce-cart') || $('body').hasClass('woocommerce-checkout')) {
                                    // Create a subtle notice that doesn't interfere with checkout
                                    if ($('.woocommerce-notices-wrapper').length && !$('.wc-referral-used-notice').length) {
                                        $('.woocommerce-notices-wrapper').prepend(
                                            '<div class="woocommerce-info wc-referral-used-notice" style="margin-bottom: 10px;">' + 
                                            response.data.message + 
                                            '</div>'
                                        );
                                        
                                        // Auto-hide the notice after 5 seconds
                                        setTimeout(function() {
                                            $('.wc-referral-used-notice').fadeOut();
                                        }, 5000);
                                    }
                                }
                            }
                        }
                    } else {
                        // Error checking coupon usage, don't auto-apply
                        console.log('Error checking coupon usage:', response.data.message);
                    }
                },
                error: function() {
                    // Error in AJAX call, don't auto-apply to be safe
                    console.log('AJAX error when checking coupon usage');
                }
            });
        }
    }
    
    // Auto-apply coupon when page loads if referral code exists (with usage check)
    setTimeout(function() {
        checkAndAutoApplyCoupon();
    }, 1000);
    
    // Re-check when cart/checkout page updates
    $(document.body).on('updated_cart_totals updated_checkout', function() {
        // Small delay to ensure DOM is updated
        setTimeout(function() {
            checkAndAutoApplyCoupon();
        }, 500);
    });
    
    // ADDITIONAL: Track coupon usage in session to prevent multiple attempts
    var couponCheckAttempted = false;
    
    // Override the checkAndAutoApplyCoupon function to prevent multiple calls
    function checkAndAutoApplyCoupon() {
        // Prevent multiple simultaneous checks
        if (couponCheckAttempted) {
            return;
        }
        
        if (typeof wc_referral_ajax !== 'undefined' && wc_referral_ajax.has_referral_code) {
            couponCheckAttempted = true;
            
            $.ajax({
                url: wc_referral_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_referral_check_coupon_usage',
                    nonce: wc_referral_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.can_use_coupon) {
                            // User can use the coupon, proceed with auto-apply
                            if ($('body').hasClass('woocommerce-cart') || $('body').hasClass('woocommerce-checkout')) {
                                // Check if coupon is not already applied
                                var appliedCoupons = $('.woocommerce-remove-coupon').length;
                                if (appliedCoupons === 0) {
                                    // Trigger coupon application
                                    $('body').trigger('update_checkout');
                                }
                            }
                        } else {
                            // User has already used this referral coupon
                            console.log('User has already used this referral coupon');
                            
                            // Clear referral code from session/cookie to prevent future attempts
                            $.ajax({
                                url: wc_referral_ajax.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'wc_referral_clear_used_code',
                                    nonce: wc_referral_ajax.nonce
                                }
                            });
                            
                            // Show message once
                            if (response.data.message && !sessionStorage.getItem('referral_used_message_shown')) {
                                if ($('body').hasClass('woocommerce-cart') || $('body').hasClass('woocommerce-checkout')) {
                                    if ($('.woocommerce-notices-wrapper').length) {
                                        $('.woocommerce-notices-wrapper').prepend(
                                            '<div class="woocommerce-info wc-referral-used-notice" style="margin-bottom: 10px;">' + 
                                            response.data.message + 
                                            '</div>'
                                        );
                                        
                                        sessionStorage.setItem('referral_used_message_shown', 'true');
                                        
                                        setTimeout(function() {
                                            $('.wc-referral-used-notice').fadeOut();
                                        }, 5000);
                                    }
                                }
                            }
                        }
                    }
                },
                complete: function() {
                    // Reset flag after a delay to allow for page changes
                    setTimeout(function() {
                        couponCheckAttempted = false;
                    }, 3000);
                }
            });
        }
    }
});

jQuery(document).ready(function($) {
    
    // Show popup on page load (with delay) - THIRD POPUP LOGIC
    setTimeout(function() {
        if ($('#referral-popup').length && !sessionStorage.getItem('wrs_referral_popup_shown')) {
            $('#referral-popup').fadeIn();
            sessionStorage.setItem('wrs_referral_popup_shown', 'true');
        }
    }, 3000);
    
    // Show popup when widget is clicked
    $(document).on('click', '.wc-referral-widget', function() {
        $('#referral-popup').fadeIn();
    });
    
    // Close popup
    $(document).on('click', '.close-popup, .referral-popup', function(e) {
        if (e.target === this) {
            $('#referral-popup').fadeOut();
        }
    });
    
    // Check email handler - matches PHP action 'wrs_check_email'
    $(document).on('click', '#wrs-check-email', function() {
        var email = $('#wrs-email').val();
        
        if (!email || !isValidEmail(email)) {
            showMessage(wrsData.texts.invalid_email, 'error');
            return;
        }

        
        $.ajax({
            url: wrsData.ajaxurl,
            type: 'POST',
            data: {
                action: 'wrs_check_email', // Matches PHP handler
                email: email,
                nonce: wrsData.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.exists) {
                        // Show login form
                        $('#wrs-step-email').hide();
                        $('#wrs-step-login').show();
                        $('#wrs-login-message').text(response.data.message);
                    } else {
                        // Show register form
                        $('#wrs-step-email').hide();
                        $('#wrs-step-register').show();
                        $('#wrs-register-message').text(response.data.message);
                    }
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Something went wrong. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Login handler - matches PHP action 'wrs_login'
    $(document).on('click', '#wrs-login-button', function() {
        var email = $('#wrs-email').val();
        var password = $('#wrs-login-password').val();
        
        if (!email || !password) {
            showMessage('Please fill in all fields.', 'error');
            return;
        }

        
        $.ajax({
            url: wrsData.ajaxurl,
            type: 'POST',
            data: {
                action: 'wrs_login', // Matches PHP handler
                email: email,
                password: password,
                nonce: wrsData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Hide login form and show referral widget
                    $('#wrs-step-login').hide();
                    $('#wrs-step-referral').show().html(response.data.widget_html);
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Login failed. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Register handler - matches PHP action 'wrs_register'
    $(document).on('click', '#wrs-register-button', function() {
        var email = $('#wrs-email').val();
        var password = $('#wrs-register-password').val();
        
        if (!email || !password) {
            showMessage('Please fill in all fields.', 'error');
            return;
        }
        
        if (password.length < 6) {
            showMessage('Password must be at least 6 characters long.', 'error');
            return;
        }

        
        $.ajax({
            url: wrsData.ajaxurl,
            type: 'POST',
            data: {
                action: 'wrs_register', // Matches PHP handler
                email: email,
                password: password,
                nonce: wrsData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Hide register form and show referral widget
                    $('#wrs-step-register').hide();
                    $('#wrs-step-referral').show().html(response.data.widget_html);
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Registration failed. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Copy referral link functionality
    $(document).on('click', '#widget-copy-link, #copy-referral-link, .widget-copy-button', function() {
        var input = $(this).siblings('input').length ? 
                   $(this).siblings('input') : 
                   $(this).parent().find('input');
        
        if (input.length) {
            input.select();
            input[0].setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                
                var button = $(this);
                var originalText = button.text();
                button.text('Copied!').addClass('copied');
                
                // Show copy message
                $('#widget-copy-message, #copy-message').show();
                
                setTimeout(function() {
                    button.text(originalText).removeClass('copied');
                    $('#widget-copy-message, #copy-message').hide();
                }, 2000);
                
            } catch (err) {
                // Fallback for older browsers
                showMessage('Please manually copy the link', 'error');
            }
        }
    });
    
    // Handle Enter key in forms
    $(document).on('keypress', '#wrs-email', function(e) {
        if (e.which === 13) {
            $('#wrs-check-email').click();
        }
    });
    
    $(document).on('keypress', '#wrs-login-password', function(e) {
        if (e.which === 13) {
            $('#wrs-login-button').click();
        }
    });
    
    $(document).on('keypress', '#wrs-register-password', function(e) {
        if (e.which === 13) {
            $('#wrs-register-button').click();
        }
    });
    
    // Helper functions
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showMessage(message, type) {
        // Remove existing messages
        $('.wrs-message').remove();
        
        var messageHtml = '<div class="wrs-message wrs-message-' + type + '">' + message + '</div>';
        $('.wrs-popup-container').prepend(messageHtml);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $('.wrs-message.wrs-message-success').fadeOut();
            }, 3000);
        }
    }
    
    // REMOVED: Auto-show popup for new visitors using localStorage
    // This was causing the popup to show repeatedly
    
    // Track referral link clicks
    $(document).on('click', 'a[href*="?ref="]', function() {
        console.log('Referral link clicked:', $(this).attr('href'));
    });
    
    // Get referral widget for logged-in users
    $(document).on('click', '.show-referral-popup', function() {
        if (wrsData.is_logged_in) {
            $.ajax({
                url: wrsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wrs_get_referral_widget',
                    nonce: wrsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#referral-popup-body').html(response.data.html);
                        $('#referral-popup').fadeIn();
                    }
                }
            });
        } else {
            $('#referral-popup').fadeIn();
        }
    });
});
