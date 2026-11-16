<?php
/**
 * Template Name: Submit Testimonial
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

get_header();

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
$listing = $listing_id ? get_post($listing_id) : null;
?>

<?php myhh_page_header(get_the_title(), $listing ? sprintf(__('Share your experience with %s', 'myhealthhub'), esc_html($listing->post_title)) : __('Share your experience with a provider', 'myhealthhub')); ?>

<div class="page-content">
    <div class="container">
        <div class="max-w-md mx-auto">
            
            <?php if (isset($_GET['testimonial_submitted']) && $_GET['testimonial_submitted'] == '1') : ?>
                <div class="form-success mb-8">
                    <h3><?php _e('Thank You!', 'myhealthhub'); ?></h3>
                    <p><?php _e('Your testimonial has been submitted for review. We appreciate your feedback!', 'myhealthhub'); ?></p>
                    <?php if ($listing_id) : ?>
                        <p style="margin-top: 15px;">
                            <a href="<?php echo esc_url(get_permalink($listing_id)); ?>" class="btn btn--outline">
                                <?php _e('Back to Listing', 'myhealthhub'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                
                <?php if ($listing) : ?>
                    <div class="bg-light p-6 rounded-lg mb-8">
                        <p style="margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--color-text-light);"><?php _e('You are submitting a testimonial for:', 'myhealthhub'); ?></p>
                        <h3 style="margin-bottom: 0;"><?php echo esc_html($listing->post_title); ?></h3>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" class="testimonial-form">
                    <?php wp_nonce_field('myhh_testimonial', 'myhh_nonce'); ?>
                    <input type="hidden" name="myhh_form_action" value="submit_testimonial">
                    <input type="hidden" name="listing_id" value="<?php echo esc_attr($listing_id); ?>">
                    
                    <?php if (!$listing) : ?>
                        <div class="form-group">
                            <label for="provider_name" class="form-label">
                                <?php _e('Provider Name', 'myhealthhub'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" id="provider_name" name="provider_name" class="form-input" required>
                            <p style="font-size: 0.75rem; color: var(--color-text-light); margin-top: 0.25rem;">
                                <?php _e('Please enter the name of the provider you want to review.', 'myhealthhub'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="testimonial_name" class="form-label">
                            <?php _e('Your Name', 'myhealthhub'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="testimonial_name" name="testimonial_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="testimonial_rating" class="form-label">
                            <?php _e('Rating', 'myhealthhub'); ?> <span class="required">*</span>
                        </label>
                        <select id="testimonial_rating" name="testimonial_rating" class="form-select" required>
                            <option value="5">5 - <?php _e('Excellent', 'myhealthhub'); ?></option>
                            <option value="4">4 - <?php _e('Very Good', 'myhealthhub'); ?></option>
                            <option value="3">3 - <?php _e('Good', 'myhealthhub'); ?></option>
                            <option value="2">2 - <?php _e('Fair', 'myhealthhub'); ?></option>
                            <option value="1">1 - <?php _e('Poor', 'myhealthhub'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="testimonial_content" class="form-label">
                            <?php _e('Your Testimonial', 'myhealthhub'); ?> <span class="required">*</span>
                        </label>
                        <textarea id="testimonial_content" name="testimonial_content" class="form-textarea" rows="6" required 
                                  placeholder="<?php esc_attr_e('Share your experience with this provider. What went well? How did they help you? Would you recommend them?', 'myhealthhub'); ?>"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn--primary btn--lg w-full">
                            <?php _e('Submit Testimonial', 'myhealthhub'); ?>
                        </button>
                    </div>
                    
                    <p style="font-size: 0.875rem; color: var(--color-text-light); text-align: center;">
                        <?php _e('By submitting this testimonial, you confirm that it is based on your genuine experience. All testimonials are reviewed before being published.', 'myhealthhub'); ?>
                    </p>
                </form>
                
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
get_footer();
