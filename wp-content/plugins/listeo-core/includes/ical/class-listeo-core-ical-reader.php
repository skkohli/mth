<?php

/**
 * This PHP class will read an ICS (`.ics`, `.ical`, `.ifb`) file, parse it and return an
 * array of its contents.
 *
 * This is WordPress coding standards adoption of https://github.com/u01jmg3/ics-parser
 *
 * PHP 5 (≥ 5.3.9)
 *
 * @author  Nemanja Cimbaljevic <https://github.com/nem-c>
 * @license https://opensource.org/licenses/mit-license.php MIT License
 * @version 2.2.2
 */

class Listeo_Core_iCal_Reader {
	// phpcs:disable Generic.Arrays.DisallowLongArraySyntax

	const DATE_TIME_FORMAT = 'Ymd\THis';
	const DATE_TIME_FORMAT_PRETTY = 'F Y H:i:s';
	const ICAL_DATE_TIME_TEMPLATE = 'TZID=%s:';
	const ISO_8601_WEEK_START = 'MO';
	const RECURRENCE_EVENT = 'Generated recurrence event';
	const SECONDS_IN_A_WEEK = 604800;
	const TIME_FORMAT = 'His';
	const TIME_ZONE_UTC = 'UTC';
	const UNIX_FORMAT = 'U';
	const UNIX_MIN_YEAR = 1970;

	/**
	 * Tracks the number of alarms in the current iCal feed
	 *
	 * @var integer
	 */
	public $alarm_count = 0;

	/**
	 * Tracks the number of events in the current iCal feed
	 *
	 * @var integer
	 */
	public $event_count = 0;

	/**
	 * Tracks the free/busy count in the current iCal feed
	 *
	 * @var integer
	 */
	public $free_busy_count = 0;

	/**
	 * Tracks the number of todos in the current iCal feed
	 *
	 * @var integer
	 */
	public $todo_count = 0;

	/**
	 * The value in years to use for indefinite, recurring events
	 *
	 * @var integer
	 */
	public $default_span = 2;

	/**
	 * Enables customisation of the default time zone
	 *
	 * @var string
	 */
	public $default_timezone;

	/**
	 * The two letter representation of the first day of the week
	 *
	 * @var string
	 */
	public $default_week_start = self::ISO_8601_WEEK_START;

	/**
	 * Toggles whether to skip the parsing of recurrence rules
	 *
	 * @var boolean
	 */
	public $skip_recurrence = false;

	/**
	 * Toggles whether to disable all character replacement.
	 *
	 * @var boolean
	 */
	public $disable_character_replacement = false;

	/**
	 * With this being non-null the parser will ignore all events more than roughly this many days after now.
	 *
	 * @var integer
	 */
	public $filter_days_before;

	/**
	 * With this being non-null the parser will ignore all events more than roughly this many days before now.
	 *
	 * @var integer
	 */
	public $filter_days_after;

	/**
	 * The parsed calendar
	 *
	 * @var array
	 */
	public $cal = array();

	/**
	 * Tracks the VFREEBUSY component
	 *
	 * @var integer
	 */
	protected $free_busy_index = 0;

	/**
	 * Variable to track the previous keyword
	 *
	 * @var string
	 */
	protected $last_keyword;

	/**
	 * Cache valid IANA time zone IDs to avoid unnecessary lookups
	 *
	 * @var array
	 */
	protected $valid_iana_timezones = array();

	/**
	 * Event recurrence instances that have been altered
	 *
	 * @var array
	 */
	protected $altered_recurrence_instances = array();

	/**
	 * An associative array containing weekday conversion data
	 *
	 * The order of the days in the array follow the ISO-8601 specification of a week.
	 *
	 * @var array
	 */
	protected $weekdays = array(
		'MO' => 'monday',
		'TU' => 'tuesday',
		'WE' => 'wednesday',
		'TH' => 'thursday',
		'FR' => 'friday',
		'SA' => 'saturday',
		'SU' => 'sunday',
	);

	/**
	 * An associative array containing frequency conversion terms
	 *
	 * @var array
	 */
	protected $frequency_conversion = array(
		'DAILY'   => 'day',
		'WEEKLY'  => 'week',
		'MONTHLY' => 'month',
		'YEARLY'  => 'year',
	);

	/**
	 * Holds the username and password for HTTP basic authentication
	 *
	 * @var array
	 */
	protected $http_basic_auth = array();

	/**
	 * Holds the custom User Agent string header
	 *
	 * @var string
	 */
	protected $http_user_agent;

	/**
	 * Holds the custom Accept Language string header
	 *
	 * @var string
	 */
	protected $http_accept_language;

	/**
	 * Define which variables can be configured
	 *
	 * @var array
	 */
	private static $configurable_options = array(
		'default_span',
		'default_timezone',
		'default_week_start',
		'disable_character_replacement',
		'filter_days_after',
		'filter_days_before',
		'skip_recurrence',
	);

	/**
	 * CLDR time zones mapped to IANA time zones.
	 *
	 * @var array
	 */
	private static $cldr_timezones_map = array(
		'(UTC-12:00) International Date Line West'                      => 'Etc/GMT+12',
		'(UTC-11:00) Coordinated Universal Time-11'                     => 'Etc/GMT+11',
		'(UTC-10:00) Hawaii'                                            => 'Pacific/Honolulu',
		'(UTC-09:00) Alaska'                                            => 'America/Anchorage',
		'(UTC-08:00) Pacific Time (US & Canada)'                        => 'America/Los_Angeles',
		'(UTC-07:00) Arizona'                                           => 'America/Phoenix',
		'(UTC-07:00) Chihuahua, La Paz, Mazatlan'                       => 'America/Chihuahua',
		'(UTC-07:00) Mountain Time (US & Canada)'                       => 'America/Denver',
		'(UTC-06:00) Central America'                                   => 'America/Guatemala',
		'(UTC-06:00) Central Time (US & Canada)'                        => 'America/Chicago',
		'(UTC-06:00) Guadalajara, Mexico City, Monterrey'               => 'America/Mexico_City',
		'(UTC-06:00) Saskatchewan'                                      => 'America/Regina',
		'(UTC-05:00) Bogota, Lima, Quito, Rio Branco'                   => 'America/Bogota',
		'(UTC-05:00) Chetumal'                                          => 'America/Cancun',
		'(UTC-05:00) Eastern Time (US & Canada)'                        => 'America/New_York',
		'(UTC-05:00) Indiana (East)'                                    => 'America/Indianapolis',
		'(UTC-04:00) Asuncion'                                          => 'America/Asuncion',
		'(UTC-04:00) Atlantic Time (Canada)'                            => 'America/Halifax',
		'(UTC-04:00) Caracas'                                           => 'America/Caracas',
		'(UTC-04:00) Cuiaba'                                            => 'America/Cuiaba',
		'(UTC-04:00) Georgetown, La Paz, Manaus, San Juan'              => 'America/La_Paz',
		'(UTC-04:00) Santiago'                                          => 'America/Santiago',
		'(UTC-03:30) Newfoundland'                                      => 'America/St_Johns',
		'(UTC-03:00) Brasilia'                                          => 'America/Sao_Paulo',
		'(UTC-03:00) Cayenne, Fortaleza'                                => 'America/Cayenne',
		'(UTC-03:00) City of Buenos Aires'                              => 'America/Buenos_Aires',
		'(UTC-03:00) Greenland'                                         => 'America/Godthab',
		'(UTC-03:00) Montevideo'                                        => 'America/Montevideo',
		'(UTC-03:00) Salvador'                                          => 'America/Bahia',
		'(UTC-02:00) Coordinated Universal Time-02'                     => 'Etc/GMT+2',
		'(UTC-01:00) Azores'                                            => 'Atlantic/Azores',
		'(UTC-01:00) Cabo Verde Is.'                                    => 'Atlantic/Cape_Verde',
		'(UTC) Coordinated Universal Time'                              => 'Etc/GMT',
		'(UTC+00:00) Casablanca'                                        => 'Africa/Casablanca',
		'(UTC+00:00) Dublin, Edinburgh, Lisbon, London'                 => 'Europe/London',
		'(UTC+00:00) Monrovia, Reykjavik'                               => 'Atlantic/Reykjavik',
		'(UTC+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna'  => 'Europe/Berlin',
		'(UTC+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague' => 'Europe/Budapest',
		'(UTC+01:00) Brussels, Copenhagen, Madrid, Paris'               => 'Europe/Paris',
		'(UTC+01:00) Sarajevo, Skopje, Warsaw, Zagreb'                  => 'Europe/Warsaw',
		'(UTC+01:00) West Central Africa'                               => 'Africa/Lagos',
		'(UTC+02:00) Amman'                                             => 'Asia/Amman',
		'(UTC+02:00) Athens, Bucharest'                                 => 'Europe/Bucharest',
		'(UTC+02:00) Beirut'                                            => 'Asia/Beirut',
		'(UTC+02:00) Cairo'                                             => 'Africa/Cairo',
		'(UTC+02:00) Chisinau'                                          => 'Europe/Chisinau',
		'(UTC+02:00) Damascus'                                          => 'Asia/Damascus',
		'(UTC+02:00) Harare, Pretoria'                                  => 'Africa/Johannesburg',
		'(UTC+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius'     => 'Europe/Kiev',
		'(UTC+02:00) Jerusalem'                                         => 'Asia/Jerusalem',
		'(UTC+02:00) Kaliningrad'                                       => 'Europe/Kaliningrad',
		'(UTC+02:00) Tripoli'                                           => 'Africa/Tripoli',
		'(UTC+02:00) Windhoek'                                          => 'Africa/Windhoek',
		'(UTC+03:00) Baghdad'                                           => 'Asia/Baghdad',
		'(UTC+03:00) Istanbul'                                          => 'Europe/Istanbul',
		'(UTC+03:00) Kuwait, Riyadh'                                    => 'Asia/Riyadh',
		'(UTC+03:00) Minsk'                                             => 'Europe/Minsk',
		'(UTC+03:00) Moscow, St. Petersburg, Volgograd'                 => 'Europe/Moscow',
		'(UTC+03:00) Nairobi'                                           => 'Africa/Nairobi',
		'(UTC+03:30) Tehran'                                            => 'Asia/Tehran',
		'(UTC+04:00) Abu Dhabi, Muscat'                                 => 'Asia/Dubai',
		'(UTC+04:00) Baku'                                              => 'Asia/Baku',
		'(UTC+04:00) Izhevsk, Samara'                                   => 'Europe/Samara',
		'(UTC+04:00) Port Louis'                                        => 'Indian/Mauritius',
		'(UTC+04:00) Tbilisi'                                           => 'Asia/Tbilisi',
		'(UTC+04:00) Yerevan'                                           => 'Asia/Yerevan',
		'(UTC+04:30) Kabul'                                             => 'Asia/Kabul',
		'(UTC+05:00) Ashgabat, Tashkent'                                => 'Asia/Tashkent',
		'(UTC+05:00) Ekaterinburg'                                      => 'Asia/Yekaterinburg',
		'(UTC+05:00) Islamabad, Karachi'                                => 'Asia/Karachi',
		'(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi'               => 'Asia/Calcutta',
		'(UTC+05:30) Sri Jayawardenepura'                               => 'Asia/Colombo',
		'(UTC+05:45) Kathmandu'                                         => 'Asia/Katmandu',
		'(UTC+06:00) Astana'                                            => 'Asia/Almaty',
		'(UTC+06:00) Dhaka'                                             => 'Asia/Dhaka',
		'(UTC+06:30) Yangon (Rangoon)'                                  => 'Asia/Rangoon',
		'(UTC+07:00) Bangkok, Hanoi, Jakarta'                           => 'Asia/Bangkok',
		'(UTC+07:00) Krasnoyarsk'                                       => 'Asia/Krasnoyarsk',
		'(UTC+07:00) Novosibirsk'                                       => 'Asia/Novosibirsk',
		'(UTC+08:00) Beijing, Chongqing, Hong Kong, Urumqi'             => 'Asia/Shanghai',
		'(UTC+08:00) Irkutsk'                                           => 'Asia/Irkutsk',
		'(UTC+08:00) Kuala Lumpur, Singapore'                           => 'Asia/Singapore',
		'(UTC+08:00) Perth'                                             => 'Australia/Perth',
		'(UTC+08:00) Taipei'                                            => 'Asia/Taipei',
		'(UTC+08:00) Ulaanbaatar'                                       => 'Asia/Ulaanbaatar',
		'(UTC+09:00) Osaka, Sapporo, Tokyo'                             => 'Asia/Tokyo',
		'(UTC+09:00) Pyongyang'                                         => 'Asia/Pyongyang',
		'(UTC+09:00) Seoul'                                             => 'Asia/Seoul',
		'(UTC+09:00) Yakutsk'                                           => 'Asia/Yakutsk',
		'(UTC+09:30) Adelaide'                                          => 'Australia/Adelaide',
		'(UTC+09:30) Darwin'                                            => 'Australia/Darwin',
		'(UTC+10:00) Brisbane'                                          => 'Australia/Brisbane',
		'(UTC+10:00) Canberra, Melbourne, Sydney'                       => 'Australia/Sydney',
		'(UTC+10:00) Guam, Port Moresby'                                => 'Pacific/Port_Moresby',
		'(UTC+10:00) Hobart'                                            => 'Australia/Hobart',
		'(UTC+10:00) Vladivostok'                                       => 'Asia/Vladivostok',
		'(UTC+11:00) Chokurdakh'                                        => 'Asia/Srednekolymsk',
		'(UTC+11:00) Magadan'                                           => 'Asia/Magadan',
		'(UTC+11:00) Solomon Is., New Caledonia'                        => 'Pacific/Guadalcanal',
		'(UTC+12:00) Anadyr, Petropavlovsk-Kamchatsky'                  => 'Asia/Kamchatka',
		'(UTC+12:00) Auckland, Wellington'                              => 'Pacific/Auckland',
		'(UTC+12:00) Coordinated Universal Time+12'                     => 'Etc/GMT-12',
		'(UTC+12:00) Fiji'                                              => 'Pacific/Fiji',
		"(UTC+13:00) Nuku'alofa"                                        => 'Pacific/Tongatapu',
		'(UTC+13:00) Samoa'                                             => 'Pacific/Apia',
		'(UTC+14:00) Kiritimati Island'                                 => 'Pacific/Kiritimati',
	);

