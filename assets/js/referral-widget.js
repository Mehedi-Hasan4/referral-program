/**
 * Referral System Widget JavaScript
 */
(function($) {
    'use strict';
    
    // DOM ready
    $(document).ready(function() {
        // Initialize referral widget
        initReferralWidget();
    });
    
    /**
     * Initialize the referral widget
     */
    function initReferralWidget() {
        const widgetButton = $('#referral-widget-button');
        const widgetPopup = $('#referral-widget-popup');
        const closeButton = $('#close-referral-popup');
        const copyButton = $('#widget-copy-link');
        
        // Show popup when widget button is clicked
        widgetButton.on('click', function() {
            widgetPopup.addClass('active');
        });
        
        // Hide popup when close button is clicked
        closeButton.on('click', function() {
            widgetPopup.removeClass('active');
        });
        
        // Hide popup when clicking outside
        $(document).on('click', function(event) {
            if (!widgetPopup.is(event.target) && 
                widgetPopup.has(event.target).length === 0 && 
                !widgetButton.is(event.target) && 
                widgetButton.has(event.target).length === 0) {
                widgetPopup.removeClass('active');
            }
        });
        
        // Copy referral link to clipboard
        if (copyButton.length) {
            copyButton.on('click', function() {
                const linkInput = document.getElementById('widget-referral-link');
                const copyMessage = $('#widget-copy-message');
                
                linkInput.select();
                document.execCommand('copy');
                
                copyMessage.fadeIn();
                copyButton.text('Copied!');
                
                setTimeout(function() {
                    copyMessage.fadeOut();
                    copyButton.text('Copy');
                }, 2000);
            });
        }
        
        // Dynamic referral link loading for non-logged in users that login
        if (!isUserLoggedIn() && $('#widget-referral-link').length === 0) {
            // Check login state periodically
            const loginCheckInterval = setInterval(function() {
                if (isUserLoggedIn()) {
                    clearInterval(loginCheckInterval);
                    loadReferralLink();
                }
            }, 5000);
        }
    }
    
    /**
     * Check if user is logged in
     */
    function isUserLoggedIn() {
        return $('body').hasClass('logged-in');
    }
    
    /**
     * Load referral link via AJAX
     */
    function loadReferralLink() {
        $.ajax({
            url: wcReferralSystem.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_referral_link',
                nonce: wcReferralSystem.nonce
            },
            success: function(response) {
                if (response.success && response.data.referral_link) {
                    const widgetBody = $('.referral-widget-body');
                    const discount = '10'; // This should be dynamically pulled from settings
                    const reward = '15';   // This should be dynamically pulled from settings
                    
                    // Replace widget content with logged in content
                    widgetBody.html(`
                        <p class="referral-widget-info">Share your unique link and your friends will get ${discount}% off their first order!</p>
                        <p class="referral-widget-reward">You'll earn a ${reward}% discount coupon when they make a purchase.</p>
                        <div class="referral-widget-link-container">
                            <input type="text" id="widget-referral-link" value="${response.data.referral_link}" readonly>
                            <button id="widget-copy-link" class="widget-copy-button">Copy</button>
                        </div>
                        <p id="widget-copy-message" class="widget-copy-message">Link copied to clipboard!</p>
                        <div class="referral-widget-social">
                            <p>Share via:</p>
                            <div class="widget-social-buttons">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(response.data.referral_link)}" target="_blank" class="widget-social-button facebook">
                                    <span>Facebook</span>
                                </a>
                                <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent('Use my referral link to get ' + discount + '% off your first order!')}&url=${encodeURIComponent(response.data.referral_link)}" target="_blank" class="widget-social-button twitter">
                                    <span>Twitter</span>
                                </a>
                                <a href="mailto:?subject=${encodeURIComponent('Get a discount on your order')}&body=${encodeURIComponent('Use my referral link to get ' + discount + '% off your first order: ' + response.data.referral_link)}" class="widget-social-button email">
                                    <span>Email</span>
                                </a>
                                <a href="https://wa.me/?text=${encodeURIComponent('Use my referral link to get ' + discount + '% off your first order: ' + response.data.referral_link)}" target="_blank" class="widget-social-button whatsapp">
                                    <span>WhatsApp</span>
                                </a>
                            </div>
                        </div>
                        <div class="referral-widget-footer">
                            <a href="/my-account/referrals/" class="widget-view-referrals">View your referrals</a>
                        </div>
                    `);
                    
                    // Reinitialize copy button functionality
                    $('#widget-copy-link').on('click', function() {
                        const linkInput = document.getElementById('widget-referral-link');
                        const copyMessage = $('#widget-copy-message');
                        
                        linkInput.select();
                        document.execCommand('copy');
                        
                        copyMessage.fadeIn();
                        $(this).text('Copied!');
                        
                        setTimeout(function() {
                            copyMessage.fadeOut();
                            $('#widget-copy-link').text('Copy');
                        }, 2000);
                    });
                }
            }
        });
    }
    
})(jQuery);