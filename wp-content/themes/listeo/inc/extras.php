<?php

/**
 * Custom functions that act independently of the theme templates
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package listeo
 */


//random string

function listeo_post_views_count()
{

  if (is_single()) {

    global $post;
    $count_post   = get_post_meta($post->ID, '_listing_views_count', true);
    $author_id     = get_post_field('post_author', $post->ID);

    $total_views   = get_user_meta($author_id, 'listeo_total_listing_views', true);

    if ($count_post == '') {

      $count_post = 1;
      add_post_meta($post->ID, '_listing_views_count', $count_post);

      $total_views = (int) $total_views + 1;
      update_user_meta($author_id, 'listeo_total_listing_views', $total_views);
    } else {

      $total_views = (int) $total_views + 1;
      update_user_meta($author_id, 'listeo_total_listing_views', $total_views);

      $count_post = (int)$count_post + 1;
      update_post_meta($post->ID, '_listing_views_count', $count_post);
    }
  }
}
add_action('wp_head', 'listeo_post_views_count');



//cmb2 slots field
function cmb2_render_callback_for_slots_ft($field, $escaped_value, $object_id, $object_type, $field_type_object)
{

  $clock_format = get_option('listeo_clock_format', '12') ?>

  <div class="availability-slots" data-clock-type="<?php echo esc_attr($clock_format); ?>hr">

    <?php
    $days = array(
      'monday'  => __('Monday', 'listeo_core'),
      'tuesday'   => __('Tuesday', 'listeo_core'),
      'wednesday' => __('Wednesday', 'listeo_core'),
      'thursday'   => __('Thursday', 'listeo_core'),
      'friday'   => __('Friday', 'listeo_core'),
      'saturday'   => __('Saturday', 'listeo_core'),
      'sunday'   => __('Sunday', 'listeo_core'),
    );

    if (!is_array($field->value)) {
      $field = json_decode($field->value);
    } else {
      $field = $field->value;
    }

    $int = 0;
    ?>

    <?php foreach ($days as $id => $dayname) {
    ?>

      <!-- Single Day Slots -->
      <div class="day-slots">
        <div class="day-slot-headline">
          <?php echo esc_html($dayname); ?>
        </div>


        <!-- Slot For Cloning / Do NOT Remove-->
        <div class="single-slot cloned">
          <div class="single-slot-left">
            <div class="single-slot-time"><?php echo esc_html($dayname); ?></div>
            <button class="remove-slot"><i class="fa fa-close"></i></button>
          </div>

          <div class="single-slot-right">
            <strong><?php esc_html_e('Slots', 'listeo_core'); ?></strong>
            <div class="plusminus horiz">
              <button></button>
              <input type="number" name="slot-qty" id="slot-qty" value="1" min="1" max="99">
              <button></button>
            </div>
          </div>
        </div>
        <!-- Slot For Cloning / Do NOT Remove-->

        <?php if (!isset($field[$int][0])) { ?>
          <!-- No slots -->
          <div class="no-slots"><?php esc_html_e('No slots added', 'listeo_core'); ?></div>
        <?php } ?>
        <!-- Slots Container -->
        <div class="slots-container">


          <!-- Slots from database loop -->
          <?php if (isset($field[$int]) && is_array($field[$int])) foreach ($field[$int] as $slot) { // slots loop
            $slot = explode('|', $slot); ?>
            <div class="single-slot ui-sortable-handle">
              <div class="single-slot-left">
                <div class="single-slot-time"><?php echo esc_html($slot[0]); ?></div>
                <button class="remove-slot"><i class="fa fa-close"></i></button>
              </div>

              <div class="single-slot-right">
                <strong><?php esc_html_e('Slots', 'listeo_core'); ?></strong>
                <div class="plusminus horiz">
                  <button disabled=""></button>
                  <input type="number" name="slot-qty" id="slot-qty" value="<?php echo esc_html($slot[1]); ?>" min="1" max="99">
                  <button></button>
                </div>
              </div>
            </div>
          <?php } ?>
          <!-- Slots from database / End -->

        </div>
        <!-- Slots Container / End -->
        <!-- Add Slot -->
        <div class="add-slot">
          <div class="add-slot-inputs">
            <input type="time" class="time-slot-start" min="00:00" max="12:59" />
            <?php if ($clock_format == '12') { ?>
              <select class="time-slot-start twelve-hr" id="">
                <option><?php esc_html_e('am', 'listeo_core'); ?></option>
                <option><?php esc_html_e('pm', 'listeo_core'); ?></option>
              </select>
            <?php } ?>

            <span>-</span>

            <input type="time" class="time-slot-end" min="00:00" max="12:59" />
            <?php if ($clock_format == '12') { ?>
              <select class="time-slot-end twelve-hr" id="">
                <option><?php esc_html_e('am'); ?></option>
                <option><?php esc_html_e('pm'); ?></option>
              </select>
            <?php } ?>

          </div>
          <div class="add-slot-btn">
            <button><?php esc_html_e('Add', 'listeo_core'); ?></button>
          </div>
        </div>
      </div>
    <?php
      $int++;
    } ?>

  </div>

<?php
  echo $field_type_object->input(array('type' => 'hidden'));
}
add_action('cmb2_render_slots', 'cmb2_render_callback_for_slots_ft', 10, 5);

function cmb2_render_callback_for_listeo_calendar_ft($field, $escaped_value, $object_id, $object_type, $field_type)
{

  //$calendar = new Listeo_Core_Calendar;

  //echo $calendar->getCalendarHTML();

?>
  <div class="fullcalendar-container">
    <div class="calendar-toolbar" style="display: none;">

      <div class="calendar-actions">
        <button type="button" id="set-price-btn" class="button"><?php _e('Set Price for Selected Dates', 'listeo_core'); ?></button>
        <button type="button" id="block-dates-btn" class="button"><?php _e('Block Selected Dates', 'listeo_core'); ?></button>
        <button type="button" id="clear-selection-btn" class="button"><?php _e('Clear Selection', 'listeo_core'); ?></button>
      </div>
    </div>
    <div id="fullcalendar"></div>
  </div>

  <!-- Price Dialog -->
  <div id="price-dialog" title="<?php _e('Set Price for Selected Dates', 'listeo_core'); ?>" style="display:none;">
    <p><?php _e('Enter price for the selected dates:', 'listeo_core'); ?></p>
    <input type="number" id="price-input" min="0" step="0.01" class="widefat">
    <div class="price-dialog-buttons">
      <button type="button" id="price-confirm" class="button"><?php _e('Set Price', 'listeo_core'); ?></button>
      <button type="button" id="price-cancel" class="button"><?php _e('Cancel', 'listeo_core'); ?></button>
    </div>
  </div>

<?php 
  // make sure we specify each part of the value we need.
  $value = wp_parse_args($field->value, array(

  'dates' => '',
  'price' => '',
  ));

  echo $field_type->input(array(
  'name' => $field_type->_name('[dates]'),
    'id' => 'fullcalendar-blocked-dates',
  'class' => 'listeo-calendar-avail',
  'value' => esc_attr($value['dates']),
  'type' => 'text',
  ));
  echo $field_type->input(array(
  'name' => $field_type->_name('[price]'),
  'id' => 'fullcalendar-price-data',
  'class' => 'listeo-calendar-price',
  'value' => esc_attr($value['price']),
  'type' => 'text',
  )); ?>

  <?php
}
add_action('cmb2_render_listeo_calendar', 'cmb2_render_callback_for_listeo_calendar_ft', 10, 5);




/**
 * CMB2 Select Multiple Custom Field Type
 * @package CMB2 Select Multiple Field Type
 */

/**
 * Adds a custom field type for select multiples.
 * @param  object $field             The CMB2_Field type object.
 * @param  string $value             The saved (and escaped) value.
 * @param  int    $object_id         The current post ID.
 * @param  string $object_type       The current object type.
 * @param  object $field_type_object The CMB2_Types object.
 * @return void
 */
if (!function_exists('cmb2_render_select_multiple_field_type_tf')) {
  function cmb2_render_select_multiple_field_type_tf($field, $escaped_value, $object_id, $object_type, $field_type_object)
  {
    $saved_values = get_post_meta($object_id, $field->args['_name']);

    $select_multiple = '<select class="widefat" multiple name="' . $field->args['_name'] . '[]" id="' . $field->args['_id'] . '"';
    foreach ($field->args['attributes'] as $attribute => $value) {
      $select_multiple .= " $attribute=\"$value\"";
    }
    $select_multiple .= ' />';

    if (is_string($escaped_value)) {
      $escaped_value = explode(',', $escaped_value);
    }
    foreach ($field->options() as $value => $name) {
      $selected = '';
      if (is_array($saved_values)) {

        if (in_array($value, $saved_values)) {
          $selected = 'selected="selected"';
        }
      } else {
        $selected = ($escaped_value && in_array($value, $escaped_value)) ? 'selected="selected"' : '';
      }


      $select_multiple .= '<option class="cmb2-option" value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($name) . '</option>';
    }

    $select_multiple .= '</select>';
    $select_multiple .= $field_type_object->_desc(true);

    echo $select_multiple; // WPCS: XSS ok.
  }
  add_action('cmb2_render_select_multiple', 'cmb2_render_select_multiple_field_type_tf', 10, 5);


  /**
   * Sanitize the selected value.
   */

  function cmb2_sanitize_select_multiple_callback_tf($override_value, $value)
  {
    if (is_array($value)) {
      foreach ($value as $key => $saved_value) {
        $value[$key] = sanitize_text_field($saved_value);
      }
      return $value;
    }
    return;
  }
  add_filter('cmb2_sanitize_select_multiple', 'cmb2_sanitize_select_multiple_callback_tf', 10, 4);



  function cmb2_save_select_multiple_callback_tf($override, array $args, array  $field_args)
  {
    if ($field_args['type'] == 'select_multiple' || $field_args['type'] === 'multicheck_split') {
      if (is_array($args['value'])) {

        delete_post_meta($args['id'], $args['field_id']);
        foreach ($args['value'] as $key => $saved_value) {
          $sanitized_value = sanitize_text_field($saved_value);
          add_post_meta($args['id'], $args['field_id'], $sanitized_value);
        }
      }
      return true;
    }
    return $override;
  }
  add_filter('cmb2_override_meta_save', 'cmb2_save_select_multiple_callback_tf', 10, 4);
}
function cmb2_render_multicheck_split_field_type_tf($field, $escaped_value, $object_id, $object_type, $field_type_object)
{
  $saved_values = get_post_meta($object_id, $field->args['_name']);

  $select_multiple = '
	<ul class="cmb2-checkbox-list cmb2-list">	';


  if (is_string($escaped_value)) {
    $escaped_value = explode(',', $escaped_value);
  }
  $i = 0;
  foreach ($field->options() as $value => $name) {
    $selected = '';
    $i++;
    if (is_array($saved_values)) {
      if (in_array($value, $saved_values)) {
        $selected = 'checked="checked"';
      }
    } else {
      $selected = ($escaped_value && in_array($value, $escaped_value)) ? 'checked="checked"' : '';
    }

    $select_multiple .= '<li><input type="checkbox" class="cmb2-option" name="' . $field->args['_name'] . '[]" id="' . $field->args['_id'] . $i . '" value="' . esc_attr($value) . '" ' . $selected . '><label for="' . $field->args['_id'] . $i . '">' . esc_html($name) . '</label></li>';
  }
  $select_multiple .= "</ul>";

  $select_multiple .= $field_type_object->_desc(true);

  echo $select_multiple; // WPCS: XSS ok.
}
add_action('cmb2_render_multicheck_split', 'cmb2_render_multicheck_split_field_type_tf', 5, 5);



function nFkuHeEWMR_listeo_license_admin_notice()
{

  $licenseKey   = get_option("Listeo_lic_Key", "");

  $liceEmail    = get_option("Listeo_lic_email", "");

  $templateDir  = get_template_directory(); //or dirname(__FILE__);
  $isactive = get_option('listeo_license_key_activated', false);

  if (!$licenseKey) {
    ob_start();

  ?>
    <div class="license-validation-popup">
      <p>Oops, seems that you have not activated your Listeo license yet!</p>
      <a href="<?php echo add_query_arg(array('tab' => 'license'), menu_page_url('listeo_license', false)) ?>" class="nav-tab">Activate License</a>
    </div>

  <?php $html = ob_get_clean();
    echo $html;
  }
}
add_action('admin_notices', 'nFkuHeEWMR_listeo_license_admin_notice');


/**
 * Check if WooCommerce is activated
 */
if (!function_exists('is_woocommerce_activated')) {
  function is_woocommerce_activated()
  {
    if (class_exists('woocommerce')) {
      return true;
    } else {
      return false;
    }
  }
}


function listeo_check_abandoned_cart()
{

  $unpaid_listing_in_cart = false;

  if (is_woocommerce_activated()) {

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
      if (WC_Product_Factory::get_product_type($cart_item['product_id']) == "listing_package" || WC_Product_Factory::get_product_type($cart_item['product_id']) == "listing_package_subscription") {
        $unpaid_listing_in_cart = true;
      };
    }
  }

  return $unpaid_listing_in_cart;
}


// add_filter( 'woocommerce_return_to_shop_redirect', 'listeo_woocommerce_shop_url' );
// /**
//  * Redirect WooCommerce Shop URL
//  */

// function listeo_woocommerce_shop_url(){


//   $submit_page = get_option('listeo_submit_page');
//   if($submit_page){
//     return get_permalink($submit_page);
//   }


// }

/**
 * ============================================================================
 * LISTEO SVG RENDERING & CACHING SYSTEM
 * ============================================================================
 * 3-tier caching: Object Cache → Database → File System
 * Auto-invalidates on file changes via filemtime()
 * Performance: 11s → 0.6s for homepage with 120 SVG icons
 * ============================================================================
 */

function listeo_render_svg_icon($value)
{
  $attachment_id = listeo_normalize_svg_attachment_id($value);

  if (!$attachment_id) {
    return '';
  }

  return listeo_get_inline_svg($attachment_id);
}

/**
 * Smart SVG renderer that automatically chooses between inline SVG and <img> tag
 * based on file size and user preference for optimal performance.
 *
 * @param int|string $value Attachment ID or value to normalize
 * @param bool|null $inline Force inline (true) or img tag (false). Null = auto-detect
 * @param int $size_threshold File size threshold in bytes (default: 3000 = 3KB)
 * @return string HTML output (inline SVG or img tag)
 */
function listeo_smart_svg_render($value, $inline = null, $size_threshold = 5000)
{
  $attachment_id = listeo_normalize_svg_attachment_id($value);

  if (!$attachment_id) {
    return '';
  }

  // Verify it's actually an SVG
  $mime_type = get_post_mime_type($attachment_id);
  if ($mime_type !== 'image/svg+xml') {
    // Not an SVG, fall back to regular image
    return wp_get_attachment_image($attachment_id, 'full');
  }

  // Allow users to filter the size threshold
  $size_threshold = apply_filters('listeo_svg_size_threshold', $size_threshold, $attachment_id);

  // Auto-detect if not specified
  if ($inline === null) {
    $attachment_file = get_attached_file($attachment_id);

    if ($attachment_file && is_readable($attachment_file)) {
      $file_size = filesize($attachment_file);
      // Use inline for small files, img tag for large files
      $inline = ($file_size < $size_threshold);
    } else {
      // If we can't read the file, default to inline (safer)
      $inline = true;
    }
  }

  // Return appropriate format
  if ($inline) {
    return listeo_get_inline_svg($attachment_id);
  } else {
    // Use img tag with proper attributes
    $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $svg_url = wp_get_attachment_url($attachment_id);

    if (!$svg_url) {
      return '';
    }

    return sprintf(
      '<img src="%s" alt="%s" class="svg-icon" loading="lazy">',
      esc_url($svg_url),
      esc_attr($alt_text ?: '')
    );
  }
}

function listeo_get_inline_svg($attachment_id)
{
  $attachment_id = absint($attachment_id);

  if (!$attachment_id) {
    return '';
  }

  // Get file modification time for cache invalidation
  $attachment_file = get_attached_file($attachment_id);
  $file_mtime = $attachment_file && file_exists($attachment_file) ? filemtime($attachment_file) : 0;

  // TIER 1: Object Cache (fastest - in-memory with Redis/Memcached support)
  $object_cache_key = "listeo_svg_{$attachment_id}_{$file_mtime}";
  $cached_svg = wp_cache_get($object_cache_key, 'listeo_svg');

  if ($cached_svg !== false) {
    return $cached_svg; // Already sanitized, return immediately
  }

  // TIER 2: Database Post Meta (fast - persistent storage)
  $meta_cache_key = "_listeo_svg_cache_{$file_mtime}";
  $cached_svg = get_post_meta($attachment_id, $meta_cache_key, true);

  if (!empty($cached_svg)) {
    // Store in object cache for subsequent requests
    wp_cache_set($object_cache_key, $cached_svg, 'listeo_svg', DAY_IN_SECONDS);
    return $cached_svg; // Already sanitized, no re-processing needed
  }

  // TIER 3: File System (slow - only on cache miss or file change)
  $raw_svg = listeo_read_svg_attachment($attachment_id);

  if (empty($raw_svg)) {
    return '';
  }

  $sanitized = listeo_sanitize_svg_markup($raw_svg);

  if (empty($sanitized)) {
    return '';
  }

  // Clean up old cache entries with different modification times
  listeo_cleanup_old_svg_cache($attachment_id, $file_mtime);

  // Save to database cache
  update_post_meta($attachment_id, $meta_cache_key, $sanitized);

  // Save to object cache
  wp_cache_set($object_cache_key, $sanitized, 'listeo_svg', DAY_IN_SECONDS);

  return $sanitized;
}

function listeo_normalize_svg_attachment_id($value)
{
  if (is_array($value)) {
    if (isset($value['id'])) {
      return absint($value['id']);
    }

    if (isset($value['value']) && is_array($value['value']) && isset($value['value']['id'])) {
      return absint($value['value']['id']);
    }
  }

  if (is_numeric($value)) {
    return absint($value);
  }

  if (is_string($value) && preg_match('/^\d+$/', $value)) {
    return absint($value);
  }

  return 0;
}

function listeo_read_svg_attachment($attachment_id)
{
  $attachment_id = absint($attachment_id);

  if (!$attachment_id) {
    return '';
  }

  $attachment = get_post($attachment_id);

  if (!$attachment) {
    return '';
  }

  $mime_type = get_post_mime_type($attachment);

  if ($mime_type && strpos($mime_type, 'svg') === false) {
    return '';
  }

  $attachment_file = get_attached_file($attachment_id);

  if ($attachment_file && is_readable($attachment_file)) {
    $contents = file_get_contents($attachment_file);

    if ($contents !== false) {
      return $contents;
    }
  }

  $attachment_url = wp_get_attachment_url($attachment_id);

  if ($attachment_url) {
    $response = wp_safe_remote_get($attachment_url, array('timeout' => 5));

    if (!is_wp_error($response)) {
      $code = wp_remote_retrieve_response_code($response);

      if ($code >= 200 && $code < 300) {
        return wp_remote_retrieve_body($response);
      }
    }
  }

  return '';
}

function listeo_sanitize_svg_markup($svg)
{
  if (empty($svg) || !is_string($svg)) {
    return '';
  }

  $svg = trim($svg);

  if ($svg === '') {
    return '';
  }

  // Drop any declared doctype to avoid external entity lookups.
  $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);

  $internal_errors = libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  $dom->strictErrorChecking = false;

  $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

  if (!$loaded || !$dom->documentElement) {
    libxml_clear_errors();
    libxml_use_internal_errors($internal_errors);

    return '';
  }

  $root = $dom->documentElement;

  if ($root->tagName !== 'svg') {
    libxml_clear_errors();
    libxml_use_internal_errors($internal_errors);

    return '';
  }

  $allowed_tags = array(
    'svg',
    'g',
    'path',
    'circle',
    'rect',
    'line',
    'polyline',
    'polygon',
    'ellipse',
    'defs',
    'clipPath',
    'mask',
    'symbol',
    'use',
    'linearGradient',
    'radialGradient',
    'stop',
    'pattern',
    'title',
    'desc',
    'metadata',
    'view',
  );

  $global_attributes = array(
    'class',
    'id',
    'x',
    'y',
    'width',
    'height',
    'cx',
    'cy',
    'r',
    'rx',
    'ry',
    'x1',
    'y1',
    'x2',
    'y2',
    'd',
    'points',
    'pathLength',
    'fill',
    'fill-opacity',
    'fill-rule',
    'stroke',
    'stroke-width',
    'stroke-linecap',
    'stroke-linejoin',
    'stroke-miterlimit',
    'stroke-dasharray',
    'stroke-dashoffset',
    'stroke-opacity',
    'opacity',
    'transform',
    'vector-effect',
    'clip-path',
    'clip-rule',
    'mask',
    'marker-start',
    'marker-mid',
    'marker-end',
    'clipPathUnits',
    'maskUnits',
    'maskContentUnits',
    'style',
    'data-name',
    'data-type',
    'aria-hidden',
    'aria-labelledby',
    'role',
    'focusable',
    'tabindex',
  );

  $tag_specific_attributes = array(
    'svg' => array(
      'xmlns',
      'xmlns:xlink',
      'xmlns:svg',
      'xml:space',
      'width',
      'height',
      'viewBox',
      'preserveAspectRatio',
      'version',
    ),
    'use' => array(
      'xlink:href',
      'href',
    ),
    'linearGradient' => array(
      'id',
      'x1',
      'x2',
      'y1',
      'y2',
      'gradientUnits',
      'gradientTransform',
    ),
    'radialGradient' => array(
      'id',
      'cx',
      'cy',
      'r',
      'fx',
      'fy',
      'gradientUnits',
      'gradientTransform',
    ),
    'stop' => array(
      'offset',
      'stop-color',
      'stop-opacity',
    ),
    'pattern' => array(
      'id',
      'patternUnits',
      'patternTransform',
      'width',
      'height',
      'viewBox',
      'x',
      'y',
    ),
  );

  $remove_nodes = array();

  $walker = $root->getElementsByTagName('*');

  foreach ($walker as $node) {
    if (!in_array($node->tagName, $allowed_tags, true)) {
      $remove_nodes[] = $node;
      continue;
    }

    $allowed_attributes = $global_attributes;

    if (isset($tag_specific_attributes[$node->tagName])) {
      $allowed_attributes = array_merge($allowed_attributes, $tag_specific_attributes[$node->tagName]);
    }

    if ($node->hasAttributes()) {
      $attributes_to_remove = array();

      foreach ($node->attributes as $attribute) {
        $name = $attribute->nodeName;
        $value = $attribute->nodeValue;

        if (strpos($name, 'on') === 0) {
          $attributes_to_remove[] = $name;
          continue;
        }

        if ($name === 'style') {
          $clean_value = function_exists('safecss_filter_attr') ? safecss_filter_attr($value) : sanitize_text_field($value);

          if ($clean_value === '') {
            $attributes_to_remove[] = $name;
          } else {
            $node->setAttribute($name, $clean_value);
          }

          continue;
        }

        if (strpos($name, 'aria-') === 0 || strpos($name, 'data-') === 0) {
          $node->setAttribute($name, sanitize_text_field($value));
          continue;
        }

        if (!in_array($name, $allowed_attributes, true)) {
          $attributes_to_remove[] = $name;
          continue;
        }

        if ($name === 'class') {
          $sanitized_classes = array_filter(array_map('sanitize_html_class', preg_split('/\s+/', $value)));

          if (empty($sanitized_classes)) {
            $attributes_to_remove[] = $name;
          } else {
            $node->setAttribute($name, implode(' ', $sanitized_classes));
          }

          continue;
        }

        if ($name === 'href' || $name === 'xlink:href') {
          $sanitized_url = esc_url_raw(trim($value));

          if ($sanitized_url === '') {
            $attributes_to_remove[] = $name;
          } else {
            $node->setAttribute($name, $sanitized_url);
          }

          continue;
        }

        $node->setAttribute($name, sanitize_text_field($value));
      }

      foreach ($attributes_to_remove as $attribute_name) {
        $node->removeAttribute($attribute_name);
      }
    }
  }

  foreach ($remove_nodes as $node) {
    if ($node->parentNode) {
      $node->parentNode->removeChild($node);
    }
  }

  libxml_clear_errors();
  libxml_use_internal_errors($internal_errors);

  return $dom->saveXML($dom->documentElement);
}

/**
 * Clean up old SVG cache entries with different modification times.
 * This ensures cache is invalidated when SVG files are updated.
 *
 * @param int $attachment_id The attachment ID
 * @param int $current_mtime Current file modification time
 * @return void
 */
function listeo_cleanup_old_svg_cache($attachment_id, $current_mtime)
{
  global $wpdb;

  // Delete all cache meta keys except the current one
  $wpdb->query(
    $wpdb->prepare(
      "DELETE FROM {$wpdb->postmeta}
       WHERE post_id = %d
       AND meta_key LIKE '_listeo_svg_cache_%%'
       AND meta_key != %s",
      $attachment_id,
      "_listeo_svg_cache_{$current_mtime}"
    )
  );

  // Also clean up legacy Elementor cache if it exists
  delete_post_meta($attachment_id, '_elementor_inline_svg');
}

/**
 * Clear all SVG caches for a specific attachment.
 * Useful when manually invalidating cache via admin action.
 *
 * @param int $attachment_id The attachment ID
 * @return void
 */
function listeo_clear_svg_cache($attachment_id)
{
  global $wpdb;

  $attachment_id = absint($attachment_id);

  if (!$attachment_id) {
    return;
  }

  // Clear database cache
  $wpdb->query(
    $wpdb->prepare(
      "DELETE FROM {$wpdb->postmeta}
       WHERE post_id = %d
       AND meta_key LIKE '_listeo_svg_cache_%%'",
      $attachment_id
    )
  );

  // Clear legacy cache
  delete_post_meta($attachment_id, '_elementor_inline_svg');

  // Clear object cache (pattern matching not supported, but will auto-expire)
  wp_cache_delete("listeo_svg_{$attachment_id}", 'listeo_svg');
}

/**
 * Hook to clear SVG cache when attachment is updated.
 * Ensures cache is invalidated on file replacement.
 */
add_action('add_attachment', 'listeo_clear_svg_cache');
add_action('edit_attachment', 'listeo_clear_svg_cache');
add_action('delete_attachment', 'listeo_clear_svg_cache');

/**
 * Get SVG cache statistics for debugging.
 * Useful for monitoring cache effectiveness.
 *
 * @return array Cache statistics
 */
function listeo_get_svg_cache_stats()
{
  global $wpdb;

  $stats = array(
    'cached_svgs' => 0,
    'legacy_cache' => 0,
    'total_cache_size' => 0,
  );

  // Count new cache entries
  $stats['cached_svgs'] = (int) $wpdb->get_var(
    "SELECT COUNT(DISTINCT post_id)
     FROM {$wpdb->postmeta}
     WHERE meta_key LIKE '_listeo_svg_cache_%'"
  );

  // Count legacy cache entries
  $stats['legacy_cache'] = (int) $wpdb->get_var(
    "SELECT COUNT(*)
     FROM {$wpdb->postmeta}
     WHERE meta_key = '_elementor_inline_svg'"
  );

  // Estimate total cache size
  $total_size = $wpdb->get_var(
    "SELECT SUM(LENGTH(meta_value))
     FROM {$wpdb->postmeta}
     WHERE meta_key LIKE '_listeo_svg_cache_%'"
  );
  $stats['total_cache_size'] = $total_size ? round($total_size / 1024, 2) . ' KB' : '0 KB';

  return $stats;
}

/**
 * Purge all SVG caches site-wide.
 * Nuclear option for cache issues.
 *
 * @return int Number of cache entries deleted
 */
function listeo_purge_all_svg_cache()
{
  global $wpdb;

  $deleted = $wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key LIKE '_listeo_svg_cache_%'
     OR meta_key = '_elementor_inline_svg'"
  );

  // Clear object cache group
  wp_cache_flush_group('listeo_svg');

  return $deleted;
}

/**
 * Register WP-CLI command if available.
 */
if (defined('WP_CLI') && WP_CLI) {
  WP_CLI::add_command('listeo svg-cache', function ($args, $assoc_args) {
    $action = isset($args[0]) ? $args[0] : 'stats';

    switch ($action) {
      case 'stats':
        $stats = listeo_get_svg_cache_stats();
        WP_CLI::success('SVG Cache Statistics:');
        WP_CLI::log('Cached SVGs: ' . $stats['cached_svgs']);
        WP_CLI::log('Legacy Cache: ' . $stats['legacy_cache']);
        WP_CLI::log('Total Size: ' . $stats['total_cache_size']);
        break;

      case 'clear':
        $deleted = listeo_purge_all_svg_cache();
        WP_CLI::success("Cleared $deleted SVG cache entries.");
        break;

      case 'clear-legacy':
        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_inline_svg'");
        WP_CLI::success("Cleared $deleted legacy cache entries.");
        break;

      default:
        WP_CLI::error('Unknown action. Use: stats, clear, or clear-legacy');
    }
  });
}

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 * @return array
 */
function recursive_array_search($needle, $haystack)
{
  foreach ($haystack as $key => $value) {
    $current_key = $key;
    if ($needle === $value or (is_array($value) && recursive_array_search($needle, $value) !== false)) {
      return $current_key;
    }
  }
  return false;
}

function listeo_body_classes($classes)
{
  // Adds a class of group-blog to blogs with more than 1 published author.
  if (is_multi_author()) {
    $classes[] = 'group-blog';
  }

  global $post;




  // Adds a class of hfeed to non-singular pages.
  if (!is_singular()) {
    $classes[] = 'hfeed';
  }

  $submit_page = get_option('listeo_submit_page');
  if (is_page($submit_page)) {
    $classes[] = 'add-listing-dashboard-template';
  }

  if (!is_user_logged_in()) {
    $classes[] = 'user_not_logged_in';
  }
  if ((is_page_template('template-home-search.php') || is_page_template('template-home-search-splash.php'))  && (get_option('listeo_home_transparent_header') == 'enable')) {
    $classes[] = 'transparent-header';
  } else {
    $classes[] = 'solid-header';
  }

  if (is_page()) {
    if (get_post_meta($post->ID, 'listeo_transparent_header', true)) {
      $classes[] = 'transparent-header';
    }
    if (get_post_meta($post->ID, 'listeo_header_shadow', true)) {
      $classes[] = 'header-shadow-disabled';
    }
    // if (get_post_meta($post->ID, 'listeo_full_width_header', true) == 'enable') {
    //   $classes[] = 'hws-header';
    // }
  }
  if (get_option('listeo_full_width_header') == 'true') {
    if (is_page() && (get_post_meta($post->ID, 'listeo_full_width_header', true) == 'disable')) {
    } else {
      // exclude that for template-dashboard and listing archive if not set to full width
      $classes[] = 'hws-header';
    }
  }
  $top_layout = get_option('pp_listings_top_layout', 'map');

  if (is_post_type_archive('listing') || is_tax('listing_category') || is_tax('region') || is_tax('listing_feature')) {
    $top_layout = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_top_layout', true);
    
    if (empty($top_layout)) {
      $top_layout = get_option('pp_listings_top_layout', 'map');
    }
    $classes[] = $top_layout . '-archive-listings-layout';
  }
  if (is_page_template('template-home-search.php')  && (get_option('listeo_home_solid_background') == 'enable')) {
    $classes[] = 'solid-bg-home-banner';
  }
  if (function_exists('dokan_get_profile_progressbar')) {
    $classes[] = 'dokan-pro-active';
  }

  // if is single listing page
  if (is_singular('listing')) {
    // get type of listing
    $listing_type = get_post_meta($post->ID, '_listing_type', true);
    $classes[] = 'listing-type-' . $listing_type;
  }



  // $is_elementor_page = get_post_meta( $post->ID, '_elementor_data', true );

  // if ( !! $is_elementor_page ) {
  //       $data = @json_decode( $is_elementor_page, true );

  //       $get_settings = new \ThzelGetElementSettings( $post->ID,'listeo-homebanner'); 
  //       $settings = $get_settings->get_settings();
  //        if(is_array($settings) && isset($settings['header_style'])){
  //           unset($classes['transparent-header']);
  //           unset($classes['solid-header']);
  //           $classes[] = $settings['header_style'];
  //        }
  // }


  $post_type_taxonomies = get_object_taxonomies('listing', 'names');
  $is_tax_archive = array_reduce($post_type_taxonomies, function ($carry, $taxonomy) {
    return $carry || is_tax($taxonomy);
  }, false);

  if ((is_post_type_archive('listing') || $is_tax_archive) && get_option('pp_listings_top_layout') == 'half') {
    $classes[] = 'page-template-template-split-map';
  }
  if (get_option('listeo_fw_header') || is_page_template('template-home-search-splash.php')) {
    $classes[] = 'full-width-header';
  }
  if (get_option('listeo_marker_no_icon') == 'no_icon') {
    $classes[] = 'no-map-marker-icon ';
  }
  if (get_option('listeo_slim_mobile_menu', 'true') == 'true') {
    $classes[] = 'slim-mobile-menu';
  }
  if (get_option('listeo_custom_mobile_menu_colors') == 'enable') {
    $classes[] = 'custom-menu-colors';
  }
  if (get_option('listeo_dark_mode', 'disable') == 'disable') {

    if (get_option('listeo_dashboard_color_scheme') == 'light' || get_option('listeo_dashboard_color_scheme') != 'dark') {
      $classes[] = 'light-dashboard';
    } else {
      $classes[] = 'dark-dashboard';
    }
  }

  

  return $classes;
}
add_filter('body_class', 'listeo_body_classes');


