<?php

class generate008
{
	# Constructor
	public function __construct ($muscatConversion, $xml)
	{
		# Create a class property handle to the parent class
		$this->muscatConversion = $muscatConversion;
		
		# Create a handle to the XML
		$this->xml = $xml;
		
	}
	
	
	# Main
	public function main ()
	{
		# Start the value
		$value = '';
		
		# Delegate the creation of the value for each set of positions
		$value .= $this->position_00_05 ();
		$value .= $this->position_06    ();
		$value .= $this->position_07_10 ();
		$value .= $this->position_11_14 ();
		$value .= $this->position_15_17 ();
		$value .= $this->position_18_34 ();
		$value .= $this->position_35_37 ();
		$value .= $this->position_38    ();
		$value .= $this->position_39    ();
		
		# Return the value
		return $value;
	}
	
	
	# 008 pos. 00-05: Date entered on file
	private function position_00_05 ()
	{
		# Date entered on system [format: yymmdd]
		return date ('ymd');
	}
	
	
	# 008 pos. 06: Type of date/Publication status
	private function position_06 ()
	{
#!# Todo
		return '/' . str_repeat ('-', 1 - 1);
	}
	
	
	# 008 pos. 07-10: Date 1
	private function position_07_10 ()
	{
#!# Todo
		return '/' . str_repeat ('-', 4 - 1);
	}
	
	
	# 008 pos. 11-14: Date 2
	private function position_11_14 ()
	{
#!# Todo
		return '/' . str_repeat ('-', 4 - 1);
	}
	
	
	# 008 pos. 15-17: Place of publication, production, or execution
	private function position_15_17 ()
	{
#!# Todo
		return '/' . str_repeat ('-', 3 - 1);
	}
	
	
	# 008 pos. 18-34: Material specific coded elements
	private function position_18_34 ()
	{
		# Determine the record type or end
		if (!$this->recordType = $this->recordType ()) {
			#!# Need to flag error
			return '/' . str_repeat ('?', 17 - 1);
		}
		
		# Determine the *form value
		$this->form = $this->muscatConversion->xPathValue ($this->xml, $this->recordType . '/form');
		
		# Determine if the record form is roughly digital/multimedia
		$this->isMultimediaish = $this->isMultimediaish ($this->form);
		
		# Compile the value by delegating each part
		$value  = $this->position_18_34__18_20 ();
		$value .= $this->position_18_34__21    ();
		$value .= $this->position_18_34__22    ();
		$value .= $this->position_18_34__23    ();
		$value .= $this->position_18_34__24_27 ();
		$value .= $this->position_18_34__28    ();
		$value .= $this->position_18_34__29    ();
		$value .= $this->position_18_34__30_31 ();
		$value .= $this->position_18_34__32    ();
		$value .= $this->position_18_34__33    ();
		$value .= $this->position_18_34__34    ();
		
		# Return the string
		return $value;
	}
	
	
	# Helper function to determine the record type
	private function recordType ()
	{
		# Determine the record type, used by subroutines
		$recordTypes = array (
			'/art/in',
			'/art/j',
			'/doc',
			'/ser',
		);
		foreach ($recordTypes as $recordType) {
			if ($this->muscatConversion->xPathValue ($this->xml, $recordType)) {
				return $recordType;	// Match found
			}
		}
		
		# Not found
		return NULL;
	}
	
	
	# Helper function to determine if the record form is roughly digital/multimedia
	private function isMultimediaish ($form)
	{
		# Define forms which come under this grouping
		$forms = array (
			'3.5 floppy disk',
			'CD-ROM',
			'DVD-ROM',
			'Map',
			'CD',
			'Sound cassette',
			'Sound disc',
			'DVD',
			'Videorecording',
			'Poster',
		);
		
		# Return whether the supplied form is one of the supported types
		return (in_array ($form, $forms));
	}
	
	
	
	# 008 pos. 18-34: Material specific coded elements: 18-20
	private function position_18_34__18_20 ()
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
					return str_repeat ('#', 3);
				case 'Map':
				case 'CD':
				case 'Sound cassette':
				case 'Sound disc':
					return str_repeat ('|', 3);
				case 'Poster':
					return str_repeat ('n', 3);
				case 'DVD':
				case 'Videorecording':
					
