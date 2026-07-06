<?php

/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package listeo
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">


	<?php wp_head(); ?>


</head>

<body <?php if (get_option('listeo_dark_mode') == 'enable') {
			echo 'id="dark-mode"';
		} ?> <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<?php if (!function_exists('elementor_theme_do_location') || !elementor_theme_do_location('header')) { ?>

		<!-- Wrapper -->


		<!-- Mobile Navigation -->
		<nav class="mobile-navigation-wrapper">
			<div class="mobile-nav-header">
				<div class="menu-logo">
					<?php
					$logo_transparent = get_option('pp_dashboard_logo_upload', ''); ?>
					<a href="<?php echo esc_url(home_url('/')); ?>" class="" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><img id="listeo_logo" src="<?php echo esc_url($logo_transparent); ?>" data-rjs="<?php echo esc_url($logo_transparent); ?>" alt="<?php esc_attr(bloginfo('name')); ?>" /></a>

				</div>
				<a href="#" class="menu-icon-toggle"></a>
			</div>

			<div class="mobile-navigation-list">
				<?php
				if (has_nav_menu('mobile')) {
					$menu_location = 'mobile';
				} else {
					$menu_location = 'primary';
				}
				wp_nav_menu(array(
					'theme_location' => $menu_location,
					'menu_id' => 'mobile-nav',
					'container' => false,
				));  ?>
			</div>

			<div class="mobile-nav-widgets">
				<?php dynamic_sidebar('mobilemenu'); ?>
				<aside id="listeo_side_social_icons" class="mobile-menu-widget widget ">
					<?php /* get the slider array */
					$footericons =  get_option('listeo_side_social_icons', array());
					if (!empty($footericons)) {

						echo '<ul class="new-footer-social-icons">';
						foreach ($footericons as $icon) {
							if ($icon['icons_service'] == 'telegram') {
								echo '<li><a target="_blank" title="' . esc_attr($icon['icons_service']) . '" href="' . esc_url($icon['icons_url']) . '"><i class="fa fa-' . $icon['icons_service'] . '"></i></a></li>';
							} else {
								echo '<li><a target="_blank" title="' . esc_attr($icon['icons_service']) . '" href="' . esc_url($icon['icons_url']) . '"><i class="icon-brand-' . $icon['icons_service'] . '"></i></a></li>';
							}
						}
						echo '</ul>';
					}
					?>
				</aside>
			</div>
		</nav>
		<!-- Mobile Navigation / End-->



		<div id="wrapper">

			<?php

			do_action('listeo_after_wrapper');
			$header_layout = get_option('listeo_header_layout');

			$sticky = get_option('listeo_sticky_header');

			if (is_singular()) {



				$sticky_single = get_post_meta($post->ID, 'listeo_sticky_header', TRUE);
				switch ($sticky_single) {
					case 'on':
					case 'enable':
						$sticky = true;
						break;

					case 'disable':
						$sticky = false;
						break;

					case 'use_global':
						$sticky = get_option('listeo_sticky_header');
						break;

					default:
						$sticky = get_option('listeo_sticky_header');
						break;
				}
				if (is_singular('listing')) {
					$sticky = false;
				}
			}


			$header_layout = apply_filters('listeo_header_layout_filter', $header_layout);
			$sticky = apply_filters('listeo_sticky_header_filter', $sticky);

			?>
			<!-- Header Container
================================================== -->
			<header id="header-container" class="fullwidth hws-wrapper">

				<!-- Header -->
				<div id="header" class="<?php if (get_option('listeo_custom_header') == 'enable') {
											echo "custom-header";
										} ?>">
					<div class="container">
						<?php
						$logo = get_option('pp_logo_upload', '');
						$logo_transparent = get_option('pp_dashboard_logo_upload', '');
						$logo_sticky = get_option('pp_sticky_logo_upload', '');
						?>
						<!-- Left Side Content -->
						<div class="hws-container">
							<div id="logo" data-logo-transparent="<?php echo esc_attr($logo_transparent); ?>" data-logo="<?php echo esc_attr($logo); ?>" data-logo-sticky="<?php echo esc_attr($logo_sticky); ?>">
								<?php
								$logo = get_option('pp_logo_upload', '');
								if ((is_page_template('template-home-search.php') || is_page_template('template-home-search-splash.php'))  && (get_option('listeo_home_transparent_header') == 'enable')) {
									$logo = get_option('pp_dashboard_logo_upload', '');
								}
								if (isset($post) && get_post_meta($post->ID, 'listeo_transparent_header', TRUE)) {
									$logo = get_option('pp_dashboard_logo_upload', '');
								}
								$logo_retina = get_option('pp_retina_logo_upload', '');
								if ($logo) {
									if (is_front_page()) { ?>
										<a href="<?php echo esc_url(home_url('/')); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><img id="listeo_logo" src="<?php echo esc_url($logo); ?>" data-rjs="<?php echo esc_url($logo_retina); ?>" alt="<?php esc_attr(bloginfo('name')); ?>" /></a>
									<?php } else { ?>
										<a href="<?php echo esc_url(home_url('/')); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><img id="listeo_logo" src="<?php echo esc_url($logo); ?>" data-rjs="<?php echo esc_url($logo_retina); ?>" alt="<?php esc_attr(bloginfo('name')); ?>" /></a>
									<?php }
								} else {
									if (is_front_page()) { ?>
										<h1><a href="<?php echo esc_url(home_url('/')); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><?php bloginfo('name'); ?></a></h1>
									<?php } else { ?>
										<h2><a href="<?php echo esc_url(home_url('/')); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><?php bloginfo('name'); ?></a></h2>
								<?php }
								}
								?>
							</div>


							<!-- Mobile Navigation -->
							<!-- 							<div class="mmenu-trigger <?php if (wp_nav_menu(array('theme_location' => 'primary', 'echo' => false)) == false) { ?> hidden-burger <?php } ?>">
								<button class="hamburger hamburger--collapse" type="button">
									<span class="hamburger-box">
										<span class="hamburger-inner"></span>
									</span>
								</button>
							</div> -->

							
							<div class="header-search-container">
								<?php echo do_shortcode('[listeo_search_form action=' . get_post_type_archive_link('listing') . ' source="header" custom_class="main-search-form gray-style"]') ?>
							</div>

							<!-- Right Side Content -->
							<?php

							$my_account_display = get_option('listeo_my_account_display', true);
							$submit_display = get_option('listeo_submit_display', true);

							if ($my_account_display != false || $submit_display != false) :	?>
								<!-- Right Side Content / End -->

								<div class="header-widget">
									<?php get_template_part('inc/mini-cart'); ?>
									<!--end navbar-right -->
									<?php
									if (class_exists('Listeo_Core_Template_Loader')) :
										$template_loader = new Listeo_Core_Template_Loader;
										$template_loader->get_template_part('account/logged_section');
									endif;
									?>
								</div>

								<!-- Right Side Content / End -->
							<?php endif; ?>

						</div>
						<div class="mobile-nav-icons">
							<div class="mobile-search-trigger">
								<i class="gg-search"></i>
								<span class="gg-close rounded"></span>
							</div>
							<div class="desktop-mmenu-trigger">
								<div class="hmb-ico-wrap">
									<div class="hmb-ico">
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
				<!-- Header / End -->

			</header>


			<!-- Header Container / End -->
		<?php } ?>