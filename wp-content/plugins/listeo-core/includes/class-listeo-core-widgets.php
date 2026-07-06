<?php

if (!defined('ABSPATH')) exit;

/**
 * Listeo Core Widget base
 */
class Listeo_Core_Widget extends WP_Widget
{
	/**
	 * Widget CSS class
	 *
	 * @access public
	 * @var string
	 */
	public $widget_cssclass;

	/**
	 * Widget description
	 *
	 * @access public
	 * @var string
	 */
	public $widget_description;

	/**
	 * Widget id
	 *
	 * @access public
	 * @var string
	 */
	public $widget_id;

	/**
	 * Widget name
	 *
	 * @access public
	 * @var string
	 */
	public $widget_name;

	/**
	 * Widget settings
	 *
	 * @access public
	 * @var array
	 */
	public $settings;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->register();
	}


	/**
	 * Register Widget
	 */
	public function register()
	{
		$widget_ops = array(
			'classname'   => $this->widget_cssclass,
			'description' => $this->widget_description
		);

		parent::__construct($this->widget_id, $this->widget_name, $widget_ops);

		add_action('save_post', array($this, 'flush_widget_cache'));
		add_action('deleted_post', array($this, 'flush_widget_cache'));
		add_action('switch_theme', array($this, 'flush_widget_cache'));
	}



	/**
	 * get_cached_widget function.
	 */
	public function get_cached_widget($args)
	{

		return false;

		$cache = wp_cache_get($this->widget_id, 'widget');

		if (!is_array($cache))
			$cache = array();

		if (isset($cache[$args['widget_id']])) {
			echo $cache[$args['widget_id']];
			return true;
		}

		return false;
	}

	/**
	 * Cache the widget
	 */
	public function cache_widget($args, $content)
	{
		$cache[$args['widget_id']] = $content;

		wp_cache_set($this->widget_id, $cache, 'widget');
	}

	/**
	 * Flush the cache
	 * @return [type]
	 */
	public function flush_widget_cache()
	{
		wp_cache_delete($this->widget_id, 'widget');
	}

	/**
	 * update function.
	 *
	 * @see WP_Widget->update
	 * @access public
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	public function update($new_instance, $old_instance)
	{
		$instance = $old_instance;

		if (!$this->settings)
			return $instance;

		foreach ($this->settings as $key => $setting) {
			$instance[$key] = sanitize_text_field($new_instance[$key]);
		}

		$this->flush_widget_cache();

		return $instance;
	}

	/**
	 * form function.
	 *
	 * @see WP_Widget->form
	 * @access public
	 * @param array $instance
	 * @return void
	 */
	function form($instance)
	{

		if (!$this->settings)
			return;

		foreach ($this->settings as $key => $setting) {

			$value = isset($instance[$key]) ? $instance[$key] : $setting['std'];

			switch ($setting['type']) {
				case 'text':
?>
					<p>
						<label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>" type="text" value="<?php echo esc_attr($value); ?>" />
					</p>
				<?php
					break;
				case 'checkbox':
				?>
					<p>
						<label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>" type="checkbox" <?php checked(esc_attr($value), 'on'); ?> />
					</p>
				<?php
					break;
				case 'number':
				?>
					<p>
						<label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>" type="number" step="<?php echo esc_attr($setting['step']); ?>" min="<?php echo esc_attr($setting['min']); ?>" max="<?php echo esc_attr($setting['max']); ?>" value="<?php echo esc_attr($value); ?>" />
					</p>
				<?php
					break;
				case 'dropdown':
				?>
					<p>
						<label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
						<select class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>">

							<?php foreach ($setting['options'] as $key => $option_value) { ?>
								<option <?php selected($value, $key); ?> value="<?php echo esc_attr($key); ?>"><?php echo esc_attr($option_value); ?></option>
							<?php } ?>
						</select>

					</p>
			<?php
					break;
			}
		}
	}

	/**
	 * widget function.
	 *
	 * @see    WP_Widget
	 * @access public
	 *
	 * @param array $args
	 * @param array $instance
	 *
	 * @return void
	 */
	public function widget($args, $instance) {}
}


/**
 * Featured listings Widget
 */
class Listeo_Core_Featured_Properties extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $wp_post_types;

		$this->widget_cssclass    = 'listeo_core widget_featured_listings';
		$this->widget_description = __('Display a list of featured listings on your site.', 'listeo_core');
		$this->widget_id          = 'widget_featured_listings';
		$this->widget_name        =  __('Listeo Featured Listings', 'listeo_core');
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('Featured Properties', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),
			'number' => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __('Number of listings to show', 'listeo_core')
			)
		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{


		ob_start();

		extract($args);

		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		$number = absint($instance['number']);
		$listings   = new WP_Query(array(
			'posts_per_page' => $number,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_type' 	 => 'listing',
			'meta_query'     =>  array(
				array(
					'key'     => '_featured',
					'value'   => 'on',
					'compare' => '=',
				),
				//array('key' => '_thumbnail_id')
			)
		));

		$template_loader = new Listeo_Core_Template_Loader;
		if ($listings->have_posts()) : ?>

			<?php echo $before_widget; ?>

			<?php if ($title) echo $before_title . $title . $after_title; ?>

			<div class="widget-listing-slider dots-nav" data-slick='{"autoplay": true, "autoplaySpeed":3000}'>
				<?php while ($listings->have_posts()) : $listings->the_post(); ?>
					<div class="fw-carousel-item">
						<?php
						//     $template_loader->get_template_part( 'content-listing-compact' );  
						$template_loader->get_template_part('content-listing');
						?>
					</div>
				<?php endwhile; ?>
			</div>

			<?php echo $after_widget; ?>

		<?php else : ?>

			<?php $template_loader->get_template_part('listing-widget', 'no-content'); ?>

		<?php endif;

		wp_reset_postdata();

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget($args, $content);
	}
}


/**
 * Save & Print listings Widget
 */
class Listeo_Core_Bookmarks_Share_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $wp_post_types;

		$this->widget_cssclass    = 'listeo_core widget_buttons';
		$this->widget_description = __('Display a Bookmarks and share buttons.', 'listeo_core');
		$this->widget_id          = 'widget_buttons_listings';
		$this->widget_name        =  __('Listeo Bookmarks & Share', 'listeo_core');
		$this->settings           = array(
			'bookmarks' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('Bookmark button', 'listeo_core')
			),
			'share' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('Enable Share buttons', 'listeo_core')
			),
			'share_facebook' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('Facebook', 'listeo_core')
			),
			'share_twitter' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('Twitter/X', 'listeo_core')
			),
			'share_linkedin' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('LinkedIn', 'listeo_core')
			),
			'share_pinterest' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('Pinterest', 'listeo_core')
			),
			'share_whatsapp' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('WhatsApp', 'listeo_core')
			),
			'share_email' => array(
				'type'  => 'checkbox',
				'std'	=> 'on',
				'label' => __('Email', 'listeo_core')
			),

		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{
		if ($this->get_cached_widget($args)) {
			return;
		}



		extract($args);

		global $post;
		if (is_null($post)) {
			return;
		}
		$share = (isset($instance['share'])) ? $instance['share'] : '';
		$bookmarks = (isset($instance['bookmarks'])) ? $instance['bookmarks'] : '';

		// Get individual share button settings
		$share_facebook = (isset($instance['share_facebook'])) ? $instance['share_facebook'] : 'on';
		$share_twitter = (isset($instance['share_twitter'])) ? $instance['share_twitter'] : 'on';
		$share_linkedin = (isset($instance['share_linkedin'])) ? $instance['share_linkedin'] : 'on';
		$share_pinterest = (isset($instance['share_pinterest'])) ? $instance['share_pinterest'] : 'on';
		$share_whatsapp = (isset($instance['share_whatsapp'])) ? $instance['share_whatsapp'] : 'on';
		$share_email = (isset($instance['share_email'])) ? $instance['share_email'] : 'on';
		ob_start();
		echo $before_widget;

		?>
		<div class="listing-share margin-top-40 margin-bottom-40 no-border">

			<?php
			if (!empty($bookmarks)) :

				$nonce = wp_create_nonce("listeo_core_bookmark_this_nonce");

				$classObj = new Listeo_Core_Bookmarks;

				if ($classObj->check_if_added($post->ID)) { ?>
					<button onclick="window.location.href='<?php echo get_permalink(get_option('listeo_bookmarks_page')) ?>'" class="like-button save liked"><span class="like-icon liked"></span> <?php esc_html_e('Bookmarked', 'listeo_core') ?>
					</button>
					<?php } else {
					if (is_user_logged_in()) { ?>
						<button class="like-button listeo_core-bookmark-it" data-post_id="<?php echo esc_attr($post->ID); ?>" data-confirm="<?php esc_html_e('Bookmarked!', 'listeo_core'); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><span class="like-icon"></span> <?php esc_html_e('Bookmark this listing', 'listeo_core') ?>
						</button>
						<?php } else {
						$popup_login = get_option('listeo_popup_login', 'ajax');
						if ($popup_login == 'ajax') { ?>
							<button href="#sign-in-dialog" class="like-button-notlogged sign-in popup-with-zoom-anim"><span class="like-icon"></span> <?php esc_html_e('Login To Bookmark Items', 'listeo_core') ?></button>
						<?php } else {
							$login_page = get_option('listeo_profile_page'); ?>
							<?php $current_listing_url = get_permalink();
							$login_url = add_query_arg('redirect_to', urlencode($current_listing_url), get_permalink($login_page)); ?><a href="<?php echo esc_url($login_url); ?>" class="like-button-notlogged"><span class="like-icon"></span> <?php esc_html_e('Login To Bookmark Items', 'listeo_core') ?></a>
						<?php } ?>
					<?php } ?>

				<?php }

				$count = get_post_meta($post->ID, 'bookmarks_counter', true);
				if ($count) :
					if ($count < 0) {
						$count = 0;
					} ?>
					<span id="bookmarks-counter"><?php printf(_n('%s person bookmarked this listing', '%s people bookmarked this listing', $count, 'listeo_core'), number_format_i18n($count)); ?> </span>
				<?php endif; ?>
			<?php
			endif;
			if (!empty($share)) :
				$id = $post->ID;
				$title = urlencode($post->post_title);
				$url =  urlencode(get_permalink($id));
				$summary = urlencode(listeo_string_limit_words($post->post_excerpt, 20));
				$thumb = wp_get_attachment_image_src(get_post_thumbnail_id($id), 'medium');
				if ($thumb) {
					$imageurl = urlencode($thumb[0]);
				} else {
					$imageurl = false;
				}

			?>
				<ul class="share-buttons margin-bottom-0">
					<?php if (!empty($share_facebook)) : ?>
						<li><a target="_blank" class="fb-share" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>"><i class="fa fa-facebook"></i> <?php esc_html_e('Share', 'listeo_core'); ?></a></li>
					<?php endif; ?>

					<?php if (!empty($share_twitter)) : ?>
						<li><a target="_blank" class="twitter-share" href="https://twitter.com/share?url=<?php echo $url; ?>&amp;text=<?php echo esc_attr($summary); ?>"><i class="fa-brands fa-x-twitter"></i> <?php esc_html_e('Share', 'listeo_core'); ?></a></li>
					<?php endif; ?>

					<?php if (!empty($share_linkedin)) : ?>
						<li><a target="_blank" class="linkedin-share" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $url; ?>&title=<?php echo $title; ?>&summary=<?php echo $summary; ?>"><i class="fa fa-linkedin"></i> <?php esc_html_e('Share', 'listeo_core'); ?></a></li>
					<?php endif; ?>

					<?php if (!empty($share_whatsapp)) : ?>
						<li><a target="_blank" class="whatsapp-share" href="https://wa.me/?text=<?php echo $title; ?>%20<?php echo $url; ?>"><i class="fa fa-whatsapp"></i> <?php esc_html_e('Share', 'listeo_core'); ?></a></li>
					<?php endif; ?>

					<?php if (!empty($share_pinterest)) : ?>
						<li><a target="_blank" class="pinterest-share" href="http://pinterest.com/pin/create/button/?url=<?php echo $url; ?>&amp;description=<?php echo esc_attr($summary); ?>&media=<?php echo esc_attr($imageurl); ?>" onclick="window.open(this.href); return false;"><i class="fa fa-pinterest-p"></i> <?php esc_html_e('Pin It', 'listeo_core'); ?></a></li>
					<?php endif; ?>

					<?php if (!empty($share_email)) : ?>
						<li><a class="email-share" href="mailto:?subject=<?php echo $title; ?>&body=<?php echo esc_attr($summary); ?>%0A%0A<?php echo $url; ?>"><i class="fa fa-envelope"></i> <?php esc_html_e('Email', 'listeo_core'); ?></a></li>
					<?php endif; ?>
				</ul>

				<div class="clearfix"></div>

			<?php endif;
			?>
		</div>
	<?php
		echo $after_widget;

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget($args, $content);
	}
}


