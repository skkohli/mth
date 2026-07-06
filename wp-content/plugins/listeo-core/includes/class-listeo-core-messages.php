<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;
/**
 * Listeo_Core_Messages class
 */
class Listeo_Core_Messages {

	public function __construct() {

        add_shortcode( 'listeo_messages', array( $this, 'listeo_messages' ) );
        add_action('wp_ajax_listeo_send_message', array($this, 'send_message_ajax'));
        add_action('wp_ajax_listeo_send_message_chat', array($this, 'send_message_ajax_chat'));
		add_action('wp_ajax_listeo_get_conversation', array($this, 'get_conversation_ajax'));
		add_action('wp_ajax_listeo_upload_message_attachment', array($this, 'upload_message_attachment_ajax'));
		add_action('wp_ajax_listeo_download_attachment', array($this, 'download_attachment'));

        add_action( 'listeo_core_check_for_new_messages', array( $this, 'check_for_new_messages' ) );

        // enqueue script only on messages page
        add_action( 'wp_enqueue_scripts', function() {
            if ( is_page( get_option( 'listeo_messages_page' ) ) ) {
                if(get_option('listeo_chat_filter','on') == 'on') {
                    
                    wp_enqueue_script( 'listeo-core-chat-filter', LISTEO_CORE_URL . 'assets/js/chatfilter.js', array( 'jquery' ), '1.0', true );
                }
                
                
            }
        } );
	}

    /**
     * Filter contact information from message text (server-side).
     * Mirrors the patterns in chatfilter.js to prevent flash of unfiltered content.
     *
     * @param string $text Raw message text
     * @return string Filtered text with contact info replaced by asterisks
     */
    public static function filter_contact_info( $text ) {
        if ( get_option( 'listeo_chat_filter', 'on' ) !== 'on' ) {
            return $text;
        }

        $patterns = array(
            // Email patterns
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/',
            '/\b[A-Za-z0-9._%+\-]+\s*@\s*[A-Za-z0-9.\-]+\s*\.\s*[A-Za-z]{2,}\b/',
            '/\b[A-Za-z0-9._%+\-]+\s*\[\s*at\s*\]\s*[A-Za-z0-9.\-]+\s*\[\s*dot\s*\]\s*[A-Za-z]{2,}\b/i',
            '/\b[A-Za-z0-9._%+\-]+\s*(?:at|AT)\s+[A-Za-z0-9.\-]+\s+(?:dot|DOT)\s+[A-Za-z]{2,}\b/',
            // Phone patterns
            '/(?:\+?\d{1,4}[\s\-\(\)]?)?\(?\d{3,4}\)?[\s\-\.]\d{3,4}[\s\-\.]\d{3,6}/',
            '/\b\d{3}[\-.\s]?\d{3}[\-.\s]?\d{4}\b/',
            '/\b\d{10,15}\b/',
            // URLs
            '/https?:\/\/(?:www\.)?[\-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b[\-a-zA-Z0-9()@:%_\+.~#?&\/=]*/',
            '/www\.[\-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b[\-a-zA-Z0-9()@:%_\+.~#?&\/=]*/',
            // Social handles
            '/@[A-Za-z0-9_]{3,}/',
            '/\b(?:instagram|insta|ig|facebook|fb|twitter|telegram|whatsapp|snapchat|tiktok|discord)\s*[:=]?\s*[A-Za-z0-9_.]{3,}/i',
            // Messaging apps
            '/\b(?:skype|whatsapp|telegram|signal)\s*[:=]?\s*[A-Za-z0-9_.+\-]{3,}/i',
        );

        foreach ( $patterns as $pattern ) {
            $text = preg_replace_callback( $pattern, function( $match ) {
                return str_repeat( '*', max( 3, strlen( $match[0] ) ) );
            }, $text );
        }

        return $text;
    }

