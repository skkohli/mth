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
	<link href="https://fonts.googleapis.com/css?family=Varela+Round" rel="stylesheet">

	<?php wp_head(); ?>
</head>

<body <?php if (get_option('listeo_dark_mode') == 'enable') {
			echo 'id="dark-mode"';
		} ?> <?php body_class(); ?>>


	<?php wp_body_open(); ?>
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
	<!-- Wrapper -->
	<div id="wrapper">
		<?php do_action('listeo_after_wrapper'); ?>


		<!-- Header Container
================================================== -->
		<header id="header-container" class="fixed fullwidth dashboard">

			<!-- Header -->
			<div id="header" class="<?php if (get_option('listeo_custom_header') == 'enable') {
										echo "custom-header";
									} ?> not-sticky">
				<div class="container">

					<!-- Left Side Content -->
					<div class="left-side">
						<div id="logo">
							<?php
							$logo = get_option('pp_logo_upload', '');
							$logo_retina = get_option('pp_retina_logo_upload', '');
							$logo_dashboard = get_option('pp_dashboard_logo_upload', '');
							if ($logo) { ?>

								<a href="<?php echo esc_url(home_url('/')); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><img id="listeo_logo" src="<?php echo esc_url($logo); ?>" data-rjs="<?php echo esc_url($logo_retina); ?>" alt="<?php esc_attr(bloginfo('name')); ?>" /></a>
								<a href="<?php echo esc_url(home_url('/')); ?>" class="dashboard-logo" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><img id="listeo_logo" src="<?php echo esc_url($logo_dashboard); ?>" data-rjs="<?php echo esc_url($logo_dashboard); ?>" alt="<?php esc_attr(bloginfo('name')); ?>" /></a>
							<?php
							} else { ?>
								<h1>
									<a href="<?php echo esc_url(home_url('/')); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><?php bloginfo('name'); ?></a>
								</h1>
							<?php
							}
							?>
						</div>


						<!-- Mobile Navigation -->
						<div class="mmenu-trigger <?php if (wp_nav_menu(array('theme_location' => 'primary', 'echo' => false)) == false) { ?> hidden-burger <?php } ?>">
							<button class="hamburger hamburger--collapse" type="button">
								<span class="hamburger-box">
									<span class="hamburger-inner"></span>
								</span>
							</button>
						</div>


						<!-- Main Navigation -->
						<nav id="navigation" class="style-1">
							<?php wp_nav_menu(array(
								'theme_location' => 'primary',
								'menu_id' => 'responsive',
								'container' => false,
								'walker' => new listeo_megamenu_walker
							));  ?>

						</nav>
						<div class="clearfix"></div>
						<!-- Main Navigation / End -->

					</div>
					<!-- Left Side Content / End -->
					<?php
					$current_user = wp_get_current_user();
					$roles = $current_user->roles;
					$role = array_shift($roles);
					if (!empty($current_user->user_firstname)) {
						$name = $current_user->user_firstname;
					} else {
						$name =  $current_user->display_name;
					}
					$my_account_display = get_option('listeo_my_account_display', true);
					$submit_display = get_option('listeo_submit_display', true);

					if ($my_account_display != false || $submit_display != false) :	?>
						<!-- Right Side Content / End -->

						<div class="right-side">
							<div class="header-widget">
								<?php get_template_part('inc/mini-cart'); ?>
								<?php
								if (class_exists('Listeo_Core_Template_Loader')) :
									$template_loader = new Listeo_Core_Template_Loader;
									$template_loader->get_template_part('account/logged_section');
								endif;
								?>
							</div>
						</div>

						<!-- Right Side Content / End -->
					<?php endif; ?>

				</div>
			</div>
			<!-- Header / End -->

		</header>
		<div class="clearfix"></div>
		<!-- Header Container / End -->