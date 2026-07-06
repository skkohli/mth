<?php
/* Get user info. */
global $wp_roles;
$current_user = wp_get_current_user();
$roles = $current_user->roles;
$role = array_shift($roles);
$template_loader = new Listeo_Core_Template_Loader;

if (isset($_GET['updated']) && $_GET['updated'] == 'true') : ?>
	<div class="notification success closeable margin-bottom-35">
		<p><?php esc_html_e('Your profile has been updated.', 'listeo_core'); ?></p><a class="close" href="#"></a>
	</div>
<?php endif; ?>


<?php if (!is_user_logged_in()) : ?>
	<p class="warning">
		<?php esc_html_e('You must be logged in to edit your profile.', 'listeo_core'); ?>
	</p><!-- .warning -->
<?php else : ?>

	<div class="row">

		<!-- Profile -->
		<div class="col-lg-6 col-md-12">
			<div class="dashboard-list-box margin-top-0">
				<h4 class="gray"><?php esc_html_e('Profile Details', 'listeo_core') ?></h4>
				<form enctype="multipart/form-data" method="post" id="edit_user" action="<?php the_permalink(); ?>">
					<div class="dashboard-list-box-static">

						<?php
						$custom_avatar = $current_user->listeo_core_avatar_id;
						$custom_avatar = wp_get_attachment_url($custom_avatar);
						if (!empty($custom_avatar)) { ?>
							<div data-photo="<?php echo $custom_avatar; ?>" data-name="<?php esc_html_e('Your Avatar', 'listeo_core'); ?>" data-size="<?php echo filesize(get_attached_file($current_user->listeo_core_avatar_id)); ?>" class="edit-profile-photo">

							<?php } else { ?>
								<div class="edit-profile-photo">
								<?php } ?>

								<div id="avatar-uploader" class="dropzone">
									<div class="dz-message" data-dz-message><span><?php esc_html_e('Upload Avatar', 'listeo_core'); ?></span></div>
								</div>
								<input class="hidden" name="listeo_core_avatar_id" type="text" id="avatar-uploader-id" value="<?php echo $current_user->listeo_core_avatar_id; ?>" />
								</div>

								<!-- Details -->
								<div class="my-profile">

									<?php

									if (get_option('listeo_profile_allow_role_change')) :
									?>
										<?php if (in_array($role, array('owner', 'guest'))) : ?>
											<label for="role"><?php esc_html_e('Change your role', 'listeo_core'); ?></label>
											<select name="role" id="role">
												<option <?php selected($role, 'guest'); ?> value="guest"><?php echo esc_html_x('Guest', 'User role', 'listeo_core'); ?></option>
												<?php if (class_exists('WeDevs_Dokan')  && get_option('listeo_role_dokan') == 'seller') : ?>
													<option <?php selected($role, 'seller'); ?> value="seller"><?php esc_html_e('Owner', 'listeo_core') ?></option>
												<?php else : ?>
													<option <?php selected($role, 'owner'); ?> value="owner"><?php esc_html_e('Owner', 'listeo_core') ?></option>
												<?php endif; ?>

											</select>
										<?php endif; ?>
									<?php endif; ?>

									<label for="first-name"><?php esc_html_e('First Name', 'listeo_core'); ?></label>
									<input class="text-input" name="first-name" type="text" id="first-name" value="<?php echo $current_user->user_firstname; ?>" />

									<label for="last-name"><?php esc_html_e('Last Name', 'listeo_core'); ?></label>
									<input class="text-input" name="last-name" type="text" id="last-name" value="<?php echo $current_user->user_lastname; ?>" />

									<label for="display_name"><?php esc_html_e('Display Name', 'listeo_core'); ?></label>
									<select name="display_name" id="display_name">
										<?php

										$public_display                     = array();
										$public_display['display_nickname'] = $current_user->nickname;
										$public_display['display_username'] = $current_user->user_login;

										if (!empty($current_user->first_name)) {
											$public_display['display_firstname'] = $current_user->first_name;
										}

										if (!empty($current_user->last_name)) {
											$public_display['display_lastname'] = $current_user->last_name;
										}

										if (!empty($current_user->first_name) && !empty($current_user->last_name)) {
											$public_display['display_firstlast'] = $current_user->first_name . ' ' . $current_user->last_name;
											$public_display['display_lastfirst'] = $current_user->last_name . ' ' . $current_user->first_name;
										}

										if (!in_array($current_user->display_name, $public_display, true)) { // Only add this if it isn't duplicated elsewhere.
											$public_display = array('display_displayname' => $current_user->display_name) + $public_display;
										}

										$public_display = array_map('trim', $public_display);
										$public_display = array_unique($public_display);

										?>
										<?php foreach ($public_display as $id => $item) : ?>
											<option <?php selected($current_user->display_name, $item); ?>><?php echo $item; ?></option>
										<?php endforeach; ?>
									</select>

									<?php if (isset($_GET['user_err_pass']) && !empty($_GET['user_err_pass'])) : ?>
										<div class="notification error closeable margin-top-35">
											<p>
												<?php
												switch ($_GET['user_err_pass']) {
													case 'error_1':
														echo esc_html_e('The Email you entered is not valid or empty. Please try again.', 'listeo_core');
														break;
													case 'error_2':
														echo esc_html_e('This email is already used by another user, please try a different one.', 'listeo_core');
														break;


													default:
														# code...
														break;
												}  ?>

											</p><a class="close" href="#"></a>
										</div>
									<?php endif; ?>
									<label for="email"><?php esc_html_e('E-mail', 'listeo_core'); ?></label>
									<input class="text-input" name="email" type="text" id="email" value="<?php the_author_meta('user_email', $current_user->ID); ?>" />

									<label for="description"><?php esc_html_e('About me', 'listeo_core'); ?></label>
									<?php
									$user_desc = get_the_author_meta('description', $current_user->ID);
									$user_desc_stripped = strip_tags($user_desc, '<p>'); //replace <p> and <a> with whatever tags you want to keep after the strip
									?>
									<textarea name="description" id="description" cols="30" rows="10"><?php echo $user_desc_stripped; ?></textarea>
									<!-- <?php
											$content = $user_desc;
											$editor_id = 'description';
											$args = array(
												'tinymce'       => array(
													'toolbar1'      => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink,undo,redo',
													'toolbar2'      => '',
													'toolbar3'      => '',
												),
											);
											//wp_editor($content, $editor_id, $args); 
											?> -->


									<!-- Rounded switch -->
									<label for="email_notifications"><?php esc_html_e('Turn off Email Notifications', 'listeo_core'); ?></label>
									<div class="switch_box box_1">

										<input type="checkbox" class="input-checkbox switch_1" name="email_notifications" id="email_notifications" value="on" <?php echo checked(get_the_author_meta('email_notifications', $current_user->ID), 'on')  ?> />
									</div>
									<?php if (class_exists('Listeo_SMS')) { ?>
										<label for="sms_notifications"><?php esc_html_e('Turn off SMS Notifications', 'listeo_core'); ?></label>
										<div class="switch_box box_1">
											<input type="checkbox" class="input-checkbox switch_1" name="sms_notifications" id="sms_notifications" value="on" <?php echo checked(get_the_author_meta('sms_notifications', $current_user->ID), 'on') ?> />
										</div>
									<?php } ?>

									<?php

									switch ($role) {
										case 'seller':
										case 'owner':
											$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
											break;
										case 'guest':
											$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_guest();
											break;
										default:
											$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
											break;
									}


									foreach ($fields as $key => $field) :

										$field['value'] = $current_user->$key;

										if ($field['type'] == 'header') { ?>
								</div>
							</div>
							<h4 class="gray submit-section-header"><?php echo $field['label']; ?></h4>
							<div class="dashboard-list-box-static">
								<div class="my-profile">

									<?php } else {

											if ($field['type'] != 'hidden') : ?>
										<label class="label-<?php echo esc_attr($key); ?>" for="<?php echo esc_attr($key); ?>">
											<?php if (isset($field['icon']) && !empty($field['icon'])) { ?>
												<i class="fa fa-<?php echo $field['icon']; ?>"></i>
											<?php } ?>
											<?php echo $field['label']; ?>
											<?php if (isset($field['tooltip']) && !empty($field['tooltip'])) { ?>
												<i class="tip" data-tip-content="<?php esc_attr_e($field['tooltip']); ?>"></i>
											<?php } ?>
										</label>
									<?php endif; ?>

							<?php
											$template_loader = new Listeo_Core_Template_Loader;

											// fix the name/id mistmatch
											if (isset($field['id'])) {
												$field['name'] = $field['id'];
											}
											if ($field['type'] == 'select_multiple') {

												$field['type'] = 'select';
												$field['multi'] = 'on';
											}
											if ($field['type'] == 'multicheck_split') {

												$field['type'] = 'checkboxes';
											}


											$template_loader->set_template_data(array('key' => $key, 'field' => $field,))->get_template_part('form-fields/' . $field['type']);
										}
									endforeach; ?>



							<?php wp_nonce_field('listeo_my_account_form', 'my_account_nonce'); ?>
							<input type="hidden" name="my-account-submission" value="1" />
							<button type="submit" form="edit_user" value="<?php esc_html_e('Submit', 'listeo_core'); ?>" class="button margin-top-20 margin-bottom-20"><?php esc_html_e('Save Changes', 'listeo_core'); ?></button>

						<?php endif; ?>



								</div>


							</div>
					</div>
			</div>


			</form>
			<!-- Change Password -->
			<div class="col-lg-6 col-md-12">
				<?php
				// Only show Zoom section if OAuth is configured
				$zoom_client_id = get_option('listeo_zoom_oauth_client_id');
				if (! empty($zoom_client_id)) :
				?>
					<div class="dashboard-list-box margin-top-0">

						<!-- Zoom Integration -->
						<div class="zoom-integration-section margin-top-0">
							<h4 class="gray"><?php esc_html_e('Zoom Integration', 'listeo_core'); ?></h4>
							<div class="dashboard-list-box-static">
								<p class=" margin-bottom-20"><?php esc_html_e('Connect your Zoom account to automatically create meetings for service bookings.', 'listeo_core'); ?></p>

								<?php
								// Get Zoom Integration instance
								$zoom_integration = new Listeo_Zoom_Integration();
								$is_connected = $zoom_integration->is_zoom_connected($current_user->ID);
								$zoom_user_email = get_user_meta($current_user->ID, 'zoom_user_email', true);

								// Check if user just connected
								if (isset($_GET['zoom_connected']) && $_GET['zoom_connected'] === '1') {
									echo '<div class="notification success closeable margin-bottom-20"><p>' . esc_html__('Zoom account connected successfully!', 'listeo_core') . '</p><a class="close"></a></div>';
								}
								?>

								<?php if ($is_connected) : ?>
									<div class="notification success margin-bottom-20">
										<p>
											<i class="sl sl-icon-check"></i>
											<strong><?php esc_html_e('Zoom Connected', 'listeo_core'); ?></strong>
											<?php if ($zoom_user_email) : ?>
												<br><span style="font-size: 14px;"><?php echo esc_html($zoom_user_email); ?></span>
											<?php endif; ?>
										</p>
									</div>
									<p>
										<a href="javascript:void(0);" id="listeo-zoom-disconnect-btn" class="button gray">
											<?php esc_html_e('Disconnect Zoom', 'listeo_core'); ?>
										</a>
									</p>
									<script>
										jQuery(document).ready(function($) {
											$('#listeo-zoom-disconnect-btn').on('click', function(e) {
												e.preventDefault();
												if (!confirm('<?php echo esc_js(__('Are you sure you want to disconnect your Zoom account?', 'listeo_core')); ?>')) {
													return;
												}

												var $btn = $(this);
												$btn.addClass('disabled').text('<?php echo esc_js(__('Disconnecting...', 'listeo_core')); ?>');

												$.ajax({
													url: listeo_core.ajax_url,
													type: 'POST',
													data: {
														action: 'listeo_zoom_disconnect',
														security: '<?php echo wp_create_nonce('listeo-zoom-disconnect'); ?>'
													},
													success: function(response) {
														if (response.success) {
															location.reload();
														} else {
															alert(response.data.message || '<?php echo esc_js(__('Failed to disconnect', 'listeo_core')); ?>');
															$btn.removeClass('disabled').text('<?php echo esc_js(__('Disconnect Zoom', 'listeo_core')); ?>');
														}
													},
													error: function() {
														alert('<?php echo esc_js(__('Failed to disconnect', 'listeo_core')); ?>');
														$btn.removeClass('disabled').text('<?php echo esc_js(__('Disconnect Zoom', 'listeo_core')); ?>');
													}
												});
											});
										});
									</script>
								<?php else : ?>
									<?php
									$connect_url = $zoom_integration->get_authorization_url($current_user->ID);
									if ($connect_url) : ?>
										<div class="notification warning margin-bottom-20">
											<p>
												<i class="sl sl-icon-exclamation"></i>
												<?php esc_html_e('Zoom not connected. Click below to authorize access.', 'listeo_core'); ?>
											</p>
										</div>
										<p>
											<a href="<?php echo esc_url($connect_url); ?>" class="button">
												<?php esc_html_e('Connect Zoom Account', 'listeo_core'); ?>
											</a>
										</p>
									<?php else : ?>
										<div class="notification error margin-bottom-20">
											<p>
												<i class="sl sl-icon-close"></i>
												<?php esc_html_e('Zoom OAuth is not configured. Please contact the site administrator.', 'listeo_core'); ?>
											</p>
										</div>
									<?php endif; ?>
								<?php endif; ?>

								<small class="margin-top-10" style="display: block;">
									<?php esc_html_e('Once connected, you can enable Zoom meetings for individual service listings. Meetings will be automatically created when bookings are confirmed.', 'listeo_core'); ?>
								</small>
							</div>
						</div>
					</div>
				<?php endif; // End Zoom OAuth check 
				?>


				<div class="dashboard-list-box <?php if (empty($zoom_client_id)) : ?> margin-top-0<?php else:?> margin-top-30 <?php endif; ?>">
					<h4 class="gray"><?php esc_html_e('Change Password', 'listeo_core') ?></h4>
					<div class="dashboard-list-box-static">

						<!-- Change Password -->
						<div class="my-profile">
							<div class="row">
								<div class="col-md-12">
									<div class="notification notice margin-top-0 margin-bottom-0">
										<p><?php esc_html_e('Your password should be at least 12 random characters long to be safe', 'listeo_core') ?></p>
									</div>
								</div>
							</div>
							<?php if (isset($_GET['updated_pass']) && $_GET['updated_pass'] == 'true') : ?>
								<div class="notification success closeable margin-bottom-35">
									<p><?php esc_html_e('Your password has been updated.', 'listeo_core'); ?></p><a class="close" href="#"></a>
								</div>
							<?php endif; ?>

							<?php if (isset($_GET['err_pass']) && !empty($_GET['err_pass'])) : ?>
								<div class="notification error closeable margin-bottom-35">
									<p>
										<?php
										switch ($_GET['err_pass']) {
											case 'error_1':
												echo esc_html_e('Your current password does not match. Please retry.', 'listeo_core');
												break;
											case 'error_2':
												echo esc_html_e('The passwords do not match. Please retry..', 'listeo_core');
												break;
											case 'error_3':
												echo esc_html_e('A bit short as a password, don\'t you think?', 'listeo_core');
												break;
											case 'error_4':
												echo esc_html_e('Password may not contain the character "\\" (backslash).', 'listeo_core');
												break;
											case 'error_5':
												echo esc_html_e('An error occurred while updating your profile. Please retry.', 'listeo_core');
												break;
											case 'error_6':
												echo esc_html_e('Please fill all password fields correctly.', 'listeo_core');
												break;

											default:
												# code...
												break;
										}  ?>

									</p><a class="close" href="#"></a>
								</div>
							<?php endif; ?>
							<form name="resetpasswordform" action="" method="post">
								<label><?php esc_html_e('Current Password', 'listeo_core'); ?></label>
								<input type="password" name="current_pass">

								<label for="pass1"><?php esc_html_e('New Password', 'listeo_core'); ?></label>
								<input name="pass1" type="password">

								<label for="pass2"><?php esc_html_e('Confirm New Password', 'listeo_core'); ?></label>
								<input name="pass2" type="password">

								<input type="submit" name="wp-submit" id="wp-submit" class="margin-top-20 button" value="<?php esc_html_e('Save Changes', 'listeo_core'); ?>" />

								<input type="hidden" name="listeo_core-password-change" value="1" />
							</form>

						</div>

					</div>
				</div>
			</div>
			<?php if (class_exists('plugin_delete_me')) : ?>
				<div class="col-lg-6 col-md-12 delete-account-section margin-top-40">
					<div class="dashboard-list-box margin-top-0">
						<h4 class="gray"><?php esc_html_e('Delete Your Account', 'listeo_core') ?></h4>
						<div class="dashboard-list-box-static">
							<?php echo do_shortcode('[plugin_delete_me /]'); ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

		</div>