    /**
     * Maintenance task to expire listings.
     */
    public function check_for_new_messages() {
        global $wpdb;
        $date_format = get_option('date_format');
       
        //  global $wpdb;
        // if ( $limit != '' ) $limit = " LIMIT " . esc_sql($limit);
        
        // if ( is_numeric($offset)) $offset = " OFFSET " . esc_sql($offset);

        // $result  = $wpdb -> get_results( "
        // SELECT * FROM `" . $wpdb->prefix . "listeo_core_conversations` 
        // WHERE  user_1 = '$user_id' OR user_2 = '$user_id'
        // ORDER BY last_update DESC $limit $offset
        // ");
        
        // return $result;

        // Notifie expiring in 5 days
       $conversation_ids = $wpdb->get_col( $wpdb->prepare( "
                SELECT id FROM {$wpdb->prefix}listeo_core_conversations
                WHERE (read_user_1 = '0'
                OR read_user_2 = '0' )
                AND notification != 'sent'
                AND last_update < %s
            ",
            current_time('timestamp', 1) - (15 * 60)
        )
        );


        if ( $conversation_ids ) {
            foreach ( $conversation_ids as $conversation_id ) {
                
                do_action('listeo_mail_to_user_new_message',$conversation_id);
            }
        }
  
    }

	public  function start_conversation( $args = 0 )  {

        global $wpdb;

        // TODO: filter by parameters
        $read_user_1 = '1';
        $read_user_2 = '0';

        $result =  $wpdb->insert( 
            $wpdb->prefix . 'listeo_core_conversations', 
            array(
                'user_1' => get_current_user_id(), //sender
                'user_2' => $args['recipient'], // recipeint
                'referral' => $args['referral'],
                'timestamp' => current_time( 'timestamp' ),
                'read_user_1' => $read_user_1, //sender already read
                'read_user_2' => $read_user_2,
                'notification' => '',
            ),
            array( 
                '%d',
                '%d', 
                '%s', 
                '%d',
                '%d',
                '%d',
            ) 
        );
        
        if(isset($wpdb->insert_id)) {
            $id = $wpdb->insert_id;
            $mail_args = array(
                'conversation_id' => $id,
            );
            do_action('listeo_mail_to_user_new_conversation',$mail_args);
        } else {
            $id = false;
        }

        return $id;
    }

    public  function send_new_message( $args = 0 )  {

        global $wpdb;

        // TODO: filter by parameters

        $data = array(
            'conversation_id' 	=> $args['conversation_id'],
            'sender_id' 		=> $args['sender_id'],
            'message' 			=> stripslashes_deep($args['message']),
            'created_at' 		=> current_time( 'timestamp' ),
        );

        // Add attachment data if provided
        if (!empty($args['attachment_id'])) {
            $data['attachment_id'] = $args['attachment_id'];
            $data['attachment_url'] = $args['attachment_url'];
            $data['attachment_name'] = $args['attachment_name'];
            $data['attachment_type'] = $args['attachment_type'];
            $data['attachment_size'] = $args['attachment_size'];
        }

        $result =  $wpdb -> insert( $wpdb->prefix . 'listeo_core_messages', $data);

		if(isset($wpdb->insert_id)) {
			$id = $wpdb->insert_id;
			$conversation = $this->get_conversation($args['conversation_id']);
			$this->restore_conversation_visibility($args['conversation_id']);
			if($conversation[0]->user_1 == $args['sender_id']) {
                $user = 'user_2';
            } else {
                $user = 'user_1';
            }
           $this->mark_as_unread($user,$args['conversation_id']);
           $this->converstation_update_date($args['conversation_id']);
        } else {
            $id = false;
        }
        return $id;
    }

