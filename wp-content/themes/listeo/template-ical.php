<?php

/**
 * Template Name: Listeo iCal Output
 */

if (!isset($_GET['calendar'])) {
	wp_die('Oooops.. Something went wrong');
}

$decode      = hex2bin($_GET['calendar']);
$data_decode = explode('|', $decode);
$listing_id  = $data_decode[0];

$eol = PHP_EOL;

$slug      = get_post_field('post_name', $listing_id);
$title     = get_post_field('post_title', $listing_id);
$title     = sprintf('%s - %s', $title, __('Bookings', 'listeo_core', 'listeo'));
$permalink = get_permalink($listing_id);

$ical_prod_id          = sprintf('-//%s//iCal Event Maker', get_bloginfo('name'));
$ical_refresh_interval = 'P30M';
$ical_timezone_string  = wp_timezone_string();

//listing timezone
$meta_timezone =  get_post_meta($post->ID, '_listing_timezone', true);
if (substr($meta_timezone, 0, 3) == "UTC") {
	$offset =  substr($meta_timezone, 3);
	$meta_timezone = str_replace('UTC', 'Etc/GMT', $meta_timezone);
	if (0 == $offset) {
	} elseif ($offset < 0) {
		$meta_timezone = str_replace('-', '+', $meta_timezone);
	} else {
		$meta_timezone = str_replace('+', '-', $meta_timezone);
	}
}
if (!empty($meta_timezone)) {
	$ical_timezone_string = $meta_timezone;
}

/**
 * Ensure timezone string is a valid IANA identifier, not a UTC offset.
 * wp_timezone_string() may return offsets like "+00:00", "+03:00" when
 * WordPress is configured with UTC offsets instead of city-based timezones.
 * Google Calendar rejects iCal files with non-IANA timezone identifiers.
 */
if (preg_match('/^[+-]\d{2}:\d{2}$/', $ical_timezone_string)) {
	$offset_hours = (int) $ical_timezone_string;
	if ($offset_hours === 0) {
		$ical_timezone_string = 'UTC';
	} else {
		// Etc/GMT uses inverted signs per POSIX convention
		$ical_timezone_string = 'Etc/GMT' . ($offset_hours > 0 ? '-' : '+') . abs($offset_hours);
	}
}

$zulu_timezone = new DateTimeZone('UTC');

/**
 * Calculate timezone offset in hours
 */
$datetime             = new DateTimeImmutable('now', new DateTimeZone($ical_timezone_string));
$ical_timezone_offset = str_replace(':', '', $datetime->format('P'));

/**
 * Get list of events in last year up to 2 years in future
 */
$events_from_datetime = wp_date('Y-m-d H:i:s', strtotime('-1 year'));
$events_to_datetime   = wp_date('Y-m-d H:i:s', strtotime('+2 years'));
$listing_id = (int) $listing_id; // Always sanitize input


$sql = $wpdb->prepare(
	"
    SELECT * FROM {$wpdb->prefix}bookings_calendar 
    WHERE (
            (%s >= date_start AND %s <= date_end) OR 
            (%s >= date_start AND %s <= date_end) OR
            (date_start >= %s AND date_end <= %s)
          )
     AND status IN ('confirmed','paid','approved','waiting','owner_reservations')
     AND listing_id = %d
     AND type = 'reservation'
",
	$events_from_datetime,
	$events_from_datetime,
	$events_to_datetime,
	$events_to_datetime,
	$events_from_datetime,
	$events_to_datetime,
	$listing_id
);

$events = $wpdb->get_results($sql);

/**
 * Calendar domain
 */
$url_parts   = parse_url(home_url());
$ical_domain = $url_parts['host'];

/**
 * Calendar timezone is tied to WordPress timezone.
 */

ob_start();

?>
<?php
/**
 * Standard iCal head data
 * Includes calendar name, refresh interval (30m by default), calendar scale
 *
 * X-WR-CALNAME: https://docs.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-oxcical/1da58449-b97e-46bd-b018-a1ce576f3e6d
 */
?>
BEGIN:VCALENDAR<?php echo $eol; ?>
VERSION:2.0<?php echo $eol; ?>
PRODID:<?php echo $ical_prod_id; ?><?php echo $eol; ?>
X-WR-CALNAME:<?php echo $title; ?><?php echo $eol; ?>
X-LISTEO-LISTING-URL:<?php echo $permalink; ?><?php echo $eol; ?>
NAME:iCal <?php echo $title; ?><?php echo $eol; ?>
REFRESH-INTERVAL;VALUE=DURATION:<?php echo $ical_refresh_interval; ?><?php echo $eol; ?>
CALSCALE:GREGORIAN<?php echo $eol; ?>
<?php
/**
 * Timezone data
 * Includes timezone ID, Timezone URL and
 */