function listeo_elementor_widget_find($data, $findkey)
{
  if (is_array($data)) {
    foreach ($data as $d) {

      if ($d && !empty($d['id']) && $d['id'] === $findkey) {
        return $d;
      }
      if ($d && !empty($d['elements']) && is_array($d['elements'])) {
        $value = listeo_elementor_widget_find($d['elements'], $findkey);
        if ($value) {
          return $value;
        }
      }
    }
  }

  return false;
}

add_action('pre_user_query', 'my_custom_users_search');
function my_custom_users_search($args)
{
  if (isset($args->query_vars['and2or']))
    $args->query_where = str_replace(') AND (', ') OR (', $args->query_where);
}
/**
 * Add a pingback url auto-discovery header for singularly identifiable articles.
 */
function listeo_pingback_header()
{
  if (is_singular() && pings_open()) {
    echo '<link rel="pingback" href="', esc_url(get_bloginfo('pingback_url')), '">';
  }
}
add_action('wp_head', 'listeo_pingback_header');



function workscout_get_rating_class($average)
{
  if (!$average) {
    $class = "no-stars";
  } else {
    switch ($average) {

      case $average >= 1 && $average < 1.5:
        $class = "one-stars";
        break;
      case $average >= 1.5 && $average < 2:
        $class = "one-and-half-stars";
        break;
      case $average >= 2 && $average < 2.5:
        $class = "two-stars";
        break;
      case $average >= 2.5 && $average < 3:
        $class = "two-and-half-stars";
        break;
      case $average >= 3 && $average < 3.5:
        $class = "three-stars";
        break;
      case $average >= 3.5 && $average < 4:
        $class = "three-and-half-stars";
        break;
      case $average >= 4 && $average < 4.5:
        $class = "four-stars";
        break;
      case $average >= 4.5 && $average < 5:
        $class = "four-and-half-stars";
        break;
      case $average >= 5:
        $class = "five-stars";
        break;

      default:
        $class = "no-stars";
        break;
    }
  }
  return $class;
}


function wsl_findeo_use_fontawesome_icons($provider_id, $provider_name, $authenticate_url)
{
  ?>
  <a rel="nofollow" href="<?php echo $authenticate_url; ?>" data-provider="<?php echo $provider_id ?>" class="wp-social-login-provider wp-social-login-provider-<?php echo strtolower($provider_id); ?>">
    <span>
      <i class="fa fa-<?php echo strtolower($provider_id); ?>"></i><?php echo $provider_name; ?>
    </span>
  </a>
<?php
}


function listeo_generate_star_rating($rating, $review_count = 0) {
  // Ensure rating is between 0 and 5
  $rating = max(0, min(5, floatval($rating)));
  $review_count = intval($review_count);
  
  $stars_html = '';
  
  // Generate 5 stars
  for ($i = 1; $i <= 5; $i++) {
      if ($i <= floor($rating)) {
          // Full star
          $stars_html .= '<i class="fa-solid fa-star"></i>' . "\n                            ";
      } elseif ($i == ceil($rating) && fmod($rating, 1) != 0) {
          // Half star (optional - you can use this for half ratings)
          $stars_html .= '<i class="fa-solid fa-star-half-stroke"></i>' . "\n                            ";
      } else {
          // Empty star
          $stars_html .= '<i class="fa-solid fa-star" style="color: #dadada"></i>' . "\n                            ";
      }
  }
  
  // Remove trailing whitespace
  $stars_html = rtrim($stars_html);
  
  $html = '<div class="listing-rating-nl">
                      <div class="stars-nl">
                          ' . $stars_html . '
                      </div>
                       <div class="rating-text-nl">' . number_format($rating, 1) . '';
if($review_count > 0) {
  $html .= ' <span>(' . $review_count . ')</span>';
}
  $html .= '</div></div> ';
  
  return $html;
}

add_filter('wsl_render_auth_widget_alter_provider_icon_markup', 'wsl_findeo_use_fontawesome_icons', 10, 3);
/**
 * Customize the PageNavi HTML before it is output
 */
add_filter('wp_pagenavi', 'listeo_pagination', 10, 2);
function listeo_pagination($html)
{
  $out = '';
  //wrap a's and span's in li's

  $out = str_replace("<a", "<li><a", $html);
  $out = str_replace("</a>", "</a></li>", $out);
  $out = str_replace("<span", "<li><span", $out);
  $out = str_replace("</span>", "</span></li>", $out);
  $out = str_replace("<div class='wp-pagenavi' role='navigation'>", "", $out);
  $out = str_replace("</div>", "", $out);
  return '<div class="pagination"><ul>' . $out . '</ul></div>';
}

function listeo_disable_sticky_footer($sticky)
{
  if (is_404()) {
    $sticky = false;
  }
  return $sticky;
}
add_action('listeo_sticky_footer_filter', 'listeo_disable_sticky_footer');


function get_listeo_icons_dropdown($sel = '')
{
  // $icons = vc_iconpicker_type_iconsmind(array());
  $output = '<option value="">' . esc_html__('no icon', 'listeo') . '</option>';

  $sl_icons = purethemes_get_simple_line_icons();

  foreach ($sl_icons as $icon) {
    $output .= '<option value="' . esc_attr($icon) . '" ' . (strcmp($icon, $sel) === 0 ? 'selected' : '') . '>' . esc_html($icon) . '(Simple Line)</option>' . "\n";
  }


  return $output;
}


/**
 * FontAwesome icons array
 */
