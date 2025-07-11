jQuery(document).ready(function($) {
    // Initialize popup
    function initReferralPopup() {
        // Show popup on load
        $('#referral-popup').fadeIn();
        
        // Close popup
        $('.close-popup').click(function() {
            $('#referral-popup').fadeOut();
        });
        
        // Mini widget toggle
        $('.widget-icon').click(function() {
            $('.widget-content').slideToggle();
        });
    }
    
    // Email check handler
    $('#check-email-btn').click(function() {
        var email = $('#referral-email').val();
        var nonce = referral_data.nonce;
        
        $.post(referral_data.ajax_url, {
            action: 'check_referral_email',
            email: email,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                if (response.data.action === 'login') {
                    $('#email-step').hide();
                    $('#login-step').show();
                } else {
                    $('#email-step').hide();
                    $('#register-step').show();
                }
            } else {
                $('#email-message').html('<p class="error">' + response.data.message + '</p>');
            }
        }).fail(function() {
            $('#email-message').html('<p class="error">An error occurred. Please try again.</p>');
        });
    });
    
    // Login handler
    $('#referral-login-btn').click(function() {
        var email = $('#referral-email').val();
        var password = $('#referral-password').val();
        var nonce = referral_data.nonce;
        
        $.post(referral_data.ajax_url, {
            action: 'process_referral_login',
            email: email,
            password: password,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                getReferralContent();
            } else {
                $('#login-message').html('<p class="error">' + response.data.message + '</p>');
            }
        });
    });
    
    // Registration handler
    $('#referral-register-btn').click(function() {
        var email = $('#referral-email').val();
        var password = $('#referral-new-password').val();
        var nonce = referral_data.nonce;
        
        $.post(referral_data.ajax_url, {
            action: 'process_referral_register',
            email: email,
            password: password,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                getReferralContent();
            } else {
                $('#register-message').html('<p class="error">' + response.data.message + '</p>');
            }
        });
    });
    
    // Get referral content
    function getReferralContent() {
        $.post(referral_data.ajax_url, {
            action: 'get_referral_content',
            nonce: referral_data.nonce
        }, function(response) {
            if (response.success) {
                $('#referral-dynamic-content').html(response.data.content);
                $('.widget-content').html(response.data.content);
                initShareButtons();
            }
        });
    }
    
    // Copy referral link
    $(document).on('click', '.copy-link', function() {
        var copyText = document.getElementById("referral-link");
        copyText.select();
        document.execCommand("copy");
        alert("Link copied to clipboard!");
    });
    
    // Share buttons
    function initShareButtons() {
        $('.share-facebook').click(function() {
            var link = $('#referral-link').val();
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link), '_blank');
        });
        
        $('.share-twitter').click(function() {
            var text = "Get " + referral_data.friend_discount + "% off your first order with my referral!";
            var link = $('#referral-link').val();
            window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(link), '_blank');
        });
        
        $('.share-email').click(function() {
            var subject = "Get " + referral_data.friend_discount + "% off your first order!";
            var body = "Hi there!\n\nUse my referral link to get " + referral_data.friend_discount + "% off your first order:\n" + $('#referral-link').val();
            window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
        });
    }
    
    // Initialize
    initReferralPopup();
    if ($('#referral-link').length) initShareButtons();
});