/**
 * Featured listings Widget
 */
class Listeo_Core_Contact_Vendor_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $wp_post_types;

		$this->widget_cssclass    = 'listeo_core  boxed-widget message-vendor ';
		$this->widget_description = __('Display a Contact form.', 'listeo_core');
		$this->widget_id          = 'widget_contact_widget_listeo';
		$this->widget_name        =  __('Listeo Contact Widget', 'listeo_core');
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('Message Vendor', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),

			'contact' => array(
				'type'  => 'dropdown',
				'std'	=> '',
				'options' => $this->get_forms(),
				'label' => __('Choose contact form', 'listeo_core')
			),
			'only_verified' => array(
				'type'  => 'checkbox',
				'std'   => 'off',
				'label' => __('Show Contact form only on verified listings', 'listeo_core')
			),
		);
		$this->register();

		//add_filter( 'wpcf7_mail_components', array( $this, 'set_question_form_recipient' ), 10, 3 );

	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{


		$queried_object = get_queried_object();
		if ($queried_object) {
			$post_id = $queried_object->ID;
		} else {
			return;
		}
		$contact_enabled = get_post_meta($post_id, '_email_contact_widget', true);
		// apply filter to enable contact form
		$contact_enabled = apply_filters('listeo_core_contact_widget_enabled', $contact_enabled, $post_id);
		if (!$contact_enabled) {
			return;
		}

		if (isset($instance['only_verified']) && $instance['only_verified'] == 'on') {
			$verified = get_post_meta($post_id, '_verified', true);
			if (!$verified) {
				return;
			}
		}

		ob_start();

		extract($args);

		echo $before_widget;
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);

	?>
		<h3><i class="fa fa-envelope-o"></i> <?php echo $title ?></h3>
		<div class="row with-forms  margin-top-0">
			<?php
			if (get_post($instance['contact'])) {
				echo do_shortcode(sprintf('[contact-form-7 id="%s"]', $instance['contact']));
			} else {
				echo 'Please choose "Contact Owner Widget" form in Appearance  → Widgets  (Single Listing Sidebar  → Listeo Contact Widget)';
				echo ' <a href="http://www.docs.purethemes.net/listeo/knowledge-base/how-to-configure-message-vendor-form/">More information.</a>';
			} ?>
		</div>

		<!-- Agent Widget / End -->
	<?php

		echo $after_widget;

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget($args, $content);
	}

	public function get_forms()
	{
		$forms  = array(0 => __('Please select a form', 'listeo_core'));

		$_forms = get_posts(
			array(
				'numberposts' => -1,
				'post_type'   => 'wpcf7_contact_form',
			)
		);

		if (!empty($_forms)) {

			foreach ($_forms as $_form) {
				$forms[$_form->ID] = $_form->post_title;
			}
		}

		return $forms;
	}
}




/**
 * Save & Print listings Widget
 */
class Listeo_Core_Search_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $wp_post_types;

		$this->widget_cssclass    = 'listeo_core widget_buttons';
		$this->widget_description = __('Display a Advanced Search Form.', 'listeo_core');
		$this->widget_id          = 'widget_search_form_listings';
		$this->widget_name        =  __('Listeo Search Form', 'listeo_core');
		if (function_exists('listeo_get_search_forms_dropdown')) {
			$search_forms = listeo_get_search_forms_dropdown('sidebar');
		} else {
			$search_forms = array();
		}

		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('Find New Home', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),
			'source' => array(
				'type'  => 'dropdown',
				'std'	=> 'sidebar',
				'options' => $search_forms,
				'label' => __('Choose search form', 'listeo_core')
			),
			'action' => array(
				'type'  => 'dropdown',
				'std'	=> 'archive',
				'options' => array(
					'current_page' => __('Redirect to current page', 'listeo_core'),
					'archive' => __('Redirect to listings archive page', 'listeo_core'),
				),
				'label' => __('Choose form action', 'listeo_core')
			),

		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{
		if ($this->get_cached_widget($args)) {
			return;
		}


		extract($args);

		echo $before_widget;
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		if (isset($instance['action'])) {
			$action  = apply_filters('listeo_core_search_widget_action', $instance['action'], $instance, $this->id_base);
		}


		if ($title) {
			echo $before_title . $title;
			//if(isset($_GET['keyword_search'])) : echo '<a id="listeo_core_reset_filters" href="#">'.esc_html__('Reset Filters','listeo_core').'</a>'; endif;
			echo $after_title;
		}
		$dynamic =  (get_option('listeo_dynamic_features') == "on") ? "on" : "off";
		if (isset($instance['source']) && !empty($instance['source'])) {
			$source = $instance['source'];
		} else {
			$source = 'sidebar_search';
		}

		if (is_tax()) {
			// check if it has a search form
			$search_form = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_search_form', true);
			$top_layout = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_top_layout', true);


			if (!empty($search_form)) {
				// Get compatible search form for the layout (auto-switch if incompatible)

				$source = listeo_get_compatible_search_form_for_layout($search_form, $top_layout);
			}
			///
		}

		if (isset($action) && $action == 'archive') {
			echo do_shortcode('[listeo_search_form  source="' . $source . '" dynamic_filters="' . $dynamic . '" 	more_text_open="' . esc_html__('More Filters', 'listeo_core') . '" more_text_close="' . esc_html__('Close Filters', 'listeo_core') . '" ajax_browsing="false" action=' . get_post_type_archive_link('listing') . ']');
		} else {
			echo do_shortcode('[listeo_search_form  source="' . $source . '"  dynamic_filters="' . $dynamic . '" more_text_close="' . esc_html__('Close Filters', 'listeo_core') . '" more_text_open="' . esc_html__('More Filters', 'listeo_core') . '"]');
		}

		echo $after_widget;
	}
}

