<?php

//create a basic pdf
if (!function_exists('tsml_pdf')) {
	function tsml_pdf() {
		
		//config
		define('K_TCPDF_EXTERNAL_CONFIG', true);
		define('PDF_FONT_NAME_MAIN', 'dejavusans');
		define('K_TCPDF_THROW_EXCEPTION_ERROR', true);

		//get library
		include('tcpdf/tcpdf.php');

		//page dimensions
		$margin_side = .25;
		$margin_top = .25;
		$page_dimensions = array(4.25, 11);
		$columns = 1;
		$column_width = ($page_dimensions[0] / $columns) - ($margin_side * 2);

		//row dimensions
		$day_width = .4;
		$time_width = .6;
		$right_width = $column_width - $day_width - $time_width;

		//runtime variables we'll need
		$days = array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT');
		$last_region = $last_day = '';

		//create new PDF document
		$pdf = new TCPDF('p', 'in', $page_dimensions, true, 'UTF-8', false);

		//set up document
		$pdf->SetCreator(get_home_url());
		$pdf->SetTitle('Meeting Schedule');
		$pdf->SetSubject('Meeting Schedule');
		$pdf->SetAuthor(get_bloginfo('name'));
		$pdf->SetKeywords('Meetings'); //?
		$pdf->SetPrintHeader(false);
		$pdf->SetPrintFooter(false);
		$pdf->SetMargins($margin_side, $margin_top);
		$pdf->SetCellPadding(0);
		$pdf->SetFont(PDF_FONT_NAME_MAIN, '', 7);
		$pdf->SetAutoPageBreak(true, $margin_top);
		$pdf->SetFontSubsetting(true);
		$pdf->SetFillColor(250, 200, 200); //pink for debugging
		$pdf->AddPage();

		//get meetings from db and sort them
		$meetings = tsml_get_meetings();
		usort($meetings, 'tsml_pdf_sort');

		foreach ($meetings as $meeting) {

			//format meeting name and group
			$meeting_name = strtoupper($meeting['name']);
			if (!empty($meeting['group'])) $meeting_name .= ' ' . $meeting['group'];

			//format types
			$types = array_map('tsml_pdf_format_types', $meeting['types']);
			$types = implode(', ', $types);

			//format location
			$location = $meeting['location'];
			if (!empty($meeting['location_notes'])) $meeting['location'] .= ' (' . $meeting['location_notes'] . ')';

			//header for the region
			if ($meeting['region'] != $last_region) {
				$pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 8);
				$pdf->Cell($column_width, .1, $meeting['region'], 'B', 1);
				$pdf->Ln(.05);
				$pdf->SetFont(PDF_FONT_NAME_MAIN, '', 7);
				$last_region = $meeting['region'];
			}

			//line one
			if ($meeting['day'] != $last_day) {
				$pdf->Cell($day_width, .1, $days[$meeting['day']]);
				$last_day = $meeting['day'];
			} else {
				$pdf->Cell($day_width, .1, '', 0, 0);
			}
			$pdf->Cell($time_width, .1, $meeting['time_formatted']);
			$pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 7);
			$pdf->MultiCell($right_width, .1, $meeting_name, 0, 'L');
			$pdf->SetFont(PDF_FONT_NAME_MAIN, '', 7);
			//$pdf->Ln();

			//line two
			$pdf->Cell($day_width, .1, '', 0, 0);
			$pdf->Cell($time_width, .1, $types);
			$lines = $pdf->MultiCell($right_width, .1, $location . ' ' . tsml_format_address($meeting['formatted_address'], true), 0, 'L');
			$pdf->Ln(.1);

		}

		//send to browser
		$pdf->Output('meeting-schedule.pdf', 'I');

	}
}

//sort by region, then sub-region, then day, then time, then meeting name, then location
if (!function_exists('tsml_pdf_sort')) {
	function tsml_pdf_sort($a, $b) {
		if ($a['region'] != $b['region']) return strcmp($a['region'], $b['region']);
		if ($a['sub_region'] != $b['sub_region']) return strcmp($a['sub_region'], $b['sub_region']);
		if ($a['day'] != $b['day']) return strcmp($a['day'], $b['day']);
		if ($a['time'] != $b['time']) return strcmp($a['time'], $b['time']);
		if ($a['name'] != $b['name']) return strcmp($a['name'], $b['name']);
		return strcmp($a['location'], $b['location']);
	}
}

if (!function_exists('tsml_pdf_format_types')) {
	function tsml_pdf_format_types($element) {
		if ($element == 'X') return '♿︎';
		return $element;
	}
}