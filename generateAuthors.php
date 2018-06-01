<?php

# Class to generate the complex author fields

/* Mutations can happen as follows:
	
	First round: looks at the very first entity (person/company/conference) where person is author/editor/etc.
		For this item:
			Start, assuming it is a 100
				Obtain the value
					|
					|---> Value that has been found can stay as 100 then Add to record; end further processing of first round
					|
					|---> A mutatation into 110 may be detected; at this point any value is thrown away; then 110 processing starts
					      |---> 110 routine generates a value; then Add to record; end further processing of first round
					      |---> OR value can mutate into 710 then Add to record; end further processing of first round
					|
					|---> A mutatation into 111 may be detected; at this point any value is thrown away; then 111 processing starts
					      |---> 710 routine generates a value; then Add to record; end further processing of first round
					      |---> OR value can mutate into 711 then Add to record; end further processing of first round
					|
					|---> Value that has been found can become 700 then Add to record; end further processing of first round
					
	Second round: looks each each other entity
		For each one:
			Start, assuming it is a 700
				Obtain the value
					|
					|---> Value can stay as 700 then Add to record; go to next in loop
					|
					|---> Value can mutate into 710 then Add to record; go to next in loop
					|
					|---> Value can mutate into 711 then Add to record; go to next in loop
*/

/* Key checks:

	- The first *art/*ag/*a field should map to the 1XX field			e.g. /records/7195/ (test #59)
	- Any further *art/*ag/*a fields should map to 7XX fields			e.g. /records/8249/ (test #60)
	- Any *art/*ag/*al fields should map to 7XX fields					e.g. /records/1963/ (test #61)
	- Any *art/*e/*n fields should map to 7XX fields					e.g. /records/5126/ (test #62)
	
	However...
	
	- Any *art/*in/*ag/*a fields should NOT map to a 1XX or 7XX field	e.g. /records/1902/ (test #64)
	- Any *art/*in/*ag/*al fields should NOT map to a 7XX field			e.g. /records/3427/ (test #65)
	- Any *art/*in/*e/*n fields should NOT map to a 7XX field			[No cases] (checked again in Jan 2017, including *ee instead of *e in case of error)

*/

