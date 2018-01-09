<?php

# Class to handle conversion of the data to MARC format
class marcConversion
{
	# Getter properties
	private $errorHtml = '';
	private $marcPreMerge = NULL;
	private $sourceRegistry = array ();
	
	# Caches
	private $lookupTablesCache = array ();
	
	# Resources
	private $isbn;
	
	
	# Constructor
	public function __construct ($muscatConversion, $transliteration, $supportedReverseTransliterationLanguages, $mergeTypes, $ksStatusTokens, $locationCodes, $diacriticsTable, $suppressionStatusKeyword, $suppressionScenarios)
	{
		# Create class property handles to the parent class
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
		$this->diacriticsTable = $diacriticsTable;
		$this->suppressionStatusKeyword = $suppressionStatusKeyword;
		$this->suppressionScenarios = $suppressionScenarios;
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
		# Get the list of leading articles
		$this->leadingArticles = $this->leadingArticles ();
		
		# Load ISBN support
		$this->isbn = $this->loadIsbnValidationLibrary ();
		
		# Load authors support
		$languageModes = array_merge (array ('default'), array_keys ($this->supportedReverseTransliterationLanguages));		// Feed in the languages list, with 'default' as the first
		require_once ('generateAuthors.php');
		$this->generateAuthors = new generateAuthors ($this, $languageModes);
		
		# Load generate008 support
		#!# Use of marcConversion::lookupValue in generate008 may be creating a circular reference
		require_once ('generate008.php');
		$this->generate008 = new generate008 ($this);
		
		# Load generate245 support
		require_once ('generate245.php');
		$this->generate245 = new generate245 ($this);
		
	}
	
	
	# Getter for error HTML string
	public function getErrorHtml ()
	{
		# End if none
		if (!$this->errorHtml) {return $this->errorHtml;}
		
		# Assemble and return the HTML
		return "\n<p class=\"warning\"><img src=\"/images/icons/exclamation.png\" class=\"icon\" /> Record <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">{$this->recordId}</a>: MARC conversion error: {$this->errorHtml}</p>";
	}
	
	
	# Getter for MARC pre-merge
	public function getMarcPreMerge ()
	{
		return $this->marcPreMerge;
	}
	
	
	# Getter for source registry
	public function getSourceRegistry ()
	{
		return $this->sourceRegistry;
	}
	
	
	# Getter for ISBN library handle
	public function getIsbn ()
	{
		return $this->isbn;
	}
	
	
	# Main entry point
	# Local documentation at: http://www.lib.cam.ac.uk/libraries/login/bibstandard/bibstandards.htm
	public function convertToMarc ($marcParserDefinition, $recordXml, $mergeDefinition = array (), $mergeType = false, $mergeVoyagerId = false, $suppressReasons = false)
	{
		# Reset the error string and source registry so that they are clean for each iteration
		$this->errorHtml = '';
		$this->marcPreMerge = NULL;
		$this->sourceRegistry = array ();
		
		# Create fresh containers for 880 reciprocal links for this record
		$this->field880subfield6ReciprocalLinks = array ();		// This is indexed by the master field, ignoring any mutations within multilines
		$this->field880subfield6Index = 0;
		$this->field880subfield6FieldInstanceIndex = array ();
		
		# Ensure the second-pass record ID flag is clean; this is used for a second-pass arising from 773 processing where the host does not exist at time of processing
		$this->secondPassRecordId = NULL;
		
		# Create property handle
		$this->suppressReasons = $suppressReasons;
		
		# Ensure the line-by-line syntax is valid, extract macros, and construct a data structure representing the record
		if (!$datastructure = $this->convertToMarc_InitialiseDatastructure ($recordXml, $marcParserDefinition)) {return false;}
		
		# End if not all macros are supported
		if (!$this->convertToMarc_MacrosAllSupported ($datastructure)) {return false;}
		
		# Load the record as a valid XML object
		$this->xml = $this->loadXmlRecord ($recordXml);
		
		# Determine the record number, used by several macros
		$this->recordId = $this->xPathValue ($this->xml, '//q0');
		
		# Determine the record type
		$this->recordType = $this->recordType ();
		
		# Up-front, process author fields
		//$initialMemoryUsage = memory_get_usage ();
		//var_dump ('|- Before creating authors fields, memory usage is: ' . $this->memoryUsage () . 'MB');
		$this->authorsFields = $this->generateAuthors->createAuthorsFields ($this->xml);
		//var_dump ('|- After running generateAuthors, memory usage is: ' . $this->memoryUsage () . 'MB; memory loss was: ' . (memory_get_usage () - $initialMemoryUsage) . ' bytes'); echo '<br />';	// ~4624 bytes used first time; ~144 bytes each iteration
		
		# Up-front, look up the host record, if any
		$this->hostRecord = $this->lookupHostRecord ();
		
		# Lookup XPath values from the record which are needed multiple times, for efficiency
		$this->form = $this->xPathValue ($this->xml, '(//form)[1]', false);
		
		# Up-front, process *p/*pt to parse into its component parts
		$this->pOrPt = $this->parsePOrPt ();
		
		# Perform XPath replacements
		if (!$datastructure = $this->convertToMarc_PerformXpathReplacements ($datastructure)) {return false;}
		
		# Expand vertically-repeatable fields
		if (!$datastructure = $this->convertToMarc_ExpandVerticallyRepeatableFields ($datastructure)) {return false;}
		
		# Process the record
		$record = $this->convertToMarc_ProcessRecord ($datastructure);
		
		# Determine the length, in bytes, which is the first five characters of the 000 (Leader), padded
		$bytes = mb_strlen ($record);
		$bytes = str_pad ($bytes, 5, '0', STR_PAD_LEFT);	// E.g. /records/1003/ has 984 bytes so becomes 00984 (test #229)
		$record = preg_replace ('/^LDR (_____)/m', "LDR {$bytes}", $record);
		
		# If required, merge with an existing Voyager record, returning by reference the pre-merge record, and below returning the merged record
		if ($mergeType) {
			$this->marcPreMerge = $record;	// Save original record pre-merge
			$record = $this->mergeWithExistingVoyager ($record, $mergeDefinition, $mergeType, $mergeVoyagerId);
		}
		
		# Report any UTF-8 problems
		if (strlen ($record) && !htmlspecialchars ($record)) {	// i.e. htmlspecialchars fails
			$this->errorHtml .= 'UTF-8 conversion failed.';
			return false;
		}
		
		# Do a check to report any case of an invalid subfield indicator
		if (preg_match_all ("/{$this->doubleDagger}[^a-z0-9]/u", $record, $matches)) {
			$this->errorHtml .= 'Invalid ' . (count ($matches[0]) == 1 ? 'subfield' : 'subfields') . " (" . implode (', ', $matches[0]) . ") detected.";
			// Leave the record visible rather than return false
		}
		
		# Do a check to report any case where a where 880 fields do not have both a field (starting validly with a $6) and a link back; e.g. /records/1062/ has "245 ## ‡6 880-01" and "880 ## ‡6 245-01" (test #230)
		preg_match_all ("/^880 [0-9#]{2} {$this->doubleDagger}6 /m", $record, $matches);
		$total880fields = count ($matches[0]);
		$total880dollar6Instances = substr_count ($record, "{$this->doubleDagger}6 880-");
		if ($total880fields != $total880dollar6Instances) {
			$this->errorHtml .= "Mismatch in 880 field/link counts ({$total880fields} vs {$total880dollar6Instances}).";
			// Leave the record visible rather than return false
		}
		
		# Return the record
		return $record;
	}
	
	
	# Function to return memory usage
	public function memoryUsage ()
	{
		return round (memory_get_usage () / 1048576, 3);
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
	private function mergeWithExistingVoyager ($localRecord, $mergeDefinitions, $mergeType, $mergeVoyagerId)
	{
		# Start a source registry, to store which source each line comes from
		$sourceRegistry = array ();
		
		# End if merge type is unsupported; this will result in an empty record
		if (!isSet ($this->mergeTypes[$mergeType])) {
			$this->errorHtml .= "Merge failed: unsupported merge type {$mergeType}. The local record has been put in, without merging.";
			return $localRecord;
		}
		
		# Select the merge definition to use
		$mergeDefinition = $mergeDefinitions[$mergeType];
		
		# Get the existing Voyager record
		if (!$voyagerRecord = $this->getExistingVoyagerRecord ($mergeVoyagerId)) {
			$this->errorHtml .= "Merge failed: could not retrieve existing Voyager record. The local record has been put in, without merging.";
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
		
		# Create a superstructure, where all fields are present from the superset, sub-indexed by source; if a field is not present it will not be present in the result (test #232)
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
					
					case 'M':							// E.g. /records/1033/ (tests #233, #234)
						$muscat = true;
						$voyager = false;
						break;
						
					case 'V':							// E.g. /records/10506/ (test #235)
						$muscat = false;
						$voyager = true;
						break;
						
					case 'M else V':					// No definitions yet, so no tests
						if ($recordPair['muscat']) {
							$muscat = true;
							$voyager = false;
						} else {
							$muscat = false;
							$voyager = true;
						}
						break;
						
					case 'V else M':					// E.g. /records/1033/ (tests #236, #237)
						if ($recordPair['voyager']) {
							$muscat = false;
							$voyager = true;
						} else {
							$muscat = true;
							$voyager = false;
						}
						break;
						
					case 'V and M':						// E.g. /records/50968/ , /records/12775/ (tests #238, #239, 240, 241)
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
		
		# Register the source registry
		$this->sourceRegistry = $sourceRegistry;
		
		# Return the merged record
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
			$hasIndicators = (!preg_match ('/^(LDR|00[0-9])$/', $shard['field']));	// E.g. /records/29550/ (tests #242, #243)
			$recordLines[] = $shard['field'] . ($hasIndicators ? ' ' . $shard['indicators'] : '') . ' ' . $shard['data'];
		}
		
		# Implode to text string
		$record = implode ("\n", $recordLines);
		
		# Return the record text block
		return $record;
	}
	
	
	# Function to load an XML record string as XML
	public function loadXmlRecord ($recordXml)
	{
		# Load the record as a valid XML object
		$xmlProlog = '<' . '?xml version="1.0" encoding="utf-8"?' . '>';
		$record = $xmlProlog . "\n<root>" . "\n" . $recordXml . "\n</root>";
		$xml = new SimpleXMLElement ($record);
		return $xml;
	}
	
	
	# Function to ensure the line-by-line syntax is valid, extract macros, and construct a data structure representing the record
	private function convertToMarc_InitialiseDatastructure ($record, $marcParserDefinition)
	{
		# Convert the definition into lines
		$marcParserDefinition = str_replace ("\r\n", "\n", $marcParserDefinition);
		$lines = explode ("\n", $marcParserDefinition);
		
		# Strip out comments and empty lines
		foreach ($lines as $lineNumber => $line) {
			
			# Skip empty lines
			if (!trim ($line)) {unset ($lines[$lineNumber]);}
			
			# Skip comment lines (test #244)
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
				$this->errorHtml .= 'Line ' . ($lineNumber + 1) . ' does not have the right syntax.';
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
					array_unshift ($macrosThisXpath, $macroMatches[2]);		// 'macro' does not appear in the result (test #245)
					$xpath = $macroMatches[1];
				}
				if ($macrosThisXpath) {
					$datastructure[$lineNumber]['macros'][$findBlock]['macrosThisXpath'] = $macrosThisXpath;	// Note that using [xpath]=>macrosThisXpath is not sufficient as lines can use the same xPath more than once
				}
				
				# Register the full block; e.g. '‡b{//recr} ' ; e.g. /records/1049/ (test #247)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['fullBlock'] = $match[0];
				
				# Register the subfield indicator (test #248)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['subfieldIndicator'] = $subfieldIndicator;
				
				# Register whether the block is an optional block; e.g. /records/2176/ (test #249)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['isOptionalBlock'] = (bool) $optionalBlockIndicator;
				
				# Register whether this xPath replacement is in the indicator block; e.g. /records/1108/ (test #250)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['isIndicatorBlockMacro'] = (bool) $isIndicatorBlockMacro;
				
				# Register the XPath; e.g. /records/1003/ (test #251)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['xPath'] = $xpath;
				
				# If the subfield is horizontally-repeatable, save the subfield indicator that should be used for imploding, resulting in e.g. $aFoo$aBar ; e.g. /records/1010/ (test #252)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['horizontalRepeatability'] = ($isHorizontallyRepeatable ? $subfieldIndicator : false);
				
				# Register any trailing space(s); e.g. /records/1049/ (test #246)
				$datastructure[$lineNumber]['xpathReplacements'][$findBlock]['trailingSpace'] = $trailingSpace;
			}
		}
		
