<?php
/**
 * Modern Events Calender Extend Class
 * 
 * @package wp-fusion-extend
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class containing MEC extend hooks
 */
class Modern_Events_Calendar_Extend {

    /**
     * Setup the plugin
     * 
     * @since 1.0
     */
    public function init() {
		add_action( 'init', array( $this, 'test_function') );
        // Sync data and apply tags when a booking is placed
		add_action( 'mec_booking_added', array( $this, 'booking_added_extend' ) );

        // Register fields for sync
        add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields_extend' ), 30 );
    }

    /**
	 * Sync data and apply tags when a booking is created
	 *
	 * @access  public
	 * @return  void
	 */

	public function booking_added_extend( $booking_id ) {

		$event_id  = get_post_meta( $booking_id, 'mec_event_id', true );
		$settings  = get_post_meta( $event_id, 'wpf_ticket_settings', true );
		$attendees = get_post_meta( $booking_id, 'mec_attendees', true );

		// Only act on each email address once
		$did_emails = array();

		foreach ( $attendees as $i => $attendee ) {

			if ( in_array( $attendee['email'], $did_emails ) ) {
				continue;
			}

			$did_emails[] = $attendee['email'];

			// Maybe quit after the first one if Add Attendees isn't checked for the ticket

			if ( $i > 0 ) {

				if ( empty( $settings ) || empty( $settings[ $attendee['id'] ] ) || empty( $settings[ $attendee['id'] ]['add_attendees'] ) ) {
					break;
				}
			}

			// Get attendee meta and sync it

			$update_data = $this->get_attendee_meta( $booking_id, $i );

			$user = get_user_by( 'email', $attendee['email'] );

			if ( ! empty( $user ) ) {

				wp_fusion()->user->push_user_meta( $user->ID, $update_data );

			} else {

				$contact_id = $this->guest_registration( $attendee['email'], $update_data );

			}

			// Apply the tags

			if ( ! empty( $settings ) && ! empty( $settings[ $attendee['id'] ] ) && ! empty( $settings[ $attendee['id'] ]['apply_tags'] ) ) {

				if ( ! empty( $user ) ) {

					wp_fusion()->user->apply_tags( $settings[ $attendee['id'] ]['apply_tags'], $user->ID );

				} elseif ( ! empty( $contact_id ) && ! is_wp_error( $contact_id ) ) {

					wpf_log( 'info', 0, 'Applying event tag(s) for guest booking: ', array( 'tag_array' => $settings[ $attendee['id'] ]['apply_tags'] ) );
					wp_fusion()->crm->apply_tags( $settings[ $attendee['id'] ]['apply_tags'], $contact_id );

				}
			}
		}

	}

    /**
	 * Gets all the attendee and event meta from a booking ID and attendee ID
	 *
	 * @access  public
	 * @return  array Update data
	 */

	public function get_attendee_meta( $booking_id, $attendee_id ) {

		$attendees = get_post_meta( $booking_id, 'mec_attendees', true );
		$event_id  = get_post_meta( $booking_id, 'mec_event_id', true );

		$start_date         = get_post_meta( $event_id, 'mec_start_date', true );
		$start_time_hour    = get_post_meta( $event_id, 'mec_start_time_hour', true );
		$start_time_minutes = get_post_meta( $event_id, 'mec_start_time_minutes', true );
		$start_time_ampm    = get_post_meta( $event_id, 'mec_start_time_ampm', true );

		$start_time  = sprintf( '%02d', $start_time_hour ) . ':';
		$start_time .= sprintf( '%02d', $start_time_minutes ) . ' ';
		$start_time .= $start_time_ampm;

		// $names = explode( ' ', $attendees[ $attendee_id ]['name'] );

		// $firstname = $names[0];
		$firstname = $attendees[ $attendee_id ]['name'];

		// unset( $names[0] );

		// if ( ! empty( $names ) ) {
		// 	$lastname = implode( ' ', $names );
		// } else {
		// 	$lastname = '';
		// }

		$organizer_id = get_post_meta($event_id, 'mec_organizer_id', true);
		$organizer = get_term( $organizer_id );
		
		// $mec_categories = get_categories( array(
		// 	'taxonomy' => 'mec_category'
		// ) );

		$mec_categories = get_the_terms( $event_id, 'mec_category' );
		if ( ! $mec_categories || is_wp_error( $mec_categories ) ) {
			$mec_categories = array();
		}
	
		$mec_categories = array_values( $mec_categories );
	
		foreach ( array_keys( $mec_categories ) as $key ) {
			_make_cat_compat( $mec_categories[ $key ] );
		}

		$categories_str = '';
		$category_offset = 0;
		foreach ( $mec_categories as $mec_category ) {
			if ( $category_offset > 0 ) {
				$categories_str .= ', ' . $mec_category->name;
			} else {
				$categories_str .= $mec_category->name;
			}
			$category_offset++;
		}

		$mec_labels = get_the_terms( $event_id, 'mec_label' );
		if ( ! $mec_labels || is_wp_error( $mec_labels ) ) {
			$mec_labels = array();
		}
	
		$mec_labels = array_values( $mec_labels );

		foreach ( array_keys( $mec_labels ) as $key ) {
			_make_cat_compat( $mec_labels[ $key ] );
		}

		$labels_str = '';
		$label_offset = 0;
		foreach ( $mec_labels as $mec_label ) {
			if ( $label_offset > 0 ) {
				$labels_str .= ', ' . $mec_label->name;
			} else {
				$labels_str .= $mec_label->name;
			}
			$label_offset++;
		}

		$location_id = get_post_meta($event_id, 'mec_location_id', true);
		$location = get_term( $location_id );

		$event_link = get_post_meta( $event_id, 'mec_read_more', true );
		$referal_link = isset( $_REQUEST['referer'] ) ? $_REQUEST['referer'] : '';

		$update_data = array(
			'first_name'      => $firstname,
			// 'last_name'       => $lastname,
			'user_email'      => $attendees[ $attendee_id ]['email'],
			'event_name'      => get_the_title( $event_id ),
			'event_date' 	  => $start_date,
			'event_time'      => $start_time,
			'surname'         => $attendees[$attendee_id]['reg'][5],
                        'attendee_tel'    => $attendees[$attendee_id]['reg'][2],
			'event_organizer' => $organizer->name,
			'event_category'  => $categories_str,
			'event_location'  => $location->name,
			'event_label'     => $labels_str,
			'event_link'      => $event_link,
			'event_referal_link' => $referal_link,
		);

		return $update_data;

	}