?>
X-WR-TIMEZONE:<?php echo $ical_timezone_string; ?><?php echo $eol; ?>
<?php foreach ($events as $event) : ?>

	<?php

	$listing_type = get_post_meta($event->listing_id, '_listing_type', true);

	/**
	 * Calculate times in Zulu timezone (UTC) for event
	 * Data in database are stored in local (WordPress configured) timezone.
	 */

	$event_dt_start_datetime = new DateTime($event->date_start, new DateTimeZone($ical_timezone_string));
	$event_dt_start_datetime->setTimezone($zulu_timezone);
	$event_dt_start = $event_dt_start_datetime->format('Ymd\THis\Z');

	$event_dt_end_datetime = new DateTime($event->date_end, new DateTimeZone($ical_timezone_string));
	$event_dt_end_datetime->setTimezone($zulu_timezone);
	$event_dt_end = $event_dt_end_datetime->format('Ymd\THis\Z');

	$event_dt_stamp_datetime = new DateTime($event->created, new DateTimeZone($ical_timezone_string));
	$event_dt_stamp_datetime->setTimezone($zulu_timezone);
	$event_dt_stamp = $event_dt_stamp_datetime->format('Ymd\THis\Z');

	if ('rental' === $listing_type) {
		// For all-day events (VALUE=DATE), DTEND is exclusive (first day property is FREE)
		// Checkout date is when guest leaves, so that's the first available day
		// No need to add +1 day - the checkout date IS the exclusive end
		$event_dt_start = $event_dt_start_datetime->format('Ymd');
		$event_dt_end   = $event_dt_end_datetime->format('Ymd');
	}

	$event_location = get_the_title($event->listing_id) . ' \; ' . listeo_escape_string(get_post_meta($event->listing_id, '_address', true));
	$event_uid      = sha1(sprintf('%s-%s', $event->ID, $event_dt_stamp));

	$event_description  = '';
	$description_object = json_decode($event->comment);
	/**
	 * <b>NULL</b> is returned if the
	 * <i>json</i> cannot be decoded or if the encoded
	 * data is deeper than the recursion limit.
	 */

	if (false === is_null($description_object)) {
		if (is_object($description_object)) {

			if (true === Listeo_Core_Bookings_Calendar::is_booking_external($event->status)) {
				$event_summary = $description_object->summary;

				$event_description = '=> ' . $description_object->uid . $eol;
				$event_description .= '---------------------' . $eol;
				$event_description .= $description_object->summary . $eol;
				$event_description .= $description_object->description . $eol;
				$event_description .= $description_object->location . $eol;
			} else {
				$event_summary = $description_object->first_name . ' ' . $description_object->last_name;

				$event_description = $description_object->first_name . ' ' . $description_object->last_name . $eol;
				$event_description .= $description_object->email . $eol;
				$event_description .= $description_object->phone . $eol;
				$event_description .= $description_object->message . $eol;
			}
		} elseif (is_string($description_object)) {
			$event_description = $description_object;
		}

		$event_description = preg_replace('/\n/', '\n', $event_description);
	}

	?>

	BEGIN:VEVENT<?php echo $eol; ?>
	UID:<?php echo $event_uid; ?>@<?php echo $ical_domain; ?><?php echo $eol; ?>
	X-LISTEO-BOOKING-ID:<?php echo $event->ID; ?><?php echo $eol; ?>
	X-LISTEO-BOOKING-STATUS:<?php echo $event->status; ?><?php echo $eol; ?>
	X-LISTEO-BOOKING-TYPE:<?php echo $event->type; ?><?php echo $eol; ?>
	X-LISTEO-BOOKING-PRICE:<?php echo $event->price; ?><?php echo $eol; ?>
	<?php if ('rental' === $listing_type) : ?>
	DTSTART;VALUE=DATE:<?php echo $event_dt_start; ?><?php echo $eol; ?>
	DTEND;VALUE=DATE:<?php echo $event_dt_end; ?><?php echo $eol; ?>
	<?php else : ?>
	DTSTART:<?php echo $event_dt_start; ?><?php echo $eol; ?>
	DTEND:<?php echo $event_dt_end; ?><?php echo $eol; ?>
	<?php endif; ?>
	DTSTAMP:<?php echo $event_dt_stamp; ?><?php echo $eol; ?>
	CREATED:<?php echo $event_dt_stamp; ?><?php echo $eol; ?>
	LAST-MODIFIED:<?php echo $event_dt_stamp; ?><?php echo $eol; ?>
	URL:<?php echo site_url('bookings/?status=approved#booking-' . $event->ID); ?><?php echo $eol; ?>
	LOCATION:<?php echo $event_location; ?><?php echo $eol; ?>
	SUMMARY:<?php echo $event_summary; ?><?php echo $eol; ?>
	DESCRIPTION:<?php echo $event_description; ?><?php echo $eol; ?>
	STATUS:CONFIRMED<?php echo $eol; ?>
	TRANSP:TRANSPARENT<?php echo $eol; ?>
	X-MICROSOFT-CDO-BUSYSTATUS:BUSY<?php echo $eol; ?>
	END:VEVENT<?php echo $eol; ?>
<?php endforeach; ?>
	END:VCALENDAR<?php echo $eol; ?>
<?php
$ical_content = ob_get_clean();
header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename=' . $slug . '.ics');
//cleanup extra spaces and empty lines
$ical_content = preg_replace('/^(\s)*/m', '', $ical_content);
$ical_content = str_replace(["\r\n", "\r", "\n"], "\r\n", $ical_content);
echo $ical_content;