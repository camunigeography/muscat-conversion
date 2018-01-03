<?php

# Class to generate the complex 008 field; see: http://www.loc.gov/marc/bibliographic/bd008.html
class generate008
{
	# Constructor
	public function __construct ($marcConversion)
	{
		# Create a class property handle to the parent class
		$this->marcConversion = $marcConversion;
		
	}
	
	
	# Main
	public function main ($xml, &$errorString = false)
	{
		# Create a handle to the XML
		$this->xml = $xml;
		
		# Determine the record type or end
		if (!$this->recordType = $this->recordType ()) {
			$errorString .= '008 field: Could not determine record type';
			return false;
		}
		
		# Determine the *form value
		$this->form = $this->marcConversion->xPathValue ($this->xml, '(//form)[1]', false);
		
		# Determine if the record form is roughly digital/multimedia
		$this->isMultimediaish = $this->isMultimediaish ($this->form);
		
		# Define the positions and their expected lengths
		$positions = array (
			'00-05'	=> 6,
			'06-14'	=> 9,
			'15-17'	=> 3,
			'18-34'	=> 17,
			'35-37'	=> 3,
			'38'	=> 1,
			'39'	=> 1,
		);
		
		# Create each position
		$value = '';
		foreach ($positions as $positions => $expectedLength) {
			
			# Get and append the value
			$function = 'position_' . str_replace ('-', '_', $positions);
			$string = $this->{$function} ($errorString);
			$value .= $string;
			
			# Sanity-check that the string length is 40; e.g. /records/1210/ (test #396)
			$length = mb_strlen ($string);
			if ($length != $expectedLength) {
				$errorString .= "008 field " . (substr_count ($positions, '-') ? 'positions' : 'position') . " {$positions}: Length is {$length} but should be {$expectedLength}";
				return false;
			}
		}
		
		# Return the value
		return $value;
	}
	
	
	# 008 pos. 00-05: Date entered on file; e.g. /records/1210/ (test #397)
	private function position_00_05 ()
	{
		# Date entered on system [format: yymmdd]
		return date ('ymd');
	}
	
	
	# 008 pos. 06: Type of date/Publication status, and 07-10: Date 1, and pos. 11-14: Date 2
	private function position_06_14 ()
	{
		# Obtain the year string
		$yearField = ($this->recordType == '/ser' ? 'r' : 'd');
		$yearString = $this->marcConversion->xPathValue ($this->xml, $this->recordType . "//{$yearField}");
		
		# Determine if the record has a year
		# Note that decade-wide dates like "199-" are considered a valid year
		$hasYear = preg_match ('/([0-9]{3}[-0-9])/', $yearString, $yearMatches);
		
		# 06:    if record is *ser, designator is 'u'.
		# 07-10: If 06 is 'u', 07-10 contain first year in *r (if *r contains no year, 07-10 contain 'uuuu');
		# 11-14: if 06 is 'u', 11-14 contain second year in *r (if *r contains no year or no second year in a "1990-" -style range, 11-14 contain 'uuuu');
		if ($this->recordType == '/ser') {
			
			# For continuing serials, e.g. ends with "1990-", there is no end date
			# E.g. /records/1036/ (test #9)
			if ($hasYear && preg_match ('/-$/', $yearString)) {		// $hasYear check to catch /records/177897/ which has "undated-" (test #10); will not cause problems for e.g. /records/145353/ which has "1997/98-" (test #11), /records/57312/ which has "1977?-" (test #12), or /records/19832/ which has "1945-73, 89-" (test #13) - they will get the first four-digit year as intended
				return 'u' . $yearMatches[1] . 'uuuu';
			}
			
			# Normalise cases of incorrect data specified as YYYY-YY, e.g. "1990-95" which should be "1990-1995"; all manually checked that these are all 19xx dates (not 18xx/20xx/etc.)
			# E.g. /records/1034/ (test #14)
			$yearString = preg_replace ('/([0-9]{4})-([0-9]{2})($|[^0-9])/', '\1-19\2\3', $yearString);
			
			# Determine the last present year in *r (which could validly be the same as the first year if the string is just "1990" - this would mean a serial that starts in 1990 and ends in 1990)
			# E.g. /records/1052/ (test #15)
			preg_match_all ('/([0-9]{4})/', $yearString, $lastYearMatches, PREG_PATTERN_ORDER);		// See: http://stackoverflow.com/questions/23343087/
			$lastYear = end ($lastYearMatches[0]);
			
			# Return the u
			if ($hasYear) {
				return 'd' . $yearMatches[1] . $lastYear;	// E.g. /records/1052/ (test #15)
			} else {
				return 'u' . 'uuuu' . 'uuuu';	// E.g. /records/1072/ (test #398)
			}
		}
		
		# 06:    If *d in *doc or *art does not contain at least one year (e.g. '[n.d.]', '-', '?'), designator is 'n';
		# 07-10: If 06 is 'n', 07-10 contain 'uuuu';
		# 11-14: If 06 is 'n' or 's', 11-14 contain 'uuuu';
		# E.g. /records/1102/ (test #16)
		if (!$hasYear) {
			return 'n' . 'uuuu' . 'uuuu';
		}
		
		# 06:    For *doc and *art, if *d is of the format '1984 (2014 printing)', designator is 'r';
		# 07-10: if 06 is 'r', 07-10 contain the later of the two years, i.e. '2014' if *d is '1984 (2014 printing)';
		# 11-14: if 06 is 'r', 11-14 contain the earlier of the two years, i.e. '1984' if *d is '1984 (2014 printing)'
		# The printing year never has a - in practice, so for this reason we do not to worry about - => u replacement
		# E.g. /records/12522/ (test #19)
		if (preg_match ('/^([0-9]{3}[-0-9]) \(([0-9]{4}) printing\)$/', $yearString, $printingMatches)) {
			return 'r' . $printingMatches[2] . $printingMatches[1];
		}
		
		# 06:    otherwise designator is 's'
		# 07-10: if 06 is 's', 07-10 contain the year from *d (i.e. no other characters e.g. '[', ']' or '?') - any digits replaced by hyphens in Muscat should be replaced by 'u' in Voyager
		# 11-14: If 06 is 'n' or 's', 11-14 contain '####';
		# E.g. /records/1306/ (test #17), /records/11150/ (test #18)
		$yearMatches[1] = str_replace ('-', 'u', $yearMatches[1]);
		return 's' . $yearMatches[1] . '####';
	}
	
	
	# 008 pos. 15-17: Place of publication, production, or execution; e.g. /records/169741/ (test #20)
	private function position_15_17 (&$errorString)
	{
		# Extract the value
		$pl = $this->marcConversion->xPathValue ($this->xml, '(//pl)[1]', false);
		
		# Look it up in the country codes table; brackets are stripped, e.g. /records/2027/ (test #482)
		return $this->marcConversion->lookupValue ('countryCodes', '', true, $stripBrackets = true, $pl, 'MARC Country Code', $errorString);
	}
	
	
	# 008 pos. 18-34: Material specific coded elements
	private function position_18_34 (&$errorString)
	{
		# Compile the value by delegating each part
		$value  = $this->position_18_34__18_20 ($errorString);
		$value .= $this->position_18_34__21    ();
		$value .= $this->position_18_34__22    ();
		$value .= $this->position_18_34__23    ();
		$value .= $this->position_18_34__24_27 ();
		$value .= $this->position_18_34__28    ();
		$value .= $this->position_18_34__29    ();
		$value .= $this->position_18_34__30_31 ();
		$value .= $this->position_18_34__32    ();
		$value .= $this->position_18_34__33    ($errorString);
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
			'/art/j',	// E.g. /records/1210/ (test #395)
			'/doc',
			'/ser',
		);
		foreach ($recordTypes as $recordType) {
			if ($this->marcConversion->xPathValue ($this->xml, $recordType)) {
				return $recordType;	// Match found
			}
		}
		