	/**
	 * Maps Windows (non-CLDR) time zone ID to IANA ID. This is pragmatic but not 100% precise as one Windows zone ID
	 * maps to multiple IANA IDs (one for each territory). For all practical purposes this should be good enough, though.
	 *
	 * Source: http://unicode.org/repos/cldr/trunk/common/supplemental/windowsZones.xml
	 *
	 * @var array
	 */
	private static $windows_timezones_map = array(
		'AUS Central Standard Time'       => 'Australia/Darwin',
		'AUS Eastern Standard Time'       => 'Australia/Sydney',
		'Afghanistan Standard Time'       => 'Asia/Kabul',
		'Alaskan Standard Time'           => 'America/Anchorage',
		'Aleutian Standard Time'          => 'America/Adak',
		'Altai Standard Time'             => 'Asia/Barnaul',
		'Arab Standard Time'              => 'Asia/Riyadh',
		'Arabian Standard Time'           => 'Asia/Dubai',
		'Arabic Standard Time'            => 'Asia/Baghdad',
		'Argentina Standard Time'         => 'America/Buenos_Aires',
		'Astrakhan Standard Time'         => 'Europe/Astrakhan',
		'Atlantic Standard Time'          => 'America/Halifax',
		'Aus Central W. Standard Time'    => 'Australia/Eucla',
		'Azerbaijan Standard Time'        => 'Asia/Baku',
		'Azores Standard Time'            => 'Atlantic/Azores',
		'Bahia Standard Time'             => 'America/Bahia',
		'Bangladesh Standard Time'        => 'Asia/Dhaka',
		'Belarus Standard Time'           => 'Europe/Minsk',
		'Bougainville Standard Time'      => 'Pacific/Bougainville',
		'Canada Central Standard Time'    => 'America/Regina',
		'Cape Verde Standard Time'        => 'Atlantic/Cape_Verde',
		'Caucasus Standard Time'          => 'Asia/Yerevan',
		'Cen. Australia Standard Time'    => 'Australia/Adelaide',
		'Central America Standard Time'   => 'America/Guatemala',
		'Central Asia Standard Time'      => 'Asia/Almaty',
		'Central Brazilian Standard Time' => 'America/Cuiaba',
		'Central Europe Standard Time'    => 'Europe/Budapest',
		'Central European Standard Time'  => 'Europe/Warsaw',
		'Central Pacific Standard Time'   => 'Pacific/Guadalcanal',
		'Central Standard Time (Mexico)'  => 'America/Mexico_City',
		'Central Standard Time'           => 'America/Chicago',
		'Chatham Islands Standard Time'   => 'Pacific/Chatham',
		'China Standard Time'             => 'Asia/Shanghai',
		'Cuba Standard Time'              => 'America/Havana',
		'Dateline Standard Time'          => 'Etc/GMT+12',
		'E. Africa Standard Time'         => 'Africa/Nairobi',
		'E. Australia Standard Time'      => 'Australia/Brisbane',
		'E. Europe Standard Time'         => 'Europe/Chisinau',
		'E. South America Standard Time'  => 'America/Sao_Paulo',
		'Easter Island Standard Time'     => 'Pacific/Easter',
		'Eastern Standard Time (Mexico)'  => 'America/Cancun',
		'Eastern Standard Time'           => 'America/New_York',
		'Egypt Standard Time'             => 'Africa/Cairo',
		'Ekaterinburg Standard Time'      => 'Asia/Yekaterinburg',
		'FLE Standard Time'               => 'Europe/Kiev',
		'Fiji Standard Time'              => 'Pacific/Fiji',
		'GMT Standard Time'               => 'Europe/London',
		'GTB Standard Time'               => 'Europe/Bucharest',
		'Georgian Standard Time'          => 'Asia/Tbilisi',
		'Greenland Standard Time'         => 'America/Godthab',
		'Greenwich Standard Time'         => 'Atlantic/Reykjavik',
		'Haiti Standard Time'             => 'America/Port-au-Prince',
		'Hawaiian Standard Time'          => 'Pacific/Honolulu',
		'India Standard Time'             => 'Asia/Calcutta',
		'Iran Standard Time'              => 'Asia/Tehran',
		'Israel Standard Time'            => 'Asia/Jerusalem',
		'Jordan Standard Time'            => 'Asia/Amman',
		'Kaliningrad Standard Time'       => 'Europe/Kaliningrad',
		'Korea Standard Time'             => 'Asia/Seoul',
		'Libya Standard Time'             => 'Africa/Tripoli',
		'Line Islands Standard Time'      => 'Pacific/Kiritimati',
		'Lord Howe Standard Time'         => 'Australia/Lord_Howe',
		'Magadan Standard Time'           => 'Asia/Magadan',
		'Magallanes Standard Time'        => 'America/Punta_Arenas',
		'Marquesas Standard Time'         => 'Pacific/Marquesas',
		'Mauritius Standard Time'         => 'Indian/Mauritius',
		'Middle East Standard Time'       => 'Asia/Beirut',
		'Montevideo Standard Time'        => 'America/Montevideo',
		'Morocco Standard Time'           => 'Africa/Casablanca',
		'Mountain Standard Time (Mexico)' => 'America/Chihuahua',
		'Mountain Standard Time'          => 'America/Denver',
		'Myanmar Standard Time'           => 'Asia/Rangoon',
		'N. Central Asia Standard Time'   => 'Asia/Novosibirsk',
		'Namibia Standard Time'           => 'Africa/Windhoek',
		'Nepal Standard Time'             => 'Asia/Katmandu',
		'New Zealand Standard Time'       => 'Pacific/Auckland',
		'Newfoundland Standard Time'      => 'America/St_Johns',
		'Norfolk Standard Time'           => 'Pacific/Norfolk',
		'North Asia East Standard Time'   => 'Asia/Irkutsk',
		'North Asia Standard Time'        => 'Asia/Krasnoyarsk',
		'North Korea Standard Time'       => 'Asia/Pyongyang',
		'Omsk Standard Time'              => 'Asia/Omsk',
		'Pacific SA Standard Time'        => 'America/Santiago',
		'Pacific Standard Time (Mexico)'  => 'America/Tijuana',
		'Pacific Standard Time'           => 'America/Los_Angeles',
		'Pakistan Standard Time'          => 'Asia/Karachi',
		'Paraguay Standard Time'          => 'America/Asuncion',
		'Romance Standard Time'           => 'Europe/Paris',
		'Russia Time Zone 10'             => 'Asia/Srednekolymsk',
		'Russia Time Zone 11'             => 'Asia/Kamchatka',
		'Russia Time Zone 3'              => 'Europe/Samara',
		'Russian Standard Time'           => 'Europe/Moscow',
		'SA Eastern Standard Time'        => 'America/Cayenne',
		'SA Pacific Standard Time'        => 'America/Bogota',
		'SA Western Standard Time'        => 'America/La_Paz',
		'SE Asia Standard Time'           => 'Asia/Bangkok',
		'Saint Pierre Standard Time'      => 'America/Miquelon',
		'Sakhalin Standard Time'          => 'Asia/Sakhalin',
		'Samoa Standard Time'             => 'Pacific/Apia',
		'Sao Tome Standard Time'          => 'Africa/Sao_Tome',
		'Saratov Standard Time'           => 'Europe/Saratov',
		'Singapore Standard Time'         => 'Asia/Singapore',
		'South Africa Standard Time'      => 'Africa/Johannesburg',
		'Sri Lanka Standard Time'         => 'Asia/Colombo',
		'Sudan Standard Time'             => 'Africa/Tripoli',
		'Syria Standard Time'             => 'Asia/Damascus',
		'Taipei Standard Time'            => 'Asia/Taipei',
		'Tasmania Standard Time'          => 'Australia/Hobart',
		'Tocantins Standard Time'         => 'America/Araguaina',
		'Tokyo Standard Time'             => 'Asia/Tokyo',
		'Tomsk Standard Time'             => 'Asia/Tomsk',
		'Tonga Standard Time'             => 'Pacific/Tongatapu',
		'Transbaikal Standard Time'       => 'Asia/Chita',
		'Turkey Standard Time'            => 'Europe/Istanbul',
		'Turks And Caicos Standard Time'  => 'America/Grand_Turk',
		'US Eastern Standard Time'        => 'America/Indianapolis',
		'US Mountain Standard Time'       => 'America/Phoenix',
		'UTC'                             => 'Etc/GMT',
		'UTC+12'                          => 'Etc/GMT-12',
		'UTC+13'                          => 'Etc/GMT-13',
		'UTC-02'                          => 'Etc/GMT+2',
		'UTC-08'                          => 'Etc/GMT+8',
		'UTC-09'                          => 'Etc/GMT+9',
		'UTC-11'                          => 'Etc/GMT+11',
		'Ulaanbaatar Standard Time'       => 'Asia/Ulaanbaatar',
		'Venezuela Standard Time'         => 'America/Caracas',
		'Vladivostok Standard Time'       => 'Asia/Vladivostok',
		'W. Australia Standard Time'      => 'Australia/Perth',
		'W. Central Africa Standard Time' => 'Africa/Lagos',
		'W. Europe Standard Time'         => 'Europe/Berlin',
		'W. Mongolia Standard Time'       => 'Asia/Hovd',
		'West Asia Standard Time'         => 'Asia/Tashkent',
		'West Bank Standard Time'         => 'Asia/Hebron',
		'West Pacific Standard Time'      => 'Pacific/Port_Moresby',
		'Yakutsk Standard Time'           => 'Asia/Yakutsk',
	);

	/**
	 * If `$filter_days_before` or `$filter_days_after` are set then the events are filtered according to the window defined
	 * by this field and `$window_max_timestamp`.
	 *
	 * @var integer
	 */
	private $window_min_timestamp;

	/**
	 * If `$filter_days_before` or `$filter_days_after` are set then the events are filtered according to the window defined
	 * by this field and `$window_min_timestamp`.
	 *
	 * @var integer
	 */
	private $window_max_timestamp;

	/**
	 * `true` if either `$filter_days_before` or `$filter_days_after` are set.
	 *
	 * @var boolean
	 */
	private $should_filter_by_window;