class generateAuthors
{
	# Define subfields that are capable of being transliterated
	private $transliterableSubfields = array (
		100 => 'aqc',
		110 => 'ab',
		111 => 'anc',
		700 => 'aqct',
		710 => 'abt',
		711 => 'anct',
	);
	
	
	# Constructor
	public function __construct ($marcConversion, $languageModes)
	{
		# Create class property handles to the parent class
		$this->marcConversion = $marcConversion;
		$this->databaseConnection = $marcConversion->databaseConnection;
		$this->settings = $marcConversion->settings;
		$this->languageModes = $languageModes;
		
		# Load lookups
		$this->lookups = array (
			'namesInDirectOrder'		=> $this->namesInDirectOrder (),
			'surnameOnly'				=> $this->surnameOnly (),
			'prefixes'					=> $this->prefixes (),
			'suffixes'					=> $this->suffixes (),
			'betweenN1AndN2'			=> $this->betweenN1AndN2 (),
			'relatorTerms'				=> $this->relatorTerms (),
			'miscList'					=> $this->miscList (),
			'affiliationList'			=> $this->affiliationList (),
			'dateList'					=> $this->dateList (),
			'fullStopExceptionsList'	=> $this->fullStopExceptionsList (),
		);
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
	}
	
	
	# Main entry point
	public function createAuthorsFields ($mainRecordXml)
	{
		# Initalise a values list
		$this->values = array ();
		
		# Create a handle to the XML
		$this->mainRecordXml = $mainRecordXml;
		
		# Determine the language of the record
		$recordLanguages = $this->marcConversion->xPathValues ($mainRecordXml, '(//lang)[%i]', false);	// e.g. /records/1220/ , /records/8690/ have multiple languages (test #66)
		
		# Process both normal and transliterated modes
		foreach ($this->languageModes as $languageMode) {
			
			# Initialise all fields, creating an empty array for each, into which lines can be registered
			$fields = array (100, 110, 111, 700, 710, 711);
			foreach ($fields as $field) {
				$this->values[$languageMode][$field] = array ();
			}
			
			# For the non-default language mode, if the current language mode does not match a language of the record, skip processing
			#!# Russian may only be one of the languages and not the relevant one
			if ($languageMode != 'default') {
				if (!in_array ($languageMode, $recordLanguages)) {
					continue;
				}
			}
			
			# Launch the two main entry points; each may include a mutation to a different field number
			$this->generateFirstEntity ($languageMode);		// Round one: first entity
			$this->generateOtherEntities ($languageMode);	// Round two: all other entities
		}
		
		# Return the values
		return $this->values;
	}
	
	
	# First entity generation entry point, which assumes 100 but may become 110/111/700/710/711
	/*
	 * This is basically the first author.
	 * It may end up switching to 110/111/700/710/711 instead.
	 * Everyone else involved in the production ends put in 7xx fields.
	 *
	 */
	private function generateFirstEntity ($languageMode)
	{
		# Assume 100 by default
		$this->field = 100;
		
		# Set the language mode
		$this->languageMode = $languageMode;
		
		# Look at the first or only *doc/*ag/*a OR *art/*ag/*a
		#   *ser     like /records/1062/ would not match (test #67)
		#   *doc     like /records/1392/ will match (test #68)
		#   *art/in  like /records/4179/ will match (test #69); its /art/in/ag/a will be ignored (test #70)
		#   *art/in with /art/in/ag/ but not /art/ag like /records/45318/ will not match (same as test #70)
		#   *art/j   like /records/1109/ will match (test #71)
		if (!$a = $this->marcConversion->xPathValue ($this->mainRecordXml, '//ag[parent::doc|parent::art]/a')) {
			return false;	// The entry in $this->values for this field will be left as when initialised, i.e. an empty array
		}
		
		# Do the classification
		$line = $this->main ($this->mainRecordXml, '//ag[parent::doc|parent::art][1]/a[1]', 100);
		
		# Subfield ‡u, if present, needs to go before subfield ‡e (test #90)
		$line = $this->shiftSubfieldU ($line);
		
		# Pass through the transliterator if required; e.g. /records/6653/ (test #107), /records/23186/ (test #108)
		$line = $this->marcConversion->macro_transliterateSubfields ($line, $this->transliterableSubfields[$this->field], $errorString_ignored, $languageMode);
		
		# Ensure the line ends with punctuation; e.g. /records/1218/ (test #72), /records/1221/ (test #73)
		$line = $this->marcConversion->macro_dotEnd ($line, $extendedCharacterList = true);
		
		# Write the value into the values registry
		$this->values[$this->languageMode][$this->field][] = $line;
	}
	
	
	# Other entities generation entry point, which assumes 700 but may become 710/711; see: http://www.loc.gov/marc/bibliographic/bd700.html
	/*
	 * This is basically all the people involved in the book except the first author, which if present is covered in 100/110/111. (tests #74, #75)
	 * It includes people in the analytic (child) records, but limited to the first of them for each such child record
	 * This creates multiple 700 lines, the lines being created as the outcome of the loop below
	 * Each "contributor block" referenced below refers to the author components, which are basically the 'classify' functions elsewhere in this class
	 * 
	 * - Check there is *doc/*ag or *art/*ag (i.e. *ser records will be ignored)
	 * - Loop through each *ag
	 * - Within each *ag, for each *a and *al add the contributor block
	 * - In the case of each *ag/*al, the "*al Detail" block (and ", ‡g (alternative name)", once only) is added
	 * - Loop through each *e
	 * - Within each *e, for each *n add the contributor block
	 * - In the case of each *e/*n, *role, with Relator Term lookup substitution, is incorporated
	 * - When considering the *e/*n, there is a guard clause to skip cases of 'the author' as the 100 field would have already pulled in that person (e.g. the 100 field could create "<name> $eIllustrator" indicating the author <name> is also the illustrator)
	 * - Check for a *ke which is a flag indicating that there are analytic (child) records, e.g. as present in /records/7463/
	 * - Look up the records whose *kg matches, e.g. /records/9375/ has *kg=7463, so this indicates that 9375 (which will be an *art) is a child of 7463
	 * - For each *kg's *art (i.e. child *art record): take the first *art/*ag/*a/ (only the first) in that record within the *ag block, i.e. /records/9375/ /art/ag/a "contributor block" (test #76), and also add the title (i.e. *art/*tg/*t) (test #77); the second indicator is set to '2' to indicate that this 700 line is an 'Analytical entry' (test #78)
	 * - Every 700 has a fixed string ", ‡5 UkCU-P." at the end (representing the Institution to which field applies)
	 * 
	 * Handling of multiple entries:
	 * - However many entries there are, each line is registered against the field number; the client code can then implode these into a multiline
	 * - After each line is registered, the field number is reset to 700 to ensure that a switch to say 710 is not leaky into the next entry
	 * - The ordering of 7xx fields ends up in numerical order, e.g. authorA then authorB may become 700 authorB then 710 authorA
	 */
	private function generateOtherEntities ($languageMode)
	{
		# Assume 700 by default
		$this->field = 700;
		
		# Set the language mode
		$this->languageMode = $languageMode;
		
		# Generate the 700 line values
		$lines = $this->generateOtherEntitiesLines ();
		
		# End if no lines
		if (!$lines) {
			return false;		// The entry in $this->values[$this->languageMode] for this field will be left as when initialised, i.e. an empty array
		}
		
		# Subfield ‡u, if present, needs to go before subfield ‡e (test #90)
		foreach ($lines as $index => $line) {
			$lines[$index]['line'] = $this->shiftSubfieldU ($line['line']);
		}
		
		# Pass each line through the transliterator if required (test #108, #109)
		foreach ($lines as $index => $line) {
			$fieldNumber = $line['field'];
			$lines[$index]['line'] = $this->marcConversion->macro_transliterateSubfields ($line['line'], $this->transliterableSubfields[$fieldNumber], $errorString_ignored, $languageMode);
		}
		
		# Ensure each line ends with punctuation; e.g. /records/7463/ (tests #81 and #82)
		foreach ($lines as $index => $line) {
			$lines[$index]['line'] = $this->marcConversion->macro_dotEnd ($line['line'], $extendedCharacterList = true);
		}
		
		# Write the values, into the values registry; the field number is not part of the line itself, e.g. /records/1127/ (test #111)
		foreach ($lines as $line) {
			$fieldNumber = $line['field'];
			$this->values[$this->languageMode][$fieldNumber][] = $line['line'];
		}
	}
	
	
	# Inner function for generateOtherEntities, covering everything except the final compilation of lines into a single string
	private function generateOtherEntitiesLines ()
	{
		# Assume 700 by default
		$this->field = 700;
		
		# Start a list of 700 line values; these are tuples as array(field,line); these are indexed numerically, rather than associatively as field=>line, as there may be more than one field instance
		# NB The client code (not this registry) then handles multiline, so that e.g. a 710 doesn't get stuck amongst 700s, e.g. /records/16272/ (test #755)
		# It is considered acceptable that the order therefore does not necessary stay the same, i.e. authorA then authorB may become 700 authorB then 710 authorA; this is probably more logical anyway in that the MARC field order then remains correct rather than e.g. 700 710 700; this is not defined at https://www.loc.gov/marc/specifications/specrecstruc.html#varifields
		$lines = array ();
		
		# If there is already a 700 field arising from generateFirstEntity, which will be a standard scalar string, register this first by transfering it into the lines format and resetting the 700 registry (test #109)
		if ($this->values[$this->languageMode][700]) {
			foreach ($this->values[$this->languageMode][700] as $line) {
				$lines[] = array ('field' => 700, 'line' => $line);
			}
			$this->values[$this->languageMode][700] = array ();		// Reset
		}
		
		# Check it is *doc/*ag or *art/*ag (i.e. ignore *ser records), e.g. /records/107192/ (test #110)
		# After this point, the only looping is through top-level *a* fields, e.g. /*/ag but not /*/in/ag
		if ($this->marcConversion->xPathValue ($this->mainRecordXml, '/ser')) {
			return $lines;
		}
		
		# Loop through each *ag
		$agIndex = 1;
		while ($this->marcConversion->xPathValue ($this->mainRecordXml, "/*/ag[$agIndex]")) {
			
			# Loop through each *a (author) in this *ag (author group)
			$aIndex = 1;	// XPaths are indexed from 1, not 0
			while ($this->marcConversion->xPathValue ($this->mainRecordXml, "/*/ag[$agIndex]/a[{$aIndex}]")) {
				
				# Skip the first /*ag/*a, e.g. /records/1127/ (tests #112, #113)
				if ($agIndex == 1 && $aIndex == 1) {
					$aIndex++;
					continue;
				}
				
				# Obtain the value
				$line = $this->main ($this->mainRecordXml, "/*/ag[$agIndex]/a[{$aIndex}]", 700);
				
				# Register the line, setting the field code, which may have been modified in main(), e.g. /records/1127/ (test #114)
				$lines[] = array ('field' => $this->field, 'line' => $line);
				
				# Next *a, e.g. /records/132356/ (test #115)
				$aIndex++;
			}
			
			# Loop through each *al (author) in this *ag (author group), e.g. /records/1565/ (test #116)
			$alIndex = 1;	// XPaths are indexed from 1, not 0
			while ($this->marcConversion->xPathValue ($this->mainRecordXml, "/*/ag[$agIndex]/al[{$alIndex}]")) {
				
				# Obtain the value
				$line = $this->main ($this->mainRecordXml, "/*/ag[$agIndex]/al[{$alIndex}]", 700);
				
				# The "*al Detail" block (and ", ‡g (alternative name)", once only) is added, e.g. /records/29234/ (test #118)
				if (!substr_count ($line, "{$this->doubleDagger}g")) {	// No actual cases found, so this block will always be entered
					$line  = $this->marcConversion->macro_dotEnd ($line, $extendedCharacterList = '.?!');		// e.g. /records/2787/ ; "700: Subfield g must be preceded by a full stop, question mark or exclamation mark." (test #83)
					$line .= "{$this->doubleDagger}g" . '(alternative name)';
				}
				
				# Register the line, setting the field code, which may have been modified in main()
				$lines[] = array ('field' => $this->field, 'line' => $line);
				
				# Next *al, e.g. /records/29234/ (test #117)
				$alIndex++;
			}
			
			# Next *ag
			$agIndex++;
		}
		
		# Loop through each *e
		$eIndex = 1;
		while ($this->marcConversion->xPathValue ($this->mainRecordXml, "/*/e[$eIndex]")) {
			
			# Within each *e, for each *n add the contributor block, e.g. /records/1247/ (test #119)
			$nIndex = 1;	// XPaths are indexed from 1, not 0
			while ($this->marcConversion->xPathValue ($this->mainRecordXml, "/*/e[$eIndex]/n[{$nIndex}]")) {
				
				# When considering the *e/*n, there is a guard clause to skip cases of 'the author' as the 100 field would have already pulled in that person (e.g. the 100 field could create "<name> $eIllustrator" indicating the author <name> is also the illustrator); e.g. /records/147053/ (test #84)
				#!# Move this check into the main processing?
				#!# Check this is as expected for e.g. /records/147053/
				$n1 = $this->marcConversion->xPathValue ($this->mainRecordXml, "/*/e[$eIndex]/n[{$nIndex}]/n1");
				if ($n1 == 'the author') {
					$nIndex++;
					continue;
				}
				
				# Obtain the value
				# In the case of each *e/*n, *role, with Relator Term lookup substitution, is incorporated; e.g. /records/47079/ (test #85) ; this is done inside classifyAdField ()
				$line = $this->main ($this->mainRecordXml, "/*/e[$eIndex]/n[{$nIndex}]", 700);
				
				# Register the line, if it has resulted in a line, setting the field code, which may have been modified in main()
				if ($line) {	// E.g. /records/8988/ which has "others" should not result in a line for /*/e[1]/n[2] due to classifyN1Field having "return false" (test #86)
					$lines[] = array ('field' => $this->field, 'line' => $line);
				}
				
				# Next *n, e.g. /records/2295/ (test #120)
				$nIndex++;
			}
			
			# Next *e
			$eIndex++;
		}
		
		# Check for a *ke which is a flag indicating that there are analytic (child) records; e.g. /records/7463/
		if ($this->marcConversion->xPathValue ($this->mainRecordXml, '//ke')) {		// Is just a flag, not a useful value (test #87); e.g. /records/1221/ contains "\<b> Analytics \<b(l) ~l 1000/"ME1221"/ ~>" which creates a button in the Muscat GUI
			
			# Look up the records whose *kg matches, e.g. /records/9375/ has *kg=7463, so this indicates that 9375 (which will be an *art) is a child of 7463 (tests #76 and #77)
			$currentRecordId = $this->marcConversion->xPathValue ($this->mainRecordXml, '/q0');
			if ($children = $this->getAnalyticChildren ($currentRecordId)) {	// Returns records as array (id=>xmlObject, ...)
				
				# Loop through each *kg's *art (i.e. child *art record)
				foreach ($children as $id => $childRecordXml) {
					
					# Take the first *art/*ag/*a/ (only the first (test #88)) in that record within the *ag block, i.e. /records/9375/ /art/ag/a "contributor block" (test #76); the second indicator is set to '2' to indicate that this 700 line is an 'Analytical entry' (test #78)
					$line = $this->main ($childRecordXml, "/*/ag[1]/a[1]", 700, '2');
					
					# Add the title (i.e. *art/*tg/*t)
					$line  = $this->marcConversion->macro_dotEnd ($line, $extendedCharacterList = '?.-)');		// (test #89) e.g. /records/9843/ , /records/13620/ ; "700: Subfield _t must be preceded by a question mark, full stop, hyphen or closing parenthesis."
					$line .= "{$this->doubleDagger}t" . $this->marcConversion->xPathValue ($childRecordXml, '/*/tg/t');
					
					# Register the line, setting the field code, which may have been modified in main()
					$lines[] = array ('field' => $this->field, 'line' => $line);
				}
			}
		}
		
		# Return the lines
		return $lines;
	}
	
	
	# Function to shift subfield ‡u, if present, to go before subfield ‡e (test #90); e.g. /records/127378/ , /records/134669/ , /records/135235/
	private function shiftSubfieldU ($line)
	{
		# Take no action unless both $u and $e are present
		if (!substr_count ($line, "{$this->doubleDagger}u") || !substr_count ($line, "{$this->doubleDagger}e")) {
			return $line;
		}
		
		# Move $u block to just before $e, leaving all others in place
		$line = preg_replace ("/^(.*)(,{$this->doubleDagger}e.*)(, {$this->doubleDagger}u[^{$this->doubleDagger}]+)(.*\.)$/u", '\1\3\2\4', $line);
		
		# Return the result
		return $line;
	}
	
	
	# Function to obtain the analytic children
	private function getAnalyticChildren ($parentId)
	{
		# Get the children
		$childIds = $this->databaseConnection->selectPairs ($this->settings['database'], 'catalogue_processed', array ('field' => 'kg', 'value' => $parentId), array ('recordId'));
		
		# Load the XML records for the children
		$children = $this->databaseConnection->selectPairs ($this->settings['database'], 'catalogue_xml', array ('id' => $childIds), array ('id', 'xml'));
		
		# Convert each XML record string to an XML object
		$childrenRecords = array ();
		foreach ($children as $id => $record) {
			$childrenRecords[$id] = $this->marcConversion->loadXmlRecord ($record);
		}
		
		# Return the records
		return $childrenRecords;
	}
	
	
	# Function providing an entry point into the main classification block, which switches between the name format
	private function main ($xml, $path, $defaultFieldCode, $secondIndicator = '#')
	{
		# Start the value
		$value = '';
		
		# Set (or reset) the field code so that every processing is guaranteed to have a clean start
		$this->field = $defaultFieldCode;
		
		# Create a handle to the context1xx flag
		$this->context1xx = (mb_substr ($defaultFieldCode, 0, 1) == 1);	// i.e. true if 1xx but not 7xx
		
		# Create a handle to the XML for this field
		$this->xml = $xml;
		
		# Create a handle to the second indicator
		$this->secondIndicator = $secondIndicator;
		
		# Does the *a contain a *n2?
		$n2 = $this->marcConversion->xPathValue ($this->xml, $path . '/n2');
		$n1 = $this->marcConversion->xPathValue ($this->xml, $path . '/n1');
		if (strlen ($n2)) {
			
			# Add to 100 field: 1, second indicator, ‡a <*a/*n1>,
			$value .= "1{$this->secondIndicator} {$this->doubleDagger}a{$n1}, ";
			
			# Classify *n2 field
			$value = $this->classifyN2Field ($path, $value, $n2);
			
		} else {
			
			# Classify *n1 field
			$value = $this->classifyN1Field ($path, $value, $n1);
		}
		
		# Return the value
		return $value;
	}
	
	
	# Function to classify *n1 field
	private function classifyN1Field ($path, $value, $n1)
	{
		# Start the value for this section
		$value = '';
		
		# Is the *n1 exactly equal to a set of specific strings?
		$strings = array (
			'other members of the expedition',
			'others',	// E.g. /records/8988/ (test #86)
		);
		if (application::iin_array ($n1, $strings)) {
			
			# If yes, no 100 field (or any 1XX field) required
			return false;	// Resets $value
		}
		
		# Is the *n1 exactly equal to a set of specific strings? E.g. /records/1394/ (test #123)
		$strings = array (
			'-',
			'Anon',
			'Anon.',
			'[Anon.]',
			'[n.p.]',
			'Unknown',
		);
		if (application::iin_array ($n1, $strings)) {
			
			# Add to 100 field
			$value .= "0{$this->secondIndicator} {$this->doubleDagger}aAnonymous";
			
			# GO TO: Classify *ad Field
			$value = $this->classifyAdField ($path, $value);
			
			# End
			return $value;
		}
		
		# Is the *n1 exactly equal to any of the names in the 'Name in direct order' tab? E.g. /records/181460/ (test #124)
		$strings = $this->entitiesToUtf8List ($this->lookups['namesInDirectOrder']);
		if (in_array ($n1, $strings)) {
			
			# Add to 100 field
			$value .= "0{$this->secondIndicator} {$this->doubleDagger}a" . $this->spaceOutInitials ($n1);	// Spacing-out needed in e.g. /records/213499/ (test #125)
			
			# Classify *nd Field
			$value = $this->classifyNdField ($path, $value);
			
			# End
			return $value;
		}
		
		# Is the *n1 exactly equal to any of the names in the 'Surname only' tab? E.g. /records/111558/ (test #126), /records/3904/ (test #127) which has HTML entities
		$surnameOnly = $this->entitiesToUtf8List ($this->lookups['surnameOnly']);
		if (in_array ($n1, $surnameOnly)) {
			
			# Add to 100 field
			$value .= "1{$this->secondIndicator} {$this->doubleDagger}a{$n1}";
			
			# Classify *nd Field
			$value = $this->classifyNdField ($path, $value);
			
			# End
			return $value;
		}
		
		# Explicitly throw away the so-far generated value
		$value = false;
		
		# Is the *n1 a conference? E.g. /records/50035/ (test #128)
		if ($this->isConference ($n1)) {
			
			# Mutate to 111/711 field instead of 100/700 field
			$value = $this->generateX11 ($path);
			
		} else {
			
			# Mutate to 110/710 field instead of 100/700 field
			$value = $this->generateX10 ($path);
		}
		
		# Return the overwritten value
		return $value;
	}
	
	
	# Helper function to determine if an *n1 is conference-like, e.g. /records/50035/ (test #128)
	private function isConference ($n1)
	{
		# Does the *n1 contain any of the following specific strings?
		$strings = array (
			'colloque',
			'colloquy',
			'conference',		// /records/50035/ (test #128)
			'congr&eacute;s',
			'congr&egrave;s',	// /records/8728/ (test #129)
			'congreso',
			'congress', // but NOT 'United States' - see below, including tests
			'konferent',	// Originally 'konferentsiya' but that is the pre-transliteration value; checked that this does not create mistaken hits; e.g. /records/32818/ (test #130)
			'konferenzen',
			'inqua',
			'polartech',
			'symposium',
			'tagung',
		);
		$strings = $this->entitiesToUtf8List ($strings);
		
		# Search for a match
		foreach ($strings as $string) {
			if (substr_count (strtolower ($n1), strtolower ($string))) {
				if (($string == 'congress') && (substr_count (strtolower ($n1), strtolower ('United States')))) {continue;}		// Whitelist this one; e.g. /records/55763/ (test #131) and /records/1912/ (test #132)
				
				# Match is found
				return true;
			}
		}
		
		# No match found
		return false;
	}
	
	
	# Function to generate a 110/710 field
	private function generateX10 ($path)
	{
		# Assume 110/710 by default
		$this->field += 10;		// 100->110, 700->710
		
		# Start the value for this section
		$value = '';
		
		# Look at the first or only *doc/*ag/*a OR *art/*ag/*a
		$n1 = $this->marcConversion->xPathValue ($this->xml, $path . '/n1');
		
		# Does the *a/*n1 contain '. ' (i.e. full stop followed by a space)?
		# Is the *n1 exactly equal to one of the names listed in the 'Full Stop Space Exceptions' tab? (test #94)
		if (substr_count ($n1, '. ') && !in_array ($n1, $this->lookups['fullStopExceptionsList'])) {
			
			# Add to 110 field: 2# ‡a <*a/*n1 [portion up to and including first full stop]> ‡b <*a/*n1 [everything after first full stop]>; e.g. /records/12195/ (test #93); e.g. /records/127474/ (test #94), /records/1261/
			$n1Components = explode ('.', $n1, 2);
			$value .= "2# {$this->doubleDagger}a{$n1Components[0]}.{$this->doubleDagger}b{$n1Components[1]}";
			
		} else {
			
			# Add to 110 field: 2# ‡a <*a/*n1>; e.g. /records/127474/ (test #94)
			$value .= "2# {$this->doubleDagger}a{$n1}";
		}
		
		# GO TO: Classify *nd Field
		$value = $this->classifyNdField ($path, $value);
		
		# Return the value
		return $value;
	}
	
	
	# Function to generate a 111/711 field
	private function generateX11 ($path)
	{
		# Assume 111/711 by default
		$this->field += 11;		// 100->111, 700->711
		
		# Start the value for this section
		$value = '';
		
		# Look at the first or only *doc/*ag/*a OR *art/*ag/*a
		$n1 = $this->marcConversion->xPathValue ($this->xml, $path . '/n1');
		
		# Parse the conference name
		$value = $this->parseConferenceTitle ($n1);
		
		# Classify *nd field
		$value = $this->classifyNdField ($path, $value);
		
		# Return the value
		return $value;
	}
	
	
	# Function to parse a conference title; see: http://www.loc.gov/marc/bibliographic/bd111.html
	private function parseConferenceTitle ($n1)
	{
		# Convert separator used in the data from , to ;
		$n1 = str_replace (', ', '; ', $n1);
		
		# Revert real commas that are not separators
		$whitelistStrings = array (
			// Present in meeting name:
			'Aerosols, Condensation',
			'Mass-Balance, Fluctuations',
			// Present in Location of meeting:
			'Washington, D.C.',
			'Edmonton, Alberta',			// /records/55264/ (test #137)
			'Yakutsk, Siberia, U.S.S.R',
		);
		$replacements = array ();
		foreach ($whitelistStrings as $whitelistString) {
			$find = str_replace (', ', '; ', $whitelistString);
			$replacements[$find] = $whitelistString;	// e.g. 'Washington; D.C.' => 'Washington, D.C.'
		}
		$n1 = strtr ($n1, $replacements);
		
		# Explode the components
		$conferenceAttributes = explode ('; ', $n1);
		
		# Start the value for this section, which is $a<conferencename>
		$value = "2# {$this->doubleDagger}a" . $conferenceAttributes[0];
		
		# Assemble according to number of parts
		$totalParts = count ($conferenceAttributes);
		switch ($totalParts) {
			
			# Simple conference name; e.g. 'Arctic Science Conference'
			case 1:
				// No addition; e.g. /records/173340/ (test #133)
				break;
				
			# Conference and date; e.g. 'Symposium on Antarctic Resources, 1978' /records/57564/ (test #134)
			case 2:
				$value .= "{$this->doubleDagger}d({$conferenceAttributes[1]})";	// Note no space before $d, e.g. /records/57564/ (test #560)
				break;
				
			# Conference, date and location; e.g. 'Conference on Antarctica, Washington, D.C., 1959' /records/32965/ (test #135)
			case 3:
				$value .= "{$this->doubleDagger}d({$conferenceAttributes[2]} :{$this->doubleDagger}c{$conferenceAttributes[1]})";
				break;
				
			# Conference, number, date and location; e.g. 'International Conference on Permafrost, 2nd, Yakutsk, Siberia, U.S.S.R, 1973' /records/51434/ (test #136)
			case 4:
				$value .= " {$this->doubleDagger}n({$conferenceAttributes[1]} :{$this->doubleDagger}d{$conferenceAttributes[3]} :{$this->doubleDagger}c{$conferenceAttributes[2]})";
				break;
		}
		
		# Return the value
		return $value;
	}
	
	
	# Function to classify *n2 field
	private function classifyN2Field ($path, $value, $n2)
	{
		# Is the *n2 exactly equal to a set of specific names?
		$names = array (
			'David B. (David Bruce)',			// /records/170179/ (test #139)
			'H. (Herv&eacute;)',				// /records/4366/ (test #140)
			'H. (Hippolyte)',
			'K.V. (Konstantin Viktorovich)',
			'L. (Letterio)',
			'M. M. (Marilyn M.)',
			'O. (Osmund)',
			'R.D. (Reginald D.)',
			'R.D. (Robert D.)',
			'V. C. (Vanessa C.)',
		);
		$names = $this->entitiesToUtf8List ($names);
		if (in_array ($n2, $names)) {
			
			# Add to 100 field: <*a/*n2 [portion before brackets]> ‡q<*a/*n2 [portion in brackets, including brackets]>
			preg_match ('/^(.+) (\(.+\))$/', $n2, $matches);
			$n2FieldValue  = $matches[1];
			$n2FieldValue .= " {$this->doubleDagger}q" . $matches[2];
			
		} else {
			
			# Add to 100 field: <*a/*n2>; e.g. /records/1296/ (test #138)
			$n2FieldValue = $n2;
		}
		
		# Any initials in the $a subfield should be separated by a space (test #91); e.g. /records/1296/ ; note that 245 $c does not seem to do the same: http://www.loc.gov/marc/bibliographic/bd245.html (test #92)
		$n2FieldValue = $this->spaceOutInitials ($n2FieldValue);	// Spacing-out needed in e.g. /records/1296/ (test #91)
		
		# Add the value
		$value .= $n2FieldValue;
		
		# Classify *nd Field
		$value = $this->classifyNdField ($path, $value);
		
		# Return the value
		return $value;
	}
	
	
	# Function to expand initials to add spaces (test #91); note that 245 $c requires the opposite - see spaceOutInitials() in generate245 (test #92)
	private function spaceOutInitials ($string)
	{
		# Any initials should be separated by a space; e.g. /records/1296/
		# This is tolerant of transliterated Cyrillic values (test #95), e.g. /records/175507/ or (old example) /records/194996/ which has "Ye.V." to become "E.V."
		$regexp = '/\b([^ ]{1,2})(\.)([^ ]{1,2})/u';	// Unicode flag needed given e.g. Polish initial in /records/201319/ (test #96) (and therefore parent record /records/44492/ (test #97))
		while (preg_match ($regexp, $string)) {
			$string = preg_replace ($regexp, '\1\2 \3', $string);
		}
		
		# Return the amended string
		return $string;
	}
	
	
	# Function to classify *nd field
	private function classifyNdField ($path, $value)
	{
		# Does the *a contain a *nd? E.g. /records/1221/ (test #141)
		$nd = $this->marcConversion->xPathValue ($this->xml, $path . '/nd');
		if (!strlen ($nd)) {
			
			# If no, GO TO: Classify *ad Field; e.g. /records/1201/ (test #142)
			$value = $this->classifyAdField ($path, $value);
			
			# Return the value
			return $value;
		}
		
		# If present, strip out leading '\v' and trailing '\n' italics; e.g. /records/45578/ (test #98)
		$nd = strip_tags ($nd);
		
		# Is the *nd exactly equal to set of specific strings?
		$strings = array (
			'Sr SGM'				=> ",{$this->doubleDagger}cSr, {$this->doubleDagger}uSGM",
			'Lord, 1920-1999'		=> ",{$this->doubleDagger}cLord,{$this->doubleDagger}d1920-1999",	// Note no space before $d, e.g. /records/172094/ (test #559)
			'Rev., O.M.I.'			=> ",{$this->doubleDagger}cRev.,{$this->doubleDagger}uO.M.I.",
			'I, Prince of Monaco'	=> ",{$this->doubleDagger}b I,{$this->doubleDagger}cPrince of Monaco",		// E.g. /records/165177/ (test #99)
			'Baron, 1880-1957'		=> ",{$this->doubleDagger}cBaron,{$this->doubleDagger}d1880-1957",
		);
		if (array_key_exists ($nd, $strings)) {
			
			# Classify multiple value *nd field
			$value = $this->classifyMultipleValueNdField ($value, $nd, $strings);
			
		} else {
			
			# Classify single value *nd field
			$value = $this->classifySingleValueNdField ($value, $nd);
		}
		
		# GO TO: Classify *ad Field
		$value = $this->classifyAdField ($path, $value);
		
		# Return the value
		return $value;
	}
	
	
	# Function to classify multiple value *nd field
	private function classifyMultipleValueNdField ($value, $nd, $strings)
	{
		# Add the looked-up value
		$value .= $strings[$nd];
		
		# Return the value
		return $value;
	}
	
	
	# Function to classify single value *nd field
	private function classifySingleValueNdField ($value, $nd)
	{
		# Delegate
		return $value = $this->_classifySingleValueNdOrAdField ($value, $nd, false);
	}
	
	
	# Helper function to classify a single value *nd or *ad field
	private function _classifySingleValueNdOrAdField ($value, $fieldValue, $checkDateList)
	{
		# Does the value of the $fieldValue appear on the Prefix list?
		# Does the value of the $fieldValue appear on the Suffix list?
		# Does the value of the $fieldValue appear on the Between *n1 and *n2 list?
		$prefixes = $this->entitiesToUtf8List ($this->lookups['prefixes']);		// E.g. /records/1201/ (test #142), /records/53959/ (test #143)
		$suffixes = $this->entitiesToUtf8List ($this->lookups['suffixes']);		// E.g. /records/23362/ (test #144)
		$betweenN1AndN2 = $this->entitiesToUtf8List ($this->lookups['betweenN1AndN2']);		// E.g. /records/3180/ (test #145)
		if (in_array ($fieldValue, $prefixes) || in_array ($fieldValue, $suffixes) || in_array ($fieldValue, $betweenN1AndN2)) {
			$value .= ",{$this->doubleDagger}c{$fieldValue}";
			return $value;
		}
		
		# Check the date list if required
		if ($checkDateList) {
			
			# Does the value of the $fieldValue appear on the Date list? E.g. /records/6575/ (test #100)
			if (in_array ($fieldValue, $this->lookups['dateList'])) {
				$value .= ",{$this->doubleDagger}d {$fieldValue}";		// Avoid space after comma to avoid Bibcheck error "100: Subfield d must be preceded by a comma" in /records/6575/ (test #101)
				return $value;
			}
		}
		
		# Do one or more words or phrases in the $fieldValue appear in the Relator terms list? E.g. /records/10004/ (test #147), /records/181142/ (test #146)
		if ($relatorTermsEField = $this->relatorTermsEField ($fieldValue)) {
			$value .= $relatorTermsEField;
			return $value;
		}
		
		# Does the value of the $fieldValue appear on the Misc. list? E.g. /records/1218/ (test #148)
		if (in_array ($fieldValue, $this->lookups['miscList'])) {
			$value  = $this->marcConversion->macro_dotEnd ($value, $extendedCharacterList = '.?!');		// "700: Subfield g must be preceded by a full stop, question mark or exclamation mark." (test #148)
			$value .= "{$this->doubleDagger}g ({$fieldValue})";
			return $value;
		}
		
		# Does the value of the $fieldValue appear on the Affiliation list? E.g. /records/19171/ (test #150), /records/18045/ (test #151)
		if (in_array ($fieldValue, $this->lookups['affiliationList'])) {
			$value .= ", {$this->doubleDagger}u{$fieldValue}";
			return $value;
		}
		
		# No change
		return $value;
	}
	
	
	# Function to get the relator terms
	private function getRelatorTerms ($valueForPrefiltering)
	{
		# Process the raw relator terms list into value => replacement; these have already been checked for uniqueness when replaced
		$relatorTerms = array ();
		foreach ($this->lookups['relatorTerms'] as $parent => $children) {
			foreach ($children as $child) {
				
				# Deal with pre-filters, which contain // in the terms list
				if (preg_match ('|(.+)//(.+):(.+)|', $child, $matches)) {
					$valueForPrefiltering = strtolower ($valueForPrefiltering);
					$matches[3] = strtolower ($matches[3]);
					
					# Determine whether to keep the entry in place
					switch ($matches[2]) {
						
						# Only - the entire string must match the specified value; e.g. "with//ONLY:with" will be ignored if the $valueForPrefiltering was "with foo"; e.g. /records/122529/ (test #152), /records/1253/ (test #153)
						case 'ONLY':
							$keep = ($matches[3] == $valueForPrefiltering);
							break;
							
						# Not - the string must not contain the specified value; e.g. "director//NOT:art director" will be ignored if the $valueForPrefiltering was "art director"; e.g. // /records/44786/ (test #154), /records/24674/ (test #155)
						case 'NOT':
							$keep = (!substr_count ($valueForPrefiltering, $matches[3]));	// Partial match, e.g. 'revised//NOT:revised translation' means that "revised translation" (matches[3]) should not match *role="Revised translation by" in e.g. /records/24674/ (test #155)
							break;
							
						# Requires - the overall record must have the specified XPath entry; e.g. // /records/139689/ (test #156), /records/101462/ (test #157)
						case 'REQUIRES':
							$keep = ($this->marcConversion->xPathValue ($this->xml, $matches[3]));
							break;
					}
					
					# If a match is supported, substitute the key; otherwise remove the entry - which will mean it never matches in the later comparison stages
					if ($keep) {
						// echo "DEBUG: Changing {$child} to {$matches[1]}<br />";
						$child = $matches[1];
					} else {
						// echo "DEBUG: Removing {$child}<br />";
						continue;	// Skip this term, i.e. do not register the value
					}
				}
				
				# Register the value
				$relatorTerms[$child] = $parent;
			}
		}
		
		// application::dumpData ($relatorTerms);
		
		# Convert entities; e.g. /records/10004/ (test #147)
		$relatorTerms = $this->entitiesToUtf8List ($relatorTerms);
		
		# Return the list
		return $relatorTerms;
	}
	
	
	# Function to convert entities in a list (e.g. &eacute => é) to unicode
	private function entitiesToUtf8List ($listRaw)
	{
		# Loop through each item in the list
		$list = array ();
		foreach ($listRaw as $key => $value) {
			$key   = html_entity_decode ($key);
			$value = html_entity_decode ($value);
			$list[$key] = $value;
		}
		
		# Return the amended list
		return $list;
	}
	
	
	# Function to classify *ad field
	private function classifyAdField ($path, $value)
	{
		# If running in a 7** context, and going through *e/*n, trigger the "Classify *e Field" subroutine check; e.g. /records/147053/ (test #158)
		if (!$this->context1xx) {
			if (preg_match ('|^/\*/e|', $path)) {
				$role = $this->marcConversion->xPathValue ($this->xml, $path . '/preceding-sibling::role');
				$value .= $this->relatorTermsEField ($role);
				return $value;
			}
		}
		
		# Look at the first or only *doc/*ag OR *art/*ag; e.g. /records/1165/ (test #102)
		$ad = $this->marcConversion->xPathValue ($this->xml, $path . '/following-sibling::ad');
		if (strlen ($ad)) {
			$value = $this->_classifySingleValueNdOrAdField ($value, $ad, true);
		}
		
		# GO TO: Add *aff Field
		$value = $this->addAffField ($path, $value);
		
		# Return the value
		return $value;
	}
	
	
	# Function to add *aff field
	private function addAffField ($path, $value)
	{
		# Is there a *aff in *doc/*ag OR *art/*ag?
		# If so, Add to 100 field; e.g. /records/121449/ (test #103)
		$aff = $this->marcConversion->xPathValue ($this->xml, $path . '/following-sibling::aff');
		if (strlen ($aff)) {
			$value .= ", {$this->doubleDagger}u{$aff}";
		}
		
		# Does the record contain a *doc/*e/*n/*n1 OR *art/*e/*n/*n1 that is equal to 'the author'?
		# E.g. *e/*role containing "Illustrated and translated by" and *n1 "the author"; e.g. /records/147053/ (test #159)
		$n1 = $this->marcConversion->xPathValue ($this->xml, '//e/n/n1');
		if ($n1 == 'the author') {
			$role = $this->marcConversion->xPathValue ($this->xml, '//e/role');	// Obtain the $role, having determined that *n1 matches "the author"
			$value .= $this->relatorTermsEField ($role);
		}
		
		# Does 100 field currently end with a punctuation mark? E.g. /records/46177/ (test #160), /records/46175/ (test #161)
		if (!preg_match ('/[.)\]\-,;:]$/', $value)) {	// http://stackoverflow.com/a/5484178 says that only ^-]\ need to be escaped inside a character class
			$value .= '.';
		}
		
		# Does 100 field include either or both of the following specified relator terms
		if ($this->context1xx) {
			if (substr_count ($value, "{$this->doubleDagger}eeditor") || substr_count ($value, "{$this->doubleDagger}ecompiler")) {
				
				# Change 1XX field to 7XX field: all indicators, fields and subfields remain the same; e.g. /records/31105/ (test #104), /records/4012/ (test #162)
				$this->field += 600;		// 100->700, 110->710
			}
		}
		
		# Return the value
		return $value;
	}
	
	
	# Function to obtain the relator term as a $e field
	private function relatorTermsEField ($role)
	{
		# Start a value
		$value = '';
		
		# Add to 100 field:
		# For each matching word / phrase, add:
		$relatorTerms = $this->getRelatorTerms ($role);
		$replacements = array ();
		foreach ($relatorTerms as $relatorTerm => $replacement) {
			
			# Check for an exact match (i.e. right-hand-side of relator terms list), e.g. "editor"; e.g. /records/113955/ (test #105)
			if (strtolower ($role) == strtolower ($replacement)) {
				$replacements[$relatorTerm] = $replacement;
				continue;
			}
			
			# Also check for a substring match, e.g. "Translated from the Icelandic by" would match "translator"; e.g. /records/1639/ (test #106)
			if (substr_count (strtolower ($role), strtolower ($relatorTerm))) {
				$replacements[$relatorTerm] = $replacement;
			}
		}
		
		# Assemble the string if there are replacements
		if ($replacements) {
			$replacements = array_unique ($replacements);
			$subfieldCode = (in_array ($this->field, array (111, 711)) ? 'j' : 'e');	// X11 have Relator Term in $j; see: http://www.loc.gov/marc/bibliographic/bd711.html
			foreach ($replacements as $replacement) {
				$value .= ",{$this->doubleDagger}{$subfieldCode}{$replacement}";	// No space before $e, whether the first or multiple, as shown at https://www.loc.gov/marc/bibliographic/bd700.html e.g. /records/2295/ (tests #121, #122), 
			}
		}
		
		# Return the value
		return $value;
	}
	
	
	# List of names in direct order
	private function namesInDirectOrder ()
	{
		return array (
			'A.Z.',
			'Adam av Bremen',
			'Adam of Bremen',		// /records/127781/
			'Adamus Bremensis',
			'Albert',
			'Alman',
			'Anne',
			'Author of "Harry Lawton\'s adventures"',
			'B.M.C.',
			'B.R.',
			'Bonxie',
			'C.D.O.',
			'C.E.L.',
			'C.L.',
			'Captain "Quaker" Oates',
			'Charles',
			'Copper River Joe',
			'Dalan',
			'DJ Spooky',
			'\'Drawer D\'',
			'Dufferin and Ava',
			'E.L.H.',		// /records/213499/
			'E.P.',
			'Earl of Carnarvon',
			'Earl of Ellesmere',
			'Earl of Southesk',
			'El Presidente de la Nacion Argentina',
			'F.W.I.',
			'[G.F.]',
			'G.F.',
			'George-Day',
			'Georgia',
			'Grant',
			'H.A.',
			'H.B.',
			'H.J.I.',
			'H.L.L.',
			'H.W.B.',
			'Haakon',
			'Heikk&aacute;-Gustu',
			'Herg&eacute;',
			'Ignatius',
			'Innocent of Kamchatka',
			'Inspector and doctor',
			'J.K.',
			'J.M.W.',
			'J.W.G.',
			'Jorsep',
			'K.B.',
			'L.Fr.',
			'Luda',
			'M.A.A.',
			'M.B.H.',
			'M.Kh.',
			'M.M.',
			'M.S.F.',
			'M.V.C.',
			'M.W.R.',
			'Malak',
			'Markoosie',
			'Matthew',
			'Mr. Penguin',
			'N.B.K.',
			'O.A.',
			'P.L.',
			'Philip',
			'Qian-Jin',
			'R.E.P.',
			'R.N.R.B.',
			'S.T.',
			'S.T.A.M.',
			'Sis-hu-lk',
			'Torwil',
			'Ungaralak',
			'W.E.C.',
			'W.E.R.',
			'W.J.',
			'W.S.B.',
			'Xenophon',
			'Yambo',
		);
	}
	
	
	# List of surname only
	private function surnameOnly ()
	{
		return array (
			'Aberdare',
			'Arkturus',
			'Baird',
			'Beebe',
			'Bellebon',
			'Boulangier',
			'Bourne',
			'Brightman',
			'Brorsen',
			'Bruchhausen',
			'Chisholm',
			'Christison',
			'de Bieberstein',
			'de Met',
			'Delisle',
			'di Georgia',	// /records/111558/ (test #126)
			'Dumas',
			'Eitel',
			'Ellyay',
			'Fabricius',
			'Fabritius',
			'Gordeyev',
			'Gronen',
			'Greene',
			'Haetta',
			'Hammelmann',
			'Harold',
			'Hauge',
			'Henrik',
			'Howard',
			'Hue',
			'Husker',
			'Ishkov',
			'Ivanov',
			'Jackson',
			'Jansen',
			'Joly',
			'Jones',
			'Kaunhowen',
			'Kerr',
			'Kretschmer',
			'Krokisius',
			'Krusenstern',
			'Le Gentil',
			'Le Monnier',
			'Lindeman',
			'Lloyd',
			'Mackinnon',
			'Mackintosh',
			'Mayne',
			'McDonald',
			'Michell',
			'Microft',
			'M&uuml;ller',
			'Naidu',
			'Noummeline',
			'O\'Connell',
			'Oliver',
			'Palmer',
			'Pearce',
			'Pickwick',
			'Puff',
			'Redgrave',
			'Repsold',
			'Rice',
			'Rivinus',
			'Rosinha',
			'Rybin',
			'Samter',
			'Sharp',
			'Shul\'ts',
			'Siden',
			'S&ouml;derbergh',	// /records/3904/ (test #127)
			'Strauch',
			'Sunman',
			'Tabarin',
			'Thorpe',
			'Tsiklinsky',
			'Vecheri',
			'Vismara',
			'Walther',
			'Warden',
			'Wareham',
			'Wiedemann',
			'Zach',
			'Zain-Azraai',
			'Zero',
			'Zeune',
		);
	}
	
	
	# List of prefixes; keep in sync with generate245::prefixes()
	private function prefixes ()
	{
		return array (
			'Commander',
			'Hon',
			'Sir',							// /records/1201/ (test #142)
			'Abb&eacute;',
			'Admiral',
			'Admiral Lord',
			'Admiral of the Fleet, Sir',
			'Admiral Sir',
			'Admiral, Sir',
			'Amiral',
			'Archdeacon',
			'Archpriest',
			'Baron',
			'Baroness',
			'Bishop',
			'Brigadier',
			'Brigadier-General',
			'Capit&aacute;n',				// /records/53959/ (test #143)
			'Capitan',
			'Capt.',
			'Captain',
			'Cdr',
			'Cdr.',
			'Chevalier',
			'Chief Justice',
			'Chief-Justice',
			'Cmdr',
			'Col.',
			'Colonel',
			'Commandant',
			'Commandante',
			'Commander',
			'Commodore',
			'Conte',
			'Contre-Amiral',
			'Coronel',
			'Count',
			'Cst.',
			'Doctor',
			'Dom',
			'Dr',
			'Dr.',
			'Duc',
			'Duke',
			'Earl',
			'Ensign',
			'Father',
			'Fr',
			'General',
			'General Sir',
			'General, Count',
			'General, Sir',
			'Graf',
			'Hon.',
			'Ing.',
			'Kapit&auml;n',
			'Kommand&oslash;rkaptajn',	// /records/19668/ (test #212)
			'Korv. Kapt.',
			'L\'Abb&eacute;',
			'l\'Ain&eacute;',
			'l\'amiral',
			'Lady',
			'Le Comte',
			'Lieut',
			'Lieut.',
			'Lieutenant Colonel',
			'Lieutenant General',
			'Lord',
			'Lt',
			'Lt Cdr',
			'Lt.',
			'Lt. Col.',
			'Maj. Gen.',
			'Major',
			'Major General',
			'Marquess',
			'Metropolitan',
			'Mme',
			'Mme.',
			'Mrs',
			'Mrs.',
			'Prince',
			'Professor',
			'Protoierey',
			'Rear Admiral',
			'Rear-Admiral',
			'Rev',
			'Rev.',
			'Rev. Dr.',
			'Rev\'d',
			'Revd',
			'Reverend',
			'Right Hon. Lord',
			'Ritter',
			'Rt. Hon.',
			'Sergeant',
			'Sir',
			'Sister',
			'Sr',
			'Sr.',
			'The Venerable',
			'Vice Admiral Sir',
			'Vice-Admiral',
			'Viscount',
			'Vlkh.',
		);
	}
	
	
	# List of suffixes
	private function suffixes ()
	{
		return array (
			'10th Baron Strabolgi',
			'1st Baron',
			'1st Baron Mountevans',
			'1st Baron Moyne',
			'1st Baron Tweedsmuir',
			'1st Marquis of Dufferin and Ava',
			'2nd Baron',
			'2nd Baron Tweedsmuir',				// /records/23362/ (test #144)
			'4th Baron',
			'Agent-General for Victoria',
			'Archbishop of Uppsala',
			'Baron Ashburton',
			'Baroness Tweedsmuir',
			'Bishop of Exeter',
			'Bishop of Keewatin',
			'Bishop of Kingston',
			'Bishop of Tasmania',
			'Capt. US Navy (Ret)',
			'Captaine de fr&eacute;gate',
			'Col USAF (Ret.) Lt',
			'Comptroller and Auditor General',
			'D.D.S.',
			'D&eacute;put&eacute;-Maire des Sables-d\'Olonne',
			'Duchess of Bedford',
			'Earl of Northbrook',
			'Earl of Southesk',
			'Governor of Falkland Islands',
			'H.E. Ambassador',
			'II',
			'II.',
			'III',
			'III.',
			'IV',
			'Jnr',
			'Jr',
			'Jr, MD',
			'Jr.',
			'Junior',
			'K.C.B. K.C.',
			'Kapt. zur See',
			'King of Norway',
			'Lord Kennet',
			'Lord of Roberval',
			'Lt. Colonel, USAF-Retired',
			'M.D.',
			'MA, Phd',
			'Major, D.S.O.',
			'Marquess of Zetland',
			'O.M.',
			'Prince, consort of Elizabeth II, Queen of Great Britain',
			'Prince, consort of Margrethe II, Queen of Denmark',
			'Prince di Cannino',
			'Prince of Monaco',
			'Prince San Donato',
			'Princess Royal, daughter of Elizabeth II, Queen of Great Britain',
			'Rear Admiral, USN (Ret.)',
			'Rear Admiral, USN (Ret)',
			'S.J.',
			'SJ',
			'Sr',
			'the Apostle, Saint',
			'Third Baron',
		);
	}
	
	
	# List of between *n1 and *n2
	private function betweenN1AndN2 ()
	{
		return array (
			'Freiherr von',		// /records/3180/ (test #145)
		);
	}
	
	
	# Relator terms
	private function relatorTerms ()
	{
		return array (
			
			'abridger' => array (
				'abridged',
				'reduced',
			),
			
			'adapter' => array (
				'adaptation',
				'adapt&eacute;',
				'adapted',
				'l\'adaptation',
			),
			
			'analyst' => array (
				'analysis',
			),
			
			'annotator' => array (
				'annotated',
				'annot&eacute;',
				'annot&eacute;e',
			),
			
			'arranger' => array (
				'arranged',
				'arrangements',
				'arranger',
			),
			
			'art director' => array (
				'art direction',
			),
			
			'artist' => array (
				'art',
				'artist',
				'artwork',
				'designs',
				'dessin',
				'drawings',
				'graphics',
				'images',
				'paintings',
				'sketches',
				'tegninger',
				'watercolors',
				'water colours',
			),
			
			'author' => array (
				'author//NOT:author\'s',
				'bokspill',
				'sc&eacute;nario',
				'text',
				'texte',
				'textes',
				'writer',
				'written',
			),
			
			'cartographer' => array (
				'cartography',
				'map',
				'maps',
			),
			
			'cinematographer' => array (
				'camera',
				'cameraman',
				'cinematography',
				'filmed',
			),
			
			'collector' => array (
				'collected',
				'collector',
			),
			
			'colorist' => array (
				'couleur',
			),
			
			'commentator for written text' => array (
				'commentaries',
				'commentary',
				'commentator',
				'commentators',
				'commented',
				'comments',
				'erl&auml;utert',
				'kommentar',
				'kommentiert',
			),
			
			'compiler' => array (
				'chosen',
				'collator',
				'comp.',
				'compilation',
				'compiled',
				'compiler',
				'compilers',
				'dargeboten',
				'selected',
				'zusammengestellt',
			),
			
			'composer' => array (
				'composer',
				'music',
				'oper',
			),
			
			'conductor' => array (
				'conducted',
			),
			
			'consultant' => array (
				'consultant',
				'consulting',
			),
			
			'contributor' => array (
				'account',
				'additional material',
				'additional work',
				'additions',
				'afterword',
				'anhang',
				'appendices',
				'appendix',
				'article',
				'art&iacute;culos',
				'asesoramiento',
				'assessment',
				'assistance',
				'association with',
				'avant-propos',
				'baksats',
				'beitrag',
				'beitr&auml;gen',
				'berechnet',
				'bericht',
				'bibliography',
				'biography',
				'captain\'s logs',
				'captions',
				'chapter',
				'chapters',
				'chronology',
				'colaborador',
				'collaboration',
				'collaborations',
				'completed',
				'conclusion',
				'concours scientifique',
				'consultation',
				'contributary (?)',
				'contributing',
				'contribution',
				'contributions',
				'contributors',
				'data',
				'discussed',
				'doaimmahan',
				'documented',
				'efterskrift',
				'epilogue',
				'essay',
				'essays',
				'estudio preliminar',
				'extracts',
				'footnotes',
				'foreword',
				'forewords',
				'forord',
				'forsats',
				'glossary',
				'index',
				'indices',
				'inleiding',
				'insects',
				'interview with',
				'introducci&oacute;n',
				'introduced',
				'introducere',
				'introduction',
				'introductions',
				'introductory',
				'mitarbeit',
				'mithilfe',
				'mitwirkung',
				'narration written',
				'narratives',
				'notas',
				'note',
				'notes',
				'observations',
				'overview',
				'parallel account',
				'participation',
				'postface',
				'preamble',
				'preface',
				'pr&eacute;face',
				'pr&eacute;fac&eacute;',
				'prefaced',
				'pr&eacute;faces',
				'prefa??',
				'prefatory',
				'prefazione',
				'pr&eacute;sent&eacute;',
				'prologo',
				'pr&ouml;logo',
				'readings written',
				'remarks',
				'salutation',
				'special thanks',
				'sunto',
				'superintended',
				'supplemental material',
				'supplementary material',
				'table',
				'tabulation',
				'technical advisor',
				'tema',
				'texten',
				'together with',
				'updated',
				'vignettes',
				'voorwoord',
				'vorgemerkungen',
				'vorwort',
				'with//ONLY:with',	// Full match just for 'with'; e.g. /records/122529/ (test #152), /records/1253/ (test #153)
				'zusammenarbeit',
			),
			
			'creator' => array (
				'created',
			),
			
			'curator' => array (
				'curator',
				'curators',
			),
			
			'designer' => array (
				'design',
				'designed',
				'gestaltung',
				'layout',
			),
			
			'director' => array (
				'directed',
				'director//NOT:art director',
				'directors',
				'sous la direction',
				'under the direction',
			),
			
			'editor' => array (
				'a cura',
				'&aacute;tdolgozta',	// /records/10004/ (test #147)
				'bearbeitet',
				'co-editor',			// /records/181142/ (test #146)
				'co-editors',
				'corrected',
				'ed.',
				'edit&eacute;',
				'edited',
				'editing',
				'editor//NOT:film editor',
				'editorial',
				'editor-in-chief',
				'editor\'s',
				'editors',
				'editors\'',
				'eds.',
				'herausgegeben',
				'prepared',
				'red.',
				'redactie',
				'redakcyjne',
				'redaksjon',
				'redaktor',
				'redaktsyey',
				'redigert',
				'revised//NOT:revised translation',	// /records/44786/ (test #154), /records/24674/ (test #155)
				'revision',
			),
			
			'engraver' => array (
				'engravings',
			),
			
			'film editor' => array (
				'film editor',
			),
			
			'filmmaker' => array (
				'film made',
			),
			
			'illustrator' => array (
				'illustrasjoner',
				'illustrated',
				'illustration',
				'illustrationen',
				'illustrations',
				'illustrator',
				'illustr&eacute;',
				'illustrert',
				'plates',
				'textzeichnungen',
			),
			
			'lithographer' => array (
				'lithographs',
				'lithographed',
			),
			
			'narrator' => array (
				'narrated',
				'narration',
				'narrator',
				'presented//REQUIRES://form',	// /records/139689/ (test #156), /records/101462/ (test #157)
				'presenter',
				'read',
			),
			
			'organizer' => array (
				'organiser',
				'organisers',
				'organising',
			),
			
			'panelist' => array (
				'panelists',
			),
			
			'performer' => array (
				'klavier-auszug',
			),
			
			'photographer' => array (
				'fotograf&iacute;a',
				'fotografien',
				'foto\'s',
				'panoramas',
				'photo',
				'photograph',
				'photographed',
				'photographer',
				'photographers',
				'photographies',
				'photographs',
				'photography',
				'photos',
			),
			
			'printer' => array (
				'printed',
			),
			
			'producer' => array (
				'produced',
				'producer',
				'producers',
				'production',
			),
			
			'publisher' => array (
				'published',
				'publishers',
			),
			
			'recordist' => array (
				'recorded',
				'recorder',
			),
			
			'redaktor' => array (
				'redacted',
				'redigit',
			),
			
			'reporter' => array (
				'reporter',
				'reporters',
			),
			
			'researcher' => array (
				'research',
				'researched',
			),
			
			'restorationist' => array (
				'restored',
			),
			
			'reviewer' => array (
				'reviewer',
				'reviewers',
			),
			
			'scientific advisor' => array (
				'botanical advisor',
			),
			
			'secretary' => array (
				'secretary',
			),
			
			'singer' => array (
				'gesang',
				'singer',
				'soprano',
			),
			
			'sponsor' => array (
				'sponsor',
				'sponsors',
			),
			
			'transcriber' => array (
				'fortalt',
				'retranscribed',
				'telescript',
				'told',
				'transcribed',
				'transcriber',
				'transcription',
				'transcriptions',
			),
			
			'translator' => array (
				'aus dem',
				'de l\'anglais',
				'deutsch von',
				'english version',
				'norwegian edition ',
				'oversat',
				'oversatt',
				'&ouml;vers&auml;ttning',
				'prevedla',
				'przektad',
				'tradu&ccedil;&atilde;o',
				'traducere',
				'traducido',
				'traduction',
				'traduit',
				'traduzione',
				'trans.',
				'translated',
				'translation',
				'translations',
				'translator',
				'translators',
				'&uuml;bersetzt',
				'&uuml;bersetzung',
				'&uuml;bertragen',
				'vertaald',
			),
			
			'witness' => array (
				'observer',
				'observers',
			),
		);
	}
	
	
	# Misc. list
	private function miscList ()
	{
		return array (
			'pseud.',
			'Campsterianus',	// /records/1218/ (test #148)
			'Pseud',
			'pseudonym',
		);
	}
	
	
	# Affiliation list
	private function affiliationList ()
	{
		return array (
			'OMI',
			'O.M.I.',						// /records/19171/ (test #150), /records/18045/ (test #151)
			'SGM',
		);
	}
	
	
	# Date list
	private function dateList ()
	{
		return array (
			'1863-1945',	// /records/6575/ (test #100)
		);
	}
	
	
	# Full stop exceptions list (test #94)
	private function fullStopExceptionsList ()
	{
		return array (
			'Alpine (Double Glazing) Co. Ltd.',
			'Commander Chr. Christensen Whaling Museum',
			'D.F. Dickins and Associates Ltd',
			'[David T. Abercrombie Co.]',
			'[F. Ellis Brigham]',
			'F.F. Slaney & Company Limited',
			'Frederick A. Cook Society',
			'G.K. Yuill and Associates Ltd.',
			'H.J. Ruitenbeek Resource Consulting Ltd.',
			'Hubert P. Wenger Foundation',
			'[Istituto Geografico Polare "S. Zavatti"]',
			'J. Lauritzen Lines',
			'L.J. Cutler and Associates Pty Ltd',
			'[L.L. Bean, Inc.]',
			'L.S. Navigational Consulting Services Ltd.',
			'[M.R. Publishing]',
			'P.J. Usher Consulting Services',
			'[R. Burns Ltd.]',
			'R.M. Hardy & Associates Ltd',
			'R.M. Hardy and Associates Ltd',
			'Robert R. Nathan Associates',
			'Roland C. Bailey & Associates',
			'S.L. Ross Environmental Research Limited',
			"St. Andrew's University",
			'St. Helena. Government',
			'St. John Ambulance Association',
			"[St. Paul's Cathedral]",
			'St.Helena. Government',
			'Stephen R. Braund & Associates',
			'T.W. Beak Consultants Ltd.',
			'Terry G. Spragg & Associates',
			'Thorpe St. Andrew School',
			'W.F. Baird and Associates',
			'W.J. Francl & Associates',
			'Z.J. Loussac Public Library',
		);
	}
}

?>
