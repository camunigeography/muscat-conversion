<?php


#!# Improve efficiency in this class by creating properties (e.g. XML) instead of passing them around or looking-up several times


# Class to handle conversion of the data to MARC format
class marcConversion
{
	# Constructor
	public function __construct ($muscatConversion, $transliteration, $supportedReverseTransliterationLanguages, $mergeTypes, $ksStatusTokens, $locationCodes, $suppressionStatusKeyword, $suppressionScenarios)
	{
		# Create class property handles to the parent class
		$this->muscatConversion = $muscatConversion;
		$this->databaseConnection = $muscatConversion->databaseConnection;
		$this->settings = $muscatConversion->settings;
		$this->applicationRoot = $muscatConversion->applicationRoot;
		$this->baseUrl = $muscatConversion->baseUrl;
		
		# Create other handles
		$this->transliteration = $transliteration;
		$this->supportedReverseTransliterationLanguages = $supportedReverseTransliterationLanguages;
		$this->mergeTypes = $mergeTypes;
		$this->ksStatusTokens = $ksStatusTokens;
		$this->locationCodes = $locationCodes;
		$this->suppressionStatusKeyword = $suppressionStatusKeyword;
		$this->suppressionScenarios = $suppressionScenarios;
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
	}
	
	
	# Main entry point
	# NB XPath functions can have PHP modifications in them using php:functionString - may be useful in future http://www.sitepoint.com/php-dom-using-xpath/ http://cowburn.info/2009/10/23/php-funcs-xpath/
	public function convertToMarc ($marcParserDefinition, $recordXml, &$errorString = '', $mergeDefinition = array (), $mergeType = false, $mergeVoyagerId = false, $suppressReasons = false, &$marcPreMerge = NULL, &$sourceRegistry = array ())
	{
		# Ensure the error string is clean for each iteration
		$errorString = '';
		
		# Create fresh containers for 880 reciprocal links for this record
		$this->field880subfield6ReciprocalLinks = array ();		// This is indexed by the master field, ignoring any mutations within multilines
		$this->field880subfield6Index = 0;
		$this->field880subfield6FieldInstanceIndex = array ();
		
		# Ensure the second-pass record ID flag is clean; this is used for a second-pass arising from 773 processing where the host does not exist at time of processing
		$this->secondPassRecordId = NULL;
		
		# Create property handle
		$this->suppressReasons = $suppressReasons;
		
		# Ensure the line-by-line syntax is valid, extract macros, and construct a data structure representing the record
		if (!$datastructure = $this->convertToMarc_InitialiseDatastructure ($recordXml, $marcParserDefinition, $errorString)) {return false;}
		
		# End if not all macros are supported
		if (!$this->convertToMarc_MacrosAllSupported ($datastructure, $errorString)) {return false;}
		
		# Load the record as a valid XML object
		$xml = $this->loadXmlRecord ($recordXml);
		
		# Determine the record number, used by several macros
		$this->recordId = $this->xPathValue ($xml, '//q0');
		
		# Up-front, process author fields
		require_once ('generateAuthors.php');
		$languageModes = array_merge (array ('default'), array_keys ($this->supportedReverseTransliterationLanguages));		// Feed in the languages list, with 'default' as the first
		$generateAuthors = new generateAuthors ($this, $xml, $languageModes);
		$this->authorsFields = $generateAuthors->getValues ();
		
		# Up-front, look up the host record, if any
		$this->hostRecord = $this->lookupHostRecord ($xml);
		
		# Perform XPath replacements
		if (!$datastructure = $this->convertToMarc_PerformXpathReplacements ($datastructure, $xml, $errorString)) {return false;}
		
		# Expand vertically-repeatable fields
		if (!$datastructure = $this->convertToMarc_ExpandVerticallyRepeatableFields ($datastructure, $errorString)) {return false;}
		
		# Process the record
		$record = $this->convertToMarc_ProcessRecord ($datastructure, $errorString);
		
		# Determine the length, in bytes, which is the first five characters of the 000 (Leader), padded
		$bytes = mb_strlen ($record);
		$bytes = str_pad ($bytes, 5, '0', STR_PAD_LEFT);
		$record = preg_replace ('/^LDR (_____)/m', "LDR {$bytes}", $record);
		
		# If required, merge with an existing Voyager record, returning by reference the pre-merge record, and below returning the merged record
		if ($mergeType) {
			$marcPreMerge = $record;	// Save to argument returned by reference
			$record = $this->mergeWithExistingVoyager ($record, $mergeDefinition, $mergeType, $mergeVoyagerId, $sourceRegistry, $errorString);
		}
		
		# Report any UTF-8 problems
		if (strlen ($record) && !htmlspecialchars ($record)) {	// i.e. htmlspecialchars fails
			$errorString .= "UTF-8 conversion failed in record <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">#{$this->recordId}</a>.";
			return false;
		}
		
		# Do a check to report any case of an invalid subfield indicator
		if (preg_match_all ("/{$this->doubleDagger}[^a-z0-9]/u", $record, $matches)) {
			$errorString .= 'Invalid ' . (count ($matches[0]) == 1 ? 'subfield' : 'subfields') . " (" . implode (', ', $matches[0]) . ") detected in record <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">#{$this->recordId}</a>.";
			// Leave the record visible rather than return false
		}
		
		# Do a check to report any case where a where 880 fields do not have both a field (starting validly with a $6) and a link back
		preg_match_all ("/^880 [0-9#]{2} {$this->doubleDagger}6 /m", $record, $matches);
		$total880fields = count ($matches[0]);
		$total880dollar6Instances = substr_count ($record, "{$this->doubleDagger}6 880-");
		if ($total880fields != $total880dollar6Instances) {
			$errorString .= "Mismatch in 880 field/link counts ({$total880fields} vs {$total880dollar6Instances}) in record <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">#{$this->recordId}</a>.";
			// Leave the record visible rather than return false
		}
		
		# Return the record
		return $record;
	}
	
	
	# Getter for second-pass record ID
	public function getSecondPassRecordId ()
	{
		return $this->secondPassRecordId;
	}
	
	
	# Function to get a list of supported macros
	public function getSupportedMacros ()
	{
		# Get the list of matching functions
		$methods = get_class_methods ($this);
		
		# Find matches
		$macros = array ();
		foreach ($methods as $method) {
			if (preg_match ('/^macro_([a-zA-Z0-9_]+)/', $method, $matches)) {
				$macros[] = $matches[1];
			}
		}
		
		# Return the list
		return $macros;
	}
	
	
	# Function to perform merge of a MARC record with an existing Voyager record
	private function mergeWithExistingVoyager ($localRecord, $mergeDefinitions, $mergeType, $mergeVoyagerId, &$sourceRegistry = array (), &$errorString)
	{
		# Start a source registry, to store which source each line comes from
		$sourceRegistry = array ();
		
		# End if merge type is unsupported; this will result in an empty record
		#!# Need to ensure this is reported during the import also
		if (!isSet ($this->mergeTypes[$mergeType])) {
			$errorString = "WARNING: Merge failed for Muscat record #{$this->recordId}: unsupported merge type {$mergeType}. The local record has been put in, without merging.";
			return $localRecord;
		}
		
		# Select the merge definition to use
		$mergeDefinition = $mergeDefinitions[$mergeType];
		
		# Get the existing Voyager record
		if (!$voyagerRecord = $this->getExistingVoyagerRecord ($mergeVoyagerId)) {
			$errorString = "WARNING: Merge failed for Muscat record #{$this->recordId}: could not retrieve existing Voyager record. The local record has been put in, without merging.";
			return $localRecord;
		}
		
		# Parse out the local MARC record and the Voyager record into nested structures
		$localRecordStructure = $this->parseMarcRecord ($localRecord);
		$voyagerRecordStructure = $this->parseMarcRecord ($voyagerRecord);
		
		# Create a superset list of all fields across both types of record
		$allFieldNumbers = array_merge (array_keys ($localRecordStructure), array_keys ($voyagerRecordStructure));
		$allFieldNumbers = array_unique ($allFieldNumbers);
		sort ($allFieldNumbers, SORT_NATURAL);	// This will order by number but put LDR at the end
		$ldr = array_pop ($allFieldNumbers);	// Remove LDR from end
		array_unshift ($allFieldNumbers, $ldr);
		
		# Create a superstructure, where all fields are present from the superset, sub-indexed by source
		$superstructure = array ();
		foreach ($allFieldNumbers as $fieldNumber) {
			$superstructure[$fieldNumber] = array (
				'muscat'	=> (isSet ($localRecordStructure[$fieldNumber])   ? $localRecordStructure[$fieldNumber]   : NULL),
				'voyager'	=> (isSet ($voyagerRecordStructure[$fieldNumber]) ? $voyagerRecordStructure[$fieldNumber] : NULL),
			);
		}
		
		/*
		echo "recordId:";
		application::dumpData ($this->recordId);
		echo "mergeType:";
		application::dumpData ($mergeType);
		echo "localRecordStructure:";
		application::dumpData ($localRecordStructure);
		echo "voyagerRecordStructure:";
		application::dumpData ($voyagerRecordStructure);
		echo "mergeDefinition:";
		application::dumpData ($mergeDefinition);
		echo "superstructure:";
		application::dumpData ($superstructure);
		*/
		
		# Perform merge based on the specified strategy
		$recordLines = array ();
		$i = 0;
		foreach ($superstructure as $fieldNumber => $recordPair) {
			
			# By default, assume the lines for this field are copied across into the eventual record from both sources
			$muscat = true;
			$voyager = true;
			
			# If there is a merge definition, apply its algorithm
			if (isSet ($mergeDefinition[$fieldNumber])) {
				switch ($mergeDefinition[$fieldNumber]) {
					
					case 'M':
						$muscat = true;
						$voyager = false;
						break;
						
					case 'V':
						$muscat = false;
						$voyager = true;
						break;
						
					case 'M else V':
						if ($recordPair['muscat']) {
							$muscat = true;
							$voyager = false;
						} else {
							$muscat = false;
							$voyager = true;
						}
						break;
						
					case 'V else M':
						if ($recordPair['voyager']) {
							$muscat = false;
							$voyager = true;
						} else {
							$muscat = true;
							$voyager = false;
						}
						break;
						
					case 'V and M':
						$muscat = true;
						$voyager = true;
						break;
				}
			}
			
			# Extract the full line from each of the local lines
			if ($muscat) {
				if ($recordPair['muscat']) {
					foreach ($recordPair['muscat'] as $recordLine) {
						$recordLines[$i] = $recordLine['fullLine'];
						$sourceRegistry[$i] = 'M';
						$i++;
					}
				}
			}
			
			# Extract the full line from each of the voyager lines
			if ($voyager) {
				if ($recordPair['voyager']) {
					foreach ($recordPair['voyager'] as $recordLine) {
						$recordLines[$i] = $recordLine['fullLine'];
						$sourceRegistry[$i] = 'V';
						$i++;
					}
				}
			}
		}
		
		# Implode the record lines
		$record = implode ("\n", $recordLines);
		
		# Return the merged record; the source registry is passed back by reference
		return $record;
	}
	
	
	# Function to obtain the data for an existing Voyager record, as a multi-dimensional array indexed by field then an array of lines for that field
	public function getExistingVoyagerRecord ($mergeVoyagerId, &$errorText = '')
	{
		# If the merge voyager ID is not yet a pure integer (i.e. not yet a one-to-one lookup), state this and end
		if (!ctype_digit ($mergeVoyagerId)) {
			$errorText = 'There is not yet a one-to-one match, so no Voyager record can be displayed.';
			return false;
		}
		
		# Look up Voyager record, or end (e.g. no match)
		if (!$voyagerRecordShards = $this->databaseConnection->select ($this->settings['database'], 'catalogue_external', array ('voyagerId' => $mergeVoyagerId))) {
			$errorText = "Error: the specified Voyager record (#{$mergeVoyagerId}) could not be found in the external datasource.";
			return false;
		}
		
		# Construct the record lines
		$recordLines = array ();
		foreach ($voyagerRecordShards as $shard) {
			$hasIndicators = (!preg_match ('/^(LDR|00[0-9])$/', $shard['field']));
			$recordLines[] = $shard['field'] . ($hasIndicators ? ' ' . $shard['indicators'] : '') . ' ' . $shard['data'];
		}
		
		# Implode to text string
		$record = implode ("\n", $recordLines);
		
		# Return the record text block
		return $record;
	}
	
	
	# Function to load an XML record string as XML
	public function loadXmlRecord ($record)
	{
		# Load the record as a valid XML object
		$xmlProlog = '<' . '?xml version="1.0" encoding="utf-8"?' . '>';
		$record = $xmlProlog . "\n<root>" . "\n" . $record . "\n</root>";
		$xml = new SimpleXMLElement ($record);
		return $xml;
	}
	
	
	# Function to ensure the line-by-line syntax is valid, extract macros, and construct a data structure representing the record
	private function convertToMarc_InitialiseDatastructure ($record, $marcParserDefinition, &$errorString = '')
	{
		# Convert the definition into lines
		$marcParserDefinition = str_replace ("\r\n", "\n", $marcParserDefinition);
		$lines = explode ("\n", $marcParserDefinition);
		
		# Strip out comments and empty lines
		foreach ($lines as $lineNumber => $line) {
			
			# Skip empty lines
			if (!trim ($line)) {unset ($lines[$lineNumber]);}
			
			# Skip comment lines
			if (mb_substr ($line, 0, 1) == '#') {unset ($lines[$lineNumber]); continue;}
		}
		
		# Start the datastructure by loading each line
		$datastructure = array ();
		foreach ($lines as $lineNumber => $line) {
			$datastructure[$lineNumber]['line'] = $line;
		}
		
		# Ensure the line-by-line syntax is valid, extract macros, and construct a data structure representing the record
		foreach ($lines as $lineNumber => $line) {
			
			# Initialise arrays to ensure attributes for each line are present
			$datastructure[$lineNumber]['controlCharacters'] = array ();
			$datastructure[$lineNumber]['macros'] = array ();
			$datastructure[$lineNumber]['xpathReplacements'] = array ();
			
			# Validate and extract the syntax
			if (!preg_match ('/^([AER]*)\s+(([0-9|LDR]{3}) .{3}.+)$/', $line, $matches)) {
				$errorString = 'Line ' . ($lineNumber + 1) . ' does not have the right syntax.';
				return false;
			}
			
			# Determine the MARC code; examples are: LDR, 008, 100, 245, 852 etc.
			$datastructure[$lineNumber]['marcCode'] = $matches[3];
			
			# Strip away (and cache) the control characters
			$datastructure[$lineNumber]['controlCharacters'] = str_split ($matches[1]);
			$datastructure[$lineNumber]['line'] = $matches[2];
			
			# Extract all XPath references
			preg_match_all ('/' . "({$this->doubleDagger}[a-z0-9])?" . '(\\??)' . '((R?)(i?){([^}]+)})' . "(\s*?)" /* Use of *? makes this capture ungreedy, so we catch any trailing space(s) */ . '/U', $line, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$subfieldIndicator = $match[1];		// e.g. $a (actually a dagger not a $)
				$optionalBlockIndicator = $match[2];
				$findBlock = $match[3];	// e.g. '{//somexpath}'
				$isHorizontallyRepeatable = $match[4];	// The 'R' flag
				$isIndicatorBlockMacro = $match[5];	// The 'i' flag
				$xpath = $match[6];
				$trailingSpace = $match[7];		// Trailing space(s), if any, so that these can be preserved during replacement
				
				# Firstly, register macro requirements by stripping these from the end of the XPath, e.g. {/*/isbn|macro:validisbn|macro:foobar} results in $datastructure[$lineNumber]['macros'][/*/isbn|macro] = array ('xpath' => 'validisbn', 'macrosThisXpath' => 'foobar')
				$macrosThisXpath = array ();
				while (preg_match ('/^(.+)\|macro:([^|]+)$/', $xpath, $macroMatches)) {
					array_unshift ($macrosThisXpath, $macroMatches[2]);
					$xpath = $macroMatches[1];
				}
				if ($macrosThisXpath) {
					$datastructure[$lineNumber]['macros'][$findBlock]['macrosThisXpath'] = $macrosThisXpath;	// Note that using [xpath]=>macrosThisXpath is not sufficient as lines can use the same xPath more than once
				}
				
				# Register the full block; e.g. '‡b{//recr} ' including any trailing space
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['fullBlock'] = $match[0];
				
				# Register the subfield indicator
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['subfieldIndicator'] = $subfieldIndicator;
				
				# Register whether the block is an optional block
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['isOptionalBlock'] = (bool) $optionalBlockIndicator;
				
				# Register whether this xPath replacement is in the indicator block
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['isIndicatorBlockMacro'] = (bool) $isIndicatorBlockMacro;
				
				# Register the XPath
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['xPath'] = $xpath;
				
				# If the subfield is horizontally-repeatable, save the subfield indicator that should be used for imploding, resulting in e.g. $aFoo$aBar
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['horizontalRepeatability'] = ($isHorizontallyRepeatable ? $subfieldIndicator : false);
				
				# Register any trailing space(s)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['trailingSpace'] = $trailingSpace;
			}
		}
		
		# Return the datastructure
		return $datastructure;
	}
	
	
	# Function to check all macros are supported
	private function convertToMarc_MacrosAllSupported ($datastructure, &$errorString = '')
	{
		# Get the supported macros
		$supportedMacros = $this->getSupportedMacros ();
		
		# Work through each line of macros
		$unknownMacros = array ();
		foreach ($datastructure as $lineNumber => $line) {
			foreach ($line['macros'] as $find => $attributes) {
				foreach ($attributes['macrosThisXpath'] as $macro) {
					$macro = preg_replace ('/^([a-zA-Z0-9_]+)\([^)]+\)/', '\1', $macro);	// Strip any prefixed (..) argument
					if (!in_array ($macro, $supportedMacros)) {
						$unknownMacros[] = $macro;
					}
				}
			}
		}
		if ($unknownMacros) {
			$errorString = 'Not all macros were recognised: ' . implode (', ', $unknownMacros);
			return false;
		}
		
		# No problems found
		return true;
	}
	
	
	# Function to perform Xpath replacements
	private function convertToMarc_PerformXpathReplacements ($datastructure, $xml, &$errorString = '')
	{
		# Lookup XPath values from the record which are needed multiple times, for efficiency
		$this->form = $this->xPathValue ($xml, '(//form)[1]', false);
		
		# Perform XPath replacements
		$compileFailures = array ();
		foreach ($datastructure as $lineNumber => $line) {
			
			# Determine if the line is vertically-repeatable
			$isVerticallyRepeatable = (in_array ('R', $datastructure[$lineNumber]['controlCharacters']));
			
			# Work through each XPath replacement
			foreach ($line['xpathReplacements'] as $find => $xpathReplacementSpec) {
				$xPath = $xpathReplacementSpec['xPath'];	// Extract from structure
				
				# Determine if horizontally-repeatable
				$isHorizontallyRepeatable = (bool) $xpathReplacementSpec['horizontalRepeatability'];
				
				# Deal with fixed strings
				if (preg_match ("/^'(.+)'$/", $xPath, $matches)) {
					$value = array ($matches[1]);
					
				# Handle the special-case where the specified XPath is just '/', representing the whole record; this indicates that the macro will process the record as a whole, ignoring any passed in value; doing this avoids the standard XPath processor resulting in an array of two values of (1) *qo and (2) *doc/*art/*ser
				} else if ($xPath == '/') {
					$value = array (true);	// Ensures the result processor continues, but this 'value' is then ignored
					
				# Otherwise, handle the standard case
				} else {
					
					# Attempt to parse
					$xPathResult = @$xml->xpath ('/root' . $xPath);
					
					# Check for compile failures
					if ($xPathResult === false) {
						$compileFailures[] = $xPath;
						continue;
					}
					
					# Obtain the value component(s)
					$value = array ();
					foreach ($xPathResult as $node) {
						$value[] = (string) $node;
					}
				}
				
				# If there was a result process it
				if ($value) {
					
					/*
					  NOTE:
					  
					  The order of processing here is important.
					  
					  Below are two steps:
					  
					  1) Assemble the string components (unless vertically-repeatable/horizontally-repeatable) into a single string:
					     e.g. {//k/kw} may end up with values 'Foo' 'Bar' 'Zog'
						 therefore these become imploded to:
						 FooBarZog
						 However, if either the R (vertically-repeatable at start of line, or horizontally-repeatable attached to macro) flag is present, then that will be stored as:
						 array('Foo', 'Bar', 'Zog')
						 
					  2) Run the value through any macros that have been defined for this XPath on this line
					     This takes effect on each value now present, i.e.
						 {//k/kw|macro::dotend} would result in either:
						 R:        FooBarZog.
						 (not R):  array('Foo.', 'Bar.', 'Zog.')
						 
					  So, currently, the code does the merging first, then macro processing on each element.
					*/
					
					# Assemble the string components (unless vertically-repeatable or horizontally-repeatable) into a single string
					if (!$isVerticallyRepeatable && !$isHorizontallyRepeatable) {
						$value = implode ('', $value);
					}
					
					# Run the value through any macros that have been defined for this XPath on this line
					if (isSet ($datastructure[$lineNumber]['macros'][$find])) {
						
						# Determine the macro(s) for this Xpath
						$macros = $datastructure[$lineNumber]['macros'][$find]['macrosThisXpath'];
						
						# For a vertically-repeatable field, process each value; otherwise process the compiled string
						if ($isVerticallyRepeatable || $isHorizontallyRepeatable) {
							foreach ($value as $index => $subValue) {
								$value[$index] = $this->processMacros ($xml, $subValue, $macros);
							}
						} else {
							$value = $this->processMacros ($xml, $value, $macros);
						}
					}
					
					# For horizontally-repeatable fields, apply uniqueness after macro processing; e.g. if Lang1, Lang2, Lang3 becomes translatedlangA, translatedlangB, translatedlangB, unique to translatedlangA, translatedlangB
					if ($isHorizontallyRepeatable) {
						$value = array_unique ($value);		// Key numbering may now have holes, but the next operation is imploding anyway
					}
					
					# If horizontally-repeatable, compile with the subfield indicator as the implode string
					if ($isHorizontallyRepeatable) {
						$value = implode ($xpathReplacementSpec['horizontalRepeatability'], $value);
					}
					
					# Register the processed value
					$datastructure[$lineNumber]['xpathReplacements'][$find]['replacement'] = $value;	// $value is usually a string, but an array if repeatable
				} else {
					$datastructure[$lineNumber]['xpathReplacements'][$find]['replacement'] = '';
				}
			}
		}
		
		# If there are compile failures, assemble this into an error message
		if ($compileFailures) {
			$errorString = 'Not all expressions compiled: ' . implode ($compileFailures);
			return false;
		}
		
		# Return the datastructure
		return $datastructure;
	}
	
	
	# Function to expand vertically-repeatable fields
	private function convertToMarc_ExpandVerticallyRepeatableFields ($datastructureUnexpanded, &$errorString = '')
	{
		$datastructure = array ();	// Expanded version, replacing the original
		foreach ($datastructureUnexpanded as $lineNumber => $line) {
			
			# If not vertically-repeatable, copy the attributes across unamended, and move on
			if (!in_array ('R', $line['controlCharacters'])) {
				$datastructure[$lineNumber] = $line;
				continue;
			}
			
			# For vertically-repeatable, first check the counts are consistent (e.g. if //k/kw generated 7 items, and //k/ks generated 5, throw an exception, as behaviour is undefined)
			$counts = array ();
			foreach ($line['xpathReplacements'] as $macroBlock => $xpathReplacementSpec) {
				$replacementValues = $xpathReplacementSpec['replacement'];
				$counts[$macroBlock] = count ($replacementValues);
			}
			if (count (array_count_values ($counts)) != 1) {
				$errorString = 'Line ' . ($lineNumber + 1) . ' is a vertically-repeatable field, but the number of generated values in the subfields are not consistent:' . application::dumpData ($counts, false, true);
				continue;
			}
			
			# If there are no values on this line, then no expansion is needed, so copy the attributes across unamended, and move on
			if (!$replacementValues) {	// Reuse the last replacementValues - it will be confirmed as being the same as all subfields will have
				$datastructure[$lineNumber] = $line;
				continue;
			}
			
			# Determine the number of line expansions (which the above check should ensure is consistent between each of the counts)
			$numberOfLineExpansions = application::array_first_value ($counts);		// Take the first count only
			
			# Clone the line, one for each subvalue, as-is, assigning a new key (original key, plus the subvalue index)
			for ($subLine = 0; $subLine < $numberOfLineExpansions; $subLine++) {
				$newLineId = "{$lineNumber}_{$subLine}";	// e.g. 17_0, 17_1 if there are two line expansion
				$datastructure[$newLineId] = $line;
			}
			
			# Overwrite the subfield value within the structure, so it contains only this subfield value, not the whole array of values
			for ($subLine = 0; $subLine < $numberOfLineExpansions; $subLine++) {
				$newLineId = "{$lineNumber}_{$subLine}";
				foreach ($line['xpathReplacements'] as $macroBlock => $xpathReplacementSpec) {
					$datastructure[$newLineId]['xpathReplacements'][$macroBlock]['replacement'] = $xpathReplacementSpec['replacement'][$subLine];
				}
			}
		}
		
		# Return the newly-expanded datastructure
		return $datastructure;
	}
	
	
	# Function to process the record
	private function convertToMarc_ProcessRecord ($datastructure, $errorString)
	{
		# Process each line
		$outputLines = array ();
		foreach ($datastructure as $lineNumber => $attributes) {
			$line = $attributes['line'];
			
			# Perform XPath replacements if any, working through each replacement
			if ($datastructure[$lineNumber]['xpathReplacements']) {
				
				# Start a flag for whether the line has content
				$lineHasContent = false;
				
				# Loop through each macro block
				$replacements = array ();
				foreach ($datastructure[$lineNumber]['xpathReplacements'] as $macroBlock => $xpathReplacementSpec) {
					$replacementValue = $xpathReplacementSpec['replacement'];
					
					# Determine if there is content
					$blockHasValue = strlen ($replacementValue);
					
					# Register replacements
					$fullBlock = $xpathReplacementSpec['fullBlock'];	// The original block, which includes any trailing space(s), e.g. "‡a{/*/edn} "
					if ($blockHasValue) {
						$replacements[$fullBlock] = $xpathReplacementSpec['subfieldIndicator'] . $replacementValue . $xpathReplacementSpec['trailingSpace'];
					} else {
						$replacements[$fullBlock] = '';		// Erase the block
					}
					
					# Perform control character checks if the macro is a normal (general value-creation) macro, not an indicator block macro
					if (!$xpathReplacementSpec['isIndicatorBlockMacro']) {
						
						# If this content macro has resulted in a value, set the line content flag
						if ($blockHasValue) {
							$lineHasContent = true;
						}
						
						# If there is an 'A' (all) control character, require all non-optional placeholders to have resulted in text
						#!# Currently this takes no account of the use of a macro in the nonfiling-character section (e.g. 02), i.e. those macros prefixed with indicators; however in practice that should always return a string
						if (in_array ('A', $datastructure[$lineNumber]['controlCharacters'])) {
							if (!$xpathReplacementSpec['isOptionalBlock']) {
								if (!$blockHasValue) {
									continue 2;	// i.e. break out of further processing of blocks on this line (as further ones are irrelevant), and skip the whole line registration below
								}
							}
						}
					}
				}
				
				# If there is an 'E' ('any') control character, require at least one replacement, i.e. that content (after the field number and indicators) exists
				if (in_array ('E', $datastructure[$lineNumber]['controlCharacters'])) {
					if (!$lineHasContent) {
						continue;	// i.e. skip this line, preventing registration below
					}
				}
				
				# Perform string translation on each line
				$line = strtr ($line, $replacements);
			}
			
			# Determine the key to use for the line output
			$i = 0;
			$lineOutputKey = $attributes['marcCode'] . '_' . $i++;	// Examples: LDR_0, 001_0, 100_0, 650_0
			while (isSet ($outputLines[$lineOutputKey])) {
				$lineOutputKey = $attributes['marcCode'] . '_' . $i++;	// e.g. 650_1 for the second 650 record, 650_2 for the third, etc.
			}
			
			# Trim the line; NB This will not trim within multiline output lines
			#!# Need to check multiline outputs to ensure they are trimming
			$line = trim ($line);
			
			# Register the value
			$outputLines[$lineOutputKey] = $line;
		}
		
		# Insert 880 reciprocal links; see: http://www.lib.cam.ac.uk/libraries/login/documentation/Unicode_non_roman_cataloguing_handout.pdf
		foreach ($this->field880subfield6ReciprocalLinks as $lineOutputKey => $linkToken) {		// $lineOutputKey is e.g. 700_0
			
			# Report data mismatches
			if (!isSet ($outputLines[$lineOutputKey])) {
				echo "<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">record #{$this->recordId}</a>:</strong> line output key {$lineOutputKey} does not exist in the output lines.</p>";
			}
			
			# For multilines, split the line into parts, prepend the link token
			if (is_array ($linkToken)) {
				$lines = explode ("\n", $outputLines[$lineOutputKey]);	// Split out
				foreach ($lines as $i => $line) {
					$lines[$i] = $this->insertSubfieldAfterMarcFieldThenIndicators ($line, $linkToken[$i]);
				}
				$outputLines[$lineOutputKey] = implode ("\n", $lines);	// Reconstruct
				
			# For standard lines, do a simple insertion
			} else {
				$outputLines[$lineOutputKey] = $this->insertSubfieldAfterMarcFieldThenIndicators ($outputLines[$lineOutputKey], $linkToken);
			}
		}
		
		# Compile the record
		$record = implode ("\n", $outputLines);
		
		# Strip tags (introduced in specialCharacterParsing) across the record: "in MARC there isn't a way to represent text in italics in a MARC record and get it to display in italics in the OPAC/discovery layer, so the HTML tags will need to be stripped."
		$tags = array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>');
		$record = str_replace ($tags, '', $record);
		
		# Return the record
		return $record;
	}
	
	
	# Function to modify a line to insert a subfield after the opening MARC field and indicators; for a multiline value, this must be one of the sublines
	private function insertSubfieldAfterMarcFieldThenIndicators ($line, $insert)
	{
		return preg_replace ('/^([0-9]{3}) ([0-9#]{2}) (.+)$/', "\\1 \\2 {$insert} \\3", $line);
	}
	
	
	# Function to process strings through macros; macros should return a processed string, or false upon failure
	private function processMacros ($xml, $string, $macros)
	{
		# Pass the string through each macro in turn
		foreach ($macros as $macro) {
			
			# Cache the original string
			$originalString = $string;
			
			# Determine any argument supplied
			$parameter = NULL;
			if (preg_match ('/([a-zA-Z0-9]+)\(([^)]+)\)/', $macro, $matches)) {
				$macro = $matches[1];	// Overwrite the method name
				$parameter = $matches[2];
			}
			
			# Pass the string through the macro
			$macroMethod = 'macro_' . $macro;
			if (is_null ($parameter)) {
				$string = $this->{$macroMethod} ($string, $xml, NULL);
			} else {
				$string = $this->{$macroMethod} ($string, $xml, $parameter);
			}
			
			// Continue to next macro (if any), using the processed string as it now stands
		}
		
		# Return the string
		return $string;
	}
	
	
	/* Macros */
	
	
	# ISBN validation
	# Permits multimedia value EANs, which are probably valid to include as the MARC spec mentions 'EAN': https://www.loc.gov/marc/bibliographic/bd020.html ; see also http://www.activebarcode.com/codes/ean13_laenderpraefixe.html
	private function macro_validisbn ($value)
	{
		# Strip off any note, for use as qualifying information
		$q = false;
		if (preg_match ('/^(.+)\s?\((.+)\)$/', $value, $matches)) {
			$value = trim ($matches[1]);
			$q = str_replace (array ('v.', 'vol.'), 'v. ', $matches[2]);	// e.g. 'v. 1' in /records/56613/ or 'set' in /records/71406/
			if ($q == 'invalid') {$q = false;}	// Will be caught below anyway; applies to /records/140472/ and /records/150974/
		}
		
		# Determine the subfield, by performing a validation; seems to permit EANs like 5391519681503 in /records/211150/
		$this->muscatConversion->loadIsbnValidationLibrary ();
		$isValid = $this->muscatConversion->isbn->validation->isbn ($value);
		$subfield = $this->doubleDagger . ($isValid ? 'a' : 'z');
		
		# Assemble the return value, adding qualifying information if required
		$string = $subfield . $value;
		if ($q) {
			$string .= "{$this->doubleDagger}q" . $q;
		}
		
		# Return the value
		return $string;
	}
	
	
	# Macro to prepend a string if there is a value
	private function macro_prepend ($value, $xml, $text)
	{
		# Return unmodified if no value
		if (!$value) {return $value;}
		
		# Prepend the text
		return $text . $value;
	}
	
	
	# Macro to check existence
	private function macro_ifValue ($value, $xml, $xPath)
	{
		return ($this->xPathValue ($xml, $xPath) ? $value : false);
	}
	
	
	# Macro to upper-case the first character
	private function macro_ucfirst ($value, $xml)
	{
		return mb_ucfirst ($value);
	}
	
	
	# Macro to implement a ternary check
	private function macro_ifElse ($value_ignored /* If empty, the macro will not even be called, so the value has to be passed in by parameter */, $xml, $parameters)
	{
		# Parse the parameters
		list ($xPath, $ifValue, $elseValue) = explode (',', $parameters, 3);
		
		# Determine the value
		$value = $this->xPathValue ($xml, $xPath);
		
		# Return the result
		return ($value ? $ifValue : $elseValue);
	}
	
	
	# Splitting of strings with colons in
	private function macro_colonSplit ($value, $xml, $splitMarker)
	{
		# Return unmodified if no split
		if (!preg_match ('/^([^:]+) ?: (.+)$/', $value, $matches)) {
			return $value;
		}
		
		# If a split is found, assemble
		$value = trim ($matches[1]) . " : {$this->doubleDagger}{$splitMarker} " . trim ($matches[2]);
		
		# Return the value
		return $value;
	}
	
	
	# Require a dot
	private function macro_requireDot ($value)
	{
		# End if none
		if (!preg_match ('/^([^:]+) ?\. (.+)$/', $value, $matches)) {
			return false;
		}
		
		# Return unmodified if present
		return $value;
	}
	
	
	# Splitting of strings with a dot
	private function macro_dotSplit ($value, $xml, $splitMarker)
	{
		# Return unmodified if no split
		if (!preg_match ('/^([^:]+) ?\. (.+)$/', $value, $matches)) {
			return $value;
		}
		
		# If a split is found, assemble
		$value = trim ($matches[1]) . ". {$this->doubleDagger}{$splitMarker} " . trim ($matches[2]);
		
		# Return the value
		return $value;
	}
	
	
	# Ending strings with dots
	public function macro_dotEnd ($value, $xml_ignored, $extendedCharacterList = false)
	{
		# End if no value
		if (!strlen ($value)) {return $value;}
		
		# Determine characters to check at the end
		$characterList = ($extendedCharacterList ? (is_string ($extendedCharacterList) ? $extendedCharacterList : '.])>') : '.');	// e.g. 260 $c shown at https://www.loc.gov/marc/bibliographic/bd260.html
		
		# Return unmodified if character already present; for comparison purposes only, this is checked against a strip-tagged version in case there are tags at the end of the string, e.g. the 710 line at /records/7463/
		if (preg_match ('/^(.+)[' . preg_quote ($characterList) . ']$/', strip_tags ($value), $matches)) {
			return $value;
		}
		
		# Add the dot
		$value .= '.';
		
		# Return the value
		return $value;
	}
	
	
	# Macro to strip values like - or ??
	private function macro_excludeNoneValue ($value)
	{
		# Return false on match
		if ($value == '-') {return false;}
		if ($value == '??') {return false;}
		
		# Return the value
		return $value;
	}
	
	
	# Macro to get multiple values as an array
	private function macro_multipleValues ($value_ignored, $xml, $parameter)
	{
		$parameter = "({$parameter})[%i]";
		$values = $this->xPathValues ($xml, $parameter, false);		// e.g. /records/2071/ for 546 $a //lang ; /records/6321/ for 260 $c //d
		$values = array_unique ($values);
		return $values;
	}
	
	
	# Macro to implode subvalues
	private function macro_implode ($values, $xml, $parameter)
	{
		# Return empty string if no values
		if (!$values) {return '';}
		
		# Implode and return
		return implode ($parameter, $values);
	}
	
	
	# Macro to implode subvalues with the comma-and algorithm; e.g. as used for 546 (example record: /records/160854/ )
	private function macro_commaAnd ($values, $xml, $parameter)
	{
		# Return empty string if no values
		if (!$values) {return '';}
		
		# Implode and return
		return application::commaAndListing ($values);
	}
	
	
	# Macro to create 260;  $a and $b are grouped as there may be more than one publisher, e.g. /records/76743/ ; see: https://www.loc.gov/marc/bibliographic/bd260.html
	private function macro_generate260 ($value_ignored, $xml, $transliterate = false)
	{
		# Start a list of values; the macro definition has already defined $a
		$results = array ();
		
		# Loop through each /*pg/*[pl|pu] group; e.g. /records/76742/
		for ($pgIndex = 1; $pgIndex <= 20; $pgIndex++) {	// XPaths are indexed from 1, not 0
			$pg = $this->xPathValue ($xml, "//pg[{$pgIndex}]");
			
			# Break out of loop if no more
			if ($pgIndex > 1) {
				if (!strlen ($pg)) {break;}
			}
			
			# Obtain the raw *pl value(s) for this *pg group
			$plValues = array ();
			for ($plIndex = 1; $plIndex <= 20; $plIndex++) {
				$plValue = $this->xPathValue ($xml, "//pg[$pgIndex]/pl[{$plIndex}]");	// e.g. /records/1639/ has multiple
				if ($plIndex > 1 && !strlen ($plValue)) {break;}	// Empty $pl is fine for first and will show [S.l.], but after that should not appear
				$plValues[] = $this->formatPl ($plValue);
			}
			
			# Obtain the raw *pu value(s) for this *pg group
			$puValue = $this->xPathValue ($xml, "//pg[$pgIndex]/pu");
			$puValues = array ();
			for ($puIndex = 1; $puIndex <= 20; $puIndex++) {
				$puValue = $this->xPathValue ($xml, "//pg[$pgIndex]/pu[{$puIndex}]");	// e.g. /records/1223/ has multiple
				if ($puIndex > 1 && !strlen ($puValue)) {break;}	// Empty $pu is fine for first and will show [s.n.], but after that should not appear
				$puValues[] = $this->formatPu ($puValue);
			}
			
			# Transliterate if required; e.g. /records/6996/ (test #58)
			if ($transliterate) {
				if ($puValues) {
					foreach ($puValues as $index => $puValue) {
						$xPath = '//lang[1]';	// Choose first only
						$language = $this->xPathValue ($xml, $xPath);
						$puValues[$index] = $this->macro_transliterate ($puValue, NULL, $language);
					}
				}
			}
			
			# Assemble the result
			#!# Need to check for cases of $b but not $a
			$results[$pgIndex]  = "{$this->doubleDagger}a" . implode (" ;{$this->doubleDagger}a", $plValues);
			if ($puValues) {
				$results[$pgIndex] .= " :{$this->doubleDagger}b" . implode (" :{$this->doubleDagger}b", $puValues);	// "a colon (:) when subfield $b is followed by another subfield $b" at https://www.loc.gov/marc/bibliographic/bd260.html
			}
		}
		
		# End if no values; e.g. /records/76740/
		if (!$results) {return false;}
		
		# Implode by space-semicolon: "a semicolon (;) when subfield $b is followed by subfield $a" at https://www.loc.gov/marc/bibliographic/bd260.html
		$result = implode (' ;', $results);
		
		# Add $c if present; confirmed these should be treated as a single $c, comma-separated, as we have no grouping information
		if ($dateValues = $this->xPathValues ($xml, '(//d)[%i]', false)) {
			if ($result) {$result .= ',';}
			$result .= "{$this->doubleDagger}c" . implode (', ', $dateValues);
		}
		
		# Ensure dot at end
		$result = $this->macro_dotEnd ($result, NULL, $extendedCharacterList = true);
		
		# Return the result
		return $result;
	}
	
	
	# Helper function for 260a *pl
	private function formatPl ($plValue)
	{
		# If no *pl, put '[S.l.]'. ; e.g. /records/1006/ ; decision made not to make a semantic difference between between a publication that is known to have an unknown publisher (i.e. a check has been done and this is explicitly noted) vs a publication whose check has never been done, so we don't know if there is a publisher or not.
		if (!$plValue) {
			return '[S.l.]';	// Meaning 'sine loco' ('without a place')
		}
		
		# *pl [if *pl is '[n.p.]' or '-', this should be replaced with '[S.l.]' ]. ; e.g. /records/1102/ , /records/1787/
		if ($plValue == '[n.p.]' || $plValue == '-') {
			return '[S.l.]';
		}
		
		# Preserve square brackets, but remove round brackets if present. ; e.g. /records/2027/ , /records/5942/ , /records/5943/
		if (preg_match ('/^\((.+)\)$/', $plValue, $matches)) {
			return $matches[1];
		}
		
		# Return the value unmodified
		return $plValue;
	}
	
	
	# Helper function for 260a *pu
	private function formatPu ($puValue)
	{
		# *pu [if *pu is '[n.pub.]' or '-', this should be replaced with '[s.n.]' ] ; e.g. /records/1105/ , /records/1745/
		if (!strlen ($puValue) || $puValue == '[n.pub.]' || $puValue == '-') {
			return '[s.n.]';	// Meaning 'sine nomine' ('without a name')
		}
		
		# Otherwise, return the value unmodified; e.g. /records/1011/
		return $puValue;
	}
	
	
	# Macro to generate the 300 field (Physical Description); 300 is a Minimum standard field; see: https://www.loc.gov/marc/bibliographic/bd300.html
	# Note: the original data is not normalised, and the spec does not account for all cases, so the implementation here is based also on observation of various records and on examples in the MARC spec, to aim for something that is 'good enough' and similar enough to the MARC examples
	# At its most basic level, in "16p., ill.", $a is the 16 pages, $b is things after
	#!# Everything before a colon should describe a volume or issue number, which should end up in a 490 or 500 instead of 300 - to be discussed
	private function macro_generate300 ($value_ignored, $xml)
	{
		# Start a result
		$result = '';
		
		# Obtain *p
		$pValues = $this->xPathValues ($xml, '(//p)[%i]', false);	// Multiple *p, e.g. /records/6002/ , /records/15711/
		$p = ($pValues ? implode ('; ', $pValues) : '');
		
		# Obtain *pt
		$ptValues = $this->xPathValues ($xml, '(//pt)[%i]', false);	// Multiple *p, e.g. /records/25179/
		$pt = ($ptValues ? implode ('; ', $ptValues) : '');		// Decided in internal meeting to use semicolon, as comma is likely to be present within a component
		
		# Determine *p or *pt
		$value = (strlen ($p) ? $p : $pt);		// Confirmed there are no records with both *p and *pt
		
		# Obtain the record type
		$recordType = $this->recordType ($xml);
		
		# Firstly, break off any final + section, for use in $e below; e.g. /records/67235/
		$e = false;
		if (substr_count ($value, '+')) {
			$plusMatches = explode ('+', $value, 2);
			$e = trim ($plusMatches[1]);
			$value = trim ($plusMatches[0]);	// Override string to strip out the + section
		}
		
		# Next split by the keyword which acts as separator between $a and an optional $b
		$a = trim ($value);
		$b = false;
		$splitWords = array ('ill', 'diag', 'map', 'table', 'graph', 'port', 'col');
		foreach ($splitWords as $word) {
			if (substr_count ($value, $word)) {
				
				# If the word requires a dot after, add this if not present; e.g. /records/1584/ , /records/3478/ , /records/1163/
				# Checked using: `SELECT * FROM catalogue_processed WHERE field IN('p','pt') AND value LIKE '%ill%' AND value NOT LIKE '%ill.%' AND value NOT REGEXP 'ill(-|\.|\'|[a-z]|$)';`
				if ($word == 'ill') {
					if (!substr_count ($value, $word . '.')) {
						if (!preg_match ('/ill(-|\'|[a-z])/', $value)) {	// I.e. don't add . in middle of word or cases like ill
							$value = str_replace ($word, $word . '.', $value);
						}
					}
				}
				
				# Assemble
				$split = explode ($word, $value, 2);	// Explode seems more reliable than preg_split, because it is difficult to get a good regexp that allows multiple delimeters, multiple presence of delimeter, and optional trailing string
				$a = trim ($split[0]);
				$b = $word . $split[1];
				break;
			}
		}
		
		# $a (R) (Extent, pagination): If record is *doc with any or no *form, or *art with *form CD, CD-ROM, DVD, DVD-ROM, Sound Cassette, Sound Disc or Videorecording: "(*v), (*p or *pt)" [all text up to and including ':']
		# $a (R) (Extent, pagination): If record is *art with no *form or *form other than listed above: 'p. '*pt [number range after ':' and before ',']
		if (($recordType == '/doc') || (substr_count ($recordType, '/art') && in_array ($this->form, array ('CD', 'CD-ROM', 'DVD', 'DVD-ROM', 'Sound Cassette', 'Sound Disc' or 'Videorecording')))) {
			$v = $this->xPathValue ($xml, '//v');
			if (strlen ($v)) {
				$result .= $v . ($a ? ' ' : ($b ? ',' : ''));	// e.g. /records/20704/ , /records/37420/ , /records/175872/ , /records/8988/
			}
		} else if (substr_count ($recordType, '/art')) {		// Not in the list of *form above
			#!# This needs to be resolved - there are 29064 records whose XML has *pt starting with a colon: SELECT * FROM `catalogue_xml` WHERE `xml` LIKE '%<pt>:%' ; e.g. /records/1160/ which has "300 ## $a:1066-1133." which is surely wrong
			// $result .= 'p. ';	// Spec unclear - subsequent instruction was "/records/152332/ still contains a spurious 'p' in the $a - please ensure this is not added to the record"
		}
		
		# If there is a *vno but no *ts (and so no 490 will be created), add this at the start, before any pagination data from *pt; e.g. /records/5174/
		$vnoPrefix = false;
		if ($vno = $this->xPathValue ($xml, '//vno')) {
			if (!$ts = $this->xPathValue ($xml, '//ts')) {
				$a = $vno . (strlen ($a) ? ', ' : '') . $a;
			}
		}
		
		# Register the $a result
		$result .= $a;
		
		# Normalise 'p' to have a dot after; safe to make this change after checking: `SELECT * FROM catalogue_processed WHERE field IN('p','pt','vno','v','ts') AND value LIKE '%p%' AND value NOT LIKE '%p.%' AND value REGEXP '[0-9]p' AND value NOT REGEXP '[0-9]p( |,|\\)|\\]|$)';`
		$result = preg_replace ('/([0-9])p([^.]|$)/', '\1p.\2', $result);	// E.g. /records/1654/ , /records/2031/ , /records/6002/
		
		# Add space between the number and the 'p.' or 'v.' ; e.g. /records/49133/ for p. ; multiple instances of page number in /records/2031/ , /records/6002/; NB No actual cases for v. in the data
		$result = preg_replace ('/([0-9]+)([pv]\.)/', '\1 \2', $result);
		
		# $b (NR) (Other physical details): *p [all text after ':' and before, but not including, '+'] or *pt [all text after the ',' - i.e. after the number range following the ':']
		if (strlen ($b)) {
			
			# Normalise comma/colon at end of $a; e.g. /records/9529/ , /records/152326/
			$result = trim ($result);
			$result = preg_replace ('/(.+)[,:]$/', '\1', $result);
			$result = trim ($result);
			
			# Add $b
			$result .= " :{$this->doubleDagger}b" . trim ($b);	// Trim whitespace at end; there will be none at start due to explicit delimeter
		}
		
		# End if no value; in this scenario, no $c should be created, i.e. the whole routine should be ended
		if (!strlen ($result) || $value == 'unpaged') {	 // 'unpaged' at /records/1248/
			$result = ($recordType == '/ser' ? 'v.' : '1 volume (unpaged)');	// e.g. /records/1000/ , /records/1019/ (confirmed to be fine) , /records/1332/
		}
		
		# $c (R) (Dimensions): *size ; e.g. /records/1103/ , multiple in /records/4329/
		$size = $this->xPathValues ($xml, '(//size)[%i]', false);
		if ($size) {
			
			# Normalise " cm." to avoid Bibcheck errors; e.g. /records/2709/ , /records/4331/ , /records/54851/ ; have checked no valid inner cases of cm
			foreach ($size as $index => $sizeItem) {
				$sizeItem = preg_replace ('/([^ ])(cm)/', '\1 \2', $sizeItem);	// Normalise to ensure space before, i.e. "cm" -> " cm"
				$sizeItem = preg_replace ('/(cm)(?!\.)/', '\1.\2', $sizeItem);	// Normalise to ensure dot after,    i.e. "cm" -> "cm.", if not already present
				$size[$index] = $sizeItem;
			}
			
			# Add the size
			$result .= " ;{$this->doubleDagger}c" . implode (" ;{$this->doubleDagger}c", $size);
		}
		
		# $e (NR) (Accompanying material): If included, '+' appears before ‡e; ‡e is then followed by *p [all text after '+']; e.g. /records/152326/ , /records/67235/
		if ($e) {
			$result .= " +{$this->doubleDagger}e" . trim ($e);
		}
		
		# Ensure 300 ends in a dot or closing bracket
		$result = $this->macro_dotEnd (trim ($result), NULL, '.)]');
		
		# Return the result
		return $result;
	}
	
	
	# Function to get an XPath value
	public function xPathValue ($xml, $xPath, $autoPrependRoot = true)
	{
		if ($autoPrependRoot) {
			$xPath = '/root' . $xPath;
		}
		$result = @$xml->xpath ($xPath);
		if (!$result) {return false;}
		$value = array ();
		foreach ($result as $node) {
			$value[] = (string) $node;
		}
		$value = implode ($value);
		return $value;
	}
	
	
	# Function to get a set of XPath values for a field known to have multiple entries; these are indexed from 1, mirroring the XPath spec, not 0
	public function xPathValues ($xml, $xPath, $autoPrependRoot = true)
	{
		# Get each value
		$values = array ();
		$maxItems = 20;
		for ($i = 1; $i <= $maxItems; $i++) {
			$xPathThisI = str_replace ('%i', $i, $xPath);	// Convert %i to loop ID if present
			$value = $this->xPathValue ($xml, $xPathThisI, $autoPrependRoot);
			if (strlen ($value)) {
				$values[$i] = $value;
			}
		}
		
		# Return the values
		return $values;
	}
	
	
	# Macro to generate the leading article count; this does not actually modify the string itself - just returns a number
	public function macro_nfCount ($value, $xml_ignored, $language = false, $externalXml = NULL)
	{
		# Get the leading articles list, indexed by language
		$leadingArticles = $this->leadingArticles ();
		
		# If the the value is surrounded by square brackets, then it can be taken as English, and the record language itself ignored
		#!# Check on effect of *to or *tc, as per /reports/bracketednfcount/
		if ($isSquareBracketed = ((substr ($value, 0, 1) == '[') && (substr ($value, -1, 1) == ']'))) {
			$language = 'English';	// E.g. /records/14153/
			if (preg_match ('/^\[La /', $value)) {	// All in /reports/bracketednfcount/ were reviewed and found to be English, except /records/9196/
				$language = 'French';
			}
		}
		
		# If a forced language is not specified, obtain the language value for the record
		#!# //lang may no longer be reliable following introduction of *lang data within *in or *j
		#!# For the 240 field, this needs to take the language whose index number is the same as t/tt/to...
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$xml = ($externalXml ? $externalXml : $this->xml);	// Use external XML if supplied
			$language = $this->xPathValue ($xml, $xPath);
		}
		