	/**
	 * Creates the Listeo_Core_iCal_Reader object
	 *
	 * @param mixed $files
	 * @param array $options
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function __construct( $files = false, array $options = array() ) {
		// auto_detect_line_endings was deprecated in PHP 8.1
		// Modern PHP handles line endings automatically
		if ( PHP_VERSION_ID < 80100 ) {
			ini_set( 'auto_detect_line_endings', '1' );
		}

		foreach ( $options as $option => $value ) {
			if ( in_array( $option, self::$configurable_options ) ) {
				$this->{$option} = $value;
			}
		}

		// Fallback to use the system default time zone
		if ( ! isset( $this->default_timezone ) || ! $this->is_valid_time_zone_id( $this->default_timezone ) ) {
			$this->default_timezone = date_default_timezone_get();
		}

		// Ideally you would use `PHP_INT_MIN` from PHP 7
		$php_int_min = - 2147483648;

		$this->window_min_timestamp = is_null( $this->filter_days_before )
			? $php_int_min
			: ( new DateTime( 'now' ) )->sub( new DateInterval( 'P' . $this->filter_days_before . 'D' ) )->getTimestamp(); //phpcs:ignore Qualifier is unnecessary and can be removed

		$this->window_max_timestamp = is_null( $this->filter_days_after )
			? PHP_INT_MAX
			: ( new DateTime( 'now' ) )->add( new DateInterval( 'P' . $this->filter_days_after . 'D' ) )->getTimestamp(); //phpcs:ignore Qualifier is unnecessary and can be removed

		$this->should_filter_by_window = ! is_null( $this->filter_days_before ) || ! is_null( $this->filter_days_after );

		if ( $files !== false ) {
			$files = is_array( $files ) ? $files : array( $files );

			foreach ( $files as $file ) {
				if ( ! is_array( $file ) && $this->is_file_or_url( $file ) ) {
					$lines = $this->file_or_url( $file );
				} else {
					$lines = is_array( $file ) ? $file : array( $file );
				}

				$this->init_lines( $lines );
			}
		}
	}

	/**
	 * Initialises lines from a string
	 *
	 * @param string $string
	 *
	 * @return Listeo_Core_iCal_Reader
	 *
	 * @throws Exception
	 */
	public function init_string( string $string ): Listeo_Core_iCal_Reader {
		$string = str_replace( array( "\r\n", "\n\r", "\r" ), "\n", $string );

		if ( empty( $this->cal ) ) {
			$lines = explode( "\n", $string );

			$this->init_lines( $lines );
		} else {
			trigger_error( 'Listeo_Core_iCal_Reader::initString: Calendar already initialised in constructor' );
		}

		return $this;
	}

	/**
	 * Initialises lines from a file
	 *
	 * @param string $file
	 *
	 * @return Listeo_Core_iCal_Reader
	 *
	 * @throws Exception
	 */
	public function init_file( string $file ): Listeo_Core_iCal_Reader {
		if ( empty( $this->cal ) ) {
			$lines = $this->file_or_url( $file );

			$this->init_lines( $lines );
		} else {
			trigger_error( 'Listeo_Core_iCal_Reader::initFile: Calendar already initialised in constructor' );
		}

		return $this;
	}

	/**
	 * Initialises lines from a URL
	 *
	 * @param string $url
	 * @param null $username
	 * @param null $password
	 * @param null $user_agent
	 * @param null $accept_language
	 *
	 * @return Listeo_Core_iCal_Reader
	 *
	 * @throws Exception
	 */
	public function init_url( string $url, $username = null, $password = null, $user_agent = null, $accept_language = null ): Listeo_Core_iCal_Reader {
		if ( ! is_null( $username ) && ! is_null( $password ) ) {
			$this->http_basic_auth['username'] = $username;
			$this->http_basic_auth['password'] = $password;
		}

		if ( ! is_null( $user_agent ) ) {
			$this->http_user_agent = $user_agent;
		}

		if ( ! is_null( $accept_language ) ) {
			$this->http_accept_language = $accept_language;
		}

		$this->init_file( $url );

		return $this;
	}

	/**
	 * Initialises the parser using an array
	 * containing each line of iCal content
	 *
	 * @param array $lines
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function init_lines( array $lines ) {
		$lines = $this->unfold( $lines );

		if ( stristr( $lines[0], 'BEGIN:VCALENDAR' ) !== false ) {
			$component = '';
			foreach ( $lines as $line ) {
				$line = rtrim( $line ); // Trim trailing whitespace
				$line = $this->remove_unprintable_chars( $line );

				if ( empty( $line ) ) {
					continue;
				}

				if ( ! $this->disable_character_replacement ) {
					$line = $this->clean_data( $line );
				}

				$add = $this->key_value_from_string( $line );

				if ( $add === false ) {
					continue;
				}

				$keyword = $add[0];
				$values  = $add[1]; // May be an array containing multiple values

				if ( ! is_array( $values ) ) {
					if ( ! empty( $values ) ) {
						$values     = array( $values ); // Make an array as not one already
						$blank_array = array(); // Empty placeholder array
						$values[]   = $blank_array;
					} else {
						$values = array(); // Use blank array to ignore this line
					}
				} elseif ( empty( $values[0] ) ) {
					$values = array(); // Use blank array to ignore this line
				}

				// Reverse so that our array of properties is processed first
				$values = array_reverse( $values );

				foreach ( $values as $value ) {
					switch ( $line ) {
						// https://www.kanzaki.com/docs/ical/vtodo.html
						case 'BEGIN:VTODO':
							if ( ! is_array( $value ) ) {
								$this->todo_count ++;
							}

							$component = 'VTODO';

							break;

						// https://www.kanzaki.com/docs/ical/vevent.html
						case 'BEGIN:VEVENT':
							if ( ! is_array( $value ) ) {
								$this->event_count ++;
							}

							$component = 'VEVENT';

							break;

						// https://www.kanzaki.com/docs/ical/vfreebusy.html
						case 'BEGIN:VFREEBUSY':
							if ( ! is_array( $value ) ) {
								$this->free_busy_index ++;
							}

							$component = 'VFREEBUSY';

							break;

						case 'BEGIN:VALARM':
							if ( ! is_array( $value ) ) {
								$this->alarm_count ++;
							}

							$component = 'VALARM';

							break;

						case 'END:VALARM':
							$component = 'VEVENT';

							break;

						case 'BEGIN:DAYLIGHT':
						case 'BEGIN:STANDARD':
						case 'BEGIN:VCALENDAR':
						case 'BEGIN:VTIMEZONE':
							$component = $value;

							break;

						case 'END:DAYLIGHT':
						case 'END:STANDARD':
						case 'END:VCALENDAR':
						case 'END:VFREEBUSY':
						case 'END:VTIMEZONE':
						case 'END:VTODO':
							$component = 'VCALENDAR';

							break;

						case 'END:VEVENT':
							if ( $this->should_filter_by_window ) {
								$this->remove_last_event_if_outside_window_and_non_recurring();
							}

							$component = 'VCALENDAR';

							break;

						default:
							$this->add_calendar_component_with_key_and_value( $component, $keyword, $value );

							break;
					}
				}
			}

			$this->process_events();

			if ( ! $this->skip_recurrence ) {
				$this->process_recurrences();

				// Apply changes to altered recurrence instances
				if ( ! empty( $this->altered_recurrence_instances ) ) {
					$events = $this->cal['VEVENT'];

					foreach ( $this->altered_recurrence_instances as $altered_recurrence_instance ) {
						if ( isset( $altered_recurrence_instance['altered-event'] ) ) {
							$altered_event  = $altered_recurrence_instance['altered-event'];
							$key            = key( $altered_event );
							$events[ $key ] = $altered_event[ $key ];
						}
					}

					$this->cal['VEVENT'] = $events;
				}
			}

			if ( $this->should_filter_by_window ) {
				$this->reduce_events_to_min_max_range();
			}

			$this->process_date_conversions();
		}
	}

	/**
	 * Removes the last event (i.e. most recently parsed) if its start date is outside the window spanned by
	 * `$window_min_timestamp` / `$window_max_timestamp`.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function remove_last_event_if_outside_window_and_non_recurring() {
		$events = $this->cal['VEVENT'];

		if ( ! empty( $events ) ) {
			$last_index = count( $events ) - 1;
			$last_event = $events[ $last_index ];

			if ( ( ! isset( $last_event['RRULE'] ) || $last_event['RRULE'] === '' ) && $this->does_event_start_outside_window( $last_event ) ) {
				$this->event_count --;

				unset( $events[ $last_index ] );
			}

			$this->cal['VEVENT'] = $events;
		}
	}

	/**
	 * Reduces the number of events to the defined minimum and maximum range
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function reduce_events_to_min_max_range() {
		$events = ( isset( $this->cal['VEVENT'] ) ) ? $this->cal['VEVENT'] : array();

		if ( ! empty( $events ) ) {
			foreach ( $events as $key => $an_event ) {
				if ( $an_event === null ) {
					unset( $events[ $key ] );

					continue;
				}

				if ( $this->does_event_start_outside_window( $an_event ) ) {
					$this->event_count --;

					unset( $events[ $key ] );

					continue;
				}
			}

			$this->cal['VEVENT'] = $events;
		}
	}

	/**
	 * Determines whether the event start date is outside `$window_min_timestamp` / `$window_max_timestamp`.
	 * Returns `true` for invalid dates.
	 *
	 * @param array $event
	 *
	 * @return boolean
	 *
	 * @throws Exception
	 */
	protected function does_event_start_outside_window( array $event ): bool {
		return ! $this->is_valid_date( $event['DTSTART'] ) || $this->is_out_of_range( $event['DTSTART'], $this->window_min_timestamp, $this->window_max_timestamp );
	}

	/**
	 * Determines whether a valid iCalendar date is within a given range
	 *
	 * @param string $calendar_date
	 * @param integer $min_timestamp
	 * @param integer $max_timestamp
	 *
	 * @return boolean
	 */
	protected function is_out_of_range( string $calendar_date, int $min_timestamp, int $max_timestamp ): bool {
		$timestamp = strtotime( explode( 'T', $calendar_date )[0] );

		return $timestamp < $min_timestamp || $timestamp > $max_timestamp;
	}

	/**
	 * Unfolds an iCal file in preparation for parsing
	 * (https://icalendar.org/iCalendar-RFC-5545/3-1-content-lines.html)
	 *
	 * @param array $lines
	 *
	 * @return array
	 */
	protected function unfold( array $lines ): array {
		$string = implode( PHP_EOL, $lines );
		$string = preg_replace( '/' . PHP_EOL . '[ \t]/', '', $string );

		$lines = explode( PHP_EOL, $string );

		return $lines;
	}

