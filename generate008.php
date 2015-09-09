<?php

# Class to generate the complex 008 field; see: http://www.loc.gov/marc/bibliographic/bd008.html
class generate008
{
	# Constructor
	public function __construct ($muscatConversion, $xml, $diacriticsTable)
	{
		# Create a class property handle to the parent class
		$this->muscatConversion = $muscatConversion;
		
		# Create a handle to the XML
		$this->xml = $xml;
		
		# Create a handle to the diacritics table
		$this->diacriticsTable = $diacriticsTable;
		
	}
	
	
	# Main
	public function main (&$error = false)
	{
		# Determine the record type or end
		if (!$this->recordType = $this->recordType ()) {
			$error = '008 field: Could not determine record type';
			return false;
		}
		
		# Determine the *form value
		$this->form = $this->muscatConversion->xPathValue ($this->xml, $this->recordType . '/form');
		
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
			$string = $this->{$function} ();
			$value .= $string;
			
			# Sanity-check that the string length is 40
			$length = strlen ($string);
			if ($length != $expectedLength) {
				$error = "008 field " . (substr_count ($positions, '-') ? 'positions' : 'position') . " {$positions}: Length of is {$length} but should be {$expectedLength}";
				return false;
			}
		}
		
		# Return the value
		return $value;
	}
	
	
	# 008 pos. 00-05: Date entered on file
	private function position_00_05 ()
	{
		# Date entered on system [format: yymmdd]
		return date ('ymd');
	}
	
	
	# 008 pos. 06: Type of date/Publication status, and 07-10: Date 1, and pos. 11-14: Date 2
	private function position_06_14 ()
	{
		# If *d in *doc or *art, or *r in *ser does not contain at least one year (e.g. '[n.d.]'), designator is 'n'
		# Note that decade-wide dates like "199-" are considered a valid year
		# If 06 is 'n', 07-10 contain 'uuuu'
		# If 06 is 'n', 'q' or 's', 11-14 contain '####'
		$yearField = ($this->recordType == '/ser' ? 'r' : 'd');
		$yearString = $this->muscatConversion->xPathValue ($this->xml, $this->recordType . "//{$yearField}");
		if (!preg_match ('/([0-9]{3}[-0-9])/', $yearString, $yearMatches)) {
			return 'n' . 'uuuu' . '####';
		}
		
		# If record is *ser, designator is 'm'
		# if 06 is 'm', 07-10 contain first year in *r
		# In the context of a *ser, - does not mean unknown digit; instead it is used only for a range
		if ($this->recordType == '/ser') {
			
			# For continuing serials, e.g. ends with "1990-", there is no end date
			if (preg_match ('/-$/', $yearString)) {
				return 'm' . $yearMatches[1] . '####';
			}
			
			# Normalise cases of incorrect data specified as YYYY-YY, e.g. "1990-95" which should be "1990-1995"; all manually checked that these are all 19xx dates (not 18xx/20xx/etc.)
			$yearString = preg_replace ('/([0-9]{4})-([0-9]{2})($|[^0-9])/', '\1-19\2\3', $yearString);
			
			# If 06 is 'm', 11-14 contain last present year in *r (which could validly be the same as the first year if the string is just "1990" - this would mean a serial that starts and 1990 and ends in 1990)
			preg_match_all ('/([0-9]{4})/', $yearString, $lastYearMatches, PREG_SET_ORDER);		// See: http://stackoverflow.com/questions/23343087/
			$lastYear = end ($lastYearMatches);
			return 'm' . $yearMatches[1] . $lastYear[1];
		}
		
		# For *doc and *art [which will be guaranteed if we have reached this point], if *d contains '?', '-', or is enclosed in square brackets, designator is 'q'
		# if 06 is 'q', 07-10 contain the year from *d (i.e. no other characters e.g. '[', ']' or '?'); any digits replaced by hyphens in Muscat should be replaced by 'u' in Voyager
		# If 06 is 'n', 'q' or 's', 11-14 contain '####'
		$yearMatch = str_replace ('-', 'u', $yearMatches[1]);
		if (substr_count ($yearString, '?'))		{return 'q' . $yearMatch . '####';}
		if (substr_count ($yearString, '-'))		{return 'q' . $yearMatch . '####';}
		if (preg_match ('/\[.+\]/', $yearString))	{return 'q' . $yearMatch . '####';}
		
		# If *d is of the format '1984 (2014 printing)', designator is 'r'
		# if 06 is 'r', 07-10 contain the later of the two years, i.e. '2014' if *d is '1984 (2014 printing)'
		# NB /records/12681/ which has "1924 (December printing)" intentionally does not comply
		# The printing year never has a - in practice, so for this reason we do not to worry about - => u replacement
		# If 06 is 'r', 11-14 contain the earlier of the two years, i.e. '1984' if *d is '1984 (2014 printing)'
		if (preg_match ('/^([0-9]{3}[-0-9]) \(([0-9]{4}) printing\)$/', $yearString, $printingMatches)) {
			return 'r' . $printingMatches[2] . $printingMatches[1];
		}
		
		# Otherwise designator is 's'
		# if 06 is 's', 07-10 contain year from *d
		# If 06 is 'n', 'q' or 's', 11-14 contain '####'
		return 's' . $yearMatches[1] . '####';
	}
	
	
	# 008 pos. 15-17: Place of publication, production, or execution
	private function position_15_17 ()
	{
		# Extract the value
		$pl = $this->muscatConversion->xPathValue ($this->xml, '(//pl)[1]', false);
		
		# Remove surrounding brackets if present, e.g. "[Frankfurt]" => "Frankfurt"
		if (preg_match ('/^\[(.+)\]$/', $pl, $matches)) {
			$pl = $matches[1];
		}
		
		# Look it up in the country codes table
		return $this->lookupValue ('countryCodes', $pl, 'MARC Country Code', '');
	}
	
	
	# 008 pos. 18-34: Material specific coded elements
	private function position_18_34 ()
	{
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
				$value  = $this->lookupValue ('journalFrequencies', $freq, 'Frequency', 'No *freq', false);
				$value .= $this->lookupValue ('journalFrequencies', $freq, 'Regularity', 'No *freq', false);
				$value .= '#';
				
				return $value;
		}
		
		# Flag error
		return NULL;
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
		if ($this->isMultimediaish) {
			switch ($this->form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
					return '##|#';
				case 'Map':
					return '#|##';
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
		
		# For /ser and /art/j, 24 is always '|'
		if (in_array ($this->recordType, array ('/ser', '/art/j'))) {
			$stack .= '|';
		}
		
		# If record starts with *kw 'Bibliograph(y|ies)' => b
		if ($this->kFieldMatches ('kw', 'Bibliograph')) {$stack .= 'b';}
		
		# If record starts with *kw 'Dictionaries*' => d
		if ($this->kFieldMatches ('kw', 'Dictionar')) {$stack .= 'd';}
		
		# If record starts with *kw 'Law*' => g
		if ($this->kFieldMatches ('kw', 'Law')) {$stack .= 'g';}
		
  		# If *loc contains (as bounded search) 'Theses' => m
		if ($this->fieldContainsBoundedStart ('location', 'Theses')) {$stack .= 'm';}
		
		# If record contains (as bounded search) *kw 'Directories' => r
		if ($this->kFieldMatches ('kw', 'Director(y|ies)', '\b')) {$stack .= 'r';}
		
		# If record starts with *kw 'Statistics' => s
		if ($this->kFieldMatches ('kw', 'Statistic')) {$stack .= 's';}
		
		# If record starts with *kw 'Treaties, international*' => z
		if ($this->kFieldMatches ('kw', 'Treaties, international')) {$stack .= 'z';}
		
		# For /doc and /art/in, if *local and/or *note contain 'offprint' => 2
		if (in_array ($this->recordType, array ('/doc', '/art/in'))) {
			if ($this->fieldRepeatableContainsBoundedStart ('local', 'offprint') || $this->fieldRepeatableContainsBoundedStart ('note', 'offprint')) {$stack .= '2';}
		}
		
		# If *t contains [could be anywhere in the title] 'calendar*' => 5
		if ($this->fieldContainsBoundedStart ('t', 'calendar')) {$stack .= '5';}
		
		# Truncate to 4 characters
		if (strlen ($stack) > 4) {
			$stack = substr ($stack, 0, 4);
		}
		
		# If any of are still empty => # in each empty position, e.g. 'bdgm', 'bdg#', 'bd##', 'b###', '####', or '|bdg', '|bd#', '|b##', '|###'
		return str_pad ($stack, 4, '#', STR_PAD_RIGHT);
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 28
	private function position_18_34__28 ()
	{
		switch ($this->form) {
			case 'CD':
			case 'Sound cassette':
			case 'Sound disc':
				return '|';
		}
		
		# If record has *kw 'Organizations, government' => o
		$kwValues = $this->muscatConversion->xPathValues ($this->xml, '//k[%i]/kw');
		foreach ($kwValues as $kw) {
			if ($kw == 'Organizations, government') {return 'o';}
		}
		
		# Else => |
		return '|';
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 29
	private function position_18_34__29 ()
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
				case 'DVD':
				case 'Videorecording':
					return 'q';
				case 'Poster':
					return 'r';
			}
		}
		
		# If *k contains '061.3' OR *loc contains '061.3' => 1
		if ($this->kContains0613 () || $this->locationContains0613 ()) {return 'd';}
		
		# Else => 0
		return '0';
	}
	
	
	# Helper function to check for k having 061.3
	private function kContains0613 ()
	{
		# NB All records have been checked that there are no "061.3[0-9]"
		$ksValues = $this->muscatConversion->xPathValues ($this->xml, '//k[%i]/ks');
		foreach ($ksValues as $ks) {
			if (preg_match ('/\b061\.3/', $ks)) {return true;}
		}
		return false;
	}
	
	
	# Helper function to check for location having 061.3
	private function locationContains0613 ()
	{
		$location = $this->muscatConversion->xPathValue ($this->xml, '//location');
		return (preg_match ('/\b061\.3/', $location));
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 30-31
	private function position_18_34__30_31 ()
	{
		switch ($this->form) {
			
			case '3.5 floppy disk':
			case 'CD-ROM':
			case 'DVD-ROM':
				return '##';
				
			case 'Map':
				return '#|';
				
			case 'CD':
			case 'Sound cassette':
			case 'Sound disc':
				
				# Start a stack of values, which will be truncated to or filled-out to 2 characters
				$stack = '';
				
				# If *t contains 'autobiography' => a
				if ($this->fieldContainsBoundedStart ('t', 'autobiograph')) {$stack .= 'a';}
				
				# If record contains *k '92[*' or *k '92(08)' => b
				if ($this->kContains92Bracket9208 ()) {$stack .= 'b';}
				
				# If *k contains '061.3' OR *loc contains '061.3' => c
				if ($this->kContains0613 () || $this->locationContains0613 ()) {$stack .= 'c';}
				
				# If record contains *k '82-2' => d
				if ($this->kFieldMatches ('ks', '82-2')) {$stack .= 'd';}
				
				# If record contains *k '82-3' => f
				if ($this->kFieldMatches ('ks', '82-3')) {$stack .= 'f';}
				
				# If record contains *k '93*' => h
				# NB 93 have been checked to ensure all are exactly 93 or 93"...
				if ($this->kFieldMatches ('ks', '93')) {$stack .= 'h';}
				
				# If *t contains 'memoir*' => m
				$t = $this->muscatConversion->xPathValue ($this->xml, '//t');
				if (preg_match ('/\bmemoir/i', $t)) {$stack .= 'm';}
				
				# If record contains *k '398' => o
				# NB Judged that ^398 is sufficient
				if ($this->kFieldMatches ('ks', '398')) {$stack .= 'o';}
				
				# If record contains *k '82-1' => p
				if ($this->kFieldMatches ('ks', '82-2')) {$stack .= 'p';}
				
				# If *t contains 'interview*' => t
				$t = $this->muscatConversion->xPathValue ($this->xml, '//t');
				if (preg_match ('/\binterview/i', $t)) {$stack .= 't';}
				
				# Truncate to 2 characters
				if (strlen ($stack) > 2) {
					$stack = substr ($stack, 0, 2);
				}
				
				# If any of pos. 30 or 31 are still empty => # in each empty position
				return str_pad ($stack, 2, '#', STR_PAD_RIGHT);	// e.g. 'ab', 'a#', '##'
			
			case 'DVD':
			case 'Videorecording':
			case 'Poster':
				return '##';
		}
		
		switch ($this->recordType) {
			
			case '/doc':
			case '/art/in':
				
				# If *t contains Festschrift => 1 then |
				$t = $this->muscatConversion->xPathValue ($this->xml, '//t');
				$tt = $this->muscatConversion->xPathValue ($this->xml, '//tt');
				if (preg_match ('/Festsxchrift/i', $t) || preg_match ('/Festxschrift/i', $tt)) {	// Simple match to deal with cases of records having two *t like 13607
					return '1' . '|';
				} else {
					return '|' . '|';
				}
			
			case '/ser':
			case '/art/j':
				return '##';
		}
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 32
	private function position_18_34__32 ()
	{
		return '#';
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 33
	private function position_18_34__33 ()
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
				case 'DVD':
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
					'82-1' => 'p',
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
				
				$lang = $this->muscatConversion->xPathValue ($this->xml, '(//lang)[1]', false);
				return $this->lookupValue ('languageCodes', $lang, 'Script Code', 'English');
		}
		
		# Flag error
		return NULL;
	}
	
	
	# Helper function to deal with k having 82-1, etc.
	private function kFieldMatches ($kField, $string, $matchType = '^')
	{
		$values = $this->muscatConversion->xPathValues ($this->xml, "//k[%i]/{$kField}");
		foreach ($values as $value) {
			switch ($matchType) {
				case '^':
				case '\b':
					if (preg_match ('/' . $matchType . $string . '/', $value)) {return true;}	// E.g. "82-1[something]" is a correct match
					break;
				case '=':
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
				case 'DVD':
				case 'Videorecording':
				case 'Poster':
					return '|';
			}
		}
		
		switch ($this->recordType) {
			case '/doc':
			case '/art/in':
				
				# If *t contains 'autobiography' => a
				if ($this->fieldContainsBoundedStart ('t', 'autobiograph')) {return 'a';}
				
				# Else if *location contains '92[*' => b
				$location = $this->muscatConversion->xPathValue ($this->xml, '//location');
				if (preg_match ('/\b92\[/', $location)) {return 'b';}
				
				# Else if *location contains '92(08)' => c
				if (preg_match ('/\b92\(08\)/', $location)) {return 'c';}
				
				# Else if record contains *k '92[*' or *k '92(08)' => d
				if ($this->kContains92Bracket9208 ()) {return 'd';}
				
				# Else => #
				return '#';
				
			case '/ser':
			case '/art/j':
				
				return '|';
		}
		
		# Flag error
		return NULL;
	}
	
	
	# Helper function to check for a field containing a string, tied at the start to a word boundary
	private function fieldContainsBoundedStart ($field, $string)
	{
		$value = $this->muscatConversion->xPathValue ($this->xml, "//{$field}");
		return (preg_match ('/\b' . $string . '/i', $value));
	}
	
	
	# Helper function to check for a repeatable field containing a string, tied at the start to a word boundary
	private function fieldRepeatableContainsBoundedStart ($field, $string)
	{
		$values = $this->muscatConversion->xPathValues ($this->xml, "//{$field}[%i]");
		foreach ($values as $value) {
			if (preg_match ('/\b' . $string . '/i', $value)) {return true;}
		}
		return false;
	}
	
	
	# Helper function to check for *k containing 92[ or 92(08)
	private function kContains92Bracket9208 ()
	{
		$ksValues = $this->muscatConversion->xPathValues ($this->xml, '//k[%i]/ks');
		foreach ($ksValues as $ks) {
			if (preg_match ('/\b(92\[|92\(08\))/', $ks)) {return true;}
		}
		return false;
	}
	
	
	# 008 pos. 35-37: Language
	private function position_35_37 ()
	{
		$lang = $this->muscatConversion->xPathValue ($this->xml, '(//lang)[1]', false);
		return $this->lookupValue ('languageCodes', $lang, 'MARC Code', 'English');
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
	private function lookupValue ($table, $value, $field, $ifEmptyUseValueFor, $caseSensitiveComparison = true)
	{
		# If the supplied value is empty, and a fallback is defined, treat the value as the fallback, which will then be looked up
		if (!$value) {$value = $ifEmptyUseValueFor;}
		
		# Get the data table
		$lookupTable = file_get_contents ($this->muscatConversion->applicationRoot . '/tables/' . $table . '.tsv');
		
		# Convert to TSV
		$lookupTable = trim ($lookupTable);
		require_once ('csv.php');
		$lookupTableRaw = csv::tsvToArray ($lookupTable, $firstColumnIsId = true);
		
		# Convert diacritics
		$lookupTable = array ();
		foreach ($lookupTableRaw as $key => $values) {
			$key = strtr ($key, $this->diacriticsTable);
			$lookupTable[$key] = $values;
		}
		
		# If doing case-insensitive comparison, convert both sides to lower case
		if (!$caseSensitiveComparison) {
			
			# Lower-case the value
			$value = mb_strtolower ($value);
			
			# Lower-case the lookup table
			$lookupTableRawLowercaseKeys = array ();
			foreach ($lookupTableRaw as $key => $values) {
				$key = mb_strtolower ($key);
				$lookupTableRawLowercaseKeys[$key] = $values;
			}
			$lookupTableRaw = $lookupTableRawLowercaseKeys;
		}
		
		/*
		# Sanity-check while developing
		$expectedLength = 1;	// Manually needs to be changed to 3 for languageCodes -> Marc Code
		foreach ($lookupTable as $entry => $values) {
			if (strlen ($values[$field]) != $expectedLength) {
				echo "<p class=\"warning\">In the {$table} definition, <em>{$entry}</em> for field <em>{$field}</em> has invalid syntax.</p>";
				return NULL;
			}
		}
		*/
		
		# Ensure the string is present
		if (!isSet ($lookupTable[$value])) {
			echo "<p class=\"warning\">In the {$table} table, value '<em>{$value}</em>' is not present in the table.</p>";
			return NULL;
		}
		
		# Compile the result
		$result = $lookupTable[$value][$field];
		
		# Trim, in case of line-ends
		$result = trim ($result);
		
		# Return the result
		return $result;
	}
}

?>