class Listeo_Core_External_Booking_Widget extends Listeo_Core_Widget
{
	public function __construct()
	{

		// create object responsible for bookings
		$this->bookings = new Listeo_Core_Bookings_Calendar;

		$this->widget_cssclass    = 'listeo_core boxed-widget booking-external-widget margin-bottom-35';
		$this->widget_description = __('Shows Booking Button for external site.', 'listeo_core');
		$this->widget_id          = 'widget_external_booking_listings';
		$this->widget_name        =  __('Listeo External Booking', 'listeo_core');
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('Booking', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),
			'btn' => array(
				'type'  => 'text',
				'std'   => __('Book Now', 'listeo_core'),
				'label' => __('Button Label', 'listeo_core')
			),
			'new_window' => array(
				'type'  => 'checkbox',
				'std'   => 'off',
				'label' => __('Open link in new tab', 'listeo_core')
			),


		);
		$this->register();
	}

	public function widget($args, $instance)
	{





		extract($args);
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		$btn  = $instance['btn'];
		$new_window  = $instance['new_window'];
		$queried_object = get_queried_object();
		if ($queried_object) {
			$post_id = $queried_object->ID;
		} else {
			return;
		}
		$book_btn = get_post_meta($post_id, '_booking_link', true);
		if (empty($book_btn)) {
			return;
		}
		ob_start();
		echo $before_widget;
		if ($title) {
			echo $before_title . '<i class="fa fa-calendar-check"></i> ' . $title . $after_title;
		}



	?>

		<div class="row with-forms  margin-top-0" id="booking-widget-anchor">
			<form autocomplete="off" id="form-booking">

				<a <?php if ($new_window == 'on') {
						echo 'target="_blank" ';
					} ?> href="<?php echo $book_btn; ?>" class="button fullwidth margin-top-5"><span class="book-now-text"><?php echo $btn; ?></span></a>

			</form>
		</div>
		<?php

		echo $after_widget;

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget($args, $content);
	}
}

/**
 * Booking Widget
 */
class Listeo_Core_Opening_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{

		$this->widget_cssclass    = 'listeo_core boxed-widget opening-hours margin-bottom-35';
		$this->widget_description = __('Shows Opening Hours.', 'listeo_core');
		$this->widget_id          = 'widget_opening_hours';
		$this->widget_name        =  __('Listeo Opening Hours', 'listeo_core');
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('Opening Hours', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),


		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{




		extract($args);
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		$queried_object = get_queried_object();
		$packages_disabled_modules = get_option('listeo_listing_packages_options', array());

		if ($queried_object) {
			$post_id = $queried_object->ID;


			if (empty($packages_disabled_modules)) {
				$packages_disabled_modules = array();
			}

			$user_package = get_post_meta($post_id, '_user_package_id', true);
			if ($user_package) {
				$package = listeo_core_get_user_package($user_package);
			}
			$listing_type = get_post_meta($post_id, '_listing_type', true);
		} else {
			return;
		}

		// Check if listing type supports opening hours
		if (!empty($listing_type)) {
			$custom_types = new Listeo_Core_Custom_Listing_Types();
			if (!$custom_types->type_supports_opening_hours($listing_type)) {
				return;
			}
		}

		if (in_array('option_opening_hours', $packages_disabled_modules)) {

			if (isset($package) && $package->has_listing_opening_hours() != 1) {
				return;
			}
		}
		$_opening_hours_status = get_post_meta($post_id, '_opening_hours_status', true);
		if (!$_opening_hours_status) {
			return;
		}
		$has_hours = false;
		//check if has any horus saved
		$days = listeo_get_days();
		foreach ($days as $d_key => $value) {
			$opening_day = get_post_meta($post_id, '_' . $d_key . '_opening_hour', true);
			$closing_day = get_post_meta($post_id, '_' . $d_key . '_closing_hour', true);

			if ((!empty($opening_day) && $opening_day != "Closed")  || (!empty($closing_day) && $closing_day != "Closed")) {
				$has_hours = true;
			}
		}
		if (!$has_hours) {
			return;
		}
		ob_start();
		echo $before_widget;
		if (listeo_check_if_open()) { ?>
			<div class="listing-badge now-open"><?php esc_html_e('Now Open', 'listeo_core'); ?></div>
		<?php } else { ?>
			<div class="listing-badge now-closed"><?php esc_html_e('Now Closed', 'listeo_core'); ?></div>
		<?php
		}
		if ($title) {
			echo $before_title . '<i class="sl sl-icon-clock"></i> ' . $title . $after_title;
		}
		?>
		<ul>
			<?php
			$clock_format = get_option('listeo_clock_format');

			foreach ($days as $d_key => $value) {
				$opening_day = get_post_meta($post_id, '_' . $d_key . '_opening_hour', true);
				$closing_day = get_post_meta($post_id, '_' . $d_key . '_closing_hour', true);

			?>

				<?php

				if (is_array($opening_day)) {
					if (!empty($opening_day[0])) :

						echo '<li class="listeo-opening-day-' . $d_key . '">';
						echo esc_html($value);

						echo '<span>';
						foreach ($opening_day as $key => $opening) {
							if (!empty($opening)) {


								$closing = $closing_day[$key];

								if ($clock_format == 12) {
									if (substr($opening, -1) != 'M' && $opening != 'Closed') {
										$opening = DateTime::createFromFormat('H:i', $opening);
										if ($opening) {
											$opening = $opening->format('h:i A');
										}
									}

									if (substr($closing, -1) != 'M' && $closing != 'Closed') {

										$closing = DateTime::createFromFormat('H:i', $closing);
										if ($closing) {
											$closing = $closing->format('h:i A');
										}
										if ($closing == '00:00') {
											$closing = '24:00';
										}
									}
								}

				?>

								<?php echo esc_html($opening); ?>
								-
								<?php
								if ($clock_format == 12 && $closing == '12:00 AM') {
									echo  '12:00 AM';
								} else if ($clock_format != 12 && $closing == '00:00') {
									echo  '24:00';
								} else {
									echo esc_html($closing);
								}
								echo '<br>';
								?>
						<?php }
						}

						echo ' </span></li>';
					else : ?>
						<li><?php echo $value; ?><span><?php esc_html_e('Closed', 'listeo_core') ?></span>
						<?php endif;
				} else {

					//not array, old listings
					if (!empty($opening_day) && !empty($closing_day)) {
						echo '<li>';
						echo esc_html($value);
						if ($clock_format == 12) {
							if (substr($opening_day, -1) != 'M' && $opening_day != 'Closed') {
								$opening_day = DateTime::createFromFormat('H:i', $opening_day)->format('h:i A');
							}

							if (substr($closing_day, -1) != 'M' && $closing_day != 'Closed') {

								$closing_day = DateTime::createFromFormat('H:i', $closing_day)->format('h:i A');

								if ($closing_day == '00:00') {
									$closing_day = '24:00';
								}
							}
						} ?>
							<span>
								<?php echo esc_html($opening_day); ?>
								-
								<?php
								if ($clock_format == 12 && $closing_day == '12:00 AM') {
									echo  '12:00 PM';
								} else if ($clock_format != 12 && $closing_day == '00:00') {
									echo  '24:00';
								} else {
									echo esc_html($closing_day);
								}

								?> </span>
						<?php } else { ?>
						<li><?php echo $value; ?><span><?php esc_html_e('Closed', 'listeo_core') ?></span>
						<?php } ?>

						</li>
					<?php }
					?>


				<?php } //end foreach 
				?>
		</ul>

	<?php


		echo $after_widget;

		$content = ob_get_clean();

		echo $content;
	}
}

/**
 * Classified Owner Widget
 */
class Listeo_Core_Classified_Owner_Widget extends Listeo_Core_Widget
{

	public function __construct()
	{

		$this->widget_cssclass    = 'listeo_core widget_listing_classified_owner boxed-widget margin-bottom-35';
		$this->widget_description = __('Shows Listing Owner info on Classified ad.', 'listeo_core');
		$this->widget_id          = 'widget_classified_listing_owner';
		$this->widget_name        =  __('Listeo Classified Owner Widget', 'listeo_core');
		$this->settings           = array(

			'phone' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Phone number', 'listeo_core')
			),
			'loggedin' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Show Phone to logged in users only', 'listeo_core')
			),