	/**
	 * Add one key and value pair to the `$this->cal` array
	 *
	 * @param string $component
	 * @param string|boolean $keyword
	 * @param string|array $value
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function add_calendar_component_with_key_and_value( string $component, $keyword, $value ) {
		if ( $keyword == false ) {
			$keyword = $this->last_keyword;
		}

		switch ( $component ) {
			case 'VALARM':
				$key1 = 'VEVENT';
				$key2 = ( $this->event_count - 1 );
				$key3 = $component;

				if ( ! isset( $this->cal[ $key1 ][ $key2 ][ $key3 ]["{$keyword}_array"] ) ) {
					$this->cal[ $key1 ][ $key2 ][ $key3 ]["{$keyword}_array"] = array();
				}

				if ( is_array( $value ) ) {
					// Add array of properties to the end
					$this->cal[ $key1 ][ $key2 ][ $key3 ]["{$keyword}_array"][] = $value;
				} else {
					if ( ! isset( $this->cal[ $key1 ][ $key2 ][ $key3 ][ $keyword ] ) ) {
						$this->cal[ $key1 ][ $key2 ][ $key3 ][ $keyword ] = $value;
					}

					if ( $this->cal[ $key1 ][ $key2 ][ $key3 ][ $keyword ] !== $value ) {
						$this->cal[ $key1 ][ $key2 ][ $key3 ][ $keyword ] .= ',' . $value;
					}
				}
				break;

			case 'VEVENT':
				$key1 = $component;
				$key2 = ( $this->event_count - 1 );

				if ( ! isset( $this->cal[ $key1 ][ $key2 ]["{$keyword}_array"] ) ) {
					$this->cal[ $key1 ][ $key2 ]["{$keyword}_array"] = array();
				}

				if ( is_array( $value ) ) {
					// Add array of properties to the end
					$this->cal[ $key1 ][ $key2 ]["{$keyword}_array"][] = $value;
				} else {
					if ( ! isset( $this->cal[ $key1 ][ $key2 ][ $keyword ] ) ) {
						$this->cal[ $key1 ][ $key2 ][ $keyword ] = $value;
					}

					if ( $keyword === 'EXDATE' ) {
						if ( trim( $value ) === $value ) {
							$array                                             = array_filter( explode( ',', $value ) );
							$this->cal[ $key1 ][ $key2 ]["{$keyword}_array"][] = $array;
						} else {
							$value                                              = explode( ',', implode( ',', $this->cal[ $key1 ][ $key2 ]["{$keyword}_array"][1] ) . trim( $value ) );
							$this->cal[ $key1 ][ $key2 ]["{$keyword}_array"][1] = $value;
						}
					} else {
						$this->cal[ $key1 ][ $key2 ]["{$keyword}_array"][] = $value;

						if ( $keyword === 'DURATION' ) {
							$duration                                          = new DateInterval( $value );
							$this->cal[ $key1 ][ $key2 ]["{$keyword}_array"][] = $duration;
						}
					}

					if ( $this->cal[ $key1 ][ $key2 ][ $keyword ] !== $value ) {
						$this->cal[ $key1 ][ $key2 ][ $keyword ] .= ',' . $value;
					}
				}
				break;

			case 'VFREEBUSY':
				$key1 = $component;
				$key2 = ( $this->free_busy_index - 1 );
				$key3 = $keyword;

				if ( $keyword === 'FREEBUSY' ) {
					if ( is_array( $value ) ) {
						$this->cal[ $key1 ][ $key2 ][ $key3 ][][] = $value;
					} else {
						$this->free_busy_count ++;

						end( $this->cal[ $key1 ][ $key2 ][ $key3 ] );
						$key = key( $this->cal[ $key1 ][ $key2 ][ $key3 ] );

						$value                                          = explode( '/', $value );
						$this->cal[ $key1 ][ $key2 ][ $key3 ][ $key ][] = $value;
					}
				} else {
					$this->cal[ $key1 ][ $key2 ][ $key3 ][] = $value;
				}
				break;

			case 'VTODO':
				$this->cal[ $component ][ $this->todo_count - 1 ][ $keyword ] = $value;

				break;

			default:
				$this->cal[ $component ][ $keyword ] = $value;

				break;
		}

		$this->last_keyword = $keyword;
	}

	/**
	 * Gets the key value pair from an iCal string
	 *
	 * @param string $text
	 *
	 * @return array|boolean
	 */
	protected function key_value_from_string( string $text ) {
		$text = htmlspecialchars( $text, ENT_NOQUOTES );

		$colon = strpos( $text, ':' );
		$quote = strpos( $text, '"' );
		if ( $colon === false ) {
			$matches = array();
		} elseif ( $quote === false || $colon < $quote ) {
			list( $before, $after ) = explode( ':', $text, 2 );
			$matches = array( $text, $before, $after );
		} else {
			list( $before, $text ) = explode( '"', $text, 2 );
			$text          = '"' . $text;
			$matches       = str_getcsv( $text, ':' );
			$combinedValue = '';

			foreach ( array_keys( $matches ) as $key ) {
				if ( $key === 0 ) {
					if ( ! empty( $before ) ) {
						$matches[ $key ] = $before . '"' . $matches[ $key ] . '"';
					}
				} else {
					if ( $key > 1 ) {
						$combinedValue .= ':';
					}

					$combinedValue .= $matches[ $key ];
				}
			}

			$matches    = array_slice( $matches, 0, 2 );
			$matches[1] = $combinedValue;
			array_unshift( $matches, $before . $text );
		}

		if ( count( $matches ) === 0 ) {
			return false;
		}

		if ( preg_match( '/^([A-Z-]+)([;][\w\W]*)?$/', $matches[1] ) ) {
			$matches = array_splice( $matches, 1, 2 ); // Remove first match and re-align ordering

			// Process properties
			if ( preg_match( '/([A-Z-]+)[;]([\w\W]*)/', $matches[0], $properties ) ) {
				// Remove first match
				array_shift( $properties );
				// Fix to ignore everything in keyword after a ; (e.g. Language, TZID, etc.)
				$matches[0] = $properties[0];
				array_shift( $properties ); // Repeat removing first match

				$formatted = array();
				foreach ( $properties as $property ) {
					// Match semicolon separator outside of quoted substrings
					preg_match_all( '~[^' . PHP_EOL . '";]+(?:"[^"\\\]*(?:\\\.[^"\\\]*)*"[^' . PHP_EOL . '";]*)*~', $property, $attributes );
					// Remove multi-dimensional array and use the first key
					$attributes = ( count( $attributes ) === 0 ) ? array( $property ) : reset( $attributes );

					if ( is_array( $attributes ) ) {
						foreach ( $attributes as $attribute ) {
							// Match equals sign separator outside of quoted substrings
							preg_match_all(
								'~[^' . PHP_EOL . '"=]+(?:"[^"\\\]*(?:\\\.[^"\\\]*)*"[^' . PHP_EOL . '"=]*)*~',
								$attribute,
								$values
							);
							// Remove multi-dimensional array and use the first key
							$value = ( count( $values ) === 0 ) ? null : reset( $values );

							if ( is_array( $value ) && isset( $value[1] ) ) {
								// Remove double quotes from beginning and end only
								$formatted[ $value[0] ] = trim( $value[1], '"' );
							}
						}
					}
				}

				// Assign the keyword property information
				$properties[0] = $formatted;

				// Add match to beginning of array
				array_unshift( $properties, $matches[1] );
				$matches[1] = $properties;
			}

			return $matches;
		} else {
			return false; // Ignore this match
		}
	}

	/**
	 * Returns a `DateTime` object from an iCal date time format
	 *
	 * @param string $ical_date
	 *
	 * @return DateTime
	 * @throws Exception
	 */
	public function ical_date_to_datetime( string $ical_date ): DateTime {
		/**
		 * iCal times may be in 3 formats, (https://www.kanzaki.com/docs/ical/dateTime.html)
		 *
		 * UTC:      Has a trailing 'Z'
		 * Floating: No time zone reference specified, no trailing 'Z', use local time
		 * TZID:     Set time zone as specified
		 *
		 * Use DateTime class objects to get around limitations with `mktime` and `gmmktime`.
		 * Must have a local time zone set to process floating times.
		 */
		$pattern = '/^(?:TZID=)?([^:]*|".*")'; // [1]: Time zone
		$pattern .= ':?';                       //      Time zone delimiter
		$pattern .= '([0-9]{8})';               // [2]: YYYYMMDD
		$pattern .= 'T?';                       //      Time delimiter
		$pattern .= '(?(?<=T)([0-9]{6}))';      // [3]: HHMMSS (filled if delimiter present)
		$pattern .= '(Z?)/';                    // [4]: UTC flag

		preg_match( $pattern, $ical_date, $date );

		if ( empty( $date ) ) {
			throw new Exception( 'Invalid iCal date format.' );
		}

		// A Unix timestamp usually cannot represent a date prior to 1 Jan 1970.
		// PHP, on the other hand, uses negative numbers for that. Thus we don't
		// need to special case them.

		if ( $date[4] === 'Z' ) {
			$date_time_zone = new DateTimeZone( self::TIME_ZONE_UTC );
		} elseif ( ! empty( $date[1] ) ) {
			$date_time_zone = $this->time_zone_string_to_date_time_zone( $date[1] );
		} else {
			$date_time_zone = new DateTimeZone( $this->default_timezone );
		}

		// The exclamation mark at the start of the format string indicates that if a
		// time portion is not included, the time in the returned DateTime should be
		// set to 00:00:00. Without it, the time would be set to the current system time.
		$dateFormat = '!Ymd';
		$dateBasic  = $date[2];
		if ( ! empty( $date[3] ) ) {
			$dateBasic  .= "T{$date[3]}";
			$dateFormat .= '\THis';
		}

		return DateTime::createFromFormat( $dateFormat, $dateBasic, $date_time_zone );
	}

	/**
	 * Returns a Unix timestamp from an iCal date time format
	 *
	 * @param string $ical_date
	 *
	 * @return integer
	 *
	 * @throws Exception
	 */
	public function ical_date_to_unix_timestamp( string $ical_date ): int {
		return $this->ical_date_to_datetime( $ical_date )->getTimestamp();
	}

	/**
	 * Returns a date adapted to the calendar time zone depending on the event `TZID`
	 *
	 * @param array $event
	 * @param string $key
	 * @param string $format
	 *
	 * @return string|boolean
	 */
	public function ical_date_with_timezone( array $event, string $key, $format = self::DATE_TIME_FORMAT ) {
		if ( ! isset( $event["{$key}_array"] ) || ! isset( $event[ $key ] ) ) {
			return false;
		}

		$date_array = $event["{$key}_array"];

		if ( $key === 'DURATION' ) {
			$datetime = $this->parse_duration( $event['DTSTART'], $date_array[2], null );
		} else {
			// When constructing from a Unix Timestamp, no time zone needs passing.
			$datetime = new DateTime( "@{$date_array[2]}" );
		}

		// Set the time zone we wish to use when running `$datetime->format`.
		$datetime->setTimezone( new DateTimeZone( $this->calendar_timezone() ) );

		if ( is_null( $format ) ) {
			return $datetime;
		}

		return $datetime->format( $format );
	}