function listeo_fa_icons_list()
{
  $icon = array('fas fa-house' => 'fa-house', 'fas fa-magnifying-glass' => 'fa-magnifying-glass', 'far fa-user' => 'fa-user', 'fas fa-user' => 'fa-user', 'fab fa-facebook' => 'fa-facebook', 'fas fa-check' => 'fa-check', 'fas fa-download' => 'fa-download', 'fab fa-twitter' => 'fa-twitter', ' fab fa-x-twitter' => 'X (Twitter)', 'far fa-image' => 'fa-image', 'fas fa-image' => 'fa-image', 'fab fa-instagram' => 'fa-instagram', 'fas fa-phone' => 'fa-phone', 'fab fa-tiktok' => 'fa-tiktok', 'fas fa-bars' => 'fa-bars', 'far fa-envelope' => 'fa-envelope', 'fas fa-envelope' => 'fa-envelope', 'fab fa-linkedin' => 'fa-linkedin', 'far fa-star' => 'fa-star', 'fas fa-star' => 'fa-star', 'fas fa-location-dot' => 'fa-location-dot', 'fab fa-github' => 'fa-github', 'fas fa-music' => 'fa-music', 'fas fa-wand-magic-sparkles' => 'fa-wand-magic-sparkles', 'far fa-heart' => 'fa-heart', 'fas fa-heart' => 'fa-heart', 'fas fa-arrow-right' => 'fa-arrow-right', 'fab fa-discord' => 'fa-discord', 'far fa-circle-xmark' => 'fa-circle-xmark', 'fas fa-circle-xmark' => 'fa-circle-xmark', 'fas fa-bomb' => 'fa-bomb', 'fas fa-poo' => 'fa-poo', 'fas fa-camera-retro' => 'fa-camera-retro', 'fas fa-xmark' => 'fa-xmark', 'fab fa-youtube' => 'fa-youtube', 'fas fa-cloud' => 'fa-cloud', 'far fa-comment' => 'fa-comment', 'fas fa-comment' => 'fa-comment', 'fas fa-caret-up' => 'fa-caret-up', 'fas fa-truck-fast' => 'fa-truck-fast', 'fab fa-wordpress' => 'fa-wordpress', 'fas fa-pen-nib' => 'fa-pen-nib', 'fas fa-arrow-up' => 'fa-arrow-up', 'fas fa-hippo' => 'fa-hippo', 'far fa-face-smile' => 'fa-face-smile', 'fas fa-face-smile' => 'fa-face-smile', 'far fa-calendar-days' => 'fa-calendar-days', 'fas fa-calendar-days' => 'fa-calendar-days', 'fas fa-paperclip' => 'fa-paperclip', 'fab fa-slack' => 'fa-slack', 'fas fa-shield-halved' => 'fa-shield-halved', 'fab fa-figma' => 'fa-figma', 'far fa-file' => 'fa-file', 'fas fa-file' => 'fa-file', 'far fa-bell' => 'fa-bell', 'fas fa-bell' => 'fa-bell', 'fas fa-cart-shopping' => 'fa-cart-shopping', 'far fa-clipboard' => 'fa-clipboard', 'fas fa-clipboard' => 'fa-clipboard', 'fas fa-filter' => 'fa-filter', 'fas fa-circle-info' => 'fa-circle-info', 'fas fa-arrow-up-from-bracket' => 'fa-arrow-up-from-bracket', 'fas fa-bolt' => 'fa-bolt', 'fas fa-car' => 'fa-car', 'fas fa-ghost' => 'fa-ghost', 'fab fa-apple' => 'fa-apple', 'fas fa-mug-hot' => 'fa-mug-hot', 'far fa-circle-user' => 'fa-circle-user', 'fas fa-circle-user' => 'fa-circle-user', 'fas fa-pen' => 'fa-pen', 'fab fa-google' => 'fa-google', 'fas fa-umbrella' => 'fa-umbrella', 'fas fa-gift' => 'fa-gift', 'fas fa-film' => 'fa-film', 'fab fa-stripe' => 'fa-stripe', 'fas fa-list' => 'fa-list', 'fas fa-gear' => 'fa-gear', 'fab fa-algolia' => 'fa-algolia', 'fas fa-trash' => 'fa-trash', 'far fa-circle-up' => 'fa-circle-up', 'fas fa-circle-up' => 'fa-circle-up', 'fab fa-docker' => 'fa-docker', 'far fa-circle-down' => 'fa-circle-down', 'fas fa-circle-down' => 'fa-circle-down', 'fas fa-inbox' => 'fa-inbox', 'fas fa-rotate-right' => 'fa-rotate-right', 'fas fa-lock' => 'fa-lock', 'fab fa-windows' => 'fa-windows', 'fas fa-headphones' => 'fa-headphones', 'fas fa-barcode' => 'fa-barcode', 'fas fa-tag' => 'fa-tag', 'fas fa-book' => 'fa-book', 'far fa-bookmark' => 'fa-bookmark', 'fas fa-bookmark' => 'fa-bookmark', 'fab fa-paypal' => 'fa-paypal', 'fas fa-print' => 'fa-print', 'fas fa-camera' => 'fa-camera', 'fab fa-stack-overflow' => 'fa-stack-overflow', 'fas fa-font' => 'fa-font', 'fas fa-video' => 'fa-video', 'fas fa-circle-half-stroke' => 'fa-circle-half-stroke', 'fas fa-droplet' => 'fa-droplet', 'far fa-pen-to-square' => 'fa-pen-to-square', 'fas fa-pen-to-square' => 'fa-pen-to-square', 'far fa-share-from-square' => 'fa-share-from-square', 'fas fa-share-from-square' => 'fa-share-from-square', 'fas fa-plus' => 'fa-plus', 'fas fa-minus' => 'fa-minus', 'fab fa-kickstarter' => 'fa-kickstarter', 'fas fa-share' => 'fa-share', 'fas fa-circle-exclamation' => 'fa-circle-exclamation', 'fas fa-fire' => 'fa-fire', 'far fa-eye' => 'fa-eye', 'fas fa-eye' => 'fa-eye', 'far fa-eye-slash' => 'fa-eye-slash', 'fas fa-eye-slash' => 'fa-eye-slash', 'fab fa-dribbble' => 'fa-dribbble', 'fas fa-plane' => 'fa-plane', 'fas fa-magnet' => 'fa-magnet', 'far fa-hand' => 'fa-hand', 'fas fa-hand' => 'fa-hand', 'far fa-folder' => 'fa-folder', 'fas fa-folder' => 'fa-folder', 'far fa-folder-open' => 'fa-folder-open', 'fas fa-folder-open' => 'fa-folder-open', 'fas fa-money-bill' => 'fa-money-bill', 'fab fa-dropbox' => 'fa-dropbox', 'far fa-thumbs-up' => 'fa-thumbs-up', 'fas fa-thumbs-up' => 'fa-thumbs-up', 'far fa-thumbs-down' => 'fa-thumbs-down', 'fas fa-thumbs-down' => 'fa-thumbs-down', 'far fa-comments' => 'fa-comments', 'fas fa-comments' => 'fa-comments', 'far fa-lemon' => 'fa-lemon', 'fas fa-lemon' => 'fa-lemon', 'fas fa-key' => 'fa-key', 'fas fa-thumbtack' => 'fa-thumbtack', 'fas fa-gears' => 'fa-gears', 'far fa-paper-plane' => 'fa-paper-plane', 'fas fa-paper-plane' => 'fa-paper-plane', 'fas fa-code' => 'fa-code', 'fab fa-squarespace' => 'fa-squarespace', 'fas fa-globe' => 'fa-globe', 'fas fa-truck' => 'fa-truck', 'fas fa-city' => 'fa-city', 'fas fa-ticket' => 'fa-ticket', 'fas fa-tree' => 'fa-tree', 'fas fa-wifi' => 'fa-wifi', 'fas fa-paint-roller' => 'fa-paint-roller', 'fas fa-bicycle' => 'fa-bicycle', 'fab fa-android' => 'fa-android', 'fas fa-sliders' => 'fa-sliders', 'fas fa-brush' => 'fa-brush', 'fas fa-hashtag' => 'fa-hashtag', 'fas fa-flask' => 'fa-flask', 'fas fa-briefcase' => 'fa-briefcase', 'far fa-compass' => 'fa-compass', 'fas fa-compass' => 'fa-compass', 'fas fa-dumpster-fire' => 'fa-dumpster-fire', 'fas fa-person' => 'fa-person', 'fas fa-person-dress' => 'fa-person-dress', 'fab fa-shopify' => 'fa-shopify', 'far fa-address-book' => 'fa-address-book', 'fas fa-address-book' => 'fa-address-book', 'fas fa-bath' => 'fa-bath', 'far fa-handshake' => 'fa-handshake', 'fas fa-handshake' => 'fa-handshake', 'fab fa-medium' => 'fa-medium', 'far fa-snowflake' => 'fa-snowflake', 'fas fa-snowflake' => 'fa-snowflake', 'fas fa-right-to-bracket' => 'fa-right-to-bracket', 'fas fa-earth-americas' => 'fa-earth-americas', 'fas fa-cloud-arrow-up' => 'fa-cloud-arrow-up', 'fas fa-binoculars' => 'fa-binoculars', 'fas fa-palette' => 'fa-palette', 'fab fa-codepen' => 'fa-codepen', 'fas fa-layer-group' => 'fa-layer-group', 'fas fa-users' => 'fa-users', 'fas fa-gamepad' => 'fa-gamepad', 'fas fa-business-time' => 'fa-business-time', 'fab fa-cloudflare' => 'fa-cloudflare', 'fas fa-feather' => 'fa-feather', 'far fa-sun' => 'fa-sun', 'fas fa-sun' => 'fa-sun', 'fas fa-link' => 'fa-link', 'fas fa-pen-fancy' => 'fa-pen-fancy', 'fab fa-airbnb' => 'fa-airbnb', 'fas fa-fish' => 'fa-fish', 'fas fa-bug' => 'fa-bug', 'fas fa-shop' => 'fa-shop', 'fas fa-mug-saucer' => 'fa-mug-saucer', 'fab fa-vimeo' => 'fa-vimeo', 'fas fa-landmark' => 'fa-landmark', 'fas fa-poo-storm' => 'fa-poo-storm', 'fab fa-whatsapp' => 'fa-whatsapp', 'fas fa-chart-simple' => 'fa-chart-simple', 'fas fa-shirt' => 'fa-shirt', 'fas fa-anchor' => 'fa-anchor', 'fas fa-quote-left' => 'fa-quote-left', 'fas fa-bag-shopping' => 'fa-bag-shopping', 'fas fa-gauge' => 'fa-gauge', 'fas fa-code-compare' => 'fa-code-compare', 'fas fa-user-secret' => 'fa-user-secret', 'fas fa-stethoscope' => 'fa-stethoscope', 'fas fa-car-side' => 'fa-car-side', 'fas fa-hand-holding-heart' => 'fa-hand-holding-heart', 'fab fa-intercom' => 'fa-intercom', 'fas fa-truck-front' => 'fa-truck-front', 'fas fa-cable-car' => 'fa-cable-car', 'fas fa-mountain-sun' => 'fa-mountain-sun', 'fas fa-location-pin' => 'fa-location-pin', 'fas fa-info' => 'fa-info', 'fas fa-user-minus' => 'fa-user-minus', 'far fa-calendar' => 'fa-calendar', 'fas fa-calendar' => 'fa-calendar', 'fas fa-cart-plus' => 'fa-cart-plus', 'far fa-clock' => 'fa-clock', 'fas fa-clock' => 'fa-clock', 'far fa-circle' => 'fa-circle', 'fas fa-circle' => 'fa-circle', 'fas fa-play' => 'fa-play', 'fas fa-cross' => 'fa-cross', 'fas fa-backward' => 'fa-backward', 'fas fa-handshake-slash' => 'fa-handshake-slash', 'fas fa-chevron-up' => 'fa-chevron-up', 'fas fa-passport' => 'fa-passport', 'fab fa-usps' => 'fa-usps', 'fas fa-question' => 'fa-question', 'fas fa-pencil' => 'fa-pencil', 'fas fa-phone-volume' => 'fa-phone-volume', 'fab fa-wix' => 'fa-wix', 'fas fa-upload' => 'fa-upload', 'fas fa-strikethrough' => 'fa-strikethrough', 'fab fa-line' => 'fa-line', 'far fa-credit-card' => 'fa-credit-card', 'fas fa-credit-card' => 'fa-credit-card', 'fas fa-street-view' => 'fa-street-view', 'fas fa-database' => 'fa-database', 'far fa-copy' => 'fa-copy', 'fas fa-copy' => 'fa-copy', 'fas fa-mobile' => 'fa-mobile', 'far fa-square' => 'fa-square', 'fas fa-square' => 'fa-square', 'fas fa-sort' => 'fa-sort', 'fas fa-forward' => 'fa-forward', 'fas fa-hourglass-start' => 'fa-hourglass-start', 'fab fa-behance' => 'fa-behance', 'far fa-newspaper' => 'fa-newspaper', 'fas fa-newspaper' => 'fa-newspaper', 'fas fa-notes-medical' => 'fa-notes-medical', 'fas fa-table' => 'fa-table', 'far fa-building' => 'fa-building', 'fas fa-building' => 'fa-building', 'fas fa-stop' => 'fa-stop', 'fab fa-openid' => 'fa-openid', 'fas fa-store' => 'fa-store', 'far fa-flag' => 'fa-flag', 'fas fa-flag' => 'fa-flag', 'fab fa-product-hunt' => 'fa-product-hunt', 'far fa-file-excel' => 'fa-file-excel', 'fas fa-file-excel' => 'fa-file-excel', 'fas fa-network-wired' => 'fa-network-wired', 'fas fa-cash-register' => 'fa-cash-register', 'fas fa-file-export' => 'fa-file-export', 'fab fa-internet-explorer' => 'fa-internet-explorer', 'far fa-hand-point-up' => 'fa-hand-point-up', 'fas fa-hand-point-up' => 'fa-hand-point-up', 'fab fa-pagelines' => 'fa-pagelines', 'fas fa-angle-up' => 'fa-angle-up', 'fas fa-shield' => 'fa-shield', 'fab fa-teamspeak' => 'fa-teamspeak', 'far fa-address-card' => 'fa-address-card', 'fas fa-address-card' => 'fa-address-card', 'fas fa-expand' => 'fa-expand', 'fas fa-flag-checkered' => 'fa-flag-checkered', 'fab fa-html5' => 'fa-html5', 'fas fa-quote-right' => 'fa-quote-right', 'fas fa-tags' => 'fa-tags', 'fas fa-server' => 'fa-server', 'fas fa-user-nurse' => 'fa-user-nurse', 'fas fa-video-slash' => 'fa-video-slash', 'fas fa-arrow-down' => 'fa-arrow-down', 'fas fa-blog' => 'fa-blog', 'fas fa-school' => 'fa-school', 'fas fa-file-invoice' => 'fa-file-invoice', 'fas fa-rocket' => 'fa-rocket', 'fas fa-spinner' => 'fa-spinner', 'fab fa-telegram' => 'fa-telegram', 'fas fa-tty' => 'fa-tty', 'fas fa-exclamation' => 'fa-exclamation', 'fas fa-water' => 'fa-water', 'far fa-registered' => 'fa-registered', 'fas fa-registered' => 'fa-registered', 'fas fa-signature' => 'fa-signature', 'fas fa-laptop' => 'fa-laptop', 'fas fa-restroom' => 'fa-restroom', 'fas fa-power-off' => 'fa-power-off', 'fas fa-sitemap' => 'fa-sitemap', 'fas fa-icons' => 'fa-icons', 'fas fa-desktop' => 'fa-desktop', 'far fa-moon' => 'fa-moon', 'fas fa-moon' => 'fa-moon', 'fas fa-calendar-week' => 'fa-calendar-week', 'fab fa-pinterest' => 'fa-pinterest', 'fas fa-pause' => 'fa-pause', 'far fa-file-word' => 'fa-file-word', 'fas fa-file-word' => 'fa-file-word', 'fas fa-vials' => 'fa-vials', 'fas fa-language' => 'fa-language', 'fas fa-door-open' => 'fa-door-open', 'fas fa-brain' => 'fa-brain', 'fas fa-hotel' => 'fa-hotel', 'fas fa-marker' => 'fa-marker', 'fas fa-star-of-life' => 'fa-star-of-life', 'fas fa-leaf' => 'fa-leaf', 'fas fa-walkie-talkie' => 'fa-walkie-talkie', 'fas fa-shower' => 'fa-shower', 'fab fa-dashcube' => 'fa-dashcube', 'fas fa-caret-down' => 'fa-caret-down', 'fab fa-ideal' => 'fa-ideal', 'fab fa-salesforce' => 'fa-salesforce', 'fas fa-file-import' => 'fa-file-import', 'fas fa-place-of-worship' => 'fa-place-of-worship', 'fas fa-wallet' => 'fa-wallet', 'fas fa-slash' => 'fa-slash', 'fab fa-readme' => 'fa-readme', 'fas fa-award' => 'fa-award', 'fas fa-toggle-on' => 'fa-toggle-on', 'fas fa-ship' => 'fa-ship', 'fab fa-free-code-camp' => 'fa-free-code-camp', 'fab fa-soundcloud' => 'fa-soundcloud', 'fas fa-chalkboard' => 'fa-chalkboard', 'fab fa-square-twitter' => 'fa-square-twitter', 'fas fa-hands' => 'fa-hands', 'fas fa-signal' => 'fa-signal', 'fas fa-motorcycle' => 'fa-motorcycle', 'fas fa-arrow-up-right-from-square' => 'fa-arrow-up-right-from-square', 'fas fa-audio-description' => 'fa-audio-description', 'fab fa-accessible-icon' => 'fa-accessible-icon', 'fas fa-seedling' => 'fa-seedling', 'far fa-closed-captioning' => 'fa-closed-captioning', 'fas fa-closed-captioning' => 'fa-closed-captioning', 'fas fa-train' => 'fa-train', 'fab fa-cc-visa' => 'fa-cc-visa', 'fas fa-arrow-left' => 'fa-arrow-left', 'fas fa-wrench' => 'fa-wrench', 'fas fa-microchip' => 'fa-microchip', 'fas fa-record-vinyl' => 'fa-record-vinyl', 'fab fa-goodreads-g' => 'fa-goodreads-g', 'fas fa-trophy' => 'fa-trophy', 'fas fa-hammer' => 'fa-hammer', 'fas fa-diamond' => 'fa-diamond', 'fas fa-robot' => 'fa-robot', 'far fa-file-pdf' => 'fa-file-pdf', 'fas fa-file-pdf' => 'fa-file-pdf', 'fab fa-google-play' => 'fa-google-play', 'far fa-hospital' => 'fa-hospital', 'fas fa-hospital' => 'fa-hospital', 'fas fa-file-contract' => 'fa-file-contract', 'fas fa-square-xmark' => 'fa-square-xmark', 'far fa-square-check' => 'fa-square-check', 'fas fa-square-check' => 'fa-square-check', 'fas fa-crown' => 'fa-crown', 'fab fa-react' => 'fa-react', 'fas fa-user-plus' => 'fa-user-plus', 'fas fa-virus' => 'fa-virus', 'fas fa-child' => 'fa-child', 'fas fa-repeat' => 'fa-repeat', 'fas fa-cube' => 'fa-cube', 'far fa-copyright' => 'fa-copyright', 'fas fa-copyright' => 'fa-copyright', 'fas fa-medal' => 'fa-medal', 'fas fa-bullseye' => 'fa-bullseye', 'fas fa-mask' => 'fa-mask', 'far fa-circle-check' => 'fa-circle-check', 'fas fa-circle-check' => 'fa-circle-check', 'fas fa-radio' => 'fa-radio', 'fas fa-reply' => 'fa-reply', 'fas fa-chair' => 'fa-chair', 'fas fa-route' => 'fa-route', 'fab fa-wikipedia-w' => 'fa-wikipedia-w', 'fas fa-plug' => 'fa-plug', 'fas fa-calculator' => 'fa-calculator', 'fas fa-dragon' => 'fa-dragon', 'fas fa-certificate' => 'fa-certificate', 'fas fa-fingerprint' => 'fa-fingerprint', 'fas fa-road' => 'fa-road', 'fas fa-crosshairs' => 'fa-crosshairs', 'fas fa-heading' => 'fa-heading', 'fas fa-percent' => 'fa-percent', 'fab fa-square-js' => 'fa-square-js', 'fas fa-user-tie' => 'fa-user-tie', 'fab fa-java' => 'fa-java', 'far fa-square-minus' => 'fa-square-minus', 'fas fa-square-minus' => 'fa-square-minus', 'fas fa-i-cursor' => 'fa-i-cursor', 'fas fa-church' => 'fa-church', 'fas fa-joint' => 'fa-joint', 'fas fa-comments-dollar' => 'fa-comments-dollar', 'fas fa-truck-monster' => 'fa-truck-monster', 'fas fa-recycle' => 'fa-recycle', 'fab fa-square-pinterest' => 'fa-square-pinterest', 'fas fa-warehouse' => 'fa-warehouse', 'fas fa-ruler' => 'fa-ruler', 'fab fa-python' => 'fa-python', 'fas fa-soap' => 'fa-soap', 'fas fa-scroll' => 'fa-scroll', 'fab fa-skype' => 'fa-skype', 'fas fa-coins' => 'fa-coins', 'fas fa-wind' => 'fa-wind', 'fas fa-baby' => 'fa-baby', 'far fa-lightbulb' => 'fa-lightbulb', 'fas fa-lightbulb' => 'fa-lightbulb', 'fab fa-linux' => 'fa-linux', 'fab fa-node' => 'fa-node', 'fab fa-rebel' => 'fa-rebel', 'fas fa-voicemail' => 'fa-voicemail', 'fas fa-puzzle-piece' => 'fa-puzzle-piece', 'far fa-keyboard' => 'fa-keyboard', 'fas fa-keyboard' => 'fa-keyboard', 'far fa-clone' => 'fa-clone', 'fas fa-clone' => 'fa-clone', 'fas fa-eraser' => 'fa-eraser', 'fas fa-wine-bottle' => 'fa-wine-bottle', 'fas fa-dice' => 'fa-dice', 'fas fa-receipt' => 'fa-receipt', 'fas fa-ring' => 'fa-ring', 'fab fa-etsy' => 'fa-etsy', 'fas fa-unlock' => 'fa-unlock', 'fab fa-discourse' => 'fa-discourse', 'fas fa-solar-panel' => 'fa-solar-panel', 'fas fa-ruler-vertical' => 'fa-ruler-vertical', 'fas fa-circle-notch' => 'fa-circle-notch', 'fas fa-people-arrows' => 'fa-people-arrows', 'fas fa-dollar-sign' => 'fa-dollar-sign', 'fab fa-amazon' => 'fa-amazon', 'fas fa-tablet' => 'fa-tablet', 'fas fa-not-equal' => 'fa-not-equal', 'fas fa-glasses' => 'fa-glasses', 'fas fa-headset' => 'fa-headset', 'fas fa-code-branch' => 'fa-code-branch', 'fab fa-glide-g' => 'fa-glide-g', 'fas fa-gopuram' => 'fa-gopuram', 'far fa-images' => 'fa-images', 'fas fa-images' => 'fa-images', 'far fa-window-restore' => 'fa-window-restore', 'fas fa-window-restore' => 'fa-window-restore', 'fas fa-industry' => 'fa-industry', 'fab fa-gitlab' => 'fa-gitlab', 'fab fa-spotify' => 'fa-spotify', 'fas fa-stamp' => 'fa-stamp', 'fas fa-microphone-slash' => 'fa-microphone-slash', 'fab fa-think-peaks' => 'fa-think-peaks', 'fab fa-microsoft' => 'fa-microsoft', 'fas fa-cookie-bite' => 'fa-cookie-bite', 'fas fa-otter' => 'fa-otter', 'fas fa-chevron-down' => 'fa-chevron-down', 'fas fa-kiwi-bird' => 'fa-kiwi-bird', 'fas fa-viruses' => 'fa-viruses', 'fab fa-elementor' => 'fa-elementor', 'fab fa-pied-piper' => 'fa-pied-piper', 'fab fa-square-youtube' => 'fa-square-youtube', 'fas fa-umbrella-beach' => 'fa-umbrella-beach', 'fas fa-subscript' => 'fa-subscript', 'fas fa-tablets' => 'fa-tablets', 'fab fa-cc-mastercard' => 'fa-cc-mastercard', 'fab fa-facebook-messenger' => 'fa-facebook-messenger', 'fab fa-atlassian' => 'fa-atlassian', 'fab fa-playstation' => 'fa-playstation', 'fab fa-fly' => 'fa-fly', 'fas fa-microphone' => 'fa-microphone', 'fab fa-meetup' => 'fa-meetup', 'fas fa-border-none' => 'fa-border-none', 'fas fa-dumbbell' => 'fa-dumbbell', 'fab fa-twitch' => 'fa-twitch', 'fas fa-plane-departure' => 'fa-plane-departure', 'fab fa-waze' => 'fa-waze', 'fas fa-z' => 'fa-z', 'fas fa-yin-yang' => 'fa-yin-yang', 'fas fa-yen-sign' => 'fa-yen-sign', 'fas fa-y' => 'fa-y', 'fas fa-xmarks-lines' => 'fa-xmarks-lines', 'fas fa-x-ray' => 'fa-x-ray', 'fas fa-x' => 'fa-x', 'fas fa-worm' => 'fa-worm', 'fas fa-won-sign' => 'fa-won-sign', 'fas fa-wine-glass-empty' => 'fa-wine-glass-empty', 'fas fa-wine-glass' => 'fa-wine-glass', 'far fa-window-minimize' => 'fa-window-minimize', 'fas fa-window-minimize' => 'fa-window-minimize', 'far fa-window-maximize' => 'fa-window-maximize', 'fas fa-window-maximize' => 'fa-window-maximize', 'fas fa-whiskey-glass' => 'fa-whiskey-glass', 'fas fa-wheelchair-move' => 'fa-wheelchair-move', 'fas fa-wheelchair' => 'fa-wheelchair', 'fas fa-wheat-awn-circle-exclamation' => 'fa-wheat-awn-circle-exclamation', 'fas fa-wheat-awn' => 'fa-wheat-awn', 'fas fa-weight-scale' => 'fa-weight-scale', 'fas fa-weight-hanging' => 'fa-weight-hanging', 'fas fa-wave-square' => 'fa-wave-square', 'fas fa-water-ladder' => 'fa-water-ladder', 'fas fa-wand-sparkles' => 'fa-wand-sparkles', 'fas fa-wand-magic' => 'fa-wand-magic', 'fas fa-w' => 'fa-w', 'fas fa-vr-cardboard' => 'fa-vr-cardboard', 'fas fa-volume-xmark' => 'fa-volume-xmark', 'fas fa-volume-off' => 'fa-volume-off', 'fas fa-volume-low' => 'fa-volume-low', 'fas fa-volume-high' => 'fa-volume-high', 'fas fa-volleyball' => 'fa-volleyball', 'fas fa-volcano' => 'fa-volcano', 'fas fa-virus-slash' => 'fa-virus-slash', 'fas fa-virus-covid-slash' => 'fa-virus-covid-slash', 'fas fa-virus-covid' => 'fa-virus-covid', 'fas fa-vihara' => 'fa-vihara', 'fas fa-vial-virus' => 'fa-vial-virus', 'fas fa-vial-circle-check' => 'fa-vial-circle-check', 'fas fa-vial' => 'fa-vial', 'fas fa-vest-patches' => 'fa-vest-patches', 'fas fa-vest' => 'fa-vest', 'fas fa-venus-mars' => 'fa-venus-mars', 'fas fa-venus-double' => 'fa-venus-double', 'fas fa-venus' => 'fa-venus', 'fas fa-vector-square' => 'fa-vector-square', 'fas fa-vault' => 'fa-vault', 'fas fa-van-shuttle' => 'fa-van-shuttle', 'fas fa-v' => 'fa-v', 'fas fa-utensils' => 'fa-utensils', 'fas fa-users-viewfinder' => 'fa-users-viewfinder', 'fas fa-users-slash' => 'fa-users-slash', 'fas fa-users-rectangle' => 'fa-users-rectangle', 'fas fa-users-rays' => 'fa-users-rays', 'fas fa-users-line' => 'fa-users-line', 'fas fa-users-gear' => 'fa-users-gear', 'fas fa-users-between-lines' => 'fa-users-between-lines', 'fas fa-user-xmark' => 'fa-user-xmark', 'fas fa-user-tag' => 'fa-user-tag', 'fas fa-user-slash' => 'fa-user-slash', 'fas fa-user-shield' => 'fa-user-shield', 'fas fa-user-pen' => 'fa-user-pen', 'fas fa-user-ninja' => 'fa-user-ninja', 'fas fa-user-lock' => 'fa-user-lock', 'fas fa-user-large-slash' => 'fa-user-large-slash', 'fas fa-user-large' => 'fa-user-large', 'fas fa-user-injured' => 'fa-user-injured', 'fas fa-user-group' => 'fa-user-group', 'fas fa-user-graduate' => 'fa-user-graduate', 'fas fa-user-gear' => 'fa-user-gear', 'fas fa-user-doctor' => 'fa-user-doctor', 'fas fa-user-clock' => 'fa-user-clock', 'fas fa-user-check' => 'fa-user-check', 'fas fa-user-astronaut' => 'fa-user-astronaut', 'fas fa-up-right-from-square' => 'fa-up-right-from-square', 'fas fa-up-right-and-down-left-from-center' => 'fa-up-right-and-down-left-from-center', 'fas fa-up-long' => 'fa-up-long', 'fas fa-up-down-left-right' => 'fa-up-down-left-right', 'fas fa-up-down' => 'fa-up-down', 'fas fa-unlock-keyhole' => 'fa-unlock-keyhole', 'fas fa-universal-access' => 'fa-universal-access', 'fas fa-underline' => 'fa-underline', 'fas fa-u' => 'fa-u', 'fas fa-tv' => 'fa-tv', 'fas fa-turn-up' => 'fa-turn-up', 'fas fa-turn-down' => 'fa-turn-down', 'fas fa-turkish-lira-sign' => 'fa-turkish-lira-sign', 'fas fa-truck-ramp-box' => 'fa-truck-ramp-box', 'fas fa-truck-plane' => 'fa-truck-plane', 'fas fa-truck-pickup' => 'fa-truck-pickup', 'fas fa-truck-moving' => 'fa-truck-moving', 'fas fa-truck-medical' => 'fa-truck-medical', 'fas fa-truck-field-un' => 'fa-truck-field-un', 'fas fa-truck-field' => 'fa-truck-field', 'fas fa-truck-droplet' => 'fa-truck-droplet', 'fas fa-truck-arrow-right' => 'fa-truck-arrow-right', 'fas fa-trowel-bricks' => 'fa-trowel-bricks', 'fas fa-trowel' => 'fa-trowel', 'fas fa-triangle-exclamation' => 'fa-triangle-exclamation', 'fas fa-tree-city' => 'fa-tree-city', 'fas fa-trash-can-arrow-up' => 'fa-trash-can-arrow-up', 'far fa-trash-can' => 'fa-trash-can', 'fas fa-trash-can' => 'fa-trash-can', 'fas fa-trash-arrow-up' => 'fa-trash-arrow-up', 'fas fa-transgender' => 'fa-transgender', 'fas fa-train-tram' => 'fa-train-tram', 'fas fa-train-subway' => 'fa-train-subway', 'fas fa-trailer' => 'fa-trailer', 'fas fa-traffic-light' => 'fa-traffic-light', 'fas fa-trademark' => 'fa-trademark', 'fas fa-tractor' => 'fa-tractor', 'fas fa-tower-observation' => 'fa-tower-observation', 'fas fa-tower-cell' => 'fa-tower-cell', 'fas fa-tower-broadcast' => 'fa-tower-broadcast', 'fas fa-tornado' => 'fa-tornado', 'fas fa-torii-gate' => 'fa-torii-gate', 'fas fa-tooth' => 'fa-tooth', 'fas fa-toolbox' => 'fa-toolbox', 'fas fa-toilets-portable' => 'fa-toilets-portable', 'fas fa-toilet-portable' => 'fa-toilet-portable', 'fas fa-toilet-paper-slash' => 'fa-toilet-paper-slash', 'fas fa-toilet-paper' => 'fa-toilet-paper', 'fas fa-toilet' => 'fa-toilet', 'fas fa-toggle-off' => 'fa-toggle-off', 'fas fa-timeline' => 'fa-timeline', 'fas fa-ticket-simple' => 'fa-ticket-simple', 'fas fa-thermometer' => 'fa-thermometer', 'fas fa-text-width' => 'fa-text-width', 'fas fa-text-slash' => 'fa-text-slash', 'fas fa-text-height' => 'fa-text-height', 'fas fa-terminal' => 'fa-terminal', 'fas fa-tents' => 'fa-tents', 'fas fa-tent-arrows-down' => 'fa-tent-arrows-down', 'fas fa-tent-arrow-turn-left' => 'fa-tent-arrow-turn-left', 'fas fa-tent-arrow-left-right' => 'fa-tent-arrow-left-right', 'fas fa-tent-arrow-down-to-line' => 'fa-tent-arrow-down-to-line', 'fas fa-tent' => 'fa-tent', 'fas fa-tenge-sign' => 'fa-tenge-sign', 'fas fa-temperature-three-quarters' => 'fa-temperature-three-quarters', 'fas fa-temperature-quarter' => 'fa-temperature-quarter', 'fas fa-temperature-low' => 'fa-temperature-low', 'fas fa-temperature-high' => 'fa-temperature-high', 'fas fa-temperature-half' => 'fa-temperature-half', 'fas fa-temperature-full' => 'fa-temperature-full', 'fas fa-temperature-empty' => 'fa-temperature-empty', 'fas fa-temperature-arrow-up' => 'fa-temperature-arrow-up', 'fas fa-temperature-arrow-down' => 'fa-temperature-arrow-down', 'fas fa-teeth-open' => 'fa-teeth-open', 'fas fa-teeth' => 'fa-teeth', 'fas fa-taxi' => 'fa-taxi', 'fas fa-tarp-droplet' => 'fa-tarp-droplet', 'fas fa-tarp' => 'fa-tarp', 'fas fa-tape' => 'fa-tape', 'fas fa-tachograph-digital' => 'fa-tachograph-digital', 'fas fa-tablet-screen-button' => 'fa-tablet-screen-button', 'fas fa-tablet-button' => 'fa-tablet-button', 'fas fa-table-tennis-paddle-ball' => 'fa-table-tennis-paddle-ball', 'fas fa-table-list' => 'fa-table-list', 'fas fa-table-columns' => 'fa-table-columns', 'fas fa-table-cells-large' => 'fa-table-cells-large', 'fas fa-table-cells' => 'fa-table-cells', 'fas fa-t' => 'fa-t', 'fas fa-syringe' => 'fa-syringe', 'fas fa-synagogue' => 'fa-synagogue', 'fas fa-swatchbook' => 'fa-swatchbook', 'fas fa-superscript' => 'fa-superscript', 'fas fa-sun-plant-wilt' => 'fa-sun-plant-wilt', 'fas fa-suitcase-rolling' => 'fa-suitcase-rolling', 'fas fa-suitcase-medical' => 'fa-suitcase-medical', 'fas fa-suitcase' => 'fa-suitcase', 'fas fa-stroopwafel' => 'fa-stroopwafel', 'fas fa-store-slash' => 'fa-store-slash', 'fas fa-stopwatch-20' => 'fa-stopwatch-20', 'fas fa-stopwatch' => 'fa-stopwatch', 'fas fa-sterling-sign' => 'fa-sterling-sign', 'fas fa-star-of-david' => 'fa-star-of-david', 'far fa-star-half-stroke' => 'fa-star-half-stroke', 'fas fa-star-half-stroke' => 'fa-star-half-stroke', 'far fa-star-half' => 'fa-star-half', 'fas fa-star-half' => 'fa-star-half', 'fas fa-star-and-crescent' => 'fa-star-and-crescent', 'fas fa-stapler' => 'fa-stapler', 'fas fa-stairs' => 'fa-stairs', 'fas fa-staff-snake' => 'fa-staff-snake', 'fas fa-square-virus' => 'fa-square-virus', 'fas fa-square-up-right' => 'fa-square-up-right', 'fas fa-square-share-nodes' => 'fa-square-share-nodes', 'fas fa-square-rss' => 'fa-square-rss', 'fas fa-square-root-variable' => 'fa-square-root-variable', 'fas fa-square-poll-vertical' => 'fa-square-poll-vertical', 'fas fa-square-poll-horizontal' => 'fa-square-poll-horizontal', 'far fa-square-plus' => 'fa-square-plus', 'fas fa-square-plus' => 'fa-square-plus', 'fas fa-square-phone-flip' => 'fa-square-phone-flip', 'fas fa-square-phone' => 'fa-square-phone', 'fas fa-square-person-confined' => 'fa-square-person-confined', 'fas fa-square-pen' => 'fa-square-pen', 'fas fa-square-parking' => 'fa-square-parking', 'fas fa-square-nfi' => 'fa-square-nfi', 'fas fa-square-h' => 'fa-square-h', 'far fa-square-full' => 'fa-square-full', 'fas fa-square-full' => 'fa-square-full', 'fas fa-square-envelope' => 'fa-square-envelope', 'far fa-square-caret-up' => 'fa-square-caret-up', 'fas fa-square-caret-up' => 'fa-square-caret-up', 'far fa-square-caret-right' => 'fa-square-caret-right', 'fas fa-square-caret-right' => 'fa-square-caret-right', 'far fa-square-caret-left' => 'fa-square-caret-left', 'fas fa-square-caret-left' => 'fa-square-caret-left', 'far fa-square-caret-down' => 'fa-square-caret-down', 'fas fa-square-caret-down' => 'fa-square-caret-down', 'fas fa-square-arrow-up-right' => 'fa-square-arrow-up-right', 'fas fa-spray-can-sparkles' => 'fa-spray-can-sparkles', 'fas fa-spray-can' => 'fa-spray-can', 'fas fa-spoon' => 'fa-spoon', 'fas fa-splotch' => 'fa-splotch', 'fas fa-spider' => 'fa-spider', 'fas fa-spell-check' => 'fa-spell-check', 'fas fa-spaghetti-monster-flying' => 'fa-spaghetti-monster-flying', 'fas fa-spa' => 'fa-spa', 'fas fa-sort-up' => 'fa-sort-up', 'fas fa-sort-down' => 'fa-sort-down', 'fas fa-socks' => 'fa-socks', 'fas fa-snowplow' => 'fa-snowplow', 'fas fa-snowman' => 'fa-snowman', 'fas fa-smoking' => 'fa-smoking', 'fas fa-smog' => 'fa-smog', 'fas fa-sleigh' => 'fa-sleigh', 'fas fa-skull-crossbones' => 'fa-skull-crossbones', 'fas fa-skull' => 'fa-skull', 'fas fa-sink' => 'fa-sink', 'fas fa-sim-card' => 'fa-sim-card', 'fas fa-signs-post' => 'fa-signs-post', 'fas fa-sign-hanging' => 'fa-sign-hanging', 'fas fa-shuttle-space' => 'fa-shuttle-space', 'fas fa-shuffle' => 'fa-shuffle', 'fas fa-shrimp' => 'fa-shrimp', 'fas fa-shop-slash' => 'fa-shop-slash', 'fas fa-shop-lock' => 'fa-shop-lock', 'fas fa-shoe-prints' => 'fa-shoe-prints', 'fas fa-shield-virus' => 'fa-shield-virus', 'fas fa-shield-heart' => 'fa-shield-heart', 'fas fa-shield-dog' => 'fa-shield-dog', 'fas fa-shield-cat' => 'fa-shield-cat', 'fas fa-shekel-sign' => 'fa-shekel-sign', 'fas fa-sheet-plastic' => 'fa-sheet-plastic', 'fas fa-share-nodes' => 'fa-share-nodes', 'fas fa-shapes' => 'fa-shapes', 'fas fa-section' => 'fa-section', 'fas fa-sd-card' => 'fa-sd-card', 'fas fa-scroll-torah' => 'fa-scroll-torah', 'fas fa-screwdriver-wrench' => 'fa-screwdriver-wrench', 'fas fa-screwdriver' => 'fa-screwdriver', 'fas fa-scissors' => 'fa-scissors', 'fas fa-school-lock' => 'fa-school-lock', 'fas fa-school-flag' => 'fa-school-flag', 'fas fa-school-circle-xmark' => 'fa-school-circle-xmark', 'fas fa-school-circle-exclamation' => 'fa-school-circle-exclamation', 'fas fa-school-circle-check' => 'fa-school-circle-check', 'fas fa-scale-unbalanced-flip' => 'fa-scale-unbalanced-flip', 'fas fa-scale-unbalanced' => 'fa-scale-unbalanced', 'fas fa-scale-balanced' => 'fa-scale-balanced', 'fas fa-satellite-dish' => 'fa-satellite-dish', 'fas fa-satellite' => 'fa-satellite', 'fas fa-sailboat' => 'fa-sailboat', 'fas fa-sack-xmark' => 'fa-sack-xmark', 'fas fa-sack-dollar' => 'fa-sack-dollar', 'fas fa-s' => 'fa-s', 'fas fa-rupiah-sign' => 'fa-rupiah-sign', 'fas fa-rupee-sign' => 'fa-rupee-sign', 'fas fa-ruler-horizontal' => 'fa-ruler-horizontal', 'fas fa-ruler-combined' => 'fa-ruler-combined', 'fas fa-rug' => 'fa-rug', 'fas fa-ruble-sign' => 'fa-ruble-sign', 'fas fa-rss' => 'fa-rss', 'fas fa-rotate-left' => 'fa-rotate-left', 'fas fa-rotate' => 'fa-rotate', 'fas fa-road-spikes' => 'fa-road-spikes', 'fas fa-road-lock' => 'fa-road-lock', 'fas fa-road-circle-xmark' => 'fa-road-circle-xmark', 'fas fa-road-circle-exclamation' => 'fa-road-circle-exclamation', 'fas fa-road-circle-check' => 'fa-road-circle-check', 'fas fa-road-bridge' => 'fa-road-bridge', 'fas fa-road-barrier' => 'fa-road-barrier', 'fas fa-right-long' => 'fa-right-long', 'fas fa-right-left' => 'fa-right-left', 'fas fa-right-from-bracket' => 'fa-right-from-bracket', 'fas fa-ribbon' => 'fa-ribbon', 'fas fa-retweet' => 'fa-retweet', 'fas fa-republican' => 'fa-republican', 'fas fa-reply-all' => 'fa-reply-all', 'far fa-rectangle-xmark' => 'fa-rectangle-xmark', 'fas fa-rectangle-xmark' => 'fa-rectangle-xmark', 'far fa-rectangle-list' => 'fa-rectangle-list', 'fas fa-rectangle-list' => 'fa-rectangle-list', 'fas fa-rectangle-ad' => 'fa-rectangle-ad', 'fas fa-ranking-star' => 'fa-ranking-star', 'fas fa-rainbow' => 'fa-rainbow', 'fas fa-radiation' => 'fa-radiation', 'fas fa-r' => 'fa-r', 'fas fa-qrcode' => 'fa-qrcode', 'fas fa-q' => 'fa-q', 'fas fa-pump-soap' => 'fa-pump-soap', 'fas fa-pump-medical' => 'fa-pump-medical', 'fas fa-prescription-bottle-medical' => 'fa-prescription-bottle-medical', 'fas fa-prescription-bottle' => 'fa-prescription-bottle', 'fas fa-prescription' => 'fa-prescription', 'fas fa-poop' => 'fa-poop', 'fas fa-podcast' => 'fa-podcast', 'fas fa-plus-minus' => 'fa-plus-minus', 'fas fa-plug-circle-xmark' => 'fa-plug-circle-xmark', 'fas fa-plug-circle-plus' => 'fa-plug-circle-plus', 'fas fa-plug-circle-minus' => 'fa-plug-circle-minus', 'fas fa-plug-circle-exclamation' => 'fa-plug-circle-exclamation', 'fas fa-plug-circle-check' => 'fa-plug-circle-check', 'fas fa-plug-circle-bolt' => 'fa-plug-circle-bolt', 'fas fa-plate-wheat' => 'fa-plate-wheat', 'fas fa-plant-wilt' => 'fa-plant-wilt', 'fas fa-plane-up' => 'fa-plane-up', 'fas fa-plane-slash' => 'fa-plane-slash', 'fas fa-plane-lock' => 'fa-plane-lock', 'fas fa-plane-circle-xmark' => 'fa-plane-circle-xmark', 'fas fa-plane-circle-exclamation' => 'fa-plane-circle-exclamation', 'fas fa-plane-circle-check' => 'fa-plane-circle-check', 'fas fa-plane-arrival' => 'fa-plane-arrival', 'fas fa-pizza-slice' => 'fa-pizza-slice', 'fas fa-pills' => 'fa-pills', 'fas fa-piggy-bank' => 'fa-piggy-bank', 'fas fa-photo-film' => 'fa-photo-film', 'fas fa-phone-slash' => 'fa-phone-slash', 'fas fa-phone-flip' => 'fa-phone-flip', 'fas fa-peso-sign' => 'fa-peso-sign', 'fas fa-peseta-sign' => 'fa-peseta-sign', 'fas fa-person-walking-with-cane' => 'fa-person-walking-with-cane', 'fas fa-person-walking-luggage' => 'fa-person-walking-luggage', 'fas fa-person-walking-dashed-line-arrow-right' => 'fa-person-walking-dashed-line-arrow-right', 'fas fa-person-walking-arrow-right' => 'fa-person-walking-arrow-right', 'fas fa-person-walking-arrow-loop-left' => 'fa-person-walking-arrow-loop-left', 'fas fa-person-walking' => 'fa-person-walking', 'fas fa-person-through-window' => 'fa-person-through-window', 'fas fa-person-swimming' => 'fa-person-swimming', 'fas fa-person-snowboarding' => 'fa-person-snowboarding', 'fas fa-person-skiing-nordic' => 'fa-person-skiing-nordic', 'fas fa-person-skiing' => 'fa-person-skiing', 'fas fa-person-skating' => 'fa-person-skating', 'fas fa-person-shelter' => 'fa-person-shelter', 'fas fa-person-running' => 'fa-person-running', 'fas fa-person-rifle' => 'fa-person-rifle', 'fas fa-person-rays' => 'fa-person-rays', 'fas fa-person-pregnant' => 'fa-person-pregnant', 'fas fa-person-praying' => 'fa-person-praying', 'fas fa-person-military-to-person' => 'fa-person-military-to-person', 'fas fa-person-military-rifle' => 'fa-person-military-rifle', 'fas fa-person-military-pointing' => 'fa-person-military-pointing', 'fas fa-person-hiking' => 'fa-person-hiking', 'fas fa-person-harassing' => 'fa-person-harassing', 'fas fa-person-half-dress' => 'fa-person-half-dress', 'fas fa-person-falling-burst' => 'fa-person-falling-burst', 'fas fa-person-falling' => 'fa-person-falling', 'fas fa-person-drowning' => 'fa-person-drowning', 'fas fa-person-dress-burst' => 'fa-person-dress-burst', 'fas fa-person-dots-from-line' => 'fa-person-dots-from-line', 'fas fa-person-digging' => 'fa-person-digging', 'fas fa-person-circle-xmark' => 'fa-person-circle-xmark', 'fas fa-person-circle-question' => 'fa-person-circle-question', 'fas fa-person-circle-plus' => 'fa-person-circle-plus', 'fas fa-person-circle-minus' => 'fa-person-circle-minus', 'fas fa-person-circle-exclamation' => 'fa-person-circle-exclamation', 'fas fa-person-circle-check' => 'fa-person-circle-check', 'fas fa-person-chalkboard' => 'fa-person-chalkboard', 'fas fa-person-cane' => 'fa-person-cane', 'fas fa-person-burst' => 'fa-person-burst', 'fas fa-person-breastfeeding' => 'fa-person-breastfeeding', 'fas fa-person-booth' => 'fa-person-booth', 'fas fa-person-biking' => 'fa-person-biking', 'fas fa-person-arrow-up-from-line' => 'fa-person-arrow-up-from-line', 'fas fa-person-arrow-down-to-line' => 'fa-person-arrow-down-to-line', 'fas fa-pepper-hot' => 'fa-pepper-hot', 'fas fa-people-roof' => 'fa-people-roof', 'fas fa-people-robbery' => 'fa-people-robbery', 'fas fa-people-pulling' => 'fa-people-pulling', 'fas fa-people-line' => 'fa-people-line', 'fas fa-people-group' => 'fa-people-group', 'fas fa-people-carry-box' => 'fa-people-carry-box', 'fas fa-pen-ruler' => 'fa-pen-ruler', 'fas fa-pen-clip' => 'fa-pen-clip', 'fas fa-peace' => 'fa-peace', 'fas fa-paw' => 'fa-paw', 'far fa-paste' => 'fa-paste', 'fas fa-paste' => 'fa-paste', 'fas fa-paragraph' => 'fa-paragraph', 'fas fa-parachute-box' => 'fa-parachute-box', 'fas fa-panorama' => 'fa-panorama', 'fas fa-pallet' => 'fa-pallet', 'fas fa-paintbrush' => 'fa-paintbrush', 'fas fa-pager' => 'fa-pager', 'fas fa-p' => 'fa-p', 'fas fa-outdent' => 'fa-outdent', 'fas fa-om' => 'fa-om', 'fas fa-oil-well' => 'fa-oil-well', 'fas fa-oil-can' => 'fa-oil-can', 'far fa-object-ungroup' => 'fa-object-ungroup', 'fas fa-object-ungroup' => 'fa-object-ungroup', 'far fa-object-group' => 'fa-object-group', 'fas fa-object-group' => 'fa-object-group', 'fas fa-o' => 'fa-o', 'far fa-note-sticky' => 'fa-note-sticky', 'fas fa-note-sticky' => 'fa-note-sticky', 'fas fa-neuter' => 'fa-neuter', 'fas fa-naira-sign' => 'fa-naira-sign', 'fas fa-n' => 'fa-n', 'fas fa-mountain-city' => 'fa-mountain-city', 'fas fa-mountain' => 'fa-mountain', 'fas fa-mound' => 'fa-mound', 'fas fa-mosquito-net' => 'fa-mosquito-net', 'fas fa-mosquito' => 'fa-mosquito', 'fas fa-mosque' => 'fa-mosque', 'fas fa-mortar-pestle' => 'fa-mortar-pestle', 'fas fa-monument' => 'fa-monument', 'fas fa-money-check-dollar' => 'fa-money-check-dollar', 'fas fa-money-check' => 'fa-money-check', 'fas fa-money-bills' => 'fa-money-bills', 'fas fa-money-bill-wheat' => 'fa-money-bill-wheat', 'fas fa-money-bill-wave' => 'fa-money-bill-wave', 'fas fa-money-bill-trend-up' => 'fa-money-bill-trend-up', 'fas fa-money-bill-transfer' => 'fa-money-bill-transfer', 'fas fa-money-bill-1-wave' => 'fa-money-bill-1-wave', 'far fa-money-bill-1' => 'fa-money-bill-1', 'fas fa-money-bill-1' => 'fa-money-bill-1', 'fas fa-mobile-screen-button' => 'fa-mobile-screen-button', 'fas fa-mobile-screen' => 'fa-mobile-screen', 'fas fa-mobile-retro' => 'fa-mobile-retro', 'fas fa-mobile-button' => 'fa-mobile-button', 'fas fa-mitten' => 'fa-mitten', 'fas fa-minimize' => 'fa-minimize', 'fas fa-mill-sign' => 'fa-mill-sign', 'fas fa-microscope' => 'fa-microscope', 'fas fa-microphone-lines-slash' => 'fa-microphone-lines-slash', 'fas fa-microphone-lines' => 'fa-microphone-lines', 'fas fa-meteor' => 'fa-meteor', 'far fa-message' => 'fa-message', 'fas fa-message' => 'fa-message', 'fas fa-mercury' => 'fa-mercury', 'fas fa-menorah' => 'fa-menorah', 'fas fa-memory' => 'fa-memory', 'fas fa-maximize' => 'fa-maximize', 'fas fa-mattress-pillow' => 'fa-mattress-pillow', 'fas fa-masks-theater' => 'fa-masks-theater', 'fas fa-mask-ventilator' => 'fa-mask-ventilator', 'fas fa-mask-face' => 'fa-mask-face', 'fas fa-martini-glass-empty' => 'fa-martini-glass-empty', 'fas fa-martini-glass-citrus' => 'fa-martini-glass-citrus', 'fas fa-martini-glass' => 'fa-martini-glass', 'fas fa-mars-stroke-up' => 'fa-mars-stroke-up', 'fas fa-mars-stroke-right' => 'fa-mars-stroke-right', 'fas fa-mars-stroke' => 'fa-mars-stroke', 'fas fa-mars-double' => 'fa-mars-double', 'fas fa-mars-and-venus-burst' => 'fa-mars-and-venus-burst', 'fas fa-mars-and-venus' => 'fa-mars-and-venus', 'fas fa-mars' => 'fa-mars', 'fas fa-map-pin' => 'fa-map-pin', 'fas fa-map-location-dot' => 'fa-map-location-dot', 'fas fa-map-location' => 'fa-map-location', 'far fa-map' => 'fa-map', 'fas fa-map' => 'fa-map', 'fas fa-manat-sign' => 'fa-manat-sign', 'fas fa-magnifying-glass-plus' => 'fa-magnifying-glass-plus', 'fas fa-magnifying-glass-minus' => 'fa-magnifying-glass-minus', 'fas fa-magnifying-glass-location' => 'fa-magnifying-glass-location', 'fas fa-magnifying-glass-dollar' => 'fa-magnifying-glass-dollar', 'fas fa-magnifying-glass-chart' => 'fa-magnifying-glass-chart', 'fas fa-magnifying-glass-arrow-right' => 'fa-magnifying-glass-arrow-right', 'fas fa-m' => 'fa-m', 'fas fa-lungs-virus' => 'fa-lungs-virus', 'fas fa-lungs' => 'fa-lungs', 'fas fa-locust' => 'fa-locust', 'fas fa-lock-open' => 'fa-lock-open', 'fas fa-location-pin-lock' => 'fa-location-pin-lock', 'fas fa-location-crosshairs' => 'fa-location-crosshairs', 'fas fa-location-arrow' => 'fa-location-arrow', 'fas fa-litecoin-sign' => 'fa-litecoin-sign', 'fas fa-list-ul' => 'fa-list-ul', 'fas fa-list-ol' => 'fa-list-ol', 'fas fa-list-check' => 'fa-list-check', 'fas fa-lira-sign' => 'fa-lira-sign', 'fas fa-link-slash' => 'fa-link-slash', 'fas fa-lines-leaning' => 'fa-lines-leaning', 'far fa-life-ring' => 'fa-life-ring', 'fas fa-life-ring' => 'fa-life-ring', 'fas fa-less-than-equal' => 'fa-less-than-equal', 'fas fa-less-than' => 'fa-less-than', 'fas fa-left-right' => 'fa-left-right', 'fas fa-left-long' => 'fa-left-long', 'fas fa-lari-sign' => 'fa-lari-sign', 'fas fa-laptop-medical' => 'fa-laptop-medical', 'fas fa-laptop-file' => 'fa-laptop-file', 'fas fa-laptop-code' => 'fa-laptop-code', 'fas fa-landmark-flag' => 'fa-landmark-flag', 'fas fa-landmark-dome' => 'fa-landmark-dome', 'fas fa-land-mine-on' => 'fa-land-mine-on', 'fas fa-l' => 'fa-l', 'fas fa-kitchen-set' => 'fa-kitchen-set', 'fas fa-kit-medical' => 'fa-kit-medical', 'fas fa-kip-sign' => 'fa-kip-sign', 'fas fa-khanda' => 'fa-khanda', 'fas fa-kaaba' => 'fa-kaaba', 'fas fa-k' => 'fa-k', 'fas fa-jug-detergent' => 'fa-jug-detergent', 'fas fa-jet-fighter-up' => 'fa-jet-fighter-up', 'fas fa-jet-fighter' => 'fa-jet-fighter', 'fas fa-jedi' => 'fa-jedi', 'fas fa-jar-wheat' => 'fa-jar-wheat', 'fas fa-jar' => 'fa-jar', 'fas fa-j' => 'fa-j', 'fas fa-italic' => 'fa-italic', 'fas fa-infinity' => 'fa-infinity', 'fas fa-indian-rupee-sign' => 'fa-indian-rupee-sign', 'fas fa-indent' => 'fa-indent', 'fas fa-image-portrait' => 'fa-image-portrait', 'fas fa-igloo' => 'fa-igloo', 'fas fa-id-card-clip' => 'fa-id-card-clip', 'far fa-id-card' => 'fa-id-card', 'fas fa-id-card' => 'fa-id-card', 'far fa-id-badge' => 'fa-id-badge', 'fas fa-id-badge' => 'fa-id-badge', 'fas fa-icicles' => 'fa-icicles', 'fas fa-ice-cream' => 'fa-ice-cream', 'fas fa-i' => 'fa-i', 'fas fa-hurricane' => 'fa-hurricane', 'fas fa-hryvnia-sign' => 'fa-hryvnia-sign', 'fas fa-house-user' => 'fa-house-user', 'fas fa-house-tsunami' => 'fa-house-tsunami', 'fas fa-house-signal' => 'fa-house-signal', 'fas fa-house-medical-flag' => 'fa-house-medical-flag', 'fas fa-house-medical-circle-xmark' => 'fa-house-medical-circle-xmark', 'fas fa-house-medical-circle-exclamation' => 'fa-house-medical-circle-exclamation', 'fas fa-house-medical-circle-check' => 'fa-house-medical-circle-check', 'fas fa-house-medical' => 'fa-house-medical', 'fas fa-house-lock' => 'fa-house-lock', 'fas fa-house-laptop' => 'fa-house-laptop', 'fas fa-house-flood-water-circle-arrow-right' => 'fa-house-flood-water-circle-arrow-right', 'fas fa-house-flood-water' => 'fa-house-flood-water', 'fas fa-house-flag' => 'fa-house-flag', 'fas fa-house-fire' => 'fa-house-fire', 'fas fa-house-crack' => 'fa-house-crack', 'fas fa-house-circle-xmark' => 'fa-house-circle-xmark', 'fas fa-house-circle-exclamation' => 'fa-house-circle-exclamation', 'fas fa-house-circle-check' => 'fa-house-circle-check', 'fas fa-house-chimney-window' => 'fa-house-chimney-window', 'fas fa-house-chimney-user' => 'fa-house-chimney-user', 'fas fa-house-chimney-medical' => 'fa-house-chimney-medical', 'fas fa-house-chimney-crack' => 'fa-house-chimney-crack', 'fas fa-house-chimney' => 'fa-house-chimney', 'far fa-hourglass-half' => 'fa-hourglass-half', 'fas fa-hourglass-half' => 'fa-hourglass-half', 'fas fa-hourglass-end' => 'fa-hourglass-end', 'far fa-hourglass' => 'fa-hourglass', 'fas fa-hourglass' => 'fa-hourglass', 'fas fa-hotdog' => 'fa-hotdog', 'fas fa-hot-tub-person' => 'fa-hot-tub-person', 'fas fa-hospital-user' => 'fa-hospital-user', 'fas fa-horse-head' => 'fa-horse-head', 'fas fa-horse' => 'fa-horse', 'fas fa-holly-berry' => 'fa-holly-berry', 'fas fa-hockey-puck' => 'fa-hockey-puck', 'fas fa-hill-rockslide' => 'fa-hill-rockslide', 'fas fa-hill-avalanche' => 'fa-hill-avalanche', 'fas fa-highlighter' => 'fa-highlighter', 'fas fa-helmet-un' => 'fa-helmet-un', 'fas fa-helmet-safety' => 'fa-helmet-safety', 'fas fa-helicopter-symbol' => 'fa-helicopter-symbol', 'fas fa-helicopter' => 'fa-helicopter', 'fas fa-heart-pulse' => 'fa-heart-pulse', 'fas fa-heart-crack' => 'fa-heart-crack', 'fas fa-heart-circle-xmark' => 'fa-heart-circle-xmark', 'fas fa-heart-circle-plus' => 'fa-heart-circle-plus', 'fas fa-heart-circle-minus' => 'fa-heart-circle-minus', 'fas fa-heart-circle-exclamation' => 'fa-heart-circle-exclamation', 'fas fa-heart-circle-check' => 'fa-heart-circle-check', 'fas fa-heart-circle-bolt' => 'fa-heart-circle-bolt', 'fas fa-headphones-simple' => 'fa-headphones-simple', 'fas fa-head-side-virus' => 'fa-head-side-virus', 'fas fa-head-side-mask' => 'fa-head-side-mask', 'fas fa-head-side-cough-slash' => 'fa-head-side-cough-slash', 'fas fa-head-side-cough' => 'fa-head-side-cough', 'fas fa-hat-wizard' => 'fa-hat-wizard', 'fas fa-hat-cowboy-side' => 'fa-hat-cowboy-side', 'fas fa-hat-cowboy' => 'fa-hat-cowboy', 'far fa-hard-drive' => 'fa-hard-drive', 'fas fa-hard-drive' => 'fa-hard-drive', 'fas fa-hanukiah' => 'fa-hanukiah', 'fas fa-handshake-simple-slash' => 'fa-handshake-simple-slash', 'fas fa-handshake-simple' => 'fa-handshake-simple', 'fas fa-handshake-angle' => 'fa-handshake-angle', 'fas fa-hands-praying' => 'fa-hands-praying', 'fas fa-hands-holding-circle' => 'fa-hands-holding-circle', 'fas fa-hands-holding-child' => 'fa-hands-holding-child', 'fas fa-hands-holding' => 'fa-hands-holding', 'fas fa-hands-clapping' => 'fa-hands-clapping', 'fas fa-hands-bubbles' => 'fa-hands-bubbles', 'fas fa-hands-bound' => 'fa-hands-bound', 'fas fa-hands-asl-interpreting' => 'fa-hands-asl-interpreting', 'fas fa-handcuffs' => 'fa-handcuffs', 'far fa-hand-spock' => 'fa-hand-spock', 'fas fa-hand-spock' => 'fa-hand-spock', 'fas fa-hand-sparkles' => 'fa-hand-sparkles', 'far fa-hand-scissors' => 'fa-hand-scissors', 'fas fa-hand-scissors' => 'fa-hand-scissors', 'far fa-hand-pointer' => 'fa-hand-pointer', 'fas fa-hand-pointer' => 'fa-hand-pointer', 'far fa-hand-point-right' => 'fa-hand-point-right', 'fas fa-hand-point-right' => 'fa-hand-point-right', 'far fa-hand-point-left' => 'fa-hand-point-left', 'fas fa-hand-point-left' => 'fa-hand-point-left',);
  return $icon;
}


