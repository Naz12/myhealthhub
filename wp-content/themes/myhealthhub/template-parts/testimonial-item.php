<?php
/**
 * Template part for displaying a single testimonial
 *
 * @package MyHealthHub
 * @version 1.0.0
 * @param array $args Arguments passed to the template
 */

// Extract arguments with defaults
$args = wp_parse_args($args, array(
    'content' => '',
    'author'  => '',
    'rating'  => 0,
    'date'    => '',
));

// Skip if no content
if (empty($args['content'])) {
    return;
}

$rating = intval($args['rating']);
?>

<div class="testimonial-item" style="padding: 24px; background: var(--color-light); border-radius: 12px; margin-bottom: 20px;">
    
    <?php if ($rating > 0) : ?>
        <div class="testimonial-item__rating" style="color: #F59E0B; margin-bottom: 12px; font-size: 1.125rem;">
            <?php echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); ?>
        </div>
    <?php endif; ?>
    
    <div class="testimonial-item__content" style="font-style: italic; color: var(--color-text); line-height: 1.7; margin-bottom: 16px;">
        "<?php echo esc_html($args['content']); ?>"
    </div>
    
    <div class="testimonial-item__footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div class="testimonial-item__author" style="font-weight: 600; color: var(--color-text);">
            — <?php echo esc_html($args['author'] ?: __('Anonymous', 'myhealthhub')); ?>
        </div>
        
        <?php if ($args['date']) : ?>
            <div class="testimonial-item__date" style="font-size: 0.875rem; color: var(--color-text-light);">
                <?php echo esc_html($args['date']); ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>