	/**
     * Test function
     * 
     * @since 1.0
     */
    public function test_function() {
		add_action( 'mec_metabox_details', array( $this, 'meta_box_organizer' ), 50 );
	}

	/**
     * Show organizer meta box
	 * 
     * @param object $post
     */
    public function meta_box_organizer($post) {
		$location_id = get_post_meta($post->ID, 'mec_location_id', true);
		$location = get_term( $location_id );
		$event_terms_category = get_terms( array(
			'taxonomy' => 'mec_category',
			'parent'   => 0,
		) );
		$event_link = get_post_meta( $post->ID, 'mec_more_info', true );
		$categories = get_the_category();
		// var_dump( $categories );
	}

    /**
     * Handle setting changes
     * 
     * @since 1.0
     */
    public function prepare_meta_fields_extend( $meta_fields ) {

		$meta_fields['surname'] = array(
			'label' => 'Last Name',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

        $meta_fields['attendee_tel'] = array(
			'label' => 'Telephone',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_organizer'] = array(
			'label' => 'Organizer',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_category'] = array(
			'label' => 'Category',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_location'] = array(
			'label' => 'Location',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_label'] = array(
			'label' => 'Label',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_link'] = array(
			'label' => 'Source URL',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_referal_link'] = array(
			'label' => 'Referal URL',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

        return $meta_fields;
        
    }

    /**
	 * Handles signups from plugins which support guest registrations
	 *
	 * @access  public
	 * @since   3.26.6
	 * @return  mixed Contact ID
	 */

	public function guest_registration( $email_address, $update_data ) {

		$contact_id  = wp_fusion()->crm->get_contact_id( $email_address );
		$update_data = apply_filters( "wpf_{$this->slug}_guest_registration_data", $update_data, $email_address, $contact_id );

		// Log whether we're creating or updating a contact, with edit link.
		if ( false !== $contact_id ) {
			$log_text = ' Updating existing contact #' . $contact_id . ': ';
		} else {
			$log_text = ' Creating new contact: ';
		}

		wpf_log( 'info', 0, $this->name . ' guest registration.' . $log_text, array( 'meta_array' => $update_data ) );

		if ( empty( $contact_id ) ) {

			$contact_id = wp_fusion()->crm->add_contact( $update_data );

			if ( ! is_wp_error( $contact_id ) ) {
				do_action( 'wpf_guest_contact_created', $contact_id, $email_address );
			}
		} else {

			wp_fusion()->crm->update_contact( $contact_id, $update_data );

			do_action( 'wpf_guest_contact_updated', $contact_id, $email_address );

		}

		if ( is_wp_error( $contact_id ) ) {

			wpf_log( $contact_id->get_error_code(), 0, 'Error adding contact: ' . $contact_id->get_error_message() );
			return false;

		}

		return $contact_id;

	}

    /**
     * Return an instance of the current class, create one if it doesn't exist
     * 
     * @since 1.0
     */
    public static function factory() {

        static $instance;

        if ( ! $instance ) {
            $instance = new self();
            $instance->init();
        }

        return $instance;
    }
}
