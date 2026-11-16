<?php
/**
 * Template part for displaying a reusable form field
 *
 * @package MyHealthHub
 * @version 1.0.0
 * @param array $args Arguments passed to the template
 */

// Extract arguments with defaults
$args = wp_parse_args($args, array(
    'type'        => 'text',
    'name'        => '',
    'id'          => '',
    'label'       => '',
    'value'       => '',
    'placeholder' => '',
    'required'    => false,
    'options'     => array(),
    'description' => '',
    'class'       => '',
));

// Skip if no name
if (empty($args['name'])) {
    return;
}

// Generate ID if not provided
if (empty($args['id'])) {
    $args['id'] = sanitize_html_class($args['name']);
}

$field_class = 'form-group';
if (!empty($args['class'])) {
    $field_class .= ' ' . esc_attr($args['class']);
}
?>

<div class="<?php echo esc_attr($field_class); ?>">
    
    <?php if (!empty($args['label'])) : ?>
        <label for="<?php echo esc_attr($args['id']); ?>" class="form-label">
            <?php echo esc_html($args['label']); ?>
            <?php if ($args['required']) : ?>
                <span class="required">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>
    
    <?php
    switch ($args['type']) {
        case 'textarea':
            ?>
            <textarea 
                id="<?php echo esc_attr($args['id']); ?>" 
                name="<?php echo esc_attr($args['name']); ?>" 
                class="form-textarea"
                <?php echo $args['required'] ? 'required' : ''; ?>
                <?php echo $args['placeholder'] ? 'placeholder="' . esc_attr($args['placeholder']) . '"' : ''; ?>
                rows="5"
            ><?php echo esc_textarea($args['value']); ?></textarea>
            <?php
            break;
            
        case 'select':
            ?>
            <select 
                id="<?php echo esc_attr($args['id']); ?>" 
                name="<?php echo esc_attr($args['name']); ?>" 
                class="form-select"
                <?php echo $args['required'] ? 'required' : ''; ?>
            >
                <?php if ($args['placeholder']) : ?>
                    <option value=""><?php echo esc_html($args['placeholder']); ?></option>
                <?php endif; ?>
                <?php foreach ($args['options'] as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($args['value'], $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
            break;
            
        case 'checkbox':
            ?>
            <label class="form-checkbox" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input 
                    type="checkbox" 
                    id="<?php echo esc_attr($args['id']); ?>" 
                    name="<?php echo esc_attr($args['name']); ?>" 
                    value="1"
                    <?php checked($args['value'], '1'); ?>
                    <?php echo $args['required'] ? 'required' : ''; ?>
                    style="width: auto;"
                >
                <span><?php echo esc_html($args['label']); ?></span>
            </label>
            <?php
            break;
            
        case 'radio':
            if (!empty($args['options'])) :
                ?>
                <div class="form-radio-group" style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <?php foreach ($args['options'] as $value => $label) : ?>
                        <label class="form-radio" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input 
                                type="radio" 
                                name="<?php echo esc_attr($args['name']); ?>" 
                                value="<?php echo esc_attr($value); ?>"
                                <?php checked($args['value'], $value); ?>
                                <?php echo $args['required'] ? 'required' : ''; ?>
                                style="width: auto;"
                            >
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php
            endif;
            break;
            
        default: // text, email, tel, url, number
            ?>
            <input 
                type="<?php echo esc_attr($args['type']); ?>" 
                id="<?php echo esc_attr($args['id']); ?>" 
                name="<?php echo esc_attr($args['name']); ?>" 
                value="<?php echo esc_attr($args['value']); ?>"
                class="form-input"
                <?php echo $args['required'] ? 'required' : ''; ?>
                <?php echo $args['placeholder'] ? 'placeholder="' . esc_attr($args['placeholder']) . '"' : ''; ?>
            >
            <?php
            break;
    }
    ?>
    
    <?php if (!empty($args['description'])) : ?>
        <p class="form-description" style="font-size: 0.8125rem; color: var(--color-text-light); margin-top: 6px; margin-bottom: 0;">
            <?php echo esc_html($args['description']); ?>
        </p>
    <?php endif; ?>
    
</div>