			'contact' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Show Send message button', 'listeo_core')
			),


		);
		$this->register();
	}




	public function widget($args, $instance)
	{
		// if ( $this->get_cached_widget( $args ) ) {
		// 	return;
		// }



		extract($args);

		$queried_object = get_queried_object();
		if (!$queried_object) {
			return;
		}
		$owner_id = $queried_object->post_author;

		if (!$owner_id) {
			return;
		}
		$owner_data = get_userdata($owner_id);
		if ($queried_object) {
			$post_id = $queried_object->ID;
			$listing_type = get_post_meta($post_id, '_listing_type', true);
		}

		if ($listing_type != 'classifieds') {
			return;
		}
		ob_start();
		echo $before_widget;


		$show_phone = (isset($instance['phone']) && !empty($instance['phone'])) ? true : false;
		$show_loggedin = (isset($instance['loggedin']) && !empty($instance['loggedin'])) ? true : false;

		$visibility_setting = get_option('listeo_user_contact_details_visibility'); // hide_all, show_all, show_logged, 
		if ($visibility_setting == 'hide_all') {
			$show_phone = false;
		} elseif ($visibility_setting == 'show_all') {
			$show_phone = true;
		} else {
			if (is_user_logged_in()) {
				if ($visibility_setting == 'show_logged') {
					$show_phone = true;
				} else {
					$show_phone = false;
				}
			} else {
				$show_phone = false;
			}
		}

		if ($show_loggedin) {
			if (is_user_logged_in()) {
				$show_phone = true;
			} else {
				$show_phone = false;
			}
		}

		$registered_date = get_the_author_meta('user_registered', $owner_id);
	?>



		<div class="classifieds-widget">
			<div class="classifieds-user">
				<div class="classifieds-user-avatar"><a href="<?php echo esc_url(get_author_posts_url($owner_id)); ?>"><?php echo get_avatar($owner_id, 56);  ?></a></div>
				<div class="classifieds-user-details">
					<h3><?php echo listeo_get_users_name($owner_id); ?></h3>
					<span><?php esc_html_e('User since ', 'listeo_core');
							echo date_i18n(get_option('date_format'), strtotime($registered_date)); ?> </span>
					<a href="<?php echo esc_url(get_author_posts_url($owner_id)); ?>"><?php esc_html_e('More ads from this user ', 'listeo_core'); ?> <i class="fa fa-chevron-right"></i></a>
				</div>
			</div>


			<div class="classifieds-widget-buttons">

				<?php


				if ($show_phone) {

					if (isset($owner_data->phone) && !empty($owner_data->phone)) : ?>
						<a class="call-btn" href="tel:<?php echo esc_attr($owner_data->phone); ?>"><?php esc_html_e('Call', 'listeo_core'); ?></a>
					<?php endif;
				} else { ?>
					<a class="call-btn sign-in popup-with-zoom-anim" href="#sign-in-dialog"><?php esc_html_e('Login to Call', 'listeo_core'); ?></a>
					<?php }
					// See Owner widget above — same fix. The guest (else)
				// branch previously rendered the Send Message CTA
				// regardless of the `contact` checkbox state. Hoist
				// the setting check outside the logged-in / guest
				// split so both paths honour it.
				$show_contact_button = isset($instance['contact']) && !empty($instance['contact']);
				if ($show_contact_button) {
					if (is_user_logged_in()) { ?>
						<!-- Reply to review popup -->
						<div id="small-dialog" class="zoom-anim-dialog mfp-hide">
							<div class="small-dialog-header">
								<h3><?php esc_html_e('Send Message', 'listeo_core'); ?></h3>
							</div>
							<div class="message-reply margin-top-0">
								<form action="" id="send-message-from-widget" data-listingid="<?php echo esc_attr($post_id); ?>">
									<textarea required data-recipient="<?php echo esc_attr($owner_id); ?>" data-referral="listing_<?php echo esc_attr($post_id); ?>" cols="40" id="contact-message" name="message" rows="3" placeholder="<?php esc_attr_e('Your message to ', 'listeo_core');
																																																											echo $owner_data->first_name; ?>"></textarea>
									<button class="button">
										<i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i><?php esc_html_e('Send Message', 'listeo_core'); ?></button>
									<div class="notification closeable success margin-top-20"></div>

								</form>

							</div>
						</div>


						<a href="#small-dialog" class="send-message-to-owner button  popup-with-zoom-anim"><?php esc_html_e('Send Message', 'listeo_core'); ?></a>
					<?php } else { ?>
						<a href="#sign-in-dialog" class="sign-in button  popup-with-zoom-anim"><?php esc_html_e('Send Message', 'listeo_core'); ?></a>
					<?php }
				}; ?>


			</div>



		</div>
		<?php
		echo $after_widget;

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget($args, $content);
	}



	////

}

//
// 
// 


/**
 * Booking Widget
 */
class Listeo_Core_Owner_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{