function listeoBrandIcons()
{
  $icons = array(
    "px" => "PX",
    "accessible-icon" => "Accessible Icon",
    "accusoft" => "Accusoft",
    "adn" => "ADN",
    "adversal" => "Adversal",
    "affiliatetheme" => "Affiliate Theme",
    "algolia" => "Algolia",
    "amazon" => "Amazon",
    "amazon-pay" => "Amazon Pay",
    "amilia" => "Amilia",
    "android" => "Android",
    "angellist" => "AngelList",
    "angrycreative" => "Angry Creative",
    "angular" => "Angular",
    "apper" => "Apper",
    "apple" => "Apple",
    "apple-pay" => "Apple Pay",
    "app-store" => "App Store",
    "app-store-ios" => "App Store iOS",
    "asymmetrik" => "Asymmetrik",
    "audible" => "Audible",
    "autoprefixer" => "Autoprefixer",
    "avianex" => "Avianex",
    "aviato" => "Aviato",
    "aws" => "AWS",
    "bandcamp" => "Bandcamp",
    "behance" => "Behance",
    "bimobject" => "Bimobject",
    "bitbucket" => "Bitbucket",
    "bitcoin" => "Bitcoin",
    "bity" => "Bity",
    "blackberry" => "Blackberry",
    "black-tie" => "Black Tie",
    "blogger" => "Blogger",
    "blogger-b" => "Blogger B",
    "bluetooth" => "Bluetooth",
    "bluetooth-b" => "Bluetooth B",
    "btc" => "BTC",
    "buromobelexperte" => "Buromobelexperte",
    "buysellads" => "BuySellAds",
    "cc-amazon-pay" => "CC Amazon Pay",
    "cc-amex" => "CC Amex",
    "cc-apple-pay" => "CC Apple Pay",
    "cc-diners-club" => "CC Diners Club",
    "cc-discover" => "CC Discover",
    "cc-jcb" => "CC JCB",
    "cc-mastercard" => "CC Mastercard",
    "cc-paypal" => "CC PayPal",
    "cc-stripe" => "CC Stripe",
    "cc-visa" => "CC Visa",
    "centercode" => "Centercode",
    "chrome" => "Chrome",
    "cloudscale" => "Cloudscale",
    "cloudsmith" => "Cloudsmith",
    "cloudversify" => "Cloudversify",
    "codepen" => "CodePen",
    "codiepie" => "Codiepie",
    "connectdevelop" => "ConnectDevelop",
    "contao" => "Contao",
    "cpanel" => "cPanel",
    "creative-commons" => "Creative Commons",
    "creative-commons-by" => "Creative Commons By",
    "creative-commons-nc" => "Creative Commons NC",
    "creative-commons-nc-eu" => "Creative Commons NC EU",
    "creative-commons-nc-jp" => "Creative Commons NC JP",
    "creative-commons-nd" => "Creative Commons ND",
    "creative-commons-pd" => "Creative Commons PD",

    "creative-commons-remix" => "Creative Commons Remix",
    "creative-commons-sa" => "Creative Commons SA",
    "creative-commons-sampling" => "Creative Commons Sampling",
    "creative-commons-sampling-plus" => "Creative Commons Sampling Plus",
    "creative-commons-share" => "Creative Commons Share",
    "css3" => "CSS3",

    "cuttlefish" => "Cuttlefish",
    "d-and-d" => "D and D",
    "dashcube" => "Dashcube",
    "delicious" => "Delicious",
    "deploydog" => "Deploydog",
    "deskpro" => "Deskpro",
    "deviantart" => "DeviantArt",
    "digg" => "Digg",
    "digital-ocean" => "Digital Ocean",
    "discord" => "Discord",
    "discourse" => "Discourse",
    "dochub" => "Dochub",
    "docker" => "Docker",
    "draft2digital" => "Draft2Digital",
    "dribbble" => "Dribbble",

    "dropbox" => "Dropbox",
    "drupal" => "Drupal",
    "dyalog" => "Dyalog",
    "earlybirds" => "Earlybirds",
    "ebay" => "eBay",
    "edge" => "Edge",
    "elementor" => "Elementor",
    "ember" => "Ember",
    "empire" => "Empire",
    "envira" => "Envira",
    "erlang" => "Erlang",
    "ethereum" => "Ethereum",
    "etsy" => "Etsy",
    "expeditedssl" => "ExpeditedSSL",
    "facebook" => "Facebook",
    "facebook-f" => "Facebook F",
    "facebook-messenger" => "Facebook Messenger",

    "firefox" => "Firefox",
    "firstdraft" => "Firstdraft",
    "first-order" => "First Order",

    "flickr" => "Flickr",
    "flipboard" => "Flipboard",
    "fly" => "Fly",
    "font-awesome" => "Font Awesome",

    "font-awesome-flag" => "Font Awesome Flag",
    "fonticons" => "Fonticons",
    "fonticons-fi" => "Fonticons Fi",
    "fort-awesome" => "Fort Awesome",

    "forumbee" => "Forumbee",
    "foursquare" => "Foursquare",
    "freebsd" => "FreeBSD",
    "free-code-camp" => "Free Code Camp",
    "fulcrum" => "Fulcrum",
    "galactic-republic" => "Galactic Republic",
    "galactic-senate" => "Galactic Senate",
    "get-pocket" => "Get Pocket",
    "gg" => "GG",
    "gg-circle" => "GG Circle",
    "git" => "Git",
    "github" => "GitHub",

    "gitkraken" => "GitKraken",
    "gitlab" => "GitLab",

    "gitter" => "Gitter",
    "glide" => "Glide",
    "glide-g" => "Glide G",
    "gofore" => "Gofore",
    "goodreads" => "Goodreads",
    "goodreads-g" => "Goodreads G",
    "google" => "Google",
    "google-drive" => "Google Drive",
    "google-play" => "Google Play",
    "google-plus" => "Google Plus",

    "google-wallet" => "Google Wallet",
    "gratipay" => "Gratipay",
    "grav" => "Grav",
    "gripfire" => "Gripfire",
    "grunt" => "Grunt",
    "gulp" => "Gulp",
    "hacker-news" => "Hacker News",

    "hips" => "Hips",
    "hire-a-helper" => "Hire A Helper",
    "hooli" => "Hooli",
    "hotjar" => "Hotjar",
    "houzz" => "Houzz",
    "html5" => "HTML5",
    "hubspot" => "HubSpot",
    "imdb" => "IMDb",
    "instagram" => "Instagram",
    "internet-explorer" => "Internet Explorer",
    "ioxhost" => "IOXhost",
    "itunes" => "iTunes",
    "itunes-note" => "iTunes Note",
    "java" => "Java",
    "jedi-order" => "Jedi Order",
    "jenkins" => "Jenkins",
    "joget" => "Joget",
    "joomla" => "Joomla",
    "js" => "JS",
    "jsfiddle" => "JSFiddle",

    "keybase" => "Keybase",
    "keycdn" => "KeyCDN",
    "kickstarter" => "Kickstarter",
    "kickstarter-k" => "Kickstarter K",
    "korvue" => "Korvue",
    "laravel" => "Laravel",
    "lastfm" => "LastFM",

    "leanpub" => "Leanpub",
    "less" => "Less",
    "line" => "Line",
    "linkedin" => "LinkedIn",
    "linkedin-in" => "LinkedIn In",
    "linode" => "Linode",
    "linux" => "Linux",
    "lyft" => "Lyft",
    "magento" => "Magento",
    "mandalorian" => "Mandalorian",
    "mastodon" => "Mastodon",
    "maxcdn" => "MaxCDN",
    "medapps" => "MedApps",
    "medium" => "Medium",
    "medium-m" => "Medium M",
    "medrt" => "Medrt",
    "meetup" => "Meetup",
    "microsoft" => "Microsoft",
    "mix" => "Mix",
    "mixcloud" => "Mixcloud",
    "mizuni" => "Mizuni",
    "modx" => "MODX",
    "monero" => "Monero",
    "napster" => "Napster",
    "nintendo-switch" => "Nintendo Switch",
    "node" => "Node.js",
    "node-js" => "Node.js",
    "npm" => "npm",
    "ns8" => "NS8",
    "nutritionix" => "Nutritionix",
    "odnoklassniki" => "Odnoklassniki",

    "old-republic" => "Old Republic",
    "opencart" => "OpenCart",
    "openid" => "OpenID",
    "opera" => "Opera",
    "optin-monster" => "Optin Monster",
    "osi" => "OSI",
    "page4" => "Page4",
    "pagelines" => "Pagelines",
    "palfed" => "Palfed",
    "patreon" => "Patreon",
    "paypal" => "PayPal",
    "periscope" => "Periscope",
    "phabricator" => "Phabricator",
    "phoenix-framework" => "Phoenix Framework",

    "php" => "PHP",
    "pied-piper" => "Pied Piper",

    "pied-piper-hat" => "Pied Piper Hat",
    "pied-piper-pp" => "Pied Piper PP",
    "pinterest" => "Pinterest",
    "pinterest-p" => "Pinterest P",

    "playstation" => "PlayStation",
    "product-hunt" => "Product Hunt",
    "pushed" => "Pushed",
    "python" => "Python",
    "qq" => "QQ",
    "quinscape" => "Quinscape",
    "quora" => "Quora",
    "ravelry" => "Ravelry",
    "react" => "React",
    "readme" => "Readme",
    "rebel" => "Rebel",
    "reddit" => "Reddit",
    "reddit-alien" => "Reddit Alien",

    "red-river" => "Red River",
    "rendact" => "Rendact",
    "renren" => "Renren",
    "replyd" => "Replyd",
    "researchgate" => "ResearchGate",
    "resolving" => "Resolving",
    "rocketchat" => "Rocket.Chat",
    "rockrms" => "Rockrms",
    "r-project" => "R Project",
    "safari" => "Safari",
    "sass" => "Sass",
    "schlix" => "Schlix",
    "scribd" => "Scribd",
    "searchengin" => "Searchengin",
    "sellcast" => "Sellcast",
    "sellsy" => "Sellsy",
    "servicestack" => "ServiceStack",
    "shirtsinbulk" => "Shirtsinbulk",
    "simplybuilt" => "Simplybuilt",
    "sistrix" => "Sistrix",
    "sith" => "Sith",
    "skyatlas" => "Skyatlas",
    "skype" => "Skype",
    "slack" => "Slack",
    "slack-hash" => "Slack Hash",
    "slideshare" => "Slideshare",
    "snapchat" => "Snapchat",
    "snapchat-ghost" => "Snapchat Ghost",

    "soundcloud" => "SoundCloud",
    "speakap" => "Speakap",
    "spotify" => "Spotify",
    "stack-exchange" => "Stack Exchange",
    "stack-overflow" => "Stack Overflow",
    "staylinked" => "StayLinked",
    "steam" => "Steam",

    "steam-symbol" => "Steam Symbol",
    "sticker-mule" => "Sticker Mule",
    "strava" => "Strava",
    "stripe" => "Stripe",
    "stripe-s" => "Stripe S",
    "studiovinari" => "Studiovinari",
    "stumbleupon" => "StumbleUpon",
    "stumbleupon-circle" => "StumbleUpon Circle",
    "superpowers" => "Superpowers",
    "supple" => "Supple",
    "teamspeak" => "Teamspeak",
    "telegram" => "Telegram",
    "telegram-plane" => "Telegram Plane",
    "tencent-weibo" => "Tencent Weibo",
    "themeisle" => "Themeisle",
    "trade-federation" => "Trade Federation",
    "tripadvisor" => "Tripadvisor",
    "tumblr" => "Tumblr",

    "twitch" => "Twitch",
    "twitter" => "Twitter",
    "x-twitter" => "X Twitter",
    "tiktok" => "TikTok",

    "typo3" => "TYPO3",
    "uber" => "Uber",
    "uikit" => "UIKit",
    "uniregistry" => "Uniregistry",
    "untappd" => "Untappd",
    "usb" => "USB",
    "ussunnah" => "USSunnah",
    "vaadin" => "Vaadin",
    "viacoin" => "Viacoin",
    "viadeo" => "Viadeo",

    "viber" => "Viber",
    "vimeo" => "Vimeo",

    "vimeo-v" => "Vimeo V",
    "vine" => "Vine",
    "vk" => "VK",
    "vnv" => "VNV",
    "vuejs" => "Vue.js",
    "weibo" => "Weibo",
    "weixin" => "Weixin",
    "whatsapp" => "WhatsApp",

    "whmcs" => "WHMCS",
    "wikipedia-w" => "Wikipedia W",
    "windows" => "Windows",
    "wolf-pack-battalion" => "Wolf Pack Battalion",
    "wordpress" => "WordPress",
    "wordpress-simple" => "WordPress Simple",
    "wpbeginner" => "WPBeginner",
    "wpexplorer" => "WPExplorer",
    "wpforms" => "WPForms",
    "xbox" => "Xbox",
    "xing" => "Xing",

    "yahoo" => "Yahoo",
    "yandex" => "Yandex",
    "yandex-international" => "Yandex International",
    "y-combinator" => "Y Combinator",
    "yelp" => "Yelp",
    "yoast" => "Yoast",
    "youtube" => "YouTube",

  );
  return $icons;
}

function purethemes_get_simple_line_icons()
{
  return array(
    'user',
    'people',
    'user-female',
    'user-follow',
    'user-following',
    'user-unfollow',
    'login',
    'logout',
    'emotsmile',
    'phone',
    'call-end',
    'call-in',
    'call-out',
    'map',
    'location',
    'direction',
    'directions',
    'compass',
    'layers',
    'menu',
    'list',
    'options-vertical',
    'options',
    'arrow-down',
    'arrow-left',
    'arrow-right',
    'arrow-up',
    'arrow-up-circle',
    'arrow-left-circle',
    'arrow-right-circle',
    'arrow-down-circle',
    'check',
    'clock',
    'plus',
    'minus',
    'close',
    'exclamation',
    'organization',
    'trophy',
    'screen-smartphone',
    'screen-desktop',
    'plane',
    'notebook',
    'mustache',
    'mouse',
    'magnet',
    'energy',
    'disc',
    'cursor',
    'cursor-move',
    'crop',
    'chemistry',
    'speedometer',
    'shield',
    'screen-tablet',
    'magic-wand',
    'hourglass',
    'graduation',
    'ghost',
    'game-controller',
    'fire',
    'eyeglass',
    'envelope-open',
    'envelope-letter',
    'bell',
    'badge',
    'anchor',
    'wallet',
    'vector',
    'speech',
    'puzzle',
    'printer',
    'present',
    'playlist',
    'pin',
    'picture',
    'handbag',
    'globe-alt',
    'globe',
    'folder-alt',
    'folder',
    'film',
    'feed',
    'drop',
    'drawer',
    'docs',
    'doc',
    'diamond',
    'cup',
    'calculator',
    'bubbles',
    'briefcase',
    'book-open',
    'basket-loaded',
    'basket',
    'bag',
    'action-undo',
    'action-redo',
    'wrench',
    'umbrella',
    'trash',
    'tag',
    'support',
    'frame',
    'size-fullscreen',
    'size-actual',
    'shuffle',
    'share-alt',
    'share',
    'rocket',
    'question',
    'pie-chart',
    'pencil',
    'note',
    'loop',
    'home',
    'grid',
    'graph',
    'microphone',
    'music-tone-alt',
    'music-tone',
    'earphones-alt',
    'earphones',
    'equalizer',
    'like',
    'dislike',
    'control-start',
    'control-rewind',
    'control-play',
    'control-pause',
    'control-forward',
    'control-end',
    'volume-1',
    'volume-2',
    'volume-off',
    'calendar',
    'calender',
    'bulb',
    'chart',
    'ban',
    'bubble',
    'camrecorder',
    'camera',
    'cloud-download',
    'cloud-upload',
    'envelope',
    'eye',
    'flag',
    'heart',
    'info',
    'key',
    'link',
    'lock',
    'lock-open',
    'magnifier',
    'magnifier-add',
    'magnifier-remove',
    'paper-clip',
    'paper-plane',
    'power',
    'refresh',
    'reload',
    'settings',
    'star',
    'symbol-female',
    'symbol-male',
    'target',
    'credit-card',
    'paypal',
    'social-tumblr',
    'social-twitter',
    'social-facebook',
    'social-instagram',
    'social-linkedin',
    'social-pinterest',
    'social-github',
    'social-google',
    'social-reddit',
    'social-skype',
    'social-dribbble',
    'social-behance',
    'social-foursqare',
    'social-soundcloud',
    'social-spotify',
    'social-stumbleupon',
    'social-youtube',
    'social-dropbox',
  );
}