		# Not found
		return NULL;
	}
	
	
	# Helper function to determine if the record form is roughly digital/multimedia; e.g. /records/2023/ (test #21)
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
	private function position_18_34__18_20 (&$errorString)
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
					return str_repeat ('#', 3);		// E.g. /records/2023/ (test #21)
				case 'Map':
				case 'CD':
				case 'Sound cassette':
				case 'Sound disc':
					return str_repeat ('|', 3);		// E.g. /records/102409/ (test #22)
				case 'Poster':
					return str_repeat ('n', 3);		// E.g. /records/95733/ (test #23)
				case 'DVD':
				case 'Videorecording':
					
					$p = $this->marcConversion->xPathValue ($this->xml, $this->recordType . '//p');
					if ($p == '2 hrs') {	// E.g. /records/96479/ (test #400)
						$p = '120 min';
					}
					if (!substr_count ($p, ' min')) {
						return str_repeat ('|', 3);	// E.g. /records/163911/ (test #399)
					}
					if ($p == '[? mins]') {
						return str_repeat ('|', 3);	// E.g. /records/78699/ (test #25)
					}
					if (!preg_match ('/([0-9]+) min/', $p, $matches)) {return NULL;}
					$minutes = $matches[1];
					if ($minutes > 999) {	// No cases in data, so no test
						return '000';
					}
					return str_pad ($minutes, 3, '0', STR_PAD_LEFT);	// E.g. /records/1968/ (test #24)
			}
		}
		
		switch ($this->recordType) {
			case '/doc':
			case '/art/in':
				
				# Add codes to stack of maximum three characters based on either *p or *pt, padding missing characters to the right with #
				$strings = array (
					'ill|diag'	=> 'a',	# If *p or *pt contains 'ill*' OR 'diag*' => a in pos. 18; e.g. /records/1115/ (test #26)
					'map'		=> 'b',	# If *p or *pt contains 'map*' => b in pos. 18 unless full, in which case => b in pos. 19; e.g. /records/1144/ (test #27)
					'plate'		=> 'f',	# If *p or *pt contains 'plate*' => f in pos. 18 unless full, in which case => f in pos. 19 unless full, in which case => f in pos. 20
				);
				$stack = '';
				$p = $this->marcConversion->xPathValue ($this->xml, $this->recordType . '//p');
				$pt = $this->marcConversion->xPathValue ($this->xml, $this->recordType . '//pt');
				foreach ($strings as $searchList => $result) {
					if (preg_match ('/\b(' . $searchList . ')/', $p) || preg_match ('/\b(' . $searchList . ')/', $pt)) {
						$stack .= $result;
					}
				}
				return str_pad ($stack, 3, '#', STR_PAD_RIGHT);	// e.g. 'abf', 'ab#', 'a##', '###'; e.g. /records/1144/ (test #27)
				
			case '/ser':
			case '/art/j':
				
				$freq = $this->marcConversion->xPathValue ($this->xml, $this->recordType . '//freq');
				$value  = $this->marcConversion->lookupValue ('journalFrequencies', 'No *freq', false, false, $freq, 'Frequency' , $errorString);
				$value .= $this->marcConversion->lookupValue ('journalFrequencies', 'No *freq', false, false, $freq, 'Regularity', $errorString);
				$value .= '#';
				
				return $value;	// E.g. /records/1027/ (test #28)
		}
		
		# Flag error
		return NULL;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 21; e.g. /records/101161/ (test #29)
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
	
	
	# 008 pos. 18-34: Material specific coded elements: 22; e.g. /records/1186/ (test #30), /records/11291/ (test #31)
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
	
	
	# 008 pos. 18-34: Material specific coded elements: 23; e.g. /records/201908/ (test #32)
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
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
					return '##|#';
				case 'Map':
					return '#|##';	// E.g. /records/101161/ (test #33)
				case 'CD':
				case 'Sound cassette':
				case 'Sound disc':
					return '||||';
				case 'DVD':
				case 'Videorecording':
				case 'Poster':
					return '####';
			}
		}
		
		# Start a stack of values, which will be truncated to or filled-out to 4 characters
		$stack = '';
		
		# For /ser and /art/j, 24 is always '|'; e.g. /records/1210/ (test #401)
		if (in_array ($this->recordType, array ('/ser', '/art/j'))) {
			$stack .= '|';
		}
		
		# If record starts with *kw 'Bibliograph(y|ies)' => b
		if ($this->kFieldMatches ('kw', 'Bibliograph')) {$stack .= 'b';} // E.g. /records/1005/ (test #34)
		
		# If record starts with *kw 'Dictionaries*' => d
		if ($this->kFieldMatches ('kw', 'Dictionar')) {$stack .= 'd';}
		
		# If record starts with *kw 'Law*' => g
		if ($this->kFieldMatches ('kw', 'Law')) {$stack .= 'g';}
		
  		# If *loc contains (as bounded search) 'Theses' => m
		if ($this->fieldContainsBoundedStart ('location', 'Theses')) {$stack .= 'm';}	// E.g. /records/3152/ (test #402), /records/21127/ (test #403)
		
		# If record contains (as bounded search) *kw 'Directories' => r
		if ($this->kFieldMatches ('kw', 'Director(y|ies)', '\b')) {$stack .= 'r';}	// E.g. /records/21310/ (test #416)
		
		# If record starts with *kw 'Statistics' => s
		if ($this->kFieldMatches ('kw', 'Statistic')) {$stack .= 's';}
		
		# If record starts with *kw 'Treaties, international*' => z
		if ($this->kFieldMatches ('kw', 'Treaties, international')) {$stack .= 'z';}
		
		# For /doc and /art/in, if *local and/or *note contain 'offprint' => 2
		if (in_array ($this->recordType, array ('/doc', '/art/in'))) {
			if ($this->fieldRepeatableContainsBoundedStart ('local', 'offprint') || $this->fieldRepeatableContainsBoundedStart ('note', 'offprint')) {$stack .= '2';}	// E.g. /records/198459/ (test #35)
		}
		
		# If *t contains [could be anywhere in the title] 'calendar*' => 5
		if ($this->fieldContainsBoundedStart ('t', 'calendar')) {$stack .= '5';}
		
		# Truncate to 4 characters
		if (mb_strlen ($stack) > 4) {
			$stack = mb_substr ($stack, 0, 4);	// No cases yet identified, so no test cases
		}
		
		# If any of are still empty => # in each empty position, e.g. 'bdgm', 'bdg#', 'bd##', 'b###', '####', or '|bdg', '|bd#', '|b##', '|###'
		return str_pad ($stack, 4, '#', STR_PAD_RIGHT);		// E.g. /records/21127/ (test #404)
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 28
	private function position_18_34__28 ()
	{
		switch ($this->form) {
			case 'CD':
			case 'Sound cassette':
			case 'Sound disc':
				return '|';		// E.g. /records/102409/ (test #405)
		}
		
		# If record has *kw 'Organizations, government' => o
		$kwValues = $this->marcConversion->xPathValues ($this->xml, '//k[%i]/kw');
		foreach ($kwValues as $kw) {
			if ($kw == 'Organizations, government') {return 'o';}	// E.g. /records/15998/ (test #36)
		}
		
		# Else => |
		return '|';	// E.g. /records/9999/ (test #406)
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 29
	private function position_18_34__29 ()
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':			// E.g. /records/182611/ (test #407)
					return '#';
				case 'Map':
				case 'CD':
				case 'Sound cassette':
				case 'Sound disc':
					return '|';
				case 'DVD':
				case 'Videorecording':
					return 'q';
				case 'Poster':
					return 'r';
			}
		}
		
		# If *k contains '061.3' OR *location contains '061.3' => 1
		if ($this->kContains0613 () || $this->locationContains0613 ()) {return 'd';}	// E.g. /records/4263/ (test #37), /records/6201/ (test #408)
		
		# Else => 0
		return '0';		// E.g. /records/9999/ (test #407)
	}
	
	
	# Helper function to check for k having 061.3; e.g. /records/4263/ (test #37)
	private function kContains0613 ()
	{
		# NB All records have been checked that there are no "061.3[0-9]"
		$ksValues = $this->marcConversion->xPathValues ($this->xml, '//k[%i]/ks');
		foreach ($ksValues as $ks) {
			if (preg_match ('/\b061\.3/', $ks)) {return true;}
		}
		return false;
	}
	
	
	# Helper function to check for location having 061.3; e.g. /records/6201/ (test #408)
	private function locationContains0613 ()
	{
		$location = $this->marcConversion->xPathValue ($this->xml, '//location');
		return (preg_match ('/\b061\.3/', $location));
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 30-31
	private function position_18_34__30_31 ()
	{
		switch ($this->form) {
			
			case '3.5 floppy disk':
			case 'CD-ROM':
			case 'DVD-ROM':			// E.g. /records/182611/ (test #409)
				return '##';
				
			case 'Map':
				return '#|';
				
			case 'CD':
			case 'Sound cassette':
			case 'Sound disc':
				
				# Start a stack of values, which will be truncated to or filled-out to 2 characters
				$stack = '';
				
				# If *t contains 'autobiography' => a
				if ($this->fieldContainsBoundedStart ('t', 'autobiograph')) {$stack .= 'a';}	// No examples found in data, so no test
				
				# If record contains *k '92[*' or *k '92(08)' => b	// E.g. /records/178689/ , /records/142030/ (test #410)
				if ($this->kContains92Bracket9208 ()) {$stack .= 'b';}
				
				# If *k contains '061.3' OR *loc contains '061.3' => c
				if ($this->kContains0613 () || $this->locationContains0613 ()) {$stack .= 'c';}
				
				# If record contains *k '82-2' => d
				if ($this->kFieldMatches ('ks', '82-2')) {$stack .= 'd';}	// E.g. /records/142030/ (test #410)
				
				# If record contains *k '82-3' => f
				if ($this->kFieldMatches ('ks', '82-3')) {$stack .= 'f';}
				
				# If record contains *k '93*' => h
				# NB 93 have been checked to ensure all are exactly 93 or 93"...
				if ($this->kFieldMatches ('ks', '93')) {$stack .= 'h';}
				
				# If *t contains 'memoir*' => m
				$t = $this->marcConversion->xPathValue ($this->xml, '//t');
				if (preg_match ('/\bmemoir/i', $t)) {$stack .= 'm';}	// No examples found in data, so no test
				
				# If record contains *k '398' => o
				# NB Judged that ^398 is sufficient
				if ($this->kFieldMatches ('ks', '398')) {$stack .= 'o';}	// No examples found in data, so no test
				
				# If record contains *k '82-1' => p
				if ($this->kFieldMatches ('ks', '82-1')) {$stack .= 'p';}	// E.g. /records/167945/ (test #563)
				
				# If *t contains 'interview*' => t
				$t = $this->marcConversion->xPathValue ($this->xml, '//t');
				if (preg_match ('/\binterview/i', $t)) {$stack .= 't';}
				
				# Truncate to 2 characters
				if (mb_strlen ($stack) > 2) {
					$stack = mb_substr ($stack, 0, 2);
				}
				
				# If any of pos. 30 or 31 are still empty => # in each empty position; e.g. /records/178689/ (test #411)
				return str_pad ($stack, 2, '#', STR_PAD_RIGHT);	// e.g. 'ab', 'a#', '##'
			
			case 'DVD':
			case 'Videorecording':
			case 'Poster':
				return '##';	// E.g. /records/160682/ (test #413)
		}
		
		# Other *form values
		switch ($this->recordType) {
			
			case '/doc':
			case '/art/in':
				
				# If *t contains Festschrift => 1 then |
				$t = $this->marcConversion->xPathValue ($this->xml, '//t');
				$tt = $this->marcConversion->xPathValue ($this->xml, '//tt');
				if (preg_match ('/Festschrift/i', $t) || preg_match ('/Festschrift/i', $tt)) {	// Simple match to deal with cases of records having two *t like /records/13607/ (test #39)
					return '1' . '|';
				}
				
				# Otherwise ||
				return '|' . '|';	// E.g. /records/167945/ (test #38)
			
			case '/ser':
			case '/art/j':
				return '##';	// E.g. /records/1031/ (test #412)
		}
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 32; e.g. /records/1031/ (test #414)
	private function position_18_34__32 ()
	{
		return '#';
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 33
	private function position_18_34__33 (&$errorString)
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
					return '#';
				case 'Map':
				case 'CD':
				case 'Sound cassette':
				case 'Sound disc':
					return '|';
				case 'DVD':					// E.g. /records/162291/ (test #415)
				case 'Videorecording':
					return 'v';
				case 'Poster':
					return 'i';
			}
		}
		
		switch ($this->recordType) {
			case '/doc':
			case '/art/in':
				
				# Check for specific *k values
				$strings = array (
					'82-1' => 'p',	// E.g. /records/1319/ (test #40)
					'82-2' => 'd',
					'82-3' => '1',
				);
				foreach ($strings as $type => $valueIfMatched) {
					if ($this->kFieldMatches ('ks', $type)) {
						return $valueIfMatched;
					}
				}
				return 0;
				
			case '/ser':
			case '/art/j':
				
				$lang = $this->marcConversion->xPathValue ($this->xml, '(//lang)[1]', false);	// E.g. /records/1031/ (test #41)
				return $this->marcConversion->lookupValue ('languageCodes', 'English', true, false, $lang, 'Script Code', $errorString);	// Script code is defined for position 33 at https://www.loc.gov/marc/bibliographic/bd008s.html
		}
		
		# Flag error
		return NULL;
	}
	
	
	# Helper function to deal with k having 82-1, etc.
	private function kFieldMatches ($kField, $string, $matchType = '^')
	{
		$values = $this->marcConversion->xPathValues ($this->xml, "//k[%i]/{$kField}");
		foreach ($values as $value) {
			switch ($matchType) {
				case '^':	// E.g. /records/1005/ (test #34)
				case '\b':	// E.g. /records/21310/ (test #416)
					if (preg_match ('/' . $matchType . $string . '/', $value)) {return true;}	// E.g. "82-1[something]" is a correct match
					break;
				case '=':	// Not actually used
					if ($string == $value) {return true;}
					break;
			}
		}
		return false;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 34
	private function position_18_34__34 ()
	{
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
					return '#';
				case 'Map':
					return '|';
				case 'CD':
				case 'Sound cassette':
				case 'Sound disc':
					return '#';
				case 'DVD':				// E.g. /records/162291/ (test #417)
				case 'Videorecording':
				case 'Poster':
					return '|';
			}
		}
		
		switch ($this->recordType) {
			case '/doc':
			case '/art/in':
				
				# If *t contains 'autobiography' => a
				if ($this->fieldContainsBoundedStart ('t', 'autobiograph')) {return 'a';}	// E.g. /records/6046/ (test #43)
				
				# Else if *location contains '92[*' => b
				$location = $this->marcConversion->xPathValue ($this->xml, '//location');	// E.g. /records/1854/ (test #418)
				if (preg_match ('/\b92\[/', $location)) {return 'b';}
				
				# Else if *location contains '92(08)' => c
				if (preg_match ('/\b92\(08\)/', $location)) {return 'c';}	// E.g. /records/1858/ (test #419)
				
				# Else if record contains *k '92[*' or *k '92(08)' => d
				if ($this->kContains92Bracket9208 ()) {return 'd';}	// E.g. /records/2505/ (test #42)
				
				# Else => #
				return '#';
				
			case '/ser':
			case '/art/j':
				
				return '|';		// E.g. /records/1210/ (test #395)
		}
		
		# Flag error
		return NULL;
	}
	
	
	# Helper function to check for a field containing a string, tied at the start to a word boundary; e.g. /records/3152/ (test #402), /records/21127/ (test #403)
	private function fieldContainsBoundedStart ($field, $string)
	{
		$value = $this->marcConversion->xPathValue ($this->xml, "//{$field}");
		return (preg_match ('/\b' . $string . '/i', $value));
	}
	
	
	# Helper function to check for a repeatable field containing a string, tied at the start to a word boundary
	private function fieldRepeatableContainsBoundedStart ($field, $string)
	{
		$values = $this->marcConversion->xPathValues ($this->xml, "(//{$field})[%i]", false);		// e.g. /records/2440/ for *local
		foreach ($values as $value) {
			if (preg_match ('/\b' . $string . '/i', $value)) {return true;}
		}
		return false;
	}
	
	
	# Helper function to check for *k containing 92[ or 92(08); e.g. /records/2505/ (test #42)
	private function kContains92Bracket9208 ()
	{
		$ksValues = $this->marcConversion->xPathValues ($this->xml, '//k[%i]/ks');
		foreach ($ksValues as $ks) {
			if (preg_match ('/\b(92\[|92\(08\))/', $ks)) {return true;}
		}
		return false;
	}
	
	
	# 008 pos. 35-37: Language; e.g. /records/29970/ (test #44)
	private function position_35_37 (&$errorString)
	{
		$lang = $this->marcConversion->xPathValue ($this->xml, '(//lang)[1]', false);
		return $this->marcConversion->lookupValue ('languageCodes', 'English', true, false, $lang, 'MARC Code', $errorString);
	}
	
	
	# 008 pos. 38: Modified record (test #420)
	private function position_38 ()
	{
		return '#';
	}
	
	
	# 008 pos. 39: Cataloguing source (test #420)
	private function position_39 ()
	{
		return 'd';
	}
}

?>