		$this->widget_cssclass    = 'listeo_core widget_listing_owner boxed-widget margin-bottom-35';
		$this->widget_description = __('Shows Listing Owner box.', 'listeo_core');
		$this->widget_id          = 'widget_listing_owner';
		$this->widget_name        =  __('Listeo Owner Widget', 'listeo_core');
		$this->settings           = array(

			'title' => array(
				'type'  => 'text',
				'std'   => __('Hosted By', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),
			'phone' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Phone number', 'listeo_core')
			),
			'email' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Email', 'listeo_core')
			),
			'bio' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Biographical info', 'listeo_core')
			),
			'social' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Social Sites profiles', 'listeo_core')
			),
			'contact' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Show Send message button', 'listeo_core')
			),
			'only_verified' => array(
				'type'  => 'checkbox',
				'std'   => 'off',
				'label' => __('Show Send message button only on verified listings', 'listeo_core')
			),
			'use_in_classified' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Use this widget also in Classified listing type', 'listeo_core')
			),
			'chat_via_whatsapp' => array(
				'type'  => 'checkbox',
				'std'   => 'on',
				'label' => __('Chat via WhatsApp', 'listeo_core')
			),
			'whatsapp_source' => array(
				'type'  => 'dropdown',
				'std'   => 'author_phone',
				'label' => __('WhatsApp Number Source', 'listeo_core'),
				'options' => array(
					'author_phone' => __('Author\'s phone (default)', 'listeo_core'),
					'listing_whatsapp' => __('Listing WhatsApp field (fallback to author)', 'listeo_core')
				)
			),


		);


		// Add dynamic custom user fields to widget settings
		$custom_fields = get_option('listeo_owner_fields', array());
		if (!empty($custom_fields) && is_array($custom_fields)) {
			// Define default/built-in fields that are already handled by the widget
			$excluded_fields = array(
				'phone',           // Already handled by widget phone setting
				'email',           // Already handled by widget email setting
				'description',     // Bio/description already handled
				'user_description', // Bio/description already handled
				'bio',            // Bio/description already handled
				'twitter',        // Social profiles already handled with icons
				'facebook',       // Social profiles already handled with icons
				'instagram',      // Social profiles already handled with icons
				'linkedin',       // Social profiles already handled with icons
				'youtube',        // Social profiles already handled with icons
				'whatsapp',       // Social profiles already handled with icons
				'skype',          // Social profiles already handled with icons
				'tiktok',         // Social profiles already handled with icons
				'telegram',         // Social profiles already handled with icons
				'_telegram',         // Social profiles already handled with icons
			);

			foreach ($custom_fields as $field_key => $field_data) {
				// Skip header/section fields that aren't actual data fields
				if (isset($field_data['type']) && $field_data['type'] !== 'header') {
					$field_id = isset($field_data['id']) ? $field_data['id'] : $field_key;

					// Skip fields that are already handled by the widget
					if (in_array($field_id, $excluded_fields)) {
						continue;
					}

					$field_name = isset($field_data['name']) ? $field_data['name'] : ucfirst(str_replace('_', ' ', $field_id));

					// Add custom field setting with "custom_" prefix to avoid conflicts
					$this->settings['custom_' . $field_id] = array(
						'type'  => 'checkbox',
						'std'   => 'off',
						'label' => sprintf(__('Show %s', 'listeo_core'), strip_tags($field_name))
					);
				}
			}
		}

		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{
		// if ( $this->get_cached_widget( $args ) ) {
		// 	return;
		// }



		extract($args);
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		$queried_object = get_queried_object();
		if (!$queried_object) {
			return;
		}
		$owner_id = $queried_object->post_author;

		if (!$owner_id) {
			return;
		}
		$owner_data = get_userdata($owner_id);
		if ($queried_object) {
			$post_id = $queried_object->ID;
			$listing_type = get_post_meta($post_id, '_listing_type', true);
			if (get_post_status($post_id) == 'expired') {
				return;
			}
		}

		// Option 3: Set default if not exists, then check
		if (!isset($instance['use_in_classified'])) {
			$instance['use_in_classified'] = null;
		}
		if ($instance['use_in_classified'] === '') {
			$instance['use_in_classified'] = false;
		}
		// if listing is set to type classifieds and widget is not set to show in classifieds, return
		if ($listing_type == 'classifieds' && $instance['use_in_classified'] != 'on') {
			return;
		}
		ob_start();

		echo $before_widget;

		if ($title) {	?>
			<div class="hosted-by-title">
				<h4><span><?php echo $title; ?></span> <a href="<?php echo esc_url(get_author_posts_url($owner_id)); ?>">
						<?php echo listeo_get_users_name($owner_id); ?></a></h4>
				<a href="<?php echo esc_url(get_author_posts_url($owner_id)); ?>" class="hosted-by-avatar"><?php echo get_avatar($owner_id, 56);  ?></a>
				<a class="hosted-by-link" href="<?php echo esc_url(get_author_posts_url($owner_id)); ?>"><?php esc_html_e('View Profile', 'listeo_core'); ?></a>
			</div>

		<?php }
		$show_bio = (isset($instance['bio']) && !empty($instance['bio'])) ? true : false;

		if ($show_bio && !empty($owner_data->user_description)) {
		?>
			<div class="hosted-by-bio">
				<?php echo wpautop(esc_html($owner_data->user_description)); ?>
			</div>


			<?php
		}

		$show_email = (isset($instance['email']) && !empty($instance['email'])) ? true : false;
		$show_phone = (isset($instance['phone']) && !empty($instance['phone'])) ? true : false;
		$show_social = (isset($instance['social']) && !empty($instance['social'])) ? true : false;
		$visibility_setting = get_option('listeo_user_contact_details_visibility'); // hide_all, show_all, show_logged, show_booked,  
		if ($visibility_setting == 'hide_all') {
			$show_details = false;
		} elseif ($visibility_setting == 'show_all') {
			$show_details = true;
		} else {
			if (is_user_logged_in()) {
				if ($visibility_setting == 'show_logged') {
					$show_details = true;
				} else {
					$show_details = false;
				}
			} else {
				$show_details = false;
			}
		}

		// Display custom user fields
		if ($show_details) {
			$this->render_custom_fields($owner_id, $instance);
		}


		if ($show_details) {
			if ($show_email || $show_phone) {  ?>
				<ul class="listing-details-sidebar">
					<?php if ($show_phone) {  ?>
						<?php if (isset($owner_data->phone) && !empty($owner_data->phone)) : ?>
							<li><i class="sl sl-icon-phone"></i> <a href="tel:<?php echo esc_attr($owner_data->phone); ?>"><?php echo esc_html($owner_data->phone); ?></a></li>
						<?php endif;
					}
					if ($show_email) {
						if (isset($owner_data->user_email)) : $email = $owner_data->user_email; ?>
							<li><i class="fa fa-envelope-o"></i><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></li>
						<?php endif; ?>
					<?php } ?>

				</ul>
			<?php }
		} else {
			if ($visibility_setting != 'hide_all') { ?>
				<p id="owner-widget-not-logged-in"><?php if (get_option('listeo_popup_login', true) != 'ajax') {
														printf(
															esc_html__('Please %s sign %s in to see contact details.', 'listeo_core'),
															sprintf('<a href="%s" class="sign-in">', wp_login_url(apply_filters('the_permalink', get_permalink($post_id), $post_id))),
															'</a>'
														);
													} else {
														printf(esc_html__('Please %s sign %s in to see contact details.', 'listeo_core'), '<a href="#sign-in-dialog" class="sign-in popup-with-zoom-anim">', '</a>');
													}
													?></p>
		<?php }
		} ?>
		<?php if ($show_details && $show_social) { ?>
			<ul class="listing-details-sidebar social-profiles">
				<?php if (isset($owner_data->twitter) && !empty($owner_data->twitter)) : ?><li><a href="<?php if (strpos($owner_data->twitter, 'http') === 0) {
																											echo esc_url($owner_data->twitter);
																										} else {
																											echo "https://x.com/" . esc_attr($owner_data->twitter);
																										} ?>" class="twitter-profile"><i class="fa-brands fa-x-twitter"></i> X.com</a></li><?php endif; ?>
				<?php if (isset($owner_data->facebook) && !empty($owner_data->facebook)) : ?><li><a href="<?php if (strpos($owner_data->facebook, 'http') === 0) {
																												echo esc_url($owner_data->facebook);
																											} else {
																												echo "https://facebook.com/" . esc_attr($owner_data->facebook);
																											} ?>" class="facebook-profile"><i class="fa fa-facebook-square"></i> Facebook</a></li><?php endif; ?>
				<?php if (isset($owner_data->instagram) && !empty($owner_data->instagram)) : ?><li><a href="<?php if (strpos($owner_data->instagram, 'http') === 0) {
																												echo esc_url($owner_data->instagram);
																											} else {
																												echo "https://instagram.com/" . esc_attr($owner_data->instagram);
																											} ?>" class="instagram-profile"><i class="fa fa-instagram"></i> Instagram</a></li><?php endif; ?>
				<?php if (isset($owner_data->linkedin) && !empty($owner_data->linkedin)) : ?><li><a href="<?php if (strpos($owner_data->linkedin, 'http') === 0) {
																												echo esc_url($owner_data->linkedin);
																											} else {
																												echo "https://linkedin.com/in/" . esc_attr($owner_data->linkedin);
																											} ?>" class="linkedin-profile"><i class="fa fa-linkedin"></i> LinkedIn</a></li><?php endif; ?>
				<?php if (isset($owner_data->youtube) && !empty($owner_data->youtube)) : ?><li><a href="<?php if (strpos($owner_data->youtube, 'http') === 0) {
																											echo esc_url($owner_data->youtube);
																										} else {
																											echo "https://youtube.com/@" . esc_attr($owner_data->youtube);
																										} ?>" class="youtube-profile"><i class="fa fa-youtube"></i> YouTube</a></li><?php endif; ?>
				<?php if (isset($owner_data->whatsapp) && !empty($owner_data->whatsapp)) : ?><li><a href="<?php if (strpos($owner_data->whatsapp, 'http') === 0) {
																												echo esc_url($owner_data->whatsapp);
																											} else {
																												echo "https://wa.me/" . esc_attr($owner_data->whatsapp);
																											} ?>" class="whatsapp-profile"><i class="fa fa-whatsapp"></i> WhatsApp</a></li><?php endif; ?>
				<?php if (isset($owner_data->skype) && !empty($owner_data->skype)) : ?><li>
						<a href="<?php if (strpos($owner_data->skype, 'http') === 0) {
										echo esc_url($owner_data->skype);
									} else {
										echo "skype:+" . esc_attr($owner_data->skype) . "?call";
									} ?>" class="skype-profile"><i class="fa fa-skype"></i> Skype</a>
					</li><?php endif; ?>
				<?php if (isset($owner_data->tiktok) && !empty($owner_data->tiktok)) : ?><li>
						<a href="<?php if (strpos($owner_data->tiktok, 'http') === 0) {
										echo esc_url($owner_data->tiktok);
									} else {
										echo "https://www.tiktok.com/@" . esc_attr($owner_data->tiktok);
									} ?>" class="tiktok-profile" target="_blank"><i class="fa-brands fa-tiktok"></i> TikTok</a>
					</li><?php endif; ?>
				<?php if (isset($owner_data->_telegram) && !empty($owner_data->_telegram)) : ?><li><a href="<?php if (strpos($owner_data->_telegram, 'http') === 0) {
																												echo esc_url($owner_data->_telegram);
																											} else {
																												echo "https://telegram.me/" . esc_attr($owner_data->_telegram);
																											} ?>" class="telegram-profile"><i class="fa fa-telegram"></i> Telegram</a></li><?php endif; ?>

				<!-- <li><a href="#" class="gplus-profile"><i class="fa fa-google-plus"></i> Google Plus</a></li> -->
			</ul>
		<?php } ?>



		<?php
		$show_send = true;
		if (
			isset($instance['only_verified']) && $instance['only_verified'] == 'on'
		) {
			$verified = get_post_meta($post_id, '_verified', true);
			if (!$verified) {
				$show_send = false;
			}
		}
		// Honour the "Show Send message button" widget setting for BOTH
		// the logged-in path (where we render the real form) AND the
		// guest path (where we render a sign-in CTA styled as the
		// Send Message button). Pre-fix, the guest branch ignored the
		// setting entirely — admins who unchecked the box still saw
		// the button as anonymous visitors.
		$show_contact_button = isset($instance['contact']) && !empty($instance['contact']);
		if ($show_send && $show_contact_button) :
			if (is_user_logged_in()) : ?>
					<!-- Reply to review popup -->
					<div id="small-dialog" class="zoom-anim-dialog mfp-hide">
						<div class="small-dialog-header">
							<h3><?php esc_html_e('Send Message', 'listeo_core'); ?></h3>
						</div>
						<div class="message-reply margin-top-0">
							<form action="" id="send-message-from-widget" data-listingid="<?php echo esc_attr($post_id); ?>">
								<textarea required data-recipient="<?php echo esc_attr($owner_id); ?>" data-referral="listing_<?php echo esc_attr($post_id); ?>" cols="40" id="contact-message" name="message" rows="3" placeholder="<?php esc_attr_e('Your message to ', 'listeo_core');
																																																										echo $owner_data->first_name; ?>"></textarea>
								<button class="button">
									<i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i><?php esc_html_e('Send Message', 'listeo_core'); ?></button>
								<div class="notification closeable success margin-top-20"></div>

							</form>

						</div>
					</div>


					<a href="#small-dialog" class="send-message-to-owner button popup-with-zoom-anim"><i class="sl sl-icon-envelope-open"></i> <?php esc_html_e('Send Message', 'listeo_core'); ?></a>
			<?php else : ?>
				<a href="#sign-in-dialog" class="sign-in button  popup-with-zoom-anim"><?php esc_html_e('Send Message', 'listeo_core'); ?></a>
			<?php endif; ?>
		<?php endif; ?>

		<?php

		if (isset($instance['chat_via_whatsapp']) && $instance['chat_via_whatsapp'] == 'on') :
			// Get WhatsApp source preference (default: author_phone for backwards compatibility)
			$whatsapp_source = isset($instance['whatsapp_source']) ? $instance['whatsapp_source'] : 'author_phone';
			$whatsapp_number = '';

			if ($whatsapp_source == 'listing_whatsapp') {
				// Prioritize listing's WhatsApp field, fall back to owner's phone
				$whatsapp_number = get_post_meta($post_id, '_whatsapp', true);
				if (empty($whatsapp_number) && isset($owner_data->phone)) {
					$whatsapp_number = $owner_data->phone;
				}
			} else {
				// Default: use author's phone (backwards compatible)
				if (isset($owner_data->phone)) {
					$whatsapp_number = $owner_data->phone;
				}
			}

			if (!empty($whatsapp_number)) : ?>
				<a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr($whatsapp_number); ?>&text=<?php echo esc_html_x('Hello', 'Whatsapp Chat via button', 'listeo_core'); ?>" target="_blank" class="send-message-to-owner button whatsapp-profile listeo-track-whatsapp"><i class="fa fa-whatsapp"></i> <?php esc_html_e('Chat via WhatsApp', 'listeo_core'); ?></a>
			<?php endif;
		endif;


		echo $after_widget;

		$content = ob_get_clean();

		echo $content;

		//$this->cache_widget($args, $content);
	}

	/**
	 * Render custom user fields
	 * 
	 * Available hooks for customization:
	 * 
	 * 1. listeo_owner_widget_custom_fields - Filter the entire fields array before rendering
	 * 2. listeo_owner_widget_custom_fields_wrapper_class - Change wrapper CSS class
	 * 3. listeo_owner_widget_field_name - Filter field display name
	 * 4. listeo_owner_widget_skip_field - Skip rendering specific fields
	 * 5. listeo_owner_widget_field_icon - Filter field icon class
	 * 6. listeo_owner_widget_custom_field_output - Complete field output override
	 * 7. listeo_owner_widget_field_{field_id}_output - Field-specific output override
	 * 8. listeo_owner_widget_field_type_{type}_output - Field type-specific override
	 * 9. listeo_owner_widget_array_field_values - Process array field values
	 * 10. listeo_owner_widget_array_field_value - Process individual array values
	 * 11. listeo_owner_widget_option_label - Override option labels
	 * 12. listeo_owner_widget_array_field_format - Control array display format
	 * 
	 * @param int $owner_id User ID
	 * @param array $instance Widget instance settings
	 */
	private function render_custom_fields($owner_id, $instance)
	{
		$custom_fields = get_option('listeo_owner_fields', array());
		if (empty($custom_fields) || !is_array($custom_fields)) {
			return;
		}

		// Define default/built-in fields that are already handled by the widget
		$excluded_fields = array(
			'phone',           // Already handled by widget phone setting
			'email',           // Already handled by widget email setting
			'description',     // Bio/description already handled
			'user_description', // Bio/description already handled
			'bio',            // Bio/description already handled
			'twitter',        // Social profiles already handled with icons
			'facebook',       // Social profiles already handled with icons
			'instagram',      // Social profiles already handled with icons
			'linkedin',       // Social profiles already handled with icons
			'youtube',        // Social profiles already handled with icons
			'whatsapp',       // Social profiles already handled with icons
			'skype',          // Social profiles already handled with icons
			'tiktok',         // Social profiles already handled with icons
			'telegram',         // Social profiles already handled with icons
			'_telegram',         // Social profiles already handled with icons
		);

		$fields_to_display = array();

		// Check which custom fields are enabled in widget settings
		foreach ($custom_fields as $field_key => $field_data) {
			if (isset($field_data['type']) && $field_data['type'] !== 'header') {
				$field_id = isset($field_data['id']) ? $field_data['id'] : $field_key;

				// Skip fields that are already handled by the widget
				if (in_array($field_id, $excluded_fields)) {
					continue;
				}

				$widget_setting_key = 'custom_' . $field_id;

				// Check if this field is enabled in widget settings
				if (isset($instance[$widget_setting_key]) && $instance[$widget_setting_key] === 'on') {
					// Get the user meta value
					$field_value = get_user_meta($owner_id, $field_id, true);

					if (!empty($field_value)) {
						$fields_to_display[] = array(
							'data' => $field_data,
							'value' => $field_value,
							'id' => $field_id
						);
					}
				}
			}
		}

		// Allow filtering the fields to display
		$fields_to_display = apply_filters('listeo_owner_widget_custom_fields', $fields_to_display, $owner_id, $instance);

		// If we have fields to display, render them
		if (!empty($fields_to_display)) {
			// Allow customizing the wrapper class
			$wrapper_class = apply_filters('listeo_owner_widget_custom_fields_wrapper_class', 'listing-details-sidebar custom-user-fields');
			echo '<ul class="' . esc_attr($wrapper_class) . '">';

			foreach ($fields_to_display as $field_info) {
				$field_data = $field_info['data'];
				$field_value = $field_info['value'];
				$field_id = $field_info['id'];

				$field_name = isset($field_data['name']) ? strip_tags($field_data['name']) : ucfirst(str_replace('_', ' ', $field_id));
				$field_type = isset($field_data['type']) ? $field_data['type'] : 'text';

				// Allow field name override
				$field_name = apply_filters('listeo_owner_widget_field_name', $field_name, $field_id, $field_data);

				// Allow skipping individual fields
				if (apply_filters('listeo_owner_widget_skip_field', false, $field_id, $field_value, $field_data)) {
					continue;
				}

				echo '<li class="custom-field-' . esc_attr($field_id) . '">';

				// Add icon if specified
				if (isset($field_data['icon']) && !empty($field_data['icon'])) {
					$icon_class = apply_filters('listeo_owner_widget_field_icon', $field_data['icon'], $field_id, $field_data);
					echo '<i class="' . esc_attr($icon_class) . '"></i> ';
				}

				// Render field based on type
				$this->render_field_by_type($field_name, $field_value, $field_type, $field_data);

				echo '</li>';
			}

			echo '</ul>';
		}
	}

	/**
	 * Render field value based on field type
	 * 
	 * @param string $field_name Display name
	 * @param mixed $field_value Field value
	 * @param string $field_type Field type
	 * @param array $field_data Complete field data
	 */
	private function render_field_by_type($field_name, $field_value, $field_type, $field_data)
	{
		$field_id = isset($field_data['id']) ? $field_data['id'] : '';

		// Allow complete override of field rendering
		$custom_output = apply_filters('listeo_owner_widget_custom_field_output', null, $field_id, $field_name, $field_value, $field_type, $field_data);
		if ($custom_output !== null) {
			echo $custom_output;
			return;
		}

		// Allow specific field override by field ID
		if (!empty($field_id)) {
			$field_specific_output = apply_filters("listeo_owner_widget_field_{$field_id}_output", null, $field_name, $field_value, $field_type, $field_data);
			if ($field_specific_output !== null) {
				echo $field_specific_output;
				return;
			}
		}

		// Allow field type-specific override
		$type_specific_output = apply_filters("listeo_owner_widget_field_type_{$field_type}_output", null, $field_name, $field_value, $field_data);
		if ($type_specific_output !== null) {
			echo $type_specific_output;
			return;
		}

		// First check if field_value is an array (for multi-select, multi-checkbox, etc.)
		if (is_array($field_value) && !empty($field_value)) {
			$this->render_array_field($field_name, $field_value, $field_type, $field_data);
			return;
		}

		switch ($field_type) {
			case 'url':
				if (filter_var($field_value, FILTER_VALIDATE_URL)) {
					echo '<a href="' . esc_url($field_value) . '" target="_blank" rel="noopener">' . esc_html($field_name) . '</a>';
				} else {
					echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($field_value);
				}
				break;

			case 'email':
				if (is_email($field_value)) {
					echo '<a href="mailto:' . esc_attr($field_value) . '">' . esc_html($field_value) . '</a>';
				} else {
					echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($field_value);
				}
				break;

			case 'phone':
			case 'tel':
				echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($field_value);
				break;

			case 'select':
			case 'select_multiple':
			case 'multiselect':
				// For select fields, check if we have options to get readable labels
				if (isset($field_data['options']) && is_array($field_data['options']) && isset($field_data['options'][$field_value])) {
					$display_value = $field_data['options'][$field_value];
				} else {
					$display_value = $field_value;
				}
				echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($display_value);
				break;

			case 'textarea':
				echo '<strong>' . esc_html($field_name) . ':</strong> ' . wp_kses_post(wpautop($field_value));
				break;

			case 'checkbox':
				if ($field_value === 'on' || $field_value === '1' || $field_value === 1) {
					echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html__('Yes', 'listeo_core');
				}
				break;

			default:
				// Default text field or unknown type
				echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($field_value);
				break;
		}
	}

	/**
	 * Render array-type fields (multi-select, multi-checkbox, etc.)
	 * 
	 * @param string $field_name Display name
	 * @param array $field_value Field value array
	 * @param string $field_type Field type
	 * @param array $field_data Complete field data
	 */
	private function render_array_field($field_name, $field_value, $field_type, $field_data)
	{
		$field_id = isset($field_data['id']) ? $field_data['id'] : '';

		// Allow array field processing override
		$processed_values = apply_filters('listeo_owner_widget_array_field_values', $field_value, $field_id, $field_type, $field_data);

		$display_items = array();
		$has_options = isset($field_data['options']) && is_array($field_data['options']);

		foreach ($processed_values as $value) {
			// Skip empty values
			if (empty($value)) {
				continue;
			}

			// Allow individual value processing
			$processed_value = apply_filters('listeo_owner_widget_array_field_value', $value, $field_id, $field_type, $field_data);

			// For checkbox fields, handle 'on' values
			if (($field_type === 'multicheck' || $field_type === 'multicheck_split' || $field_type === 'multi-checkbox') && $processed_value === 'on') {
				$display_items[] = esc_html__('Yes', 'listeo_core');
				continue;
			}

			// Check if we have options to convert value to readable label
			if ($has_options && isset($field_data['options'][$processed_value])) {
				$label = $field_data['options'][$processed_value];
				// Allow option label override
				$custom_label = apply_filters('listeo_owner_widget_option_label', $label, $processed_value, $field_id, $field_data);
				$display_items[] = esc_html($custom_label);
			} else {
				// If no options mapping, use the value as-is
				$display_items[] = esc_html($processed_value);
			}
		}

		if (!empty($display_items)) {
			// Allow display format override
			$display_format = apply_filters('listeo_owner_widget_array_field_format', 'auto', $field_id, $display_items, $field_data);

			echo '<strong>' . esc_html($field_name) . ':</strong> ';

			switch ($display_format) {
				case 'comma':
					echo implode(', ', $display_items);
					break;
				case 'bullets':
					echo '<br>';
					foreach ($display_items as $item) {
						echo '• ' . $item . '<br>';
					}
					break;
				case 'list':
					echo '<ul>';
					foreach ($display_items as $item) {
						echo '<li>' . $item . '</li>';
					}
					echo '</ul>';
					break;
				case 'auto':
				default:
					// Auto-format based on count
					if (count($display_items) <= 3) {
						echo implode(', ', $display_items);
					} else {
						echo '<br>';
						foreach ($display_items as $item) {
							echo '• ' . $item . '<br>';
						}
					}
					break;
			}
		}
	}
}