	/**
	 * Performs admin tasks on all events as read from the iCal file.
	 * Adds a Unix timestamp to all `{DTSTART|DTEND|RECURRENCE-ID}_array` arrays
	 * Tracks modified recurrence instances
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function process_events() {
		$checks = null;
		$events = ( isset( $this->cal['VEVENT'] ) ) ? $this->cal['VEVENT'] : array();

		if ( ! empty( $events ) ) {
			foreach ( $events as $key => $an_event ) {
				foreach ( array( 'DTSTART', 'DTEND', 'RECURRENCE-ID' ) as $type ) {
					if ( isset( $an_event[ $type ] ) ) {
						$date = $an_event["{$type}_array"][1];

						if ( isset( $an_event["{$type}_array"][0]['TZID'] ) ) {
							$timezone = $this->escape_param_text( $an_event["{$type}_array"][0]['TZID'] );
							$date     = sprintf( self::ICAL_DATE_TIME_TEMPLATE, $timezone ) . $date;
						}

						$an_event["{$type}_array"][2] = $this->ical_date_to_unix_timestamp( $date );
						$an_event["{$type}_array"][3] = $date;
					}
				}

				if ( isset( $an_event['RECURRENCE-ID'] ) ) {
					$uid = $an_event['UID'];

					if ( ! isset( $this->altered_recurrence_instances[ $uid ] ) ) {
						$this->altered_recurrence_instances[ $uid ] = array();
					}

					$recurrence_date_utc                                = $this->ical_date_to_unix_timestamp( $an_event['RECURRENCE-ID_array'][3] );
					$this->altered_recurrence_instances[ $uid ][ $key ] = $recurrence_date_utc;
				}

				$events[ $key ] = $an_event;
			}

			$event_keys_to_remove = array();

			foreach ( $events as $key => $event ) {
				$checks[] = ! isset( $event['RECURRENCE-ID'] );
				$checks[] = isset( $event['UID'] );
				$checks[] = isset( $event['UID'] ) && isset( $this->altered_recurrence_instances[ $event['UID'] ] );

				if ( (bool) array_product( $checks ) ) {
					$event_dtstart_unix = $this->ical_date_to_unix_timestamp( $event['DTSTART_array'][3] );

					// phpcs:ignore CustomPHPCS.ControlStructures.AssignmentInCondition
					if ( ( $altered_event_key = array_search( $event_dtstart_unix, $this->altered_recurrence_instances[ $event['UID'] ] ) ) !== false ) {
						$event_keys_to_remove[] = $altered_event_key;

						$altered_event                                                        = array_replace_recursive( $events[ $key ], $events[ $altered_event_key ] );
						$this->altered_recurrence_instances[ $event['UID'] ]['altered-event'] = array( $key => $altered_event );
					}
				}

				unset( $checks );
			}

			foreach ( $event_keys_to_remove as $event_key_to_remove ) {
				$events[ $event_key_to_remove ] = null;
			}

			$this->cal['VEVENT'] = $events;
		}
	}

	/**
	 * Processes recurrence rules
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function process_recurrences() {
		$events = ( isset( $this->cal['VEVENT'] ) ) ? $this->cal['VEVENT'] : array();

		// If there are no events, then we have nothing to process.
		if ( empty( $events ) ) {
			return;
		}

		$all_event_recurrences = array();
		$event_keys_to_remove  = array();

		foreach ( $events as $key => $an_event ) {
			if ( ! isset( $an_event['RRULE'] ) || $an_event['RRULE'] === '' ) {
				continue;
			}

			// Tag as generated by a recurrence rule
			$an_event['RRULE_array'][2] = self::RECURRENCE_EVENT;

			// Create new initial starting point.
			$initial_event_date = $this->ical_date_to_datetime( $an_event['DTSTART_array'][3] );

			// Separate the RRULE stanzas, and explode the values that are lists.
			$rrules = array();
			foreach ( explode( ';', $an_event['RRULE'] ) as $s ) {
				list( $k, $v ) = explode( '=', $s );
				if ( in_array( $k, array( 'BYSETPOS', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'BYYEARDAY', 'BYWEEKNO' ) ) ) {
					$rrules[ $k ] = explode( ',', $v );
				} else {
					$rrules[ $k ] = $v;
				}
			}

			// Get frequency
			$frequency = $rrules['FREQ'];

			// Reject RRULE if BYDAY stanza is invalid:
			// > The BYDAY rule part MUST NOT be specified with a numeric value
			// > when the FREQ rule part is not set to MONTHLY or YEARLY.
			// > Furthermore, the BYDAY rule part MUST NOT be specified with a
			// > numeric value with the FREQ rule part set to YEARLY when the
			// > BYWEEKNO rule part is specified.
			if ( isset( $rrules['BYDAY'] ) ) {
				$check_by_days = function ( $carry, $weekday ) {
					return $carry && substr( $weekday, - 2 ) === $weekday;
				};
				if ( ! in_array( $frequency, array( 'MONTHLY', 'YEARLY' ) ) ) {
					if ( ! array_reduce( $rrules['BYDAY'], $check_by_days, true ) ) {
						error_log( "Listeo_Core_iCal_Reader::ProcessRecurrences: A {$frequency} RRULE may not contain BYDAY values with numeric prefixes" );

						continue;
					}
				} elseif ( $frequency === 'YEARLY' && ! empty( $rrules['BYWEEKNO'] ) ) {
					if ( ! array_reduce( $rrules['BYDAY'], $check_by_days, true ) ) {
						error_log( 'Listeo_Core_iCal_Reader::ProcessRecurrences: A YEARLY RRULE with a BYWEEKNO part may not contain BYDAY values with numeric prefixes' );

						continue;
					}
				}
			}

			// Get Interval
			$interval = ( empty( $rrules['INTERVAL'] ) ) ? 1 : $rrules['INTERVAL'];

			// Throw an error if this isn't an integer.
			if ( ! is_int( $this->default_span ) ) {
				trigger_error( 'Listeo_Core_iCal_Reader::defaultSpan: User defined value is not an integer' );
			}

			// Compute EXDATEs
			$exdates = $this->parse_exdates( $an_event );

			// Determine if the initial date is also an EXDATE
			$initial_date_is_exdate = array_reduce( $exdates, function ( $carry, $exdate ) use ( $initial_event_date ) {
				return $carry || $exdate->getTimestamp() == $initial_event_date->getTimestamp();
			}, false );

			if ( $initial_date_is_exdate ) {
				$event_keys_to_remove[] = $key;
			}

			/**
			 * Determine at what point we should stop calculating recurrences
			 * by looking at the UNTIL or COUNT rrule stanza, or, if neither
			 * if set, using a fallback.
			 *
			 * If the initial date is also an EXDATE, it shouldn't be included
			 * in the count.
			 *
			 * Syntax:
			 *   UNTIL={enddate}
			 *   COUNT=<positive integer>
			 *
			 * Where:
			 *   enddate = <icalDate> || <icalDateTime>
			 */
			$count       = 1;
			$count_limit = ( isset( $rrules['COUNT'] ) ) ? intval( $rrules['COUNT'] ) : 0;
			$until       = date_create()->modify( "{$this->default_span} years" )->setTime( 23, 59, 59 )->getTimestamp();

			if ( isset( $rrules['UNTIL'] ) ) {
				$until = min( $until, $this->ical_date_to_unix_timestamp( $rrules['UNTIL'] ) );
			}

			$event_recurrences = array();

			$frequency_recurring_date_time = clone $initial_event_date;
			while ( $frequency_recurring_date_time->getTimestamp() <= $until ) {
				$candidate_datetimes = array();

				// phpcs:ignore Squiz.ControlStructures.SwitchDeclaration.MissingDefault
				switch ( $frequency ) {
					case 'DAILY':
						if ( ! empty( $rrules['BYMONTHDAY'] ) ) {
							if ( ! isset( $month_days ) ) {
								// This variable is unset when we change months (see below)
								$month_days = $this->get_days_of_month_matching_by_month_day_r_rule( $rrules['BYMONTHDAY'], $frequency_recurring_date_time );
							}

							if ( ! in_array( $frequency_recurring_date_time->format( 'j' ), $month_days ) ) {
								break;
							}
						}

						$candidate_datetimes[] = clone $frequency_recurring_date_time;

						break;

					case 'WEEKLY':
						$initial_day_of_week = $frequency_recurring_date_time->format( 'N' );
						$matching_days       = array( $initial_day_of_week );

						if ( ! empty( $rrules['BYDAY'] ) ) {
							// setISODate() below uses the ISO-8601 specification of weeks: start on
							// a Monday, end on a Sunday. However, RRULEs (or the caller of the
							// parser) may state an alternate WeeKSTart.
							$wkst_transition = 7;

							if ( empty( $rrules['WKST'] ) ) {
								if ( $this->default_week_start !== self::ISO_8601_WEEK_START ) {
									$wkst_transition = array_search( $this->default_week_start, array_keys( $this->weekdays ) );
								}
							} elseif ( $rrules['WKST'] !== self::ISO_8601_WEEK_START ) {
								$wkst_transition = array_search( $rrules['WKST'], array_keys( $this->weekdays ) );
							}

							$matching_days = array_map(
								function ( $weekday ) use ( $initial_day_of_week, $wkst_transition, $interval ) {
									$day = array_search( $weekday, array_keys( $this->weekdays ) );

									if ( $day < $initial_day_of_week ) {
										$day += 7;
									}

									if ( $day >= $wkst_transition ) {
										$day += 7 * ( $interval - 1 );
									}

									// Ignoring alternate week starts, $day at this point will have a
									// value between 0 and 6. But setISODate() expects a value of 1 to 7.
									// Even with alternate week starts, we still need to +1 to set the
									// correct weekday.
									$day ++;

									return $day;
								},
								$rrules['BYDAY']
							);
						}

						sort( $matching_days );

						foreach ( $matching_days as $day ) {
							$cloned_datetime       = clone $frequency_recurring_date_time;
							$candidate_datetimes[] = $cloned_datetime->setISODate(
								$frequency_recurring_date_time->format( 'o' ),
								$frequency_recurring_date_time->format( 'W' ),
								$day
							);
						}
						break;

					case 'MONTHLY':
						$matching_days = array();

						if ( ! empty( $rrules['BYMONTHDAY'] ) ) {
							$matching_days = $this->get_days_of_month_matching_by_month_day_r_rule( $rrules['BYMONTHDAY'], $frequency_recurring_date_time );
							if ( ! empty( $rrules['BYDAY'] ) ) {
								$matching_days = array_filter(
									$this->get_days_of_month_matching_by_day_r_rule( $rrules['BYDAY'], $frequency_recurring_date_time ),
									function ( $monthDay ) use ( $matching_days ) {
										return in_array( $monthDay, $matching_days );
									}
								);
							}
						} elseif ( ! empty( $rrules['BYDAY'] ) ) {
							$matching_days = $this->get_days_of_month_matching_by_day_r_rule( $rrules['BYDAY'], $frequency_recurring_date_time );
						}

						if ( ! empty( $rrules['BYSETPOS'] ) ) {
							$matching_days = $this->filter_values_using_by_set_pos_r_rule( $rrules['BYSETPOS'], $matching_days );
						}

						foreach ( $matching_days as $day ) {
							// Skip invalid dates (e.g. 30th February)
							if ( $day > $frequency_recurring_date_time->format( 't' ) ) {
								continue;
							}

							$cloned_datetime       = clone $frequency_recurring_date_time;
							$candidate_datetimes[] = $cloned_datetime->setDate(
								$frequency_recurring_date_time->format( 'Y' ),
								$frequency_recurring_date_time->format( 'm' ),
								$day
							);
						}
						break;

					case 'YEARLY':
						$matching_days = array();

						if ( ! empty( $rrules['BYMONTH'] ) ) {
							$bymonth_recurring_datetime = clone $frequency_recurring_date_time;
							foreach ( $rrules['BYMONTH'] as $byMonth ) {
								$bymonth_recurring_datetime->setDate(
									$frequency_recurring_date_time->format( 'Y' ),
									$byMonth,
									$frequency_recurring_date_time->format( 'd' )
								);

								// Determine the days of the month affected
								// (The interaction between BYMONTHDAY and BYDAY is resolved later.)
								$month_days = array();
								if ( ! empty( $rrules['BYMONTHDAY'] ) ) {
									$month_days = $this->get_days_of_month_matching_by_month_day_r_rule( $rrules['BYMONTHDAY'], $bymonth_recurring_datetime );
								} elseif ( ! empty( $rrules['BYDAY'] ) ) {
									$month_days = $this->get_days_of_month_matching_by_day_r_rule( $rrules['BYDAY'], $bymonth_recurring_datetime );
								} else {
									$month_days[] = $bymonth_recurring_datetime->format( 'd' );
								}

								// And add each of them to the list of recurrences
								foreach ( $month_days as $day ) {
									$matching_days[] = intval(
										                   $bymonth_recurring_datetime->setDate(
											                   $frequency_recurring_date_time->format( 'Y' ),
											                   $bymonth_recurring_datetime->format( 'm' ),
											                   $day
										                   )->format( 'z' )
									                   ) + 1;
								}
							}
						} elseif ( ! empty( $rrules['BYWEEKNO'] ) ) {
							$matching_days = $this->get_days_of_year_matching_by_week_no_r_rule( $rrules['BYWEEKNO'], $frequency_recurring_date_time );
						} elseif ( ! empty( $rrules['BYYEARDAY'] ) ) {
							$matching_days = $this->get_days_of_year_matching_by_year_day_r_rule( $rrules['BYYEARDAY'], $frequency_recurring_date_time );
						} elseif ( ! empty( $rrules['BYMONTHDAY'] ) ) {
							$matching_days = $this->get_days_of_year_matching_by_month_day_r_rule( $rrules['BYMONTHDAY'], $frequency_recurring_date_time );
						}

						if ( ! empty( $rrules['BYDAY'] ) ) {
							if ( ! empty( $rrules['BYYEARDAY'] ) || ! empty( $rrules['BYMONTHDAY'] ) || ! empty( $rrules['BYWEEKNO'] ) ) {
								$matching_days = array_filter(
									$this->get_days_of_year_matching_by_day_r_rule( $rrules['BYDAY'], $frequency_recurring_date_time ),
									function ( $yearDay ) use ( $matching_days ) {
										return in_array( $yearDay, $matching_days );
									}
								);
							} elseif ( count( $matching_days ) === 0 ) {
								$matching_days = $this->get_days_of_year_matching_by_day_r_rule( $rrules['BYDAY'], $frequency_recurring_date_time );
							}
						}

						if ( count( $matching_days ) === 0 ) {
							$matching_days = array( intval( $frequency_recurring_date_time->format( 'z' ) ) + 1 );
						} else {
							sort( $matching_days );
						}

						if ( ! empty( $rrules['BYSETPOS'] ) ) {
							$matching_days = $this->filter_values_using_by_set_pos_r_rule( $rrules['BYSETPOS'], $matching_days );
						}

						foreach ( $matching_days as $day ) {
							$cloned_datetime       = clone $frequency_recurring_date_time;
							$candidate_datetimes[] = $cloned_datetime->setDate(
								$frequency_recurring_date_time->format( 'Y' ),
								1,
								$day
							);
						}
						break;
				}

				foreach ( $candidate_datetimes as $candidate ) {
					$timestamp = $candidate->getTimestamp();
					if ( $timestamp <= $initial_event_date->getTimestamp() ) {
						continue;
					}

					if ( $timestamp > $until ) {
						break;
					}

					// Exclusions
					$is_excluded = array_filter( $exdates, function ( $exdate ) use ( $timestamp ) {
						return $exdate->getTimestamp() == $timestamp;
					} );

					if ( isset( $this->altered_recurrence_instances[ $an_event['UID'] ] ) ) {
						if ( in_array( $timestamp, $this->altered_recurrence_instances[ $an_event['UID'] ] ) ) {
							$is_excluded = true;
						}
					}

					if ( ! $is_excluded ) {
						$event_recurrences[] = $candidate;
						$this->event_count ++;
					}

					// Count all evaluated candidates including excluded ones
					if ( isset( $rrules['COUNT'] ) ) {
						$count ++;

						// If RRULE[COUNT] is reached then break
						if ( $count >= $count_limit ) {
							break 2;
						}
					}
				}

				// Move forwards $interval $frequency.
				$month_pre_move = $frequency_recurring_date_time->format( 'm' );
				$frequency_recurring_date_time->modify( "{$interval} {$this->frequency_conversion[$frequency]}" );

				// As noted in Example #2 on https://www.php.net/manual/en/datetime.modify.php,
				// there are some occasions where adding months doesn't give the month you might
				// expect. For instance: January 31st + 1 month == March 3rd (March 2nd on a leap
				// year.) The following code crudely rectifies this.
				if ( $frequency === 'MONTHLY' ) {
					$month_diff = $frequency_recurring_date_time->format( 'm' ) - $month_pre_move;

					if ( ( $month_diff > 0 && $month_diff > $interval ) || ( $month_diff < 0 && $month_diff > $interval - 12 ) ) {
						$frequency_recurring_date_time->modify( '-1 month' );
					}
				}

				// $month_days is set in the DAILY frequency if the BYMONTHDAY stanza is present in
				// the RRULE. The variable only needs to be updated when we change months, so we
				// unset it here, prompting a recreation next iteration.
				if ( isset( $month_days ) && $frequency_recurring_date_time->format( 'm' ) !== $month_pre_move ) {
					unset( $month_days );
				}
			}

