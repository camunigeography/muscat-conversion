<?php


#!# Characters like ^t need to be converted in lookups


# Class to generate the complex 008 field
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
				
				$lang = $this->muscatConversion->xPathValue ($this->xml, '//lang');
				return $this->lookupValue ('languageCodeTable', $lang, 'Script Code', 'English');
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
	
	
	# Function to determine the language code table
	private function languageCodeTable ()
	{
		# Define the lookup table
		return $lookupTable = "
			Muscat Language	MARC Code	Script Code
			Afrikaans	afr	b
			Aleut	ale	b
			Altay	alt	c
			Ahtna	ath	b
			Athapaskan	ath	b
			Dena'ina	ath	b
			Dene	ath	b
			Byelorussian	bel	c
			Buryat	bua	c
			Bulgarian	bul	b
			Catalan	cat	b
			Chinese	chi	e
			Church Slavonic	chu	c
			Cree	cre	z
			Czech	cze	b
			Danish	dan	b
			Dutch	dut	b
			Flemish	dut	b
			English	eng	a
			Esperanto	epo	b
			Estonian	est	b
			Faroese	fao	b
			Finnish	fin	b
			Khanty	fiu	z
			Mansi	fiu	z
			Veps	fiu	z
			French	fre	b
			German	ger	b
			Gwich'in	gwi	b
			Hebrew	heb	h
			Hindi	hin	j
			Croatian	hrv	b
			Hungarian	hun	b
			Icelandic	ice	b
			Inuktitut	iku	z
			Inuit	iku	z
			Inuktituk	iku	z
			Inupiat	ipk	b
			In^tupiat	ipk	b
			Italian	ita	b
			Japanese	jpn	d
			Greenlandic	kal	b
			Kalaallisut	kal	b
			Komi	kom	c
			Korean	kor	k
			Karelian	krl	b
			Latin	lat	a
			Latvian	lav	b
			Lithuanian	lit	b
			Chukchi	mis	c
			Itel'men	mis	c
			Ket	mis	c
			Koryak	mis	c
			Negidal	mis	c
			Nenets	mis	c
			Nganasan	mis	c
			Nivkh	mis	c
			Yukagir	mis	c
			Norwegian	nor	b
			Chippewa	oji	z
			Polish	pol	b
			Portuguese	por	b
			Romanian	rum	b
			Russian	rus	c
			Sakha	sah	c
			Yakut	sah	c
			Sel'kup	sel	c
			Slovak	slo	b
			Slovene	slv	b
			Saami	smi	z
			Saami (Inari)	smn	b
			Spanish	spa	b
			Swedish	swe	b
			Dolgan	tut	z
			Even	tut	z
			Evenki	tut	z
			Lamut	tut	z
			Nanay	tut	z
			Oroch	tut	z
			Teleut	tut	z
			Udege	tut	z
			Udegey	tut	z
			Tuvan	tyv	c
			Ukrainian	ukr	c
			Yiddish	yid	h
			Yup'ik	ypk	z
		";
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
		$t = $this->muscatConversion->xPathValue ($this->xml, "//{$field}");
		return (preg_match ('/\b' . $string . '/i', $t));
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
		
		/*
		# Sanity-check while developing
		$expectedLength = 1;	// Manually needs to be changed to 3 for languageCodeTable -> Marc Code
		foreach ($lookupTable as $entry => $values) {
			if (strlen ($values[$field]) != $expectedLength) {
				echo "<p class=\"warning\">In the {$tableFunction} definition, <em>{$entry}</em> for field <em>{$field}</em> has invalid syntax.</p>";
				return NULL;
			}
		}
		*/
		
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