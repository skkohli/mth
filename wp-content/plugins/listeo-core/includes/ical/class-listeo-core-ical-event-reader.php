<?php

class Listeo_Core_ICal_Event_Reader {
	// phpcs:disable Generic.Arrays.DisallowLongArraySyntax

	const HTML_TEMPLATE = '<p>%s: %s</p>';

	/**
	 * https://www.kanzaki.com/docs/ical/summary.html
	 *
	 * @var $summary
	 */
	public $summary;

	/**
	 * https://www.kanzaki.com/docs/ical/dtstart.html
	 *
	 * @var $dtstart
	 */
	public $dtstart;

	/**
	 * https://www.kanzaki.com/docs/ical/dtend.html
	 *
	 * @var $dtend
	 */
	public $dtend;

	/**
	 * https://www.kanzaki.com/docs/ical/duration.html
	 *
	 * @var $duration
	 */
	public $duration;

	/**
	 * https://www.kanzaki.com/docs/ical/dtstamp.html
	 *
	 * @var $dtstamp
	 */
	public $dtstamp;

	/**
	 * When the event starts, represented as a timezone-adjusted string
	 *
	 * @var $dtstart_tz
	 */
	public $dtstart_tz;

	/**
	 * When the event ends, represented as a timezone-adjusted string
	 *
	 * @var $dtend_tz
	 */
	public $dtend_tz;

	/**
	 * https://www.kanzaki.com/docs/ical/uid.html
	 *
	 * @var $uid
	 */
	public $uid;

	/**
	 * https://www.kanzaki.com/docs/ical/created.html
	 *
	 * @var $created
	 */
	public $created;

	/**
	 * https://www.kanzaki.com/docs/ical/lastModified.html
	 *
	 * @var $lastmodified
	 */
	public $lastmodified;

	/**
	 * https://www.kanzaki.com/docs/ical/description.html
	 *
	 * @var $description
	 */
	public $description;

	/**
	 * https://www.kanzaki.com/docs/ical/location.html
	 *
	 * @var $location
	 */
	public $location;

	/**
	 * https://www.kanzaki.com/docs/ical/sequence.html
	 *
	 * @var $sequence
	 */
	public $sequence;

	/**
	 * https://www.kanzaki.com/docs/ical/status.html
	 *
	 * @var $status
	 */
	public $status;

	/**
	 * https://www.kanzaki.com/docs/ical/transp.html
	 *
	 * @var $transp
	 */
	public $transp;

	/**
	 * https://www.kanzaki.com/docs/ical/organizer.html
	 *
	 * @var $organizer
	 */
	public $organizer;

	/**
	 * https://www.kanzaki.com/docs/ical/attendee.html
	 *
	 * @var $attendee
	 */
	public $attendee;

	/**
	 * Derived variables
	 */

	public $all_day_event = false;

	/**
	 * Event days is array of date string in Y-m-d format.
	 * This array is list of event days for all day events.
	 * It is not time zone dependant, as all day events do not have time portion and timezone included.
	 * They are immutable to timezone
	 *
	 * @var array
	 */
	public $event_days = [];

	/**
	 * Array representation of dtstart with metadata
	 *
	 * @var array
	 */
	public $dtstart_array = [];

	/**
	 * Array representation of dtend with metadata
	 *
	 * @var array
	 */
	public $dtend_array = [];

	/**
	 * Array representation of summary with metadata
	 *
	 * @var array
	 */
	public $summary_array = [];

	/**
	 * Array representation of description with metadata
	 *
	 * @var array
	 */
	public $description_array = [];

	/**
	 * Array representation of dtstamp with metadata
	 *
	 * @var array
	 */
	public $dtstamp_array = [];

	/**
	 * Array representation of uid with metadata
	 *
	 * @var array
	 */
	public $uid_array = [];

	/**
	 * Array representation of location with metadata
	 *
	 * @var array
	 */
	public $location_array = [];

	/**
	 * Array representation of created with metadata
	 *
	 * @var array
	 */
	public $created_array = [];

	/**
	 * Array representation of lastmodified with metadata
	 *
	 * @var array
	 */
	public $lastmodified_array = [];