			unset( $month_days ); // Unset it here as well, so it doesn't bleed into the calculation of the next recurring event.

			// Determine event length
			$event_length = 0;
			if ( isset( $an_event['DURATION'] ) ) {
				$cloned_datetime = clone $initial_event_date;
				$end_date        = $cloned_datetime->add( $an_event['DURATION_array'][2] );
				$event_length    = $end_date->getTimestamp() - $an_event['DTSTART_array'][2];
			} elseif ( isset( $an_event['DTEND_array'] ) ) {
				$event_length = $an_event['DTEND_array'][2] - $an_event['DTSTART_array'][2];
			}

			// Whether or not the initial date was UTC
			$initial_date_was_utc = substr( $an_event['DTSTART'], - 1 ) === 'Z';

			// Build the param array
			$date_param_array = array();
			if (
				! $initial_date_was_utc
				&& isset( $an_event['DTSTART_array'][0]['TZID'] )
				&& $this->is_valid_time_zone_id( $an_event['DTSTART_array'][0]['TZID'] )
			) {
				$date_param_array['TZID'] = $an_event['DTSTART_array'][0]['TZID'];
			}

			// Populate the `DT{START|END}[_array]`s
			$event_recurrences = array_map(
				function ( $recurring_datetime ) use ( $an_event, $event_length, $initial_date_was_utc, $date_param_array ) {
					$tzid_prefix = ( isset( $date_param_array['TZID'] ) ) ? 'TZID=' . $this->escape_param_text( $date_param_array['TZID'] ) . ':' : '';

					foreach ( array( 'DTSTART', 'DTEND' ) as $dtkey ) {
						$an_event[ $dtkey ] = $recurring_datetime->format( self::DATE_TIME_FORMAT ) . ( ( $initial_date_was_utc ) ? 'Z' : '' );

						$an_event["{$dtkey}_array"] = array(
							$date_param_array,                    // [0] Array of params (incl. TZID)
							$an_event[ $dtkey ],                   // [1] ICalDateTime string w/o TZID
							$recurring_datetime->getTimestamp(), // [2] Unix Timestamp
							"{$tzid_prefix}{$an_event[$dtkey]}",  // [3] Full ICalDateTime string
						);

						if ( $dtkey !== 'DTEND' ) {
							$recurring_datetime->modify( "{$event_length} seconds" );
						}
					}

					return $an_event;
				},
				$event_recurrences
			);