function vc_iconpicker_type_iconsmind($icons)
{
  $iconsmind_icons = array(
    array('' => 'empty'),
    array('im im-icon-A-Z' => 'A-Z'),
    array('im im-icon-Aa' => 'Aa'),
    array('im im-icon-Add-Bag' => 'Add-Bag'),
    array('im im-icon-Add-Basket' => 'Add-Basket'),
    array('im im-icon-Add-Cart' => 'Add-Cart'),
    array('im im-icon-Add-File' => 'Add-File'),
    array('im im-icon-Add-SpaceAfterParagraph' => 'Add-SpaceAfterParagraph'),
    array('im im-icon-Add-SpaceBeforeParagraph' => 'Add-SpaceBeforeParagraph'),
    array('im im-icon-Add-User' => 'Add-User'),
    array('im im-icon-Add-UserStar' => 'Add-UserStar'),
    array('im im-icon-Add-Window' => 'Add-Window'),
    array('im im-icon-Add' => 'Add'),
    array('im im-icon-Address-Book' => 'Address-Book'),
    array('im im-icon-Address-Book2' => 'Address-Book2'),
    array('im im-icon-Administrator' => 'Administrator'),
    array('im im-icon-Aerobics-2' => 'Aerobics-2'),
    array('im im-icon-Aerobics-3' => 'Aerobics-3'),
    array('im im-icon-Aerobics' => 'Aerobics'),
    array('im im-icon-Affiliate' => 'Affiliate'),
    array('im im-icon-Aim' => 'Aim'),
    array('im im-icon-Air-Balloon' => 'Air-Balloon'),
    array('im im-icon-Airbrush' => 'Airbrush'),
    array('im im-icon-Airship' => 'Airship'),
    array('im im-icon-Alarm-Clock' => 'Alarm-Clock'),
    array('im im-icon-Alarm-Clock2' => 'Alarm-Clock2'),
    array('im im-icon-Alarm' => 'Alarm'),
    array('im im-icon-Alien-2' => 'Alien-2'),
    array('im im-icon-Alien' => 'Alien'),
    array('im im-icon-Aligator' => 'Aligator'),
    array('im im-icon-Align-Center' => 'Align-Center'),
    array('im im-icon-Align-JustifyAll' => 'Align-JustifyAll'),
    array('im im-icon-Align-JustifyCenter' => 'Align-JustifyCenter'),
    array('im im-icon-Align-JustifyLeft' => 'Align-JustifyLeft'),
    array('im im-icon-Align-JustifyRight' => 'Align-JustifyRight'),
    array('im im-icon-Align-Left' => 'Align-Left'),
    array('im im-icon-Align-Right' => 'Align-Right'),
    array('im im-icon-Alpha' => 'Alpha'),
    array('im im-icon-Ambulance' => 'Ambulance'),
    array('im im-icon-AMX' => 'AMX'),
    array('im im-icon-Anchor-2' => 'Anchor-2'),
    array('im im-icon-Anchor' => 'Anchor'),
    array('im im-icon-Android-Store' => 'Android-Store'),
    array('im im-icon-Android' => 'Android'),
    array('im im-icon-Angel-Smiley' => 'Angel-Smiley'),
    array('im im-icon-Angel' => 'Angel'),
    array('im im-icon-Angry' => 'Angry'),
    array('im im-icon-Apple-Bite' => 'Apple-Bite'),
    array('im im-icon-Apple-Store' => 'Apple-Store'),
    array('im im-icon-Apple' => 'Apple'),
    array('im im-icon-Approved-Window' => 'Approved-Window'),
    array('im im-icon-Aquarius-2' => 'Aquarius-2'),
    array('im im-icon-Aquarius' => 'Aquarius'),
    array('im im-icon-Archery-2' => 'Archery-2'),
    array('im im-icon-Archery' => 'Archery'),
    array('im im-icon-Argentina' => 'Argentina'),
    array('im im-icon-Aries-2' => 'Aries-2'),
    array('im im-icon-Aries' => 'Aries'),
    array('im im-icon-Army-Key' => 'Army-Key'),
    array('im im-icon-Arrow-Around' => 'Arrow-Around'),
    array('im im-icon-Arrow-Back3' => 'Arrow-Back3'),
    array('im im-icon-Arrow-Back' => 'Arrow-Back'),
    array('im im-icon-Arrow-Back2' => 'Arrow-Back2'),
    array('im im-icon-Arrow-Barrier' => 'Arrow-Barrier'),
    array('im im-icon-Arrow-Circle' => 'Arrow-Circle'),
    array('im im-icon-Arrow-Cross' => 'Arrow-Cross'),
    array('im im-icon-Arrow-Down' => 'Arrow-Down'),
    array('im im-icon-Arrow-Down2' => 'Arrow-Down2'),
    array('im im-icon-Arrow-Down3' => 'Arrow-Down3'),
    array('im im-icon-Arrow-DowninCircle' => 'Arrow-DowninCircle'),
    array('im im-icon-Arrow-Fork' => 'Arrow-Fork'),
    array('im im-icon-Arrow-Forward' => 'Arrow-Forward'),
    array('im im-icon-Arrow-Forward2' => 'Arrow-Forward2'),
    array('im im-icon-Arrow-From' => 'Arrow-From'),
    array('im im-icon-Arrow-Inside' => 'Arrow-Inside'),
    array('im im-icon-Arrow-Inside45' => 'Arrow-Inside45'),
    array('im im-icon-Arrow-InsideGap' => 'Arrow-InsideGap'),
    array('im im-icon-Arrow-InsideGap45' => 'Arrow-InsideGap45'),
    array('im im-icon-Arrow-Into' => 'Arrow-Into'),
    array('im im-icon-Arrow-Join' => 'Arrow-Join'),
    array('im im-icon-Arrow-Junction' => 'Arrow-Junction'),
    array('im im-icon-Arrow-Left' => 'Arrow-Left'),
    array('im im-icon-Arrow-Left2' => 'Arrow-Left2'),
    array('im im-icon-Arrow-LeftinCircle' => 'Arrow-LeftinCircle'),
    array('im im-icon-Arrow-Loop' => 'Arrow-Loop'),
    array('im im-icon-Arrow-Merge' => 'Arrow-Merge'),
    array('im im-icon-Arrow-Mix' => 'Arrow-Mix'),
    array('im im-icon-Arrow-Next' => 'Arrow-Next'),
    array('im im-icon-Arrow-OutLeft' => 'Arrow-OutLeft'),
    array('im im-icon-Arrow-OutRight' => 'Arrow-OutRight'),
    array('im im-icon-Arrow-Outside' => 'Arrow-Outside'),
    array('im im-icon-Arrow-Outside45' => 'Arrow-Outside45'),
    array('im im-icon-Arrow-OutsideGap' => 'Arrow-OutsideGap'),
    array('im im-icon-Arrow-OutsideGap45' => 'Arrow-OutsideGap45'),
    array('im im-icon-Arrow-Over' => 'Arrow-Over'),
    array('im im-icon-Arrow-Refresh' => 'Arrow-Refresh'),
    array('im im-icon-Arrow-Refresh2' => 'Arrow-Refresh2'),
    array('im im-icon-Arrow-Right' => 'Arrow-Right'),
    array('im im-icon-Arrow-Right2' => 'Arrow-Right2'),
    array('im im-icon-Arrow-RightinCircle' => 'Arrow-RightinCircle'),
    array('im im-icon-Arrow-Shuffle' => 'Arrow-Shuffle'),
    array('im im-icon-Arrow-Squiggly' => 'Arrow-Squiggly'),
    array('im im-icon-Arrow-Through' => 'Arrow-Through'),
    array('im im-icon-Arrow-To' => 'Arrow-To'),
    array('im im-icon-Arrow-TurnLeft' => 'Arrow-TurnLeft'),
    array('im im-icon-Arrow-TurnRight' => 'Arrow-TurnRight'),
    array('im im-icon-Arrow-Up' => 'Arrow-Up'),
    array('im im-icon-Arrow-Up2' => 'Arrow-Up2'),
    array('im im-icon-Arrow-Up3' => 'Arrow-Up3'),
    array('im im-icon-Arrow-UpinCircle' => 'Arrow-UpinCircle'),
    array('im im-icon-Arrow-XLeft' => 'Arrow-XLeft'),
    array('im im-icon-Arrow-XRight' => 'Arrow-XRight'),
    array('im im-icon-Ask' => 'Ask'),
    array('im im-icon-Assistant' => 'Assistant'),
    array('im im-icon-Astronaut' => 'Astronaut'),
    array('im im-icon-At-Sign' => 'At-Sign'),
    array('im im-icon-ATM' => 'ATM'),
    array('im im-icon-Atom' => 'Atom'),
    array('im im-icon-Audio' => 'Audio'),
    array('im im-icon-Auto-Flash' => 'Auto-Flash'),
    array('im im-icon-Autumn' => 'Autumn'),
    array('im im-icon-Baby-Clothes' => 'Baby-Clothes'),
    array('im im-icon-Baby-Clothes2' => 'Baby-Clothes2'),
    array('im im-icon-Baby-Cry' => 'Baby-Cry'),
    array('im im-icon-Baby' => 'Baby'),
    array('im im-icon-Back2' => 'Back2'),
    array('im im-icon-Back-Media' => 'Back-Media'),
    array('im im-icon-Back-Music' => 'Back-Music'),
    array('im im-icon-Back' => 'Back'),
    array('im im-icon-Background' => 'Background'),
    array('im im-icon-Bacteria' => 'Bacteria'),
    array('im im-icon-Bag-Coins' => 'Bag-Coins'),
    array('im im-icon-Bag-Items' => 'Bag-Items'),
    array('im im-icon-Bag-Quantity' => 'Bag-Quantity'),
    array('im im-icon-Bag' => 'Bag'),
    array('im im-icon-Bakelite' => 'Bakelite'),
    array('im im-icon-Ballet-Shoes' => 'Ballet-Shoes'),
    array('im im-icon-Balloon' => 'Balloon'),
    array('im im-icon-Banana' => 'Banana'),
    array('im im-icon-Band-Aid' => 'Band-Aid'),
    array('im im-icon-Bank' => 'Bank'),
    array('im im-icon-Bar-Chart' => 'Bar-Chart'),
    array('im im-icon-Bar-Chart2' => 'Bar-Chart2'),
    array('im im-icon-Bar-Chart3' => 'Bar-Chart3'),
    array('im im-icon-Bar-Chart4' => 'Bar-Chart4'),
    array('im im-icon-Bar-Chart5' => 'Bar-Chart5'),
    array('im im-icon-Bar-Code' => 'Bar-Code'),
    array('im im-icon-Barricade-2' => 'Barricade-2'),
    array('im im-icon-Barricade' => 'Barricade'),
    array('im im-icon-Baseball' => 'Baseball'),
    array('im im-icon-Basket-Ball' => 'Basket-Ball'),
    array('im im-icon-Basket-Coins' => 'Basket-Coins'),
    array('im im-icon-Basket-Items' => 'Basket-Items'),
    array('im im-icon-Basket-Quantity' => 'Basket-Quantity'),
    array('im im-icon-Bat-2' => 'Bat-2'),
    array('im im-icon-Bat' => 'Bat'),
    array('im im-icon-Bathrobe' => 'Bathrobe'),
    array('im im-icon-Batman-Mask' => 'Batman-Mask'),
    array('im im-icon-Battery-0' => 'Battery-0'),
    array('im im-icon-Battery-25' => 'Battery-25'),
    array('im im-icon-Battery-50' => 'Battery-50'),
    array('im im-icon-Battery-75' => 'Battery-75'),
    array('im im-icon-Battery-100' => 'Battery-100'),
    array('im im-icon-Battery-Charge' => 'Battery-Charge'),
    array('im im-icon-Bear' => 'Bear'),
    array('im im-icon-Beard-2' => 'Beard-2'),
    array('im im-icon-Beard-3' => 'Beard-3'),
    array('im im-icon-Beard' => 'Beard'),
    array('im im-icon-Bebo' => 'Bebo'),
    array('im im-icon-Bee' => 'Bee'),
    array('im im-icon-Beer-Glass' => 'Beer-Glass'),
    array('im im-icon-Beer' => 'Beer'),
    array('im im-icon-Bell-2' => 'Bell-2'),
    array('im im-icon-Bell' => 'Bell'),
    array('im im-icon-Belt-2' => 'Belt-2'),
    array('im im-icon-Belt-3' => 'Belt-3'),
    array('im im-icon-Belt' => 'Belt'),
    array('im im-icon-Berlin-Tower' => 'Berlin-Tower'),
    array('im im-icon-Beta' => 'Beta'),
    array('im im-icon-Betvibes' => 'Betvibes'),
    array('im im-icon-Bicycle-2' => 'Bicycle-2'),
    array('im im-icon-Bicycle-3' => 'Bicycle-3'),
    array('im im-icon-Bicycle' => 'Bicycle'),
    array('im im-icon-Big-Bang' => 'Big-Bang'),
    array('im im-icon-Big-Data' => 'Big-Data'),
    array('im im-icon-Bike-Helmet' => 'Bike-Helmet'),
    array('im im-icon-Bikini' => 'Bikini'),
    array('im im-icon-Bilk-Bottle2' => 'Bilk-Bottle2'),
    array('im im-icon-Billing' => 'Billing'),
    array('im im-icon-Bing' => 'Bing'),
    array('im im-icon-Binocular' => 'Binocular'),
    array('im im-icon-Bio-Hazard' => 'Bio-Hazard'),
    array('im im-icon-Biotech' => 'Biotech'),
    array('im im-icon-Bird-DeliveringLetter' => 'Bird-DeliveringLetter'),
    array('im im-icon-Bird' => 'Bird'),
    array('im im-icon-Birthday-Cake' => 'Birthday-Cake'),
    array('im im-icon-Bisexual' => 'Bisexual'),
    array('im im-icon-Bishop' => 'Bishop'),
    array('im im-icon-Bitcoin' => 'Bitcoin'),
    array('im im-icon-Black-Cat' => 'Black-Cat'),
    array('im im-icon-Blackboard' => 'Blackboard'),
    array('im im-icon-Blinklist' => 'Blinklist'),
    array('im im-icon-Block-Cloud' => 'Block-Cloud'),
    array('im im-icon-Block-Window' => 'Block-Window'),
    array('im im-icon-Blogger' => 'Blogger'),
    array('im im-icon-Blood' => 'Blood'),
    array('im im-icon-Blouse' => 'Blouse'),
    array('im im-icon-Blueprint' => 'Blueprint'),
    array('im im-icon-Board' => 'Board'),
    array('im im-icon-Bodybuilding' => 'Bodybuilding'),
    array('im im-icon-Bold-Text' => 'Bold-Text'),
    array('im im-icon-Bone' => 'Bone'),
    array('im im-icon-Bones' => 'Bones'),
    array('im im-icon-Book' => 'Book'),
    array('im im-icon-Bookmark' => 'Bookmark'),
    array('im im-icon-Books-2' => 'Books-2'),
    array('im im-icon-Books' => 'Books'),
    array('im im-icon-Boom' => 'Boom'),
    array('im im-icon-Boot-2' => 'Boot-2'),
    array('im im-icon-Boot' => 'Boot'),
    array('im im-icon-Bottom-ToTop' => 'Bottom-ToTop'),
    array('im im-icon-Bow-2' => 'Bow-2'),
    array('im im-icon-Bow-3' => 'Bow-3'),
    array('im im-icon-Bow-4' => 'Bow-4'),
    array('im im-icon-Bow-5' => 'Bow-5'),
    array('im im-icon-Bow-6' => 'Bow-6'),
    array('im im-icon-Bow' => 'Bow'),
    array('im im-icon-Bowling-2' => 'Bowling-2'),
    array('im im-icon-Bowling' => 'Bowling'),
    array('im im-icon-Box2' => 'Box2'),
    array('im im-icon-Box-Close' => 'Box-Close'),
    array('im im-icon-Box-Full' => 'Box-Full'),
    array('im im-icon-Box-Open' => 'Box-Open'),
    array('im im-icon-Box-withFolders' => 'Box-withFolders'),
    array('im im-icon-Box' => 'Box'),
    array('im im-icon-Boy' => 'Boy'),
    array('im im-icon-Bra' => 'Bra'),
    array('im im-icon-Brain-2' => 'Brain-2'),
    array('im im-icon-Brain-3' => 'Brain-3'),
    array('im im-icon-Brain' => 'Brain'),
    array('im im-icon-Brazil' => 'Brazil'),
    array('im im-icon-Bread-2' => 'Bread-2'),
    array('im im-icon-Bread' => 'Bread'),
    array('im im-icon-Bridge' => 'Bridge'),
    array('im im-icon-Brightkite' => 'Brightkite'),
    array('im im-icon-Broke-Link2' => 'Broke-Link2'),
    array('im im-icon-Broken-Link' => 'Broken-Link'),
    array('im im-icon-Broom' => 'Broom'),
    array('im im-icon-Brush' => 'Brush'),
    array('im im-icon-Bucket' => 'Bucket'),
    array('im im-icon-Bug' => 'Bug'),
    array('im im-icon-Building' => 'Building'),
    array('im im-icon-Bulleted-List' => 'Bulleted-List'),
    array('im im-icon-Bus-2' => 'Bus-2'),
    array('im im-icon-Bus' => 'Bus'),
    array('im im-icon-Business-Man' => 'Business-Man'),
    array('im im-icon-Business-ManWoman' => 'Business-ManWoman'),
    array('im im-icon-Business-Mens' => 'Business-Mens'),
    array('im im-icon-Business-Woman' => 'Business-Woman'),
    array('im im-icon-Butterfly' => 'Butterfly'),
    array('im im-icon-Button' => 'Button'),
    array('im im-icon-Cable-Car' => 'Cable-Car'),
    array('im im-icon-Cake' => 'Cake'),
    array('im im-icon-Calculator-2' => 'Calculator-2'),
    array('im im-icon-Calculator-3' => 'Calculator-3'),
    array('im im-icon-Calculator' => 'Calculator'),
    array('im im-icon-Calendar-2' => 'Calendar-2'),
    array('im im-icon-Calendar-3' => 'Calendar-3'),
    array('im im-icon-Calendar-4' => 'Calendar-4'),
    array('im im-icon-Calendar-Clock' => 'Calendar-Clock'),
    array('im im-icon-Calendar' => 'Calendar'),
    array('im im-icon-Camel' => 'Camel'),
    array('im im-icon-Camera-2' => 'Camera-2'),
    array('im im-icon-Camera-3' => 'Camera-3'),
    array('im im-icon-Camera-4' => 'Camera-4'),
    array('im im-icon-Camera-5' => 'Camera-5'),
    array('im im-icon-Camera-Back' => 'Camera-Back'),
    array('im im-icon-Camera' => 'Camera'),
    array('im im-icon-Can-2' => 'Can-2'),
    array('im im-icon-Can' => 'Can'),
    array('im im-icon-Canada' => 'Canada'),
    array('im im-icon-Cancer-2' => 'Cancer-2'),
    array('im im-icon-Cancer-3' => 'Cancer-3'),
    array('im im-icon-Cancer' => 'Cancer'),
    array('im im-icon-Candle' => 'Candle'),
    array('im im-icon-Candy-Cane' => 'Candy-Cane'),
    array('im im-icon-Candy' => 'Candy'),
    array('im im-icon-Cannon' => 'Cannon'),
    array('im im-icon-Cap-2' => 'Cap-2'),
    array('im im-icon-Cap-3' => 'Cap-3'),
    array('im im-icon-Cap-Smiley' => 'Cap-Smiley'),
    array('im im-icon-Cap' => 'Cap'),
    array('im im-icon-Capricorn-2' => 'Capricorn-2'),
    array('im im-icon-Capricorn' => 'Capricorn'),
    array('im im-icon-Car-2' => 'Car-2'),
    array('im im-icon-Car-3' => 'Car-3'),
    array('im im-icon-Car-Coins' => 'Car-Coins'),
    array('im im-icon-Car-Items' => 'Car-Items'),
    array('im im-icon-Car-Wheel' => 'Car-Wheel'),
    array('im im-icon-Car' => 'Car'),
    array('im im-icon-Cardigan' => 'Cardigan'),
    array('im im-icon-Cardiovascular' => 'Cardiovascular'),
    array('im im-icon-Cart-Quantity' => 'Cart-Quantity'),
    array('im im-icon-Casette-Tape' => 'Casette-Tape'),
    array('im im-icon-Cash-Register' => 'Cash-Register'),
    array('im im-icon-Cash-register2' => 'Cash-register2'),
    array('im im-icon-Castle' => 'Castle'),
    array('im im-icon-Cat' => 'Cat'),
    array('im im-icon-Cathedral' => 'Cathedral'),
    array('im im-icon-Cauldron' => 'Cauldron'),
    array('im im-icon-CD-2' => 'CD-2'),
    array('im im-icon-CD-Cover' => 'CD-Cover'),
    array('im im-icon-CD' => 'CD'),
    array('im im-icon-Cello' => 'Cello'),
    array('im im-icon-Celsius' => 'Celsius'),
    array('im im-icon-Chacked-Flag' => 'Chacked-Flag'),
    array('im im-icon-Chair' => 'Chair'),
    array('im im-icon-Charger' => 'Charger'),
    array('im im-icon-Check-2' => 'Check-2'),
    array('im im-icon-Check' => 'Check'),
    array('im im-icon-Checked-User' => 'Checked-User'),
    array('im im-icon-Checkmate' => 'Checkmate'),
    array('im im-icon-Checkout-Bag' => 'Checkout-Bag'),
    array('im im-icon-Checkout-Basket' => 'Checkout-Basket'),
    array('im im-icon-Checkout' => 'Checkout'),
    array('im im-icon-Cheese' => 'Cheese'),
    array('im im-icon-Cheetah' => 'Cheetah'),
    array('im im-icon-Chef-Hat' => 'Chef-Hat'),
    array('im im-icon-Chef-Hat2' => 'Chef-Hat2'),
    array('im im-icon-Chef' => 'Chef'),
    array('im im-icon-Chemical-2' => 'Chemical-2'),
    array('im im-icon-Chemical-3' => 'Chemical-3'),
    array('im im-icon-Chemical-4' => 'Chemical-4'),
    array('im im-icon-Chemical-5' => 'Chemical-5'),
    array('im im-icon-Chemical' => 'Chemical'),
    array('im im-icon-Chess-Board' => 'Chess-Board'),
    array('im im-icon-Chess' => 'Chess'),
    array('im im-icon-Chicken' => 'Chicken'),
    array('im im-icon-Chile' => 'Chile'),
    array('im im-icon-Chimney' => 'Chimney'),
    array('im im-icon-China' => 'China'),
    array('im im-icon-Chinese-Temple' => 'Chinese-Temple'),
    array('im im-icon-Chip' => 'Chip'),
    array('im im-icon-Chopsticks-2' => 'Chopsticks-2'),
    array('im im-icon-Chopsticks' => 'Chopsticks'),
    array('im im-icon-Christmas-Ball' => 'Christmas-Ball'),
    array('im im-icon-Christmas-Bell' => 'Christmas-Bell'),
    array('im im-icon-Christmas-Candle' => 'Christmas-Candle'),
    array('im im-icon-Christmas-Hat' => 'Christmas-Hat'),
    array('im im-icon-Christmas-Sleigh' => 'Christmas-Sleigh'),
    array('im im-icon-Christmas-Snowman' => 'Christmas-Snowman'),
    array('im im-icon-Christmas-Sock' => 'Christmas-Sock'),
    array('im im-icon-Christmas-Tree' => 'Christmas-Tree'),
    array('im im-icon-Christmas' => 'Christmas'),
    array('im im-icon-Chrome' => 'Chrome'),
    array('im im-icon-Chrysler-Building' => 'Chrysler-Building'),
    array('im im-icon-Cinema' => 'Cinema'),
    array('im im-icon-Circular-Point' => 'Circular-Point'),
    array('im im-icon-City-Hall' => 'City-Hall'),
    array('im im-icon-Clamp' => 'Clamp'),
    array('im im-icon-Clapperboard-Close' => 'Clapperboard-Close'),
    array('im im-icon-Clapperboard-Open' => 'Clapperboard-Open'),
    array('im im-icon-Claps' => 'Claps'),
    array('im im-icon-Clef' => 'Clef'),
    array('im im-icon-Clinic' => 'Clinic'),
    array('im im-icon-Clock-2' => 'Clock-2'),
    array('im im-icon-Clock-3' => 'Clock-3'),
    array('im im-icon-Clock-4' => 'Clock-4'),
    array('im im-icon-Clock-Back' => 'Clock-Back'),
    array('im im-icon-Clock-Forward' => 'Clock-Forward'),
    array('im im-icon-Clock' => 'Clock'),
    array('im im-icon-Close-Window' => 'Close-Window'),
    array('im im-icon-Close' => 'Close'),
    array('im im-icon-Clothing-Store' => 'Clothing-Store'),
    array('im im-icon-Cloud--' => 'Cloud--'),
    array('im im-icon-Cloud-' => 'Cloud-'),
    array('im im-icon-Cloud-Camera' => 'Cloud-Camera'),
    array('im im-icon-Cloud-Computer' => 'Cloud-Computer'),
    array('im im-icon-Cloud-Email' => 'Cloud-Email'),
    array('im im-icon-Cloud-Hail' => 'Cloud-Hail'),
    array('im im-icon-Cloud-Laptop' => 'Cloud-Laptop'),
    array('im im-icon-Cloud-Lock' => 'Cloud-Lock'),
    array('im im-icon-Cloud-Moon' => 'Cloud-Moon'),
    array('im im-icon-Cloud-Music' => 'Cloud-Music'),
    array('im im-icon-Cloud-Picture' => 'Cloud-Picture'),
    array('im im-icon-Cloud-Rain' => 'Cloud-Rain'),
    array('im im-icon-Cloud-Remove' => 'Cloud-Remove'),
    array('im im-icon-Cloud-Secure' => 'Cloud-Secure'),
    array('im im-icon-Cloud-Settings' => 'Cloud-Settings'),
    array('im im-icon-Cloud-Smartphone' => 'Cloud-Smartphone'),
    array('im im-icon-Cloud-Snow' => 'Cloud-Snow'),
    array('im im-icon-Cloud-Sun' => 'Cloud-Sun'),
    array('im im-icon-Cloud-Tablet' => 'Cloud-Tablet'),
    array('im im-icon-Cloud-Video' => 'Cloud-Video'),
    array('im im-icon-Cloud-Weather' => 'Cloud-Weather'),
    array('im im-icon-Cloud' => 'Cloud'),
    array('im im-icon-Clouds-Weather' => 'Clouds-Weather'),
    array('im im-icon-Clouds' => 'Clouds'),
    array('im im-icon-Clown' => 'Clown'),
    array('im im-icon-CMYK' => 'CMYK'),
    array('im im-icon-Coat' => 'Coat'),
    array('im im-icon-Cocktail' => 'Cocktail'),
    array('im im-icon-Coconut' => 'Coconut'),
    array('im im-icon-Code-Window' => 'Code-Window'),
    array('im im-icon-Coding' => 'Coding'),
    array('im im-icon-Coffee-2' => 'Coffee-2'),
    array('im im-icon-Coffee-Bean' => 'Coffee-Bean'),
    array('im im-icon-Coffee-Machine' => 'Coffee-Machine'),
    array('im im-icon-Coffee-toGo' => 'Coffee-toGo'),
    array('im im-icon-Coffee' => 'Coffee'),
    array('im im-icon-Coffin' => 'Coffin'),
    array('im im-icon-Coin' => 'Coin'),
    array('im im-icon-Coins-2' => 'Coins-2'),
    array('im im-icon-Coins-3' => 'Coins-3'),
    array('im im-icon-Coins' => 'Coins'),
    array('im im-icon-Colombia' => 'Colombia'),
    array('im im-icon-Colosseum' => 'Colosseum'),
    array('im im-icon-Column-2' => 'Column-2'),
    array('im im-icon-Column-3' => 'Column-3'),
    array('im im-icon-Column' => 'Column'),
    array('im im-icon-Comb-2' => 'Comb-2'),
    array('im im-icon-Comb' => 'Comb'),
    array('im im-icon-Communication-Tower' => 'Communication-Tower'),
    array('im im-icon-Communication-Tower2' => 'Communication-Tower2'),
    array('im im-icon-Compass-2' => 'Compass-2'),
    array('im im-icon-Compass-3' => 'Compass-3'),
    array('im im-icon-Compass-4' => 'Compass-4'),
    array('im im-icon-Compass-Rose' => 'Compass-Rose'),
    array('im im-icon-Compass' => 'Compass'),
    array('im im-icon-Computer-2' => 'Computer-2'),
    array('im im-icon-Computer-3' => 'Computer-3'),
    array('im im-icon-Computer-Secure' => 'Computer-Secure'),
    array('im im-icon-Computer' => 'Computer'),
    array('im im-icon-Conference' => 'Conference'),
    array('im im-icon-Confused' => 'Confused'),
    array('im im-icon-Conservation' => 'Conservation'),
    array('im im-icon-Consulting' => 'Consulting'),
    array('im im-icon-Contrast' => 'Contrast'),
    array('im im-icon-Control-2' => 'Control-2'),
    array('im im-icon-Control' => 'Control'),
    array('im im-icon-Cookie-Man' => 'Cookie-Man'),
    array('im im-icon-Cookies' => 'Cookies'),
    array('im im-icon-Cool-Guy' => 'Cool-Guy'),
    array('im im-icon-Cool' => 'Cool'),
    array('im im-icon-Copyright' => 'Copyright'),
    array('im im-icon-Costume' => 'Costume'),
    array('im im-icon-Couple-Sign' => 'Couple-Sign'),
    array('im im-icon-Cow' => 'Cow'),
    array('im im-icon-CPU' => 'CPU'),
    array('im im-icon-Crane' => 'Crane'),
    array('im im-icon-Cranium' => 'Cranium'),
    array('im im-icon-Credit-Card' => 'Credit-Card'),
    array('im im-icon-Credit-Card2' => 'Credit-Card2'),
    array('im im-icon-Credit-Card3' => 'Credit-Card3'),
    array('im im-icon-Cricket' => 'Cricket'),
    array('im im-icon-Criminal' => 'Criminal'),
    array('im im-icon-Croissant' => 'Croissant'),
    array('im im-icon-Crop-2' => 'Crop-2'),
    array('im im-icon-Crop-3' => 'Crop-3'),
    array('im im-icon-Crown-2' => 'Crown-2'),
    array('im im-icon-Crown' => 'Crown'),
    array('im im-icon-Crying' => 'Crying'),
    array('im im-icon-Cube-Molecule' => 'Cube-Molecule'),
    array('im im-icon-Cube-Molecule2' => 'Cube-Molecule2'),
    array('im im-icon-Cupcake' => 'Cupcake'),
    array('im im-icon-Cursor-Click' => 'Cursor-Click'),
    array('im im-icon-Cursor-Click2' => 'Cursor-Click2'),
    array('im im-icon-Cursor-Move' => 'Cursor-Move'),
    array('im im-icon-Cursor-Move2' => 'Cursor-Move2'),
    array('im im-icon-Cursor-Select' => 'Cursor-Select'),
    array('im im-icon-Cursor' => 'Cursor'),
    array('im im-icon-D-Eyeglasses' => 'D-Eyeglasses'),
    array('im im-icon-D-Eyeglasses2' => 'D-Eyeglasses2'),
    array('im im-icon-Dam' => 'Dam'),
    array('im im-icon-Danemark' => 'Danemark'),
    array('im im-icon-Danger-2' => 'Danger-2'),
    array('im im-icon-Danger' => 'Danger'),
    array('im im-icon-Dashboard' => 'Dashboard'),
    array('im im-icon-Data-Backup' => 'Data-Backup'),
    array('im im-icon-Data-Block' => 'Data-Block'),
    array('im im-icon-Data-Center' => 'Data-Center'),
    array('im im-icon-Data-Clock' => 'Data-Clock'),
    array('im im-icon-Data-Cloud' => 'Data-Cloud'),
    array('im im-icon-Data-Compress' => 'Data-Compress'),
    array('im im-icon-Data-Copy' => 'Data-Copy'),
    array('im im-icon-Data-Download' => 'Data-Download'),
    array('im im-icon-Data-Financial' => 'Data-Financial'),
    array('im im-icon-Data-Key' => 'Data-Key'),
    array('im im-icon-Data-Lock' => 'Data-Lock'),
    array('im im-icon-Data-Network' => 'Data-Network'),
    array('im im-icon-Data-Password' => 'Data-Password'),
    array('im im-icon-Data-Power' => 'Data-Power'),
    array('im im-icon-Data-Refresh' => 'Data-Refresh'),
    array('im im-icon-Data-Save' => 'Data-Save'),
    array('im im-icon-Data-Search' => 'Data-Search'),
    array('im im-icon-Data-Security' => 'Data-Security'),
    array('im im-icon-Data-Settings' => 'Data-Settings'),
    array('im im-icon-Data-Sharing' => 'Data-Sharing'),
    array('im im-icon-Data-Shield' => 'Data-Shield'),
    array('im im-icon-Data-Signal' => 'Data-Signal'),
    array('im im-icon-Data-Storage' => 'Data-Storage'),
    array('im im-icon-Data-Stream' => 'Data-Stream'),
    array('im im-icon-Data-Transfer' => 'Data-Transfer'),
    array('im im-icon-Data-Unlock' => 'Data-Unlock'),
    array('im im-icon-Data-Upload' => 'Data-Upload'),
    array('im im-icon-Data-Yes' => 'Data-Yes'),
    array('im im-icon-Data' => 'Data'),
    array('im im-icon-David-Star' => 'David-Star'),
    array('im im-icon-Daylight' => 'Daylight'),
    array('im im-icon-Death' => 'Death'),
    array('im im-icon-Debian' => 'Debian'),
    array('im im-icon-Dec' => 'Dec'),
    array('im im-icon-Decrase-Inedit' => 'Decrase-Inedit'),
    array('im im-icon-Deer-2' => 'Deer-2'),
    array('im im-icon-Deer' => 'Deer'),
    array('im im-icon-Delete-File' => 'Delete-File'),
    array('im im-icon-Delete-Window' => 'Delete-Window'),
    array('im im-icon-Delicious' => 'Delicious'),
    array('im im-icon-Depression' => 'Depression'),
    array('im im-icon-Deviantart' => 'Deviantart'),
    array('im im-icon-Device-SyncwithCloud' => 'Device-SyncwithCloud'),
    array('im im-icon-Diamond' => 'Diamond'),
    array('im im-icon-Dice-2' => 'Dice-2'),
    array('im im-icon-Dice' => 'Dice'),
    array('im im-icon-Digg' => 'Digg'),
    array('im im-icon-Digital-Drawing' => 'Digital-Drawing'),
    array('im im-icon-Diigo' => 'Diigo'),
    array('im im-icon-Dinosaur' => 'Dinosaur'),
    array('im im-icon-Diploma-2' => 'Diploma-2'),
    array('im im-icon-Diploma' => 'Diploma'),
    array('im im-icon-Direction-East' => 'Direction-East'),
    array('im im-icon-Direction-North' => 'Direction-North'),
    array('im im-icon-Direction-South' => 'Direction-South'),
    array('im im-icon-Direction-West' => 'Direction-West'),
    array('im im-icon-Director' => 'Director'),
    array('im im-icon-Disk' => 'Disk'),
    array('im im-icon-Dj' => 'Dj'),
    array('im im-icon-DNA-2' => 'DNA-2'),
    array('im im-icon-DNA-Helix' => 'DNA-Helix'),
    array('im im-icon-DNA' => 'DNA'),
    array('im im-icon-Doctor' => 'Doctor'),
    array('im im-icon-Dog' => 'Dog'),
    array('im im-icon-Dollar-Sign' => 'Dollar-Sign'),
    array('im im-icon-Dollar-Sign2' => 'Dollar-Sign2'),
    array('im im-icon-Dollar' => 'Dollar'),
    array('im im-icon-Dolphin' => 'Dolphin'),
    array('im im-icon-Domino' => 'Domino'),
    array('im im-icon-Door-Hanger' => 'Door-Hanger'),
    array('im im-icon-Door' => 'Door'),
    array('im im-icon-Doplr' => 'Doplr'),
    array('im im-icon-Double-Circle' => 'Double-Circle'),
    array('im im-icon-Double-Tap' => 'Double-Tap'),
    array('im im-icon-Doughnut' => 'Doughnut'),
    array('im im-icon-Dove' => 'Dove'),
    array('im im-icon-Down-2' => 'Down-2'),
    array('im im-icon-Down-3' => 'Down-3'),
    array('im im-icon-Down-4' => 'Down-4'),
    array('im im-icon-Down' => 'Down'),
    array('im im-icon-Download-2' => 'Download-2'),
    array('im im-icon-Download-fromCloud' => 'Download-fromCloud'),
    array('im im-icon-Download-Window' => 'Download-Window'),
    array('im im-icon-Download' => 'Download'),
    array('im im-icon-Downward' => 'Downward'),
    array('im im-icon-Drag-Down' => 'Drag-Down'),
    array('im im-icon-Drag-Left' => 'Drag-Left'),
    array('im im-icon-Drag-Right' => 'Drag-Right'),
    array('im im-icon-Drag-Up' => 'Drag-Up'),
    array('im im-icon-Drag' => 'Drag'),
    array('im im-icon-Dress' => 'Dress'),
    array('im im-icon-Drill-2' => 'Drill-2'),
    array('im im-icon-Drill' => 'Drill'),
    array('im im-icon-Drop' => 'Drop'),
    array('im im-icon-Dropbox' => 'Dropbox'),
    array('im im-icon-Drum' => 'Drum'),
    array('im im-icon-Dry' => 'Dry'),
    array('im im-icon-Duck' => 'Duck'),
    array('im im-icon-Dumbbell' => 'Dumbbell'),
    array('im im-icon-Duplicate-Layer' => 'Duplicate-Layer'),
    array('im im-icon-Duplicate-Window' => 'Duplicate-Window'),
    array('im im-icon-DVD' => 'DVD'),
    array('im im-icon-Eagle' => 'Eagle'),
    array('im im-icon-Ear' => 'Ear'),
    array('im im-icon-Earphones-2' => 'Earphones-2'),
    array('im im-icon-Earphones' => 'Earphones'),
    array('im im-icon-Eci-Icon' => 'Eci-Icon'),
    array('im im-icon-Edit-Map' => 'Edit-Map'),
    array('im im-icon-Edit' => 'Edit'),
    array('im im-icon-Eggs' => 'Eggs'),
    array('im im-icon-Egypt' => 'Egypt'),
    array('im im-icon-Eifel-Tower' => 'Eifel-Tower'),
    array('im im-icon-eject-2' => 'eject-2'),
    array('im im-icon-Eject' => 'Eject'),
    array('im im-icon-El-Castillo' => 'El-Castillo'),
    array('im im-icon-Elbow' => 'Elbow'),
    array('im im-icon-Electric-Guitar' => 'Electric-Guitar'),
    array('im im-icon-Electricity' => 'Electricity'),
    array('im im-icon-Elephant' => 'Elephant'),
    array('im im-icon-Email' => 'Email'),
    array('im im-icon-Embassy' => 'Embassy'),
    array('im im-icon-Empire-StateBuilding' => 'Empire-StateBuilding'),
    array('im im-icon-Empty-Box' => 'Empty-Box'),
    array('im im-icon-End2' => 'End2'),
    array('im im-icon-End-2' => 'End-2'),
    array('im im-icon-End' => 'End'),
    array('im im-icon-Endways' => 'Endways'),
    array('im im-icon-Engineering' => 'Engineering'),
    array('im im-icon-Envelope-2' => 'Envelope-2'),
    array('im im-icon-Envelope' => 'Envelope'),
    array('im im-icon-Environmental-2' => 'Environmental-2'),
    array('im im-icon-Environmental-3' => 'Environmental-3'),
    array('im im-icon-Environmental' => 'Environmental'),
    array('im im-icon-Equalizer' => 'Equalizer'),
    array('im im-icon-Eraser-2' => 'Eraser-2'),
    array('im im-icon-Eraser-3' => 'Eraser-3'),
    array('im im-icon-Eraser' => 'Eraser'),
    array('im im-icon-Error-404Window' => 'Error-404Window'),
    array('im im-icon-Euro-Sign' => 'Euro-Sign'),
    array('im im-icon-Euro-Sign2' => 'Euro-Sign2'),
    array('im im-icon-Euro' => 'Euro'),
    array('im im-icon-Evernote' => 'Evernote'),
    array('im im-icon-Evil' => 'Evil'),
    array('im im-icon-Explode' => 'Explode'),
    array('im im-icon-Eye-2' => 'Eye-2'),
    array('im im-icon-Eye-Blind' => 'Eye-Blind'),
    array('im im-icon-Eye-Invisible' => 'Eye-Invisible'),
    array('im im-icon-Eye-Scan' => 'Eye-Scan'),
    array('im im-icon-Eye-Visible' => 'Eye-Visible'),
    array('im im-icon-Eye' => 'Eye'),
    array('im im-icon-Eyebrow-2' => 'Eyebrow-2'),
    array('im im-icon-Eyebrow-3' => 'Eyebrow-3'),
    array('im im-icon-Eyebrow' => 'Eyebrow'),
    array('im im-icon-Eyeglasses-Smiley' => 'Eyeglasses-Smiley'),
    array('im im-icon-Eyeglasses-Smiley2' => 'Eyeglasses-Smiley2'),
    array('im im-icon-Face-Style' => 'Face-Style'),
    array('im im-icon-Face-Style2' => 'Face-Style2'),
    array('im im-icon-Face-Style3' => 'Face-Style3'),
    array('im im-icon-Face-Style4' => 'Face-Style4'),
    array('im im-icon-Face-Style5' => 'Face-Style5'),
    array('im im-icon-Face-Style6' => 'Face-Style6'),
    array('im im-icon-Facebook-2' => 'Facebook-2'),
    array('im im-icon-Facebook' => 'Facebook'),
    array('im im-icon-Factory-2' => 'Factory-2'),
    array('im im-icon-Factory' => 'Factory'),
    array('im im-icon-Fahrenheit' => 'Fahrenheit'),
    array('im im-icon-Family-Sign' => 'Family-Sign'),
    array('im im-icon-Fan' => 'Fan'),
    array('im im-icon-Farmer' => 'Farmer'),
    array('im im-icon-Fashion' => 'Fashion'),
    array('im im-icon-Favorite-Window' => 'Favorite-Window'),
    array('im im-icon-Fax' => 'Fax'),
    array('im im-icon-Feather' => 'Feather'),
    array('im im-icon-Feedburner' => 'Feedburner'),
    array('im im-icon-Female-2' => 'Female-2'),
    array('im im-icon-Female-Sign' => 'Female-Sign'),
    array('im im-icon-Female' => 'Female'),
    array('im im-icon-File-Block' => 'File-Block'),
    array('im im-icon-File-Bookmark' => 'File-Bookmark'),
    array('im im-icon-File-Chart' => 'File-Chart'),
    array('im im-icon-File-Clipboard' => 'File-Clipboard'),
    array('im im-icon-File-ClipboardFileText' => 'File-ClipboardFileText'),
    array('im im-icon-File-ClipboardTextImage' => 'File-ClipboardTextImage'),
    array('im im-icon-File-Cloud' => 'File-Cloud'),
    array('im im-icon-File-Copy' => 'File-Copy'),
    array('im im-icon-File-Copy2' => 'File-Copy2'),
    array('im im-icon-File-CSV' => 'File-CSV'),
    array('im im-icon-File-Download' => 'File-Download'),
    array('im im-icon-File-Edit' => 'File-Edit'),
    array('im im-icon-File-Excel' => 'File-Excel'),
    array('im im-icon-File-Favorite' => 'File-Favorite'),
    array('im im-icon-File-Fire' => 'File-Fire'),
    array('im im-icon-File-Graph' => 'File-Graph'),
    array('im im-icon-File-Hide' => 'File-Hide'),
    array('im im-icon-File-Horizontal' => 'File-Horizontal'),
    array('im im-icon-File-HorizontalText' => 'File-HorizontalText'),
    array('im im-icon-File-HTML' => 'File-HTML'),
    array('im im-icon-File-JPG' => 'File-JPG'),
    array('im im-icon-File-Link' => 'File-Link'),
    array('im im-icon-File-Loading' => 'File-Loading'),
    array('im im-icon-File-Lock' => 'File-Lock'),
    array('im im-icon-File-Love' => 'File-Love'),
    array('im im-icon-File-Music' => 'File-Music'),
    array('im im-icon-File-Network' => 'File-Network'),
    array('im im-icon-File-Pictures' => 'File-Pictures'),
    array('im im-icon-File-Pie' => 'File-Pie'),
    array('im im-icon-File-Presentation' => 'File-Presentation'),
    array('im im-icon-File-Refresh' => 'File-Refresh'),
    array('im im-icon-File-Search' => 'File-Search'),
    array('im im-icon-File-Settings' => 'File-Settings'),
    array('im im-icon-File-Share' => 'File-Share'),
    array('im im-icon-File-TextImage' => 'File-TextImage'),
    array('im im-icon-File-Trash' => 'File-Trash'),
    array('im im-icon-File-TXT' => 'File-TXT'),
    array('im im-icon-File-Upload' => 'File-Upload'),
    array('im im-icon-File-Video' => 'File-Video'),
    array('im im-icon-File-Word' => 'File-Word'),
    array('im im-icon-File-Zip' => 'File-Zip'),
    array('im im-icon-File' => 'File'),
    array('im im-icon-Files' => 'Files'),
    array('im im-icon-Film-Board' => 'Film-Board'),
    array('im im-icon-Film-Cartridge' => 'Film-Cartridge'),
    array('im im-icon-Film-Strip' => 'Film-Strip'),
    array('im im-icon-Film-Video' => 'Film-Video'),
    array('im im-icon-Film' => 'Film'),
    array('im im-icon-Filter-2' => 'Filter-2'),
    array('im im-icon-Filter' => 'Filter'),
    array('im im-icon-Financial' => 'Financial'),
    array('im im-icon-Find-User' => 'Find-User'),
    array('im im-icon-Finger-DragFourSides' => 'Finger-DragFourSides'),
    array('im im-icon-Finger-DragTwoSides' => 'Finger-DragTwoSides'),
    array('im im-icon-Finger-Print' => 'Finger-Print'),
    array('im im-icon-Finger' => 'Finger'),
    array('im im-icon-Fingerprint-2' => 'Fingerprint-2'),
    array('im im-icon-Fingerprint' => 'Fingerprint'),
    array('im im-icon-Fire-Flame' => 'Fire-Flame'),
    array('im im-icon-Fire-Flame2' => 'Fire-Flame2'),
    array('im im-icon-Fire-Hydrant' => 'Fire-Hydrant'),
    array('im im-icon-Fire-Staion' => 'Fire-Staion'),
    array('im im-icon-Firefox' => 'Firefox'),
    array('im im-icon-Firewall' => 'Firewall'),
    array('im im-icon-First-Aid' => 'First-Aid'),
    array('im im-icon-First' => 'First'),
    array('im im-icon-Fish-Food' => 'Fish-Food'),
    array('im im-icon-Fish' => 'Fish'),
    array('im im-icon-Fit-To' => 'Fit-To'),
    array('im im-icon-Fit-To2' => 'Fit-To2'),
    array('im im-icon-Five-Fingers' => 'Five-Fingers'),
    array('im im-icon-Five-FingersDrag' => 'Five-FingersDrag'),
    array('im im-icon-Five-FingersDrag2' => 'Five-FingersDrag2'),
    array('im im-icon-Five-FingersTouch' => 'Five-FingersTouch'),
    array('im im-icon-Flag-2' => 'Flag-2'),
    array('im im-icon-Flag-3' => 'Flag-3'),
    array('im im-icon-Flag-4' => 'Flag-4'),
    array('im im-icon-Flag-5' => 'Flag-5'),
    array('im im-icon-Flag-6' => 'Flag-6'),
    array('im im-icon-Flag' => 'Flag'),
    array('im im-icon-Flamingo' => 'Flamingo'),
    array('im im-icon-Flash-2' => 'Flash-2'),
    array('im im-icon-Flash-Video' => 'Flash-Video'),
    array('im im-icon-Flash' => 'Flash'),
    array('im im-icon-Flashlight' => 'Flashlight'),
    array('im im-icon-Flask-2' => 'Flask-2'),
    array('im im-icon-Flask' => 'Flask'),
    array('im im-icon-Flick' => 'Flick'),
    array('im im-icon-Flickr' => 'Flickr'),
    array('im im-icon-Flowerpot' => 'Flowerpot'),
    array('im im-icon-Fluorescent' => 'Fluorescent'),
    array('im im-icon-Fog-Day' => 'Fog-Day'),
    array('im im-icon-Fog-Night' => 'Fog-Night'),
    array('im im-icon-Folder-Add' => 'Folder-Add'),
    array('im im-icon-Folder-Archive' => 'Folder-Archive'),
    array('im im-icon-Folder-Binder' => 'Folder-Binder'),
    array('im im-icon-Folder-Binder2' => 'Folder-Binder2'),
    array('im im-icon-Folder-Block' => 'Folder-Block'),
    array('im im-icon-Folder-Bookmark' => 'Folder-Bookmark'),
    array('im im-icon-Folder-Close' => 'Folder-Close'),
    array('im im-icon-Folder-Cloud' => 'Folder-Cloud'),
    array('im im-icon-Folder-Delete' => 'Folder-Delete'),
    array('im im-icon-Folder-Download' => 'Folder-Download'),
    array('im im-icon-Folder-Edit' => 'Folder-Edit'),
    array('im im-icon-Folder-Favorite' => 'Folder-Favorite'),
    array('im im-icon-Folder-Fire' => 'Folder-Fire'),
    array('im im-icon-Folder-Hide' => 'Folder-Hide'),
    array('im im-icon-Folder-Link' => 'Folder-Link'),
    array('im im-icon-Folder-Loading' => 'Folder-Loading'),
    array('im im-icon-Folder-Lock' => 'Folder-Lock'),
    array('im im-icon-Folder-Love' => 'Folder-Love'),
    array('im im-icon-Folder-Music' => 'Folder-Music'),
    array('im im-icon-Folder-Network' => 'Folder-Network'),
    array('im im-icon-Folder-Open' => 'Folder-Open'),
    array('im im-icon-Folder-Open2' => 'Folder-Open2'),
    array('im im-icon-Folder-Organizing' => 'Folder-Organizing'),
    array('im im-icon-Folder-Pictures' => 'Folder-Pictures'),
    array('im im-icon-Folder-Refresh' => 'Folder-Refresh'),
    array('im im-icon-Folder-Remove-' => 'Folder-Remove-'),
    array('im im-icon-Folder-Search' => 'Folder-Search'),
    array('im im-icon-Folder-Settings' => 'Folder-Settings'),
    array('im im-icon-Folder-Share' => 'Folder-Share'),
    array('im im-icon-Folder-Trash' => 'Folder-Trash'),
    array('im im-icon-Folder-Upload' => 'Folder-Upload'),
    array('im im-icon-Folder-Video' => 'Folder-Video'),
    array('im im-icon-Folder-WithDocument' => 'Folder-WithDocument'),
    array('im im-icon-Folder-Zip' => 'Folder-Zip'),
    array('im im-icon-Folder' => 'Folder'),
    array('im im-icon-Folders' => 'Folders'),
    array('im im-icon-Font-Color' => 'Font-Color'),
    array('im im-icon-Font-Name' => 'Font-Name'),
    array('im im-icon-Font-Size' => 'Font-Size'),
    array('im im-icon-Font-Style' => 'Font-Style'),
    array('im im-icon-Font-StyleSubscript' => 'Font-StyleSubscript'),
    array('im im-icon-Font-StyleSuperscript' => 'Font-StyleSuperscript'),
    array('im im-icon-Font-Window' => 'Font-Window'),
    array('im im-icon-Foot-2' => 'Foot-2'),
    array('im im-icon-Foot' => 'Foot'),
    array('im im-icon-Football-2' => 'Football-2'),
    array('im im-icon-Football' => 'Football'),
    array('im im-icon-Footprint-2' => 'Footprint-2'),
    array('im im-icon-Footprint-3' => 'Footprint-3'),
    array('im im-icon-Footprint' => 'Footprint'),
    array('im im-icon-Forest' => 'Forest'),
    array('im im-icon-Fork' => 'Fork'),
    array('im im-icon-Formspring' => 'Formspring'),
    array('im im-icon-Formula' => 'Formula'),
    array('im im-icon-Forsquare' => 'Forsquare'),
    array('im im-icon-Forward' => 'Forward'),
    array('im im-icon-Fountain-Pen' => 'Fountain-Pen'),
    array('im im-icon-Four-Fingers' => 'Four-Fingers'),
    array('im im-icon-Four-FingersDrag' => 'Four-FingersDrag'),
    array('im im-icon-Four-FingersDrag2' => 'Four-FingersDrag2'),
    array('im im-icon-Four-FingersTouch' => 'Four-FingersTouch'),
    array('im im-icon-Fox' => 'Fox'),
    array('im im-icon-Frankenstein' => 'Frankenstein'),
    array('im im-icon-French-Fries' => 'French-Fries'),
    array('im im-icon-Friendfeed' => 'Friendfeed'),
    array('im im-icon-Friendster' => 'Friendster'),
    array('im im-icon-Frog' => 'Frog'),
    array('im im-icon-Fruits' => 'Fruits'),
    array('im im-icon-Fuel' => 'Fuel'),
    array('im im-icon-Full-Bag' => 'Full-Bag'),
    array('im im-icon-Full-Basket' => 'Full-Basket'),
    array('im im-icon-Full-Cart' => 'Full-Cart'),
    array('im im-icon-Full-Moon' => 'Full-Moon'),
    array('im im-icon-Full-Screen' => 'Full-Screen'),
    array('im im-icon-Full-Screen2' => 'Full-Screen2'),
    array('im im-icon-Full-View' => 'Full-View'),
    array('im im-icon-Full-View2' => 'Full-View2'),
    array('im im-icon-Full-ViewWindow' => 'Full-ViewWindow'),
    array('im im-icon-Function' => 'Function'),
    array('im im-icon-Funky' => 'Funky'),
    array('im im-icon-Funny-Bicycle' => 'Funny-Bicycle'),
    array('im im-icon-Furl' => 'Furl'),
    array('im im-icon-Gamepad-2' => 'Gamepad-2'),
    array('im im-icon-Gamepad' => 'Gamepad'),
    array('im im-icon-Gas-Pump' => 'Gas-Pump'),
    array('im im-icon-Gaugage-2' => 'Gaugage-2'),
    array('im im-icon-Gaugage' => 'Gaugage'),
    array('im im-icon-Gay' => 'Gay'),
    array('im im-icon-Gear-2' => 'Gear-2'),
    array('im im-icon-Gear' => 'Gear'),
    array('im im-icon-Gears-2' => 'Gears-2'),
    array('im im-icon-Gears' => 'Gears'),
    array('im im-icon-Geek-2' => 'Geek-2'),
    array('im im-icon-Geek' => 'Geek'),
    array('im im-icon-Gemini-2' => 'Gemini-2'),
    array('im im-icon-Gemini' => 'Gemini'),
    array('im im-icon-Genius' => 'Genius'),
    array('im im-icon-Gentleman' => 'Gentleman'),
    array('im im-icon-Geo--' => 'Geo--'),
    array('im im-icon-Geo-' => 'Geo-'),
    array('im im-icon-Geo-Close' => 'Geo-Close'),
    array('im im-icon-Geo-Love' => 'Geo-Love'),
    array('im im-icon-Geo-Number' => 'Geo-Number'),
    array('im im-icon-Geo-Star' => 'Geo-Star'),
    array('im im-icon-Geo' => 'Geo'),
    array('im im-icon-Geo2--' => 'Geo2--'),
    array('im im-icon-Geo2-' => 'Geo2-'),
    array('im im-icon-Geo2-Close' => 'Geo2-Close'),
    array('im im-icon-Geo2-Love' => 'Geo2-Love'),
    array('im im-icon-Geo2-Number' => 'Geo2-Number'),
    array('im im-icon-Geo2-Star' => 'Geo2-Star'),
    array('im im-icon-Geo2' => 'Geo2'),
    array('im im-icon-Geo3--' => 'Geo3--'),
    array('im im-icon-Geo3-' => 'Geo3-'),
    array('im im-icon-Geo3-Close' => 'Geo3-Close'),
    array('im im-icon-Geo3-Love' => 'Geo3-Love'),
    array('im im-icon-Geo3-Number' => 'Geo3-Number'),
    array('im im-icon-Geo3-Star' => 'Geo3-Star'),
    array('im im-icon-Geo3' => 'Geo3'),
    array('im im-icon-Gey' => 'Gey'),
    array('im im-icon-Gift-Box' => 'Gift-Box'),
    array('im im-icon-Giraffe' => 'Giraffe'),
    array('im im-icon-Girl' => 'Girl'),
    array('im im-icon-Glass-Water' => 'Glass-Water'),
    array('im im-icon-Glasses-2' => 'Glasses-2'),
    array('im im-icon-Glasses-3' => 'Glasses-3'),
    array('im im-icon-Glasses' => 'Glasses'),
    array('im im-icon-Global-Position' => 'Global-Position'),
    array('im im-icon-Globe-2' => 'Globe-2'),
    array('im im-icon-Globe' => 'Globe'),
    array('im im-icon-Gloves' => 'Gloves'),
    array('im im-icon-Go-Bottom' => 'Go-Bottom'),
    array('im im-icon-Go-Top' => 'Go-Top'),
    array('im im-icon-Goggles' => 'Goggles'),
    array('im im-icon-Golf-2' => 'Golf-2'),
    array('im im-icon-Golf' => 'Golf'),
    array('im im-icon-Google-Buzz' => 'Google-Buzz'),
    array('im im-icon-Google-Drive' => 'Google-Drive'),
    array('im im-icon-Google-Play' => 'Google-Play'),
    array('im im-icon-Google-Plus' => 'Google-Plus'),
    array('im im-icon-Google' => 'Google'),
    array('im im-icon-Gopro' => 'Gopro'),
    array('im im-icon-Gorilla' => 'Gorilla'),
    array('im im-icon-Gowalla' => 'Gowalla'),
    array('im im-icon-Grave' => 'Grave'),
    array('im im-icon-Graveyard' => 'Graveyard'),
    array('im im-icon-Greece' => 'Greece'),
    array('im im-icon-Green-Energy' => 'Green-Energy'),
    array('im im-icon-Green-House' => 'Green-House'),
    array('im im-icon-Guitar' => 'Guitar'),
    array('im im-icon-Gun-2' => 'Gun-2'),
    array('im im-icon-Gun-3' => 'Gun-3'),
    array('im im-icon-Gun' => 'Gun'),
    array('im im-icon-Gymnastics' => 'Gymnastics'),
    array('im im-icon-Hair-2' => 'Hair-2'),
    array('im im-icon-Hair-3' => 'Hair-3'),
    array('im im-icon-Hair-4' => 'Hair-4'),
    array('im im-icon-Hair' => 'Hair'),
    array('im im-icon-Half-Moon' => 'Half-Moon'),
    array('im im-icon-Halloween-HalfMoon' => 'Halloween-HalfMoon'),
    array('im im-icon-Halloween-Moon' => 'Halloween-Moon'),
    array('im im-icon-Hamburger' => 'Hamburger'),
    array('im im-icon-Hammer' => 'Hammer'),
    array('im im-icon-Hand-Touch' => 'Hand-Touch'),
    array('im im-icon-Hand-Touch2' => 'Hand-Touch2'),
    array('im im-icon-Hand-TouchSmartphone' => 'Hand-TouchSmartphone'),
    array('im im-icon-Hand' => 'Hand'),
    array('im im-icon-Hands' => 'Hands'),
    array('im im-icon-Handshake' => 'Handshake'),
    array('im im-icon-Hanger' => 'Hanger'),
    array('im im-icon-Happy' => 'Happy'),
    array('im im-icon-Hat-2' => 'Hat-2'),
    array('im im-icon-Hat' => 'Hat'),
    array('im im-icon-Haunted-House' => 'Haunted-House'),
    array('im im-icon-HD-Video' => 'HD-Video'),
    array('im im-icon-HD' => 'HD'),
    array('im im-icon-HDD' => 'HDD'),
    array('im im-icon-Headphone' => 'Headphone'),
    array('im im-icon-Headphones' => 'Headphones'),
    array('im im-icon-Headset' => 'Headset'),
    array('im im-icon-Heart-2' => 'Heart-2'),
    array('im im-icon-Heart' => 'Heart'),
    array('im im-icon-Heels-2' => 'Heels-2'),
    array('im im-icon-Heels' => 'Heels'),
    array('im im-icon-Height-Window' => 'Height-Window'),
    array('im im-icon-Helicopter-2' => 'Helicopter-2'),
    array('im im-icon-Helicopter' => 'Helicopter'),
    array('im im-icon-Helix-2' => 'Helix-2'),
    array('im im-icon-Hello' => 'Hello'),
    array('im im-icon-Helmet-2' => 'Helmet-2'),
    array('im im-icon-Helmet-3' => 'Helmet-3'),
    array('im im-icon-Helmet' => 'Helmet'),
    array('im im-icon-Hipo' => 'Hipo'),
    array('im im-icon-Hipster-Glasses' => 'Hipster-Glasses'),
    array('im im-icon-Hipster-Glasses2' => 'Hipster-Glasses2'),
    array('im im-icon-Hipster-Glasses3' => 'Hipster-Glasses3'),
    array('im im-icon-Hipster-Headphones' => 'Hipster-Headphones'),
    array('im im-icon-Hipster-Men' => 'Hipster-Men'),
    array('im im-icon-Hipster-Men2' => 'Hipster-Men2'),
    array('im im-icon-Hipster-Men3' => 'Hipster-Men3'),
    array('im im-icon-Hipster-Sunglasses' => 'Hipster-Sunglasses'),
    array('im im-icon-Hipster-Sunglasses2' => 'Hipster-Sunglasses2'),
    array('im im-icon-Hipster-Sunglasses3' => 'Hipster-Sunglasses3'),
    array('im im-icon-Hokey' => 'Hokey'),
    array('im im-icon-Holly' => 'Holly'),
    array('im im-icon-Home-2' => 'Home-2'),
    array('im im-icon-Home-3' => 'Home-3'),
    array('im im-icon-Home-4' => 'Home-4'),
    array('im im-icon-Home-5' => 'Home-5'),
    array('im im-icon-Home-Window' => 'Home-Window'),
    array('im im-icon-Home' => 'Home'),
    array('im im-icon-Homosexual' => 'Homosexual'),
    array('im im-icon-Honey' => 'Honey'),
    array('im im-icon-Hong-Kong' => 'Hong-Kong'),
    array('im im-icon-Hoodie' => 'Hoodie'),
    array('im im-icon-Horror' => 'Horror'),
    array('im im-icon-Horse' => 'Horse'),
    array('im im-icon-Hospital-2' => 'Hospital-2'),
    array('im im-icon-Hospital' => 'Hospital'),
    array('im im-icon-Host' => 'Host'),
    array('im im-icon-Hot-Dog' => 'Hot-Dog'),
    array('im im-icon-Hotel' => 'Hotel'),
    array('im im-icon-Hour' => 'Hour'),
    array('im im-icon-Hub' => 'Hub'),
    array('im im-icon-Humor' => 'Humor'),
    array('im im-icon-Hurt' => 'Hurt'),
    array('im im-icon-Ice-Cream' => 'Ice-Cream'),
    array('im im-icon-ICQ' => 'ICQ'),
    array('im im-icon-ID-2' => 'ID-2'),
    array('im im-icon-ID-3' => 'ID-3'),
    array('im im-icon-ID-Card' => 'ID-Card'),
    array('im im-icon-Idea-2' => 'Idea-2'),
    array('im im-icon-Idea-3' => 'Idea-3'),
    array('im im-icon-Idea-4' => 'Idea-4'),
    array('im im-icon-Idea-5' => 'Idea-5'),
    array('im im-icon-Idea' => 'Idea'),
    array('im im-icon-Identification-Badge' => 'Identification-Badge'),
    array('im im-icon-ImDB' => 'ImDB'),
    array('im im-icon-Inbox-Empty' => 'Inbox-Empty'),
    array('im im-icon-Inbox-Forward' => 'Inbox-Forward'),
    array('im im-icon-Inbox-Full' => 'Inbox-Full'),
    array('im im-icon-Inbox-Into' => 'Inbox-Into'),
    array('im im-icon-Inbox-Out' => 'Inbox-Out'),
    array('im im-icon-Inbox-Reply' => 'Inbox-Reply'),
    array('im im-icon-Inbox' => 'Inbox'),
    array('im im-icon-Increase-Inedit' => 'Increase-Inedit'),
    array('im im-icon-Indent-FirstLine' => 'Indent-FirstLine'),
    array('im im-icon-Indent-LeftMargin' => 'Indent-LeftMargin'),
    array('im im-icon-Indent-RightMargin' => 'Indent-RightMargin'),
    array('im im-icon-India' => 'India'),
    array('im im-icon-Info-Window' => 'Info-Window'),
    array('im im-icon-Information' => 'Information'),
    array('im im-icon-Inifity' => 'Inifity'),
    array('im im-icon-Instagram' => 'Instagram'),
    array('im im-icon-Internet-2' => 'Internet-2'),
    array('im im-icon-Internet-Explorer' => 'Internet-Explorer'),
    array('im im-icon-Internet-Smiley' => 'Internet-Smiley'),
    array('im im-icon-Internet' => 'Internet'),
    array('im im-icon-iOS-Apple' => 'iOS-Apple'),
    array('im im-icon-Israel' => 'Israel'),
    array('im im-icon-Italic-Text' => 'Italic-Text'),
    array('im im-icon-Jacket-2' => 'Jacket-2'),
    array('im im-icon-Jacket' => 'Jacket'),
    array('im im-icon-Jamaica' => 'Jamaica'),
    array('im im-icon-Japan' => 'Japan'),
    array('im im-icon-Japanese-Gate' => 'Japanese-Gate'),
    array('im im-icon-Jeans' => 'Jeans'),
    array('im im-icon-Jeep-2' => 'Jeep-2'),
    array('im im-icon-Jeep' => 'Jeep'),
    array('im im-icon-Jet' => 'Jet'),
    array('im im-icon-Joystick' => 'Joystick'),
    array('im im-icon-Juice' => 'Juice'),
    array('im im-icon-Jump-Rope' => 'Jump-Rope'),
    array('im im-icon-Kangoroo' => 'Kangoroo'),
    array('im im-icon-Kenya' => 'Kenya'),
    array('im im-icon-Key-2' => 'Key-2'),
    array('im im-icon-Key-3' => 'Key-3'),
    array('im im-icon-Key-Lock' => 'Key-Lock'),
    array('im im-icon-Key' => 'Key'),
    array('im im-icon-Keyboard' => 'Keyboard'),
    array('im im-icon-Keyboard3' => 'Keyboard3'),
    array('im im-icon-Keypad' => 'Keypad'),
    array('im im-icon-King-2' => 'King-2'),
    array('im im-icon-King' => 'King'),
    array('im im-icon-Kiss' => 'Kiss'),
    array('im im-icon-Knee' => 'Knee'),
    array('im im-icon-Knife-2' => 'Knife-2'),
    array('im im-icon-Knife' => 'Knife'),
    array('im im-icon-Knight' => 'Knight'),
    array('im im-icon-Koala' => 'Koala'),
    array('im im-icon-Korea' => 'Korea'),
    array('im im-icon-Lamp' => 'Lamp'),
    array('im im-icon-Landscape-2' => 'Landscape-2'),
    array('im im-icon-Landscape' => 'Landscape'),
    array('im im-icon-Lantern' => 'Lantern'),
    array('im im-icon-Laptop-2' => 'Laptop-2'),
    array('im im-icon-Laptop-3' => 'Laptop-3'),
    array('im im-icon-Laptop-Phone' => 'Laptop-Phone'),
    array('im im-icon-Laptop-Secure' => 'Laptop-Secure'),
    array('im im-icon-Laptop-Tablet' => 'Laptop-Tablet'),
    array('im im-icon-Laptop' => 'Laptop'),
    array('im im-icon-Laser' => 'Laser'),
    array('im im-icon-Last-FM' => 'Last-FM'),
    array('im im-icon-Last' => 'Last'),
    array('im im-icon-Laughing' => 'Laughing'),
    array('im im-icon-Layer-1635' => 'Layer-1635'),
    array('im im-icon-Layer-1646' => 'Layer-1646'),
    array('im im-icon-Layer-Backward' => 'Layer-Backward'),
    array('im im-icon-Layer-Forward' => 'Layer-Forward'),
    array('im im-icon-Leafs-2' => 'Leafs-2'),
    array('im im-icon-Leafs' => 'Leafs'),
    array('im im-icon-Leaning-Tower' => 'Leaning-Tower'),
    array('im im-icon-Left--Right' => 'Left--Right'),
    array('im im-icon-Left--Right3' => 'Left--Right3'),
    array('im im-icon-Left-2' => 'Left-2'),
    array('im im-icon-Left-3' => 'Left-3'),
    array('im im-icon-Left-4' => 'Left-4'),
    array('im im-icon-Left-ToRight' => 'Left-ToRight'),
    array('im im-icon-Left' => 'Left'),
    array('im im-icon-Leg-2' => 'Leg-2'),
    array('im im-icon-Leg' => 'Leg'),
    array('im im-icon-Lego' => 'Lego'),
    array('im im-icon-Lemon' => 'Lemon'),
    array('im im-icon-Len-2' => 'Len-2'),
    array('im im-icon-Len-3' => 'Len-3'),
    array('im im-icon-Len' => 'Len'),
    array('im im-icon-Leo-2' => 'Leo-2'),
    array('im im-icon-Leo' => 'Leo'),
    array('im im-icon-Leopard' => 'Leopard'),
    array('im im-icon-Lesbian' => 'Lesbian'),
    array('im im-icon-Lesbians' => 'Lesbians'),
    array('im im-icon-Letter-Close' => 'Letter-Close'),
    array('im im-icon-Letter-Open' => 'Letter-Open'),
    array('im im-icon-Letter-Sent' => 'Letter-Sent'),
    array('im im-icon-Libra-2' => 'Libra-2'),
    array('im im-icon-Libra' => 'Libra'),
    array('im im-icon-Library-2' => 'Library-2'),
    array('im im-icon-Library' => 'Library'),
    array('im im-icon-Life-Jacket' => 'Life-Jacket'),
    array('im im-icon-Life-Safer' => 'Life-Safer'),
    array('im im-icon-Light-Bulb' => 'Light-Bulb'),
    array('im im-icon-Light-Bulb2' => 'Light-Bulb2'),
    array('im im-icon-Light-BulbLeaf' => 'Light-BulbLeaf'),
    array('im im-icon-Lighthouse' => 'Lighthouse'),
    array('im im-icon-Like-2' => 'Like-2'),
    array('im im-icon-Like' => 'Like'),
    array('im im-icon-Line-Chart' => 'Line-Chart'),
    array('im im-icon-Line-Chart2' => 'Line-Chart2'),
    array('im im-icon-Line-Chart3' => 'Line-Chart3'),
    array('im im-icon-Line-Chart4' => 'Line-Chart4'),
    array('im im-icon-Line-Spacing' => 'Line-Spacing'),
    array('im im-icon-Line-SpacingText' => 'Line-SpacingText'),
    array('im im-icon-Link-2' => 'Link-2'),
    array('im im-icon-Link' => 'Link'),
    array('im im-icon-Linkedin-2' => 'Linkedin-2'),
    array('im im-icon-Linkedin' => 'Linkedin'),
    array('im im-icon-Linux' => 'Linux'),
    array('im im-icon-Lion' => 'Lion'),
    array('im im-icon-Livejournal' => 'Livejournal'),
    array('im im-icon-Loading-2' => 'Loading-2'),
    array('im im-icon-Loading-3' => 'Loading-3'),
    array('im im-icon-Loading-Window' => 'Loading-Window'),
    array('im im-icon-Loading' => 'Loading'),
    array('im im-icon-Location-2' => 'Location-2'),
    array('im im-icon-Location' => 'Location'),
    array('im im-icon-Lock-2' => 'Lock-2'),
    array('im im-icon-Lock-3' => 'Lock-3'),
    array('im im-icon-Lock-User' => 'Lock-User'),
    array('im im-icon-Lock-Window' => 'Lock-Window'),
    array('im im-icon-Lock' => 'Lock'),
    array('im im-icon-Lollipop-2' => 'Lollipop-2'),
    array('im im-icon-Lollipop-3' => 'Lollipop-3'),
    array('im im-icon-Lollipop' => 'Lollipop'),
    array('im im-icon-Loop' => 'Loop'),
    array('im im-icon-Loud' => 'Loud'),
    array('im im-icon-Loudspeaker' => 'Loudspeaker'),
    array('im im-icon-Love-2' => 'Love-2'),
    array('im im-icon-Love-User' => 'Love-User'),
    array('im im-icon-Love-Window' => 'Love-Window'),
    array('im im-icon-Love' => 'Love'),
    array('im im-icon-Lowercase-Text' => 'Lowercase-Text'),
    array('im im-icon-Luggafe-Front' => 'Luggafe-Front'),
    array('im im-icon-Luggage-2' => 'Luggage-2'),
    array('im im-icon-Macro' => 'Macro'),
    array('im im-icon-Magic-Wand' => 'Magic-Wand'),
    array('im im-icon-Magnet' => 'Magnet'),
    array('im im-icon-Magnifi-Glass-' => 'Magnifi-Glass-'),
    array('im im-icon-Magnifi-Glass' => 'Magnifi-Glass'),
    array('im im-icon-Magnifi-Glass2' => 'Magnifi-Glass2'),
    array('im im-icon-Mail-2' => 'Mail-2'),
    array('im im-icon-Mail-3' => 'Mail-3'),
    array('im im-icon-Mail-Add' => 'Mail-Add'),
    array('im im-icon-Mail-Attachement' => 'Mail-Attachement'),
    array('im im-icon-Mail-Block' => 'Mail-Block'),
    array('im im-icon-Mail-Delete' => 'Mail-Delete'),
    array('im im-icon-Mail-Favorite' => 'Mail-Favorite'),
    array('im im-icon-Mail-Forward' => 'Mail-Forward'),
    array('im im-icon-Mail-Gallery' => 'Mail-Gallery'),
    array('im im-icon-Mail-Inbox' => 'Mail-Inbox'),
    array('im im-icon-Mail-Link' => 'Mail-Link'),
    array('im im-icon-Mail-Lock' => 'Mail-Lock'),
    array('im im-icon-Mail-Love' => 'Mail-Love'),
    array('im im-icon-Mail-Money' => 'Mail-Money'),
    array('im im-icon-Mail-Open' => 'Mail-Open'),
    array('im im-icon-Mail-Outbox' => 'Mail-Outbox'),
    array('im im-icon-Mail-Password' => 'Mail-Password'),
    array('im im-icon-Mail-Photo' => 'Mail-Photo'),
    array('im im-icon-Mail-Read' => 'Mail-Read'),
    array('im im-icon-Mail-Removex' => 'Mail-Removex'),
    array('im im-icon-Mail-Reply' => 'Mail-Reply'),
    array('im im-icon-Mail-ReplyAll' => 'Mail-ReplyAll'),
    array('im im-icon-Mail-Search' => 'Mail-Search'),
    array('im im-icon-Mail-Send' => 'Mail-Send'),
    array('im im-icon-Mail-Settings' => 'Mail-Settings'),
    array('im im-icon-Mail-Unread' => 'Mail-Unread'),
    array('im im-icon-Mail-Video' => 'Mail-Video'),
    array('im im-icon-Mail-withAtSign' => 'Mail-withAtSign'),
    array('im im-icon-Mail-WithCursors' => 'Mail-WithCursors'),
    array('im im-icon-Mail' => 'Mail'),
    array('im im-icon-Mailbox-Empty' => 'Mailbox-Empty'),
    array('im im-icon-Mailbox-Full' => 'Mailbox-Full'),
    array('im im-icon-Male-2' => 'Male-2'),
    array('im im-icon-Male-Sign' => 'Male-Sign'),
    array('im im-icon-Male' => 'Male'),
    array('im im-icon-MaleFemale' => 'MaleFemale'),
    array('im im-icon-Man-Sign' => 'Man-Sign'),
    array('im im-icon-Management' => 'Management'),
    array('im im-icon-Mans-Underwear' => 'Mans-Underwear'),
    array('im im-icon-Mans-Underwear2' => 'Mans-Underwear2'),
    array('im im-icon-Map-Marker' => 'Map-Marker'),
    array('im im-icon-Map-Marker2' => 'Map-Marker2'),
    array('im im-icon-Map-Marker3' => 'Map-Marker3'),
    array('im im-icon-Map' => 'Map'),
    array('im im-icon-Map2' => 'Map2'),
    array('im im-icon-Marker-2' => 'Marker-2'),
    array('im im-icon-Marker-3' => 'Marker-3'),
    array('im im-icon-Marker' => 'Marker'),
    array('im im-icon-Martini-Glass' => 'Martini-Glass'),
    array('im im-icon-Mask' => 'Mask'),
    array('im im-icon-Master-Card' => 'Master-Card'),
    array('im im-icon-Maximize-Window' => 'Maximize-Window'),
    array('im im-icon-Maximize' => 'Maximize'),
    array('im im-icon-Medal-2' => 'Medal-2'),
    array('im im-icon-Medal-3' => 'Medal-3'),
    array('im im-icon-Medal' => 'Medal'),
    array('im im-icon-Medical-Sign' => 'Medical-Sign'),
    array('im im-icon-Medicine-2' => 'Medicine-2'),
    array('im im-icon-Medicine-3' => 'Medicine-3'),
    array('im im-icon-Medicine' => 'Medicine'),
    array('im im-icon-Megaphone' => 'Megaphone'),
    array('im im-icon-Memory-Card' => 'Memory-Card'),
    array('im im-icon-Memory-Card2' => 'Memory-Card2'),
    array('im im-icon-Memory-Card3' => 'Memory-Card3'),
    array('im im-icon-Men' => 'Men'),
    array('im im-icon-Menorah' => 'Menorah'),
    array('im im-icon-Mens' => 'Mens'),
    array('im im-icon-Metacafe' => 'Metacafe'),
    array('im im-icon-Mexico' => 'Mexico'),
    array('im im-icon-Mic' => 'Mic'),
    array('im im-icon-Microphone-2' => 'Microphone-2'),
    array('im im-icon-Microphone-3' => 'Microphone-3'),
    array('im im-icon-Microphone-4' => 'Microphone-4'),
    array('im im-icon-Microphone-5' => 'Microphone-5'),
    array('im im-icon-Microphone-6' => 'Microphone-6'),
    array('im im-icon-Microphone-7' => 'Microphone-7'),
    array('im im-icon-Microphone' => 'Microphone'),
    array('im im-icon-Microscope' => 'Microscope'),
    array('im im-icon-Milk-Bottle' => 'Milk-Bottle'),
    array('im im-icon-Mine' => 'Mine'),
    array('im im-icon-Minimize-Maximize-Close-Window' => 'Minimize-Maximize-Close-Window'),
    array('im im-icon-Minimize-Window' => 'Minimize-Window'),
    array('im im-icon-Minimize' => 'Minimize'),
    array('im im-icon-Mirror' => 'Mirror'),
    array('im im-icon-Mixer' => 'Mixer'),
    array('im im-icon-Mixx' => 'Mixx'),
    array('im im-icon-Money-2' => 'Money-2'),
    array('im im-icon-Money-Bag' => 'Money-Bag'),
    array('im im-icon-Money-Smiley' => 'Money-Smiley'),
    array('im im-icon-Money' => 'Money'),
    array('im im-icon-Monitor-2' => 'Monitor-2'),
    array('im im-icon-Monitor-3' => 'Monitor-3'),
    array('im im-icon-Monitor-4' => 'Monitor-4'),
    array('im im-icon-Monitor-5' => 'Monitor-5'),
    array('im im-icon-Monitor-Analytics' => 'Monitor-Analytics'),
    array('im im-icon-Monitor-Laptop' => 'Monitor-Laptop'),
    array('im im-icon-Monitor-phone' => 'Monitor-phone'),
    array('im im-icon-Monitor-Tablet' => 'Monitor-Tablet'),
    array('im im-icon-Monitor-Vertical' => 'Monitor-Vertical'),
    array('im im-icon-Monitor' => 'Monitor'),
    array('im im-icon-Monitoring' => 'Monitoring'),
    array('im im-icon-Monkey' => 'Monkey'),
    array('im im-icon-Monster' => 'Monster'),
    array('im im-icon-Morocco' => 'Morocco'),
    array('im im-icon-Motorcycle' => 'Motorcycle'),
    array('im im-icon-Mouse-2' => 'Mouse-2'),
    array('im im-icon-Mouse-3' => 'Mouse-3'),
    array('im im-icon-Mouse-4' => 'Mouse-4'),
    array('im im-icon-Mouse-Pointer' => 'Mouse-Pointer'),
    array('im im-icon-Mouse' => 'Mouse'),
    array('im im-icon-Moustache-Smiley' => 'Moustache-Smiley'),
    array('im im-icon-Movie-Ticket' => 'Movie-Ticket'),
    array('im im-icon-Movie' => 'Movie'),
    array('im im-icon-Mp3-File' => 'Mp3-File'),
    array('im im-icon-Museum' => 'Museum'),
    array('im im-icon-Mushroom' => 'Mushroom'),
    array('im im-icon-Music-Note' => 'Music-Note'),
    array('im im-icon-Music-Note2' => 'Music-Note2'),
    array('im im-icon-Music-Note3' => 'Music-Note3'),
    array('im im-icon-Music-Note4' => 'Music-Note4'),
    array('im im-icon-Music-Player' => 'Music-Player'),
    array('im im-icon-Mustache-2' => 'Mustache-2'),
    array('im im-icon-Mustache-3' => 'Mustache-3'),
    array('im im-icon-Mustache-4' => 'Mustache-4'),
    array('im im-icon-Mustache-5' => 'Mustache-5'),
    array('im im-icon-Mustache-6' => 'Mustache-6'),
    array('im im-icon-Mustache-7' => 'Mustache-7'),
    array('im im-icon-Mustache-8' => 'Mustache-8'),
    array('im im-icon-Mustache' => 'Mustache'),
    array('im im-icon-Mute' => 'Mute'),
    array('im im-icon-Myspace' => 'Myspace'),
    array('im im-icon-Navigat-Start' => 'Navigat-Start'),
    array('im im-icon-Navigate-End' => 'Navigate-End'),
    array('im im-icon-Navigation-LeftWindow' => 'Navigation-LeftWindow'),
    array('im im-icon-Navigation-RightWindow' => 'Navigation-RightWindow'),
    array('im im-icon-Nepal' => 'Nepal'),
    array('im im-icon-Netscape' => 'Netscape'),
    array('im im-icon-Network-Window' => 'Network-Window'),
    array('im im-icon-Network' => 'Network'),
    array('im im-icon-Neutron' => 'Neutron'),
    array('im im-icon-New-Mail' => 'New-Mail'),
    array('im im-icon-New-Tab' => 'New-Tab'),
    array('im im-icon-Newspaper-2' => 'Newspaper-2'),
    array('im im-icon-Newspaper' => 'Newspaper'),
    array('im im-icon-Newsvine' => 'Newsvine'),
    array('im im-icon-Next2' => 'Next2'),
    array('im im-icon-Next-3' => 'Next-3'),
    array('im im-icon-Next-Music' => 'Next-Music'),
    array('im im-icon-Next' => 'Next'),
    array('im im-icon-No-Battery' => 'No-Battery'),
    array('im im-icon-No-Drop' => 'No-Drop'),
    array('im im-icon-No-Flash' => 'No-Flash'),
    array('im im-icon-No-Smoking' => 'No-Smoking'),
    array('im im-icon-Noose' => 'Noose'),
    array('im im-icon-Normal-Text' => 'Normal-Text'),
    array('im im-icon-Note' => 'Note'),
    array('im im-icon-Notepad-2' => 'Notepad-2'),
    array('im im-icon-Notepad' => 'Notepad'),
    array('im im-icon-Nuclear' => 'Nuclear'),
    array('im im-icon-Numbering-List' => 'Numbering-List'),
    array('im im-icon-Nurse' => 'Nurse'),
    array('im im-icon-Office-Lamp' => 'Office-Lamp'),
    array('im im-icon-Office' => 'Office'),
    array('im im-icon-Oil' => 'Oil'),
    array('im im-icon-Old-Camera' => 'Old-Camera'),
    array('im im-icon-Old-Cassette' => 'Old-Cassette'),
    array('im im-icon-Old-Clock' => 'Old-Clock'),
    array('im im-icon-Old-Radio' => 'Old-Radio'),
    array('im im-icon-Old-Sticky' => 'Old-Sticky'),
    array('im im-icon-Old-Sticky2' => 'Old-Sticky2'),
    array('im im-icon-Old-Telephone' => 'Old-Telephone'),
    array('im im-icon-Old-TV' => 'Old-TV'),
    array('im im-icon-On-Air' => 'On-Air'),
    array('im im-icon-On-Off-2' => 'On-Off-2'),
    array('im im-icon-On-Off-3' => 'On-Off-3'),
    array('im im-icon-On-off' => 'On-off'),
    array('im im-icon-One-Finger' => 'One-Finger'),
    array('im im-icon-One-FingerTouch' => 'One-FingerTouch'),
    array('im im-icon-One-Window' => 'One-Window'),
    array('im im-icon-Open-Banana' => 'Open-Banana'),
    array('im im-icon-Open-Book' => 'Open-Book'),
    array('im im-icon-Opera-House' => 'Opera-House'),
    array('im im-icon-Opera' => 'Opera'),
    array('im im-icon-Optimization' => 'Optimization'),
    array('im im-icon-Orientation-2' => 'Orientation-2'),
    array('im im-icon-Orientation-3' => 'Orientation-3'),
    array('im im-icon-Orientation' => 'Orientation'),
    array('im im-icon-Orkut' => 'Orkut'),
    array('im im-icon-Ornament' => 'Ornament'),
    array('im im-icon-Over-Time' => 'Over-Time'),
    array('im im-icon-Over-Time2' => 'Over-Time2'),
    array('im im-icon-Owl' => 'Owl'),
    array('im im-icon-Pac-Man' => 'Pac-Man'),
    array('im im-icon-Paint-Brush' => 'Paint-Brush'),
    array('im im-icon-Paint-Bucket' => 'Paint-Bucket'),
    array('im im-icon-Paintbrush' => 'Paintbrush'),
    array('im im-icon-Palette' => 'Palette'),
    array('im im-icon-Palm-Tree' => 'Palm-Tree'),
    array('im im-icon-Panda' => 'Panda'),
    array('im im-icon-Panorama' => 'Panorama'),
    array('im im-icon-Pantheon' => 'Pantheon'),
    array('im im-icon-Pantone' => 'Pantone'),
    array('im im-icon-Pants' => 'Pants'),
    array('im im-icon-Paper-Plane' => 'Paper-Plane'),
    array('im im-icon-Paper' => 'Paper'),
    array('im im-icon-Parasailing' => 'Parasailing'),
    array('im im-icon-Parrot' => 'Parrot'),
    array('im im-icon-Password-2shopping' => 'Password-2shopping'),
    array('im im-icon-Password-Field' => 'Password-Field'),
    array('im im-icon-Password-shopping' => 'Password-shopping'),
    array('im im-icon-Password' => 'Password'),
    array('im im-icon-pause-2' => 'pause-2'),
    array('im im-icon-Pause' => 'Pause'),
    array('im im-icon-Paw' => 'Paw'),
    array('im im-icon-Pawn' => 'Pawn'),
    array('im im-icon-Paypal' => 'Paypal'),
    array('im im-icon-Pen-2' => 'Pen-2'),
    array('im im-icon-Pen-3' => 'Pen-3'),
    array('im im-icon-Pen-4' => 'Pen-4'),
    array('im im-icon-Pen-5' => 'Pen-5'),
    array('im im-icon-Pen-6' => 'Pen-6'),
    array('im im-icon-Pen' => 'Pen'),
    array('im im-icon-Pencil-Ruler' => 'Pencil-Ruler'),
    array('im im-icon-Pencil' => 'Pencil'),
    array('im im-icon-Penguin' => 'Penguin'),
    array('im im-icon-Pentagon' => 'Pentagon'),
    array('im im-icon-People-onCloud' => 'People-onCloud'),
    array('im im-icon-Pepper-withFire' => 'Pepper-withFire'),
    array('im im-icon-Pepper' => 'Pepper'),
    array('im im-icon-Petrol' => 'Petrol'),
    array('im im-icon-Petronas-Tower' => 'Petronas-Tower'),
    array('im im-icon-Philipines' => 'Philipines'),
    array('im im-icon-Phone-2' => 'Phone-2'),
    array('im im-icon-Phone-3' => 'Phone-3'),
    array('im im-icon-Phone-3G' => 'Phone-3G'),
    array('im im-icon-Phone-4G' => 'Phone-4G'),
    array('im im-icon-Phone-Simcard' => 'Phone-Simcard'),
    array('im im-icon-Phone-SMS' => 'Phone-SMS'),
    array('im im-icon-Phone-Wifi' => 'Phone-Wifi'),
    array('im im-icon-Phone' => 'Phone'),
    array('im im-icon-Photo-2' => 'Photo-2'),
    array('im im-icon-Photo-3' => 'Photo-3'),
    array('im im-icon-Photo-Album' => 'Photo-Album'),
    array('im im-icon-Photo-Album2' => 'Photo-Album2'),
    array('im im-icon-Photo-Album3' => 'Photo-Album3'),
    array('im im-icon-Photo' => 'Photo'),
    array('im im-icon-Photos' => 'Photos'),
    array('im im-icon-Physics' => 'Physics'),
    array('im im-icon-Pi' => 'Pi'),
    array('im im-icon-Piano' => 'Piano'),
    array('im im-icon-Picasa' => 'Picasa'),
    array('im im-icon-Pie-Chart' => 'Pie-Chart'),
    array('im im-icon-Pie-Chart2' => 'Pie-Chart2'),
    array('im im-icon-Pie-Chart3' => 'Pie-Chart3'),
    array('im im-icon-Pilates-2' => 'Pilates-2'),
    array('im im-icon-Pilates-3' => 'Pilates-3'),
    array('im im-icon-Pilates' => 'Pilates'),
    array('im im-icon-Pilot' => 'Pilot'),
    array('im im-icon-Pinch' => 'Pinch'),
    array('im im-icon-Ping-Pong' => 'Ping-Pong'),
    array('im im-icon-Pinterest' => 'Pinterest'),
    array('im im-icon-Pipe' => 'Pipe'),
    array('im im-icon-Pipette' => 'Pipette'),
    array('im im-icon-Piramids' => 'Piramids'),
    array('im im-icon-Pisces-2' => 'Pisces-2'),
    array('im im-icon-Pisces' => 'Pisces'),
    array('im im-icon-Pizza-Slice' => 'Pizza-Slice'),
    array('im im-icon-Pizza' => 'Pizza'),
    array('im im-icon-Plane-2' => 'Plane-2'),
    array('im im-icon-Plane' => 'Plane'),
    array('im im-icon-Plant' => 'Plant'),
    array('im im-icon-Plasmid' => 'Plasmid'),
    array('im im-icon-Plaster' => 'Plaster'),
    array('im im-icon-Plastic-CupPhone' => 'Plastic-CupPhone'),
    array('im im-icon-Plastic-CupPhone2' => 'Plastic-CupPhone2'),
    array('im im-icon-Plate' => 'Plate'),
    array('im im-icon-Plates' => 'Plates'),
    array('im im-icon-Plaxo' => 'Plaxo'),
    array('im im-icon-Play-Music' => 'Play-Music'),
    array('im im-icon-Plug-In' => 'Plug-In'),
    array('im im-icon-Plug-In2' => 'Plug-In2'),
    array('im im-icon-Plurk' => 'Plurk'),
    array('im im-icon-Pointer' => 'Pointer'),
    array('im im-icon-Poland' => 'Poland'),
    array('im im-icon-Police-Man' => 'Police-Man'),
    array('im im-icon-Police-Station' => 'Police-Station'),
    array('im im-icon-Police-Woman' => 'Police-Woman'),
    array('im im-icon-Police' => 'Police'),
    array('im im-icon-Polo-Shirt' => 'Polo-Shirt'),
    array('im im-icon-Portrait' => 'Portrait'),
    array('im im-icon-Portugal' => 'Portugal'),
    array('im im-icon-Post-Mail' => 'Post-Mail'),
    array('im im-icon-Post-Mail2' => 'Post-Mail2'),
    array('im im-icon-Post-Office' => 'Post-Office'),
    array('im im-icon-Post-Sign' => 'Post-Sign'),
    array('im im-icon-Post-Sign2ways' => 'Post-Sign2ways'),
    array('im im-icon-Posterous' => 'Posterous'),
    array('im im-icon-Pound-Sign' => 'Pound-Sign'),
    array('im im-icon-Pound-Sign2' => 'Pound-Sign2'),
    array('im im-icon-Pound' => 'Pound'),
    array('im im-icon-Power-2' => 'Power-2'),
    array('im im-icon-Power-3' => 'Power-3'),
    array('im im-icon-Power-Cable' => 'Power-Cable'),
    array('im im-icon-Power-Station' => 'Power-Station'),
    array('im im-icon-Power' => 'Power'),
    array('im im-icon-Prater' => 'Prater'),
    array('im im-icon-Present' => 'Present'),
    array('im im-icon-Presents' => 'Presents'),
    array('im im-icon-Press' => 'Press'),
    array('im im-icon-Preview' => 'Preview'),
    array('im im-icon-Previous' => 'Previous'),
    array('im im-icon-Pricing' => 'Pricing'),
    array('im im-icon-Printer' => 'Printer'),
    array('im im-icon-Professor' => 'Professor'),
    array('im im-icon-Profile' => 'Profile'),
    array('im im-icon-Project' => 'Project'),
    array('im im-icon-Projector-2' => 'Projector-2'),
    array('im im-icon-Projector' => 'Projector'),
    array('im im-icon-Pulse' => 'Pulse'),
    array('im im-icon-Pumpkin' => 'Pumpkin'),
    array('im im-icon-Punk' => 'Punk'),
    array('im im-icon-Punker' => 'Punker'),
    array('im im-icon-Puzzle' => 'Puzzle'),
    array('im im-icon-QIK' => 'QIK'),
    array('im im-icon-QR-Code' => 'QR-Code'),
    array('im im-icon-Queen-2' => 'Queen-2'),
    array('im im-icon-Queen' => 'Queen'),
    array('im im-icon-Quill-2' => 'Quill-2'),
    array('im im-icon-Quill-3' => 'Quill-3'),
    array('im im-icon-Quill' => 'Quill'),
    array('im im-icon-Quotes-2' => 'Quotes-2'),
    array('im im-icon-Quotes' => 'Quotes'),
    array('im im-icon-Radio' => 'Radio'),
    array('im im-icon-Radioactive' => 'Radioactive'),
    array('im im-icon-Rafting' => 'Rafting'),
    array('im im-icon-Rain-Drop' => 'Rain-Drop'),
    array('im im-icon-Rainbow-2' => 'Rainbow-2'),
    array('im im-icon-Rainbow' => 'Rainbow'),
    array('im im-icon-Ram' => 'Ram'),
    array('im im-icon-Razzor-Blade' => 'Razzor-Blade'),
    array('im im-icon-Receipt-2' => 'Receipt-2'),
    array('im im-icon-Receipt-3' => 'Receipt-3'),
    array('im im-icon-Receipt-4' => 'Receipt-4'),
    array('im im-icon-Receipt' => 'Receipt'),
    array('im im-icon-Record2' => 'Record2'),
    array('im im-icon-Record-3' => 'Record-3'),
    array('im im-icon-Record-Music' => 'Record-Music'),
    array('im im-icon-Record' => 'Record'),
    array('im im-icon-Recycling-2' => 'Recycling-2'),
    array('im im-icon-Recycling' => 'Recycling'),
    array('im im-icon-Reddit' => 'Reddit'),
    array('im im-icon-Redhat' => 'Redhat'),
    array('im im-icon-Redirect' => 'Redirect'),
    array('im im-icon-Redo' => 'Redo'),
    array('im im-icon-Reel' => 'Reel'),
    array('im im-icon-Refinery' => 'Refinery'),
    array('im im-icon-Refresh-Window' => 'Refresh-Window'),
    array('im im-icon-Refresh' => 'Refresh'),
    array('im im-icon-Reload-2' => 'Reload-2'),
    array('im im-icon-Reload-3' => 'Reload-3'),
    array('im im-icon-Reload' => 'Reload'),
    array('im im-icon-Remote-Controll' => 'Remote-Controll'),
    array('im im-icon-Remote-Controll2' => 'Remote-Controll2'),
    array('im im-icon-Remove-Bag' => 'Remove-Bag'),
    array('im im-icon-Remove-Basket' => 'Remove-Basket'),
    array('im im-icon-Remove-Cart' => 'Remove-Cart'),
    array('im im-icon-Remove-File' => 'Remove-File'),
    array('im im-icon-Remove-User' => 'Remove-User'),
    array('im im-icon-Remove-Window' => 'Remove-Window'),
    array('im im-icon-Remove' => 'Remove'),
    array('im im-icon-Rename' => 'Rename'),
    array('im im-icon-Repair' => 'Repair'),
    array('im im-icon-Repeat-2' => 'Repeat-2'),
    array('im im-icon-Repeat-3' => 'Repeat-3'),
    array('im im-icon-Repeat-4' => 'Repeat-4'),
    array('im im-icon-Repeat-5' => 'Repeat-5'),
    array('im im-icon-Repeat-6' => 'Repeat-6'),
    array('im im-icon-Repeat-7' => 'Repeat-7'),
    array('im im-icon-Repeat' => 'Repeat'),
    array('im im-icon-Reset' => 'Reset'),
    array('im im-icon-Resize' => 'Resize'),
    array('im im-icon-Restore-Window' => 'Restore-Window'),
    array('im im-icon-Retouching' => 'Retouching'),
    array('im im-icon-Retro-Camera' => 'Retro-Camera'),
    array('im im-icon-Retro' => 'Retro'),
    array('im im-icon-Retweet' => 'Retweet'),
    array('im im-icon-Reverbnation' => 'Reverbnation'),
    array('im im-icon-Rewind' => 'Rewind'),
    array('im im-icon-RGB' => 'RGB'),
    array('im im-icon-Ribbon-2' => 'Ribbon-2'),
    array('im im-icon-Ribbon-3' => 'Ribbon-3'),
    array('im im-icon-Ribbon' => 'Ribbon'),
    array('im im-icon-Right-2' => 'Right-2'),
    array('im im-icon-Right-3' => 'Right-3'),
    array('im im-icon-Right-4' => 'Right-4'),
    array('im im-icon-Right-ToLeft' => 'Right-ToLeft'),
    array('im im-icon-Right' => 'Right'),
    array('im im-icon-Road-2' => 'Road-2'),
    array('im im-icon-Road-3' => 'Road-3'),
    array('im im-icon-Road' => 'Road'),
    array('im im-icon-Robot-2' => 'Robot-2'),
    array('im im-icon-Robot' => 'Robot'),
    array('im im-icon-Rock-andRoll' => 'Rock-andRoll'),
    array('im im-icon-Rocket' => 'Rocket'),
    array('im im-icon-Roller' => 'Roller'),
    array('im im-icon-Roof' => 'Roof'),
    array('im im-icon-Rook' => 'Rook'),
    array('im im-icon-Rotate-Gesture' => 'Rotate-Gesture'),
    array('im im-icon-Rotate-Gesture2' => 'Rotate-Gesture2'),
    array('im im-icon-Rotate-Gesture3' => 'Rotate-Gesture3'),
    array('im im-icon-Rotation-390' => 'Rotation-390'),
    array('im im-icon-Rotation' => 'Rotation'),
    array('im im-icon-Router-2' => 'Router-2'),
    array('im im-icon-Router' => 'Router'),
    array('im im-icon-RSS' => 'RSS'),
    array('im im-icon-Ruler-2' => 'Ruler-2'),
    array('im im-icon-Ruler' => 'Ruler'),
    array('im im-icon-Running-Shoes' => 'Running-Shoes'),
    array('im im-icon-Running' => 'Running'),
    array('im im-icon-Safari' => 'Safari'),
    array('im im-icon-Safe-Box' => 'Safe-Box'),
    array('im im-icon-Safe-Box2' => 'Safe-Box2'),
    array('im im-icon-Safety-PinClose' => 'Safety-PinClose'),
    array('im im-icon-Safety-PinOpen' => 'Safety-PinOpen'),
    array('im im-icon-Sagittarus-2' => 'Sagittarus-2'),
    array('im im-icon-Sagittarus' => 'Sagittarus'),
    array('im im-icon-Sailing-Ship' => 'Sailing-Ship'),
    array('im im-icon-Sand-watch' => 'Sand-watch'),
    array('im im-icon-Sand-watch2' => 'Sand-watch2'),
    array('im im-icon-Santa-Claus' => 'Santa-Claus'),
    array('im im-icon-Santa-Claus2' => 'Santa-Claus2'),
    array('im im-icon-Santa-onSled' => 'Santa-onSled'),
    array('im im-icon-Satelite-2' => 'Satelite-2'),
    array('im im-icon-Satelite' => 'Satelite'),
    array('im im-icon-Save-Window' => 'Save-Window'),
    array('im im-icon-Save' => 'Save'),
    array('im im-icon-Saw' => 'Saw'),
    array('im im-icon-Saxophone' => 'Saxophone'),
    array('im im-icon-Scale' => 'Scale'),
    array('im im-icon-Scarf' => 'Scarf'),
    array('im im-icon-Scissor' => 'Scissor'),
    array('im im-icon-Scooter-Front' => 'Scooter-Front'),
    array('im im-icon-Scooter' => 'Scooter'),
    array('im im-icon-Scorpio-2' => 'Scorpio-2'),
    array('im im-icon-Scorpio' => 'Scorpio'),
    array('im im-icon-Scotland' => 'Scotland'),
    array('im im-icon-Screwdriver' => 'Screwdriver'),
    array('im im-icon-Scroll-Fast' => 'Scroll-Fast'),
    array('im im-icon-Scroll' => 'Scroll'),
    array('im im-icon-Scroller-2' => 'Scroller-2'),
    array('im im-icon-Scroller' => 'Scroller'),
    array('im im-icon-Sea-Dog' => 'Sea-Dog'),
    array('im im-icon-Search-onCloud' => 'Search-onCloud'),
    array('im im-icon-Search-People' => 'Search-People'),
    array('im im-icon-secound' => 'secound'),
    array('im im-icon-secound2' => 'secound2'),
    array('im im-icon-Security-Block' => 'Security-Block'),
    array('im im-icon-Security-Bug' => 'Security-Bug'),
    array('im im-icon-Security-Camera' => 'Security-Camera'),
    array('im im-icon-Security-Check' => 'Security-Check'),
    array('im im-icon-Security-Settings' => 'Security-Settings'),
    array('im im-icon-Security-Smiley' => 'Security-Smiley'),
    array('im im-icon-Securiy-Remove' => 'Securiy-Remove'),
    array('im im-icon-Seed' => 'Seed'),
    array('im im-icon-Selfie' => 'Selfie'),
    array('im im-icon-Serbia' => 'Serbia'),
    array('im im-icon-Server-2' => 'Server-2'),
    array('im im-icon-Server' => 'Server'),
    array('im im-icon-Servers' => 'Servers'),
    array('im im-icon-Settings-Window' => 'Settings-Window'),
    array('im im-icon-Sewing-Machine' => 'Sewing-Machine'),
    array('im im-icon-Sexual' => 'Sexual'),
    array('im im-icon-Share-onCloud' => 'Share-onCloud'),
    array('im im-icon-Share-Window' => 'Share-Window'),
    array('im im-icon-Share' => 'Share'),
    array('im im-icon-Sharethis' => 'Sharethis'),
    array('im im-icon-Shark' => 'Shark'),
    array('im im-icon-Sheep' => 'Sheep'),
    array('im im-icon-Sheriff-Badge' => 'Sheriff-Badge'),
    array('im im-icon-Shield' => 'Shield'),
    array('im im-icon-Ship-2' => 'Ship-2'),
    array('im im-icon-Ship' => 'Ship'),
    array('im im-icon-Shirt' => 'Shirt'),
    array('im im-icon-Shoes-2' => 'Shoes-2'),
    array('im im-icon-Shoes-3' => 'Shoes-3'),
    array('im im-icon-Shoes' => 'Shoes'),
    array('im im-icon-Shop-2' => 'Shop-2'),
    array('im im-icon-Shop-3' => 'Shop-3'),
    array('im im-icon-Shop-4' => 'Shop-4'),
    array('im im-icon-Shop' => 'Shop'),
    array('im im-icon-Shopping-Bag' => 'Shopping-Bag'),
    array('im im-icon-Shopping-Basket' => 'Shopping-Basket'),
    array('im im-icon-Shopping-Cart' => 'Shopping-Cart'),
    array('im im-icon-Short-Pants' => 'Short-Pants'),
    array('im im-icon-Shoutwire' => 'Shoutwire'),
    array('im im-icon-Shovel' => 'Shovel'),
    array('im im-icon-Shuffle-2' => 'Shuffle-2'),
    array('im im-icon-Shuffle-3' => 'Shuffle-3'),
    array('im im-icon-Shuffle-4' => 'Shuffle-4'),
    array('im im-icon-Shuffle' => 'Shuffle'),
    array('im im-icon-Shutter' => 'Shutter'),
    array('im im-icon-Sidebar-Window' => 'Sidebar-Window'),
    array('im im-icon-Signal' => 'Signal'),
    array('im im-icon-Singapore' => 'Singapore'),
    array('im im-icon-Skate-Shoes' => 'Skate-Shoes'),
    array('im im-icon-Skateboard-2' => 'Skateboard-2'),
    array('im im-icon-Skateboard' => 'Skateboard'),
    array('im im-icon-Skeleton' => 'Skeleton'),
    array('im im-icon-Ski' => 'Ski'),
    array('im im-icon-Skirt' => 'Skirt'),
    array('im im-icon-Skrill' => 'Skrill'),
    array('im im-icon-Skull' => 'Skull'),
    array('im im-icon-Skydiving' => 'Skydiving'),
    array('im im-icon-Skype' => 'Skype'),
    array('im im-icon-Sled-withGifts' => 'Sled-withGifts'),
    array('im im-icon-Sled' => 'Sled'),
    array('im im-icon-Sleeping' => 'Sleeping'),
    array('im im-icon-Sleet' => 'Sleet'),
    array('im im-icon-Slippers' => 'Slippers'),
    array('im im-icon-Smart' => 'Smart'),
    array('im im-icon-Smartphone-2' => 'Smartphone-2'),
    array('im im-icon-Smartphone-3' => 'Smartphone-3'),
    array('im im-icon-Smartphone-4' => 'Smartphone-4'),
    array('im im-icon-Smartphone-Secure' => 'Smartphone-Secure'),
    array('im im-icon-Smartphone' => 'Smartphone'),
    array('im im-icon-Smile' => 'Smile'),
    array('im im-icon-Smoking-Area' => 'Smoking-Area'),
    array('im im-icon-Smoking-Pipe' => 'Smoking-Pipe'),
    array('im im-icon-Snake' => 'Snake'),
    array('im im-icon-Snorkel' => 'Snorkel'),
    array('im im-icon-Snow-2' => 'Snow-2'),
    array('im im-icon-Snow-Dome' => 'Snow-Dome'),
    array('im im-icon-Snow-Storm' => 'Snow-Storm'),
    array('im im-icon-Snow' => 'Snow'),
    array('im im-icon-Snowflake-2' => 'Snowflake-2'),
    array('im im-icon-Snowflake-3' => 'Snowflake-3'),
    array('im im-icon-Snowflake-4' => 'Snowflake-4'),
    array('im im-icon-Snowflake' => 'Snowflake'),
    array('im im-icon-Snowman' => 'Snowman'),
    array('im im-icon-Soccer-Ball' => 'Soccer-Ball'),
    array('im im-icon-Soccer-Shoes' => 'Soccer-Shoes'),
    array('im im-icon-Socks' => 'Socks'),
    array('im im-icon-Solar' => 'Solar'),
    array('im im-icon-Sound-Wave' => 'Sound-Wave'),
    array('im im-icon-Sound' => 'Sound'),
    array('im im-icon-Soundcloud' => 'Soundcloud'),
    array('im im-icon-Soup' => 'Soup'),
    array('im im-icon-South-Africa' => 'South-Africa'),
    array('im im-icon-Space-Needle' => 'Space-Needle'),
    array('im im-icon-Spain' => 'Spain'),
    array('im im-icon-Spam-Mail' => 'Spam-Mail'),
    array('im im-icon-Speach-Bubble' => 'Speach-Bubble'),
    array('im im-icon-Speach-Bubble2' => 'Speach-Bubble2'),
    array('im im-icon-Speach-Bubble3' => 'Speach-Bubble3'),
    array('im im-icon-Speach-Bubble4' => 'Speach-Bubble4'),
    array('im im-icon-Speach-Bubble5' => 'Speach-Bubble5'),
    array('im im-icon-Speach-Bubble6' => 'Speach-Bubble6'),
    array('im im-icon-Speach-Bubble7' => 'Speach-Bubble7'),
    array('im im-icon-Speach-Bubble8' => 'Speach-Bubble8'),
    array('im im-icon-Speach-Bubble9' => 'Speach-Bubble9'),
    array('im im-icon-Speach-Bubble10' => 'Speach-Bubble10'),
    array('im im-icon-Speach-Bubble11' => 'Speach-Bubble11'),
    array('im im-icon-Speach-Bubble12' => 'Speach-Bubble12'),
    array('im im-icon-Speach-Bubble13' => 'Speach-Bubble13'),
    array('im im-icon-Speach-BubbleAsking' => 'Speach-BubbleAsking'),
    array('im im-icon-Speach-BubbleComic' => 'Speach-BubbleComic'),
    array('im im-icon-Speach-BubbleComic2' => 'Speach-BubbleComic2'),
    array('im im-icon-Speach-BubbleComic3' => 'Speach-BubbleComic3'),
    array('im im-icon-Speach-BubbleComic4' => 'Speach-BubbleComic4'),
    array('im im-icon-Speach-BubbleDialog' => 'Speach-BubbleDialog'),
    array('im im-icon-Speach-Bubbles' => 'Speach-Bubbles'),
    array('im im-icon-Speak-2' => 'Speak-2'),
    array('im im-icon-Speak' => 'Speak'),
    array('im im-icon-Speaker-2' => 'Speaker-2'),
    array('im im-icon-Speaker' => 'Speaker'),
    array('im im-icon-Spell-Check' => 'Spell-Check'),
    array('im im-icon-Spell-CheckABC' => 'Spell-CheckABC'),
    array('im im-icon-Spermium' => 'Spermium'),
    array('im im-icon-Spider' => 'Spider'),
    array('im im-icon-Spiderweb' => 'Spiderweb'),
    array('im im-icon-Split-FourSquareWindow' => 'Split-FourSquareWindow'),
    array('im im-icon-Split-Horizontal' => 'Split-Horizontal'),
    array('im im-icon-Split-Horizontal2Window' => 'Split-Horizontal2Window'),
    array('im im-icon-Split-Vertical' => 'Split-Vertical'),
    array('im im-icon-Split-Vertical2' => 'Split-Vertical2'),
    array('im im-icon-Split-Window' => 'Split-Window'),
    array('im im-icon-Spoder' => 'Spoder'),
    array('im im-icon-Spoon' => 'Spoon'),
    array('im im-icon-Sport-Mode' => 'Sport-Mode'),
    array('im im-icon-Sports-Clothings1' => 'Sports-Clothings1'),
    array('im im-icon-Sports-Clothings2' => 'Sports-Clothings2'),
    array('im im-icon-Sports-Shirt' => 'Sports-Shirt'),
    array('im im-icon-Spot' => 'Spot'),
    array('im im-icon-Spray' => 'Spray'),
    array('im im-icon-Spread' => 'Spread'),
    array('im im-icon-Spring' => 'Spring'),
    array('im im-icon-Spurl' => 'Spurl'),
    array('im im-icon-Spy' => 'Spy'),
    array('im im-icon-Squirrel' => 'Squirrel'),
    array('im im-icon-SSL' => 'SSL'),
    array('im im-icon-St-BasilsCathedral' => 'St-BasilsCathedral'),
    array('im im-icon-St-PaulsCathedral' => 'St-PaulsCathedral'),
    array('im im-icon-Stamp-2' => 'Stamp-2'),
    array('im im-icon-Stamp' => 'Stamp'),
    array('im im-icon-Stapler' => 'Stapler'),
    array('im im-icon-Star-Track' => 'Star-Track'),
    array('im im-icon-Star' => 'Star'),
    array('im im-icon-Starfish' => 'Starfish'),
    array('im im-icon-Start2' => 'Start2'),
    array('im im-icon-Start-3' => 'Start-3'),
    array('im im-icon-Start-ways' => 'Start-ways'),
    array('im im-icon-Start' => 'Start'),
    array('im im-icon-Statistic' => 'Statistic'),
    array('im im-icon-Stethoscope' => 'Stethoscope'),
    array('im im-icon-stop--2' => 'stop--2'),
    array('im im-icon-Stop-Music' => 'Stop-Music'),
    array('im im-icon-Stop' => 'Stop'),
    array('im im-icon-Stopwatch-2' => 'Stopwatch-2'),
    array('im im-icon-Stopwatch' => 'Stopwatch'),
    array('im im-icon-Storm' => 'Storm'),
    array('im im-icon-Street-View' => 'Street-View'),
    array('im im-icon-Street-View2' => 'Street-View2'),
    array('im im-icon-Strikethrough-Text' => 'Strikethrough-Text'),
    array('im im-icon-Stroller' => 'Stroller'),
    array('im im-icon-Structure' => 'Structure'),
    array('im im-icon-Student-Female' => 'Student-Female'),
    array('im im-icon-Student-Hat' => 'Student-Hat'),
    array('im im-icon-Student-Hat2' => 'Student-Hat2'),
    array('im im-icon-Student-Male' => 'Student-Male'),
    array('im im-icon-Student-MaleFemale' => 'Student-MaleFemale'),
    array('im im-icon-Students' => 'Students'),
    array('im im-icon-Studio-Flash' => 'Studio-Flash'),
    array('im im-icon-Studio-Lightbox' => 'Studio-Lightbox'),
    array('im im-icon-Stumbleupon' => 'Stumbleupon'),
    array('im im-icon-Suit' => 'Suit'),
    array('im im-icon-Suitcase' => 'Suitcase'),
    array('im im-icon-Sum-2' => 'Sum-2'),
    array('im im-icon-Sum' => 'Sum'),
    array('im im-icon-Summer' => 'Summer'),
    array('im im-icon-Sun-CloudyRain' => 'Sun-CloudyRain'),
    array('im im-icon-Sun' => 'Sun'),
    array('im im-icon-Sunglasses-2' => 'Sunglasses-2'),
    array('im im-icon-Sunglasses-3' => 'Sunglasses-3'),
    array('im im-icon-Sunglasses-Smiley' => 'Sunglasses-Smiley'),
    array('im im-icon-Sunglasses-Smiley2' => 'Sunglasses-Smiley2'),
    array('im im-icon-Sunglasses-W' => 'Sunglasses-W'),
    array('im im-icon-Sunglasses-W2' => 'Sunglasses-W2'),
    array('im im-icon-Sunglasses-W3' => 'Sunglasses-W3'),
    array('im im-icon-Sunglasses' => 'Sunglasses'),
    array('im im-icon-Sunrise' => 'Sunrise'),
    array('im im-icon-Sunset' => 'Sunset'),
    array('im im-icon-Superman' => 'Superman'),
    array('im im-icon-Support' => 'Support'),
    array('im im-icon-Surprise' => 'Surprise'),
    array('im im-icon-Sushi' => 'Sushi'),
    array('im im-icon-Sweden' => 'Sweden'),
    array('im im-icon-Swimming-Short' => 'Swimming-Short'),
    array('im im-icon-Swimming' => 'Swimming'),
    array('im im-icon-Swimmwear' => 'Swimmwear'),
    array('im im-icon-Switch' => 'Switch'),
    array('im im-icon-Switzerland' => 'Switzerland'),
    array('im im-icon-Sync-Cloud' => 'Sync-Cloud'),
    array('im im-icon-Sync' => 'Sync'),
    array('im im-icon-Synchronize-2' => 'Synchronize-2'),
    array('im im-icon-Synchronize' => 'Synchronize'),
    array('im im-icon-T-Shirt' => 'T-Shirt'),
    array('im im-icon-Tablet-2' => 'Tablet-2'),
    array('im im-icon-Tablet-3' => 'Tablet-3'),
    array('im im-icon-Tablet-Orientation' => 'Tablet-Orientation'),
    array('im im-icon-Tablet-Phone' => 'Tablet-Phone'),
    array('im im-icon-Tablet-Secure' => 'Tablet-Secure'),
    array('im im-icon-Tablet-Vertical' => 'Tablet-Vertical'),
    array('im im-icon-Tablet' => 'Tablet'),
    array('im im-icon-Tactic' => 'Tactic'),
    array('im im-icon-Tag-2' => 'Tag-2'),
    array('im im-icon-Tag-3' => 'Tag-3'),
    array('im im-icon-Tag-4' => 'Tag-4'),
    array('im im-icon-Tag-5' => 'Tag-5'),
    array('im im-icon-Tag' => 'Tag'),
    array('im im-icon-Taj-Mahal' => 'Taj-Mahal'),
    array('im im-icon-Talk-Man' => 'Talk-Man'),
    array('im im-icon-Tap' => 'Tap'),
    array('im im-icon-Target-Market' => 'Target-Market'),
    array('im im-icon-Target' => 'Target'),
    array('im im-icon-Taurus-2' => 'Taurus-2'),
    array('im im-icon-Taurus' => 'Taurus'),
    array('im im-icon-Taxi-2' => 'Taxi-2'),
    array('im im-icon-Taxi-Sign' => 'Taxi-Sign'),
    array('im im-icon-Taxi' => 'Taxi'),
    array('im im-icon-Teacher' => 'Teacher'),
    array('im im-icon-Teapot' => 'Teapot'),
    array('im im-icon-Technorati' => 'Technorati'),
    array('im im-icon-Teddy-Bear' => 'Teddy-Bear'),
    array('im im-icon-Tee-Mug' => 'Tee-Mug'),
    array('im im-icon-Telephone-2' => 'Telephone-2'),
    array('im im-icon-Telephone' => 'Telephone'),
    array('im im-icon-Telescope' => 'Telescope'),
    array('im im-icon-Temperature-2' => 'Temperature-2'),
    array('im im-icon-Temperature-3' => 'Temperature-3'),
    array('im im-icon-Temperature' => 'Temperature'),
    array('im im-icon-Temple' => 'Temple'),
    array('im im-icon-Tennis-Ball' => 'Tennis-Ball'),
    array('im im-icon-Tennis' => 'Tennis'),
    array('im im-icon-Tent' => 'Tent'),
    array('im im-icon-Test-Tube' => 'Test-Tube'),
    array('im im-icon-Test-Tube2' => 'Test-Tube2'),
    array('im im-icon-Testimonal' => 'Testimonal'),
    array('im im-icon-Text-Box' => 'Text-Box'),
    array('im im-icon-Text-Effect' => 'Text-Effect'),
    array('im im-icon-Text-HighlightColor' => 'Text-HighlightColor'),
    array('im im-icon-Text-Paragraph' => 'Text-Paragraph'),
    array('im im-icon-Thailand' => 'Thailand'),
    array('im im-icon-The-WhiteHouse' => 'The-WhiteHouse'),
    array('im im-icon-This-SideUp' => 'This-SideUp'),
    array('im im-icon-Thread' => 'Thread'),
    array('im im-icon-Three-ArrowFork' => 'Three-ArrowFork'),
    array('im im-icon-Three-Fingers' => 'Three-Fingers'),
    array('im im-icon-Three-FingersDrag' => 'Three-FingersDrag'),
    array('im im-icon-Three-FingersDrag2' => 'Three-FingersDrag2'),
    array('im im-icon-Three-FingersTouch' => 'Three-FingersTouch'),
    array('im im-icon-Thumb' => 'Thumb'),
    array('im im-icon-Thumbs-DownSmiley' => 'Thumbs-DownSmiley'),
    array('im im-icon-Thumbs-UpSmiley' => 'Thumbs-UpSmiley'),
    array('im im-icon-Thunder' => 'Thunder'),
    array('im im-icon-Thunderstorm' => 'Thunderstorm'),
    array('im im-icon-Ticket' => 'Ticket'),
    array('im im-icon-Tie-2' => 'Tie-2'),
    array('im im-icon-Tie-3' => 'Tie-3'),
    array('im im-icon-Tie-4' => 'Tie-4'),
    array('im im-icon-Tie' => 'Tie'),
    array('im im-icon-Tiger' => 'Tiger'),
    array('im im-icon-Time-Backup' => 'Time-Backup'),
    array('im im-icon-Time-Bomb' => 'Time-Bomb'),
    array('im im-icon-Time-Clock' => 'Time-Clock'),
    array('im im-icon-Time-Fire' => 'Time-Fire'),
    array('im im-icon-Time-Machine' => 'Time-Machine'),
    array('im im-icon-Time-Window' => 'Time-Window'),
    array('im im-icon-Timer-2' => 'Timer-2'),
    array('im im-icon-Timer' => 'Timer'),
    array('im im-icon-To-Bottom' => 'To-Bottom'),
    array('im im-icon-To-Bottom2' => 'To-Bottom2'),
    array('im im-icon-To-Left' => 'To-Left'),
    array('im im-icon-To-Right' => 'To-Right'),
    array('im im-icon-To-Top' => 'To-Top'),
    array('im im-icon-To-Top2' => 'To-Top2'),
    array('im im-icon-Token-' => 'Token-'),
    array('im im-icon-Tomato' => 'Tomato'),
    array('im im-icon-Tongue' => 'Tongue'),
    array('im im-icon-Tooth-2' => 'Tooth-2'),
    array('im im-icon-Tooth' => 'Tooth'),
    array('im im-icon-Top-ToBottom' => 'Top-ToBottom'),
    array('im im-icon-Touch-Window' => 'Touch-Window'),
    array('im im-icon-Tourch' => 'Tourch'),
    array('im im-icon-Tower-2' => 'Tower-2'),
    array('im im-icon-Tower-Bridge' => 'Tower-Bridge'),
    array('im im-icon-Tower' => 'Tower'),
    array('im im-icon-Trace' => 'Trace'),
    array('im im-icon-Tractor' => 'Tractor'),
    array('im im-icon-traffic-Light' => 'traffic-Light'),
    array('im im-icon-Traffic-Light2' => 'Traffic-Light2'),
    array('im im-icon-Train-2' => 'Train-2'),
    array('im im-icon-Train' => 'Train'),
    array('im im-icon-Tram' => 'Tram'),
    array('im im-icon-Transform-2' => 'Transform-2'),
    array('im im-icon-Transform-3' => 'Transform-3'),
    array('im im-icon-Transform-4' => 'Transform-4'),
    array('im im-icon-Transform' => 'Transform'),
    array('im im-icon-Trash-withMen' => 'Trash-withMen'),
    array('im im-icon-Tree-2' => 'Tree-2'),
    array('im im-icon-Tree-3' => 'Tree-3'),
    array('im im-icon-Tree-4' => 'Tree-4'),
    array('im im-icon-Tree-5' => 'Tree-5'),
    array('im im-icon-Tree' => 'Tree'),
    array('im im-icon-Trekking' => 'Trekking'),
    array('im im-icon-Triangle-ArrowDown' => 'Triangle-ArrowDown'),
    array('im im-icon-Triangle-ArrowLeft' => 'Triangle-ArrowLeft'),
    array('im im-icon-Triangle-ArrowRight' => 'Triangle-ArrowRight'),
    array('im im-icon-Triangle-ArrowUp' => 'Triangle-ArrowUp'),
    array('im im-icon-Tripod-2' => 'Tripod-2'),
    array('im im-icon-Tripod-andVideo' => 'Tripod-andVideo'),
    array('im im-icon-Tripod-withCamera' => 'Tripod-withCamera'),
    array('im im-icon-Tripod-withGopro' => 'Tripod-withGopro'),
    array('im im-icon-Trophy-2' => 'Trophy-2'),
    array('im im-icon-Trophy' => 'Trophy'),
    array('im im-icon-Truck' => 'Truck'),
    array('im im-icon-Trumpet' => 'Trumpet'),
    array('im im-icon-Tumblr' => 'Tumblr'),
    array('im im-icon-Turkey' => 'Turkey'),
    array('im im-icon-Turn-Down' => 'Turn-Down'),
    array('im im-icon-Turn-Down2' => 'Turn-Down2'),
    array('im im-icon-Turn-DownFromLeft' => 'Turn-DownFromLeft'),
    array('im im-icon-Turn-DownFromRight' => 'Turn-DownFromRight'),
    array('im im-icon-Turn-Left' => 'Turn-Left'),
    array('im im-icon-Turn-Left3' => 'Turn-Left3'),
    array('im im-icon-Turn-Right' => 'Turn-Right'),
    array('im im-icon-Turn-Right3' => 'Turn-Right3'),
    array('im im-icon-Turn-Up' => 'Turn-Up'),
    array('im im-icon-Turn-Up2' => 'Turn-Up2'),
    array('im im-icon-Turtle' => 'Turtle'),
    array('im im-icon-Tuxedo' => 'Tuxedo'),
    array('im im-icon-TV' => 'TV'),
    array('im im-icon-Twister' => 'Twister'),
    array('im im-icon-Twitter-2' => 'Twitter-2'),
    array('im im-icon-Twitter' => 'Twitter'),
    array('im im-icon-Two-Fingers' => 'Two-Fingers'),
    array('im im-icon-Two-FingersDrag' => 'Two-FingersDrag'),
    array('im im-icon-Two-FingersDrag2' => 'Two-FingersDrag2'),
    array('im im-icon-Two-FingersScroll' => 'Two-FingersScroll'),
    array('im im-icon-Two-FingersTouch' => 'Two-FingersTouch'),
    array('im im-icon-Two-Windows' => 'Two-Windows'),
    array('im im-icon-Type-Pass' => 'Type-Pass'),
    array('im im-icon-Ukraine' => 'Ukraine'),
    array('im im-icon-Umbrela' => 'Umbrela'),
    array('im im-icon-Umbrella-2' => 'Umbrella-2'),
    array('im im-icon-Umbrella-3' => 'Umbrella-3'),
    array('im im-icon-Under-LineText' => 'Under-LineText'),
    array('im im-icon-Undo' => 'Undo'),
    array('im im-icon-United-Kingdom' => 'United-Kingdom'),
    array('im im-icon-United-States' => 'United-States'),
    array('im im-icon-University-2' => 'University-2'),
    array('im im-icon-University' => 'University'),
    array('im im-icon-Unlike-2' => 'Unlike-2'),
    array('im im-icon-Unlike' => 'Unlike'),
    array('im im-icon-Unlock-2' => 'Unlock-2'),
    array('im im-icon-Unlock-3' => 'Unlock-3'),
    array('im im-icon-Unlock' => 'Unlock'),
    array('im im-icon-Up--Down' => 'Up--Down'),
    array('im im-icon-Up--Down3' => 'Up--Down3'),
    array('im im-icon-Up-2' => 'Up-2'),
    array('im im-icon-Up-3' => 'Up-3'),
    array('im im-icon-Up-4' => 'Up-4'),
    array('im im-icon-Up' => 'Up'),
    array('im im-icon-Upgrade' => 'Upgrade'),
    array('im im-icon-Upload-2' => 'Upload-2'),
    array('im im-icon-Upload-toCloud' => 'Upload-toCloud'),
    array('im im-icon-Upload-Window' => 'Upload-Window'),
    array('im im-icon-Upload' => 'Upload'),
    array('im im-icon-Uppercase-Text' => 'Uppercase-Text'),
    array('im im-icon-Upward' => 'Upward'),
    array('im im-icon-URL-Window' => 'URL-Window'),
    array('im im-icon-Usb-2' => 'Usb-2'),
    array('im im-icon-Usb-Cable' => 'Usb-Cable'),
    array('im im-icon-Usb' => 'Usb'),
    array('im im-icon-User' => 'User'),
    array('im im-icon-Ustream' => 'Ustream'),
    array('im im-icon-Vase' => 'Vase'),
    array('im im-icon-Vector-2' => 'Vector-2'),
    array('im im-icon-Vector-3' => 'Vector-3'),
    array('im im-icon-Vector-4' => 'Vector-4'),
    array('im im-icon-Vector-5' => 'Vector-5'),
    array('im im-icon-Vector' => 'Vector'),
    array('im im-icon-Venn-Diagram' => 'Venn-Diagram'),
    array('im im-icon-Vest-2' => 'Vest-2'),
    array('im im-icon-Vest' => 'Vest'),
    array('im im-icon-Viddler' => 'Viddler'),
    array('im im-icon-Video-2' => 'Video-2'),
    array('im im-icon-Video-3' => 'Video-3'),
    array('im im-icon-Video-4' => 'Video-4'),
    array('im im-icon-Video-5' => 'Video-5'),
    array('im im-icon-Video-6' => 'Video-6'),
    array('im im-icon-Video-GameController' => 'Video-GameController'),
    array('im im-icon-Video-Len' => 'Video-Len'),
    array('im im-icon-Video-Len2' => 'Video-Len2'),
    array('im im-icon-Video-Photographer' => 'Video-Photographer'),
    array('im im-icon-Video-Tripod' => 'Video-Tripod'),
    array('im im-icon-Video' => 'Video'),
    array('im im-icon-Vietnam' => 'Vietnam'),
    array('im im-icon-View-Height' => 'View-Height'),
    array('im im-icon-View-Width' => 'View-Width'),
    array('im im-icon-Vimeo' => 'Vimeo'),
    array('im im-icon-Virgo-2' => 'Virgo-2'),
    array('im im-icon-Virgo' => 'Virgo'),
    array('im im-icon-Virus-2' => 'Virus-2'),
    array('im im-icon-Virus-3' => 'Virus-3'),
    array('im im-icon-Virus' => 'Virus'),
    array('im im-icon-Visa' => 'Visa'),
    array('im im-icon-Voice' => 'Voice'),
    array('im im-icon-Voicemail' => 'Voicemail'),
    array('im im-icon-Volleyball' => 'Volleyball'),
    array('im im-icon-Volume-Down' => 'Volume-Down'),
    array('im im-icon-Volume-Up' => 'Volume-Up'),
    array('im im-icon-VPN' => 'VPN'),
    array('im im-icon-Wacom-Tablet' => 'Wacom-Tablet'),
    array('im im-icon-Waiter' => 'Waiter'),
    array('im im-icon-Walkie-Talkie' => 'Walkie-Talkie'),
    array('im im-icon-Wallet-2' => 'Wallet-2'),
    array('im im-icon-Wallet-3' => 'Wallet-3'),
    array('im im-icon-Wallet' => 'Wallet'),
    array('im im-icon-Warehouse' => 'Warehouse'),
    array('im im-icon-Warning-Window' => 'Warning-Window'),
    array('im im-icon-Watch-2' => 'Watch-2'),
    array('im im-icon-Watch-3' => 'Watch-3'),
    array('im im-icon-Watch' => 'Watch'),
    array('im im-icon-Wave-2' => 'Wave-2'),
    array('im im-icon-Wave' => 'Wave'),
    array('im im-icon-Webcam' => 'Webcam'),
    array('im im-icon-weight-Lift' => 'weight-Lift'),
    array('im im-icon-Wheelbarrow' => 'Wheelbarrow'),
    array('im im-icon-Wheelchair' => 'Wheelchair'),
    array('im im-icon-Width-Window' => 'Width-Window'),
    array('im im-icon-Wifi-2' => 'Wifi-2'),
    array('im im-icon-Wifi-Keyboard' => 'Wifi-Keyboard'),
    array('im im-icon-Wifi' => 'Wifi'),
    array('im im-icon-Wind-Turbine' => 'Wind-Turbine'),
    array('im im-icon-Windmill' => 'Windmill'),
    array('im im-icon-Window-2' => 'Window-2'),
    array('im im-icon-Window' => 'Window'),
    array('im im-icon-Windows-2' => 'Windows-2'),
    array('im im-icon-Windows-Microsoft' => 'Windows-Microsoft'),
    array('im im-icon-Windows' => 'Windows'),
    array('im im-icon-Windsock' => 'Windsock'),
    array('im im-icon-Windy' => 'Windy'),
    array('im im-icon-Wine-Bottle' => 'Wine-Bottle'),
    array('im im-icon-Wine-Glass' => 'Wine-Glass'),
    array('im im-icon-Wink' => 'Wink'),
    array('im im-icon-Winter-2' => 'Winter-2'),
    array('im im-icon-Winter' => 'Winter'),
    array('im im-icon-Wireless' => 'Wireless'),
    array('im im-icon-Witch-Hat' => 'Witch-Hat'),
    array('im im-icon-Witch' => 'Witch'),
    array('im im-icon-Wizard' => 'Wizard'),
    array('im im-icon-Wolf' => 'Wolf'),
    array('im im-icon-Woman-Sign' => 'Woman-Sign'),
    array('im im-icon-WomanMan' => 'WomanMan'),
    array('im im-icon-Womans-Underwear' => 'Womans-Underwear'),
    array('im im-icon-Womans-Underwear2' => 'Womans-Underwear2'),
    array('im im-icon-Women' => 'Women'),
    array('im im-icon-Wonder-Woman' => 'Wonder-Woman'),
    array('im im-icon-Wordpress' => 'Wordpress'),
    array('im im-icon-Worker-Clothes' => 'Worker-Clothes'),
    array('im im-icon-Worker' => 'Worker'),
    array('im im-icon-Wrap-Text' => 'Wrap-Text'),
    array('im im-icon-Wreath' => 'Wreath'),
    array('im im-icon-Wrench' => 'Wrench'),
    array('im im-icon-X-Box' => 'X-Box'),
    array('im im-icon-X-ray' => 'X-ray'),
    array('im im-icon-Xanga' => 'Xanga'),
    array('im im-icon-Xing' => 'Xing'),
    array('im im-im im-icon-Yacht' => 'Yacht'),
    array('im im-icon-Yahoo-Buzz' => 'Yahoo-Buzz'),
    array('im im-icon-Yahoo' => 'Yahoo'),
    array('im im-icon-Yelp' => 'Yelp'),
    array('im im-icon-Yes' => 'Yes'),
    array('im im-icon-Ying-Yang' => 'Ying-Yang'),
    array('im im-icon-Youtube' => 'Youtube'),
    array('im im-icon-Z-A' => 'Z-A'),
    array('im im-icon-Zebra' => 'Zebra'),
    array('im im-icon-Zombie' => 'Zombie'),
    array('im im-icon-Zoom-Gesture' => 'Zoom-Gesture'),
    array('im im-icon-Zootool' => 'Zootool'),
  );

  return array_merge($icons, $iconsmind_icons);
}



