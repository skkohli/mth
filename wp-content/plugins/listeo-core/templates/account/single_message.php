<?php $ids = '';
//user_1 ten co wysyla
//user_2 ten co dostaje
if(isset($data)) :
	$ids	 	= (isset($data->ids)) ? $data->ids : '' ;
endif;
if( isset( $_GET["action"]) && $_GET["action"] == 'view' )  {

	$messages = new Listeo_Core_Messages();

	//check if user can

	$conversation_id = $_GET["conv_id"]; 
	
	$current_user_id = get_current_user_id();
	
	// get this conversation data
	$this_conv = $messages->get_conversation($conversation_id);	
	if(!$this_conv) { ?>
		<h4><?php esc_html_e('This message does not exists.','listeo_core'); ?></h4>
		<?php return;
	}
	if($current_user_id == (int)$this_conv[0]->user_1 || $current_user_id == (int)$this_conv[0]->user_2 ) :

		$is_visible = $messages->conversation_visible_for_user($this_conv, $current_user_id);
		if ( ! $is_visible ) {
			$restore_url = add_query_arg( array( 'action' => 'restore', 'conv_id' => (int) $conversation_id ), get_permalink( get_option( 'listeo_messages_page' ) ) );
			?>
			<div class="notification warning margin-top-0"><p><?php echo wp_kses_post( sprintf( __( 'This conversation is hidden. %1$sRestore it%2$s to see previous messages.', 'listeo_core' ), '<a href="' . esc_url( $restore_url ) . '">', '</a>' ) ); ?></p></div>
			<?php
			return;
		}

		// mark this message as read
		$messages->mark_as_read($conversation_id);	
		
		// set who is adversary on that converstation
		$adversary = ($this_conv[0]->user_1 == $current_user_id) ? $this_conv[0]->user_2 : $this_conv[0]->user_1 ;
		$recipient = get_userdata( $adversary ); 
		if(!$recipient){
			$name = esc_html__('User has been removed','listeo_core');
		} else {
			if(empty($recipient->first_name) && empty($recipient->last_name)) {
			$name = $recipient->user_nicename;
		} else {
			$name = $recipient->first_name .' '.$recipient->last_name;
		} 
		
		}
		
		$referral = $messages->get_conversation_referral($this_conv[0]->referral);
		
		?>
		<div class="messages-container margin-top-0">
			<div class="messages-headline">
					<h4><?php echo esc_html($name); ?><?php if($referral) : ?> <span><?php echo ($referral);  ?></span><?php endif; ?></h4>
					<a href="?action=delete&conv_id=<?php echo esc_attr($conversation_id); ?>" class="message-action" id="message-delete"><i class="sl sl-icon-eye-off"></i> <?php esc_html_e('Hide Conversation', 'listeo_core' ); ?></a>
			</div>

			<div class="messages-container-inner">

				<!-- Messages -->
				<div class="messages-inbox">
					<?php if($ids) { ?>
					<ul>
						<?php 

							foreach ($ids as $key => $conversation) {
									
									$message_url = add_query_arg( array( 'action' => 'view',  'conv_id' => $conversation->id ), get_permalink( get_option( 'listeo_messages_page' )) );
		
									$last_msg = $messages->get_last_message($conversation->id);
									$conversation_data = $messages->get_conversation($conversation->id);	

									$if_read = $messages->check_if_read($conversation_data);

									$_conv_list_adversary = ($conversation_data[0]->user_1 == $current_user_id) ? $conversation_data[0]->user_2 : $conversation_data[0]->user_1 ;	
									$user_data = get_userdata( $_conv_list_adversary );

									$referral = $messages->get_conversation_referral($conversation->referral);
								?>
								<li <?php if(!$if_read) : ?>class="unread"<?php endif; ?>>
									<a href="<?php echo esc_url($message_url) ?>">
										<div class="message-avatar"><?php echo get_avatar($_conv_list_adversary, '70') ?></div>
					
										<div class="message-by">
											<div class="message-by-headline">
												<?php
												if(!$user_data){
													$name = esc_html__('User has been removed','listeo_core');
												} else {
												if(empty($user_data->first_name) && empty($user_data->last_name)) {
													$name = $user_data->user_nicename;
												} else {
													$name = $user_data->first_name .' '.$user_data->last_name;
												} } ?>
												<h5><?php echo esc_html($name); ?>
												<?php if(!$if_read) : ?><i><?php esc_html_e('Unread','listeo_core') ?></i><?php endif; ?>
											</h5>
											<span><?php echo human_time_diff( $last_msg[0]->created_at, current_time('timestamp')  );  ?></span>
											</div>
											<p>
												<?php if($referral) : echo esc_html(strip_tags($referral)); endif; ?>
												<?php 
											//echo $last_msg[0]->message;
											 ?></p>
										</div>
									</a>
								</li>
							<?php } ?>
						</ul>
					<?php } ?>
					
				
				</div>
				<!-- Messages / End -->

				<!-- Message Content -->
				<div class="message-content">
					<div class="message-bubbles">
						<?php
							$conversation = $messages->get_single_conversation($current_user_id,$conversation_id);
							foreach ($conversation as $key => $message) {
								?>
								<div class="message-bubble <?php if($current_user_id == (int) $message->sender_id ) echo esc_attr('me'); ?>">
									<div class="message-avatar"><a href="<?php echo esc_url(get_author_posts_url($message->sender_id)); ?>"><?php echo get_avatar($message->sender_id, '70') ?></a></div>
									<div class="message-text">
										<?php if (!empty($message->message)) : ?>
											<?php echo wpautop(esc_html(Listeo_Core_Messages::filter_contact_info($message->message))); ?>
										<?php endif; ?>
										<?php if (!empty($message->attachment_id)) :
											$attachment_url = admin_url('admin-ajax.php?action=listeo_download_attachment&attachment_id=' . $message->attachment_id . '&message_id=' . $message->id);
											$file_ext = pathinfo($message->attachment_name, PATHINFO_EXTENSION);
											$file_icon = 'fa-file';
											// Set icon based on file type
											if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
												$file_icon = 'fa-file-image';
											} elseif ($file_ext == 'pdf') {
												$file_icon = 'fa-file-pdf';
											} elseif (in_array($file_ext, ['doc', 'docx'])) {
												$file_icon = 'fa-file-word';
											} elseif (in_array($file_ext, ['xls', 'xlsx'])) {
												$file_icon = 'fa-file-excel';
											} elseif (in_array($file_ext, ['zip', 'rar'])) {
												$file_icon = 'fa-file-archive';
											}
											?>
											<div class="message-attachment">
												<a href="<?php echo esc_url($attachment_url); ?>" class="attachment-link" target="_blank">
													<i class="fa <?php echo esc_attr($file_icon); ?>"></i>
													<span class="attachment-name"><?php echo esc_html($message->attachment_name); ?></span>
													<?php if ($message->attachment_size) : ?>
														<span class="attachment-size">(<?php echo size_format($message->attachment_size); ?>)</span>
													<?php endif; ?>
												</a>
											</div>
										<?php endif; ?>
										<?php if (apply_filters('listeo_show_message_timestamp', true)) : ?>
											<span class="message-time"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $message->created_at)); ?></span>
										<?php endif; ?>
									</div>
								</div>
							<?php }
						?>
						
					</div>
					<img style="display: none; " src="<?php echo get_template_directory_uri(); ?>/images/loader.gif" alt="" class="loading">
					<!-- Reply Area -->
					<div class="clearfix"></div>
					<div class="message-reply">
						<form action="" id="send-message-from-chat" enctype="multipart/form-data">
							<textarea cols="40" id="contact-message" name="message" rows="3" placeholder="<?php esc_html_e('Your Message', 'listeo_core'); ?>"></textarea>

							<?php if (get_option('listeo_message_attachments', 'on') === 'on') : ?>
							<!-- File attachment input -->
							<div class="message-attachment-upload">
								<label for="message-attachment" class="attachment-label" data-tooltip="<?php esc_attr_e('Formats: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX, TXT, ZIP, RAR. Max: 10MB', 'listeo_core'); ?>">
									<i class="fa fa-paperclip"></i>
									<span><?php esc_html_e('Attach file', 'listeo_core'); ?></span>
									<input type="file" id="message-attachment" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" style="display:none;">
								</label>
								<div class="attachment-preview" style="display:none;">
									<span class="attachment-preview-name"></span>
									<a href="#" class="remove-attachment"><i class="fa fa-times"></i></a>
								</div>
								<div class="attachment-upload-progress" style="display:none;">
									<div class="progress-bar"><div class="progress-fill"></div></div>
								</div>
							</div>
							<?php endif; ?>

							<input type="hidden" id="conversation_id" name="conversation_id" value="<?php echo esc_attr($_GET["conv_id"]) ?>">
							<input type="hidden" id="recipient" name="recipient" value="<?php echo esc_attr($adversary) ?>">
							<input type="hidden" id="attachment_id" name="attachment_id" value="">
							<?php wp_nonce_field('listeo_messages_nonce', 'messages_nonce'); ?>
							<button class="button"><?php esc_html_e('Send Message', 'listeo_core'); ?></button>
						</form>
					</div>
					
				</div>
				<!-- Message Content -->

			</div>

		</div>
	<?php else: ?>
		<?php esc_html_e("It's not your converstation!",'listeo_core'); ?>
	<?php endif; ?>
<?php } else {
	die();
} ?>
