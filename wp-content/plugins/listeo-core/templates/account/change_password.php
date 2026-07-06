<?php if(is_user_logged_in()) : ?>

<div class="col-md-8">
	<div class="row">
		<div class="col-md-6  my-profile">
		
			<h4 class="margin-top-0 margin-bottom-30"><?php esc_html_e('Change Password','listeo_core'); ?></h4>
			<?php if ( isset($_GET['updated']) && $_GET['updated'] == 'true' ) : ?> 
				<div class="notification success closeable margin-bottom-35"><p><?php esc_html_e('Your password has been updated.', 'listeo_core'); ?></p><a class="close" href="#"></a></div> 
			<?php endif; ?>

			<?php  if ( isset($_GET['err']) && !empty($_GET['err'])  ) : ?> 
				<div class="notification error closeable margin-bottom-35"><p>
					<?php
					switch ($_GET['err']) {
					 	case 'error_1':
					 		echo esc_html_e('Your current password does not match. Please retry.','listeo_core');
					 		break;
					 	case 'error_2':
					 		echo esc_html_e('The passwords do not match. Please retry..','listeo_core');
					 		break;					 	
					 	case 'error_3':
					 		echo esc_html_e('A bit short as a password, don\'t you think?','listeo_core');
					 		break;					 	
					 	case 'error_4':
					 		echo esc_html_e('Password may not contain the character "\\" (backslash).','listeo_core');
					 		break;
					 	case 'error_5':
					 		echo esc_html_e('An error occurred while updating your profile. Please retry.','listeo_core');
					 		break;
					 	
					 	default:
					 		# code...
					 		break;
					 }  ?>
						
					</p><a class="close" href="#"></a></div> 
			<?php endif; ?>
			<form name="resetpasswordform" action="" method="post">
				<label><?php esc_html_e('Current Password','listeo_core'); ?></label>
				<input type="password" name="current_pass">

				<label for="pass1"><?php esc_html_e('New Password','listeo_core'); ?></label>
				<input name="pass1" type="password">

				<label for="pass2"><?php esc_html_e('Confirm New Password','listeo_core'); ?></label>
				<input name="pass2" type="password">

				<input type="submit" name="wp-submit" id="wp-submit" class="margin-top-20 button" value="<?php esc_html_e('Save Changes','listeo_core'); ?>" />
				
				<input type="hidden" name="listeo_core-password-change" value="1" />
			</form>

		</div>

		<div class="col-md-6">
			<div class="notification notice">
				<p><?php esc_html_e('Your password should be at least 12 random characters long to be safe','listeo_core') ?></p>
			</div>
		</div>

	</div>
</div>
<?php endif; ?>