					$p = $this->muscatConversion->xPathValue ($this->xml, $this->recordType . '//p');
					if (!substr_count ($p, ' min')) {
						return str_repeat ('|', 3);
					}
					if (!preg_match ('/([0-9]+) min/', $p, $matches)) {return NULL;}
					$minutes = $matches[1];
					if ($minutes > 999) {
						return '000';
					}
					return str_pad ($minutes, 3, '0', STR_PAD_LEFT);
			}
		}
		
		switch ($this->recordType) {
			case '/doc':
			case '/art/in':
				
				# Add codes to stack of maximum three characters based on either *p or *pt, padding missing characters to the right with #
				$strings = array (
					'ill|diag'	=> 'a',	# If *p or *pt contains 'ill*' OR 'diag*' => a in pos. 18
					'map'		=> 'b',	# If *p or *pt contains 'map*' => b in pos. 18 unless full, in which case => b in pos. 19
					'plate'		=> 'f',	# If *p or *pt contains 'plate*' => f in pos. 18 unless full, in which case => f in pos. 19 unless full, in which case => f in pos. 20
				);
				$stack = '';
				$p = $this->muscatConversion->xPathValue ($this->xml, $this->recordType . '//p');
				$pt = $this->muscatConversion->xPathValue ($this->xml, $this->recordType . '//pt');
				foreach ($strings as $searchList => $result) {
					if (preg_match ('/\b(' . $searchList . ')/', $p) || preg_match ('/\b(' . $searchList . ')/', $pt)) {
						$stack .= $result;
					}
				}
				return str_pad ($stack, 3, '#', STR_PAD_RIGHT);	// e.g. 'abf', 'ab#', 'a##', '###'
				
			case '/ser':
			case '/art/j':
				
				$freq = $this->muscatConversion->xPathValue ($this->xml, $this->recordType . '//freq');
				$value  = $this->lookupValue ('journalFrequencyTable', $freq, 'Frequency', 'No *freq');
				$value .= $this->lookupValue ('journalFrequencyTable', $freq, 'Regularity', 'No *freq');
				$value .= '#';
				
				return $value;
		}
		
		# Flag error
		return NULL;
	}
	
	
	# Function to determine the Journal frequency and regularity
	private function journalFrequencyTable ()
	{
		# Define the lookup table
		return $lookupTable = '
			*freq	Frequency	Regularity
			No *freq	#	u
			-	#	u
			10-12 issues p.a.	m	n
			12 issues per vol. until 1959, irregular thereafter, with Neue Folge issued as sequential monographic series	#	u
			12 p.a.	m	r
			2 issues P.A.	f	r
			3 issues p.a.	t	r
			3 issues per year	t	r
			3 per year	t	r
			3 times yearly	t	r
			4 issues p.a.	q	r
			4 times per year	q	r
			4-6 issues p.a.	z	n
			5 issues p.a.	q	x
			54 issues per year	w	x
			6 issues p.a.	b	r
			8 issues p.a.	b	x
			9 issues p.a.	m	x
			annual	a	r
			annual (2008-)	a	r
			annual from 2009	a	r
			annual?	a	r
			Annually	a	r
			bi-annual	f	r
			bi-annually	f	r
			bi-monthly	b	r
			bi-weekly	e	r
			biannual	f	r
			Biannually	f	r
			biennial	g	r
			biennual	g	r
			bimonthly	b	r
			biweekly	e	r
			daily	d	r
			Eight to ten issues per year, mostly published two at a time. From 1985, five issues per year	q	x
			Five issues in 1996 (73e Anne^ae). Quarterly from 1997 (74e Anne^ae)	q	n
			five issues p.a.	q	x
			Five issues per year	q	x
			fornightly	e	r
			fortnightly	e	r
			four times per year	q	r
			Initially annual, later quarterly	q	r
			iregular	#	x
			irregular	#	x
			monthly	m	r
			Monthly (except Jan., Apr., Jul., and Oct.)	b	n
			Monthly, later weekly	w	r
			normally 3 issues p.a.	t	r
			occasional	#	x
			Pilot issue	#	x
			Quarterly	q	r
			quarterly (1970-2007).	q	r
			[Quarterly]	q	r
			quaterly	q	r
			regular	z	r
			semi-annual	f	r
			semiannual	f	r
			Six times per year	b	r
			Three times a year	t	r
			three times per year	t	r
			Tri-annual	t	r
			Triannual	#	n
			trienially	h	r
			triennially	h	r
			Twice a year	f	r
			twice yearly	f	r
			Unknown	#	u
			varies	#	x
			weekly	w	r
		';
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 21
	private function position_18_34__21 ()
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
				case 'DVD':
				case 'Videorecording':
				case 'Poster':
					return '#';
				default:
					return '|';
			}
		}
		
		switch ($this->recordType) {
			case '/doc':
			case '/art/in':
				return '#';
			case '/ser':
			case '/art/j':
				return '|';
		}
		
		# Flag error
		return NULL;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 22
	private function position_18_34__22 ()
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case 'DVD':
				case 'Videorecording':
				case 'Poster':
					return '#';
				default:
					return '|';
			}
		}
		
		switch ($this->recordType) {
			case '/doc':
			case '/art/in':
				return '|';
			case '/ser':
			case '/art/j':
				
				if (!$this->form) {return '#';}
				
				switch ($this->form) {
					case 'Internet resource':
						return 'o';
					case 'Microfiche':
						return 'b';
					case 'Microfilm':
						return 'a';
					case 'Online publication':
						return 'o';
					case 'PDF':
						return 's';
				}
		}
		
		# Flag error
		return NULL;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 23
	private function position_18_34__23 ()
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
					return 'q';
				case 'Map':
					return '|';
				case 'CD':
				case 'Sound cassette':
				case 'Sound disc':
					return 'q';
				case 'DVD':
				case 'Videorecording':
				case 'Poster':
					return '#';
			}
		}
		
		if (!$this->form) {return '#';}
		
		switch ($this->form) {
			case 'Internet resource':
				return 'o';
			case 'Microfiche':
				return 'b';
			case 'Microfilm':
				return 'a';
			case 'Online publication':
				return 'o';
			case 'PDF':
				return 's';
		}
		
		# Flag error
		return NULL;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 24-27
	private function position_18_34__24_27 ()
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 28
	private function position_18_34__28 ()
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 29
	private function position_18_34__29 ()
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 30-31
	private function position_18_34__30_31 ()
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 32
	private function position_18_34__32 ()
	{
		return '#';
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 33
	private function position_18_34__33 ()
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 34
	private function position_18_34__34 ()
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	# 008 pos. 35-37: Language
	private function position_35_37 ()
	{
#!# Todo
		return '/' . str_repeat ('-', 3 - 1);
	}
	
	
	# 008 pos. 38: Modified record
	private function position_38 ()
	{
		return '#';
	}
	
	
	# 008 pos. 39: Cataloguing source
	private function position_39 ()
	{
		return 'd';
	}
	
	
	# Generalised lookup table function
	private function lookupValue ($tableFunction, $value, $field, $ifEmptyUseValueFor = false)
	{
		# If the supplied value is empty, and a fallback is defined, treat the value as the fallback, which will then be looked up
		if ($ifEmptyUseValueFor) {
			if (!$value) {$value = $ifEmptyUseValueFor;}
		}
		
		# Get the data table
		$lookupTable = $this->{$tableFunction} ();
		
		# Convert to TSV
		$lookupTable = implode ("\n", array_map ('trim', explode ("\n", trim ($lookupTable))));
		require_once ('csv.php');
		$lookupTable = csv::tsvToArray ($lookupTable, $firstColumnIsId = true);
		
		# Sanity-check
		foreach ($lookupTable as $entry => $values) {
			if (strlen ($values[$field]) != 1) {
				echo "<p class=\"warning\">In the {$tableFunction} definition, <em>{$entry}</em> for field <em>{$field}</em> has invalid syntax.</p>";
				return NULL;
			}
		}
		
		# Ensure the string is present
		if (!isSet ($lookupTable[$value])) {
			echo "<p class=\"warning\">In {$tableFunction}, {$value} is not present in the table.</p>";
			return NULL;
		}
		
		# Compile the result
		$result = $lookupTable[$value][$field];
		
		# Return the result
		return $result;
	}
}

?>