/**
 * Core class used to implement a Recent Posts widget.
 *
 * @since 2.8.0
 *
 * @see WP_Widget
 */
class Listeo_Recent_Posts extends WP_Widget
{

	/**
	 * Sets up a new Recent Posts widget instance.
	 *
	 * @since 2.8.0
	 * @access public
	 */
	public function __construct()
	{
		$widget_ops = array(
			'classname' => 'listeo_recent_entries',
			'description' => __('Your site&#8217;s most recent Posts.', 'listeo'),
			'customize_selective_refresh' => true,
		);
		parent::__construct('listeo-recent-posts', __('Listeo Recent Posts', 'listeo'), $widget_ops);
		$this->alt_option_name = 'listeo_recent_entries';
	}

	/**
	 * Outputs the content for the current Recent Posts widget instance.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param array $args Display arguments including 'before_title', 'after_title',
	 * 'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Recent Posts widget instance.
	 */
	public function widget($args, $instance)
	{
		if (!isset($args['widget_id'])) {
			$args['widget_id'] = $this->id;
		}

		$title = (!empty($instance['title'])) ? $instance['title'] : __('Recent Posts', 'listeo');

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);

		$number = (!empty($instance['number'])) ? absint($instance['number']) : 5;
		if (!$number)
			$number = 5;
		$show_date = isset($instance['show_date']) ? $instance['show_date'] : false;