    public function send_message_ajax() {

        $recipient = sanitize_text_field($_REQUEST['recipient']);
        $referral = sanitize_text_field($_REQUEST['referral']);
        $message = sanitize_textarea_field($_REQUEST['message']);
        $conv_arr = array();
        
        $conv_arr['recipient'] = $recipient;
        $conv_arr['referral'] = $referral;
        $conv_arr['message'] = $message;
        //check if conv exists
        $con_exists = $this->conversation_exists($recipient,$referral);
        $new_converstation  = ($con_exists) ? $con_exists : $this->start_conversation($conv_arr) ;
        

        if($new_converstation){
            $message = $_REQUEST['message'];
            $mess_arr = array();
            $mess_arr['conversation_id'] = $new_converstation;
            $mess_arr['sender_id'] = get_current_user_id();
            $mess_arr['message'] = $message;
            $id = $this->send_new_message($mess_arr);
        }

        if($id) {
            $result['type'] = 'success';
            $result['message'] = __( 'Your message was successfully sent' , 'listeo_core' );
        } else {
            $result['type'] = 'error';
            $result['message'] = __( 'Message couldn\'t be send' , 'listeo_core' );
        }

        $result = json_encode($result);
        echo $result;      
        die();
    }

    public function send_message_ajax_chat() {

        $conversation_id = sanitize_text_field($_REQUEST['conversation_id']);
    	$message = sanitize_textarea_field($_REQUEST['message']);
        if(empty($message) && empty($_REQUEST['attachment_id'])){
            $result['type'] = 'error';
            $result['message'] = __( 'Empty message' , 'listeo_core' );
            $result = json_encode($result);
            echo $result;

            die();
        }
        if(empty($conversation_id)){
            $result['type'] = 'error';
            $result['message'] = __( 'Whoops, we have a problem' , 'listeo_core' );
            $result = json_encode($result);
            echo $result;

            die();
        }

    	$mess_arr['conversation_id'] = $conversation_id;
    	$mess_arr['sender_id'] = get_current_user_id();
    	$mess_arr['message'] = $message;

        // Handle attachment if provided
        if (!empty($_REQUEST['attachment_id'])) {
            $attachment_id = intval($_REQUEST['attachment_id']);
            $attachment = get_post($attachment_id);

            if ($attachment && $attachment->post_type === 'attachment') {
                $mess_arr['attachment_id'] = $attachment_id;
                $mess_arr['attachment_url'] = wp_get_attachment_url($attachment_id);
                $mess_arr['attachment_name'] = basename(get_attached_file($attachment_id));
                $mess_arr['attachment_type'] = $attachment->post_mime_type;
                $mess_arr['attachment_size'] = filesize(get_attached_file($attachment_id));
            }
        }

    	$id = $this->send_new_message($mess_arr);

        if($id) {
            $result['type'] = 'success';
            $result['message'] = __( 'Your message was successfully sent' , 'listeo_core' );
        } else {
            $result['type'] = 'error';
            $result['message'] = __( 'Message couldn\'t be send' , 'listeo_core' );
        }

        $result = json_encode($result);
        echo $result;

	    die();
    }

   /**
	* Get user conversations
	*
    *
	*/
	public function get_conversations( $user_id, $limit = '', $offset = '')  {

        global $wpdb;
        if ( $limit != '' ) $limit = " LIMIT " . esc_sql($limit);
        
        if ( is_numeric($offset)) $offset = " OFFSET " . esc_sql($offset);

		$table_name = $wpdb->prefix . 'listeo_core_conversations';
		if ( $this->deleted_flags_supported() ) {
			$sql = "
		SELECT * FROM `{$table_name}`
		WHERE (user_1 = %d AND COALESCE(deleted_user_1, 0) = 0)
		   OR (user_2 = %d AND COALESCE(deleted_user_2, 0) = 0)
		ORDER BY last_update DESC {$limit} {$offset}
	";
		} else {
			$sql = "
		SELECT * FROM `{$table_name}`
		WHERE user_1 = %d OR user_2 = %d
		ORDER BY last_update DESC {$limit} {$offset}
	";
		}

        $result = $wpdb->get_results(
            $wpdb->prepare($sql, (int)$user_id, (int)$user_id),
            
        );
        
        return $result;
    }