		# Return the datastructure
		return $datastructure;
	}
	
	
	# Function to check all macros are supported
	private function convertToMarc_MacrosAllSupported ($datastructure)
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
		
		# Report unrecognised macros
		if ($unknownMacros) {
			$this->errorHtml .= 'Not all macros were recognised: ' . implode (', ', $unknownMacros);
			return false;
		}
		
		# No problems found
		return true;
	}
	
	
	# Function to perform Xpath replacements
	# NB XPath functions can have PHP modifications in them using php:functionString - may be useful in future; see: https://www.sitepoint.com/php-dom-using-xpath/ and https://www.cowburn.info/2009/10/23/php-funcs-xpath/
	private function convertToMarc_PerformXpathReplacements ($datastructure)
	{
		# Perform XPath replacements; e.g. /records/1003/ (test #251)
		$compileFailures = array ();
		foreach ($datastructure as $lineNumber => $line) {
			
			# Determine if the line is vertically-repeatable; e.g. /records/1599/ (test #253)
			$isVerticallyRepeatable = (in_array ('R', $datastructure[$lineNumber]['controlCharacters']));
			
			# Work through each XPath replacement
			foreach ($line['xpathReplacements'] as $find => $xpathReplacementSpec) {
				$xPath = $xpathReplacementSpec['xPath'];	// Extract from structure
				
				# Determine if horizontally-repeatable; e.g. /records/1010/ (test #252)
				$isHorizontallyRepeatable = (bool) $xpathReplacementSpec['horizontalRepeatability'];
				
				# Deal with fixed strings; e.g. /records/3056/ (test #254)
				if (preg_match ("/^'(.+)'$/", $xPath, $matches)) {
					$value = array ($matches[1]);
					
				# Handle the special-case where the specified XPath is just '/', representing the whole record; this indicates that the macro will process the record as a whole, ignoring any passed in value; doing this avoids the standard XPath processor resulting in an array of two values of (1) *qo and (2) *doc/*art/*ser ; e.g. /records/3056/ (test #255)
				} else if ($xPath == '/') {
					$value = array (true);	// Ensures the result processor continues, but this 'value' is then ignored
					
				# Otherwise, handle the standard case; e.g. /records/1003/ (test #251)
				} else {
					
					# Attempt to parse
					$xPathResult = @$this->xml->xpath ('/root' . $xPath);
					
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
								$value[$index] = $this->processMacros ($subValue, $macros);
							}
						} else {
							$value = $this->processMacros ($value, $macros);
						}
					}
					
					# For horizontally-repeatable fields, apply uniqueness after macro processing; e.g. if Lang1, Lang2, Lang3 becomes translatedlangA, translatedlangB, translatedlangB, unique to translatedlangA, translatedlangB; no examples available
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
			$this->errorHtml .= 'Not all expressions compiled: ' . implode ($compileFailures);
			return false;
		}
		
		# Return the datastructure
		return $datastructure;
	}
	
	
	# Function to expand vertically-repeatable fields
	private function convertToMarc_ExpandVerticallyRepeatableFields ($datastructureUnexpanded)
	{
		$datastructure = array ();	// Expanded version, replacing the original
		foreach ($datastructureUnexpanded as $lineNumber => $line) {
			
			# If not vertically-repeatable, copy the attributes across unamended, and move on
			if (!in_array ('R', $line['controlCharacters'])) {
				$datastructure[$lineNumber] = $line;
				continue;
			}
			
			# For vertically-repeatable, first check the counts are consistent (e.g. if //k/kw generated 7 items, and //k/ks generated 5, throw an error, as behaviour is undefined); no tests possible as this is basically now deprected - no examples in parser left, as groupings all handled by macros now
			$counts = array ();
			foreach ($line['xpathReplacements'] as $macroBlock => $xpathReplacementSpec) {
				$replacementValues = $xpathReplacementSpec['replacement'];
				$counts[$macroBlock] = count ($replacementValues);
			}
			if (count (array_count_values ($counts)) != 1) {
				$this->errorHtml .= 'Line ' . ($lineNumber + 1) . ' is a vertically-repeatable field, but the number of generated values in the subfields are not consistent:' . application::dumpData ($counts, false, true);
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
		
		# Return the newly-expanded datastructure; e.g. /records/1599/ (test #253)
		return $datastructure;
	}
	
	
	# Function to process the record
	private function convertToMarc_ProcessRecord ($datastructure)
	{
		# Process each line
		$outputLines = array ();
		foreach ($datastructure as $lineNumber => $attributes) {
			$line = $attributes['line'];
			
			# Perform XPath replacements if any, working through each replacement; e.g. /records/1049/ (test #247)
			if ($datastructure[$lineNumber]['xpathReplacements']) {
				
				# Start a flag for whether the line has content
				$lineHasContent = false;
				
				# Loop through each macro block; e.g. /records/1049/ (test #247)
				$replacements = array ();
				foreach ($datastructure[$lineNumber]['xpathReplacements'] as $macroBlock => $xpathReplacementSpec) {
					$replacementValue = $xpathReplacementSpec['replacement'];
					
					# Determine if there is content
					$blockHasValue = strlen ($replacementValue);
					
					# Register replacements
					$fullBlock = $xpathReplacementSpec['fullBlock'];	// The original block, which includes any trailing space(s), e.g. "‡a{/*/edn} " ; e.g. if optional block is skipped because of no value then following block will not have a space before: /records/1049/ (test #260)
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
						
						# If there is an 'A' (all) control character, require all non-optional placeholders to have resulted in text; e.g. /records/3056/ (test #257), /records/3057/ (test #258)
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
				
				# If there is an 'E' ('any' ['either']) control character, require at least one replacement, i.e. that content (after the field number and indicators) exists; e.g. /records/1049/ (test #259)
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
			
			# Trim the line, e.g. /records/1054/ (test #261); NB This will not trim within multiline output lines
			#!# Need to check multiline outputs to ensure they are trimming
			$line = trim ($line);
			
			# Register the value
			$outputLines[$lineOutputKey] = $line;
		}
		
		# Insert 880 reciprocal links; see: http://www.lib.cam.ac.uk/libraries/login/documentation/Unicode_non_roman_cataloguing_handout.pdf ; e.g. /records/1062/ has "245 ## ‡6 880-01" and "880 ## ‡6 245-01" (test #230)
		foreach ($this->field880subfield6ReciprocalLinks as $lineOutputKey => $linkToken) {		// $lineOutputKey is e.g. 700_0
			
			# Report data mismatches
			if (!isSet ($outputLines[$lineOutputKey])) {
				$this->errorHtml .= "Line output key {$lineOutputKey} does not exist in the output lines.";
			}
			
			# For multilines, split the line into parts, prepend the link token
			if (is_array ($linkToken)) {
				$lines = explode ("\n", $outputLines[$lineOutputKey]);	// Split out
				foreach ($lines as $i => $line) {
					$lines[$i] = $this->insertSubfieldAfterMarcFieldThenIndicators ($line, $linkToken[$i]);
				}
				$outputLines[$lineOutputKey] = implode ("\n", $lines);	// Reassemble; e.g. /records/1697/ (test #262)
				
			# For standard lines, do a simple insertion
			} else {
				$outputLines[$lineOutputKey] = $this->insertSubfieldAfterMarcFieldThenIndicators ($outputLines[$lineOutputKey], $linkToken);	// E.g. /records/2800/ (test #263)
			}
		}
		
		# Compile the record
		$record = implode ("\n", $outputLines);
		
		# Strip tags (introduced in specialCharacterParsing) across the record: "in MARC there isn't a way to represent text in italics in a MARC record and get it to display in italics in the OPAC/discovery layer, so the HTML tags will need to be stripped."
		$tags = array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>');	// E.g. /records/1131/ (test #264), /records/2800/ (test #265), /records/61528/ (test #266)
		$record = str_replace ($tags, '', $record);
		
		# Return the record
		return $record;
	}
	
	
	# Function to modify a line to insert a subfield after the opening MARC field and indicators; for a multiline value, this must be one of the sublines; e.g. /records/1697/ (test #262), /records/2800/ (test #263)
	private function insertSubfieldAfterMarcFieldThenIndicators ($line, $insert)
	{
		return preg_replace ('/^([0-9]{3}) ([0-9#]{2}) (.+)$/', "\\1 \\2 {$insert} \\3", $line);
	}
	
	
	# Function to process strings through macros; macros should return a processed string, or false upon failure
	private function processMacros ($string, $macros)
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
				$string = $this->{$macroMethod} ($string, NULL, $this->errorHtml);
			} else {
				$string = $this->{$macroMethod} ($string, $parameter, $this->errorHtml);	// E.g. /records/2176/ (test #268)
			}
			
			// Continue to next macro in chain (if any), using the processed string as it now stands; e.g. /records/2800/ (test #267)
		}
		
		# Return the string
		return $string;
	}
	
	
	/* Macros */
	
	
	# ISBN validation
	# Permits multimedia value EANs, which are probably valid to include as the MARC spec mentions 'EAN': https://www.loc.gov/marc/bibliographic/bd020.html ; see also http://www.activebarcode.com/codes/ean13_laenderpraefixe.html
	private function macro_validisbn ($value)
	{
		# Extract qualifying information, e.g. /records/73935/, /records/177698/, /records/215088/ (test #592)
		$q = false;
		if (preg_match ('/^([0-9]+) \(([^)]+)\)$/', $value, $matches)) {
			$value = $matches[1];
			$q = "{$this->doubleDagger}q{$matches[2]}";
		}
		
		# Determine the subfield, by performing a validation; seems to permit EANs like 5391519681503 in /records/211150/ (test #270)
		$isValid = $this->isbn->validation->isbn ($value);
		$subfield = $this->doubleDagger . ($isValid ? 'a' : 'z');	// E.g. /records/211150/ (test #271), /records/49940/ (test #272)
		
		# Assemble the return value, adding qualifying information if present
		$string = $subfield . $value . $q;
		
		# Return the value
		return $string;
	}
	
	
	# Macro to convert an internationalised domain name to Unicode; see: https://en.wikipedia.org/wiki/Internationalized_domain_name#Top-level_domain_implementation
	private function macro_idnConversion ($url)
	{
		# Convert if required, e.g. /records/197739/ (test #584)
		$hostname = parse_url ($url, PHP_URL_HOST);
		if (preg_match ('/^xn--/', $hostname)) {
			$utf8DomainName = idn_to_utf8 ($hostname);
			$url = str_replace ($hostname, $utf8DomainName, $url);	// Substitute the domain within the overall URL
		}
		
		# Return the possibly-modified URL
		return $url;
	}
	
	
	# Macro to prepend a string if there is a value; e.g. /records/49940/ (test #273)
	private function macro_prepend ($value, $text)
	{
		# Return unmodified if no value
		if (!$value) {return $value;}	// E.g. /records/49941/ (test #274)
		
		# Prepend the text
		return $text . $value;
	}
	
	
	# Macro to check existence; e.g. /records/1058/ (test #275); no negative test possible as no case in parser definition
	private function macro_ifValue ($value, $xPath)
	{
		return ($this->xPathValue ($this->xml, $xPath) ? $value : false);
	}
	
	
	# Macro to upper-case the first character; e.g. /records/1054/ (test #276)
	private function macro_ucfirst ($value)
	{
		return mb_ucfirst ($value);
	}
	
	
	# Macro to implement a ternary check; e.g. /records/1010/ (test #277), /records/2176/ (test #278)
	private function macro_ifElse ($value_ignored /* If empty, the macro will not even be called, so the value has to be passed in by parameter */, $parameters)
	{
		# Parse the parameters
		list ($xPath, $ifValue, $elseValue) = explode (',', $parameters, 3);
		
		# Determine the value
		$value = $this->xPathValue ($this->xml, $xPath);
		
		# Return the result
		return ($value ? $ifValue : $elseValue);
	}
	
	
	# Splitting of strings with colons in
	private function macro_colonSplit ($value, $splitMarker)
	{
		# Return unmodified if no split; e.g. /records/1019/ (test #280)
		if (!preg_match ('/^([^:]+) ?: (.+)$/', $value, $matches)) {
			return $value;
		}
		
		# If a split is found, assemble; e.g. /records/3765/ (test #279)
		$value = trim ($matches[1]) . " :{$this->doubleDagger}{$splitMarker}" . trim ($matches[2]);
		
		# Return the value
		return $value;
	}
	
	
	# Ending strings with dots; e.g. /records/1102/ (test #281), /records/1109/ (test #282), /records/1105/ (test #283), /records/1063/ (test #284)
	public function macro_dotEnd ($value, $extendedCharacterList = false)
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
		if ($value == '-') {return false;}		// E.g. /records/138387/ (test #285)
		if ($value == '??') {return false;}		// E.g. /records/116085/
		
		# Return the value; e.g. /records/1102/ (test #286)
		return $value;
	}
	
	
	# Macro to get multiple values as an array; e.g. /records/205727/ for 546 $a //lang (test #287), no value(s): /records/1102/ (test #288)
	private function macro_multipleValues ($value_ignored, $parameter)
	{
		$parameter = "({$parameter})[%i]";
		$values = $this->xPathValues ($this->xml, $parameter, false);
		$values = array_unique ($values);	// e.g. /records/1337/ (test #289)
		return $values;
	}
	
	
	# Macro to implode subvalues; e.g. /records/132384/ (test #290), /records/1104/ (test #291)
	private function macro_implode ($values, $parameter)
	{
		# Return empty string if no values
		if (!$values) {return '';}	// E.g. /records/1007/ (test #292)
		
		# Implode and return
		return implode ($parameter, $values);
	}
	
	
	# Macro to implode subvalues with the comma-and algorithm; e.g. as used for 546 in /records/160854/ (test #293)
	private function macro_commaAnd ($values, $parameter)
	{
		# Return empty string if no values; e.g. /records/1102/ (test #296)
		if (!$values) {return '';}
		
		# Implode and return; e.g. /records/160854/ (test #293), /records/1144/ (test #294), /records/1007/ (test #295)
		return application::commaAndListing ($values);
	}
	
	
	# Macro to set the second indicator for a 260 field, based on whether it is a thesis, e.g. /records/3152/ (test #317)
	private function macro_theses260i ($values_ignored)
	{
		# "If *loc starts with 'Theses' second indicator should be '0'.", e.g. /records/3152/ (test #317)
		$locations = $this->xPathValues ($this->xml, '//loc[%i]/location', false);
		foreach ($locations as $location) {
			if (preg_match ('/^Theses/', $location)) {
				return '0';
			}
		}
		
		# Otherwise return #, e.g. /records/1117/ (test #318)
		return '#';
	}
	
	
	# Macro to create 260; $a and $b are grouped as there may be more than one publisher, e.g. /records/76743/ (#test 297); see: https://www.loc.gov/marc/bibliographic/bd260.html
	private function macro_generate260 ($value_ignored, $transliterate = false)
	{
		# In transliteration mode, end if not Russian; e.g. /records/1014/ (test #316)
		#!# Not yet implemented
		
		# Start a list of values; the macro definition has already defined $a
		$results = array ();
		
		# Loop through each /*pg/*[pl|pu] group; e.g. /records/76743/ (test #297), /records/1786/ (test #298)
		for ($pgIndex = 1; $pgIndex <= 20; $pgIndex++) {	// XPaths are indexed from 1, not 0; 20 chosen as a high number to ensure sufficient *pg groups
			$pg = $this->xPathValue ($this->xml, "//pg[{$pgIndex}]");
			
			# Break out of loop if no more
			if ($pgIndex > 1) {
				if (!strlen ($pg)) {break;}
			}
			
			# Obtain the raw *pl value(s) for this *pg group
			$plValues = array ();
			for ($plIndex = 1; $plIndex <= 20; $plIndex++) {
				$plValue = $this->xPathValue ($this->xml, "//pg[$pgIndex]/pl[{$plIndex}]");	// e.g. /records/1639/ has multiple (test #299)
				if ($plIndex > 1 && !strlen ($plValue)) {break;}	// Empty $pl is fine for first and will show [S.l.] ('sine loco', i.e. 'without place'), e.g. /records/1484/ (test #300), but after that should not appear (no examples found)
				$plValues[] = $this->formatPl ($plValue);
			}
			
			# Obtain the raw *pu value(s) for this *pg group
			$puValue = $this->xPathValue ($this->xml, "//pg[$pgIndex]/pu");
			$puValues = array ();
			for ($puIndex = 1; $puIndex <= 20; $puIndex++) {
				$puValue = $this->xPathValue ($this->xml, "//pg[$pgIndex]/pu[{$puIndex}]");	// e.g. /records/1223/ has multiple (test #301)
				if ($puIndex > 1 && !strlen ($puValue)) {break;}	// Empty $pu is fine for first and will show [s.n.] ('sine nomine', i.e. 'without name'), e.g. /records/1730/ (test #302), but after that should not appear (no examples found)
				$puValues[] = $this->formatPu ($puValue);	// Will always return a string
			}
			
			# Transliterate if required; e.g. /records/6996/ (test #58)
			if ($transliterate) {
				if ($puValues) {
					foreach ($puValues as $index => $puValue) {
						$xPath = '//lang[1]';	// Choose first only
						$language = $this->xPathValue ($this->xml, $xPath);
						$puValues[$index] = $this->macro_transliterate ($puValue, NULL, $language);	// [S.l.] and [s.n.] will not get transliterated as they are in brackets, e.g. /records/76740/ (test #306)
					}
				}
			}
			
			# Assemble the result; NB If there is no $a and $b value, but there is a date, "260 ## ‡a[S.l.] :‡b[s.n.],‡c1985." is indeed correct to have $a and $b both created as shown, e.g. /records/76740/ (test #652)
			$results[$pgIndex]  = "{$this->doubleDagger}a" . implode (" ;{$this->doubleDagger}a", $plValues);
			$results[$pgIndex] .= " :{$this->doubleDagger}b" . implode (" :{$this->doubleDagger}b", $puValues);	// "a colon (:) when subfield $b is followed by another subfield $b" at https://www.loc.gov/marc/bibliographic/bd260.html , e.g. /records/1223/ (test #304)
		}
		
		# Implode by space-semicolon: "a semicolon (;) when subfield $b is followed by subfield $a" at https://www.loc.gov/marc/bibliographic/bd260.html , e.g. /records/76743/ (test #303)
		$result = implode (' ;', $results);
		
		# Add $c if present; confirmed these should be treated as a single $c, comma-separated, as we have no grouping information; e.g. /records/76740/ (test #307)
		# "If the record is a *ser, populate $c with *r instead of putting '[n.d.]' (*ser records do not include a *d); if a *ser record does not include a *r, do not include a $c."
		$cField = ($this->recordType == '/ser' ? 'r' : 'd');	// E.g. *ser record /records/1009/ (test #634), other record types /records/5943/ (test #635)
		$dateValues = $this->xPathValues ($this->xml, "(//{$cField})[%i]", false);
		if ($this->recordType == '/ser' && preg_match ('/[a-zA-Z]/', $dateValues[1] /* i.e. the first in xPath terms */ )) {	// For *ser records, if the value has any a-z text present, e.g. "unknown", "current only", etc., then treat this as an invalid date range, and therefore do not create a $c, e.g. /records/1024/ (tests #644) and normal year test case /records/1029/ (test #645); assumes single *r - see /reports/sermultipler/
			$dateValues = NULL;
		}
		if ($dateValues) {
			if ($result) {$result .= ',';}
			$result .= "{$this->doubleDagger}c" . implode (', ', $dateValues);	// Nothing in spec suggests modification if empty, /records/1102/ has [n.d.] (test #312), which remains as-is
		}
		
		# Ensure dot at end; e.g. /records/76740/ (test #308), /records/1105/ (test #283)
		$result = $this->macro_dotEnd ($result, $extendedCharacterList = true);
		
		# Return the result
		return $result;
	}
	
	
	# Helper function for 260a *pl
	private function formatPl ($plValue)
	{
		# If no *pl, put '[S.l.]'. ; e.g. /records/1484/ (test #300) ; decision made not to make a semantic difference between between a publication that is known to have an unknown publisher (i.e. a check has been done and this is explicitly noted) vs a publication whose check has never been done, so we don't know if there is a publisher or not.
		if (!$plValue) {
			return '[S.l.]';	// Meaning 'sine loco' ('without a place')
		}
		
		# *pl [if *pl is '[n.p.]' or '-', this should be replaced with '[S.l.]' ]. ; e.g. /records/1787/, /records/1102/ (test #308)
		if ($plValue == '[n.p.]' || $plValue == '-') {
			return '[S.l.]';
		}
		
		# Preserve square brackets, but remove round brackets if present. ; e.g. /records/2027/ , /records/5942/ (test #309) , /records/5943/ (test #310)
		if (preg_match ('/^\((.+)\)$/', $plValue, $matches)) {
			return $matches[1];
		}
		
		# Return the value unmodified; e.g. /records/1117/ (test #315)
		return $plValue;
	}
	
	
	# Helper function for 260a *pu
	private function formatPu ($puValue)
	{
		# *pu [if *pu is '[n.pub.]' or '-', this should be replaced with '[s.n.]' ] ; e.g. /records/1105/ , /records/1745/ (test #313)
		if (!strlen ($puValue) || $puValue == '[n.pub.]' || $puValue == '-') {
			return '[s.n.]';	// Meaning 'sine nomine' ('without a name')
		}
		
		# Otherwise, return the value unmodified; e.g. /records/1117/ (test #314)
		return $puValue;
	}
	
	
	# Up-front, process *p/*pt to parse into its component parts
	private function parsePOrPt ()
	{
		# Start an array to hold the components
		$pOrPt = array ();
		
		# Obtain *p; there should be only one; see: /reports/multipleppt/ ; e.g. /records/1175/ (test #320); no *p (as expected for an *art record): /records/1107/ (test #321) - should not be any cases of no *p in *doc (see: /reports/docnop/)
		$p = $this->xPathValue ($this->xml, '//p[1]', false);
		
		# Obtain *pt; e.g. /records/1129/ (test #323); no *pt: /records/1106/ (test #324)
		$pt = $this->xPathValue ($this->xml, '//pt[1]', false);
		
		# Determine *p or *pt; e.g. *p in /records/15716/ (test #325), *pt in /records/25180/ (test #326)
		$pOrPt = (strlen ($p) ? $p : $pt);		// Confirmed there are no records with both *p and *pt
		
		# Firstly, break off any final + section (removing the + itself), for use in $e (Accompanying material) below; e.g. /records/67235/ (test #327)
		$additionalMaterial = false;
		if (substr_count ($pOrPt, '+')) {
			$plusMatches = explode ('+', $pOrPt, 2);
			$additionalMaterial = trim ($plusMatches[1]);
			$pOrPt = trim ($plusMatches[0]);	// Override string to strip out the + section
		}
		
		# Normalise commas to have a space after; e.g. /records/8167/ (test #555)
		$pOrPt = preg_replace ('/,([^ ])/', ', \1', $pOrPt);
		
		# Normalise abbreviations to have a dot; use of \b prevents this adding . in middle of word (e.g. for 'ill'); e.g. /records/1584/ (test #329) , /records/1163/ (test #330); supports multiple replacements, e.g. /records/147891/ (test #533); supports optional plural 's', e.g. /records/34364/ (test #534)
		# Checked using: `SELECT * FROM catalogue_processed WHERE field IN('p','pt') AND value LIKE '%ill%' AND value NOT LIKE '%ill.%' AND value NOT REGEXP 'ill(-|\.|\'|[a-z]|$)';`
		$abbreviations = array ('col', 'diag', 'fig', 'ill', 'illus', 'port');
		$pOrPt = preg_replace ('/' . "\b" . '(' . implode ('|', $abbreviations) . ')(s?)' . "\b" . '([^\.]|$)' . '/', '\1\2.\3', $pOrPt);
		
		# Split off the physical description; prefer explicit " : " marker where present; else use splitting point tokens
		$pOrPt = trim ($pOrPt);
		$physicalDescription = false;
		if (substr_count ($pOrPt, ' : ')) {
			
			# Explicit space-colon-space split point; e.g. "2 computer disks (45.5 min.; 54.5 min.) : sd." present in /records/199582/ (test #631)
			$matches = explode (' : ', $pOrPt, 2);
			$pOrPt = trim ($matches[0]);
			$physicalDescription = trim ($matches[1]);
		} else {
			
			# Protect specific phrases to bypass the keyword splitter; e.g. /records/13890/ (test #632)
			$phrases = array (
				'leaves of map',
				'leaf of map',
			);
			$protectedSubstringsPattern = '<||%i||>';
			$protectedParts = array ();
			foreach ($phrases as $i => $phrase) {
				$key = str_replace ('%i', $i, $protectedSubstringsPattern);
				$protectedParts[$phrase] = $key;
			}
			$pOrPt = strtr ($pOrPt, $protectedParts);
			
			# Next split by the keyword which acts as separating point between citation and an optional $b (i.e. is the start of an optional $b); e.g. /records/51787/ (test #328); first comma cannot be used reliably because the pagination list could be e.g. "3,5,97-100"; split is done for the first instance of a split word, e.g. /records/12780/ (test #535)
			$splitWords = array ('col', 'diag', 'fig', 'figures', 'graph', 'ill', 'illus', 'map', 'port', 'table', );	// These may be pluralised, using the s? below; e.g. /records/1684/ (test #512)
			$matches = preg_split ('/' . "\b" . '((?:' . implode ('|', $splitWords) . ')s?' . "\b.*$)" . '/', $pOrPt, 2, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);		// Use of \b word boundary ensures not splitting at substrings, e.g. bibliography at 'graph': /records/54670/ (test #220); .* is used so that both appreviated types ("ill.", "diag.") and non-abbreviated types ("tables", "map") - see test for latter: /records/24489/ (test #630)
			if (count ($matches) == 2) {
				$pOrPt = trim ($matches[0]);
				$physicalDescription = trim ($matches[1]);
			}
			
			# Revert specific phrases
			$pOrPt = strtr ($pOrPt, array_flip ($protectedParts));
			$physicalDescription = strtr ($physicalDescription, array_flip ($protectedParts));
		}
		
		# At this point, $pOrPt represents the citation
		$citation = $pOrPt;
		
		# Normalise 'p' to have a dot after; safe to make this change after checking: `SELECT * FROM catalogue_processed WHERE field IN('p','pt','vno','v','ts') AND value LIKE '%p%' AND value NOT LIKE '%p.%' AND value REGEXP '[0-9]p' AND value NOT REGEXP '[0-9]p( |,|\\)|\\]|$)';`
		$citation = preg_replace ('/([0-9])p([^.]|$)/', '\1p.\2', $citation);	// E.g. /records/6002/ , /records/1654/ (test #346) , multiple in single string: /records/2031/ (test #347)
		
		# Remove comma/colon/semicolon at end; e.g. /records/215835/
		$citation = trim (preg_replace ('/^(.+)[,;:]$/', '\1', trim ($citation)));
		
		# Tokenise the citation list to volume => pagination pairs
		$citationListValues = $this->tokeniseCitationList ($citation);
		
		# Construct the volume list
		$volumeList = implode ('; ', array_keys ($citationListValues));	// As per same comment below in macro_generate773, semicolon rather than comma is chosen because there could be '73(1,5)' which would cause 'Vols. ' to appear rather than 'Vol. '
		
		# If there is a *vno, add this at the start of the analytic volume designation, before any pagination (extent) data from *pt; e.g. /records/6787/ (test #352) and negative test for 300 in same record /records/6787/ (test #351)
		if ($vno = $this->xPathValue ($this->xml, '//vno')) {
			$volumeList = $this->macro_dotEnd ($vno) . (strlen ($volumeList) ? ' ' : '') . $volumeList;		// E.g. dot added before other citation substring in /records/7865/ (test #519); no existing $a so no comma in /records/6787/ (test #352)
		}
		
		# Create the page string or count; if one than one item, a count is used; tests present in function
		$pages = $this->pagesString ($citationListValues);
		
		# Assemble the datastructure
		$result = array (
			'citation' => $citation,						// e.g. "41(11) :14-18; 41(12) :22-25; 42(1) :26-28, 68-72" (analytic/pseudo-analyic from several volumes), or ":14-18" (single-volume monograph)
			'volumeList' => $volumeList,					// e.g. "41(11), 41(12), 42(1)" (analytic/pseudo-analyic from several volumes), or nothing (single-volume monograph)
			'pages' => $pages,								// e.g. 17, being a count (analytic/pseudo-analyic from several volumes), or a range "p. 14-18" (single-volume monograph)
			'physicalDescription' => $physicalDescription,	// e.g. ill., maps
			'additionalMaterial' => $additionalMaterial,	// e.g. CD-ROM
		);
		
		# Return the assembled data
		return $result;
	}
	
	
	# Helper function to tokenise the citation list to volume => pagination pairs
	private function tokeniseCitationList ($citation)
	{
		# Split by semicolon-space, e.g. /records/54657/ (test #608)
		$citationParts = explode ('; ', $citation);
		
		# Create a list of volume => pagination pairs; decision taken that volume should be unique, and if not, this represents an error in the original data
		$citationListValues = array ();
		foreach ($citationParts as $citationPart) {
			
			# If in the format of "<volume>: <pagination-string>", split out, trimming both sides, e.g. /records/54657/ (test #609); otherwise no volume but pagination, e.g. /records/6787/ (test #610)
			if (substr_count ($citationPart, ':')) {
				list ($volume, $paginationString) = explode (':', $citationPart, 2);
				$volume = trim ($volume);
				$paginationString = trim ($paginationString);
			} else {
				$volume = false;
				$paginationString = $citationPart;
			}
			
			# Register the tokenised pair
			$citationListValues[$volume] = $paginationString;
		}
		
		# Return the tokenised pairs
		return $citationListValues;
	}
	
	
	# Helper function to create (used only for non- *doc records) the page string or count; if one than one item, a count is used; many tests present as shown
	private function pagesString ($citationListValues)
	{
		# If only one item in the citation list, list out the page details, e.g. "p. 438-442" in /records/214872/ (test #607)
		if (count ($citationListValues) == 1) {
			
			# Obtain the pages string, e.g. /records/214872/ (test #607)
			$pagesString = application::array_first_value ($citationListValues);	// We use this as this has split out the volume (key) from page (value)
			
			# Add "p. " prefix if required, e.g. /records/1107/ (test #524)
			if ($this->pDotPrefixRequired ($pagesString)) {
				$pagesString = 'p. ' . $pagesString;
			}
			
		# Otherwise, for a complex citation, create a count, e.g. /records/54657/ (test #597)
		} else {
			
			# Count for each pagination string, including p. suffix, e.g. /records/54657/ (test #597)
			$pageCount = 0;
			foreach ($citationListValues as $volume => $paginationString) {
				$pageCount += $this->pageCount ($paginationString);
			}
			$pagesString = $pageCount . ' p.';
		}
		
		# Return the assembled pages string
		return $pagesString;
	}
	
	
	# Helper function to determine if pages should have p. prefixed, e.g. /records/1107/ (test #524)
	private function pDotPrefixRequired ($pages)
	{
		# Do not add p. if already present: 'p. '*pt [number range after ':' and before ',']; e.g. /records/1654/ (test #525)
		if (substr_count ($pages, 'p.')) {
			return false;
		}
		
		# Do not add p. prefix if contains square bracket, like '[42] pages', e.g. /records/169753/ (test #600)
		if (preg_match ('/^\[/', $pages)) {
			return false;
		}
		
		#!# /records/2047/ ends up with "‡a3 parts Variously paged"
		# Do not add p. prefix if unpaged (and variants), e.g. /records/1147/ (test #602)
		$unpagedTypes = array ('unpaged', 'variously paged', 'various pagings');	// Use lower-case in comparison, e.g. upper-case in /records/209663/ (test #603)
		foreach ($unpagedTypes as $unpagedType) {
			if (substr_count (mb_strtolower ($pages), $unpagedType)) {
				return false;
			}
		}
		
		# Do not add p. prefix if multimediaish article, e.g. /records/2023/ (test #601)
		$isMultimedia = (in_array ($this->form, array ('CD', 'CD-ROM', 'DVD', 'DVD-ROM', 'Sound Cassette', 'Sound Disc', 'Videorecording')));
		if ($isMultimedia) {
			return false;
		}
		
		# Note that there are items like "Not in SPRI" which may have no p. and will not be fixed; they should end up with "‡ap." - see /reports/artnopt/, e.g. /records/3981/ (test #604)
		// No logic required
		
		# 'p. ' dot is required, e.g. /records/1107/ (test #524)
		return true;
	}
	
	
	# Helper function to create a page count from a pagination string, e.g. /records/54657/ (test #597)
	private function pageCount ($paginationString)
	{
		# Start a counter
		$pages = 0;
		
		# Convert the pagination string into a list of tokens (which may be only one item), e.g. multiple in /records/54657/ (test #605), single in /records/3656/ (test #606)
		$paginationSegments = explode (', ', $paginationString);
		foreach ($paginationSegments as $pagination) {
			
			# Single page number, e.g. "27" is 1, e.g. /records/2237/ (test #598)
			if (ctype_digit ($pagination)) {
				$pages += 1;	// I.e. single page (not the page number itself)
				
			# Range, e.g. "26-28" is 3, e.g. /records/54657/ (test #599)
			} else if (preg_match ('/^([0-9]+)-([0-9]+)$/', $pagination, $matches)) {
				$pages += (($matches[2] - $matches[1]) + 1);	// +1 because it has to match itself
				
			# Roman numeral single page number, e.g. "v" is 5; no examples available (so code is not actually used, but mocked data shows confirmed working)
			} else if (preg_match ('/^([ivxcldmIVXCLDM]+)$/', $pagination, $matches)) {	// Expected to be lower-case but upper-case support kept in
				$pages += 1;	// I.e. single page (not the page number itself)
				
			# Roman numeral range, e.g. "v-viii" is 4; no examples available (so code is not actually used, but mocked data shows confirmed working)
			} else if (preg_match ('/^([ivxcldmIVXCLDM]+)-([ivxcldmIVXCLDM]+)$/i', $pagination, $matches)) {	// Expected to be lower-case but upper-case support kept in
				$pages += ((application::romanNumeralToInt ($matches[2]) - application::romanNumeralToInt ($matches[1])) + 1);	// +1 because it has to match itself
				
			# Unsupported value
			} else {
				$this->errorHtml .= "Unrecognised pagination format: {$pagination}";
			}
		}
		
		# Return the count for this pagination string
		return $pages;
	}
	
	
	# Macro to generate the 300 field (Physical Description); 300 is a Minimum standard field; see: https://www.loc.gov/marc/bibliographic/bd300.html
	# Note: the original data is not normalised, and the spec does not account for all cases, so the implementation here is based also on observation of various records and on examples in the MARC spec, to aim for something that is 'good enough' and similar enough to the MARC examples
	# At its most basic level, in "16p., ill.", $a is the 16 pages, $b is things after
	private function macro_generate300 ($value_ignored, $parameter_ignored = false, &$errorHtml)
	{
		# Start a result
		$result = '';
		
		# $a (R) (Extent, pagination): If record is *doc with any or no *form (e.g. /records/20704/ (test #331)): "(*v), (*p or *pt)" [all text up to and including ':']
		# NB Spec also stated the following to provide the same result, but no cases exist: "or *art with multimediaish *form CD, CD-ROM, DVD, DVD-ROM, Sound Cassette, Sound Disc or Videorecording"
		
		# $a is a description of the physical extent, simplified in the case of analytics across several volumes, e.g. /records/2281/ (test #626 - which uses a multi-volume *doc, as single volume would be the same as pages and therefore would be a poor test); /records/54657/ (test #627)
		$isDoc = ($this->recordType == '/doc');
		if ($isDoc) {
			$a = $this->pOrPt['citation'];
		} else if ($this->recordType == '/ser') {
			$a = NULL;	// For *ser, number of volumes is unknown to Muscat; code lower then converts this to 'v.' (as there will be no $b also), e.g. /records/1019/ (test #341)
		} else {
			$a = $this->pOrPt['pages'];
		}
		
		# Create local handle to the physical description
		$b = $this->pOrPt['physicalDescription'];
		
		# If a doc with a *v, begin with *v and surround the a part with brackets; e.g. /records/37420/ (test #331)
		if ($isDoc) {
			$vMuscat = $this->xPathValue ($this->xml, '//v');
			if (strlen ($vMuscat)) {
				if ($a) {
					$a = $vMuscat . ' (' . $a . ')';	// I.e. "Volume (pages)", e.g. /records/2281/ (test #513)
				} else {
					$a = $vMuscat;	// I.e. no citation - just has number of volumes, e.g. /records/37420/ (test #628)
				}
			}
		}
		
		# Add space between the number and the 'p.' or 'v.' ; e.g. /records/49133/ for p. (test #349); normalisation not required: /records/13745/ (test #350) ; multiple instances of page number in /records/2031/ ; NB No actual cases for v. in the data; avoids dot after 'vols': /records/20704/ (test #348)
		$a = preg_replace ('/([0-9]+)([pv]\.)/', '\1 \2', $a);
		
		# Register the $a
		$result .= $a;
		
		# $b (NR) (Other physical details): *p [all text after ':' and before, but not including, '+'] or *pt [all text after the ',' - i.e. after the number range following the ':'], e.g. /records/9529/ (test #629)
		if (strlen ($b)) {
			$b = trim ($b);
			$b = preg_replace ('/(.+)[,;:]$/', '\1', $b);	// E.g. /records/9529/ (test #528)
			$b = trim ($b);
			$result .= " :{$this->doubleDagger}b" . $b;
		}
		
		# If no value, or 'unpaged', set an explicit string; other subfields may continue after, e.g. /records/174009/ (test #344)
		if (!strlen ($result) || strtolower ($this->pOrPt['citation']) == 'unpaged') {	 // 'unpaged' at /records/1248/ (test #341); 'Unpaged' at /records/174009/ (test #343)
			$result = ($this->recordType == '/ser' ? 'v.' : '1 volume (unpaged)');	// E.g. *ser with empty $result: /records/1019/ (confirmed to be fine) (test #341); *doc with empty $result: /records/1334/ (test #345); no cases of unpaged (*p or *pt) for *ser so no test; *doc with unpaged: /records/174009/ (test #343)
		}
		
		# $c (R) (Dimensions): *size (NB which comes before $e) ; e.g. /records/1103/ (test #335), multiple in /records/4329/ (test #336)
		$size = $this->xPathValues ($this->xml, '(//size)[%i]', false);
		if ($size) {
			
			# Normalise " cm." to avoid Bibcheck errors; e.g. /records/2709/ , /records/4331/ , /records/54851/ (test #337) ; have checked no valid inner cases of cm
			foreach ($size as $index => $sizeItem) {
				$sizeItem = preg_replace ('/([^ ])(cm)/', '\1 \2', $sizeItem);	// Normalise to ensure space before, i.e. "cm" -> " cm"; e.g. /records/54851/ (test #337), but not /records/1102/ which already has a space (test #338)
				$sizeItem = preg_replace ('/(cm)(?!\.)/', '\1.\2', $sizeItem);	// Normalise to ensure dot after,    i.e. "cm" -> "cm.", if not already present; e.g. /records/1102/ (test #339), but not /records/1102/ which already has a dot (test #340)
				$size[$index] = $sizeItem;
			}
			
			# Add the size; e.g. multiple in /records/4329/ (test #336)
			$result .= " ;{$this->doubleDagger}c" . implode (" ;{$this->doubleDagger}c", $size);
		}
		
		# $e (NR) (Accompanying material): If included, '+' appears before ‡e; ‡e is then followed by *p [all text after '+']; e.g. /records/67235/ , /records/152326/ (test #333)
		$e = $this->pOrPt['additionalMaterial'];
		if ($e) {
			$result .= " +{$this->doubleDagger}e" . trim ($e);
		}
		
		# Ensure 300 ends in a dot or closing bracket; e.g. /records/67235/ (test #334)
		$result = $this->macro_dotEnd (trim ($result), '.)]');
		
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
	
	
	# Macro to generate the leading article count; this does not actually modify the string itself - just returns a number; e.g. 245 (based on *t) in /records/1116/ (test #355); 245 for Spanish record in /records/19042/ (test #356); 242 field (based on *tt) in /records/1204/ (test #357)
	public function macro_nfCount ($value, $language = false, &$errorHtml_ignored = false, $externalXml = NULL)
	{
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
		#!# For the 240 field, this needs to take the language whose index number is the same as t/tt/to... - see /records/1572/ (test #358)
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$xml = ($externalXml ? $externalXml : $this->xml);	// Use external XML if supplied
			$language = $this->xPathValue ($xml, $xPath);
		}
		
		# If no language specified, choose 'English'
		if (!strlen ($language)) {$language = 'English';}
		
		# End if the language is not in the list of leading articles
		if (!isSet ($this->leadingArticles[$language])) {return '0';}
		
		# Work through each leading article, and if a match is found, return the string length, e.g. /records/1116/ (test #355); /records/19042/ (test #356)
		# "Diacritical marks or special characters at the beginning of a title field that does not begin with an initial article are not counted as nonfiling characters." - https://www.loc.gov/marc/bibliographic/bd245.html
		# Therefore incorporate starting brackets in the consideration and the count if there is a leading article; see: https://www.loc.gov/marc/bibliographic/bd245.html , e.g. /records/27894/ (test #359), /records/56786/ (test #360), /records/4993/ (test #361)
		# Include known starting/trailing punctuation within the count, e.g. /records/11329/ (test #362) , /records/1325/ (test #363) like example '15$aThe "winter mind"' in MARC documentation , /records/10366/ , as per http://www.library.yale.edu/cataloging/music/filing.htm#ignore
		foreach ($this->leadingArticles[$language] as $leadingArticle) {
			if (preg_match ("/^(['\"\[]*{$leadingArticle}['\"]*)/i", $value, $matches)) {	// Case-insensitive match
				return (string) mb_strlen ($matches[1]); // The space, if present, is part of the leading article definition itself
			}
		}
		
		# Return '0' by default; e.g. /records/56593/ (test #364), /record/1125/ (test #365)
		return '0';
	}
	
	
	# Macro to set an indicator based on the presence of a 100/110 field; e.g. /records/1257/ (test #366)
	private function macro_indicator1xxPresent ($defaultValue, $setValueIfAuthorsPresent)
	{
		# If authors field present, return the new value; e.g. /records/1257/ (test #366)
		if (strlen ($this->authorsFields['default'][100]) || strlen ($this->authorsFields['default'][110]) || strlen ($this->authorsFields['default'][111])) {
			return $setValueIfAuthorsPresent;
		}
		
		# Otherwise return the default; e.g. /records/1844/ (test #367)
		return $defaultValue;
	}
	
	
	# Macro to convert language codes and notes for the 041 field; see: http://www.loc.gov/marc/bibliographic/bd041.html
	private function macro_languages041 ($value_ignored, $indicatorMode = false, &$errorHtml)
	{
		# Start the string
		$string = '';
		
		# Obtain any languages used in the record
		$languages = $this->xPathValues ($this->xml, '(//lang)[%i]', false);	// E.g. /records/168933/ (test #369)
		$languages = array_unique ($languages);	// E.g. /records/2071/ has two sets of French (test #368)
		
		# Obtain any note containing "translation from [language(s)]"; e.g. /records/4353/ (test #372) , /records/2040/ (test #373)
		#!# Should *abs and *role also be considered?; see results from quick query: SELECT * FROM `catalogue_processed` WHERE `value` LIKE '%translated from original%', e.g. /records/1639/ and /records/175067/
		$notes = $this->xPathValues ($this->xml, '(//note)[%i]', false);
		$nonLanguageWords = array ('article', 'published', 'manuscript');	// e.g. /records/196791/ , /records/32279/ (test #375)
		$translationNotes = array ();
		foreach ($notes as $note) {
			# Perform a match, e.g. /records/175067/ (test #376); this is not using a starting at (^) match e.g. /records/190904/ which starts "English translation from Russian" (test #377)
			if (preg_match ('/[Tt]ranslat(?:ion|ed) (?:from|reprint of)(?: original| the|) ([a-zA-Z]+)/i', $note, $matches)) {	// Deliberately not using strip_tags, as that would pick up Translation from <em>publicationname</em> which would not be wanted anyway, e.g. /records/8814/ (test #378)
				// application::dumpData ($matches);
				$language = $matches[1];	// e.g. 'Russian', 'English'
				
				# Skip blacklisted non-language words; e.g. /records/44377/ which has "Translation of article from", /records/32279/ (test #375)
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
				return '1';		// "1 - Item is or includes a translation"; e.g. /records/23776/ (test #379)
			} else {
				return '0';		// "0 - Item not a translation/does not include a translation"; e.g. /records/10009/ which is simply in another language (test #380)
			}
		}
		
		# If no *lang field and no note regarding translation, do not include 041 field; e.g. /records/4355/ (test #370)
		if (!$languages && !$translationNotes) {return false;}
		
		# $a: If no *lang field but note regarding translation, use 'eng'; e.g. /records/23776/ (test #371)
		if (!$languages && $translationNotes) {
			$languages[] = 'English';
		}
		
		# $a: Map each language listed in *lang field to 3-digit code in Language Codes worksheet and include in separate ‡a subfield; e.g. /records/168933/ (test #369)
		$a = array ();
		foreach ($languages as $language) {
			$a[] = $this->lookupValue ('languageCodes', $fallbackKey = false, true, false, $language, 'MARC Code', $errorHtml);
		}
		$string = implode ("{$this->doubleDagger}a", $a);	// First $a is the parser spec
		
		# $h: If *note includes 'translation from [language(s)]', map each language to 3-digit code in Language Codes worksheet and include in separate ‡h subfield; e.g. /records/4353/ (test #372) , /records/2040/ (test #373)
		$h = array ();
		if ($translationNotes) {
			foreach ($translationNotes as $note => $language) {
				$marcCode = $this->lookupValue ('languageCodes', $fallbackKey = false, true, false, $language, 'MARC Code', $errorHtml);
				if ($marcCode) {
					$h[] = $marcCode;
				} else {
					$errorHtml .= "The record included a language note but the language '<em>{$language}</em>'.";
				}
			}
		}
		if ($h) {
			$string .= "{$this->doubleDagger}h" . implode ("{$this->doubleDagger}h", $h);	// No cases of multiple $h found so no tests
		}
		
		# Return the result string
		return $string;
	}
	
	
	# Function to perform transliteration on specified subfields present in a full line; this is basically a tokenisation wrapper to macro_transliterate; e.g. /records/35733/ (test #381), /records/1406/ (test #382)
	public function macro_transliterateSubfields ($value, $applyToSubfields, &$errorHtml_ignored = NULL, $language = false /* Parameter always supplied by external callers */)
	{
		# If a forced language is not specified, obtain the language value for the record
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($this->xml, $xPath);
		}
		
		# Return unmodified if the language mode is default
		if ($language == 'default') {return $value;}
		
		# Ensure language is supported
		if (!isSet ($this->supportedReverseTransliterationLanguages[$language])) {return false;}	// Return false to ensure no result, e.g. /records/162154/ (test #383)
		
		# If the subfield list is specified as '*', treat this as all subfields present in the string (logically, a non-empty string will always have at least one subfield), so synthesize the applyToSubfields value from what is present in the supplied string
		if ($applyToSubfields == '*') {		// No actual cases at present, so no tests
			preg_match_all ("/{$this->doubleDagger}([a-z0-9])/", $value, $matches);
			$applyToSubfields = implode ($matches[1]);	// e.g. 'a' in the case of a 490; e.g. /records/15150/ , /records/1406/ (test #382)
		}
		
		# Explode subfield string and prepend the double-dagger
		$applyToSubfields = str_split ($applyToSubfields);
		foreach ($applyToSubfields as $index => $applyToSubfield) {
			$applyToSubfields[$index] = $this->doubleDagger . $applyToSubfield;
		}
		
		# Tokenise, e.g. array ([0] => "1# ", [1] => "‡a", [2] => "Chalyshev, Aleksandr Vasil'yevich.", [3] => "‡b", [4] => "Something else." ...; e.g. /records/35733/ (test #384)
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
	
	
	# Function to tokenise a string into subfields; e.g. /records/35733/ (test #384)
	private function tokeniseToSubfields ($line)
	{
		# Tokenise, e.g. array ([0] => "1# ", [1] => "‡a", [2] => "Chalyshev, Aleksandr Vasil'yevich.", [3] => "‡b", [4] => "Something else." ...
		return preg_split ("/({$this->doubleDagger}[a-z0-9])/", $line, -1, PREG_SPLIT_DELIM_CAPTURE);
	}
	
	
	# Macro to perform transliteration; e.g. /records/6653/ (test #107), /records/23186/ (test #108)
	private function macro_transliterate ($value, $language = false)
	{
		# If a forced language is not specified, obtain the language value for the record
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($this->xml, $xPath);
		}
		
		# End without output if no language, i.e. if default
		if (!$language) {return false;}		// No known code paths identified, as callers already appear to guard against this, so no tests
		
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
	private function macro_generateLeader ($value)
	{
		# Start the string
		$string = '';
		
		# Positions 00-04: "Computer-generated, five-character number equal to the length of the entire record, including itself and the record terminator. The number is right justified and unused positions contain zeros."
		$string .= '_____';		// Will be fixed-up later in post-processing, as at this point we do not know the length of the record (test #229)
		
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
			case 'Videorecording':			// E.g. /records/9992/ (test #385)
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
		if (!$this->form) {$value06 = 'a';}	// E.g. /records/1187/ (test #386)
		$string .= $value06;
		
		# Position 07: Bibliographic level
		#!# If merging, we would need to have a check that this matches
		$isPseudoAnalytic = (!$this->hostRecord && in_array ($this->recordType, array ('/art/in', '/art/j')));
		if ($isPseudoAnalytic) {
			$string .= 'm';		// E.g. /records/1330/ (test #550)
		} else {
			$position7Values = array (
				'/art/in'	=> 'a',
				'/art/j'	=> 'a',		// E.g. /records/1768/ (test #568)
				'/doc'		=> 'm',		// E.g. /records/1187/ (test #387)
				'/ser'		=> 's',
			);
			$string .= $position7Values[$this->recordType];
		}
		
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
		
		# Return the string; e.g. /records/1188/ (test #388)
		return $string;
	}
	
	
	# Helper function to determine the record type
	#!#C Copied from generate008 class
	private function recordType ()
	{
		# Determine the record type, used by subroutines
		$recordTypes = array (
			'/art/in',		// E.g. /records/1104/ (test #389)
			'/art/j',
			'/doc',
			'/ser',
		);
		foreach ($recordTypes as $recordType) {
			if ($this->xPathValue ($this->xml, $recordType)) {
				return $recordType;	// Match found
			}
		}
		
		# Not found
		return NULL;
	}
	
	
	# Macro for generating a datetime; e.g. /records/1000/ (test #390)
	private function macro_migrationDatetime ($value)
	{
		# Date and Time of Latest Transaction; see: http://www.loc.gov/marc/bibliographic/bd005.html
		return date ('YmdHis.0');
	}
	
	
	# Macro for generating a datetime; e.g. /records/1000/ (test #391)
	private function macro_migrationDate ($value)
	{
		# Date and Time of Latest Transaction; see: http://www.loc.gov/marc/bibliographic/bd005.html
		return date ('Ymd');
	}
	
	
	# Macro for generating the 007 field, Physical Description Fixed Field; see: http://www.loc.gov/marc/bibliographic/bd007.html
	private function macro_generate007 ($value)
	{
		# No form value
		if (!$this->form) {return 'ta';}	// E.g. /records/1187/ (test #394)
		
		# Start a list of return values
		$field007s = array ();
		
		# Get the list of *form values
		$forms = $this->xPathValues ($this->xml, '//form[%i]', false);
		
		# Loop through each *form
		foreach ($forms as $form) {
			
			# Define the values
			$field007values = array (
				'Map'					=> 'aj#|||||',
				'3.5 floppy disk'		=> 'cj#|a|||||||||',	// E.g. /records/179694/ (test #007)
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
				'Videorecording'		=> 'vf#|u||u|',			// E.g. /records/9992/ (test #392)
				'Text'					=> 'ta',				// #!# No test available yet as no records
			);
			
			# Look up the value and return it
			$field007s[] = $field007values[$form];
		}
		
		# Compile these into a multiline string, e.g. /records/181410/ (paired test #575 and #576)
		$string = implode ("\n007 ", $field007s);
		
		# Return the string
		return $string;
	}
	
	
	# Macro for generating the 008 field; tests have full coverage as noted in the generate008 class
	private function macro_generate008 ($value, $parameter_ignored, &$errorHtml)
	{
		# Subclass, due to the complexity of this field
		//$initialMemoryUsage = memory_get_usage ();
		//var_dump ('|- Before running generate008, memory usage is: ' . $this->memoryUsage () . 'MB');
		if (!$value = $this->generate008->main ($this->xml, $error)) {
			$errorHtml .= $error . '.';
		}
		//var_dump ('|- After running generateAuthors, memory usage is: ' . $this->memoryUsage () . 'MB; memory loss was: ' . (memory_get_usage () - $initialMemoryUsage) . ' bytes'); echo '<br />';	// ~2630592 bytes used first time; ~880 bytes each iteration
		
		# Return the value
		return $value;
	}
	
	
	# Macro to describe Russian transliteration scheme used, for 546 $a
	#!# Needs to be made consistent with languages041 macro
	#!# Uses only //lang[1]
	private function macro_isTransliterated ($language)
	{
		# Return string; e.g. /records/1526/ (test #421)
		if ($language == 'Russian') {
			return 'Russian transliteration entered into original records using BGN/PCGN 1947 romanization of Russian; Cyrillic text in MARC 880 field(s) reverse transliterated from this by automated process; BGN/PCGN 1947 text then upgraded to Library of Congress romanization.';
		}
		
		# No match; e.g. /records/1527/ (test #422)
		return false;
	}
	
	
	# Macro for generating an authors field, e.g. 100; tests have full coverage as noted in the generateAuthors class
	private function macro_generateAuthors ($value, $arg)
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
			if (!$languageMode = $this->getTransliterationLanguage ($this->xml)) {return false;}
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
	private function macro_880subfield6 ($value, $masterField)
	{
		# End if no value; e.g. 110 field in /records/151048/ (test #423)
		if (!$value) {return $value;}
		
		# Determine the field instance index, starting at 0; this will always be 0 unless called from a repeatable
		#!# Repeatable field support not checked in practice yet as there are no such fields
		$this->field880subfield6FieldInstanceIndex[$masterField] = (isSet ($this->field880subfield6FieldInstanceIndex[$masterField]) ? $this->field880subfield6FieldInstanceIndex[$masterField] + 1 : 0);
		
		# For a multiline field, e.g. /records/162152/ (test #424), parse out the field number, which on subsequent lines will not necessarily be the same as the master field; e.g. /records/68500/ (tests #425, #426)
		if (substr_count ($value, "\n")) {
			
			# Normalise first line
			if (!preg_match ('/^([0-9]{3} )/', $value)) {
				$value = $masterField . ' ' . $value;
			}
			
			# Convert to field, indicators, and line
			preg_match_all ('/^([0-9]{3}) (.+)$/m', $value, $lines, PREG_SET_ORDER);
			
			# Construct each line; link field may go into double digits, e.g. /records/150141/ (test #427, #428); indicators should match, e.g. /records/150141/ (test #429)
			$values = array ();
			foreach ($lines as $multilineSubfieldIndex => $line) {	// $line[1] will be the actual subfield code (e.g. 710), not the master field (e.g. 700), i.e. it may be a mutated value (e.g. 700 -> 710) as in e.g. /records/68500/ (tests #425, #426) and similar in /records/150141/ , /records/183507/ , /records/196199/
				$values[] = $this->construct880Subfield6Line ($line[2], $line[1], $masterField, $this->field880subfield6FieldInstanceIndex[$masterField], $multilineSubfieldIndex);
			}
			
			# Compile the result back to a multiline string
			$value = implode ("\n" . '880 ', $values);
			
		} else {
			
			# Render the line, e.g. 490 in /records/150141/ (test #430)
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
		$indexFormatted = str_pad ($this->field880subfield6Index, 2, '0', STR_PAD_LEFT);	// E.g. /records/150141/ (tests #427, #431)
		$subfield6 = $this->doubleDagger . '6 ' . $masterField . '-' . $indexFormatted;		// Decided to add space after $6 for clarity, to avoid e.g. '$6880-02' which is less clear than '$6 880-02', e.g. /records/150141/ (test #432)
		
		# Insert the subfield after the indicators; this is similar to insertSubfieldAfterMarcFieldThenIndicators but without the initial MARC field number; e.g. /records/150141/ (test #429)
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
	
	
	# Macro for generating the 245 field; tests have full coverage as noted in the generate245 class
	private function macro_generate245 ($value, $flag, &$errorHtml)
	{
		# If running in transliteration mode, require a supported language
		$languageMode = 'default';
		if ($flag == 'transliterated') {
			if (!$languageMode = $this->getTransliterationLanguage ($this->xml)) {return false;}
		}
		
		# Generate the value from the subclass
		$this->generate245->setRecord ($this->xml, $languageMode);
		$value = $this->generate245->main ($this->authorsFields, $error);
		if ($error) {
			$errorHtml .= $error . '.';
		}
		
		# Return the value, which may be false if transliteration not intended
		return $value;
	}
	
	
	# Macro for generating the 250 field
	private function macro_generate250 ($value, $ignored)
	{
		# Start an array of subfields
		$subfields = array ();
		
		# Implement subfield $a, e.g. /records/1405/ (test #433)
		if ($a = $this->xPathValue ($this->xml, '/*/edn')) {
			$subfields[] = "{$this->doubleDagger}a" . $a;
		}
		
		# Implement subfield $b; examples given in the function; e.g. /records/3887/ (test #434), /records/7017/ (has multiple *ee and multiple *n within this) (test #435)
		if ($b = $this->generate250b ($value, $this->xml, $ignored, $this->authorsFields)) {
			$subfields[] = "/{$this->doubleDagger}b" . $b;	# Bibcheck notes that space-slash ( /) is required (as shown in the MARC spec), e.g. /records/3421/ (test #567)
		}
		
		# Return false if no subfields; e.g. /records/1031/ (test #436)
		if (!$subfields) {return false;}
		
		# Compile the overall string; e.g. /records/45901/ (test #437)
		$value = implode (' ', $subfields);
		
		# Ensure the value ends with a dot (even if punctuation already present); e.g. /records/4432/ , /records/2549/ (test #438)
		$value = $this->macro_dotEnd ($value);
		
		# Return the value
		return $value;
	}
	
	
	# Helper function for generating the 250 $b subfield
	private function generate250b ($value, $ignored)
	{
		# Use the role-and-siblings part of the 245 processor
		$this->generate245->setRecord ($this->xml);
		
		# Create the list of subvalues if there is *ee?; e.g. /records/3887/ (test #434), /records/7017/ (has multiple *ee and multiple *n within this) (records #435) , /records/45901/ , /records/168490/
		$subValues = array ();
		$eeIndex = 1;
		while ($this->xPathValue ($this->xml, "//ee[$eeIndex]")) {	// Check if *ee container exists
			$subValues[] = $this->generate245->roleAndSiblings ("//ee[$eeIndex]");
			$eeIndex++;
		}
		
		# Return false if no subvalues, i.e. no $b due to absence of *ee, e.g. /records/1405/ (test #443)
		if (!$subValues) {return false;}
		
		# Implode values, e.g. /records/7017/ (test #435)
		$value = implode ('; ', $subValues);
		
		# Return the value
		return $value;
	}
	
	
	# Macro for generating the 490 field
	#!# Currently almost all parts of the conversion system assume a single *ts - this will need to be fixed; likely also to need to expand 880 mirrors to be repeatable
	#!# Repeatability experimentally added to 490 at definition level, but this may not work properly as the field reads in *vno for instance; all derived uses of *ts need to be checked
	#!# Issue of missing $a needs to be resolved in original data
	#!# For pseudo-analytic /art/j and possibly /art/in where there the host is a series, everything before a colon in the record's *pt (analytic volume designation) that describes a volume or issue number should possibly end up in 490
	public function macro_generate490 ($ts, $ignored, &$errorHtml_ignored = false, &$matchedRegexp = false, $reportGenerationMode = false)
	{
		# Obtain the *ts value or end, e.g. no *ts in /records/1253/ (test #444)
		if (!strlen ($ts)) {return false;}
		
		# Series titles:
		# Decided not to treat "Series [0-9]+$" as a special case that avoids the splitting into $a... ;$v...
		# This is because there is clear inconsistency in the records, e.g.: "Field Columbian Museum, Zoological Series 2", "Burt Franklin Research and Source Works Series 60"
		
		# Ensure the matched regexp, passed back by reference, is reset
		$matchedRegexp = false;
		
		# Add support for 490 $x (ISSN), which is MARC-style syntax added to some Muscat records; e.g. /records/148932/ (test #556), and /records/70414/ (test #566) which is not at the end
		$ts = preg_replace ('/ \$x(\d{4}-\d{3}[\dxX])/', " {$this->doubleDagger}x\\1", trim ($ts));	// Regexp in parenthesis as at https://en.wikipedia.org/wiki/International_Standard_Serial_Number
		$ts = preg_replace ("/([^,]) {$this->doubleDagger}x/", "\\1, {$this->doubleDagger}x", $ts);
		$ts = preg_replace ("/\s+{$this->doubleDagger}x/", "{$this->doubleDagger}x", $ts);
		
		# If the *ts contains a semicolon, this indicates specifically-cleaned data, so handle this explicitly; e.g. /records/2296/ (test #445)
		if (substr_count ($ts, ';')) {
			
			# Allocate the pieces before and after the semicolon; records checked to ensure none have more than one semicolon, e.g. /records/5517/ (test #446)
			list ($seriesTitle, $volumeNumber) = explode (';', $ts, 2);
			$seriesTitle = trim ($seriesTitle);		// E.g. /records/2296/ (test #447)
			$volumeNumber = trim ($volumeNumber);
			$matchedRegexp = 'Explicit semicolon match';
			
		} else {
			
			# By default, treat as simple series title without volume number, e.g. /records/1188/ (test #451)
			$seriesTitle = $ts;
			$volumeNumber = NULL;
			
			# Load the regexps list if not already done so
			if (!isSet ($this->regexps490)) {
				
				# Load the regexp list; this is sorted longest first to try to avoid ordering bugs; e.g. /records/6264/ (test #449)
				$this->regexps490Base = application::textareaToList ($this->applicationRoot . '/tables/' . 'volumeRegexps.txt', true, true, true);
				
				# Add implicit boundaries to each regexp
				$this->regexps490 = array ();
				foreach ($this->regexps490Base as $index => $regexp) {
					$this->regexps490[$index] = '^(.+)\s+(' . $regexp . ')$';
				}
			}
			
			# Find the first match, then stop, if any
			foreach ($this->regexps490 as $index => $regexp) {
				$delimeter = '~';	// Known not to be in the tables/volumeRegexps.txt list
				if (preg_match ($delimeter . $regexp . $delimeter . 'i', $ts, $matches)) {	// Regexps are permitted to have their own captures; matches 3 onwards are just ignored; this is done case-insensitively, e.g.: /records/170770/ (test #450)
					$seriesTitle = $matches[1];
					$volumeNumber = $matches[2];
					$matchedRegexp = ($index + 1) . ': ' . $this->regexps490Base[$index];		// Pass back by reference the matched regexp, prefixed by the number in the list, indexed from 1
					break;	// Relevant regexp found
				}
			}
		}
		
		# If there is a *vno, use it in $v (e.g. /records/10279/ (test #452)
		if (!$reportGenerationMode) {		// I.e. if running in MARC generation context, rather than for report generation
			if ($vno = $this->xPathValue ($this->xml, '//vno')) {
				$volumeNumber = ($volumeNumber ? $volumeNumber . ', ' : '') . $vno;		// If volume number (from *ts) already present, e.g. /records/9031/ (test #453), append the *vno to existing, separated by comma
			}
		}
		
		# Start with the $a subfield
		$string = $this->doubleDagger . 'a' . $seriesTitle;
		
		# Deal with optional volume number
		if (strlen ($volumeNumber)) {
			
			# Strip any trailing ,. character in $a, and re-trim, e.g. /records/3748/ (test #454)
			$string = preg_replace ('/^(.+)[.,]$/', '\1', $string);
			$string = trim ($string);
			
			# Add space-semicolon before $v if not already present, e.g. /records/3748/ (test #454)
			if (mb_substr ($string, -1) != ';') {	// Normalise to end ";"
				$string .= ' ;';
			}
			if (mb_substr ($string, -2) != ' ;') {	// Normalise to end " ;", e.g. /records/31402/ (test #455)
				$string = preg_replace ('/;$/', ' ;', $string);
			}
			
			# Add the volume number; Bibcheck requires: "490: Subfield v must be preceeded by a space-semicolon"
			$string .= $this->doubleDagger . 'v' . $volumeNumber;
		}
		
		# Return the string
		return $string;
	}
	
	
	# Macro for generating 5xx notes
	private function macro_generate5xxNote ($note, $field)
	{
		# Define supported types (other than default 500), specifying the captured text to appear
		$specialFields = array (
			505 => '^Contents: (.+)$',			// Actually implemented below, but has to be defined here to avoid it also becoming a standard 500, e.g. /records/1488/ (test #581)
			538 => '^(Mode of access: .+)$',	// 538 - System Details Note; see: https://www.loc.gov/marc/bibliographic/bd538.html , e.g. /records/145666/ (test #582)
		);
		
		# Supported special-case fields
		if (isSet ($specialFields[$field])) {
			
			# Check for a match and return the captured text if so
			if (preg_match ('/' . $specialFields[$field] . '/', $note, $matches)) {
				return $matches[1];
			}
			
			# Otherwise no result if no match
			return false;
		}
		
		# For standard 500 fields, ensure none of the specialist types match, as it will be caught in another invocation
		foreach ($specialFields as $supportedType => $regexp) {
			if (preg_match ('/' . $regexp . '/', $note)) {
				return false;
			}
		}
		
		# Otherwise, return the confirmed standard 500 note, e.g. /records/1019/ (tests #509, #510)
		return $note;
	}
	
	
	# Helper function for 505 - Formatted Contents Note; see: https://www.loc.gov/marc/bibliographic/bd505.html , e.g. /records/1488/ (test #581)
	private function macro_generate505Note ($note)
	{
		# End if the note is not a content note
		if (!preg_match ('/^Contents: (.+)$/', $note, $matches)) {
			return false;
		}
		
		# Use only the extracted section, removing "Contents: " which is assumed to be added by the library catalogue GUI; e.g. /records/1488/ (test #591)
		$note = $matches[1];
		
		# In enhanced format perform substitutions e.g. /records/4660/ (test #588); in simple format, retain as simple $a, e.g. /records/1488/ (test #587)
		if ($enhancedFormat = substr_count ($note, ' / ')) {
			
			# Define replacements
			$replacements = array (
				' / '	=> " /{$this->doubleDagger}r",
				' -- '	=> " --{$this->doubleDagger}t",
			);
			$note = strtr ($note, $replacements);
		}
		
		# Determine the indicators
		$firstIndicator = '0';
		$secondIndicator = ($enhancedFormat ? '0' : '#');	// E.g. /records/1488/ (test #589), /records/4660/ (test #590)
		
		# Determine the starting subfield type
		$openingSubfield = $this->doubleDagger . ($enhancedFormat ? 't' : 'a');	// E.g. /records/1488/ (test #587), /records/4660/ (test #588)
		
		# Compile the string
		$string = $firstIndicator . $secondIndicator . ' ' . $openingSubfield . $note;
		
		# Return the string
		return $string;
	}
	
	
	# Macro for generating the 541 field, which looks at *acq groups; it may generate a multiline result, e.g. /records/3959/ (test #456); see: https://www.loc.gov/marc/bibliographic/bd541.html
	#!# Support for *acc, which is currently having things like *acc/*date lost as is it not present elsewhere
	private function macro_generate541 ($value)
	{
		# Start a list of results
		$resultLines = array ();
		
		# Loop through each *acq in the record; e.g. multiple in /records/3959/ (test #456)
		$acqIndex = 1;
		while ($this->xPathValue ($this->xml, "//acq[$acqIndex]")) {
			
			# Start a line of subfields, used to construct the values; e.g. /records/176629/ (test #457)
			$subfields = array ();
			
			# Support $c - constructed from *fund / *kb / *sref
			/* Spec is:
				"*fund OR *kb OR *sref, unless the record contains a combination / multiple instances of these fields - in which case:
				- IF record contains ONE *sref and ONE *fund and NO *kb => ‡c*sref '--' *fund
				- IF record contains ONE *sref and ONE *kb and NO *fund => ‡c*sref '--' *kb"
			*/
			#!# Spec is unclear: What if there are more than one of these, or other combinations not shown here? Currently, items have any second (or third, etc.) lost, or e.g. *kb but not *sref would not show
			$fund = $this->xPathValues ($this->xml, "//acq[$acqIndex]/fund[%i]");	// Code		// #!# e.g. multiple at /records/132544/ , /records/138939/ - also need tests once decision made
			#!# Should $kb be top-level, rather than within an *acq group? What happens if multiple *acq groups, which will each pick up the same *kb
			$kb   = $this->xPathValues ($this->xml, "//kb[%i]");					// Exchange
			$sref = $this->xPathValues ($this->xml, "//acq[$acqIndex]/sref[%i]");	// Supplier reference
			$c = false;
			if (count ($sref) == 1 && count ($fund) == 1 && !$kb) {
				$c = $sref[1] . '--' . $fund[1];	// E.g. /records/176629/ (test #459)
			} else if (count ($sref) == 1 && count ($kb) == 1 && !$fund) {
				$c = $sref[1] . '--' . $kb[1];		// E.g. /records/195699/ (test #460)
			} else if ($fund) {
				$c = $fund[1];	// E.g. /records/132544/ (test #458)
			} else if ($kb) {
				$c = $kb[1];	// E.g. /records/1010/ (test #461)
			} else if ($sref) {
				$c = $sref[1];	// E.g. /records/168419/ (test #462)
			}
			if ($c) {
				$subfields[] = "{$this->doubleDagger}c" . $c;
			}
			
			# Create $a, from *o - Source of acquisition, e.g. /records/1050/ (test #463)
			if ($value = $this->xPathValue ($this->xml, "//acq[$acqIndex]/o")) {
				$subfields[] = "{$this->doubleDagger}a" . $value;
			}
			
			# Create $d, from *date - Date of acquisition, e.g. /records/3173/ (test #464)
			if ($value = $this->xPathValue ($this->xml, "//acq[$acqIndex]/date")) {
				$subfields[] = "{$this->doubleDagger}d" . $value;
			}
			
			#!# *acc/*ref?
			
			# Create $h, from *pr - Purchase price, e.g. /records/3173/ (test #465)
			if ($value = $this->xPathValue ($this->xml, "//acq[$acqIndex]/pr")) {
				$subfields[] = "{$this->doubleDagger}h" . $value;
			}
			
			# Register the line if subfields have been created, e.g. /records/3173/ (test #466)
			if ($subfields) {
				$subfields[] = "{$this->doubleDagger}5" . 'UkCU-P';	// Institution to which field applies, i.e. SPRI
				$resultLines[] = implode (' ', $subfields);
			}
			
			# Next *acq
			$acqIndex++;
		}
		
		# End if no lines, e.g. /records/3174/ (test #467)
		if (!$resultLines) {return false;}
		
		# Implode the list, e.g. /records/3959/ (tests #456, #468)
		$result = implode ("\n" . '541 0# ', $resultLines);
		
		# Return the result
		return $result;
	}
	
	
	# Macro for adding $5 to 563 - Binding Information, e.g. /records/3056/ (test #583)
	private function macro_addUkcup ($value)
	{
		# End if no value (not actually a necessary check as should not enter the function otherwise)
		if (!$value) {return false;}
		
		# Add $5 to each
		$value .= " {$this->doubleDagger}5UkCU-P";
		
		# Return the value
		return $value;
	}
	
	
	# Macro to determine if a value is not surrounded by round brackets, e.g. /records/1003/ (tests #469, #470)
	private function macro_isNotRoundBracketed ($value)
	{
		return ((mb_substr ($value, 0, 1) != '(') || (mb_substr ($value, -1) != ')') ? $value : false);
	}
	
	
	# Macro to determine if a value is surrounded by round brackets, e.g. /records/1003/ (tests #471, #472)
	private function macro_isRoundBracketed ($value)
	{
		return ((mb_substr ($value, 0, 1) == '(') && (mb_substr ($value, -1) == ')') ? $value : false);
	}
	
	
	# Macro to look up a *ks (UDC) value
	private function macro_addLookedupKsValue ($value, $parameter_ignored, &$errorHtml)
	{
		# End if no value
		if (!strlen ($value)) {return $value;}
		
		# Load the UDC translation table if not already loaded
		if (!isSet ($this->udcTranslations)) {
			$this->udcTranslations = $this->databaseConnection->selectPairs ($this->settings['database'], 'udctranslations', array (), array ('ks', 'kw'));
		}
		
		# Split out any additional description string for re-insertation below, e.g. /records/1008/ (test #473)
		$description = false;
		if (preg_match ("/^(.+)\[(.+)\]$/", $value, $matches)) {
			$value = $matches[1];
			$description = $matches[2];
		}
		
		# Skip if a known value (before brackes, which are now stripped) to be ignored, e.g. /records/166245/ (test #474)
		if (in_array ($value, $this->ksStatusTokens)) {return false;}
		
		# Ensure the value is in the table, e.g. /records/166245/ (test #475)
		if (!isSet ($this->udcTranslations[$value])) {
			// NB For the following error, see also /reports/periodicalpam/ which covers scenario of records temporarily tagged as 'MPP'
			$errorHtml .= "650 UDC field '<em>{$value}</em>' is not a valid UDC code.";
			return false;
		}
		
		# Construct the result string, e.g. /records/166245/ (test #475)
		$string = strtolower ('UDC') . $this->doubleDagger . 'a' . $value . ' -- ' . $this->udcTranslations[$value] . ($description ? ": {$description}" : false);
		
		# Return the result string
		return $string;
	}
	
	
	# Macro to look up a *rpl value
	private function macro_lookupRplValue ($value, $parameter_ignored, &$errorHtml)
	{
		# Fix up incorrect data, e.g. /records/16098/ (test #477)
		if ($value == 'E1') {$value = 'E2';}
		if ($value == 'H' ) {$value = 'H1';}
		
		# Define the *rpl mappings, e.g. /records/16098/ (test #478)
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
			$errorHtml .= "650 PGA field {$value} is not a valid PGA code letter.";
			return false;
		}
		
		# Construct the result string, e.g. /records/1102/ (test #479)
		$string = 'local' . $this->doubleDagger . 'a' . $value . ' -- ' . $mappings[$value];
		
		# Return the result string
		return $string;
	}
	
	
	# Generalised lookup table function
	public function lookupValue ($table, $fallbackKey, $caseSensitiveComparison = true, $stripBrackets = false, $value, $field, &$errorHtml)
	{
		# Load the lookup table
		$lookupTable = $this->loadLookupTable ($table, $fallbackKey, $caseSensitiveComparison, $stripBrackets);
		
		# If required, strip surrounding square/round brackets if present, e.g. "[Frankfurt]" => "Frankfurt" or "(Frankfurt)" => "Frankfurt", e.g. /records/2027/ (test #482)
		# Note that '(' is an odd Muscat convention, and '[' is the MARC convention
		# Note: In the actual data for 260, square brackets are preserved but round brackets are removed if present - see formatPl and its tests
		$valueOriginal = $value;	// Cache
		if ($stripBrackets) {
			if (preg_match ('/^[\[|\(](.+)[\]|\)]$/', $value, $matches)) {
				$value = $matches[1];
			}
		}
		
		# If doing case-insensitive comparison, convert the supplied value to lower case, e.g. /records/52260/ (test #483)
		if (!$caseSensitiveComparison) {
			$value = mb_strtolower ($value);
		}
		
		# Ensure the string is present
		if (!isSet ($lookupTable[$value])) {
			$errorHtml .= "In the {$table} table, the value '<em>{$valueOriginal}</em>' is not present in the table.";
			return NULL;
		}
		
		# Compile the result
		$result = $lookupTable[$value][$field];
		
		# Trim, in case of line-ends
		$result = trim ($result);
		
		# Return the result
		return $result;
	}
	
	
	# Function to load and process a lookup table, e.g. /records/173681/ (test #484)
	private function loadLookupTable ($table, $fallbackKey, $caseSensitiveComparison, $stripBrackets)
	{
		# Lookup from cache if present
		if (isSet ($this->lookupTablesCache[$table])) {
			return $this->lookupTablesCache[$table];
		}
		
		# Get the data table
		$lookupTable = file_get_contents ($this->applicationRoot . '/tables/' . $table . '.tsv');
		
		# Undo Muscat escaped asterisks @* , e.g. /records/180287/ (test #485)
		$lookupTable = $this->unescapeMuscatAsterisks ($lookupTable);
		
		# Convert to TSV
		$lookupTable = trim ($lookupTable);
		require_once ('csv.php');
		$lookupTableRaw = csv::tsvToArray ($lookupTable, $firstColumnIsId = true);
		
		# Define the fallback value in case that is needed
		if (!isSet ($lookupTableRaw[''])) {
			$lookupTableRaw[''] = $lookupTableRaw[$fallbackKey];	// E.g. /records/180290/ (test #486)
		}
		$lookupTableRaw[false]	= $lookupTableRaw[$fallbackKey];	// Boolean false also needs to be defined because no-match value from an xPathValue() lookup will be false, e.g. /records/180289/ (test #487)
		
		# Perform conversions on the key names
		$lookupTable = array ();
		foreach ($lookupTableRaw as $key => $values) {
			
			# Convert diacritics, e.g. /records/148511/ (test #488)
			$key = strtr ($key, $this->diacriticsTable);
			
			# Strip surrounding square/round brackets if present, e.g. "[Frankfurt]" => "Frankfurt" or "(Frankfurt)" => "Frankfurt"; no examples found but tested manually
			if ($stripBrackets) {
				if (preg_match ('/^[\[|\(](.+)[\]|\)]$/', $key, $matches)) {
					$key = $matches[1];
				}
				
				/*
				# Sanity-checking test while developing
				if (isSet ($lookupTable[$key])) {
					if ($values !== $lookupTable[$key]) {
						$this->errorHtml .= "In the {$table} definition, <em>{$key}</em> for field <em>{$field}</em> has inconsistent value when comapring the bracketed and non-bracketed versions.";
						return NULL;
					}
				}
				*/
			}
			
			# Register the converted value
			$lookupTable[$key] = $values;
		}
		
		# If doing case-insensitive comparison, convert values to lower case, e.g. /records/52260/ (test #489)
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
				$this->errorHtml .= "In the {$table} definition, <em>{$entry}</em> for field <em>{$field}</em> has invalid syntax.";
				return NULL;
			}
		}
		*/
		
		# Register to cache; this assumes that parameters will be consistent
		$this->lookupTablesCache[$table] = $lookupTable;
		
		# Return the table
		return $lookupTable;
	}
	
	
	# Helper function to unescape Muscat asterisks, e.g. /records/180287/ (test #485)
	#!#C Copied from muscatConversion (to avoid memory issues)
	private function unescapeMuscatAsterisks ($string)
	{
		return str_replace ('@*', '*', $string);
	}
	
	
	# Macro to generate the 500 for analytics (displaying free-form text version of 773 or host 245), whose logic is closely associated with 773, e.g. /records/1109/ (test #490)
	private function macro_generate500analytics ($value, $parameter_unused)
	{
		# End if not analytic; e.g. /records/1102/ (test #537)
		if (!in_array ($this->recordType, array ('/art/in', '/art/j'))) {return false;}
		
		# /art/j case
		if ($this->recordType == '/art/j') {
			
			# If there is a host record, we use the 773 (but do not prefix with "In: ")
			#!# Note: at present, some pseudo-analytics erroneously have a *kg in - this will either be fixed in the data, or we will change here to use 490 instead of 773 in cases of a controlled serial
			if ($this->hostRecord) {
				
				# Get the data from the 773, e.g. /records/1109/ (test #490)
				$result = $this->macro_generate773 ($value, $parameter_unused, $errorHtml_ignored, $mode500 = true);
				
			# If no host record (i.e. a pseudo-analytic), we assume it is an offprint, and use the title in the *j (i.e. second half) section
			} else {
				
				# Construct the string, starting with Offprint, and using the title in the *j (i.e. second half) section; e.g. /records/214872/ (test #541)
				$result = 'Offprint: ' . $this->xPathValue ($this->xml, '/art/j/tg/t');
				
				# Add the citation, starting with a dot (e.g. /records/214872/ (test #596)), followed by the pre-assembled citation value, e.g. /records/2237/ (test #660)
				if ($this->pOrPt['citation']) {
					$result = $this->macro_dotEnd ($result) . ' ' . $this->prefixVolAnalyticVolumeDesignation ($this->pOrPt['citation']);
				}
				
				# Add dot at end, e.g. /records/214872/ (test #595); see also equivalent tests for /art/in
				$result = $this->macro_dotEnd ($result);
			}
			
		# /art/in case
		} else if ($this->recordType == '/art/in') {
			
			# For genuine analytics, use the 245 of the host record, but prefix with "In: " ; e.g. /records/5472/ (test #538)
			if ($this->hostRecord) {
				
				# Obtain the 245 of the host record ; e.g. /records/5472/ (test #538)
				$marc = $this->parseMarcRecord ($this->hostRecord);
				$result = $marc['245'][0]['line'];
				
				# If $c contains a section derived from role, which we believe is after ' ; ' (and is the only use of semicolon in $c), remove that trailing section; e.g. /records/101441/ (test #552), /records/5029/ (test #553)
				$result = preg_replace ("/({$this->doubleDagger}c[^{$this->doubleDagger}]+) ; (.+)({$this->doubleDagger}|$)/", '\1\3', $result);
				
				# Add volume list if present, e.g. /records/4268/ (test #661)
				if ($this->pOrPt['volumeList']) {
					
					# If there is a " /" split (e.g. "and annexe /‡cInstitut de France"), put the Volume list just before the split point, e.g. /records/4268/ (test #662)
					$splitPoint = " /{$this->doubleDagger}";
					if (substr_count ($result, $splitPoint)) {
						
						# Add a dot before the inserted volume list that is being added just before the split point (e.g. /records/2905/ (test #663)), unless there is already one present (e.g. "1843 ... /‡cSir John" in /records/2995/ (test #664))
						$possibleDot = (substr_count ($result, '.' . $splitPoint) ? '' : '.');
						$result = str_replace ($splitPoint, $possibleDot . ' ' . $this->pOrPt['volumeList'] . $splitPoint, $result);	// Dot is added; this is safe because, in Muscat, the *t does not normally end with a dot, except for specific cases - see /reports/tdot/
						
					# Otherwise (i.e. if no split point), simply append; no instances found so no test
					} else {
						$result .= ' ' . $this->pOrPt['volumeList'];
					}
				}
				
				# Prefix 'In: ' at the start, e.g. /records/1222/ (test #492)
				$result = 'In: ' . $result;
				
			# For pseudo-analytics, there will be no host record, so create a title with statement of responsibility
			} else {
				
				# Construct the string, starting with Offprint, and using the title in the *in (i.e. second half) section; e.g. /records/1107/ (test #544)
				$result = 'Offprint: ' . $this->xPathValue ($this->xml, '/art/in/tg/t');
				
				# Add volume list if present, e.g. /records/11526/ (test #665)
				if ($this->pOrPt['volumeList']) {
					$result = $this->macro_dotEnd ($result) . ' ' . $this->prefixVolAnalyticVolumeDesignation ($this->pOrPt['volumeList']);
				}
				
				# Create the SoR based on 245; e.g. simple case in /records/14136/ (test #546), multiple authors example in /records/1330/ (test #547), corporate authors example in /records/1811/ (test #548); NB role confirmed not present in the data for pseudo-analytic pseudo-hosts
				$this->generate245->setRecord ($this->xml);
				$result .= $this->generate245->statementOfResponsibility ('/art/in', $result);
				
				# Ensure whole string ends with a dot, e.g. /records/1244/ added (test #593), /records/1107/ already present (test #594); see: https://www.oclc.org/bibformats/en/specialcataloging.html#CHDEBCCB
				$result = $this->macro_dotEnd ($result);
			}
			
			# Normalise space after colon when just before $b; e.g. /records/5472/ (test #536)
			$result = str_replace (":{$this->doubleDagger}b", ": {$this->doubleDagger}b", $result);
			
			# Ensure slash has space just before $c; e.g. /records/2072/ (test #539)
			$result = str_replace ("/{$this->doubleDagger}c", "/ {$this->doubleDagger}c", $result);
			
			# Ensure space before $h; e.g. /records/64883/ (test #551)
			$result = str_replace ("{$this->doubleDagger}h", " {$this->doubleDagger}h", $result);
		}
		
		# Strip out any $6 880 linking field; e.g. /records/22095/ (test #554)
		$result = preg_replace ("/({$this->doubleDagger}6 ?880-[0-9]{2} ?)({$this->doubleDagger}|$)/", '\2', $result);
		
		# Strip subfield indicators, e.g. /records/1129/ (test #491)
		$result = $this->stripSubfields ($result);
		
		# Assign as $a, e.g. /records/1109/ (test #540)
		$result = "{$this->doubleDagger}a" . $result;
		
		# Return the result
		return $result;
	}
	
	
	# Function to prefix Vol. to the analyticVolumeDesignation
	private function prefixVolAnalyticVolumeDesignation ($analyticVolumeDesignation)
	{
		# Add a "Vol." prefix when starting with a number; e.g. /art/j records: /records/214872/ (test #542) and negative test /records/215150/ (test #543); /art/in record: /records/1218/ (test #545)
		$prefix = (preg_match ('/^[0-9]/', $analyticVolumeDesignation) ? 'Vol. ' : '');
		return $prefix . $analyticVolumeDesignation;
	}
	
	
	# Function to provide subfield stripping, e.g. /records/1129/ (test #491)
	public function stripSubfields ($string)
	{
		return preg_replace ("/({$this->doubleDagger}[a-z0-9])/", '', $string);
	}
	
	
	# Function to look up the host record, if any
	private function lookupHostRecord ()
	{
		# Up-front, obtain the host ID (if any) from *kg, used in both 773 and 500, e.g. /records/1129/ (test #493)
		if (!$hostId = $this->xPathValue ($this->xml, '//k2/kg')) {return NULL;}
		
		# Obtain the processed MARC record; note that createMarcRecords processes the /doc records before /art/in records
		$hostRecord = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_marc', 'marc', $conditions = array ('id' => $hostId));
		
		# If there is no host record yet (because the ordering is such that it has not yet been reached), register the child for reprocessing in the second-pass phase
		if (!$hostRecord) {
			
			# Validate as a separate check that the host record exists; if this fails, the record itself is wrong and therefore report this error
			if (!$hostRecordXmlExists = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_xml', 'id', $conditions = array ('id' => $hostId))) {
				$this->errorHtml .= "Cannot match *kg, as there is no host record <a href=\"{$this->baseUrl}/records/{$hostId}/\">#{$hostId}</a>.";
			}
			
			# The host MARC record has not yet been processed, therefore register the child for reprocessing in the second-pass phase
			$this->secondPassRecordId = $this->recordId;
		}
		
		# Return the host record
		return $hostRecord;
	}
	
	
	# Macro to generate the 773 (Host Item Entry) field; see: http://www.loc.gov/marc/bibliographic/bd773.html ; e.g. /records/1129/ (test #493)
	private function macro_generate773 ($value, $parameter_unused, &$errorHtml_ignored = false, $mode500 = false)
	{
		# Start a result
		$result = '';
		
		# Only relevant if there is a host record (i.e. has a *kg which exists); records will usually be /art/in or /art/j only, but there are some /doc records, e.g. /records/1129/ (test #493), or negative case /records/2075/ (test #494)
		#!# At present this leaves tens of thousands of journal analytics without links (because they don't have explicit *kg fields) - these are pseudo-analytics
		if (!$this->hostRecord) {return false;}
		
		# Parse out the host record
		$marc = $this->parseMarcRecord ($this->hostRecord);
		
		# Start a list of subfields
		$subfields = array ();
		
		# Add 773 $7 (e.g. /records/215149/ (test #569)), except in 500 mode
		if (!$mode500) {	// E.g. /records/175904/ (test #572)
			$subfields[] = "{$this->doubleDagger}7" . $this->generate773dollar7 ($marc);
		}
		
		# Add 773 ‡a; *art/*in records only; $a is not used for *art/*j because journals don't have authors - instead $t is relevant
		if ($this->recordType == '/art/in') {
			
			# If the host record has a 100 field, copy in the 1XX (Main entry heading) from the host record, omitting subfield codes; otherwise use 245 $c
			if (isSet ($marc['100'])) {
				$aSubfieldValue = $this->combineSubfieldValues ('a', $marc['100']);	// E.g. lookup of record 2070 in /records/2074/ (test #495)
			} else if (isSet ($marc['245'])) {
				$aSubfieldValue = $this->combineSubfieldValues ('a', $marc['245'], array ('c'));	// E.g. lookup of record 1221 in /records/1222/ (test #496)
			}
			
			# Add a dot at the end; we know that there will be always be something following this, because in the (current) /art/in context, all parents are known to have a title, e.g. /records/67559/ (test #497)
			$subfields[] = $this->macro_dotEnd ($aSubfieldValue, $extendedCharacterList = '.])>-');	// See: https://www.oclc.org/bibformats/en/7xx/773.html which has more examples than the main MARC site
		}
		
		# Add 773 ‡t: Copy in the 245 (Title) ‡a and ‡b from the host record, omitting subfield codes, stripping leading articles, e.g. /records/67559/ (test #666)
		if (isSet ($marc['245'])) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($this->xml, $xPath);
			if (!$language) {$language = 'English';}
			$subfields[] = $this->combineSubfieldValues ('t', $marc['245'], array ('a', 'b'), ' ', $language);	// Space separator only, as already has : in master 245; e.g. /records/67559/ (test #529), /records/59148/ (test #530). Will automatically have a . (which replaces /) e.g. /records/59148/ (test #531)
		}
		
		# Add 773 ‡d: Copy in the 260 (Place, publisher, and date of publication) from the host record, omitting subfield codes; *art/*in records only; e.g. /records/59148/ (test #667)
		if ($this->recordType == '/art/in') {
			if (isSet ($marc['260'])) {
				
				#!# Need to add support for an 880(773) - e.g. /records/59148/ should get lines for both the LoC transliteration and the Cyrillic
				
				# Remove $6 880 field if present, e.g. /records/59148/ (test #668)
				if (isSet ($marc['260'][0]['subfields']['6'])) {
					unset ($marc['260'][0]['subfields']['6']);
				}
				
				# If publisher and year are present, use (no-space)-comma-space for the splitter between those two, combining them before colon splitting of other fields; e.g. /records/2614/ ; confirmed that, if reaching this point, $marc['260'][0]['subfields'] always contains 3 subfields
				if (isSet ($marc['260'][0]['subfields']['b']) && isSet ($marc['260'][0]['subfields']['c'])) {
					$subfieldBValue = rtrim ($marc['260'][0]['subfields']['b'][0]);	// Extract to avoid double-comma in next line, e.g. /records/103259/
					$marc['260'][0]['subfields']['_'][0] = $subfieldBValue . (substr ($subfieldBValue, -1) != ',' ? ',' : '') . ' ' . $marc['260'][0]['subfields']['c'][0];	// Make a virtual field, $_
					unset ($marc['260'][0]['subfields']['b']);
					unset ($marc['260'][0]['subfields']['c']);
				}
				
				# Split by space-colon-space; e.g. /records/59148/ (test #532)
				$subfields[] = $this->combineSubfieldValues ('d', $marc['260'], array (), ' : ', false, $normaliseTrailingImplode = true);
			}
		}
		
		# Add 773 ‡g: *pt (Related parts) [of child record, i.e. not host record]: analytic volume designation (if present), followed - if *art/*j - by (meaningful) date (if present)
		if (in_array ($this->recordType, array ('/art/in', '/art/j'))) {
			$gComponents = array ();
			
			# When generating a 500, use citation (e.g. /records/1109/ (test #673)), otherwise use volume list (e.g. /records/1668/ (test #674))
			$volumeListOrCitation = ($mode500 ? $this->pOrPt['citation'] : $this->pOrPt['volumeList']);
			if ($volumeListOrCitation) {	// E.g. /records/1668/ creates $g (test #514), but /records/54886/ has no $g (test #515)
				$separator = '; ';	// Semicolon is necessary for 500 mode; in 773 mode, semicolon rather than comma is chosen because there could be e.g. '73(1,5)' which would cause 'Vols. ' to appear rather than 'Vol. ', e.g. /records/6100/ (test #675)
				$prefix = (preg_match ('/^[0-9]/', $volumeListOrCitation) ? (substr_count ($volumeListOrCitation, $separator) ? 'Vols. ' : 'Vol. ') : '');	// E.g. /records/1668/ (test #521); multiple in /records/6100/ (test #675); negative case /records/1300/ (test #522)
				$gComponents[] = $prefix . $volumeListOrCitation;
			}
			
			# /art/j has date, e.g. /records/4844/ (test #519), /records/54886/ has no $g (test #515) as it is an *art/*in
			if ($this->recordType == '/art/j') {
				if ($d = $this->xPathValue ($this->xml, '/art/j/d')) {
					if (!in_array ($d, array ('[n.d.]', '-'))) {	// E.g. /records/1166/ (test #520)
						$gComponents[] = '(' . $this->xPathValue ($this->xml, '/art/j/d') . ')';
					}
				}
			}
			
			# Add $g if there are components, e.g. /records/1283/ (test #671)
			if ($gComponents) {
				$subfields[] = "{$this->doubleDagger}g" . implode (' ', $gComponents);	// Joined by space, e.g. /records/1200/ (test #672)
			}
		}
		
		# Except in 500 mode, add 773 ‡w: Copy in the 001 (Record control number) from the host record; this will need to be modified in the target Voyager system post-import, e.g. /records/6787/ (test #670)
		#!# For one of the merge strategies, the Voyager number will already be known
		if (!$mode500) {
			$subfields[] = "{$this->doubleDagger}w" . $marc['001'][0]['line'];
		}
		
		# Compile the result, separating by space, e.g. /records/6787/ (test #669)
		$result = implode (' ', $subfields);
		
		# Return the result
		return $result;
	}
	
	
	# Function to create the 773 $7 code; examples: /records/215149/ (test #569) /records/36315/ (test #570), /records/1768/ (test #571)
	# This is a four-digit code documented at https://www.loc.gov/marc/bibliographic/bd76x78x.html
	private function generate773dollar7 ($hostRecord)
	{
		# Start an array for the four values
		$dollar7 = array ();
		
		# Position 0 - Type of main entry heading; value corresponds to the 1XX tag of the related record
		switch (true) {
			case (isSet ($hostRecord[100])): $dollar7[0] = 'p'; break;	// E.g. /records/215149/ (test #569)
			case (isSet ($hostRecord[110])): $dollar7[0] = 'c'; break;	// E.g. /records/36315/ (test #570)
			case (isSet ($hostRecord[111])): $dollar7[0] = 'm'; break;
			default:                         $dollar7[0] = 'n'; break;
		}
		
		# Position 1 - Form of name; value of the first indicator in the 1XX of the related record
		switch (true) {
			case (isSet ($hostRecord[100])): $dollar7[1] = substr ($hostRecord[100][0]['indicators'], 0, 1); break;	// E.g. /records/215149/ (test #569)
			case (isSet ($hostRecord[110])): $dollar7[1] = substr ($hostRecord[110][0]['indicators'], 0, 1); break;	// E.g. /records/36315/ (test #570)
			case (isSet ($hostRecord[111])): $dollar7[1] = substr ($hostRecord[111][0]['indicators'], 0, 1); break;
			default:                         $dollar7[1] = 'n'; break;
		}
		
		# Position 2 - Type of record; is the value of Leader/06 (i.e. 7th character) of the related record, e.g. /records/1768/ (test #571)
		$dollar7[2] = substr ($hostRecord['LDR'][0]['line'], 6, 1);
		
		# Position 3 - Bibliographic level; is the value of Leader/07 (i.e. 8th character) of the related record, e.g. /records/1768/ (test #571)
		$dollar7[3] = substr ($hostRecord['LDR'][0]['line'], 7, 1);
		
		# Compile the value
		$dollar7 = implode ('', $dollar7);
		
		# Return the string
		return $dollar7;
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
		# End if language not supported
		if (!isSet ($this->leadingArticles[$language])) {return $string;}
		
		# Strip from start if present
		$list = implode ('|', $this->leadingArticles[$language]);
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
	
	
	# Macro to lookup periodical locations, which may generate a multiline result, e.g. /records/1102/ (test #621); see: https://www.loc.gov/marc/bibliographic/bd852.html
	# Note that the algorithm here is a simplified version of doc/852 locations flowchart.xlsx (which was created before work to clean up 'Not in SPRI' records)
	private function macro_generate852 ($value_ignored)
	{
		# Get the locations (if any), e.g. single location in /records/1102/ (test #621), multiple locations in /records/3959/ (test #622)
		$locations = $this->xPathValues ($this->xml, '//loc[%i]/location');
		
		# End if no locations, i.e. no result and therefore no 852 field, e.g. /records/1331/ (test #648) - this is the normal scenario for *status = RECEIVED, ON ORDER, etc.
		if (!$locations) {return false;}
		
		# For the special of "Not in SPRI" being present (in any *location), then create a single 852 value, with the other location(s) noted if any, e.g. /records/7976/ (test #649); /reports/notinspriinspri/ confirms there are now no cases of items "Not in SPRI" also having a SPRI location
		if (in_array ('Not in SPRI', $locations)) {	// NB Manually validated in the database that this is always present as the full string, not a match
			$otherLocations = array_diff ($locations, array ('Not in SPRI'));	// I.e. unset the entry containing this value
			$result  = "{$this->doubleDagger}2camdept";
			$result .= " {$this->doubleDagger}bSPRI-NIS";
			if ($otherLocations) {		// $x notes other location(s) if any; e.g. /records/7976/ (test #649); none in e.g. /records/1302/ (test #650)
				$result .= " {$this->doubleDagger}x" . implode (" {$this->doubleDagger}x", $otherLocations);		// Multiple in e.g. /records/31021/ (test #651)
			}
			$result .= " {$this->doubleDagger}zNot in SPRI";
			return $result;
		}
		
		# Report any that do not have a matching location; NB /reports/locationauthoritycontrol/ ensures authority control in terms of always havig a space after or end-of-string
		foreach ($locations as $location) {
			if (!preg_match ('@^(' . implode ('|', array_keys ($this->locationCodes)) . ')@', $location)) {
				$this->errorHtml .= 'The record contains an invalid *location value.';
				return false;
			}
		}
		
		# Loop through each location to create a result line
		$resultLines = array ();
		foreach ($locations as $index => $location) {
			
			# Split the value out into values for ‡b (location code) and ‡h (classification, which may or may not exist)
			$locationCode = false;
			$locationName = false;
			$classification = false;
			foreach ($this->locationCodes as $startsWith => $code) {
				if (preg_match ("|^({$startsWith})(.*)|", $location, $matches)) {
					$locationCode = $code;
					$locationName = $matches[1];	// I.e. non-regexp version of locationCode, e.g. "Electronic Resource (online)"
					$classification = trim ($matches[2]);		# "Cupboard 223" would have "223"; this is doing: "Remove the portion of *location that maps to a Voyager location code (i.e. the portion that appears in the location codes list) - the remainder will be referred to as *location_trimmed"
					break;
				}
			}
			
			# Start the record with 852 7# ‡2camdept (which is the source indicator), e.g. /records/3959/ (test #623)
			$result  = "{$this->doubleDagger}2camdept";
			
			# Add corresponding Voyager location code to record: ‡b SPRI-XXX, e.g. /records/31500/ (test #654)
			$result .= " {$this->doubleDagger}b" . $locationCode;
			
			# In the case of Shelved with ..., add clear description for use in $c, and do not use a classification, e.g. /records/1032/ (test #625)
			if ($isShelvedWith = preg_match ('/^Shelved with (pamphlets|monographs)$/', $location, $matches)) {
				$result .= " {$this->doubleDagger}c" . 'Issues shelved individually with ' . $matches[1];
			}
			
			# Online items get $h (and does not get $c disambiguation check), e.g. /records/1473/ (test #659)
			if ($locationName == 'Electronic Resource (online)') {
				$result .= " {$this->doubleDagger}h" . $locationName;
			} else {
				
				# In the case of location codes where there is a many-to-one relationship (e.g. "Library Office" and "Librarian's Office" both map to SPRI-LIO), except for SPRI-SER, then add the original location verbatim, so that it can be disambiguated; e.g. /records/2023/ (test #656); negative case (i.e. non-ambigous) in /records/1711/ (test #658)
				if (!in_array ($locationCode, array ('SPRI-SER', 'SPRI-SHF', 'SPRI-PAM'))) {	// E.g. /records/211109/ (test #657)
					$locationCodeCounts = array_count_values ($this->locationCodes);
					if ($locationCodeCounts[$locationCode] > 1) {
						$result .= " {$this->doubleDagger}c" . $locationName;	// E.g. SPRI-LIO in /records/2023/ (test #656)
					}
				}
			}
			
			# Does *location_original start with a number? This is to deal with cases like "141 C", in which the creation of "SPRI-SER" in the MARC record is implicit
			if (!$isShelvedWith) {		// "Shelved with ..." items do not get $h, e.g. /records/1032/ (test #653)
				if (preg_match ('/^[0-9]/', $location)) {
					
					# Add to record: ‡h <*location_original> (i.e. the full string), e.g. /records/62816/ gets "‡h141 C"
					$result .= " {$this->doubleDagger}h" . $location;
					
				} else {
					
					# "Is *location_trimmed empty?; If no, Add to record: ‡h <*location_trimmed>"; e.g. /records/1111/ (test #646) (which has "(*7) : 341.24" which comes from "Shelf (*7) : 341.24") ; Empty example at: /records/31500/ (test #647) which has "Bibliographers' Office"
					if (strlen ($classification)) {
						$result .= " {$this->doubleDagger}h" . $classification;
					}
				}
			}
			
			# If records are missing, add public note; e.g. /records/1014/ (test #655)
			# /reports/notinsprimissing/ confirms that no record has BOTH "Not in SPRI" (which would result in $z already existing above) and "MISSING"
			# Note that this will set a marker for each *location; the report /reports/multiplelocationsmissing/ lists these cases, which will need to be fixed up post-migration - we are unable to work out from the Muscat record which *location the "MISSING" refers to
			#!# Ideally also need to trigger this in cases where the record has note to this effect; or check that MISSING exists in all such cases by checking and amending records in /reports/notemissing/
			$ksValues = $this->xPathValues ($this->xml, '//k[%i]/ks');
			foreach ($ksValues as $ksValue) {
				if (substr_count ($ksValue, 'MISSING')) {		// Covers 'MISSING' and e.g. 'MISSING[2004]' etc.; e.g. /records/1323/ ; data checked to ensure that the string always appears as upper-case "MISSING" ; all records checked that MISSING* is always in the format ^MISSING\[.+\]$, using "SELECT * FROM catalogue_processed WHERE field = 'ks' AND value like  'MISSING%' AND value !=  'MISSING' AND value NOT REGEXP '^MISSING\\[.+\\]$'"
					$result .= " {$this->doubleDagger}z" . 'Item(s) missing';
					break;
				}
			}
			
			# Register this result
			$resultLines[] = trim ($result);
		}
		
		# Implode the list as a multiline if multiple, e.g. /records/3959/ (test #622)
		$result = implode ("\n" . '852 7# ', $resultLines);
		
		# Return the result
		return $result;
	}
	
	
	# Macro to generate 916, which is based on *acc/*ref *acc/*date pairs
	private function macro_generate916 ($value)
	{
		# Define the supported *acc/... fields that can be included
		#!# Not sure if con, recr, status should be present; ref and date are confirmed fine
		$supportedFields = array ('ref', 'date', 'con', 'recr');
		
		# Loop through each *acc in the record; e.g. multiple in /records/3959/ (test #585)
		$acc = array ();
		$accIndex = 1;
		while ($this->xPathValue ($this->xml, "//acc[$accIndex]")) {
			
			# Capture *acc/*ref and *acc/*date in this grouping
			$components = array ();
			foreach ($supportedFields as $field) {
				
				# Obtain the component; if there are multiple of the same type in this group, then first combine them using comma-space, e.g. /records/56613/ (test #269)
				if ($componentParts = $this->xPathValues ($this->xml, "//acc[$accIndex]/{$field}[%i]")) {
					$component = implode (', ', $componentParts);
					$components[] = $component;
				}
			}
			
			# Register this *acc group if components have been generated, combining with dash
			if ($components) {
				$acc[] = implode (' -- ', $components);	// E.g. /records/3959/ (test #585)
			}
			
			# Next *acc
			$accIndex++;
		}
		
		# End if none, e.g. /records/1102/ (test #149) which has no *acc group
		if (!$acc) {return false;}
		
		# Compile the components, separated by semicolon, e.g. /records/3959/ (test #586)
		$result = implode ('; ', $acc);
		
		# Return the result
		return $result;
	}
	
	
	# Macro to generate a 917 record for the supression reason, e.g. /records/1026/ (test #611)
	private function macro_showSuppressionReason ($value)
	{
		# End if no suppress reason(s), e.g. /records/1027/ (test #612)
		if (!$this->suppressReasons) {return false;}
		
		# Explode by comma, e.g. /records/1122/ (tests #613 and #614)
		$suppressReasons = explode (', ', $this->suppressReasons);
		
		# Create a list of results, adding an explanation for each, e.g. /records/1026/ (test #615)
		$resultLines = array ();
		foreach ($suppressReasons as $suppressReason) {
			$resultLines[] = 'Suppression reason: ' . $suppressReason . ' (' . $this->suppressionScenarios[$suppressReason][0] . ')';
		}
		
		# Implode the list, e.g. /records/1122/ (tests #613 and #614)
		$result = implode ("\n" . "917 ## {$this->doubleDagger}a", $resultLines);
		
		# Return the result line/multiline, e.g. /records/1026/ (test #611)
		return $result;
	}
	
	
	# Macro to determine cataloguing status; this uses values from both *ks OR *status, but the coexistingksstatus report is marked clean, ensuring that no data is lost
	#!# /reports/coexistingksstatus/ is not clean as of 2/Jan/2018
	private function macro_cataloguingStatus ($value)
	{
		# Return *ks if on the list of *ks status tokens, e.g. /records/56056/ (test #616)
		$ksValues = $this->xPathValues ($this->xml, '//k[%i]/ks');
		$results = array ();
		foreach ($ksValues as $ks) {
			$ksBracketsStrippedForComparison = (substr_count ($ks, '[') ? strstr ($ks, '[', true) : $ks);	// Strip brackets, so that e.g. "MISSING[2007]" matches against MISSING, e.g. /records/3549/ , /records/2823/ (test #618)
			if (in_array ($ksBracketsStrippedForComparison, $this->ksStatusTokens)) {
				$results[] = $ks;	// Actual *ks in the data, not the comparator version without brackets
			}
		}
		if ($results) {
			
			# Return the value, separating multiple values with semicolon, e.g. /records/60776/ (test #617)
			return implode ('; ', $results);
		}
		
		# Otherwise return *status, e.g. /records/1373/ (test #619), except for records marked explicitly to be suppressed, e.g. /records/10001/ (test #620), which is a special keyword not intended to appear in the record output
		$status = $this->xPathValue ($this->xml, '//status');	// E.g. /records/1373/ (test #619)
		if ($status == $this->suppressionStatusKeyword) {return false;}	// E.g. /records/10001/ (test #620)
		return $status;
	}
	
	
	# Lookup table for leading articles in various languages; note that Russian has no leading articles; see useful list at: https://en.wikipedia.org/wiki/Article_(grammar)#Variation_among_languages
	public function leadingArticles ($groupByLanguage = true)
	{
		# Define the leading articles
		# This is based on a list sent from pjg on 14/Jul/2014, which provided codes shown at https://www.loc.gov/marc/languages/language_name.html
		# Can verify presence of languages using: `SELECT value, COUNT(*) FROM catalogue_processed WHERE field = 'lang' GROUP BY value LIMIT 9999;`
		$leadingArticles = array (
			'a ' => 'English Galician Hungarian Portuguese',
			'al-' => 'Arabic',			// #!# Check what should happen for 245 field in /records/62926/ which is an English record but with Al- name at start of title
			'an ' => 'English',
			'ane ' => 'Middle-English',
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
			'ho ' => 'Aeolic-Greek',
			'il ' => 'Italian Maltese',
			"l'" => 'Catalan French Italian Maltese',		// e.g. /records/4571/ ; Catalan checked in https://en.wikipedia.org/wiki/Catalan_grammar#Articles
			'la ' => 'Catalan French Italian Spanish',
			'las ' => 'Spanish',
			'le ' => 'French Italian',
			'les ' => 'Catalan French',
			'lo ' => 'Italian Spanish',
			'los ' => 'Spanish',
			'os ' => 'Portuguese',
			'ta ' => 'Aeolic-Greek',
			'ton ' => 'Aeolic-Greek',
			'the ' => 'English',
			'um ' => 'Portuguese',
			'uma ' => 'Portuguese',
			'un ' => 'Catalan Spanish French Italian',
			'una ' => 'Catalan Spanish Italian',
			'une ' => 'French',
			'uno ' => 'Italian',
			'y ' => 'Welsh',
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
	
	
	# Function to load the ISBN validation library; see: https://github.com/Fale/isbn , and a manual checker at: http://www.isbn-check.com/
	private function loadIsbnValidationLibrary ()
	{
		# This is a Composer package, so work around the autoloading requirement; see: http://stackoverflow.com/questions/599670/how-to-include-all-php-files-from-a-directory
		foreach (glob ($this->applicationRoot . '/libraries/isbn/src/Isbn/*.php') as $filename) {
			require_once $filename;
		}
		
		# Load and instantiate the library
		require_once ('libraries/isbn/src/Isbn/Isbn.php');
		return new Isbn\Isbn();
	}
}

?>
