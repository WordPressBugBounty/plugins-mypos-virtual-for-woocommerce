/**
 * myPOS Admin JavaScript
 *
 * @package myPOS
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Toggle description text change
        initToggleDescriptions();
    });

    /**
     * Initialize toggle description changes
     */
    function initToggleDescriptions() {
        // Find all toggles with data attributes for description
        $('.mypos-toggle').each(function() {
            var $toggle = $(this);
            var $row = $toggle.closest('tr');
            var $description = $row.find('.description, p.description');
            
            // Get custom descriptions from data attributes if set
            var descOn = $toggle.data('desc-on');
            var descOff = $toggle.data('desc-off');
            
            if (descOn && descOff) {
                // Set initial description based on current state
                updateDescription($toggle, $description, descOn, descOff);
                
                // Listen for changes
                $toggle.on('change', function() {
                    updateDescription($(this), $description, descOn, descOff);
                });
            }
        });

        // Specific toggle handlers - Test Mode
        var $testToggle = $('#woocommerce_mypos_virtual_test');
        if ($testToggle.length) {
            var $testRow = $testToggle.closest('tr');
            var $testDesc = $testRow.find('.description, p.description');
            
            // Create description element if it doesn't exist
            if (!$testDesc.length) {
                $testDesc = $('<p class="description"></p>');
                $testToggle.closest('td').find('fieldset').append($testDesc);
            }
            
            var testDescOn = myposAdminL10n.testModeOnDesc || 'Test mode is enabled. Transactions will be processed in sandbox environment.';
            var testDescOff = myposAdminL10n.testModeOffDesc || 'Test mode is disabled. Transactions will be processed in production environment.';
            
            // Set initial state
            updateTestModeDescription($testToggle, $testDesc, testDescOn, testDescOff);
            
            // Listen for changes
            $testToggle.on('change', function() {
                updateTestModeDescription($(this), $testDesc, testDescOn, testDescOff);
            });
        }
    }

    /**
     * Update description based on toggle state
     */
    function updateDescription($toggle, $description, descOn, descOff) {
        if ($toggle.is(':checked')) {
            $description.html(descOn);
        } else {
            $description.html(descOff);
        }
    }

    /**
     * Update Test Mode description
     */
    function updateTestModeDescription($toggle, $description, descOn, descOff) {
        if ($toggle.is(':checked')) {
            $description.html(descOn).css('color', '#d63638');
        } else {
            $description.html(descOff).css('color', '#2e7d32');
        }
    }

})(jQuery);