// class ThzelGetElementSettings {


//   public function __construct( $postid, $widget_type ) {


//     $this->postid     = $postid;
//     $this->widget_type  = $widget_type;
//     $this->widget     = null;

//     $this->parse();

//   }


//   public function elementor(){

//     return  \Elementor\Plugin::$instance;

//   }

//   public function get_settings () {
//     if( $this->widget ){
//       $widget = $this->elementor()->elements_manager->create_element_instance( $this->widget );

//       return $widget->get_settings_for_display();  
//     } else {
//       return;
//     }

//   }

//   private function parse() {

//     $data = $this->read_data();

//     $this->parse_options($data);

//   }

//   private function read_data () {

//     return $this->elementor()->documents->get( $this->postid )->get_elements_data();

//   }

//   private function parse_options($data) {

//     if(!is_array($data) || empty($data)){
//       return;
//     }   

//     foreach ( $data as $item ) {

//       if(empty($item)){
//         continue;
//       }

//       if ( 'section' === $item['elType'] || 'column' === $item['elType']) {

//         $this->parse_options($item['elements']);

//       } else {

//         $this->parse_options_simple($item);
//       }
//     }
//   }

//   private function parse_options_simple($item) {

//     if (

//       $item['widgetType'] === $this->widget_type

//     ) {
//       $this->widget = $item;
//     }
//   }
// }