		# If no language specified, choose 'English'
		if (!strlen ($language)) {$language = 'English';}
		
		# End if the language is not in the list of leading articles
		if (!isSet ($leadingArticles[$language])) {return '0';}
		
		# Work through each leading article, and if a match is found, return the string length
		foreach ($leadingArticles[$language] as $leadingArticle) {
			if (preg_match ("/^(['\"\[]*{$leadingArticle}['\"]*)/i", $value, $matches)) {	// Case-insensitive match; Incorporate starting brackets in the consideration and the count (e.g. /records/27894/ ); Include known starting/trailing punctuation within the count (e.g. /records/11329/ , /records/1325/ , /records/10366/ ) as per http://www.library.yale.edu/cataloging/music/filing.htm#ignore
				return (string) mb_strlen ($matches[1]); // The space, if present, is part of the leading article definition itself
			}
		}
		
		# Return '0' by default
		return '0';
	}
	
	
	# Macro to set an indicator based on the presence of a 100/110 field; e.g. /records/1844/
	private function macro_indicator1xxPresent ($defaultValue, $xml, $setValueIfAuthorsPresent)
	{
		# If authors field present, return the new value
		if (strlen ($this->authorsFields['default'][100]) || strlen ($this->authorsFields['default'][110]) || strlen ($this->authorsFields['default'][111])) {
			return $setValueIfAuthorsPresent;
		}
		
		# Otherwise return the default
		return $defaultValue;
	}
	
	
	# Lookup table for leading articles in various languages; note that Russian has no leading articles; see useful list at: https://en.wikipedia.org/wiki/Article_(grammar)#Variation_among_languages
	public function leadingArticles ($groupByLanguage = true)
	{
		# Define the leading articles
		$leadingArticles = array (
			'a ' => 'English glg Hungarian Portuguese',
			'al-' => 'ara',			// #!# Check what should happen for 245 field in /records/62926/ which is an English record but with Al- name at start of title
			'an ' => 'English',
			'ane ' => 'enm',
			'das ' => 'German',
			'de ' => 'Danish Swedish',
			'dem ' => 'German',
			'den ' => 'Danish German Norwegian Swedish',
			'der ' => 'German',
			'det ' => 'Danish German Norwegian Swedish',
			'die ' => 'German',
			'een ' => 'Dutch',
			'ei ' => 'Norwegian',	// /records/103693/ (test #171)
			'ein ' => 'German Norwegian',
			'eine ' => 'German',
			'einem ' => 'German',
			'einen ' => 'German',
			'einer ' => 'German',
			'eines ' => 'German',
			'eit ' => 'Norwegian',
			'el ' => 'Spanish',
			'els ' => 'Catalan',
			'en ' => 'Danish Norwegian Swedish',
			'et ' => 'Danish Norwegian',
			'ett ' => 'Swedish',
			'gl ' => 'Italian',
			'gli ' => 'Italian',
			'ha ' => 'Hebrew',
			'het ' => 'Dutch',
			'ho ' => 'grc',
			'il ' => 'Italian mlt',
			"l'" => 'Catalan French Italian mlt',		// e.g. /records/4571/ ; Catalan checked in https://en.wikipedia.org/wiki/Catalan_grammar#Articles
			'la ' => 'Catalan French Italian Spanish',
			'las ' => 'Spanish',
			'le ' => 'French Italian',
			'les ' => 'Catalan French',
			'lo ' => 'Italian Spanish',
			'los ' => 'Spanish',
			'os ' => 'Portuguese',
			#!# Codes still present
			'ta ' => 'grc',
			'ton ' => 'grc',
			'the ' => 'English',
			'um ' => 'Portuguese',
			'uma ' => 'Portuguese',
			'un ' => 'Catalan Spanish French Italian',
			'una ' => 'Catalan Spanish Italian',
			'une ' => 'French',
			'uno ' => 'Italian',
			'y ' => 'wel',
		);
		
		# End if not required to group by language
		if (!$groupByLanguage) {
			return $leadingArticles;
		}
		
		# Process the list, tokenising by language
		$leadingArticlesByLanguage = array ();
		foreach ($leadingArticles as $leadingArticle => $languages) {
			$languages = explode (' ', $languages);
			foreach ($languages as $language) {
				$leadingArticlesByLanguage[$language][] = $leadingArticle;
			}
		}
		
		/*
		# ACTUALLY, this is not required, because a space in the text is the delimeter
		# Arrange by longest-first
		$sortByStringLength = create_function ('$a, $b', 'return mb_strlen ($b) - mb_strlen ($a);');
		foreach ($leadingArticlesByLanguage as $language => $leadingArticles) {
			usort ($leadingArticles, $sortByStringLength);	// Sort by string length
			$leadingArticlesByLanguage[$language] = $leadingArticles;	// Overwrite list with newly-sorted list
		}
		*/
		
		# Return the array
		return $leadingArticlesByLanguage;
	}
	
	
	# Macro to convert language codes and notes for the 041 field; see: http://www.loc.gov/marc/bibliographic/bd041.html
	private function macro_languages041 ($value_ignored, $xml, $indicatorMode = false)
	{
		# Start the string
		$string = '';
		
		# Obtain any languages used in the record
		$languages = $this->xPathValues ($xml, '(//lang)[%i]', false);	// e.g. /records/2071/ has multiple
		$languages = array_unique ($languages);
		
		# Obtain any note containing "translation from [language(s)]"
		#!# Should *abs and *role also be considered?; see results from quick query: SELECT * FROM `catalogue_processed` WHERE `value` LIKE '%translated from original%', e.g. /records/1639/
		$notes = $this->xPathValues ($xml, '(//note)[%i]', false);
		$nonLanguageWords = array ('article', 'published', 'manuscript');	// e.g. /records/32279/ , /records/175067/ , /records/196791/
		$translationNotes = array ();
		foreach ($notes as $note) {
			# Perform a match; this is not using a starting at (^) match e.g. /records/190904/ which starts "English translation from Russian"
			if (preg_match ('/[Tt]ranslat(?:ion|ed) (?:from|reprint of)(?: original| the|) ([a-zA-Z]+)/i', $note, $matches)) {	// Deliberately not using strip_tags, as that would pick up Translation from <em>publicationname</em> which would not be wanted anyway
				// application::dumpData ($matches);
				$language = $matches[1];	// e.g. 'Russian', 'English'
				
				# Skip blacklisted non-language words; e.g. /records/44377/ which has "Translation of article from"
				if (in_array ($language, $nonLanguageWords)) {continue;}
				
				# Register the value
				$translationNotes[$note] = $language;
			}
		}
		
		// application::dumpData ($languages);
		// application::dumpData ($translationNotes);
		
		# In indicator mode, return the indicator at this point: if there is a $h, the first indicator is 1 and if there is no $h, the first indicator is 0
		if ($indicatorMode) {
			if ($translationNotes) {
				return '1';		// "1 - Item is or includes a translation"; e.g. /records/23776/
			} else {
				return '0';		// "0 - Item not a translation/does not include a translation"; e.g. /records/10009/ which is simply in another language
			}
		}
		
		# If no *lang field and no note regarding translation, do not include 041 field; e.g. /records/4355/
		if (!$languages && !$translationNotes) {return false;}
		
		# $a: If no *lang field but note regarding translation, use 'eng'; e.g. /records/23776/
		if (!$languages && $translationNotes) {
			$languages[] = 'English';
		}
		
		# $a: Map each language listed in *lang field to 3-digit code in Language Codes worksheet and include in separate ‡a subfield;
		$a = array ();
		foreach ($languages as $language) {
			$a[] = $this->lookupValue ('languageCodes', $fallbackKey = false, true, false, $language, 'MARC Code');
		}
		$string = implode ("{$this->doubleDagger}a", $a);	// First $a is the parser spec
		
		# $h: If *note includes 'translation from [language(s)]', map each language to 3-digit code in Language Codes worksheet and include in separate ‡h subfield; e.g. /records/4353/ , /records/2040/
		$h = array ();
		if ($translationNotes) {
			foreach ($translationNotes as $note => $language) {
				$marcCode = $this->lookupValue ('languageCodes', $fallbackKey = false, true, false, $language, 'MARC Code');
				if ($marcCode) {
					$h[] = $marcCode;
				} else {
					echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">record #{$this->recordId}</a>:</strong> the record included a language note but the language '<em>{$language}</em>'.</p>";
				}
			}
		}
		if ($h) {
			$string .= "{$this->doubleDagger}h" . implode ("{$this->doubleDagger}h", $h);	// First $a is the parser spec
		}
		
		# Return the result string
		return $string;
	}
	
	
	# Function to perform transliteration on specified subfields present in a full line; this is basically a tokenisation wrapper to macro_transliterate
	public function macro_transliterateSubfields ($value, $xml, $applyToSubfields, $language = false /* Always supplied by external callers */)
	{
		# If a forced language is not specified, obtain the language value for the record
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($xml, $xPath);
		}
		
		# Return unmodified if the language mode is default
		if ($language == 'default') {return $value;}
		
		# Ensure language is supported
		if (!isSet ($this->supportedReverseTransliterationLanguages[$language])) {return false;}	// Return false to ensure no result, e.g. /records/162154/
		
		# If the subfield list is specified as '*', treat this as all subfields present in the string (logically, a non-empty string will always have at least one subfield), so synthesize the applyToSubfields value from what is present in the supplied string
		if ($applyToSubfields == '*') {
			preg_match_all ("/{$this->doubleDagger}([a-z0-9])/", $value, $matches);
			$applyToSubfields = implode ($matches[1]);	// e.g. 'av' in the case of a 490; e.g. /records/15150/
		}
		
		# Explode subfield string and prepend the double-dagger
		$applyToSubfields = str_split ($applyToSubfields);
		foreach ($applyToSubfields as $index => $applyToSubfield) {
			$applyToSubfields[$index] = $this->doubleDagger . $applyToSubfield;
		}
		
		# Tokenise, e.g. array ([0] => "1# ", [1] => "‡a", [2] => "Chalyshev, Aleksandr Vasil'yevich.", [3] => "‡b", [4] => "Something else." ...
		$tokens = $this->tokeniseToSubfields ($value);
		
		# Work through the spread list
		$subfield = false;
		foreach ($tokens as $index => $string) {
			
			# Register then skip subfield indictors
			if (preg_match ("/^({$this->doubleDagger}[a-z0-9])$/", $string)) {
				$subfield = $string;
				continue;
			}
			
			# Skip if no subfield, i.e. previous field, assigned; this also catches cases of an opening first/second indicator pair
			if (!$subfield) {continue;}
			
			# Skip conversion if the subfield is not required to be converted
			if (!in_array ($subfield, $applyToSubfields)) {continue;}
			
			# Convert subfield contents
			$tokens[$index] = $this->macro_transliterate ($string, NULL, $language);
		}
		
		# Re-glue the string
		// application::dumpData ($tokens);
		$value = implode ($tokens);
		
		# Return the value
		return $value;
	}
	
	
	# Function to tokenise a string into subfields
	private function tokeniseToSubfields ($line)
	{
		# Tokenise, e.g. array ([0] => "1# ", [1] => "‡a", [2] => "Chalyshev, Aleksandr Vasil'yevich.", [3] => "‡b", [4] => "Something else." ...
		return preg_split ("/({$this->doubleDagger}[a-z0-9])/", $line, -1, PREG_SPLIT_DELIM_CAPTURE);
	}
	
	
	# Macro to perform transliteration
	private function macro_transliterate ($value, $xml, $language = false)
	{
		# If a forced language is not specified, obtain the language value for the record
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($xml, $xPath);
		}
		
		# End without output if no language, i.e. if default
		if (!$language) {return false;}
		
		# Ensure language is supported
		if (!isSet ($this->supportedReverseTransliterationLanguages[$language])) {return false;}	// Return false to ensure no result, unlike the main transliterate() routine
		
		# Pass the value into the transliterator
		#!# Need to clarify why there is still BGN latin remaining
		#!# Old transliteration needs to be upgraded in catalogue_processed and here in MARC generation - needs to be upgraded for 880-700 field, e.g. /records/1844/, but need to check all callers to macro_transliterate to see if they are consistently using Loc
		/*
			Callers are:
			880-490:transliterateSubfields(a) uses //ts (1240 shards)
			generate260 uses //pg[]/pu[], but 880 generate260(transliterated); e.g. /records/6996/ (test #58)
			MORE TODO
		*/
		#!# Need to determine whether the $lpt argument should ever be looked up, i.e. whether the $value represents a title and the record is in Russian
		$output = $this->transliteration->transliterateBgnLatinToCyrillic ($value, $lpt = false, $language);
		
		# Return the string
		return $output;
	}
	
	
	# Macro for generating the Leader
	private function macro_generateLeader ($value, $xml)
	{
		# Start the string
		$string = '';
		
		# Positions 00-04: "Computer-generated, five-character number equal to the length of the entire record, including itself and the record terminator. The number is right justified and unused positions contain zeros."
		$string .= '_____';		// Will be fixed-up later in post-processing, as at this point we do not know the length of the record
		
		# Position 05: One-character alphabetic code that indicates the relationship of the record to a file for file maintenance purposes.
		$string .= 'n';		// Indicates record is newly-input
		
		# Position 06: One-character alphabetic code used to define the characteristics and components of the record.
		#!# If merging, we would need to have a check that this matches
		switch ($this->form) {
			case 'Internet resource':
			case 'Microfiche':
			case 'Microfilm':
			case 'Online publication':
			case 'PDF':
				$value06 = 'a'; break;
			case 'Map':
				$value06 = 'e'; break;
			case 'DVD':
			case 'Videorecording':
				$value06 = 'g'; break;
			case 'CD':
			case 'Sound cassette':
			case 'Sound disc':
				$value06 = 'i'; break;
			case 'Poster':
				$value06 = 'k'; break;
			case '3.5 floppy disk':
			case 'CD-ROM':
			case 'DVD-ROM':
				$value06 = 'm'; break;
		}
		if (!$this->form) {$value06 = 'a';}
		$string .= $value06;
		
		# Position 07: Bibliographic level
		#!# If merging, we would need to have a check that this matches
		$position7Values = array (
			'/art/in'	=> 'a',
			'/art/j'	=> 'b',
			'/doc'		=> 'm',
			'/ser'		=> 's',
		);
		$recordType = $this->recordType ($xml);
		$string .= $position7Values[$recordType];
		
		# Position 08: Type of control
		$string .= '#';
		
		# Position 09: Character coding scheme
		$string .= 'a';
		
		# Position 10: Indicator count: Computer-generated number 2 that indicates the number of character positions used for indicators in a variable data field. 
		$string .= '2';
		
		# Position 11: Subfield code count: Computer-generated number 2 that indicates the number of character positions used for each subfield code in a variable data field. 
		$string .= '2';
		
		# Positions 12-16: Base address of data: Computer-generated, five-character numeric string that indicates the first character position of the first variable control field in a record.
		# "This is calculated and updated when the bib record is loaded into the Voyager database, so you if you're not able to calculate it at your end you could just set it to 00000."
		#!# If merging, we would probably overwrite whatever is currently present in Voyager as 00000, so the computer re-computes it
		$string .= '00000';
		
		# Position 17: Encoding level: One-character alphanumeric code that indicates the fullness of the bibliographic information and/or content designation of the MARC record. 
		#!# If merging, we think that # is better than 7; other values would need to be checked; NB the value '7' could be a useful means to determine Voyager records that are minimal (i.e. of limited use)
		$string .= '#';
		
		# Position 18: Descriptive cataloguing form
		#!# If merging, we would need to check with the UL that our 'a' trumps '#'; other values would need to be checked
		$string .= 'a';	// Denotes AACR2
		
		# Position 19: Multipart resource record level
		#!# If merging, we need to check that our '#' is equivalent to ' ' in Voyager syntax
		$string .= '#';	// Denotes not specified or not applicable
		
		# Position 20: Length of the length-of-field portion: Always contains a 4.
		$string .= '4';
		
		# Position 21: Length of the starting-character-position portion: Always contains a 5.
		$string .= '5';
		
		# Position 22: Length of the implementation-defined portion: Always contains a 0.
		$string .= '0';
		
		# Position 23: Undefined: Always contains a 0.
		$string .= '0';
		
		# Return the string
		return $string;
	}
	
	
	# Helper function to determine the record type
	#!#C Copied from generate008 class
	private function recordType ($xml)
	{
		# Determine the record type, used by subroutines
		$recordTypes = array (
			'/art/in',
			'/art/j',
			'/doc',
			'/ser',
		);
		foreach ($recordTypes as $recordType) {
			if ($this->xPathValue ($xml, $recordType)) {
				return $recordType;	// Match found
			}
		}
		
		# Not found
		return NULL;
	}
	
	
	# Macro for generating a datetime
	private function macro_migrationDatetime ($value, $xml)
	{
		# Date and Time of Latest Transaction; see: http://www.loc.gov/marc/bibliographic/bd005.html
		return date ('YmdHis.0');
	}
	
	
	# Macro for generating a datetime
	private function macro_migrationDate ($value, $xml)
	{
		# Date and Time of Latest Transaction; see: http://www.loc.gov/marc/bibliographic/bd005.html
		return date ('Ymd');
	}
	
	
	# Macro for generating the 007 field, Physical Description Fixed Field; see: http://www.loc.gov/marc/bibliographic/bd007.html
	private function macro_generate007 ($value, $xml)
	{
		# No form value
		if (!$this->form) {return 'ta';}
		
		# Define the values
		$field007values = array (
			'Map'					=> 'aj#|||||',
			'3.5 floppy disk'		=> 'cj#|a|||||||||',
			'CD-ROM'				=> 'co#|g|||||||||',
			'DVD-ROM'				=> 'co#|g|||||||||',
			'Internet resource'		=> 'cr#|n|||||||||',
			'Online publication'	=> 'cr#|n|||||||||',
			'PDF'					=> 'cu#|n||||a||||',
			'Microfiche'			=> 'h|#||||||||||',
			'Microfilm'				=> 'h|#||||||||||',
			'Poster'				=> 'kk#|||',
			'CD'					=> 'sd#|||gnn|||||',
			'Sound cassette'		=> 'ss#|||||||||||',
			'Sound disc'			=> 'sd#||||nn|||||',
			'DVD'					=> 'vd#|v||z|',
			'Videorecording'		=> 'vf#|u||u|',
		);
		
		# Look up the value and return it
		return $field007values[$this->form];
	}
	
	
	# Macro for generating the 008 field
	private function macro_generate008 ($value, $xml)
	{
		# Subclass, due to the complexity of this field
		require_once ('generate008.php');
		$generate008 = new generate008 ($this, $xml);
		if (!$value = $generate008->main ($error)) {
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">record #{$this->recordId}</a>:</strong> " . htmlspecialchars ($error) . '.</p>';
		}
		
		# Return the value
		return $value;
	}
	
	
	# Macro to describe Russian transliteration scheme used, for 546 $a
	#!# Needs to be made consistent with languages041 macro
	private function macro_isTransliterated ($language)
	{
		# Return string
		if ($language == 'Russian') {
			return 'Russian transliteration entered into original records using BGN/PCGN 1947 romanization of Russian; Cyrillic text in MARC 880 field(s) reverse transliterated from this by automated process; BGN/PCGN 1947 text then upgraded to Library of Congress romanization.';
		}
		
		# No match
		return false;
	}
	
	
	# Macro for generating an authors field, e.g. 100
	private function macro_generateAuthors ($value, $xml, $arg)
	{
		# Parse the arguments
		$fieldNumber = $arg;	// Default single argument representing the field number
		$flag = false;			// E.g. 'transliterated'
		if (substr_count ($arg, ',')) {
			list ($fieldNumber, $flag) = explode (',', $arg, 2);
		}
		
		# If running in transliteration mode, require a supported language
		$languageMode = 'default';
		if ($flag == 'transliterated') {
			if (!$languageMode = $this->getTransliterationLanguage ($xml)) {return false;}
		}
		
		# Return the value (which may be false, meaning no field should be created)
		return $this->authorsFields[$languageMode][$fieldNumber];
	}
	
	
	# Function to determine whether a language is supported, and return it if so
	private function getTransliterationLanguage ($xml)
	{
		#!# Currently checking only the first language
		$language = $this->xPathValue ($xml, '//lang[1]');
		if ($language && isSet ($this->supportedReverseTransliterationLanguages[$language])) {
			return $language;
		} else {
			return false;
		}
	}
	
	
	# Macro to add in the 880 subfield index
	private function macro_880subfield6 ($value, $xml, $masterField)
	{
		# End if no value
		if (!$value) {return $value;}
		
		# Determine the field instance index, starting at 0; this will always be 0 unless called from a repeatable
		#!# Repeatable field support not checked in practice yet as there are no such fields
		$this->field880subfield6FieldInstanceIndex[$masterField] = (isSet ($this->field880subfield6FieldInstanceIndex[$masterField]) ? $this->field880subfield6FieldInstanceIndex[$masterField] + 1 : 0);
		
		# For a multiline field, parse out the field number, which on subsequent lines will not necessarily be the same as the master field; e.g. /records/162152/
		if (substr_count ($value, "\n")) {
			
			# Normalise first line
			if (!preg_match ('/^([0-9]{3} )/', $value)) {
				$value = $masterField . ' ' . $value;
			}
			
			# Convert to field, indicators, and line
			preg_match_all ('/^([0-9]{3}) (.+)$/m', $value, $lines, PREG_SET_ORDER);
			
			# Construct each line
			$values = array ();
			foreach ($lines as $multilineSubfieldIndex => $line) {	// $line[1] will be the actual subfield code (e.g. 710), not the master field (e.g. 700), i.e. it may be a mutated value (e.g. 700 -> 710) as in e.g. /records/68500/ and similar in /records/150141/ , /records/183507/ , /records/196199/
				$values[] = $this->construct880Subfield6Line ($line[2], $line[1], $masterField, $this->field880subfield6FieldInstanceIndex[$masterField], $multilineSubfieldIndex);
			}
			
			# Compile the result back to a multiline string
			$value = implode ("\n" . '880 ', $values);
			
		} else {
			
			# Render the line
			$value = $this->construct880Subfield6Line ($value, $masterField, $masterField, $this->field880subfield6FieldInstanceIndex[$masterField]);
		}
		
		# Return the modified value
		return $value;
	}
	
	
	# Helper function to render a 880 subfield 6 line
	private function construct880Subfield6Line ($line, $masterField, $masterFieldIgnoringMutation, $fieldInstance, $multilineSubfieldIndex = false)
	{
		# Advance the index, which is incremented globally across the record; starting from 1
		$this->field880subfield6Index++;
		
		# Assemble the subfield for use in the 880 line
		$indexFormatted = str_pad ($this->field880subfield6Index, 2, '0', STR_PAD_LEFT);
		$subfield6 = $this->doubleDagger . '6 ' . $masterField . '-' . $indexFormatted;		// Decided to add space after $6 for clarity, to avoid e.g. '$6880-02' which is less clear than '$6 880-02'
		
		# Insert the subfield after the indicators; this is similar to insertSubfieldAfterMarcFieldThenIndicators but without the initial MARC field number
		if (preg_match ('/^([0-9#]{2}) (.+)$/', $line)) {	// Can't get a single regexp that makes the indicator block optional
			$line = preg_replace ('/^([0-9#]{2}) (.+)$/', "\\1 {$subfield6} \\2", $line);	// I.e. a macro block result line that includes the two indicators at the start (e.g. a 100), e.g. '1# $afoo'
		} else {
			$line = preg_replace ('/^(.+)$/', "{$subfield6} \\1", $line);	// I.e. a macro block result line that does NOT include the two indicators at the start (e.g. a 490), e.g. '$afoo'
		}
		
		# Register the link so that the reciprocal link can be added within the master field; this is registered either as an array (representing parts of a multiline string) or a string (for a standard field)
		$fieldKey = $masterFieldIgnoringMutation . '_' . $fieldInstance;	// e.g. 700_0; this uses the master field, ignoring the mutation, so that $this->field880subfield6ReciprocalLinks is indexed by the master field; this ensures reliable lookup in records such as /records/68500/ where a mutation exists in the middle of a master field (i.e. 700, 700, 710, 700, 700)
		$linkToken = $this->doubleDagger . '6 ' . '880' . '-' . $indexFormatted;
		if ($multilineSubfieldIndex !== false) {		// i.e. has supplied value
			$this->field880subfield6ReciprocalLinks[$fieldKey][$multilineSubfieldIndex] = $linkToken;
		} else {
			$this->field880subfield6ReciprocalLinks[$fieldKey] = $linkToken;
		}
		
		# Return the line
		return $line;
	}
	
	
	# Macro for generating the 245 field
	private function macro_generate245 ($value, $xml, $flag)
	{
		# If running in transliteration mode, require a supported language
		$languageMode = 'default';
		if ($flag == 'transliterated') {
			if (!$languageMode = $this->getTransliterationLanguage ($xml)) {return false;}
		}
		
		# Subclass, due to the complexity of this field
		require_once ('generate245.php');
		$generate245 = new generate245 ($this, $xml, $this->authorsFields, $languageMode);
		$value = $generate245->main ($error);
		if ($error) {
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">record #{$this->recordId}</a>:</strong> " . htmlspecialchars ($error) . '.</p>';
		}
		
		# Return the value, which may be false if transliteration not intended
		return $value;
	}
	
	
	# Macro for generating the 250 field
	private function macro_generate250 ($value, $xml, $ignored)
	{
		# Start an array of subfields
		$subfields = array ();
		
		# Implement subfield $a
		if ($a = $this->xPathValue ($xml, '/*/edn')) {
			$subfields[] = "{$this->doubleDagger}a" . $a;
		}
		
		# Implement subfield $b; examples given in the function
		if ($b = $this->generate250b ($value, $xml, $ignored, $this->authorsFields)) {
			$subfields[] = "{$this->doubleDagger}b" . $b;
		}
		
		# Return false if no subfields
		if (!$subfields) {return false;}
		
		# Compile the overall string
		$value = implode (' ', $subfields);
		
		# Ensure the value ends with a dot (even if punctuation already present); e.g. /records/2549/ , /records/4432/
		$value = $this->macro_dotEnd ($value, NULL);
		
		# Return the value
		return $value;
	}
	
	
	# Helper function for generating the 250 $b subfield
	private function generate250b ($value, $xml, $ignored)
	{
		# Use the role-and-siblings part of the 245 processor
		require_once ('generate245.php');
		$generate245 = new generate245 ($this, $xml, $this->authorsFields);
		
		# Create the list of subvalues if there is *ee?; e.g. /records/3887/ , /records/7017/ (has multiple *ee and multiple *n within this) , /records/45901/ , /records/168490/
		$subValues = array ();
		$eeIndex = 1;
		while ($this->xPathValue ($xml, "//ee[$eeIndex]")) {	// Check if *ee container exists
			$subValues[] = $generate245->roleAndSiblings ("//ee[$eeIndex]");
			$eeIndex++;
		}
		
		# Return false if no subvalues
		if (!$subValues) {return false;}
		
		# Implode values
		$value = implode ('; ', $subValues);
		
		# Return the value
		return $value;
	}
	
	
	# Macro for generating the 490 field
	#!# Currently almost all parts of the conversion system assume a single *ts - this will need to be fixed; likely also to need to expand 880 mirrors to be repeatable
	#!# Repeatability experimentally added to 490 at definition level, but this may not work properly as the field reads in *vno for instance; all derived uses of *ts need to be checked
	#!# Issue of missing $a needs to be resolved in original data
	public function macro_generate490 ($ts, $xml, $ignored, &$matchedRegexp = false, $reportGenerationMode = false)
	{
		# Obtain the *ts value or end
		if (!strlen ($ts)) {return false;}
		
		# Series titles:
		# Decided not to treat "Series [0-9]+$" as a special case that avoids the splitting into $a... ;$v...
		# This is because there is clear inconsistency in the records, e.g.: "Field Columbian Museum, Zoological Series 2", "Burt Franklin Research and Source Works Series 60"
		
		# Ensure the matched regexp, passed back by reference, is reset
		$matchedRegexp = false;
		
		# If the *ts contains a semicolon, this indicates specifically-cleaned data, so handle this explicitly; e.g. /records/2296/
		if (substr_count ($ts, ';')) {
			
			# Allocate the pieces before and after the semicolon; see: http://stackoverflow.com/a/717388/180733
			$parts = explode (';', $ts);
			$volumeNumber = trim (array_pop ($parts));
			$seriesTitle = trim (implode (';', $parts));
			$matchedRegexp = 'Explicit semicolon match';
			
		} else {
			
			# By default, treat as simple series title without volume number
			$seriesTitle = $ts;
			$volumeNumber = NULL;
			
			# Load the regexps list if not already done so
			if (!isSet ($this->regexps490)) {
				
				# Load the regexp list
				$this->regexps490Base = $this->muscatConversion->oneColumnTableToList ('volumeRegexps.txt');
				
				# Add implicit boundaries to each regexp
				$this->regexps490 = array ();
				foreach ($this->regexps490Base as $index => $regexp) {
					$this->regexps490[$index] = '^(.+)\s+(' . $regexp . ')$';
				}
			}
			
			# Find the first match, then stop, if any
			foreach ($this->regexps490 as $index => $regexp) {
				$delimeter = '~';	// Known not to be in the tables/volumeRegexps.txt list
				if (preg_match ($delimeter . $regexp . $delimeter, $ts, $matches)) {	// Regexps are permitted to have their own captures; matches 3 onwards are just ignored
					$seriesTitle = $matches[1];
					$volumeNumber = $matches[2];
					$matchedRegexp = ($index + 1) . ': ' . $this->regexps490Base[$index];		// Pass back by reference the matched regexp, prefixed by the number in the list, indexed from 1
					break;	// Relevant regexp found
				}
			}
		}
		
		# If there is a *vno, add that
		if (!$reportGenerationMode) {		// I.e. if running in MARC generation context, rather than for report generation
			if ($vno = $this->xPathValue ($xml, '//vno')) {
				$volumeNumber = ($volumeNumber ? $volumeNumber . ', ' : '') . $vno;		// If already present, e.g. /records/1896/ , append to existing, separated by comma; records with no number in the *ts like /records/101358/ will appear as normal
			}
		}
		
		# Start with the $a subfield
		$string = $this->doubleDagger . 'a' . $seriesTitle;
		
		# Deal with optional volume number
		if (strlen ($volumeNumber)) {
			
			# Strip any trailing ,. character in $a, and re-trim
			$string = preg_replace ('/^(.+)[.,]$/', '\1', $string);
			$string = trim ($string);
			
			# Add space-semicolon before $v if not already present
			if (mb_substr ($string, -1) != ';') {	// Normalise to end ";"
				$string .= ' ;';
			}
			if (mb_substr ($string, -2) != ' ;') {	// Normalise to end " ;"
				$string = preg_replace ('/;$/', ' ;', $string);
			}
			
			# Add the volume number; Bibcheck requires: "490: Subfield v must be preceeded by a space-semicolon"
			$string .= $this->doubleDagger . 'v' . $volumeNumber;
		}
		
		# Return the string
		return $string;
	}
	
	
	# Macro for generating the 541 field, which looks at *acq groups; it may generate a multiline result; see: https://www.loc.gov/marc/bibliographic/bd541.html
	#!# Support for *acc, which is currently having things like *acc/*date lost as is it not present elsewhere
	private function macro_generate541 ($value, $xml)
	{
		# Start a list of results
		$resultLines = array ();
		
		# Loop through each *acq in the record; e.g. multiple in /records/3959/
		$acqIndex = 1;
		while ($this->xPathValue ($xml, "//acq[$acqIndex]")) {
			
			# Start a line of subfields, used to construct the values; e.g. /records/176629/
			$subfields = array ();
			
			# Support $c - constructed from *fund / *kb / *sref
			/* Spec is:
				"*fund OR *kb OR *sref, unless the record contains a combination / multiple instances of these fields - in which case:
				- IF record contains ONE *sref and ONE *fund and NO *kb => ‡c*sref '--' *fund
				- IF record contains ONE *sref and ONE *kb and NO *fund => ‡c*sref '--' *kb"
			*/
			#!# Spec is unclear: What if there are more than one of these, or other combinations not shown here? Currently, items have any second (or third, etc.) lost, or e.g. *kb but not *sref would not show
			$fund = $this->xPathValues ($xml, "//acq[$acqIndex]/fund[%i]");	// Code		// e.g. multiple at /records/132544/ , /records/138939/
			#!# Should $kb be top-level, rather than within an *acq group? What happens if multiple *acq groups, which will each pick up the same *kb
			$kb   = $this->xPathValues ($xml, "//kb[%i]");					// Exchange
			$sref = $this->xPathValues ($xml, "//acq[$acqIndex]/sref[%i]");	// Supplier reference
			$c = false;
			if (count ($sref) == 1 && count ($fund) == 1 && !$kb) {
				$c = $sref[1] . '--' . $fund[1];
			} else if (count ($sref) == 1 && count ($kb) == 1 && !$fund) {
				$c = $sref[1] . '--' . $kb[1];
			} else if ($fund) {
				$c = $fund[1];
			} else if ($kb) {
				$c = $kb[1];
			} else if ($sref) {
				$c = $sref[1];
			}
			if ($c) {
				$subfields[] = "{$this->doubleDagger}c" . $c;
			}
			
			# Create $a, from *o - Source of acquisition
			if ($value = $this->xPathValue ($xml, "//acq[$acqIndex]/o")) {
				$subfields[] = "{$this->doubleDagger}a" . $value;
			}
			
			# Create $d, from *date - Date of acquisition
			if ($value = $this->xPathValue ($xml, "//acq[$acqIndex]/date")) {
				$subfields[] = "{$this->doubleDagger}d" . $value;
			}
			
			#!# *acc/*ref?
			
			# Create $h, from *pr - Purchase price
			if ($value = $this->xPathValue ($xml, "//acq[$acqIndex]/pr")) {
				$subfields[] = "{$this->doubleDagger}h" . $value;
			}
			
			# Register the line if subfields have been created
			if ($subfields) {
				$subfields[] = "{$this->doubleDagger}5" . 'UkCU-P';	// Institution to which field applies, i.e. SPRI
				$resultLines[] = implode (' ', $subfields);
			}
			
			# Next *acq
			$acqIndex++;
		}
		
		# End if no lines
		if (!$resultLines) {return false;}
		
		# Implode the list
		$result = implode ("\n" . '541 0# ', $resultLines);
		
		# Return the result
		return $result;
	}
	
	
	# Macro to determine if a value is not surrounded by round brackets
	private function macro_isNotRoundBracketed ($value)
	{
		return ((mb_substr ($value, 0, 1) != '(') || (mb_substr ($value, -1) != ')') ? $value : false);
	}
	
	
	# Macro to determine if a value is surrounded by round brackets
	private function macro_isRoundBracketed ($value)
	{
		return ((mb_substr ($value, 0, 1) == '(') && (mb_substr ($value, -1) == ')') ? $value : false);
	}
	
	
	# Macro to look up a *ks (UDC) value
	private function macro_addLookedupKsValue ($value, $xml)
	{
		# End if no value
		if (!strlen ($value)) {return $value;}
		
		# Load the UDC translation table if not already loaded
		if (!isSet ($this->udcTranslations)) {
			$this->udcTranslations = $this->databaseConnection->selectPairs ($this->settings['database'], 'udctranslations', array (), array ('ks', 'kw'));
		}
		
		# Split out any additional description string
		$description = false;
		if (preg_match ("/^(.+)\[(.+)\]$/", $value, $matches)) {
			$value = $matches[1];
			$description = $matches[2];
		}
		
		# Skip if a known value (before brackes, which are now stripped) to be ignored
		if (in_array ($value, $this->ksStatusTokens)) {return false;}
		
		# Ensure the value is in the table
		if (!isSet ($this->udcTranslations[$value])) {
			// NB For the following error, see also /reports/periodicalpam/ which covers scenario of records temporarily tagged as 'MPP'
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">record #{$this->recordId}</a>:</strong> 650 UDC field '<em>{$value}</em>' is not a valid UDC code.</p>";
			return false;
		}
		
		# Construct the result string
		$string = strtolower ('UDC') . $this->doubleDagger . 'a' . $value . ' -- ' . $this->udcTranslations[$value] . ($description ? ": {$description}" : false);
		
		# Return the result string
		return $string;
	}
	
	
	# Macro to look up a *rpl value
	private function macro_lookupRplValue ($value, $xml)
	{
		# Fix up incorrect data
		if ($value == 'E1') {$value = 'E2';}
		if ($value == 'H' ) {$value = 'H1';}
		
		# Define the *rpl mappings
		$mappings = array (
			'A'		=> 'Geophysical sciences (general)',
			'B'		=> 'Geology and soil sciences',
			'C'		=> 'Oceanography, hydrography and hydrology',
			'D'		=> 'Atmospheric sciences',
			// 'E1'	=> '',	// Error in original data: should be E2
			'E2'	=> 'Glaciology: general',
			'E3'	=> 'Glaciology: instruments and methods',
			'E4'	=> 'Glaciology: physics and chemistry of ice',
			'E5'	=> 'Glaciology: land ice',
			'E6'	=> 'Glaciology: floating ice',
			'E7'	=> 'Glaciology: glacial geology and ice ages',
			'E8'	=> 'Glaciology: frost action and permafrost',
			'E9'	=> 'Glaciology: meteorology and climatology',
			'E10'	=> 'Glaciology: snow and avalanches',
			'E11'	=> 'Glaciology: glaciohydrology',
			'E12'	=> 'Glaciology: frozen ground / snow and ice engineering',
			'E13'	=> 'Glaciology: glacioastronomy',
			'E14'	=> 'Glaciology: biological aspects of ice and snow',
			'F'		=> 'Biological sciences',
			'G'		=> 'Botany',
			// 'H'	=> '',	// Error in original data: should be H1
			'H1'	=> 'Zoology: general',
			'H2'	=> 'Zoology: invertebrates',
			'H3'	=> 'Zoology: vertebrates',
			'H4'	=> 'Zoology: fish',
			'H5'	=> 'Zoology: birds',
			'H6'	=> 'Zoology: mammals',
			'I'		=> 'Medicine and health',
			'J'		=> 'Social sciences',
			'K'		=> 'Economics and economic development',
			'L'		=> 'Communication and transportation',
			'M'		=> 'Engineering and construction',
			'N'		=> 'Renewable resources',
			'O'		=> 'Not in Polar and Glaciological Abstracts',
			'P'		=> 'Non-renewable resources',
			'Q'		=> 'Land use, planning and recreation',
			'R'		=> 'Arts',
			'S'		=> 'Literature and Language',
			'T'		=> 'Social anthropology and ethnography',
			'U'		=> 'Archaeology',
			'V'		=> 'History',
			'W'		=> 'Expeditions and exploration',
			'X'		=> 'Biographies and obituaries',
			'Y'		=> 'Descriptive general accounts',
			'Z'		=> 'Miscellaneous',
		);
		
		# Ensure the value is in the table
		if (!isSet ($mappings[$value])) {
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">record #{$this->recordId}</a>:</strong> 650 PGA field {$value} is not a valid PGA code letter.</p>";
			return false;
		}
		
		# Construct the result string
		$string = 'local' . $this->doubleDagger . 'a' . $value . ' -- ' . $mappings[$value];
		
		# Return the result string
		return $string;
	}
	
	
	# Generalised lookup table function
	public function lookupValue ($table, $fallbackKey, $caseSensitiveComparison = true, $stripBrackets = false, $value, $field)
	{
		# Load the lookup table
		$lookupTable = $this->loadLookupTable ($table, $fallbackKey, $caseSensitiveComparison, $stripBrackets);
		
		# If required, strip surrounding square/round brackets if present, e.g. "[Frankfurt]" => "Frankfurt" or "(Frankfurt)" => "Frankfurt"
		# Note that '(' is an odd Muscat convention, and '[' is the MARC convention
		# Note: In the actual data for 260, preserve square brackets, but remove round brackets if present
		$valueOriginal = $value;	// Cache
		if ($stripBrackets) {
			if (preg_match ('/^[\[|\(](.+)[\]|\)]$/', $value, $matches)) {
				$value = $matches[1];
			}
		}
		
		# If doing case-insensitive comparison, convert the supplied value to lower case
		if (!$caseSensitiveComparison) {
			$value = mb_strtolower ($value);
		}
		
		# Ensure the string is present
		if (!isSet ($lookupTable[$value])) {
			echo "<p class=\"warning\">In the {$table} table, the value '<em>{$valueOriginal}</em>' is not present in the table.</p>";
			return NULL;
		}
		
		# Compile the result
		$result = $lookupTable[$value][$field];
		
		# Trim, in case of line-ends
		$result = trim ($result);
		
		# Return the result
		return $result;
	}
	
	
	# Function to load and process a lookup table
	private function loadLookupTable ($table, $fallbackKey, $caseSensitiveComparison, $stripBrackets)
	{
		# Lookup from cache if present
		if (isSet ($this->lookupTablesCache[$table])) {
			return $this->lookupTablesCache[$table];
		}
		
		# Get the data table
		$lookupTable = file_get_contents ($this->applicationRoot . '/tables/' . $table . '.tsv');
		
		# Undo Muscat escaped asterisks @*
		$lookupTable = $this->muscatConversion->unescapeMuscatAsterisks ($lookupTable);
		
		# Convert to TSV
		$lookupTable = trim ($lookupTable);
		require_once ('csv.php');
		$lookupTableRaw = csv::tsvToArray ($lookupTable, $firstColumnIsId = true);
		
		# Define the fallback value in case that is needed
		if (!isSet ($lookupTableRaw[''])) {
			$lookupTableRaw[''] = $lookupTableRaw[$fallbackKey];
		}
		$lookupTableRaw[false]	= $lookupTableRaw[$fallbackKey];	// Boolean false also needs to be defined because no-match value from an xPathValue() lookup will be false
		
		# Obtain required resources
		$diacriticsTable = $this->muscatConversion->diacriticsTable ();
		
		# Perform conversions on the key names
		$lookupTable = array ();
		foreach ($lookupTableRaw as $key => $values) {
			
			# Convert diacritics
			$key = strtr ($key, $diacriticsTable);
			
			# Strip surrounding square/round brackets if present, e.g. "[Frankfurt]" => "Frankfurt" or "(Frankfurt)" => "Frankfurt"
			if ($stripBrackets) {
				if (preg_match ('/^[\[|\(](.+)[\]|\)]$/', $key, $matches)) {
					$key = $matches[1];
				}
				
				/*
				# Sanity-checking test while developing
				if (isSet ($lookupTable[$key])) {
					if ($values !== $lookupTable[$key]) {
						echo "<p class=\"warning\">In the {$table} definition, <em>{$key}</em> for field <em>{$field}</em> has inconsistent value when comapring the bracketed and non-bracketed versions.</p>";
						return NULL;
					}
				}
				*/
			}
			
			# Register the converted value
			$lookupTable[$key] = $values;
		}
		
		# If doing case-insensitive comparison, convert values to lower case
		if (!$caseSensitiveComparison) {
			$lookupTableLowercaseKeys = array ();
			foreach ($lookupTable as $key => $values) {
				$key = mb_strtolower ($key);
				$lookupTableLowercaseKeys[$key] = $values;
			}
			$lookupTable = $lookupTableLowercaseKeys;
		}
		
		/*
		# Sanity-checking test while developing
		$expectedLength = 1;	// Manually needs to be changed to 3 for languageCodes -> Marc Code
		foreach ($lookupTable as $entry => $values) {
			if (mb_strlen ($values[$field]) != $expectedLength) {
				echo "<p class=\"warning\">In the {$table} definition, <em>{$entry}</em> for field <em>{$field}</em> has invalid syntax.</p>";
				return NULL;
			}
		}
		*/
		
		# Register to cache; this assumes that parameters will be consistent
		$this->lookupTablesCache[$table] = $lookupTable;
		
		# Return the table
		return $lookupTable;
	}
	
	
	# Macro to generate the 500 (displaying free-form text version of 773), whose logic is closely associated with 773
	private function macro_generate500 ($value, $xml, $parameter_unused)
	{
		#!# In the case of all records whose serial title is listed in /reports/seriestitlemismatches3/ , need to branch at this point and create a 500 note from the local information (i.e. the record itself, not the parent, as in 773 below)
		
		
		# Get the data from the 773
		if (!$result = $this->macro_generate773 ($value, $xml, $parameter_unused, $mode500 = true)) {return false;}
		
		# Strip subfield indicators
		$result = $this->stripSubfields ($result);
		
		# Prefix 'In: ' at the start
		$result = "{$this->doubleDagger}a" . 'In: ' . $result;
		
		# Return the result
		return $result;
	}
	
	
	# Function to provide subfield stripping
	public function stripSubfields ($string)
	{
		return preg_replace ("/({$this->doubleDagger}[a-z0-9])/", '', $string);
	}
	
	
	# Function to look up the host record, if any
	private function lookupHostRecord ($xml)
	{
		# Up-front, obtain the host ID (if any) from *kg, used in both 773 and 500
		if (!$hostId = $this->xPathValue ($xml, '//k2/kg')) {return NULL;}
		
		# Obtain the processed MARC record; note that createMarcRecords processes the /doc records before /art/in records
		$hostRecord = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_marc', 'marc', $conditions = array ('id' => $hostId));
		
		# If there is no host record yet (because the ordering is such that it has not yet been reached), register the child for reprocessing in the second-pass phase
		if (!$hostRecord) {
			
			# Validate as a separate check that the host record exists; if this fails, the record itself is wrong and therefore report this error
			if (!$hostRecordXmlExists = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_xml', 'id', $conditions = array ('id' => $hostId))) {
				echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">record #{$this->recordId}</a>:</strong> Cannot match *kg, as there is no host record <a href=\"{$this->baseUrl}/records/{$hostId}/\">#{$hostId}</a>.</p>";
			}
			
			# The host MARC record has not yet been processed, therefore register the child for reprocessing in the second-pass phase
			$this->secondPassRecordId = $this->recordId;
		}
		
		# Return the host record
		return $hostRecord;
	}
	
	
	# Macro to generate the 773 (Host Item Entry) field; see: http://www.loc.gov/marc/bibliographic/bd773.html ; e.g. /records/2071/
	#!# 773 is not currently being generated for /art/j analytics (generally *location=Periodical); this is because of the *kg check below; the spec needs to define some implementation for this; for *location=Pam, the same information goes in a 500 field rather than a 773; again this needs a spec
	private function macro_generate773 ($value, $xml, $parameter_unused, $mode500 = false)
	{
		# Start a result
		$result = '';
		
		# Only relevant if there is a host record (i.e. has a *kg which exists); records will usually be /art/in or /art/j only, but there are some /doc records
		#!# At present this leaves tens of thousands of journal analytics without links (because they don't have explicit *kg fields)
		if (!$this->hostRecord) {return false;}
		
		# Parse out the host record
		$marc = $this->parseMarcRecord ($this->hostRecord);
		
		# Obtain the record type
		$recordType = $this->recordType ($xml);
		
		# Start a list of subfields
		$subfields = array ();
		
		# Add 773 ‡a; *art/*in records only
		#!# Needs implementation for things that are /art/j
		if ($recordType == '/art/in') {
			
			# If the host record has a 100 field, copy in the 1XX (Main entry heading) from the host record, omitting subfield codes; otherwise use 245 $c
			if (isSet ($marc['100'])) {
				$aSubfieldValue = $this->combineSubfieldValues ('a', $marc['100']);
			} else if (isSet ($marc['245'])) {
				$aSubfieldValue = $this->combineSubfieldValues ('a', $marc['245'], array ('c'));
			}
			
			#!# Need to strip '.' (to avoid e.g. "Martin Smith.,") if not an initial, or initials (like Eds.); this may need to be a crude string replacement because we don't have access to the tokenisation
			
			
			# Add a comma at the end; we know that there will be always be something following this, because in the (current) /art/in context, all parents are know to have a title
			$subfields[] = $aSubfieldValue . ',';
		}
		
		# Add 773 ‡t: Copy in the 245 (Title) ‡a and ‡b from the host record, omitting subfield codes, stripping leading articles
		if (isSet ($marc['245'])) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($xml, $xPath);
			if (!$language) {$language = 'English';}
			$subfields[] = $this->combineSubfieldValues ('t', $marc['245'], array ('a', 'b'), ', ', $language);
		}
		
		# Add 773 ‡d: Copy in the 260 (Place, publisher, and date of publication) from the host record, omitting subfield codes; *art/*in records only
		if ($recordType == '/art/in') {
			if (isSet ($marc['260'])) {
				
				# If publisher and year are present, use (no-space)-comma-space for the splitter between those two, combining them before colon splitting of other fields; e.g. /records/2614/ ; confirmed that, if reaching this point, $marc['260'][0]['subfields'] always contains 3 subfields
				if (isSet ($marc['260'][0]['subfields']['b']) && isSet ($marc['260'][0]['subfields']['c'])) {
					$subfieldBValue = rtrim ($marc['260'][0]['subfields']['b'][0]);	// Extract to avoid double-comma in next line, e.g. /records/103259/
					$marc['260'][0]['subfields']['_'][0] = $subfieldBValue . (substr ($subfieldBValue, -1) != ',' ? ',' : '') . ' ' . $marc['260'][0]['subfields']['c'][0];	// Make a virtual field, $_
					unset ($marc['260'][0]['subfields']['b']);
					unset ($marc['260'][0]['subfields']['c']);
				}
				
				# Split by colon
				$subfields[] = $this->combineSubfieldValues ('d', $marc['260'], array (), ': ', false, $normaliseTrailingImplode = true);
			}
		}
		
		# Add 773 ‡g: *pt (Related parts) [of child record, i.e. not host record]; *art/*j only
		if ($recordType == '/art/j') {
			if ($pt = $this->xPathValue ($xml, '/art/j/pt')) {	// e.g. /records/14527/
				$subfields[] = "{$this->doubleDagger}g" . $pt;
			}
		}
		
		# Except in 500 mode, add 773 ‡w: Copy in the 001 (Record control number) from the host record; this will need to be modified in the target Voyager system post-import
		#!# For one of the merge strategies, this number will be known
		if (!$mode500) {
			$subfields[] = "{$this->doubleDagger}w" . $marc['001'][0]['line'];
		}
		
		#!# Might need date also
		
		#!# Might need volume also
		
		# Compile the result
		$result = implode (' ', $subfields);
		
		# Return the result
		return $result;
	}
	
	
	# Function to combine subfield values in a line to a single string
	private function combineSubfieldValues ($parentSubfield, $field, $onlySubfields = array (), $implodeSubfields = ', ', $stripLeadingArticleLanguage = false, $normaliseTrailingImplode = false)
	{
		# If normalising the implode so that an existing trailing string (e.g. ':') is present, remove it to avoid duplicates, e.g. /records/103259/
		if ($normaliseTrailingImplode) {
			$token = trim ($implodeSubfields);
			foreach ($field[0]['subfields'] as $subfield => $subfieldValues) {
				foreach ($subfieldValues as $subfieldKey => $subfieldValue) {
					$subfieldValue = trim ($subfieldValue);
					if (substr ($subfieldValue, 0 - strlen ($token)) == $token) {
						$field[0]['subfields'][$subfield][$subfieldKey] = trim (substr ($subfieldValue, 0, 0 - strlen ($token)));
					}
				}
			}
		}
		
		# Create the result
		$fieldValues = array ();
		foreach ($field[0]['subfields'] as $subfield => $subfieldValues) {	// Only [0] used, as it is known that all fields using this function are non-repeatable fields
			
			# Skip if required
			if ($onlySubfields && !in_array ($subfield, $onlySubfields)) {continue;}
			
			# Add the field values for this subfield
			$fieldValues[] = implode (', ', $subfieldValues);
		}
		
		# Fix up punctuation
		$totalFieldValues = count ($fieldValues);
		foreach ($fieldValues as $index => $fieldValue) {
			
			# Avoid double commas after joining; e.g. /records/2614/
			if (($index + 1) != $totalFieldValues) {	// Do not consider last in loop
				if (mb_substr ($fieldValue, -1) == ',') {
					$fieldValue = mb_substr ($fieldValue, 0, -1);
				}
			}
			
			# Avoid ending a field with " /"
			if (mb_substr ($fieldValue, -1) == '/') {
				$fieldValue = trim (mb_substr ($fieldValue, 0, -1)) . '.';
			}
			
			# Register the amended value
			$fieldValues[$index] = $fieldValue;
		}
		
		#!# Need to handle cases like /records/191969/ having a field value ending with :
		
		# Compile the value
		$value = implode ($implodeSubfields, $fieldValues);
		
		# Strip leading article if required; e.g. /records/3075/ , /records/3324/ , /records/5472/ (German)
		if ($stripLeadingArticleLanguage) {
			$value = $this->stripLeadingArticle ($value, $stripLeadingArticleLanguage);
		}
		
		# Compile the result
		$result = "{$this->doubleDagger}{$parentSubfield}" . $value;
		
		# Return the result
		return $result;
	}
	
	
	# Function to strip a leading article
	private function stripLeadingArticle ($string, $language)
	{
		# Get the list of leading articles
		$leadingArticles = $this->leadingArticles ();
		
		# End if language not supported
		if (!isSet ($leadingArticles[$language])) {return $string;}
		
		# Strip from start if present
		$list = implode ('|', $leadingArticles[$language]);
		$string = preg_replace ("/^({$list})(.+)$/i", '\2', $string);	// e.g. /records/3075/ , /records/3324/
		$string = mb_ucfirst ($string);
		
		# Return the amended string
		return $string;
	}
	
	
	# Macro to parse out a MARC record into subfields
	public function parseMarcRecord ($marc, $parseSubfieldsToPairs = true)
	{
		# Parse the record
		preg_match_all ('/^([LDR0-9]{3}) (?:([#0-9]{2}) )?(.+)$/mu', $marc, $matches, PREG_SET_ORDER);
		
		# Convert to key-value pairs; in the case of repeated records, the value is converted to an array
		$record = array ();
		foreach ($matches as $match) {
			$fieldNumber = $match[1];
			$record[$fieldNumber][] = array (
				'fullLine'		=> $match[0],
				'line'			=> $match[3],
				'indicators'	=> $match[2],
				'subfields'		=> ($parseSubfieldsToPairs ? $this->parseSubfieldsToPairs ($match[3]) : $match[3]),
			);
		}
		
		// application::dumpData ($record);
		
		# Return the record
		return $record;
	}
	
	
	# Function to parse subfields into key-value pairs
	public function parseSubfieldsToPairs ($line, $knownSingular = false)
	{
		# Tokenise
		$tokens = $this->tokeniseToSubfields ($line);
		
		# Convert to key-value pairs
		$subfields = array ();
		$subfield = false;
		foreach ($tokens as $index => $string) {
			
			# Register then skip subfield indictors
			if (preg_match ("/^{$this->doubleDagger}([a-z0-9])$/", $string, $matches)) {
				$subfield = $matches[1];
				continue;
			}
			
			# Skip if no subfield, i.e. previous field, assigned; this also catches cases of an opening first/second indicator pair
			if (!$subfield) {continue;}
			
			# Register the subfields, resulting in e.g. ($a => $aFoo, $b => $bBar)
			if ($knownSingular) {
				$subfields[$subfield] = $string;	// If known to be singular, avoid indexing by [0]
			} else {
				$subfields[$subfield][] = $string;
			}
		}
		
		# Return the subfield pairs
		return $subfields;
	}
	
	
	# Macro to lookup periodical locations, which may generate a multiline result; see: https://www.loc.gov/marc/holdings/hd852.html
	private function macro_generate852 ($value, $xml)
	{
		# Start a list of results
		$resultLines = array ();
		
		# Get the locations
		$locations = $this->xPathValues ($xml, '//loc[%i]/location');
		
		# Loop through each location
		foreach ($locations as $index => $location) {
			
			# Start record with 852 7#  ‡2camdept
			$result = 'camdept';	// NB The initial "852 7#  ‡2" is stated within the parser definition and line splitter
			
			# Is *location 'Not in SPRI' OR does *location start with 'Shelved with'?
			if ($location == 'Not in SPRI' || preg_match ('/^Shelved with/', $location)) {
				
				# Does the record contain another *location field?
				if (count ($locations) > 1) {
					
					# Does the record contain any  other *location fields that have not already been mapped to 852 fields?; If not, skip to next, or end
					continue;
					
				} else {
					
					# Is *location 'Not in SPRI'?; if yes, add to record: ‡z Not in SPRI; if no, Add to record: ‡c <*location>
					if ($location == 'Not in SPRI') {
						#!# $bSPRI-NIS logic needs checking
						$result .= " {$this->doubleDagger}bSPRI-NIS";
						$result .= " {$this->doubleDagger}zNot in SPRI";
					} else {
						$result .= " {$this->doubleDagger}c" . $location;
					}
					
					# Register this result
					$resultLines[] = $result;
					
					# End 852 field; No more 852 fields required
					break;	// Break main foreach loop
				}
				
			} else {
				
				# This *location will be referred to as *location_original; does *location_original appear in the location codes list?
				$locationStartsWith = false;
				$locationCode = false;
				foreach ($this->locationCodes as $startsWith => $code) {
					if (preg_match ("|^{$startsWith}|", $location)) {
						$locationStartsWith = $startsWith;
						$locationCode = $code;
						break;
					}
				}
				if ($locationCode) {
					
					# Add corresponding Voyager location code to record: ‡b SPRI-XXX
					$result .= " {$this->doubleDagger}b" . $locationCode;
					
					# Does the record contain another *location field that starts with 'Shelved with'?; See: /records/204332/
					if ($shelvedWithIndex = application::preg_match_array ('^Shelved with', $locations, true)) {
						
						# This *location will be referred to as *location_shelved; Add to record: ‡c <*location_shelved>
						$result .= " {$this->doubleDagger}c" . $locations[$shelvedWithIndex];
					}
					
					# Does *location_original start with a number?
					if (preg_match ('/^[0-9]/', $location)) {
						
						# Add to record: ‡h <*location_original>
						$result .= " {$this->doubleDagger}h" . $location;
						
					} else {
						
						# Remove the portion of *location that maps to a Voyager location code (i.e. the portion that appears in the location codes list) - the remainder will be referred to as *location_trimmed
						$locationTrimmed = preg_replace ("|^{$locationStartsWith}|", '', $location);
						$locationTrimmed = trim ($locationTrimmed);
						
						# Is *location_trimmed empty?; If no, Add to record: ‡h <*location_trimmed> ; e.g. /records/37181/
						if (strlen ($locationTrimmed)) {
							$result .= " {$this->doubleDagger}h" . $locationTrimmed;
						}
					}
					
				} else {
					
					# Add to record: ‡x <*location_original>
					$result .= " {$this->doubleDagger}x" . $location;
				}
				
				# Does the record contain another *location field that is equal to 'Not in SPRI'?
				if ($notInSpriLocationIndex = application::preg_match_array ('^Not in SPRI$', $locations, true)) {
					
					# Add to record: ‡z Not in SPRI
					#!# $bSPRI-NIS logic needs checking
					$result .= " {$this->doubleDagger}bSPRI-NIS";
					$result .= " {$this->doubleDagger}zNot in SPRI";
				}
			}
			
			# If records are missing, add public note; e.g. /records/1014/ , and /records/25728/ ; a manual query has been done that no record has BOTH "Not in SPRI" (which would result in $z already existing above) and "MISSING" using "SELECT * FROM catalogue_xml WHERE xml like BINARY '%MISSING%' and xml LIKE '%Not in SPRI%';"
			# Note that this will set a marker for each *location; the report /reports/multiplelocationsmissing/ lists these cases, which will need to be fixed up post-migration - we are unable to work out from the Muscat record which *location the "MISSING" refers to
			#!# Ideally also need to trigger this in cases where the record has note to this effect; or check that MISSING exists in all such cases by checking and amending records in /reports/notemissing/
			$ksValues = $this->xPathValues ($xml, '//k[%i]/ks');
			foreach ($ksValues as $ksValue) {
				if (substr_count ($ksValue, 'MISSING')) {		// Covers 'MISSING' and e.g. 'MISSING[2004]' etc.; e.g. /records/1323/ ; data checked to ensure that the string always appears as upper-case "MISSING" ; all records checked that MISSING* is always in the format ^MISSING\[.+\]$, using "SELECT * FROM catalogue_processed WHERE field = 'ks' AND value like  'MISSING%' AND value !=  'MISSING' AND value NOT REGEXP '^MISSING\\[.+\\]$'"
					$result .= " {$this->doubleDagger}z" . 'Missing';
					break;
				}
			}
			
			# Register this result
			$resultLines[] = trim ($result);
		}
		
		# Implode the list
		$result = implode ("\n" . "852 7# {$this->doubleDagger}2", $resultLines);
		
		# Return the result
		return $result;
	}
	
	
	# Macro to generate 916, which is based on *acc/*ref *acc/*date pairs
	private function macro_generate916 ($value, $xml)
	{
		# Define the supported *acc/... fields that can be included
		#!# Not sure if con, recr, status should be present; ref and date are confirmed fine
		$supportedFields = array ('ref', 'date', 'con', 'recr');
		
		# Loop through each *acq in the record; e.g. multiple in /records/3959/
		$acc = array ();
		$accIndex = 1;
		while ($this->xPathValue ($xml, "//acc[$accIndex]")) {
			
			# Capture *acc/*ref and *acc/*date in this grouping
			$components = array ();
			foreach ($supportedFields as $field) {
				if ($component = $this->xPathValue ($xml, "//acc[$accIndex]/{$field}")) {
					$components[] = $component;
				}
			}
			
			# Register this *acc group if components have been generated
			if ($components) {
				$acc[] = implode (' ', $components);
			}
			
			# Next *acc
			$accIndex++;
		}
		
		# End if none
		if (!$acc) {return false;}
		
		# Compile the components
		$result = implode ('; ', $acc);
		
		# Return the result
		return $result;
	}
	
	
	# Macro to generate a 917 record for the supression reason
	private function macro_showSuppressionReason ($value, $xml)
	{
		# End if no suppress reason(s)
		if (!$this->suppressReasons) {return false;}
		
		# Explode by comma
		$suppressReasons = explode (', ', $this->suppressReasons);
		
		# Create a list of results
		$resultLines = array ();
		foreach ($suppressReasons as $suppressReason) {
			$resultLines[] = 'Suppression reason: ' . $suppressReason . ' (' . $this->suppressionScenarios[$suppressReason][0] . ')';
		}
		
		# Implode the list
		$result = implode ("\n" . "917 ## {$this->doubleDagger}a", $resultLines);
		
		# Return the result line/multiline
		return $result;
	}
	
	
	# Macro to determine cataloguing status; this uses values from both *ks OR *status, but the coexistingksstatus report is marked clean, ensuring that no data is lost
	private function macro_cataloguingStatus ($value, $xml)
	{
		# Return *ks if on the list; separate multiple values with semicolon, e.g. /records/205603/
		$ksValues = $this->xPathValues ($xml, '//k[%i]/ks');
		$results = array ();
		foreach ($ksValues as $ks) {
			$ksBracketsStrippedForComparison = (substr_count ($ks, '[') ? strstr ($ks, '[', true) : $ks);	// So that "MISSING[2007]" matches against MISSING, e.g. /records/2823/ , /records/3549/
			if (in_array ($ksBracketsStrippedForComparison, $this->ksStatusTokens)) {
				$results[] = $ks;	// Actual *ks in the data, not the comparator version without brackets
			}
		}
		if ($results) {
			return implode ('; ', $results);
		}
		
		# Otherwise return *status (e.g. /records/1373/ ), except for records marked explicitly to be suppressed (e.g. /records/10001/ ), which is a special keyword not intended to appear in the record output
		$status = $this->xPathValue ($xml, '//status');
		if ($status == $this->suppressionStatusKeyword) {return false;}
		return $status;
	}
}

?>