		/**
		 * Filters the arguments for the Recent Posts widget.
		 *
		 * @since 3.4.0
		 *
		 * @see WP_Query::get_posts()
		 *
		 * @param array $args An array of arguments used to retrieve the recent posts.
		 */
		$r = new WP_Query(apply_filters('widget_posts_args', array(
			'posts_per_page' => $number,
			'no_found_rows' => true,
			'post_status' => 'publish',
			'ignore_sticky_posts' => true
		)));

		if ($r->have_posts()) :
			?>
			<?php echo $args['before_widget']; ?>
			<?php if ($title) {
				echo $args['before_title'] . $title . $args['after_title'];
			} ?>
			<ul class="widget-tabs">
				<?php while ($r->have_posts()) : $r->the_post(); ?>
					<li>
						<div class="widget-content">
							<?php if (has_post_thumbnail()) { ?>
								<div class="widget-thumb">
									<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('listeo-post-thumb'); ?></a>
								</div>
							<?php } ?>

							<div class="widget-text">
								<h5><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h5>
								<span><?php echo get_the_date(); ?></span>
							</div>
							<div class="clearfix"></div>
						</div>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php echo $args['after_widget']; ?>
		<?php
			// Reset the global $the_post as this query will have stomped on it
			wp_reset_postdata();

		endif;
	}

	/**
	 * Handles updating the settings for the current Recent Posts widget instance.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['show_date'] = isset($new_instance['show_date']) ? (bool) $new_instance['show_date'] : false;
		return $instance;
	}

	/**
	 * Outputs the settings form for the Recent Posts widget.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param array $instance Current settings.
	 */
	public function form($instance)
	{
		$title     = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$number    = isset($instance['number']) ? absint($instance['number']) : 5;
		$show_date = isset($instance['show_date']) ? (bool) $instance['show_date'] : false;
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'listeo'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:', 'listeo'); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3" />
		</p>

		<p><input class="checkbox" type="checkbox" <?php checked($show_date); ?> id="<?php echo $this->get_field_id('show_date'); ?>" name="<?php echo $this->get_field_name('show_date'); ?>" />
			<label for="<?php echo $this->get_field_id('show_date'); ?>"><?php _e('Display post date?', 'listeo'); ?></label>
		</p>
		<?php
	}
}



/**
 * Booking Widget
 */
class Listeo_Coupon_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{

		$this->widget_cssclass    = 'listeo_core boxed-widget coupon-widget margin-bottom-35';
		$this->widget_description = __('Shows Listing Coupon.', 'listeo_core');
		$this->widget_id          = 'widget_coupon';
		$this->widget_name        =  __('Listeo Coupon Widget ', 'listeo_core');
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('Coupon', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),


		);
		$this->register();
	}

	/**
	 * Check if a coupon is expired based on its expiry date.
	 *
	 * @access private
	 * @param int $coupon_id Coupon ID.
	 * @return bool True if expired, false if still valid or no expiry date.
	 */
	private function is_coupon_expired($coupon_id)
	{
		$expiry_date = get_post_meta($coupon_id, 'date_expires', true);

		// If no expiry date is set, coupon never expires
		if (empty($expiry_date) || $expiry_date === '0' || $expiry_date === 0) {
			return false;
		}

		// Convert expiry date to timestamp if it's not already
		$expiry_timestamp = is_numeric($expiry_date) ? intval($expiry_date) : strtotime($expiry_date);

		// If we can't parse the date, assume it's not expired (safe fallback)
		if ($expiry_timestamp === false) {
			return false;
		}

		// Check if expiry date is in the past
		return $expiry_timestamp < time();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{




		extract($args);
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		$queried_object = get_queried_object();
		if ($queried_object) {
			$post_id = $queried_object->ID;
		} else {
			return;
		}
		$packages_disabled_modules = get_option('listeo_listing_packages_options', array());
		if (empty($packages_disabled_modules)) {
			$packages_disabled_modules = array();
		}
		if ($queried_object) {
			$post_id = $queried_object->ID;
			$listing_type = get_post_meta($post_id, '_listing_type', true);

			if (empty($packages_disabled_modules)) {
				$packages_disabled_modules = array();
			}

			$user_package = get_post_meta($post_id, '_user_package_id', true);
			if ($user_package) {
				$package = listeo_core_get_user_package($user_package);
			}
		}

		if (in_array('option_coupons', $packages_disabled_modules)) {

			if (isset($package) && $package->has_listing_coupons() != 1) {
				return;
			}
		}
		$_coupon_section_status = get_post_meta($post_id, '_coupon_section_status', true);
		if (!$_coupon_section_status) {
			return;
		}
		//get coupon
		ob_start();
		$coupon_ids =  get_post_meta($post_id, '_coupon_for_widget', false);
		$coupon_ids = array_unique($coupon_ids);
		if (is_array($coupon_ids) && !empty($coupon_ids)) {
			foreach ($coupon_ids as $coupon_id) {
				if (!($coupon_id)) {

					break;
				}

				$coupon_post = get_post($coupon_id);
				//$coupon = new WC_Coupon($coupon_id);
				if (!$coupon_post) {
					break;
				}

				if ($coupon_post) {
					$coupon_data = new WC_Coupon($coupon_id);
				}

				// Skip expired coupons completely
				if ($this->is_coupon_expired($coupon_id)) {
					continue;
				}



				//echo $before_widget;
				$coupon_bg = get_post_meta($coupon_id, 'coupon_bg-uploader-id', true);
				$coupon_bg_url = wp_get_attachment_url($coupon_bg);

		?>
				<!-- Coupon Widget -->
				<div class="coupon-widget" style="<?php if ($coupon_bg) : ?>background-image: url(<?php echo esc_url($coupon_bg_url); ?>); <?php endif; ?> margin:20px 0px;">
					<a class="coupon-top">

						<?php $coupon_amount = wc_format_localized_price($coupon_data->get_amount());
						$currency_abbr = get_option('listeo_currency');
						$currency_postion = get_option('listeo_currency_postion');
						$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

						if ($coupon_data->get_discount_type() == 'fixed_product') {
							if ($currency_postion == 'after') { ?>
								<h3><?php echo sprintf(esc_html__('Get %1$s%2$s discount!', 'listeo_core'), $coupon_amount, $currency_symbol); ?></h3>
							<?php } else { ?>
								<h3><?php echo sprintf(esc_html__('Get %1$s%2$s discount!', 'listeo_core'), $currency_symbol, $coupon_amount); ?></h3>
							<?php } ?>

						<?php } else { ?>
							<h3><?php echo sprintf(esc_html__('Get %1$s%% discount!', 'listeo_core'), $coupon_amount); ?></h3>
						<?php } ?>


						<?php
						$expiry_date = $coupon_data->get_date_expires();
						if ($expiry_date) : ?>
							<div class="coupon-valid-untill"><?php esc_html_e('Expires', 'listeo_core'); ?> <?php echo esc_html($expiry_date->date_i18n(get_option('date_format')));  ?></div>
						<?php endif; ?>
						<?php if ($coupon_data->get_description()) : ?>
							<div class="coupon-how-to-use"><?php echo $coupon_data->get_description(); ?></div>
						<?php endif; ?>
					</a>
					<div class="coupon-bottom">
						<div class="coupon-scissors-icon"></div>
						<div class="coupon-code"><?php echo $coupon_data->get_code(); ?></div>
					</div>
				</div>



		<?php
			}
		}




		//echo $after_widget; 

		$content = ob_get_clean();

		echo $content;
	}
}


/**
 * Booking Widget
 */
class Listeo_Shop_Vendor_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{

