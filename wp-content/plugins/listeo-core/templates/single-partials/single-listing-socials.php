<?php

// if $data is not null
if (!empty($data)) {
	// extract $data as variables
	if (isset($data->show_email)) {
		$show_email = $data->show_email;
	} else {
		$show_email = true;
	}
} else {
	$show_email = true;
}
// check post status
if (get_post_status() == 'expired') {
	return;
}
$contacts = false;
$phone = get_post_meta(get_the_ID(), '_phone', true);
$mail = get_post_meta(get_the_ID(), '_email', true);
if (!$show_email) {
	$mail = false;
}
$website = get_post_meta(get_the_ID(), '_website', true);
if ($phone || $mail || $website) {
	$contacts = true;
}

// get social media links - build a map from clean name to actual meta key
// Field IDs may have a tab prefix (e.g., _contact_tab_telegram) from the editor
$defined_socials_raw = get_option('listeo_contact_tab_fields');

$socials_map = array();
if(empty($defined_socials_raw)) {
    foreach (array('_facebook', '_twitter', '_instagram', '_youtube', '_skype', '_whatsapp') as $s) {
        $socials_map[$s] = $s;
    }
} else {
    foreach(array_keys($defined_socials_raw) as $id) {
        $clean = preg_replace('/^_[a-z0-9]+_tab_/', '_', $id);
        $socials_map[$clean] = $id;
    }
}

$socials = false;
$facebook    = isset($socials_map['_facebook'])    ? get_post_meta(get_the_ID(), $socials_map['_facebook'], true)    : false;
$youtube     = isset($socials_map['_youtube'])     ? get_post_meta(get_the_ID(), $socials_map['_youtube'], true)     : false;
$twitter     = isset($socials_map['_twitter'])     ? get_post_meta(get_the_ID(), $socials_map['_twitter'], true)     : false;
$instagram   = isset($socials_map['_instagram'])   ? get_post_meta(get_the_ID(), $socials_map['_instagram'], true)   : false;
$skype       = isset($socials_map['_skype'])       ? get_post_meta(get_the_ID(), $socials_map['_skype'], true)       : false;
$whatsapp    = isset($socials_map['_whatsapp'])     ? get_post_meta(get_the_ID(), $socials_map['_whatsapp'], true)    : false;
$linkedin    = isset($socials_map['_linkedin'])    ? get_post_meta(get_the_ID(), $socials_map['_linkedin'], true)    : false;
$soundcloud  = isset($socials_map['_soundcloud'])  ? get_post_meta(get_the_ID(), $socials_map['_soundcloud'], true)  : false;
$pinterest   = isset($socials_map['_pinterest'])   ? get_post_meta(get_the_ID(), $socials_map['_pinterest'], true)   : false;
$viber       = isset($socials_map['_viber'])       ? get_post_meta(get_the_ID(), $socials_map['_viber'], true)       : false;
$tiktok      = isset($socials_map['_tiktok'])      ? get_post_meta(get_the_ID(), $socials_map['_tiktok'], true)      : false;
$snapchat    = isset($socials_map['_snapchat'])    ? get_post_meta(get_the_ID(), $socials_map['_snapchat'], true)    : false;
$telegram    = isset($socials_map['_telegram'])    ? get_post_meta(get_the_ID(), $socials_map['_telegram'], true)    : false;
$tumblr      = isset($socials_map['_tumblr'])      ? get_post_meta(get_the_ID(), $socials_map['_tumblr'], true)      : false;
$reddit      = isset($socials_map['_reddit'])      ? get_post_meta(get_the_ID(), $socials_map['_reddit'], true)      : false;
$medium      = isset($socials_map['_medium'])      ? get_post_meta(get_the_ID(), $socials_map['_medium'], true)      : false;
$twitch      = isset($socials_map['_twitch'])      ? get_post_meta(get_the_ID(), $socials_map['_twitch'], true)      : false;
$mixcloud    = isset($socials_map['_mixcloud'])    ? get_post_meta(get_the_ID(), $socials_map['_mixcloud'], true)    : false;
$tripadvisor = isset($socials_map['_tripadvisor']) ? get_post_meta(get_the_ID(), $socials_map['_tripadvisor'], true) : false;
$yelp        = isset($socials_map['_yelp'])        ? get_post_meta(get_the_ID(), $socials_map['_yelp'], true)        : false;
$foursquare  = isset($socials_map['_foursquare'])  ? get_post_meta(get_the_ID(), $socials_map['_foursquare'], true)  : false;
$line        = isset($socials_map['_line'])        ? get_post_meta(get_the_ID(), $socials_map['_line'], true)        : false;