// This filter allow a wp_dropdown_categories select to return multiple items
add_filter('wp_dropdown_cats', 'willy_wp_dropdown_cats_multiple', 10, 2);
function willy_wp_dropdown_cats_multiple($output, $r)
{
  if (!empty($r['multiple'])) {
    $output = preg_replace('/<select(.*?)>/i', '<select$1 multiple="multiple">', $output);
    $output = preg_replace('/name=([\'"]{1})(.*?)\1/i', 'name=$2[]', $output);
  }
  return $output;
}

// This Walker is needed to match more than one selected value
class Willy_Walker_CategoryDropdown extends Walker_CategoryDropdown
{
  public function start_el(&$output, $category, $depth = 0, $args = array(), $id = 0)
  {
    $pad = str_repeat('&nbsp;', $depth * 3);

    /** This filter is documented in wp-includes/category-template.php */
    $cat_name = apply_filters('list_cats', $category->name, $category);

    if (isset($args['value_field']) && isset($category->{$args['value_field']})) {
      $value_field = $args['value_field'];
    } else {
      $value_field = 'term_id';
    }

    $output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr($category->{$value_field}) . "\"";

    // Type-juggling causes false matches, so we force everything to a string.
    if (in_array($category->{$value_field}, (array)$args['selected'], true))
      $output .= ' selected="selected"';
    $output .= '>';
    $output .= $pad . $cat_name;
    if ($args['show_count'])
      $output .= '&nbsp;&nbsp;(' . number_format_i18n($category->count) . ')';
    $output .= "</option>\n";
  }
}
