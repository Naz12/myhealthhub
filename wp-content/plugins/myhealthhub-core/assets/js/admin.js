/**
 * My Health Hub Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Confirm actions
        $('.myhh-confirm-action').on('click', function(e) {
            var message = $(this).data('confirm-message');
            if (message && !confirm(message)) {
                e.preventDefault();
            }
        });

        // Status change handler
        $('select[name="myhh_status"], select[name="myhh_claim_status"]').on('change', function() {
            $(this).closest('form').addClass('dirty');
        });

        // Quick approve/reject for testimonials
        $('.myhh-quick-approve').on('click', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            var testimonialId = $(this).data('id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'myhh_approve_testimonial',
                    testimonial_id: testimonialId,
                    nonce: myhh_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                }
            });
        });
    });

})(jQuery);
