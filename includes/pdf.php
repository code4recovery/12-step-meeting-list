<?php
//special implementation of tcpdf to generate printed meeting schedule
//used by includes/ajax.php tsml_ajax_pdf()

//config before including TCPDF
define('K_TCPDF_EXTERNAL_CONFIG', true);
define('PDF_FONT_NAME_MAIN', 'dejavusans');
define('K_TCPDF_THROW_EXCEPTION_ERROR', true);

//get library
include('tcpdf/tcpdf.php');

//debugging
ini_set('display_errors', WP_DEBUG);

//define class
if (!class_exists('TSMLPDF')) {
	class TSMLPDF extends TCPDF {

		protected $options = array(); //configuration options

		protected $content_width; //width - margins

		protected $current_region;

		protected $last_region;

		public function __construct($options=array()) {

			//mix options with defaults
			$this->options = array_merge(array(
				'author' => get_bloginfo('name'),
				'continued' => ' (contd.)',
				'creator' => get_home_url(),
				'height' => 11,
				'keywords' => 'Meetings',
				'margin' => 1,
				'orientation' => 'p',
				'subject' => 'Meeting Schedule',
				'title' => 'Meeting Schedule',
				'types' => array(
					'X' => '♿︎',
				),
				'types_separator' => ' ',
				'units' => 'in',
				'width' => 8.5,
			), $options);

			$this->content_width = $this->options['width'] - ($this->options['margin'] * 2);

			//call TCPDF
			parent::__construct($this->options['orientation'], $this->options['units'], array($this->options['width'], $this->options['height']));

			//set up PDF
			$this->SetTitle($this->options['title']);
			$this->SetSubject($this->options['subject']);
			$this->SetCreator($this->options['creator']);
			$this->SetAuthor($this->options['author']);
			$this->SetKeywords($this->options['keywords']);
			$this->SetMargins($this->options['margin'], $this->options['margin'] * 1.75);
			$this->SetAutoPageBreak(true, $this->options['margin'] * 1.5);
			$this->SetFontSubsetting(true);
			$this->SetFillColor(255, 182, 193); //pink for debugging

			//get data
			$meetings = tsml_get_meetings();
			usort($meetings, array($this, 'SortMeetings'));

			//get current region
			$this->current_region = $meetings[0]['region'];

			//get output started
			$this->SetCellPadding(0);
			$this->AddPage();

			//column widths
			$day_width = .4;
			$time_width = .6;
			$right_width = $this->content_width - $day_width - $time_width;

			//runtime variables we'll need
			$days = array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT');
			$last_region = $last_day = '';

			foreach ($meetings as $meeting) {

				//format meeting name and group
				$meeting_name = strtoupper($meeting['name']);
				if (!empty($meeting['group'])) $meeting_name .= ' ' . $meeting['group'];

				//format types
				$types = array_map(array($this, 'FormatTypes'), $meeting['types']);
				$types = implode($this->options['types_separator'], $types);

				//format region
				$region = $meeting['region'];
				if (!empty($meeting['sub_region'])) $region .= ': ' . $meeting['sub_region'];

				//format location
				$location = $meeting['location'];
				if (!empty($meeting['location_notes'])) $meeting['location'] .= ' (' . $meeting['location_notes'] . ')';

				//header for the region
				if ($region != $this->current_region) {
					$this->Header($region);
				}

				//line one
				$this->SetFontNormal();
				if ($meeting['day'] != $last_day) {
					$this->Cell($day_width, .1, $days[$meeting['day']]);
					$last_day = $meeting['day'];
				} else {
					$this->Cell($day_width, .1, '', 0, 0);
				}
				$this->SetFont(PDF_FONT_NAME_MAIN, 'B', 7);
				$this->Cell($time_width, .1, $meeting['time_formatted']);
				$this->MultiCell($right_width, .1, $meeting_name, 0, 'L');
				$this->SetFontNormal();

				//line two
				$this->Cell($day_width, .1, '', 0, 0);
				$this->Cell($time_width, .1, $types);
				$this->MultiCell($right_width, .1, $location . ' ' . tsml_format_address($meeting['formatted_address'], true), 0, 'L');
				$this->Ln(.1);

			}

			return $this;
		}

		public function Header($region=-1) {
			if ($region == -1) {
				//called from addpage
				$this->SetXY($this->options['margin'], $this->options['margin']);
			} else {
				$this->current_region = $region;				
			}

			$contd = ($this->last_region == $this->current_region) ? $this->options['continued'] : '';

			$this->SetFontHeading();
			$this->Cell($this->content_width, .1, $this->current_region . $contd, 'B');
			$this->Ln(.2);
			$this->SetFontNormal();

			$this->last_region = $this->current_region;
		}

		public function Footer() {
			$this->SetXY($this->options['margin'], 0 - $this->options['margin']);
			$this->SetFontNormal();
			$this->Cell($this->content_width, 0, $this->PageNo(), 0, 0, 'C');
		}

		public function SetFontHeading() {
			$this->SetFont(PDF_FONT_NAME_MAIN, 'B', 8);
		}

		public function SetFontNormal() {
			$this->SetFont(PDF_FONT_NAME_MAIN, '', 7);
		}

		//sort by region, then sub-region, then day, then time, then meeting name, then location
		function SortMeetings($a, $b) {
			if ($a['region'] != $b['region']) return strcmp($a['region'], $b['region']);
			if ($a['sub_region'] != $b['sub_region']) return strcmp($a['sub_region'], $b['sub_region']);
			if ($a['day'] != $b['day']) return strcmp($a['day'], $b['day']);
			if ($a['time'] != $b['time']) return strcmp($a['time'], $b['time']);
			if ($a['name'] != $b['name']) return strcmp($a['name'], $b['name']);
			return strcmp($a['location'], $b['location']);
		}

		//replace types with local ones
		public function FormatTypes($element) {
			if (!array_key_exists($element, $this->options['types'])) return $element;
			return $this->options['types'][$element];
		}
	}
}