		$this->widget_cssclass    = 'listeo_core boxed-widget shop-vendor-widget margin-bottom-35';
		$this->widget_description = __('Shows Vendor card on listing.', 'listeo_core');
		$this->widget_id          = 'widget_listeo_dokan_vendor';
		$this->widget_name        =  __('Listeo Dokan Vendor Widget ', 'listeo_core');
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('My shop', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),


		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{

		if (!class_exists('WeDevs_Dokan')) {
			return;
		}



		extract($args);
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		$queried_object = get_queried_object();


		if ($queried_object) {
			$post_id = $queried_object->ID;
		} else {
			return;
		}

		$vendor_id = get_post_field('post_author', $post_id);
		$is_vendor = get_user_meta($vendor_id, 'dokan_enable_selling', true);

		if (!$is_vendor || $is_vendor == 'no') {
			return;
		}

		// Get the WP_User object (the vendor) from author ID
		$_store_widget_status = get_post_meta($post_id, '_store_widget_status', true);

		if (!$_store_widget_status) {
			return;
		}
		ob_start();
		$vendor            = dokan()->vendor->get($vendor_id);
		$store_banner_id   = $vendor->get_banner_id();
		$store_name        = $vendor->get_shop_name();
		$store_url         = $vendor->get_shop_url();
		$store_rating      = $vendor->get_rating();
		$is_store_featured = $vendor->is_featured();
		$store_phone       = $vendor->get_phone();
		$store_info        = dokan_get_store_info($vendor_id);
		$store_address     = dokan_get_seller_short_address($vendor_id);
		$store_banner_url  = $store_banner_id ? wp_get_attachment_image_src($store_banner_id, 'full') : DOKAN_PLUGIN_ASSEST . '/images/default-store-banner.png';

		$show_store_open_close    = dokan_get_option('store_open_close', 'dokan_appearance', 'on');
		$dokan_store_time_enabled = isset($store_info['dokan_store_time_enabled']) ? $store_info['dokan_store_time_enabled'] : '';
		$store_open_is_on = ('on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled && !$is_store_featured) ? 'store_open_is_on' : '';

		// Display the seller name linked to the store
		if ($title) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		//echo $before_widget;


		?>

		<div id="dokan-seller-listing-wrap" class="grid-view listeo-dokan-widget">
			<div class="seller-listing-content">
				<ul class="dokan-seller-wrap">
					<li class="dokan-single-seller woocommerce coloum-1 <?php echo (!$store_banner_id) ? 'no-banner-img' : ''; ?>">
						<a href="<?php echo esc_url($store_url); ?>">
							<div class="store-wrapper">
								<div class="store-header">
									<div class="store-banner">

										<img src="<?php echo is_array($store_banner_url) ? esc_attr($store_banner_url[0]) : esc_attr($store_banner_url); ?>">

									</div>
								</div>

								<div class="store-content <?php echo !$store_banner_id ? esc_attr('default-store-banner') : '' ?>">
									<div class="store-data-container">
										<div class="featured-favourite">
											<?php if ($is_store_featured) : ?>
												<div class="featured-label"><?php esc_html_e('Featured', 'dokan-lite'); ?></div>
											<?php endif ?>

											<?php do_action('dokan_seller_listing_after_featured', $vendor, $store_info); ?>
										</div>

										<?php if ('on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled) : ?>
											<?php if (dokan_is_store_open($vendor_id)) { ?>
												<span class="dokan-store-is-open-close-status dokan-store-is-open-status" title="<?php esc_attr_e('Store is Open', 'dokan-lite'); ?>"><?php esc_html_e('Open', 'dokan-lite'); ?></span>
											<?php } else { ?>
												<span class="dokan-store-is-open-close-status dokan-store-is-closed-status" title="<?php esc_attr_e('Store is Closed', 'dokan-lite'); ?>"><?php esc_html_e('Closed', 'dokan-lite'); ?></span>
											<?php } ?>
										<?php endif ?>

										<div class="store-data <?php echo esc_attr($store_open_is_on); ?>">
											<h2><?php echo esc_html($store_name); ?></h2>

											<?php if (!empty($store_rating['count'])) : ?>
												<?php $rating = dokan_get_readable_seller_rating($vendor_id); ?>
												<div class="dokan-store-rating <?php if (!strpos($rating, 'seller-rating') == '<') {
																					echo "no-reviews-rating";
																				} ?>">
													<i class="fa fa-star"></i>
													<?php echo wp_kses_post($rating); ?>
												</div>
											<?php endif ?>

											<?php if (!dokan_is_vendor_info_hidden('address') && $store_address) : ?>
												<?php
												$allowed_tags = array(
													'span' => array(
														'class' => array(),
													),
													'br' => array()
												);
												?>
												<p class="store-address"><?php echo wp_kses($store_address, $allowed_tags); ?></p>
											<?php endif ?>

											<?php if (!dokan_is_vendor_info_hidden('phone') && $store_phone) { ?>
												<p class="store-phone">
													<i class="fa fa-phone" aria-hidden="true"></i> <?php echo esc_html($store_phone); ?>
												</p>
											<?php } ?>

											<?php do_action('dokan_seller_listing_after_store_data', $vendor, $store_info); ?>
										</div>
									</div>
								</div>

								<div class="store-footer">

									<?php $rating = dokan_get_readable_seller_rating($vendor_id); ?>
									<div class="dokan-store-rating <?php if (!strpos($rating, 'seller-rating') == '<') {
																		echo "no-reviews-rating";
																	} ?>">
										<i class="fa fa-star"></i>
										<?php echo wp_kses_post($rating); ?>
									</div>

									<div class="seller-avatar">

										<img src="<?php echo esc_url($vendor->get_avatar()) ?>" alt="<?php echo esc_attr($vendor->get_shop_name()) ?>" size="150">

									</div>

									<span class="dashicons dashicons-arrow-right-alt2 dokan-btn-theme dokan-btn-round"></span>

									<?php do_action('dokan_seller_listing_footer_content', $vendor, $store_info); ?>
								</div>
							</div>
						</a>
					</li>

				</ul>
			</div>
		</div>
		<!-- Coupon Widget -->




		<?php


		//echo $after_widget; 

		$content = ob_get_clean();

		echo $content;
	}
}

//ads widget
class Listeo_Core_Ads_Widget extends Listeo_Core_Widget
{

	/**
	 * Constructor
	 */
	public function __construct()
	{

		$this->widget_cssclass    = 'listeo_core listeoads-widget margin-bottom-35';
		$this->widget_description = __('Shows Listings Ads.', 'listeo_core');
		$this->widget_id          = 'widget_ads';
		$this->widget_name        =  __('Listeo Listing Ads Widget ', 'listeo_core');
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __('Ads', 'listeo_core'),
				'label' => __('Title', 'listeo_core')
			),
			'number' => array(
				'type'  => 'number',
				'std'   => 5,
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'label' => __('Number of Ads', 'listeo_core')
			),

		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{




		extract($args);
		$ads = listeo_get_ids_listings_for_ads('sidebar');
		if (empty($ads)) {
			return;
		}
		$title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		//$number = absint($instance['number']);
		$number = isset($instance['number']) ? $instance['number'] : 5;
		$listings   = new WP_Query(array(
			'posts_per_page' => $number,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post__in'       => $ads,
			'post_type' 	 => 'listing',

		));
		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;
		if ($listings->have_posts()) : ?>

			<?php echo $before_widget; ?>

			<?php if ($title) echo $before_title . $title . $after_title; ?>

			<div class="widget-listing-slider dots-nav new-grid-layout-nl" data-slick='{"autoplay": true, "autoplaySpeed":3000}'>
				<?php while ($listings->have_posts()) : $listings->the_post(); ?>
					<div class="fw-carousel-item">
						<?php
						$ad_data = array(
							'ad' => true,
							'ad_id' => get_the_ID(),
						);
						//     $template_loader->get_template_part( 'content-listing-compact' );  
						$template_loader->set_template_data($ad_data)->get_template_part('content-listing-grid');
						?>
					</div>
				<?php endwhile; ?>
			</div>

			<?php echo $after_widget; ?>

		<?php else : ?>

			<?php $template_loader->get_template_part('listing-widget', 'no-content'); ?>

<?php endif;

		wp_reset_postdata();

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget($args, $content);
	}
}

register_widget('Listeo_Core_Featured_Properties');
register_widget('Listeo_Core_Bookmarks_Share_Widget');

register_widget('Listeo_Core_External_Booking_Widget');
register_widget('Listeo_Core_Search_Widget');
register_widget('Listeo_Core_Opening_Widget');
register_widget('Listeo_Core_Owner_Widget');
register_widget('Listeo_Core_Classified_Owner_Widget');
register_widget('Listeo_Core_Contact_Vendor_Widget');
register_widget('Listeo_Recent_Posts');
register_widget('Listeo_Coupon_Widget');
register_widget('Listeo_Shop_Vendor_Widget');
register_widget('Listeo_Core_Ads_Widget');



function custom_get_post_author_email($atts)
{
	$value = '';
	global $post;
	$post_id = $post->ID;
	$email = get_post_meta($post_id, '_email', true);
	if (!$email) {
		$object = get_post($post_id);
		//just get the email of the listing author
		$owner_ID = $object->post_author;
		//retrieve the owner user data to get the email
		$owner_info = get_userdata($owner_ID);
		if (false !== $owner_info) {
			$email = $owner_info->user_email;
		}
	}
	return $email;
}
add_shortcode('CUSTOM_POST_AUTHOR_EMAIL', 'custom_get_post_author_email');
add_shortcode('LISTING_OWNER_EMAIL', 'custom_get_post_author_email');

//_email
function custom_get_post_listing_title($atts)
{
	$value = '';
	global $post;
	$post_id = $post->ID;
	if ($post_id) {
		$value = get_the_title($post_id);
	}
	return $value;
}
add_shortcode('LISTING_TITLE', 'custom_get_post_listing_title');

//_email
function custom_get_post_listing_url($atts)
{
	$value = '';
	global $post;
	$post_id = $post->ID;
	if ($post_id) {
		$value = get_permalink($post_id);
	}
	return $value;
}
add_shortcode('LISTING_URL', 'custom_get_post_listing_url');