	/**
	 * Array representation of sequence with metadata
	 *
	 * @var array
	 */
	public $sequence_array = [];

	/**
	 * Array representation of status with metadata
	 *
	 * @var array
	 */
	public $status_array = [];

	/**
	 * Array representation of transp with metadata
	 *
	 * @var array
	 */
	public $transp_array = [];

	/**
	 * Array representation of organizer with metadata
	 *
	 * @var array
	 */
	public $organizer_array = [];

	/**
	 * Array representation of attendee with metadata
	 *
	 * @var array
	 */
	public $attendee_array = [];

	/**
	 * Array representation of duration with metadata
	 *
	 * @var array
	 */
	public $duration_array = [];

	/**
	 * Creates the Event object
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			$variable          = self::snake_case( $key );
			$this->{$variable} = self::prepare_data( $value );
		}

		$this->derive_variables();
	}

	/**
	 * Derives variables that are not defined in event
	 */
	protected function derive_variables(): void {
		$this->derive_all_day_event();
	}

	/**
	 * Derive all_day_event
	 */
	protected function derive_all_day_event() {
		if ( true === isset( $this->dtstart_array[0]['VALUE'] ) && isset( $this->dtend_array[0]['VALUE'] ) ) {
			if ( 'DATE' === $this->dtstart_array[0]['VALUE'] && 'DATE' === $this->dtend_array[0]['VALUE'] ) {
				$this->all_day_event = true;
				$this->derive_event_days();
			}
		}
	}

	protected function derive_event_days() {
		$this->event_days = [];
		$start_day        = new DateTimeImmutable( $this->dtstart, new DateTimeZone( 'UTC' ) );
		$end_day          = new DateTimeImmutable( $this->dtend, new DateTimeZone( 'UTC' ) );

		$days_diff = $end_day->diff($start_day)->days;

		for ( $i = 0; $i < $days_diff; $i ++ ) {
			$interval           = new DateInterval( sprintf( 'P%dD', $i ) );
			$this->event_days[] = $start_day->add( $interval )->format( 'Y-m-d');
		}
	}

	/**
	 * Prepares the data for output
	 *
	 * @param mixed $value
	 *
	 * @return array|string|mixed
	 */
	protected function prepare_data( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( trim( str_replace( '\n', "\n", $value ) ) );
		} elseif ( is_array( $value ) ) {
			return array_map( array( self::class, 'prepare_data' ), $value );
		}

		return $value;
	}

	/**
	 * Returns Event data excluding anything blank
	 * within an HTML template
	 *
	 * @param string $html HTML template to use
	 *
	 * @return string
	 */
	public function print_data( $html = self::HTML_TEMPLATE ): string {
		$data = array(
			'SUMMARY'       => $this->summary,
			'DTSTART'       => $this->dtstart,
			'DTEND'         => $this->dtend,
			'DTSTART_TZ'    => $this->dtstart_tz,
			'DTEND_TZ'      => $this->dtend_tz,
			'DURATION'      => $this->duration,
		//	'DTSTAMP'       => $this->dtstamp,
			'UID'           => $this->uid,
			'CREATED'       => $this->created,
			'LAST-MODIFIED' => $this->lastmodified,
			'DESCRIPTION'   => $this->description,
			'LOCATION'      => $this->location,
			'SEQUENCE'      => $this->sequence,
			'STATUS'        => $this->status,
			'TRANSP'        => $this->transp,
			'ORGANISER'     => $this->organizer,
			'ATTENDEE(S)'   => $this->attendee,
		);

		// Remove any blank values
		$data = array_filter( $data );

		$output = '';

		foreach ( $data as $key => $value ) {
			$output .= sprintf( $html, $key, $value );
		}

		return $output;
	}

	/**
	 * Converts the given input to snake_case
	 *
	 * @param string $input
	 * @param string $glue
	 * @param string $separator
	 *
	 * @return string
	 */
	protected static function snake_case( string $input, $glue = '_', $separator = '-' ): string {
		$input = preg_split( '/(?<=[a-z])(?=[A-Z])/x', $input );
		$input = implode( $glue, $input );
		$input = str_replace( $separator, $glue, $input );

		return strtolower( $input );
	}
}