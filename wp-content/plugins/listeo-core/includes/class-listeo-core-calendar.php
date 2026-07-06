<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Enhanced Listeo_Core_Calendar class with FullCalendar support
 */
class Listeo_Core_Calendar
{

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26
	 * @static
	 * @return self Main instance.
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private $weekDayName_SF = array("SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT");
	private $weekDayName = array("MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN");
	private $currentDay = 0;
	private $currentMonth = 0;
	private $currentYear = 0;
	private $currentMonthStart = null;
	private $currentMonthDaysLength = null;
	private $use_fullcalendar = true; // Flag to determine whether to use FullCalendar

	function __construct()
	{
		$this->currentYear = date("Y", time());
		$this->currentMonth = date("m", time());

		$this->currentMonthStart = $this->currentYear . '-' . $this->currentMonth . '-01';
		$this->currentMonthDaysLength = date('t', strtotime($this->currentMonthStart));

		add_action('wp_ajax_listeo_core_calendar', array($this, 'getCalendarAJAX'));
		add_action('wp_ajax_nopriv_listeo_core_calendar', array($this, 'getCalendarAJAX'));

		// Allow disabling FullCalendar via filter
		$this->use_fullcalendar = apply_filters('listeo_use_fullcalendar', true);
	}

	function getCalendarAJAX()
	{
		if (!empty($_POST['year'])) {
			$this->currentYear = $_POST['year'];
		}
		if (!empty($_POST['month'])) {
			$this->currentMonth = $_POST['month'];
		}
		$this->currentMonthStart = $this->currentYear . '-' . $this->currentMonth . '-01';
		$this->currentMonthDaysLength = date('t', strtotime($this->currentMonthStart));

		$result['type'] = 'success';

		// Use the appropriate calendar HTML generation method
		if ($this->use_fullcalendar) {
			$result['response'] = $this->getFullCalendarHTML();
		} else {
			$result['response'] = $this->getCalendarHTML();
		}

		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$result = json_encode($result);
			echo $result;
		} else {
			header('Location: ' . $_SERVER['HTTP_REFERER']);
		}
		die();
	}

	/**
	 * Get the FullCalendar HTML - This method returns a placeholder that will be replaced by JS
	 */
	function getFullCalendarHTML()
	{
		return '<div id="fullcalendar"></div>';
	}

	/**
	 * Legacy calendar HTML generation for backward compatibility
	 */
	function getCalendarHTML()
	{
		$calendarHTML = '<div id="listeo-calendar-outer-container">';
		$calendarHTML .= '<table id="listeo-calendar-outer">';
		$calendarHTML .= '<thead><tr><th class="calendar-nav" colspan="7">' . $this->getCalendarNavigation() . '</th></tr>';
		$calendarHTML .= '<tr class="week-name-title">' . $this->getWeekDayName() . '</tr></thead>';
		$calendarHTML .= '<tbody class="week-day-cell">' . $this->getWeekDays() . '</tbody>';
		$calendarHTML .= '</table>';
		$calendarHTML .= '</div>';
		return $calendarHTML;
	}

	function getCalendarNavigation()
	{
		$prevMonthYear = date('m,Y', strtotime($this->currentMonthStart . ' -1 Month'));
		$prevMonthYearArray = explode(",", $prevMonthYear);

		$nextMonthYear = date('m,Y', strtotime($this->currentMonthStart . ' +1 Month'));
		$nextMonthYearArray = explode(",", $nextMonthYear);

		$navigationHTML = '<div class="prev" data-prev-month="' . $prevMonthYearArray[0] . '" data-prev-year = "' . $prevMonthYearArray[1] . '"><i class="sl sl-icon-arrow-left"></i></div>';
		$navigationHTML .= '<span id="currentMonth">' . date_i18n('M ', strtotime($this->currentMonthStart)) . '</span>';
		$navigationHTML .= '<span contenteditable="true" id="currentYear">' . date_i18n('Y', strtotime($this->currentMonthStart)) . '</span>';
		$navigationHTML .= '<div class="next" data-next-month="' . $nextMonthYearArray[0] . '" data-next-year = "' . $nextMonthYearArray[1] . '"><i class="sl sl-icon-arrow-right"></i></div>';
		return $navigationHTML;
	}

	function getWeekDayName()
	{
		$WeekDayName = '';
		$this_WeekDayName = (get_option('start_of_week')) ? $this->weekDayName : $this->weekDayName_SF;
		foreach ($this_WeekDayName as $dayname) {
			switch ($dayname) {
				case 'SUN':
					$translated_day = __('SUN', 'listeo_core');
					break;
				case 'MON':
					$translated_day = __('MON', 'listeo_core');
					break;
				case 'TUE':
					$translated_day = __('TUE', 'listeo_core');
					break;
				case 'WED':
					$translated_day = __('WED', 'listeo_core');
					break;
				case 'THU':
					$translated_day = __('THU', 'listeo_core');
					break;
				case 'FRI':
					$translated_day = __('FRI', 'listeo_core');
					break;
				case 'SAT':
					$translated_day = __('SAT', 'listeo_core');
					break;

				default:
					# code...
					break;
			}
			$WeekDayName .= '<th>' . $translated_day . '</th>';
		}
		return $WeekDayName;
	}

	function getWeekDays()
	{
		$weekLength = $this->getWeekLengthByMonth();
		$firstDayOfTheWeek = date('N', strtotime($this->currentMonthStart));
		$weekDays = "";

		$date = strtotime(date("Y-m-d"));
		$today = date('d', $date);
		$start_of_week = intval(get_option('start_of_week')); // 0 - sunday, 1- monday

		for ($i = 0; $i < $weekLength; $i++) {
			$weekDays .= '<tr>';
			//-			
			if ($start_of_week == 0) {
				for ($j = 0; $j < 7; $j++) {
					$cellIndex = $i * 7 + $j;

					$cellValue = null;

					if ($cellIndex == $firstDayOfTheWeek) {
						$this->currentDay = 1;
					}
					if (!empty($this->currentDay) && $this->currentDay <= $this->currentMonthDaysLength) {
						$cellValue = $this->currentDay;
						$this->currentDay++;
					}

					if ($cellValue) {
						$weekDays .= '<td class="listeo-calendar-day ';
						if ($cellValue == $today) {
							$weekDays .= 'todays_date';
						}
						if ($j == 5 || $j == 6) {
							$weekDays .= ' weekend';
						}
						$weekDays .= '"data-timestamp="' . strtotime("$cellValue.$this->currentMonth.$this->currentYear") . '"';
						$weekDays .= 'data-date="' . $cellValue . '-' . $this->currentMonth . '-' . $this->currentYear . '">';
						//-					$weekDays .= '<span class="calendar-day-date-name">'.$this->weekDayName[$j-1].'</span>';					
						$weekDays .= '<span class="calendar-day-date-name">' . $this->weekDayName[$j] . '</span>';
						$weekDays .= '<span class="calendar-day-date">' . $cellValue . '</span>';
						$weekDays .= '<div class="calendar-price">
										<span>' . esc_html__('Price for day', 'listeo_core') . '</span> 
										<button  type="button">' . esc_html__('Set price', 'listeo_core') . '</button>
									</div>';
						$weekDays .= '</td>';
					} else {
						$weekDays .= '<td class="listeo-empty-calendar-day"></td>';
					}
				}
			} else {
				for ($j = 1; $j <= 7; $j++) {
					$cellIndex = $i * 7 + $j;

					$cellValue = null;

					if ($cellIndex == $firstDayOfTheWeek) {
						$this->currentDay = 1;
					}
					if (!empty($this->currentDay) && $this->currentDay <= $this->currentMonthDaysLength) {
						$cellValue = $this->currentDay;
						$this->currentDay++;
					}

					if ($cellValue) {
						$weekDays .= '<td class="listeo-calendar-day ';
						if ($cellValue == $today) {
							$weekDays .= 'todays_date';
						}
						if ($j == 6 || $j == 7) {
							$weekDays .= ' weekend';
						}
						$weekDays .= '"data-timestamp="' . strtotime("$cellValue.$this->currentMonth.$this->currentYear") . '"';
						$weekDays .= 'data-date="' . $cellValue . '-' . $this->currentMonth . '-' . $this->currentYear . '">';
						$weekDays .= '<span class="calendar-day-date-name">' . $this->weekDayName[$j - 1] . '</span>';

						$weekDays .= '<span class="calendar-day-date">' . $cellValue . '</span>';
						$weekDays .= '<div class="calendar-price">
										<span>' . esc_html__('Price for day', 'listeo_core') . '</span> 
										<button  type="button">' . esc_html__('Set price', 'listeo_core') . '</button>
									</div>';
						$weekDays .= '</td>';
					} else {
						$weekDays .= '<td class="listeo-empty-calendar-day"></td>';
					}
				}
			}
			$weekDays .= '</tr>';
		}
		return $weekDays;
	}

	function getWeekLengthByMonth()
	{
		$weekLength = intval($this->currentMonthDaysLength / 7);
		if ($this->currentMonthDaysLength % 7 > 0) {
			$weekLength++;
		}
		$monthStartDay = date('N', strtotime($this->currentMonthStart));
		$monthEndingDay = date('N', strtotime($this->currentYear . '-' . $this->currentMonth . '-' . $this->currentMonthDaysLength));
		if ($monthEndingDay < $monthStartDay) {
			$weekLength++;
		}

		return $weekLength;
	}
}
