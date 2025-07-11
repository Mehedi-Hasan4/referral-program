/**
 * Referral System Admin JavaScript
 */
(function($) {
    'use strict';
    
    // DOM ready
    $(document).ready(function() {
        // Initialize admin functions
        initAnalyticsAnimations();
    });
    
    /**
     * Initialize analytics page animations
     */
    function initAnalyticsAnimations() {
        // Animate conversion bar on analytics page
        const conversionBar = $('.conversion-progress');
        
        if (conversionBar.length) {
            const width = conversionBar.width();
            conversionBar.width(0);
            
            setTimeout(function() {
                conversionBar.css('width', width + 'px');
            }, 300);
        }
    }
    
})(jQuery);