if ($facebook || $line || $youtube || $twitter || $instagram || $skype || $whatsapp || $linkedin || $soundcloud || $pinterest || $viber || $tiktok || $snapchat || $telegram || $tumblr || $reddit || $medium || $twitch || $mixcloud || $tripadvisor || $yelp || $foursquare) {
	$socials = true;
}

if ($socials || $contacts) :
?>

	<div class="listing-links-container">
		<?php
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


		if ($contacts) :

			if ($show_details) { ?>

				<ul class="listing-links contact-links">
					<?php if (isset($phone) && !empty($phone)) : ?>
						<li><a href="tel:<?php echo esc_attr($phone); ?>" class="listing-links"><i class="fa fa-phone"></i> <?php echo esc_html($phone); ?></a></li>
					<?php endif; ?>
					<?php if (isset($mail) && !empty($mail)) : ?>
						<li><a href="mailto:<?php echo esc_attr($mail); ?>" class="listing-links"><i class="fa fa-envelope-o"></i> <?php echo esc_html($mail); ?></a>
						</li>
					<?php endif; ?>
					<?php if (isset($website) && !empty($website)) :
						$url =  wp_parse_url($website); ?>
						<li><a rel="noopener noreferrer" href="<?php echo esc_url($website) ?>" target="_blank" class="listing-links listing-website"><i class="fa fa-link"></i> <?php
																																					if (isset($url['host'])) {
																																						echo esc_html($url['host']);
																																					} else {
																																						esc_html_e('Visit website', 'listeo_core');
																																					} ?></a></li>
					<?php endif; ?>
				</ul>
				<div class="clearfix"></div>
				<?php
			} else {
				if ($visibility_setting != 'hide_all') { ?>
					<p><?php if (get_option('listeo_popup_login', true) != 'ajax') {
							printf(
								esc_html__('Please %s sign %s in to see contact details.', 'listeo_core'),
								sprintf('<a href="%s" class="sign-in">', wp_login_url(apply_filters('the_permalink', get_permalink(get_the_ID()), get_the_ID()))),
								'</a>'
							);
						} else {
							printf(esc_html__('Please %s sign %s in to see contact details.', 'listeo_core'), '<a href="#sign-in-dialog" class="sign-in popup-with-zoom-anim">', '</a>');
						}
						?></p>
				<?php } ?>
		<?php }
		endif; ?>

		​<?php if ($show_details && $socials) : ?>
		<ul class="listing-links">
			<?php if (isset($facebook) && !empty($facebook)) : ?>
				<li><a href="<?php if (strpos($facebook, 'http') === 0) {
								echo esc_url($facebook);
							} else {
								echo "https://facebook.com/" . esc_attr($facebook);
							} ?>" target="_blank" class="listing-links-fb"><i class="fa fa-facebook-square"></i> Facebook</a></li>
			<?php endif; ?>
			<?php if (isset($youtube) && !empty($youtube)) : ?>
				<li><a href="<?php if (strpos($youtube, 'http') === 0) {
								echo esc_url($youtube);
							} else {
								echo "https://youtube.com/@" . esc_attr($youtube);
							} ?>" target="_blank" class="listing-links-yt"><i class="fa fa-youtube-play"></i> YouTube</a></li>
			<?php endif; ?>
			<?php if (isset($instagram) && !empty($instagram)) : ?>
				<li><a href="<?php if (strpos($instagram, 'http') === 0) {
								echo esc_url($instagram);
							} else {
								echo "https://instagram.com/" . esc_attr($instagram);
							} ?>" target="_blank" class="listing-links-ig"><i class="fa fa-instagram"></i> Instagram</a></li>
			<?php endif; ?>
			<?php if (isset($twitter) && !empty($twitter)) : ?>
				<li><a href="<?php if (strpos($twitter, 'http') === 0) {
								echo esc_url($twitter);
							} else {
								echo "https://x.com/" . esc_attr($twitter);
							} ?>" target="_blank" class="listing-links-tt"><i class="fa-brands fa-x-twitter"></i> Share</a></li>
			<?php endif; ?>
			<?php if (isset($linkedin) && !empty($linkedin)) : ?>
				<li><a href="<?php if (strpos($linkedin, 'http') === 0) {
								echo esc_url($linkedin);
							} else {
								echo "https://linkedin.com/in/" . esc_attr($linkedin);
							} ?>" target="_blank" class="listing-links-linkedit"><i class="fa fa-linkedin"></i> LinkedIn</a></li>
			<?php endif; ?>
			<?php if (isset($viber) && !empty($viber)) : ?>
				<li><a href="<?php echo esc_url($viber); ?>" target="_blank" class="listing-links-viber"><i class="fab fa-viber"></i> Viber</a></li>
			<?php endif; ?>
			<?php if (isset($skype) && !empty($skype)) : ?>
				<li><a href="<?php if (strpos($skype, 'http') === 0) {
									echo esc_url($skype);
								} else {
									echo "skype:+" . esc_attr($skype) . "?call";
								} ?>" target="_blank" class="listing-links-skype"><i class="fa fa-skype"></i> Skype</a></li>
			<?php endif; ?>
			<?php if (isset($whatsapp) && !empty($whatsapp)) : ?>
				<li><a href="<?php if (strpos($whatsapp, 'http') === 0) {
									echo esc_url($whatsapp);
								} else {
									echo "https://wa.me/" . esc_attr($whatsapp);
								} ?>" target="_blank" class="listing-links-whatsapp"><i class="fa fa-whatsapp"></i> WhatsApp</a></li>
			<?php endif; ?>
			<?php if (isset($soundcloud) && !empty($soundcloud)) : ?>
				<li><a href="<?php if (strpos($soundcloud, 'http') === 0) {
									echo esc_url($soundcloud);
								} else {
									echo "https://soundcloud.com/" . esc_attr($soundcloud);
								} ?>" target="_blank" class="listing-links-soundcloud"><i class="fa fa-soundcloud"></i> Soundcloud</a></li>
			<?php endif; ?>
			<?php if (isset($pinterest) && !empty($pinterest)) : ?>
				<li><a href="<?php if (strpos($pinterest, 'http') === 0) {
									echo esc_url($pinterest);
								} else {
									echo "https://pinterest.com/" . esc_attr($pinterest);
								} ?>" target="_blank" class="listing-links-pinterest"><i class="fa fa-pinterest"></i> Pinterest</a></li>
			<?php endif; ?>
			<?php if (isset($tiktok) && !empty($tiktok)) : ?>
				<li><a href="<?php if (strpos($tiktok, 'http') === 0) {
									echo esc_url($tiktok);
								} else {
									echo "https://tiktok.com/@" . esc_attr($tiktok);
								} ?>" target="_blank" class="listing-links-tiktok"><i class="fab fa-tiktok"></i> TikTok</a></li>
			<?php endif; ?>
			<?php if (isset($snapchat) && !empty($snapchat)) : ?>
				<li><a href="<?php if (strpos($snapchat, 'http') === 0) {
									echo esc_url($snapchat);
								} else {
									echo "https://snapchat.com/add/" . esc_attr($snapchat);
								} ?>" target="_blank" class="listing-links-snapchat"><i class="fab fa-snapchat"></i> Snapchat</a></li>
			<?php endif; ?>
			<?php if (isset($telegram) && !empty($telegram)) : ?>
				<li><a href="<?php if (strpos($telegram, 'http') === 0) {
									echo esc_url($telegram);
								} else {
									echo "https://telegram.me/" . esc_attr($telegram);
								} ?>" target="_blank" class="listing-links-telegram"><i class="fab fa-telegram"></i> Telegram</a></li>
			<?php endif; ?>
			<?php if (isset($tumblr) && !empty($tumblr)) : ?>
				<li><a href="<?php if (strpos($tumblr, 'http') === 0) {
									echo esc_url($tumblr);
								} else {
									echo "https://tumblr.com/" . esc_attr($tumblr);
								} ?>" target="_blank" class="listing-links-tumblr"><i class="fab fa-tumblr"></i> Tumblr</a></li>
			<?php endif; ?>
			<?php if (isset($reddit) && !empty($reddit)) : ?>
				<li><a href="<?php if (strpos($reddit, 'http') === 0) {
									echo esc_url($reddit);
								} else {
									echo "https://reddit.com/u/" . esc_attr($reddit);
								} ?>" target="_blank" class="listing-links-reddit"><i class="fab fa-reddit"></i> Reddit</a></li>
			<?php endif; ?>
			<?php if (isset($medium) && !empty($medium)) : ?>
				<li><a href="<?php if (strpos($medium, 'http') === 0) {
									echo esc_url($medium);
								} else {
									echo "https://medium.com/@" . esc_attr($medium);
								} ?>" target="_blank" class="listing-links-medium"><i class="fab fa-medium"></i> Medium</a></li>
			<?php endif; ?>
			<?php if (isset($twitch) && !empty($twitch)) : ?>
				<li><a href="<?php if (strpos($twitch, 'http') === 0) {
									echo esc_url($twitch);
								} else {
									echo "https://twitch.tv/" . esc_attr($twitch);
								} ?>" target="_blank" class="listing-links-twitch"><i class="fab fa-twitch"></i> Twitch</a></li>
			<?php endif; ?>
			<?php if (isset($mixcloud) && !empty($mixcloud)) : ?>
				<li><a href="<?php if (strpos($mixcloud, 'http') === 0) {
									echo esc_url($mixcloud);
								} else {
									echo "https://mixcloud.com/" . esc_attr($mixcloud);
								} ?>" target="_blank" class="listing-links-mixcloud"><i class="fab fa-mixcloud"></i> Mixcloud</a></li>
			<?php endif; ?>
			<?php if (isset($tripadvisor) && !empty($tripadvisor)) : ?>
				<li><a href="<?php if (strpos($tripadvisor, 'http') === 0) {
									echo esc_url($tripadvisor);
								} else {
									echo "https://tripadvisor.com/" . esc_attr($tripadvisor);
								} ?>" target="_blank" class="listing-links-tripadvisor"><i class="fab fa-tripadvisor"></i> TripAdvisor</a></li>
			<?php endif; ?>
			<?php if (isset($yelp) && !empty($yelp)) : ?>
				<li><a href="<?php if (strpos($yelp, 'http') === 0) {
									echo esc_url($yelp);
								} else {
									echo "https://yelp.com/" . esc_attr($yelp);
								} ?>" target="_blank" class="listing-links-yelp"><i class="fab fa-yelp"></i> Yelp</a></li>
			<?php endif; ?>
			<?php if (isset($foursquare) && !empty($foursquare)) : ?>
				<li><a href="<?php if (strpos($foursquare, 'http') === 0) {
									echo esc_url($foursquare);
								} else {
									echo "https://foursquare.com/" . esc_attr($foursquare);
								} ?>" target="_blank" class="listing-links-foursquare"><i class="fab fa-foursquare"></i> Foursquare</a></li>
			<?php endif; ?>

			<?php if (isset($line) && !empty($line)) : ?>
				<li><a href="<?php if (strpos($line, 'http') === 0) {
									echo esc_url($line);
								} else {
									echo "https://line.me/" . esc_attr($line);
								} ?>" target="_blank" class="listing-links-line"><i class="fab fa-line"></i> Line</a></li>
			<?php endif; ?>


		</ul>
		<div class="clearfix"></div>
	<?php endif; ?>

	</div>
	<div class="clearfix"></div>
<?php endif; ?>