			$all_event_recurrences = array_merge( $all_event_recurrences, $event_recurrences );
		}

		// Nullify the initial events that are also EXDATEs
		foreach ( $event_keys_to_remove as $event_key_to_remove ) {
			$events[ $event_key_to_remove ] = null;
		}

		$events = array_merge( $events, $all_event_recurrences );

		$this->cal['VEVENT'] = $events;
	}

	/**
	 * Resolves values from indices of the range 1 -> $limit.
	 *
	 * For instance, if passed [1, 4, -16] and 28, this will return [1, 4, 13].
	 *
	 * @param array $indexes
	 * @param int $limit
	 *
	 * @return array
	 */
	protected function resolve_indices_of_range( array $indexes, int $limit ): array {
		$matching = array();
		foreach ( $indexes as $index ) {
			if ( $index > 0 && $index <= $limit ) {
				$matching[] = $index;
			} elseif ( $index < 0 && - $index <= $limit ) {
				$matching[] = $index + $limit + 1;
			}
		}

		sort( $matching );

		return $matching;
	}

	/**
	 * Find all days of a month that match the BYDAY stanza of an RRULE.
	 *
	 * With no {ordwk}, then return the day number of every {weekday}
	 * within the month.
	 *
	 * With a +ve {ordwk}, then return the {ordwk} {weekday} within the
	 * month.
	 *
	 * With a -ve {ordwk}, then return the {ordwk}-to-last {weekday}
	 * within the month.
	 *
	 * RRule Syntax:
	 *   BYDAY={bywdaylist}
	 *
	 * Where:
	 *   bywdaylist = {weekdaynum}[,{weekdaynum}...]
	 *   weekdaynum = [[+]{ordwk} || -{ordwk}]{weekday}
	 *   ordwk      = 1 to 53
	 *   weekday    = SU || MO || TU || WE || TH || FR || SA
	 *
	 * @param array $by_days
	 * @param DateTime $initial_date_time
	 *
	 * @return array
	 */
	protected function get_days_of_month_matching_by_day_r_rule( array $by_days, DateTime $initial_date_time ): array {
		$matching_days = array();

		foreach ( $by_days as $weekday ) {
			$byday_datetime = clone $initial_date_time;

			$ordwk = intval( substr( $weekday, 0, - 2 ) );

			// Quantise the date to the first instance of the requested day in a month
			// (Or last if we have a -ve {ordwk})
			$byday_datetime->modify(
				( ( $ordwk < 0 ) ? 'Last' : 'First' ) .
				' ' .
				$this->weekdays[ substr( $weekday, - 2 ) ] . // e.g. "Monday"
				' of ' .
				$initial_date_time->format( 'F' ) // e.g. "June"
			);

			if ( $ordwk < 0 ) { // -ve {ordwk}
				$byday_datetime->modify( ( ++ $ordwk ) . ' week' );
				$matching_days[] = $byday_datetime->format( 'j' );
			} elseif ( $ordwk > 0 ) { // +ve {ordwk}
				$byday_datetime->modify( ( -- $ordwk ) . ' week' );
				$matching_days[] = $byday_datetime->format( 'j' );
			} else { // No {ordwk}
				while ( $byday_datetime->format( 'n' ) === $initial_date_time->format( 'n' ) ) {
					$matching_days[] = $byday_datetime->format( 'j' );
					$byday_datetime->modify( '+1 week' );
				}
			}
		}

		// Sort into ascending order
		sort( $matching_days );

		return $matching_days;
	}

	/**
	 * Find all days of a month that match the BYMONTHDAY stanza of an RRULE.
	 *
	 * RRUle Syntax:
	 *   BYMONTHDAY={bymodaylist}
	 *
	 * Where:
	 *   bymodaylist = {monthdaynum}[,{monthdaynum}...]
	 *   monthdaynum = ([+] || -) {ordmoday}
	 *   ordmoday    = 1 to 31
	 *
	 * @param array $by_month_days
	 * @param DateTime $initial_date_time
	 *
	 * @return array
	 */
	protected function get_days_of_month_matching_by_month_day_r_rule( array $by_month_days, DateTime $initial_date_time ): array {
		return $this->resolve_indices_of_range( $by_month_days, $initial_date_time->format( 't' ) );
	}

	/**
	 * Find all days of a year that match the BYDAY stanza of an RRULE.
	 *
	 * With no {ordwk}, then return the day number of every {weekday}
	 * within the year.
	 *
	 * With a +ve {ordwk}, then return the {ordwk} {weekday} within the
	 * year.
	 *
	 * With a -ve {ordwk}, then return the {ordwk}-to-last {weekday}
	 * within the year.
	 *
	 * RRule Syntax:
	 *   BYDAY={bywdaylist}
	 *
	 * Where:
	 *   bywdaylist = {weekdaynum}[,{weekdaynum}...]
	 *   weekdaynum = [[+]{ordwk} || -{ordwk}]{weekday}
	 *   ordwk      = 1 to 53
	 *   weekday    = SU || MO || TU || WE || TH || FR || SA
	 *
	 * @param array $by_days
	 * @param DateTime $initial_date_time
	 *
	 * @return array
	 */
	protected function get_days_of_year_matching_by_day_r_rule( array $by_days, DateTime $initial_date_time ): array {
		$matching_days = array();

		foreach ( $by_days as $weekday ) {
			$byday_datetime = clone $initial_date_time;

			$ordwk = intval( substr( $weekday, 0, - 2 ) );

			// Quantise the date to the first instance of the requested day in a year
			// (Or last if we have a -ve {ordwk})
			$byday_datetime->modify(
				( ( $ordwk < 0 ) ? 'Last' : 'First' ) .
				' ' .
				$this->weekdays[ substr( $weekday, - 2 ) ] . // e.g. "Monday"
				' of ' . ( ( $ordwk < 0 ) ? 'December' : 'January' ) .
				' ' . $initial_date_time->format( 'Y' ) // e.g. "2018"
			);

			if ( $ordwk < 0 ) { // -ve {ordwk}
				$byday_datetime->modify( ( ++ $ordwk ) . ' week' );
				$matching_days[] = intval( $byday_datetime->format( 'z' ) ) + 1;
			} elseif ( $ordwk > 0 ) { // +ve {ordwk}
				$byday_datetime->modify( ( -- $ordwk ) . ' week' );
				$matching_days[] = intval( $byday_datetime->format( 'z' ) ) + 1;
			} else { // No {ordwk}
				while ( $byday_datetime->format( 'Y' ) === $initial_date_time->format( 'Y' ) ) {
					$matching_days[] = intval( $byday_datetime->format( 'z' ) ) + 1;
					$byday_datetime->modify( '+1 week' );
				}
			}
		}

		// Sort into ascending order
		sort( $matching_days );

		return $matching_days;
	}

	/**
	 * Find all days of a year that match the BYYEARDAY stanza of an RRULE.
	 *
	 * RRUle Syntax:
	 *   BYYEARDAY={byyrdaylist}
	 *
	 * Where:
	 *   byyrdaylist = {yeardaynum}[,{yeardaynum}...]
	 *   yeardaynum  = ([+] || -) {ordyrday}
	 *   ordyrday    = 1 to 366
	 *
	 * @param array $byYearDays
	 * @param DateTime $initial_date_time
	 *
	 * @return array
	 */
	protected function get_days_of_year_matching_by_year_day_r_rule( array $byYearDays, DateTime $initial_date_time ): array {
		// `DateTime::format('L')` returns 1 if leap year, 0 if not.
		$days_in_this_year = $initial_date_time->format( 'L' ) ? 366 : 365;

		return $this->resolve_indices_of_range( $byYearDays, $days_in_this_year );
	}

	/**
	 * Find all days of a year that match the BYWEEKNO stanza of an RRULE.
	 *
	 * Unfortunately, the RFC5545 specification does not specify exactly
	 * how BYWEEKNO should expand on the initial DTSTART when provided
	 * without any other stanzas.
	 *
	 * A comparison of expansions used by other ics parsers may be found
	 * at https://github.com/s0600204/ics-parser-1/wiki/byweekno
	 *
	 * This method uses the same expansion as the python-dateutil module.
	 *
	 * RRUle Syntax:
	 *   BYWEEKNO={bywknolist}
	 *
	 * Where:
	 *   bywknolist = {weeknum}[,{weeknum}...]
	 *   weeknum    = ([+] || -) {ordwk}
	 *   ordwk      = 1 to 53
	 *
	 * @param array $byWeekNums
	 * @param DateTime $initial_date_time
	 *
	 * @return array
	 */
	protected function get_days_of_year_matching_by_week_no_r_rule( array $byWeekNums, DateTime $initial_date_time ): array {
		// `DateTime::format('L')` returns 1 if leap year, 0 if not.
		$is_leap_year          = $initial_date_time->format( 'L' );
		$first_day_of_the_year = date_create( "first day of January {$initial_date_time->format('Y')}" )->format( 'D' );
		$weeks_in_this_year    = ( $first_day_of_the_year === 'Thu' || $is_leap_year && $first_day_of_the_year === 'Wed' ) ? 53 : 52;

		$matching_weeks  = $this->resolve_indices_of_range( $byWeekNums, $weeks_in_this_year );
		$matching_days   = array();
		$byweek_datetime = clone $initial_date_time;
		foreach ( $matching_weeks as $week_num ) {
			$day_num = intval(
				           $byweek_datetime->setISODate(
					           $initial_date_time->format( 'Y' ),
					           $week_num
				           )->format( 'z' )
			           ) + 1;
			for ( $x = 0; $x < 7; ++ $x ) {
				$matching_days[] = $x + $day_num;
			}
		}

		sort( $matching_days );

		return $matching_days;
	}

	/**
	 * Find all days of a year that match the BYMONTHDAY stanza of an RRULE.
	 *
	 * RRule Syntax:
	 *   BYMONTHDAY={bymodaylist}
	 *
	 * Where:
	 *   bymodaylist = {monthdaynum}[,{monthdaynum}...]
	 *   monthdaynum = ([+] || -) {ordmoday}
	 *   ordmoday    = 1 to 31
	 *
	 * @param array $by_month_days
	 * @param DateTime $initial_date_time
	 *
	 * @return array
	 */
	protected function get_days_of_year_matching_by_month_day_r_rule( array $by_month_days, DateTime $initial_date_time ): array {
		$matching_days   = array();
		$month_date_time = clone $initial_date_time;
		for ( $month = 1; $month < 13; $month ++ ) {
			$month_date_time->setDate(
				$initial_date_time->format( 'Y' ),
				$month,
				1
			);

			$month_days = $this->get_days_of_month_matching_by_month_day_r_rule( $by_month_days, $month_date_time );
			foreach ( $month_days as $day ) {
				$matching_days[] = intval(
					                   $month_date_time->setDate(
						                   $initial_date_time->format( 'Y' ),
						                   $month_date_time->format( 'm' ),
						                   $day
					                   )->format( 'z' )
				                   ) + 1;
			}
		}

		return $matching_days;
	}

	/**
	 * Filters a provided values-list by applying a BYSETPOS RRule.
	 *
	 * Where a +ve {daynum} is provided, the {ordday} position'd value as
	 * measured from the start of the list of values should be retained.
	 *
	 * Where a -ve {daynum} is provided, the {ordday} position'd value as
	 * measured from the end of the list of values should be retained.
	 *
	 * RRule Syntax:
	 *   BYSETPOS={bysplist}
	 *
	 * Where:
	 *   bysplist  = {setposday}[,{setposday}...]
	 *   setposday = {daynum}
	 *   daynum    = [+ || -] {ordday}
	 *   ordday    = 1 to 366
	 *
	 * @param array $by_set_pos
	 * @param array $values_list
	 *
	 * @return array
	 */
	protected function filter_values_using_by_set_pos_r_rule( array $by_set_pos, array $values_list ): array {
		$filtered_matches = array();

		foreach ( $by_set_pos as $set_position ) {
			if ( $set_position < 0 ) {
				$set_position = count( $values_list ) + ++ $set_position;
			}

			// Positioning starts at 1, array indexes start at 0
			if ( isset( $values_list[ $set_position - 1 ] ) ) {
				$filtered_matches[] = $values_list[ $set_position - 1 ];
			}
		}

		return $filtered_matches;
	}

	/**
	 * Processes date conversions using the time zone
	 *
	 * Add keys `DTSTART_tz` and `DTEND_tz` to each Event
	 * These keys contain dates adapted to the calendar
	 * time zone depending on the event `TZID`.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function process_date_conversions() {
		$events = ( isset( $this->cal['VEVENT'] ) ) ? $this->cal['VEVENT'] : array();

		if ( ! empty( $events ) ) {
			foreach ( $events as $key => $an_event ) {
				if ( is_null( $an_event ) || ! $this->is_valid_date( $an_event['DTSTART'] ) ) {
					unset( $events[ $key ] );
					$this->event_count --;

					continue;
				}

				$events[ $key ]['DTSTART_tz'] = $this->ical_date_with_timezone( $an_event, 'DTSTART' );

				if ( $this->ical_date_with_timezone( $an_event, 'DTEND' ) ) {
					$events[ $key ]['DTEND_tz'] = $this->ical_date_with_timezone( $an_event, 'DTEND' );
				} elseif ( $this->ical_date_with_timezone( $an_event, 'DURATION' ) ) {
					$events[ $key ]['DTEND_tz'] = $this->ical_date_with_timezone( $an_event, 'DURATION' );
				} else {
					$events[ $key ]['DTEND_tz'] = $events[ $key ]['DTSTART_tz'];
				}
			}

			$this->cal['VEVENT'] = $events;
		}
	}

	/**
	 * Returns an array of Events.
	 * Every event is a class with the event
	 * details being properties within it.
	 *
	 * @return array
	 */
	public function events(): array {
		$array = $this->cal;
		$array = isset( $array['VEVENT'] ) ? $array['VEVENT'] : array();

		$events = array();

		if ( ! empty( $array ) ) {
			foreach ( $array as $event ) {
				$events[] = new Listeo_Core_ICal_Event_Reader( $event );
			}
		}

		return $events;
	}

	/**
	 * Returns the calendar name
	 *
	 * @return string
	 */
	public function calendar_name(): string {
		return isset( $this->cal['VCALENDAR']['X-WR-CALNAME'] ) ? $this->cal['VCALENDAR']['X-WR-CALNAME'] : '';
	}

	/**
	 * Returns the calendar description
	 *
	 * @return string
	 */
	public function calendar_description(): string {
		return isset( $this->cal['VCALENDAR']['X-WR-CALDESC'] ) ? $this->cal['VCALENDAR']['X-WR-CALDESC'] : '';
	}

	/**
	 * Returns the calendar time zone
	 *
	 * @param boolean $ignore_utc
	 *
	 * @return string
	 */
	public function calendar_timezone( $ignore_utc = false ): ?string {
		if ( isset( $this->cal['VCALENDAR']['X-WR-TIMEZONE'] ) ) {
			$timezone = $this->cal['VCALENDAR']['X-WR-TIMEZONE'];
		} elseif ( isset( $this->cal['VTIMEZONE']['TZID'] ) ) {
			$timezone = $this->cal['VTIMEZONE']['TZID'];
		} else {
			$timezone = $this->default_timezone;
		}

		// Validate the time zone, falling back to the time zone set in the PHP environment.
		$timezone = $this->time_zone_string_to_date_time_zone( $timezone )->getName();

		if ( $ignore_utc && strtoupper( $timezone ) === self::TIME_ZONE_UTC ) {
			return null;
		}

		return $timezone;
	}

	/**
	 * Returns an array of arrays with all free/busy events.
	 * Every event is an associative array and each property
	 * is an element it.
	 *
	 * @return array
	 */
	public function free_busy_events(): array {
		$array = $this->cal;

		return isset( $array['VFREEBUSY'] ) ? $array['VFREEBUSY'] : array();
	}

	/**
	 * Returns a boolean value whether the
	 * current calendar has events or not
	 *
	 * @return boolean
	 */
	public function has_events(): bool {
		return ( count( $this->events() ) > 0 ) ?: false;
	}

	/**
	 * Returns a sorted array of the events in a given range,
	 * or an empty array if no events exist in the range.
	 *
	 * Events will be returned if the start or end date is contained within the
	 * range (inclusive), or if the event starts before and end after the range.
	 *
	 * If a start date is not specified or of a valid format, then the start
	 * of the range will default to the current time and date of the server.
	 *
	 * If an end date is not specified or of a valid format, then the end of
	 * the range will default to the current time and date of the server,
	 * plus 20 years.
	 *
	 * Note that this function makes use of Unix timestamps. This might be a
	 * problem for events on, during, or after 29 Jan 2038.
	 * See https://en.wikipedia.org/wiki/Unix_time#Representing_the_number
	 *
	 * @param string|null $range_start
	 * @param string|null $range_end
	 *
	 * @return array
	 * @throws Exception
	 */
	public function events_from_range( $range_start = null, $range_end = null ): array {
		// Sort events before processing range
		$events = $this->sort_events_with_order( $this->events() );

		if ( empty( $events ) ) {
			return array();
		}

		$extended_events = array();

		if ( ! is_null( $range_start ) ) {
			try {
				$range_start = new DateTime( $range_start, new DateTimeZone( $this->default_timezone ) );
			} catch ( Exception $exception ) {
				error_log( "Listeo_Core_iCal_Reader::events_from_range: Invalid date passed ({$range_start})" );
				$range_start = false;
			}
		} else {
			$range_start = new DateTime( 'now', new DateTimeZone( $this->default_timezone ) );
		}

		if ( ! is_null( $range_end ) ) {
			try {
				$range_end = new DateTime( $range_end, new DateTimeZone( $this->default_timezone ) );
			} catch ( Exception $exception ) {
				error_log( "Listeo_Core_iCal_Reader::events_from_range: Invalid date passed ({$range_end})" );
				$range_end = false;
			}
		} else {
			$range_end = new DateTime( 'now', new DateTimeZone( $this->default_timezone ) );
			$range_end->modify( '+20 years' );
		}

		// If start and end are identical and are dates with no times...
		if ( $range_end->format( 'His' ) == 0 && $range_start->getTimestamp() === $range_end->getTimestamp() ) {
			$range_end->modify( '+1 day' );
		}

		$range_start = $range_start->getTimestamp();
		$range_end   = $range_end->getTimestamp();

		foreach ( $events as $an_event ) {
			$event_start = $an_event->dtstart_array[2];
			$event_end   = ( isset( $an_event->dtend_array[2] ) ) ? $an_event->dtend_array[2] : null;

			if (
				( $event_start >= $range_start && $event_start < $range_end )         // Event start date contained in the range
				|| ( $event_end !== null
				     && (
					     ( $event_end > $range_start && $event_end <= $range_end )     // Event end date contained in the range
					     || ( $event_start < $range_start && $event_end > $range_end ) // Event starts before and finishes after range
				     )
				)
			) {
				$extended_events[] = $an_event;
			}
		}

		if ( empty( $extended_events ) ) {
			return array();
		}

		return $extended_events;
	}

	/**
	 * Returns a sorted array of the events following a given string,
	 * or `false` if no events exist in the range.
	 *
	 * @param string $interval
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function events_from_interval( string $interval ): array {
		$range_start = new DateTime( 'now', new DateTimeZone( $this->default_timezone ) );
		$range_end   = new DateTime( 'now', new DateTimeZone( $this->default_timezone ) );

		$date_interval = DateInterval::createFromDateString( $interval );
		$range_end->add( $date_interval );

		return $this->events_from_range( $range_start->format( 'Y-m-d' ), $range_end->format( 'Y-m-d' ) );
	}

	/**
	 * Sorts events based on a given sort order
	 *
	 * @param array $events
	 * @param integer $sort_order Either SORT_ASC, SORT_DESC, SORT_REGULAR, SORT_NUMERIC, SORT_STRING
	 *
	 * @return array
	 */
	public function sort_events_with_order( array $events, $sort_order = SORT_ASC ): array {
		$extended_events = array();
		$timestamp       = array();

		foreach ( $events as $key => $an_event ) {
			$extended_events[] = $an_event;
			$timestamp[ $key ] = $an_event->dtstart_array[2];
		}

		array_multisort( $timestamp, $sort_order, $extended_events );

		return $extended_events;
	}

	/**
	 * Checks if a time zone is valid (IANA, CLDR, or Windows)
	 *
	 * @param string $timezone
	 *
	 * @return boolean
	 */
	protected function is_valid_time_zone_id( string $timezone ): bool {
		return $this->is_valid_iana_time_zone_id( $timezone ) !== false
		       || $this->is_valid_cldr_time_zone_id( $timezone ) !== false
		       || $this->is_valid_windows_time_zone_id( $timezone ) !== false;
	}

	/**
	 * Checks if a time zone is a valid IANA time zone
	 *
	 * @param string $timezone
	 *
	 * @return boolean
	 */
	protected function is_valid_iana_time_zone_id( string $timezone ): bool {
		if ( in_array( $timezone, $this->valid_iana_timezones ) ) {
			return true;
		}

		$valid = array();
		$tza   = timezone_abbreviations_list();

		foreach ( $tza as $zone ) {
			foreach ( $zone as $item ) {
				$valid[ $item['timezone_id'] ] = true;
			}
		}

		unset( $valid[''] );

		if ( isset( $valid[ $timezone ] ) || in_array( $timezone, timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ) ) ) {
			$this->valid_iana_timezones[] = $timezone;

			return true;
		}

		return false;
	}

	/**
	 * Checks if a time zone is a valid CLDR time zone
	 *
	 * @param string $timezone
	 *
	 * @return boolean
	 */
	public function is_valid_cldr_time_zone_id( string $timezone ): bool {
		return array_key_exists( html_entity_decode( $timezone ), self::$cldr_timezones_map );
	}

	/**
	 * Checks if a time zone is a recognised Windows (non-CLDR) time zone
	 *
	 * @param string $timezone
	 *
	 * @return boolean
	 */
	public function is_valid_windows_time_zone_id( string $timezone ): bool {
		return array_key_exists( html_entity_decode( $timezone ), self::$windows_timezones_map );
	}

	/**
	 * Parses a duration and applies it to a date
	 *
	 * @param string $date
	 * @param string $duration
	 * @param string $format
	 *
	 * @return integer|DateTime
	 */
	protected function parse_duration( string $date, string $duration, $format = self::UNIX_FORMAT ) {
		$datetime = date_create( $date );
		$datetime->modify( "{$duration->y} year" );
		$datetime->modify( "{$duration->m} month" );
		$datetime->modify( "{$duration->d} day" );
		$datetime->modify( "{$duration->h} hour" );
		$datetime->modify( "{$duration->i} minute" );
		$datetime->modify( "{$duration->s} second" );

		if ( is_null( $format ) ) {
			$output = $datetime;
		} elseif ( $format === self::UNIX_FORMAT ) {
			$output = $datetime->getTimestamp();
		} else {
			$output = $datetime->format( $format );
		}

		return $output;
	}

	/**
	 * Removes unprintable ASCII and UTF-8 characters
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function remove_unprintable_chars( string $data ): string {
		return preg_replace( '/[\x00-\x1F\x7F\xA0]/u', '', $data );
	}

	/**
	 * Provides a  for PHP 7.2's `mb_chr()`, which is a multibyte safe version of `chr()`.
	 * Multibyte safe.
	 *
	 * @param integer $code
	 *
	 * @return string
	 */
	protected function mb_chr( int $code ): string // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
	{
		if ( function_exists( 'mb_chr' ) ) {
			return mb_chr( $code );
		} else {
			if ( ( $code %= 0x200000 ) < 0x80 ) {
				$s = chr( $code );
			} elseif ( $code < 0x800 ) {
				$s = chr( 0xc0 | $code >> 6 ) . chr( 0x80 | $code & 0x3f );
			} elseif ( $code < 0x10000 ) {
				$s = chr( 0xe0 | $code >> 12 ) . chr( 0x80 | $code >> 6 & 0x3f ) . chr( 0x80 | $code & 0x3f );
			} else {
				$s = chr( 0xf0 | $code >> 18 ) . chr( 0x80 | $code >> 12 & 0x3f ) . chr( 0x80 | $code >> 6 & 0x3f ) . chr( 0x80 | $code & 0x3f );
			}

			return $s;
		}
	}

	/**
	 * Replace all occurrences of the search string with the replacement string.
	 * Multibyte safe.
	 *
	 * @param string|array $search
	 * @param string|array $replace
	 * @param string|array $subject
	 * @param string $encoding
	 * @param integer $count
	 *
	 * @return array|string
	 */
	protected static function mb_str_replace( $search, $replace, $subject, $encoding = null, &$count = 0 ) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
	{
		if ( is_array( $subject ) ) {
			// Call `mb_str_replace()` for each subject in the array, recursively
			foreach ( $subject as $key => $value ) {
				$subject[ $key ] = self::mb_str_replace( $search, $replace, $value, $encoding, $count );
			}
		} else {
			// Normalize $search and $replace so they are both arrays of the same length
			$searches     = is_array( $search ) ? array_values( $search ) : array( $search );
			$replacements = is_array( $replace ) ? array_values( $replace ) : array( $replace );
			$replacements = array_pad( $replacements, count( $searches ), '' );

			foreach ( $searches as $key => $search ) {
				if ( is_null( $encoding ) ) {
					$encoding = mb_detect_encoding( $search, 'UTF-8', true );
				}

				$replace   = $replacements[ $key ];
				$searchLen = mb_strlen( $search, $encoding );

				$sb = array();
				while ( ( $offset = mb_strpos( $subject, $search, 0, $encoding ) ) !== false ) {
					$sb[]    = mb_substr( $subject, 0, $offset, $encoding );
					$subject = mb_substr( $subject, $offset + $searchLen, null, $encoding );
					++ $count;
				}

				$sb[]    = $subject;
				$subject = implode( $replace, $sb );
			}
		}

		return $subject;
	}

	/**
	 * Places double-quotes around texts that have characters not permitted
	 * in parameter-texts, but are permitted in quoted-texts.
	 *
	 * @param string $candidate_text
	 *
	 * @return string
	 */
	protected function escape_param_text( string $candidate_text ): string {
		if ( strpbrk( $candidate_text, ':;,' ) !== false ) {
			return '"' . $candidate_text . '"';
		}

		return $candidate_text;
	}

	/**
	 * Replaces curly quotes and other special characters
	 * with their standard equivalents
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function clean_data( string $data ) {
		$replacement_chars = array(
			"\xe2\x80\x98" => "'",   // ‘
			"\xe2\x80\x99" => "'",   // ’
			"\xe2\x80\x9a" => "'",   // ‚
			"\xe2\x80\x9b" => "'",   // ‛
			"\xe2\x80\x9c" => '"',   // “
			"\xe2\x80\x9d" => '"',   // ”
			"\xe2\x80\x9e" => '"',   // „
			"\xe2\x80\x9f" => '"',   // ‟
			"\xe2\x80\x93" => '-',   // –
			"\xe2\x80\x94" => '--',  // —
			"\xe2\x80\xa6" => '...', // …
			"\xc2\xa0"     => ' ',
		);
		// Replace UTF-8 characters
		$cleaned_data = strtr( $data, $replacement_chars );

		// Replace Windows-1252 equivalents
		$chars_to_replace = array_map( function ( $code ) {
			return $this->mb_chr( $code );
		}, array( 133, 145, 146, 147, 148, 150, 151, 194 ) );
		$cleaned_data     = $this->mb_str_replace( $chars_to_replace, $replacement_chars, $cleaned_data );

		return $cleaned_data;
	}

	/**
	 * Parses a list of excluded dates
	 * to be applied to an Event
	 *
	 * @param array $event
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function parse_exdates( array $event ): array {
		if ( empty( $event['EXDATE_array'] ) ) {
			return array();
		} else {
			$exdates = $event['EXDATE_array'];
		}

		$output           = array();
		$current_timezone = new DateTimeZone( $this->default_timezone );

		foreach ( $exdates as $sub_array ) {
			end( $sub_array );
			$finalKey = key( $sub_array );

			foreach ( array_keys( $sub_array ) as $key ) {
				if ( $key === 'TZID' ) {
					$current_timezone = $this->time_zone_string_to_date_time_zone( $sub_array[ $key ] );
				} elseif ( is_numeric( $key ) ) {
					$ical_date = $sub_array[ $key ];

					if ( substr( $ical_date, - 1 ) === 'Z' ) {
						$current_timezone = new DateTimeZone( self::TIME_ZONE_UTC );
					}

					$output[] = new DateTime( $ical_date, $current_timezone );

					if ( $key === $finalKey ) {
						// Reset to default
						$current_timezone = new DateTimeZone( $this->default_timezone );
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Checks if a date string is a valid date
	 *
	 * @param string $value
	 *
	 * @return boolean
	 * @throws Exception
	 */
	public function is_valid_date( string $value ): bool {
		if ( ! $value ) {
			return false;
		}

		try {
			new DateTime( $value );

			return true;
		} catch ( Exception $exception ) {
			return false;
		}
	}

	/**
	 * Checks if a filename exists as a file or URL
	 *
	 * @param string $filename
	 *
	 * @return boolean
	 */
	protected function is_file_or_url( string $filename ): bool {
		return ( file_exists( $filename ) || filter_var( $filename, FILTER_VALIDATE_URL ) ) ?: false;
	}

	/**
	 * Reads an entire file or URL into an array
	 *
	 * @param string $filename
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function file_or_url( string $filename ): array {
		$options                   = array();
		$options['http']           = array();
		$options['http']['header'] = array();

		if ( ! empty( $this->http_basic_auth ) || ! empty( $this->http_user_agent ) || ! empty( $this->http_accept_language ) ) {
			if ( ! empty( $this->http_basic_auth ) ) {
				$username  = $this->http_basic_auth['username'];
				$password  = $this->http_basic_auth['password'];
				$basicAuth = base64_encode( "{$username}:{$password}" );

				$options['http']['header'][] = "Authorization: Basic {$basicAuth}";
			}

			if ( ! empty( $this->http_user_agent ) ) {
				$options['http']['header'][] = "User-Agent: {$this->http_user_agent}";
			}

			if ( ! empty( $this->http_accept_language ) ) {
				$options['http']['header'][] = "Accept-language: {$this->http_accept_language}";
			}
		}

		$options['http']['protocol_version'] = '1.1';

		$options['http']['header'][] = 'Connection: close';

		// Use WordPress HTTP API with proper error handling
		$remote = wp_remote_get( $filename, array(
			'timeout'     => 45,
			'redirection' => 5,
			'user-agent'  => ! empty( $this->http_user_agent ) ? $this->http_user_agent : 'Listeo iCal Importer',
			'sslverify'   => true,
		) );

		// Check for errors
		if ( is_wp_error( $remote ) ) {
			$error_message = sprintf(
				'Failed to fetch iCal file from %s: %s',
				$filename,
				$remote->get_error_message()
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $error_message );
			}

			throw new Exception( $error_message );
		}

		// Check HTTP response code
		$response_code = wp_remote_retrieve_response_code( $remote );
		if ( $response_code !== 200 ) {
			$error_message = sprintf(
				'Failed to fetch iCal file from %s: HTTP %d - %s',
				$filename,
				$response_code,
				wp_remote_retrieve_response_message( $remote )
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $error_message );
			}

			throw new Exception( $error_message );
		}

		// Get response body
		$body = wp_remote_retrieve_body( $remote );

		if ( empty( $body ) ) {
			$error_message = sprintf( 'Empty response body from %s', $filename );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $error_message );
			}

			throw new Exception( $error_message );
		}

		// Check if response looks like an iCal file
		if ( stripos( $body, 'BEGIN:VCALENDAR' ) === false ) {
			$error_message = sprintf(
				'Invalid iCal format from %s (missing BEGIN:VCALENDAR). Response: %s',
				$filename,
				substr( $body, 0, 200 )
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $error_message );
			}

			throw new Exception( $error_message );
		}

		return preg_split( '/\r\n|\n|\r/', trim( $body ) );
	}

	/**
	 * Returns a `DateTimeZone` object based on a string containing a time zone name.
	 * Falls back to the default time zone if string passed not a recognised time zone.
	 *
	 * @param string $timezone_string
	 *
	 * @return DateTimeZone
	 */
	public function time_zone_string_to_date_time_zone( string $timezone_string ): DateTimeZone {
		// Some time zones contain characters that are not permitted in param-texts,
		// but are within quoted texts. We need to remove the quotes as they're not
		// actually part of the time zone.
		$timezone_string = trim( $timezone_string, '"' );
		$timezone_string = html_entity_decode( $timezone_string );

		if ( $this->is_valid_iana_time_zone_id( $timezone_string ) ) {
			return new DateTimeZone( $timezone_string );
		}

		if ( $this->is_valid_cldr_time_zone_id( $timezone_string ) ) {
			return new DateTimeZone( self::$cldr_timezones_map[ $timezone_string ] );
		}

		if ( $this->is_valid_windows_time_zone_id( $timezone_string ) ) {
			return new DateTimeZone( self::$windows_timezones_map[ $timezone_string ] );
		}

		return new DateTimeZone( $this->default_timezone );
	}
}