    public function delete_conversations( $conv_id ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $conversation = $this->get_conversation($conv_id);
        
		if($conversation){
			$conversation_row = $conversation[0];
			if($conversation_row->user_1 == $user_id || $conversation_row->user_2 == $user_id ){
				if ( ! $this->deleted_flags_supported() ) {
					$result = $wpdb->delete( $wpdb->prefix . 'listeo_core_conversations', array( 'id' => $conv_id) );
					$wpdb->delete( $wpdb->prefix . 'listeo_core_messages', array( 'conversation_id' => $conv_id) );
					return (bool) $result;
				}

				$delete_column = ($conversation_row->user_1 == $user_id) ? 'deleted_user_1' : 'deleted_user_2';
				$read_column   = ($conversation_row->user_1 == $user_id) ? 'read_user_1' : 'read_user_2';

				$update = $wpdb->update(
					$wpdb->prefix . 'listeo_core_conversations',
					array(
						$delete_column => 1,
						$read_column   => 1,
					),
					array( 'id' => $conv_id )
				);

				if ( false === $update ) {
					return false;
				}

				$other_flag = ($delete_column === 'deleted_user_1')
					? (int) (isset($conversation_row->deleted_user_2) ? $conversation_row->deleted_user_2 : 0)
					: (int) (isset($conversation_row->deleted_user_1) ? $conversation_row->deleted_user_1 : 0);

				if ( 1 === $other_flag ) {
					$wpdb->delete( $wpdb->prefix . 'listeo_core_conversations', array( 'id' => $conv_id ) );
					$wpdb->delete( $wpdb->prefix . 'listeo_core_messages', array( 'conversation_id' => $conv_id ) );
				}

				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

//     public function get_conversations_by_latest(){
//         global $wpdb
// SELECT conv.id FROM wp_listeo_core_conversations AS conv LEFT JOIN wp_listeo_core_messages AS mes ON conv.id=mes.conversation_id  order by mes.created_at DESC 
//     }

    public function get_conversation_ajax() {
		$conversation_id = sanitize_text_field($_REQUEST['conversation_id']);
		if(!$conversation_id) {
			return;
			die();
		}
			$user_id = get_current_user_id();
			$conversation_meta = $this->get_conversation($conversation_id);
			if ( empty($conversation_meta) || ! $this->conversation_visible_for_user($conversation_meta, $user_id) ) {
				$result['type'] = 'error';
				$result['message'] = esc_html__( 'Conversation is not available.', 'listeo_core' );
				echo json_encode( $result );
				die();
			}
			$conversation = $this->get_single_conversation($user_id,$conversation_id);

            ob_start();
            foreach ($conversation as $key => $message) { ?>
                <div class="message-bubble <?php if($user_id == $message->sender_id ) echo esc_attr('me'); ?>">
                    <div class="message-avatar"><a href="<?php echo esc_url(get_author_posts_url($message->sender_id)); ?>"><?php echo get_avatar($message->sender_id, '70') ?></a></div>
                    <div class="message-text">
                        <?php if (!empty($message->message)) : ?>
                            <?php echo wpautop(esc_html(self::filter_contact_info($message->message))); ?>
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
            $output = ob_get_clean();

            $result['type'] = 'success';
            $result['message'] = $output;
            $result = json_encode($result);
            echo $result;
        die();
    }

    public function get_conversation( $conversation_id)  {

        global $wpdb;

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}listeo_core_conversations` WHERE id = %d",
                (int) $conversation_id
            ),
            
        );

        return $result;

    }

    public  function get_single_conversation( $user_id, $conversation_id)  {

        global $wpdb;

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}listeo_core_messages` 
                 WHERE conversation_id = %d 
                 ORDER BY created_at ASC",
                (int) $conversation_id
            ),
           
        );

        return $result;

    }

    public function get_conversation_referral($referral)
    {
        if ($referral) {
            //$referral = $conversation[0]->referral;

            if (strpos($referral, 'listing_') !== false) {

                $listing_id = str_replace('listing_', '', $referral);
                // return title with link
                return "<a href='" . get_permalink($listing_id) . "'>" . get_the_title($listing_id) . "</a>";

                //return get_the_title($listing_id);
            }
            if (strpos($referral, 'booking_') !== false) {

                $booking_id = str_replace('booking_', '', $referral);
                $bookings = new Listeo_Core_Bookings_Calendar;
                $booking_data = $bookings->get_booking($booking_id);
                if ($booking_data) {
                    $title = get_the_title($booking_data['listing_id']);
                    $status = $booking_data['status'];

                    return __('Reservation for ', 'listeo_core') . $title;
                } else {
                    return __('No referral', 'listeo_core');
                }
            } else {
                return __('No referral', 'listeo_core');
            }
        }
    }

    

    /**
    * Get user conversations
    *
    *
    */
    public  function get_last_message( $conversation)  {

        global $wpdb;

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}listeo_core_messages`
                 WHERE conversation_id = %d
                 ORDER BY created_at DESC LIMIT 1",
                (int)$conversation
            ),
           
        );

        return $result;

    }

    /**
    * Mark as read
    *
    *
    */
    public  function mark_as_read( $conversation)  {

        global $wpdb;
        
        $conv = $this->get_conversation($conversation);
        
        if($conv[0]->user_1 == get_current_user_id()) {
            $user = 'user_1';
        } else {
            $user = 'user_2';
        }

        $result  = $wpdb->update( 
            $wpdb->prefix . 'listeo_core_conversations', 
            array( 'read_'.$user  => 1 ), 
            array( 'id' => $conversation ) 
        );
        
        return $result;
    }
    /**
    * Mark as unread
    *
    *
    */
    public  function converstation_update_date( $conversation )  {

        global $wpdb;

        $result  = $wpdb->update( 
            $wpdb->prefix . 'listeo_core_conversations', 
            array( 'last_update' => current_time( 'timestamp',1 ) ), 
            array( 'id' => $conversation ) 
        );
        
        return $result;
    } 

    /**
    * Mark as unread
    *
    *
    */
    public  function mark_as_unread( $user, $conversation)  {

        global $wpdb;
        
        $result  = $wpdb->update( 
            $wpdb->prefix . 'listeo_core_conversations', 
            array( 'read_'.$user => 0,  'notification' => ''  ), 
            array( 'id' => $conversation )
            
        );
        
        return $result;
    }  

    /**
	* Check if read
	*
    *
	*/
	public  function check_if_read( $conversation_data)  {
        $user_id = get_current_user_id();
       
        if(isset($conversation_data)){
            if( (string) $conversation_data[0]->user_1 == $user_id){
                return $conversation_data[0]->read_user_1;
            } else {
                return $conversation_data[0]->read_user_2;
            }
        }
        
    }

	public function conversation_exists($recipient,$referral){
		global $wpdb;
	    $user_id = get_current_user_id();
	    $table_name = $wpdb->prefix . 'listeo_core_conversations';

	    $conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE referral = %s 
				AND ((user_1 = %d AND user_2 = %d) OR (user_1 = %d AND user_2 = %d))
				LIMIT 1",
				$referral,
				$user_id,
				$recipient,
				$recipient,
				$user_id
			)
		);

	    if ( $conversation ) {
	    	if ( $this->deleted_flags_supported() ) {
	    		$this->restore_conversation_visibility( (int) $conversation->id, $user_id );
	    	}

	    	return (int) $conversation->id;
	    }

	    return false;
	}

	private function deleted_flags_supported() {
		static $supported = null;
		if ( null !== $supported ) {
			return $supported;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'listeo_core_conversations';
		$supported = ! empty( $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE 'deleted_user_1'" ) );
		return $supported;
	}

	private function restore_conversation_visibility( $conversation_id, $user_id = null ) {
		if ( ! $this->deleted_flags_supported() ) {
			return false;
		}

		global $wpdb;
		$data = array();

		if ( null === $user_id ) {
			$data = array(
				'deleted_user_1' => 0,
				'deleted_user_2' => 0,
			);
		} else {
			$conversation = $this->get_conversation( $conversation_id );
			if ( empty( $conversation ) ) {
				return false;
			}
			$row = $conversation[0];
			if ( (int) $row->user_1 === (int) $user_id ) {
				$data['deleted_user_1'] = 0;
			} elseif ( (int) $row->user_2 === (int) $user_id ) {
				$data['deleted_user_2'] = 0;
			} else {
				return false;
			}
		}

		if ( empty( $data ) ) {
			return false;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'listeo_core_conversations',
			$data,
			array( 'id' => $conversation_id )
		);

		return false !== $updated;
	}

	public function conversation_visible_for_user( $conversation, $user_id ) {
		if ( ! $this->deleted_flags_supported() ) {
			return true;
		}

		if ( empty( $conversation ) ) {
			return false;
		}

		if ( is_array( $conversation ) ) {
			$conversation = reset( $conversation );
		}

		if ( ! $conversation ) {
			return false;
		}

		if ( (int) $conversation->user_1 === (int) $user_id ) {
			$flag = isset( $conversation->deleted_user_1 ) ? (int) $conversation->deleted_user_1 : 0;
			return 0 === $flag;
		}

		if ( (int) $conversation->user_2 === (int) $user_id ) {
			$flag = isset( $conversation->deleted_user_2 ) ? (int) $conversation->deleted_user_2 : 0;
			return 0 === $flag;
		}

		return false;
	}
	/**
	 * Handle attachment upload via AJAX
	 */
	public function upload_message_attachment_ajax() {
		// Check if attachments are enabled
		if (get_option('listeo_message_attachments', 'on') !== 'on') {
			wp_send_json_error(__('Message attachments are disabled', 'listeo_core'));
		}

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(__('You must be logged in to upload attachments', 'listeo_core'));
		}

		// Check nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo_messages_nonce')) {
			wp_send_json_error(__('Security check failed', 'listeo_core'));
		}

		// Check conversation ID and verify user is part of it
		if (!isset($_POST['conversation_id'])) {
			wp_send_json_error(__('Invalid conversation', 'listeo_core'));
		}

		$conversation_id = intval($_POST['conversation_id']);
		$conversation = $this->get_conversation($conversation_id);
		$user_id = get_current_user_id();

		if (!$conversation || ($conversation[0]->user_1 != $user_id && $conversation[0]->user_2 != $user_id)) {
			wp_send_json_error(__('You are not authorized to upload to this conversation', 'listeo_core'));
		}

		// Check if file was uploaded
		if (empty($_FILES['attachment'])) {
			wp_send_json_error(__('No file uploaded', 'listeo_core'));
		}

		// Allowed file types
		$allowed_types = array(
			'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx',
			'xls', 'xlsx', 'txt', 'zip', 'rar'
		);

		$file = $_FILES['attachment'];
		$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		// Validate file type
		if (!in_array($file_ext, $allowed_types)) {
			wp_send_json_error(__('File type not allowed', 'listeo_core'));
		}

		// Validate file size (10MB max)
		$max_size = 10 * 1024 * 1024; // 10MB in bytes
		if ($file['size'] > $max_size) {
			wp_send_json_error(__('File size exceeds 10MB limit', 'listeo_core'));
		}

		// Handle file upload
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// Upload file to media library
		$upload_overrides = array('test_form' => false);
		$movefile = wp_handle_upload($file, $upload_overrides);

		if ($movefile && !isset($movefile['error'])) {
			// Create attachment
			$attachment = array(
				'post_mime_type' => $movefile['type'],
				'post_title' => sanitize_file_name($file['name']),
				'post_content' => '',
				'post_status' => 'inherit'
			);

			$attach_id = wp_insert_attachment($attachment, $movefile['file']);

			if (!is_wp_error($attach_id)) {
				// Generate attachment metadata
				$attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
				wp_update_attachment_metadata($attach_id, $attach_data);

				// Return success with attachment info
				wp_send_json_success(array(
					'attachment_id' => $attach_id,
					'attachment_url' => wp_get_attachment_url($attach_id),
					'attachment_name' => basename($movefile['file']),
					'attachment_type' => $movefile['type'],
					'attachment_size' => $file['size']
				));
			} else {
				wp_send_json_error(__('Failed to create attachment', 'listeo_core'));
			}
		} else {
			$error = isset($movefile['error']) ? $movefile['error'] : __('Upload failed', 'listeo_core');
			wp_send_json_error($error);
		}
	}

	/**
	 * Handle secure attachment download
	 */
	public function download_attachment() {
		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_die(__('You must be logged in to download attachments', 'listeo_core'));
		}

		// Get attachment and message info
		if (!isset($_GET['attachment_id']) || !isset($_GET['message_id'])) {
			wp_die(__('Invalid attachment request', 'listeo_core'));
		}

		$attachment_id = intval($_GET['attachment_id']);
		$message_id = intval($_GET['message_id']);
		$user_id = get_current_user_id();

		// Get message from database
		global $wpdb;
		$message = $wpdb->get_row($wpdb->prepare(
			"SELECT m.*, c.user_1, c.user_2
			FROM {$wpdb->prefix}listeo_core_messages m
			JOIN {$wpdb->prefix}listeo_core_conversations c ON m.conversation_id = c.id
			WHERE m.id = %d AND m.attachment_id = %d",
			$message_id,
			$attachment_id
		));

		// Verify user has access to this attachment
		if (!$message || ($message->user_1 != $user_id && $message->user_2 != $user_id)) {
			wp_die(__('You do not have permission to download this attachment', 'listeo_core'));
		}

		// Get file path
		$file_path = get_attached_file($attachment_id);

		if (!file_exists($file_path)) {
			wp_die(__('File not found', 'listeo_core'));
		}

		// Serve file for download
		$file_name = basename($file_path);
		$file_type = wp_check_filetype($file_path);

		header('Content-Type: ' . $file_type['type']);
		header('Content-Disposition: attachment; filename="' . $file_name . '"');
		header('Content-Length: ' . filesize($file_path));
		header('Cache-Control: must-revalidate');
		header('Pragma: public');

		readfile($file_path);
		exit;
	}

	/**
	 * User messages shortcode
	 */
	public function listeo_messages( $atts ) {
		
		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to manage your messages.', 'listeo_core' );
		}

		$user_id = get_current_user_id();
	
		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );
        $limit = 7;
        $page = (isset($_GET['messages-page'])) ? $_GET['messages-page'] : 1;
        
        $offset = ( absint( $page ) - 1 ) * absint( $limit );
        
		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;
        if( isset( $_GET["action"]) && $_GET["action"] == 'view' )  {
            $template_loader->set_template_data( 
                array( 
                    'ids' => $this->get_conversations($user_id) 
                )
            ) -> get_template_part( 'account/single_message' ); 
        } else {
			if( isset( $_GET["action"]) && $_GET["action"] == 'delete' )  {
				if(isset( $_GET["conv_id"]) && !empty($_GET["conv_id"])) {
					$conv_id = $_GET["conv_id"];
					$delete = $this->delete_conversations($conv_id);   
					if($delete) { ?>
						<div class="notification success"><p><?php esc_html_e('Conversation hidden','listeo_core'); ?></p></div>
					<?php } else { ?>
						<div class="notification error"><p><?php esc_html_e('Conversation couldn\'t be hidden.','listeo_core'); ?></p></div>
					<?php }
				}
			}
			if( isset( $_GET["action"]) && $_GET["action"] == 'restore' )  {
				if(isset( $_GET["conv_id"]) && !empty($_GET["conv_id"])) {
					$conv_id = (int) $_GET["conv_id"];
					$restored = $this->restore_conversation_visibility( $conv_id, get_current_user_id() );
					if($restored) { ?>
						<div class="notification success"><p><?php esc_html_e('Conversation restored','listeo_core'); ?></p></div>
					<?php } else { ?>
						<div class="notification error"><p><?php esc_html_e('Conversation couldn\'t be restored.','listeo_core'); ?></p></div>
					<?php }
				}
			}
            $total = count($this->get_conversations($user_id));
            
            $max_number_pages = ceil($total/$limit);
            $template_loader->set_template_data( 
                array( 
                    'ids' => $this->get_conversations($user_id,$limit,$offset),
                    'total_pages' => $max_number_pages
                ) 
            ) -> get_template_part( 'account/messages' ); 
        }

		return ob_get_clean();
	}
}
