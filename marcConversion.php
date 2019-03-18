<?php

# Class to handle conversion of the data to MARC format
class marcConversion
{
	# Getter properties
	private $errorHtml = '';
	private $marcPreMerge = NULL;
	private $sourceRegistry = array ();
	private $filterTokens = array ();
	
	# Caches
	private $lookupTablesCache = array ();
	
	# Resources
	private $isbn;
	
	# Define the merge types
	private $mergeTypes = array (
		'TIP'	=> 'Exact Title match and ISSN match, and top answer in Probablistic search',
		'TP'	=> 'Exact Title, but not ISSN, and top answer in Probablistic search',
		'IP'	=> 'ISSN match, but not exact title, and top answer in Probablistic search',
		'P'		=> 'Probable match, unconfirmed, and top answer in Probablistic search',
		'C'		=> 'probable match, Confirmed',
	);
	
	# Define the location codes, as regexps
	private $locationCodes = array (
		# NB First in this list must be the numeric type for the reports to work correctly
		'[0-9]{1,3} ?[A-Z]'							=> 'SPRI-SER',	// Serial
		'Periodical'								=> 'SPRI-SER',	// Analytics (has parent serial, or has a reference in square brackets to a serial with which it is shelved)
		'Archives'									=> 'SPRI-ARC',
		'Atlas'										=> 'SPRI-ATL',
		'Basement'									=> 'SPRI-BMT',
		"Bibliographers' Office"					=> 'SPRI-BIB',
		'Cupboard'									=> 'SPRI-CBD',
		'Folio'										=> 'SPRI-FOL',
		'Large Atlas'								=> 'SPRI-LAT',
		"Librarian's Office"						=> 'SPRI-LIO',
		'Library Office'							=> 'SPRI-LIO',
		'Map Room'									=> 'SPRI-MAP',
		'Pam'										=> 'SPRI-PAM',
		'Picture Library'							=> 'SPRI-PIC',
		'Reference'									=> 'SPRI-REF',
		'Russian'									=> 'SPRI-RUS',
		'Shelf'										=> 'SPRI-SHF',
		'Special Collection'						=> 'SPRI-SPC',
		'Theses'									=> 'SPRI-THE',
		'Digital Repository'						=> 'SPRI-ELE',
		'Electronic Resource \(online\)'			=> 'SPRI-ELE',		// No items any more
		"Friends' Room"								=> 'SPRI-FRI',
		'Museum Working Collection'					=> 'SPRI-MUS',
		'Shelved with pamphlets'					=> 'SPRI-PAM',
		'Shelved with monographs'					=> 'SPRI-SHF',
		'Destroyed during audit'					=> 'IGNORE',
		// SPRI-NIS defined in marcConversion code
	);
	
	# Define known *ks values that represent status values rather than classifications
	private $ksStatusTokens = array (
		'MISSING',
		'PGA',		// Record intended for inclusion in next issue of PGA
		'X',		// Serial (issue(s)) not yet abstracted)
		'Y',		// Host item with analytics on card catalogue)
		'Z',		// Host item not yet analyzed)
		'C',		// Current serial
		'D',		// Dead serial
	);
	
	# Suppression keyword in *status
	private $suppressionStatusKeyword = 'SUPPRESS';
	
	# Acquisition date cut-off for on-order -type items; these range from 22/04/1992 to 30/10/2015; the intention of this date is that 'recent' on-order items (intended to be 1 year ago) would be migrated but suppressed, and the rest deleted - however, this needs review; newest is 2016/12/05
	private $acquisitionDate = '2015-01-01';
	
	# Supported transliteration upgrade (BGN/PCGN -> Library of Congress) fields, at either (top/bottom) level of a record
	# Confirmed other fields not likely, using: `SELECT field, count(*)  FROM `catalogue_processed` WHERE field NOT IN ('kw', 'ks', 'abs', 'doslink', 'winlink', 'lang', 'tc', 'tt', 'location') AND recordLanguage = 'Russian' AND `value` LIKE '%ya%' GROUP BY field;`
	private $transliterationUpgradeFields = array (
		'n1', 'n2', 'nd',	// 1xx, 7xx;	NB Keep these three together as generate245::classifyNdField() (as called from generate245::statementOfResponsibility() ), and generate245::roleAndSiblings() assumes they will be in sync in terms of transliteration
		'to',				// 240;			NB Then stripped, except records with *lto
		't',				// 245
		'ta',				// 246
		'pu',				// 260			NB *pl not in scope of transliteration - see note in generate260
		'ts',				// 490
		'note',				// 505			NB Then stripped, except for 'Contents: ' note records (minus known non-Russian)
		// (773 from host)	// 773
		'ft',				// 780
		'st',				// 785
	);
	
	# Define fields for transliteration name matching
	private $transliterationNameMatchingFields = array (
		'n1',
	);
	
	# HTML tags potentially present in output, which will then be stripped
	private $htmlTags = array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>');
	
	
	# Constructor
	public function __construct ($muscatConversion, $transliteration)
	{
		# Create class property handles to the parent class
		$this->databaseConnection = $muscatConversion->databaseConnection;
		$this->settings = $muscatConversion->settings;
		$this->applicationRoot = $muscatConversion->applicationRoot;
		$this->baseUrl = $muscatConversion->baseUrl;
		
		# Transliteration handles
		$this->transliteration = $transliteration;
		$this->supportedReverseTransliterationLanguages = $transliteration->getSupportedReverseTransliterationLanguages ();
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
		# Get the list of leading articles
		$this->leadingArticles = $this->leadingArticles ();
		
		# Load the diacritics table
		$this->diacriticsTable = $this->diacriticsTable ();
		
		# Load the suppression and ignoration scenarios
		$this->suppressionScenarios = $this->suppressionScenarios ();
		$this->ignorationScenarios = $this->ignorationScenarios ();
		
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
		
		# Create a registry of *pu shard language values
		$this->puLanguages = $this->getPuLanguages ();
		
	}
	
	
	# Getter for error HTML string
	public function getErrorHtml ()
	{
		# End if none
		if (!$this->errorHtml) {return $this->errorHtml;}
		
		# Assemble and return the HTML
		return "\n<p class=\"warning\"><img src=\"/images/icons/exclamation.png\" class=\"icon\" />" . ($this->recordId ? " Record <a href=\"{$this->baseUrl}/records/{$this->recordId}/\">{$this->recordId}</a>: " : '') . "MARC conversion error: {$this->errorHtml}</p>";
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
	
	
	# Getter for filter tokens, as a string
	public function getFilterTokensString ()
	{
		return implode (', ', $this->filterTokens);
	}
	
	
	# Getter for definitions
	
	public function getMergeTypes ()
	{
		return $this->mergeTypes;
	}
	
	public function getLocationCodes ()
	{
		return $this->locationCodes;
	}
	
	public function getKsStatusTokens ()
	{
		return $this->ksStatusTokens;
	}
	
	public function getSuppressionStatusKeyword ()
	{
		return $this->suppressionStatusKeyword;
	}
	
	public function getDiacriticsTable ()
	{
		return $this->diacriticsTable;
	}
	
	public function getSuppressionScenarios ()
	{
		return $this->suppressionScenarios;
	}
	
	public function getIgnorationScenarios ()
	{
		return $this->ignorationScenarios;
	}
	
	public function getAcquisitionDate ()
	{
		return $this->acquisitionDate;
	}
	
	public function getTransliterationUpgradeFields ()
	{
		return $this->transliterationUpgradeFields;
	}
	
	public function getTransliterationNameMatchingFields ()
	{
		return $this->transliterationNameMatchingFields;
	}
	
	public function getHtmlTags ()
	{
		return $this->htmlTags;
	}
	
	
	# Getter for ISBN library handle
	public function getIsbn ()
	{
		return $this->isbn;
	}
	
	
	# Main entry point
	# Local documentation at: http://www.lib.cam.ac.uk/libraries/login/bibstandard/bibstandards.htm
	public function convertToMarc ($marcParserDefinition, $recordXml, $mergeDefinition = array (), $mergeType = false, $mergeVoyagerId = false, $suppressReasons = false, $stripLeaderInMerge = true)
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
		
		# Create property handle for filter tokens
		$this->suppressReasons = $suppressReasons;
		$this->filterTokens = array ();
		
		# Ensure the line-by-line syntax is valid, extract macros, and construct a data structure representing the record
		if (!$datastructure = $this->convertToMarc_InitialiseDatastructure ($recordXml, $marcParserDefinition)) {return false;}
		
		# Load the record as a valid XML object
		$this->xml = $this->loadXmlRecord ($recordXml);
		
		# Determine the record number, used by several macros
		$this->recordId = $this->xPathValue ($this->xml, '//q0');
		
		# End if not all macros are supported
		if (!$this->convertToMarc_MacrosAllSupported ($datastructure)) {return false;}
		
		# Determine the record type
		$this->recordType = $this->recordType ();
		
		# Up-front, process author fields
		$this->authorsFields = $this->generateAuthors->createAuthorsFields ($this->xml);
		
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
			$record = $this->mergeWithExistingVoyager ($record, $mergeDefinition, $mergeType, $mergeVoyagerId, $stripLeaderInMerge);
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
		
		# Do a check to report any case where a where 880 fields do not have both a field (starting validly with a $6) and a link back; e.g. /records/1062/ has "245 ## ‡6880-01" and "880 ## ‡6245-01/(N" (tests #230, #231)
		preg_match_all ("/^880 [0-9#]{2} {$this->doubleDagger}6/m", $record, $matches);
		$total880fields = count ($matches[0]);
		$total880dollar6Instances = substr_count ($record, "{$this->doubleDagger}6880-");
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
	private function mergeWithExistingVoyager ($localRecord, $mergeDefinitions, $mergeType, $mergeVoyagerId, $stripLeaderInMerge)
	{
		# Return the record unchanged if merging is not enabled
		if (!$this->settings['mergingEnabled']) {
			return $localRecord;
		}
		
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
		if (!$voyagerRecord = $this->getExistingVoyagerRecord ($mergeVoyagerId, $stripLeaderInMerge)) {
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
	public function getExistingVoyagerRecord ($mergeVoyagerId, $stripLeaderInMerge = true, &$errorText = '')
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
		
		# Replace spaces with # in the Leader (LDR), to use the same format as generated records; cannot be tested as block below (with test #787) strips this in import
		foreach ($voyagerRecordShards as $shardId => $shard) {
			if ($shard['field'] == 'LDR') {
				$voyagerRecordShards[$shardId]['data'] = str_replace (' ', '#', $shard['data']);
				break;	// Only one, so stop loop
			}
		}
		
		# During import (but not in dynamic loading), remove the Leader (LDR) in the merge record, to avoid a double-leader, which causes Bibcheck to fail; e.g. /records/1011/ (test #787)
		if ($stripLeaderInMerge) {
			foreach ($voyagerRecordShards as $shardId => $shard) {
				if ($shard['field'] == 'LDR') {
					unset ($voyagerRecordShards[$shardId]);
					break;	// Only one, so stop loop
				}
			}
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
			preg_match_all ('/' . "({$this->doubleDagger}[a-z0-9])?" . '((R?)(i?){([^}]+)})' . "(\s*?)" /* Use of *? makes this capture ungreedy, so we catch any trailing space(s) */ . '/U', $line, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$subfieldIndicator = $match[1];		// e.g. $a (actually a dagger not a $)
				$findBlock = $match[2];	// e.g. '{//somexpath}'
				$isHorizontallyRepeatable = $match[3];	// The 'R' flag
				$isIndicatorBlockMacro = $match[4];	// The 'i' flag
				$xpath = $match[5];
				$trailingSpace = $match[6];		// Trailing space(s), if any, so that these can be preserved during replacement
				
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
					
					# For horizontally-repeatable fields, if any sub-value has been returned as false, skip it; no known cases, but can be tested against /records/16928/ by changing 876 parser to exceptBegins(Title), which should exclude the first $x without leaving a space (i.e. "$x $xFormerly")
					if ($isHorizontallyRepeatable) {
						foreach ($value as $index => $subValue) {
							if ($subValue === false) {
								unset ($value[$index]);
							}
						}
					}
					
					# For horizontally-repeatable fields, apply uniqueness after macro processing; e.g. if Lang1, Lang2, Lang3 becomes translatedlangA, translatedlangB, translatedlangB, unique to translatedlangA, translatedlangB; no examples available
					if ($isHorizontallyRepeatable) {
						$value = array_unique ($value);		// Key numbering may now have holes, but the next operation is imploding anyway
					}
					
					# If horizontally-repeatable, compile with the subfield indicator as the implode string, including a space for clarity, e.g. /records/1010/ (test #752)
					if ($isHorizontallyRepeatable) {
						$value = implode (' ' . $xpathReplacementSpec['horizontalRepeatability'], $value);
					}
					
					# Register the processed value
					$datastructure[$lineNumber]['xpathReplacements'][$find]['replacement'] = $value;	// $value is usually a string, but an array if repeatable
				} else {	// i.e. !$value :
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
				$counts[$macroBlock] = (is_string ($replacementValues) ? 1 : count ($replacementValues));	// Check for is_string to avoid PHP7.2 warning following change in count() ; see: https://php.net/count#example-6224 and https://wiki.php.net/rfc/counting_non_countables
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
						
						# If there is an 'A' (all) control character, require all placeholders to have resulted in text; e.g. /records/3056/ (test #257), /records/3057/ (test #258)
						#!# Currently this takes no account of the use of a macro in the nonfiling-character section (e.g. 02), i.e. those macros prefixed with indicators; however in practice that should always return a string
						if (in_array ('A', $datastructure[$lineNumber]['controlCharacters'])) {
							if (!$blockHasValue) {
								continue 2;	// i.e. break out of further processing of blocks on this line (as further ones are irrelevant), and skip the whole line registration below
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
		
		# Insert 880 reciprocal links; see: http://www.lib.cam.ac.uk/libraries/login/documentation/Unicode_non_roman_cataloguing_handout.pdf ; e.g. /records/1062/ has "245 ## ‡6880-01" and "880 ## ‡6245-01" (tests #230, #231)
		foreach ($this->field880subfield6ReciprocalLinks as $lineOutputKey => $linkToken) {		// $lineOutputKey is e.g. 700_0
			
			# Report data mismatches
			if (!isSet ($outputLines[$lineOutputKey])) {
				$this->errorHtml .= "Line output key {$lineOutputKey} does not exist in the output lines.";
			}
			
			# For multilines, split the line into parts, prepend the link token
			if (is_array ($linkToken)) {
				$lines = explode ("\n", $outputLines[$lineOutputKey]);	// Split out
				foreach ($lines as $i => $line) {
					if (isSet ($linkToken[$i])) {
						$lines[$i] = $this->insertSubfieldAfterMarcFieldThenIndicators ($line, $linkToken[$i]);
					}
				}
				$outputLines[$lineOutputKey] = implode ("\n", $lines);	// Reassemble; e.g. /records/1697/ (test #262)
				
			# For standard lines, do a simple insertion
			} else {
				$outputLines[$lineOutputKey] = $this->insertSubfieldAfterMarcFieldThenIndicators ($outputLines[$lineOutputKey], $linkToken);	// E.g. /records/1062/ (test #263)
			}
		}
		
		# Compile the record
		$record = implode ("\n", $outputLines);
		
		# Strip tags (introduced in specialCharacterParsing) across the record: "in MARC there isn't a way to represent text in italics in a MARC record and get it to display in italics in the OPAC/discovery layer, so the HTML tags will need to be stripped."
		$record = str_replace ($this->htmlTags, '', $record);	// E.g. /records/1131/ (test #264), /records/2800/ (test #265), /records/61528/ (test #266)
		
		# Return the record
		return $record;
	}
	
	
	# Function to modify a line to insert a subfield after the opening MARC field and indicators; for a multiline value, this must be one of the sublines; e.g. /records/1697/ (test #262), /records/1062/ (test #263)
	private function insertSubfieldAfterMarcFieldThenIndicators ($line, $insert)
	{
		return preg_replace ('/^([0-9]{3}) ([0-9#]{2}) (.+)$/', "\\1 \\2 {$insert}\\3", $line);
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
				$string = $this->{$macroMethod} ($string, $parameter, $this->errorHtml);	// E.g. $b for colonSplit in 246 in /records/3765/ (test #268)
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
		# Extract qualifying information, e.g. /records/73935/, /records/177698/, /records/215088/ (test #592), X check digit in /records/165286/ (test #741)
		$q = false;
		if (preg_match ('/^([0-9X]+) \(([^)]+)\)$/', $value, $matches)) {
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
	
	
	# Macro to ensure a string does not match a specified (and exact) value; e.g. filtering out of only 'English' for 546 in /records/1007/ (test #802)
	private function macro_exceptExactly ($value, $text)
	{
		# Return false if the value matches the specified text; e.g. no 546 in /records/1007/ (test #802)
		if ($value === $text) {return false;}
		
		# Otherwise, pass the value through unamended
		return $value;
	}
	
	
	# Macro to ensure a string does not start with a specified value; e.g. filtering out of only 'Provenance: '... for 876 in /records/8957/ (test #809)
	private function macro_exceptBegins ($value, $text)
	{
		# Return false if the value begins with the specified text; e.g. no 876 in /records/8957/ (test #809)
		if (mb_substr ($value, 0, mb_strlen ($text)) === $text) {return false;}
		
		# Otherwise, pass the value through unamended
		return $value;
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
	
	
	# Macro to implement a ternary check; e.g. /records/1122/ (test #277), /records/1921/ (test #278)
	private function macro_ifElse ($value_ignored /* If empty, the macro will not even be called, so the value has to be passed in by parameter */, $parameters)
	{
		# Parse the parameters
		list ($xPath, $ifValue, $elseValue) = explode (',', $parameters, 3);
		
		# Determine the value
		$value = $this->xPathValue ($this->xml, $xPath);
		
		# Return the result
		return ($value ? $ifValue : $elseValue);
	}
	
	
	# Macro to check whether a value matches a supplied string, passing through the value if so, or returning values; e.g. /records/88661/ (test #831), negative case: /records/1319/ (test #832)
	private function macro_ifXpathValue ($value, $parameters)
	{
		# Parse the parameters
		list ($xPath, $testValue) = explode (',', $parameters, 2);
		
		# Determine the value
		$xPathValue = $this->xPathValue ($this->xml, $xPath);
		
		# Return the result
		return ($xPathValue == $testValue ? $value : false);
	}
	
	
	# Macro to check whether a value does not match a supplied string, passing through the value if so, or returning values; e.g. /records/213625/ (test #918), (double-)negative case: /records/5265/ (test #917)
	private function macro_ifNotXpathValue ($value, $parameters)
	{
		# Parse the parameters
		list ($xPath, $testValue) = explode (',', $parameters, 2);
		
		# Determine the value
		$xPathValue = $this->xPathValue ($this->xml, $xPath);
		
		# Return the result
		return ($xPathValue == $testValue ? false : $value);
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
		
		# Return unmodified if character already present; for comparison purposes only, this is checked against a strip-tagged version in case there are tags at the end of the string, e.g. the 710 line at /records/7463/ (test #696)
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
		
		# Implode and return; e.g. /records/160854/ (test #293), /records/1144/ (test #294), /records/115769/ (test #295)
		return application::commaAndListing ($values);
	}
	
	
	# Macro to create 260; $a and $b are grouped as there may be more than one publisher, e.g. /records/76743/ (#test 297); see: https://www.loc.gov/marc/bibliographic/bd260.html
	private function macro_generate260 ($value_ignored, $transliterate = false)
	{
		# If transliterating, ensure this is transliterable, by determining if this record is in the transliteration table; end if not Russian; e.g. /records/148932/ (test #316)
		# This correctly deals with main/bottom-half issues, e.g. English record with bottom-half Russian: /records/189648/ (test #770); no examples of the opposite way round, but transliterations table from which this feature logic depends on, is considered robust
		if ($transliterate) {
			if (!isSet ($this->puLanguages[$this->recordId])) {
				return false;
			}
		}
		
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
				$plValue = $this->xPathValue ($this->xml, "//pg[$pgIndex]/pl[{$plIndex}]");	// E.g. /records/1639/ has multiple (test #299)
				if ($plIndex > 1 && !strlen ($plValue)) {break;}	// Empty $pl is fine for first and will show [S.l.] ('sine loco', i.e. 'without place'), e.g. /records/1484/ (test #300), but after that should not appear (no examples found)
				$plValues[] = $this->formatPl ($plValue);
			}
			
			# Obtain the raw *pu value(s) for this *pg group
			$puValue = $this->xPathValue ($this->xml, "//pg[$pgIndex]/pu");
			$puValues = array ();
			for ($puIndex = 1; $puIndex <= 20; $puIndex++) {
				$puValue = $this->xPathValue ($this->xml, "//pg[$pgIndex]/pu[{$puIndex}]");	// E.g. /records/1223/ has multiple (test #301)
				if ($puIndex > 1 && !strlen ($puValue)) {break;}	// Empty $pu is fine for first and will show [s.n.] ('sine nomine', i.e. 'without name'), e.g. /records/1730/ (test #302), but after that should not appear (no examples found)
				$puValues[] = $this->formatPu ($puValue);	// Will always return a string
			}
			
			# Transliterate *pu if required; e.g. /records/6996/ (test #58); case with protected string has that left but other elements transliterated, e.g. /records/210284/ (test #869)
			# NB No attempt is made to transliterate *pl; e.g. /records/12099/ (test #306) - too few cases which would mean c. 250 whitelisted strings but only 50 possible Russian strings, and too many would be difficult to determine manually if in Russian - see: `SELECT DISTINCT value FROM catalogue_processed WHERE field LIKE 'pl' AND recordLanguage LIKE 'Russian' ORDER BY value;`
			if ($transliterate) {
				if ($puValues) {
					$transliterationPresent = false;
					foreach ($puValues as $index => $puValue) {
						
						# NB The language is force-set to Russian, as the top guard clause would prevent getting this far; also this avoids auto-use in macro_transliterate() of //lang[1] which will not be section-half compliant
						$puValues[$index] = $this->macro_transliterate ($puValue, 'Russian', $errorHtml_ignored, $nonTransliterable, $nonTransliterableReturnsFalse = false);	// NB: [s.n.] does not exist within any Russian record, but should not get transliterated anyway, being in square brackets (not possible to test)
						
						# Unless the string has been found to be non-transliterable, flag that transliteration is present, to enable full non-transliterable lines to be removed from 880 generation, e.g. /records/214774/ (test #870)
						if (!$nonTransliterable) {
							$transliterationPresent = true;
						}
					}
					
					# Return false if no transliteration has taken place, e.g. /records/214774/ (test #870), but kept in /records/210284/ (test #869) where one subfield does have transliteration
					if (!$transliterationPresent) {return false;}	// It is enough to check within the *pu handling, as this is the only part of generate260 involving transliteration
				}
			}
			
			# Assemble the result; NB If there is no $a and $b value, but there is a date, "260 ## ‡a[S.l.] :‡b[s.n.],‡c1985." is indeed correct to have $a and $b both created as shown, e.g. /records/76740/ (test #652)
			$results[$pgIndex]  = "{$this->doubleDagger}a" . implode (" ;{$this->doubleDagger}a", $plValues) . ' :';	// E.g. /records/1639/ has multiple, so semi-colon present (test #299)
			$results[$pgIndex] .= "{$this->doubleDagger}b" . implode (" :{$this->doubleDagger}b", $puValues);	// "a colon (:) when subfield $b is followed by another subfield $b" at https://www.loc.gov/marc/bibliographic/bd260.html , e.g. /records/1223/ (test #304)
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
	
	
	# Function to create a registry of *pu shard language values
	private function getPuLanguages ()
	{
		# Return the lookup; selectPairs will smash recordId values together, which is fine, as even if there is more than one *pu per record, the values will all be the same
		return $puLanguages = $this->databaseConnection->selectPairs ($this->settings['database'], 'transliterations', array ('field' => 'pu'), array ('recordId', 'language'));
	}
	
	
	# Helper function for 260a *pl
	private function formatPl ($plValue)
	{
		# If no *pl, put '[S.l.]'. ; e.g. /records/1484/ (test #300) ; decision made not to make a semantic difference between between a publication that is known to have an unknown publisher (i.e. a check has been done and this is explicitly noted) vs a publication whose check has never been done, so we don't know if there is a publisher or not.
		if (!$plValue) {
			return '[S.l.]';	// Meaning 'sine loco' ('without a place')
		}
		
		# *pl [if *pl is '[n.p.]' or '-', this should be replaced with '[S.l.]' ]. ; e.g. /records/1787/ (test #799), /records/1102/ (test #308)
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
		# *pu [if *pu is '[n.pub.]' or '-', this should be replaced with '[s.n.]' ] ; e.g. /records/1105/ (test #313), /records/1788/ (test #800)
		if (!strlen ($puValue) || $puValue == '[n.pub.]' || $puValue == '-') {
			return '[s.n.]';	// Meaning 'sine nomine' ('without a name')
		}
		
		# Otherwise, return the value unmodified; e.g. /records/1117/ (test #314)
		return $puValue;
	}
	
	
	# Up-front, process *p/*pt to parse into its component parts, for use in 300/500/773
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
				'leaf of plate',
			);
			$protectedSubstringsPattern = '<||%i||>';
			$protectedParts = array ();
			foreach ($phrases as $i => $phrase) {
				$key = str_replace ('%i', $i, $protectedSubstringsPattern);
				$protectedParts[$phrase] = $key;
			}
			$pOrPt = strtr ($pOrPt, $protectedParts);
			
			# Next split by the keyword which acts as separating point between citation and an optional $b (i.e. is the start of an optional $b); e.g. /records/51787/ (test #328); first comma cannot be used reliably because the pagination list could be e.g. "3,5,97-100"; split is done for the first instance of a split word, e.g. /records/12780/ (test #535)
			$splitWords = array ('col', 'diag', 'fig', 'figures', 'graph', 'ill', 'illus', 'map', 'port', 'portrait', 'table', );	// These may be pluralised, using the s? below; e.g. /records/1684/ (test #512); 'portrait'/'portraits' acceptable string, e.g. /records/27684/ (test #884)
			$matches = preg_split ('/' . "\b" . '((?:' . implode ('|', $splitWords) . ')s?' . "\b.*$)" . '/', $pOrPt, 2, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);		// Use of \b word boundary ensures not splitting at substrings, e.g. bibliography at 'graph': /records/54670/ (test #220); .* is used so that both appreviated types ("ill.", "diag.") and non-abbreviated types ("tables", "map") - see test for latter: /records/24489/ (test #630)
			if (count ($matches) == 2) {
				$pOrPt = trim ($matches[0]);
				$physicalDescription = trim ($matches[1]);
			}
			
			# Revert specific phrases (test as per forward part of algorithm, above)
			$pOrPt = strtr ($pOrPt, array_flip ($protectedParts));
			$physicalDescription = strtr ($physicalDescription, array_flip ($protectedParts));
		}
		
		# At this point, $pOrPt represents the citation, e.g. /records/2237/ (test #681), general example with $b having been split off: /records/189056/ (test #526)
		$citation = $pOrPt;
		
		# Normalise 'p' to have a dot after; safe to make this change after checking: `SELECT * FROM catalogue_processed WHERE field IN('p','pt','vno','v','ts') AND value LIKE '%p%' AND value NOT LIKE '%p.%' AND value REGEXP '[0-9]p' AND value NOT REGEXP '[0-9]p( |,|\\)|\\]|$)';`
		$citation = preg_replace ('/([0-9])p([^.]|$)/', '\1p.\2', $citation);	// E.g. /records/6002/ , /records/6448/ (test #346); should not be multiple in single string, but previous pre-fixed data showed this worked correctly
		
		# Add space between the number and the 'p.'; e.g. /records/49133/ for p. (test #349); normalisation not required: /records/13745/ (test #350); multiple instances of page number in /records/2031/ though this is wrong data as per /reports/pnodot/
		$citation = preg_replace ('/([0-9])(p\.)/', '\1 \2', $citation);
		$citation = preg_replace ('/(\[[0-9]\])(p\.)/', '\1 \2', $citation);	// E.g. /records/13270/ (test #711)
		
		# Remove comma/colon/semicolon at end; e.g. /records/9529/ (test #680)
		$citation = trim (preg_replace ('/^(.+)[,;:]$/', '\1', trim ($citation)));
		
		# Tokenise the citation list to volume => pagination pairs; tests present in function
		$citationListValues = $this->tokeniseCitationList ($citation);
		
		# Construct the volume list using the keys from the citation list, e.g. /records/4268/ (test #678); multiple separated by semicolon-space, e.g. /records/6100/ (test #679)
		$volumeList = implode ('; ', array_keys ($citationListValues));		// As per same comment below in macro_generate773, semicolon rather than comma is chosen because there could be e.g. '73(1,5)' which would cause 'Vols. ' to appear rather than 'Vol. '
		
		# If there is a *vno, add this at the start of the analytic volume designation, before any pagination (extent) data from *pt; e.g. /records/6787/ (test #352) and negative test for 300 in same record /records/6787/ (test #351)
		if ($vno = $this->xPathValue ($this->xml, '//vno')) {
			$volumeList = $this->macro_dotEnd ($vno) . (strlen ($volumeList) ? ' ' : '') . $volumeList;		// E.g. dot added before other citation substring in /records/7865/ (test #519); no existing $a so no comma in /records/6787/ (test #352)
		}
		
		# Create the page string or count; if one than one item, a count is used; tests present in function
		$pages = $this->pagesString ($citationListValues);
		
		# Assemble the registry
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
			# Thus, if the citation starts with colon, it will be stripped out; e.g. /records/1107/ (test #523)
			if (substr_count ($citationPart, ':')) {
				list ($volume, $paginationString) = explode (':', $citationPart, 2);
				$volume = trim ($volume);
				$paginationString = trim ($paginationString);
			} else {
				$volume = '';	// Use this rather than (bool) false, as otherwise becomes key zero: $citationListValues[0]: ...
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
			$pageCount = false;
			$suffix = false;
			foreach ($citationListValues as $volume => $paginationString) {
				$pageCount += $this->pageCount ($paginationString, $suffix);
			}
			$pagesString = $pageCount . ' p.';
			if ($suffix) {
				$pagesString .= ', ' . $suffix;	// E.g. /records/10957/ (test #776)
			}
		}
		
		# Return the assembled pages string
		return $pagesString;
	}
	
	
	# Helper function to determine if pages should have p. prefixed, e.g. /records/1107/ (test #524)
	private function pDotPrefixRequired ($pages)
	{
		# Do not add p. if already present: 'p. '*pt [number range after ':' and before ',']; e.g. /records/6448/ (test #525)
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
	private function pageCount ($paginationString, &$suffix)
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
				
			# "[1] leaf of plates" should be ignored in the page count, but added as a suffix; e.g. /records/10957/ (test #776)
			} else if (preg_match ('/\[[0-9]\] leaf of plates/', $pagination, $matches)) {
				$pages += 0;
				$suffix = $pagination;
				
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
		
		# Add space between the number and the 'v.'; NB No actual cases for v. in the data; avoids dot after 'vols': /records/20704/ (test #348)
		$a = preg_replace ('/([0-9]+)([v]\.)/', '\1 \2', $a);
		
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
	
	
	# Function to get an XPath value, e.g. /records/1000/ (test #698)
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
	
	
	# Function to get a set of XPath values for a field known to have multiple entries, e.g. /records/1003/ (test #699); these are indexed from 1, mirroring the XPath spec, not 0, e.g. /records/1127/ (test #700)
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
			// Note: it is important to complete a full run, and not break the loop at this point if not found, because e.g. //k[6]/ks might exist even if //k[5]/ks is not present; e.g. /records/60776/ (test #697)
		}
		
		# Return the values
		return $values;
	}
	
	
	# Macro to generate the leading article count; this does not actually modify the string itself - just returns a number; e.g. 245 (based on *t) in /records/1116/ (test #355); 245 for Spanish record in /records/19042/ (test #356); 242 field (based on *tt) in /records/1204/ (test #357)
	public function macro_nfCount ($value, $language = false, &$errorHtml_ignored = false, $externalXml = NULL, $confirmedTopLevel = false)
	{
		# Strip any HTML tags, as will be stripped in the final record, e.g. /records/15161/ (test #782)
		$value = strip_tags ($value);
		
		# If the the value is surrounded by square brackets, then it can be taken as English, and the record language itself ignored
		#!# Check on effect of *to or *tc, as per /reports/bracketednfcount/
		if ($isSquareBracketed = ((substr ($value, 0, 1) == '[') && (substr ($value, -1, 1) == ']'))) {
			$language = 'English';	// E.g. /records/14153/
			if (preg_match ('/^\[La /', $value)) {	// All in /reports/bracketednfcount/ were reviewed and found to be English, except /records/9196/ and others below
				$language = 'French';
			}
			if (preg_match ('/^\[Die /', $value)) {	// /records/176560/
				$language = 'German';
			}
		}
		
		# If a forced language is not specified, obtain the language value for the record
		#!# Need to check that first //lang is what is always wanted, i.e. not using *lang data within *in or *j
		if (!$language) {
			$xml = ($externalXml ? $externalXml : $this->xml);	// Use external XML if supplied
			$xPath = '(//lang)[1]';	// Choose first only, e.g. /records/2003/ (test #883) which has two instances of *lang=French within the record
			$language = $this->xPathValue ($xml, $xPath, false);
		}
		
		# Handle parallel titles; e.g. /records/100909/ (test #788)
		if ($confirmedTopLevel) {	// Currently supported for top-level checking only
			if (substr_count ($value, ' = ')) {
				$xPath = '/*/tg/lpt';
				$xml = ($externalXml ? $externalXml : $this->xml);	// Use external XML if supplied
				if ($lpt = $this->xPathValue ($xml, $xPath)) {
					list ($language, $otherLanguage) = explode (' = ', $lpt, 2);
				}
			}
		}
		
		# If no language specified, choose 'English'
		if (!strlen ($language)) {$language = 'English';}
		
		# End if the language is not in the list of leading articles, e.g. /records/211109/ (test #701)
		if (!isSet ($this->leadingArticles[$language])) {return '0';}
		
		# Work through each leading article, and if a match is found, return the string length, e.g. /records/1116/ (test #355); /records/19042/ (test #356)
		# "Diacritical marks or special characters at the beginning of a title field that does not begin with an initial article are not counted as nonfiling characters." - https://www.loc.gov/marc/bibliographic/bd245.html
		# Therefore incorporate starting brackets in the consideration and the count if there is a leading article; see: https://www.loc.gov/marc/bibliographic/bd245.html , e.g. /records/27894/ (test #359), /records/56786/ (test #360), /records/4993/ (test #361)
		# Include known starting/trailing punctuation within the count, e.g. /records/11329/ (test #362) , /records/1325/ (test #363) like example '15$aThe "winter mind"' in MARC documentation , /records/10366/ , as per http://www.library.yale.edu/cataloging/music/filing.htm#ignore
		foreach ($this->leadingArticles[$language] as $leadingArticle) {
			if (preg_match ('/^(' . "['\"\[]*" . $leadingArticle . "['\"]*" . ')/i', $value, $matches)) {	// Case-insensitive match, e.g. /records/1127/ (test #702)
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
		if ($this->authorsFields['default'][100] || $this->authorsFields['default'][110] || $this->authorsFields['default'][111]) {
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
			if (preg_match ('/[Tt]ranslat(?:ion|ed) (?:from|reprint of)(?: original| the original| the|) ([a-zA-Z]+)/i', $note, $matches)) {	// Deliberately not using strip_tags, as that would pick up Translation from <em>publicationname</em> which would not be wanted anyway, e.g. /records/8814/ (test #378)
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
	public function macro_transliterateSubfields ($value, $applyToSubfields, &$errorHtml = NULL, $language = false /* Parameter always supplied by external callers */)
	{
		# If a forced language is not specified, obtain the language value for the record
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($this->xml, $xPath);
		}
		
		# Return unmodified if the language mode is default, e.g. /records/211150/ (test #700)
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
		$transliterationPresent = false;
		foreach ($tokens as $index => $string) {
			
			# Register then skip subfield indicators
			if (preg_match ("/^({$this->doubleDagger}[a-z0-9])$/", $string)) {
				$subfield = $string;
				continue;
			}
			
			# Skip if no subfield, i.e. previous field, assigned; this also catches cases of an opening first/second indicator pair
			if (!$subfield) {continue;}
			
			# Skip conversion if the subfield is not required to be converted
			if (!in_array ($subfield, $applyToSubfields)) {continue;}
			
			# Convert subfield contents, e.g. /records/35733/ (test #381)
			$tokens[$index] = $this->macro_transliterate ($string, $language, $errorHtml, $nonTransliterable);
			
			# Unless the string has been found to be non-transliterable, flag that transliteration is present, to enable full non-transliterable lines to be removed from 880 generation, e.g. "Anonymous" in /records/1571/ (test #791)
			if (!$nonTransliterable) {
				$transliterationPresent = true;
			}
		}
		
		# Return false if no transliteration has taken place, e.g. "Anonymous" in /records/1571/ (test #791); this will take account of $applyToSubfields (i.e. a in `transliterateSubfields(a)`) as those will be skipped, leaving $transliterationPresent as false, e.g. /records/16319/ (test #852)
		if (!$transliterationPresent) {return false;}
		
		# Re-glue the string, e.g. /records/35733/ (test #381)
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
	private function macro_transliterate ($value, $language = false, &$errorHtml_ignored = NULL, &$nonTransliterable = false, $nonTransliterableReturnsFalse = true)
	{
		# If a forced language is not specified, obtain the language value for the record
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($this->xml, $xPath);
		}
		
		# End without output if no language, i.e. if default
		if (!$language) {return false;}		// No known code paths identified, as callers already appear to guard against this, so no tests
		
		# Ensure language is supported; e.g. /records/6692/, but cannot add test as transliteration would pass through the value anyway
		if (!isSet ($this->supportedReverseTransliterationLanguages[$language])) {return false;}	// Return false to ensure no result, unlike the main transliterate() routine
		
		# Pass the value into the transliterator
		/*
			Callers are:
			880-490:transliterateSubfields(a) uses //ts (1240 shards)
			generate260 uses //pg[]/pu[], but 880 generate260(transliterated); e.g. /records/6996/ (test #58)
			MORE TODO
		*/
		#!# Need to determine whether the $lpt argument should ever be looked up, i.e. whether the $value represents a title and the record is in Russian
		$output = $this->transliteration->transliterateLocLatinToCyrillic ($value, $lpt = false, $error /* returned by reference */, $nonTransliterable /* returned by reference */);
		
		# Return false if string is unchanged, e.g. fully in brackets or entirely a protected string, e.g. /records/214774/ (test #840)
		if ($nonTransliterable) {
			if ($nonTransliterableReturnsFalse) {	// i.e. the direct parser calling mode - may be disabled by code callers
				return false;
			}
		}
		
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
		
		# Position 08: Type of control; e.g. /records/1188/ (test #388)
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
			return 'Russian transliteration entered into original records using BGN/PCGN 1947 romanisation of Russian; Cyrillic text in MARC 880 field(s) reverse transliterated from this by automated process; BGN/PCGN 1947 text then upgraded to Library of Congress romanisation.';
		}
		
		# No match; e.g. /records/1527/ (test #422)
		return false;
	}
	
	
	# Macro for generating an authors field, e.g. 100; tests have full coverage as noted in the generateAuthors class
	private function macro_authorsField ($value, $arg)
	{
		# Parse the arguments
		$fieldNumber = $arg;	// Default single argument representing the field number
		$flag = false;			// E.g. 'transliterated'
		if (substr_count ($arg, ',')) {
			list ($fieldNumber, $flag) = explode (',', $arg, 2);
		}
		
		# If running in transliteration mode, require a supported language, e.g. /records/27093/ (test #885)
		$languageMode = 'default';
		if ($flag == 'transliterated') {
			if (!$languageMode = $this->getTransliterationLanguage ($this->xml, $checkNtTokens = true)) {return false;}		// E.g. /records/27094/ (test #886)
		}
		
		# Compile the value, to a multiline if required, e.g. /records/2295/ (test #756), or false for no lines (e.g. /records/178377/ (test #757))
		$string = ($this->authorsFields[$languageMode][$fieldNumber] ? implode ("\n{$fieldNumber} ", $this->authorsFields[$languageMode][$fieldNumber]) : false);
		
		# Return the value, which may be a multiline, e.g. /records/2295/ (test #756), or may be false (meaning no field should be created) (e.g. /records/178377/ (test #757))
		return $string;
	}
	
	
	# Function to determine whether a language is supported, and return it if so, e.g. /records/210651/ (test #165)
	private function getTransliterationLanguage ($xml, $checkNtTokens = false)
	{
		# Determine if *nt={BGNRus|LOCRus}, e.g. /records/102036/ (test #728) which is a Yakut record with *nt=BGNRus sections
		if ($checkNtTokens) {
			if ($this->supportedNtTokensPresent ($xml)) {
				return 'Russian';
			}
		}
		
		# Get the record language, if any, and return it if present and supported (or false)
		$language = $this->xPathValue ($xml, '/*/tg/lang[1]');		// I.e. top-level only, e.g. /records/27093/ (test #885)
		if ($language && isSet ($this->supportedReverseTransliterationLanguages[$language])) {		// # NB This will not mistakenly catch "Byelorussian" as Russian, e.g. /records/96819/ (test #847))
			return $language;	// E.g. /records/210651/ (test #165)
		} else {
			return false;
		}
	}
	
	
	# Helper function to determine presence of *nt={BGNRus|LOCRus} language override tokens, e.g. /records/102036/ (test #728)
	public function supportedNtTokensPresent ($xml)
	{
		# Check for supported *nt tokens present anywhere in the data
		$supportedNtTokens = array ('BGNRus', 'LOCRus');
		$ntTokens = $this->xPathValues ($xml, '(//nt)[%i]', false);
		$supportedNtTokensPresent = array_intersect ($ntTokens, $supportedNtTokens);
		return $supportedNtTokensPresent;
	}
	
	
	# Macro to add in the 880 subfield index
	private function macro_880subfield6 ($value, $masterField)
	{
		# End if no value; e.g. 110 field in /records/151048/ (test #423)
		if (!$value) {return $value;}
		
		# If master field is supplied as e.g. "780,t" this means treat as field and the incoming subfield to be prepended before the value but after the $6; e.g. /records/35280/ (test #826); handled correctly for repeatability, e.g. /records/205613/ (test #827)
		$addSubfield = false;
		if (preg_match ('/^([0-9]+),([a-z0-9])$/', $masterField, $matches)) {
			$masterField = $matches[1];
			$value = $this->doubleDagger . $matches[2] . $value;	// NB not supported for multineline, but no instances of such usage in the parser definition
		}
		
		# Determine the field instance index, starting at 0; this will always be 0 unless called from a repeatable; repeatable fields supported, e.g. internal representation of $this->field880subfield6FieldInstanceIndex[785_1] in /records/205613/ (test #871)
		$this->field880subfield6FieldInstanceIndex[$masterField] = (isSet ($this->field880subfield6FieldInstanceIndex[$masterField]) ? $this->field880subfield6FieldInstanceIndex[$masterField] + 1 : 0);
		
		# For a multiline field, e.g. /records/162152/ (test #424), parse out the field number, which on subsequent lines will not necessarily be the same as the master field; e.g. /records/68500/ (tests #425, #426)
		if (substr_count ($value, "\n")) {
			
			# Normalise first line
			if (!preg_match ('/^([0-9]{3} )/', $value)) {
				$value = $masterField . ' ' . $value;
			}
			
			# Convert to field, indicators, and line
			preg_match_all ('/^([0-9]{3}) (.*)$/m', $value, $lines, PREG_SET_ORDER);	// .* rather than .+ used, as generateOtherEntitiesLines may have resulted in empty line due to *nt=None
			
			# Construct each line; link field may go into double digits, e.g. /records/150141/ (test #427, #428); indicators should match, e.g. /records/150141/ (test #429)
			$values = array ();
			foreach ($lines as $multilineSubfieldIndex => $line) {	// $line[1] will be the actual subfield code (e.g. 710), not the master field (e.g. 700), i.e. it may be a mutated value (e.g. 700 -> 710) as in e.g. /records/68500/ (tests #425, #426) and similar in /records/150141/ , /records/183507/ , /records/196199/
				$value = $this->construct880Subfield6Line ($line[2], $line[1], $masterField, $this->field880subfield6FieldInstanceIndex[$masterField], $multilineSubfieldIndex);
				if (strlen ($value)) {
					$values[] = $value;
				}
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
		# If empty line supplied, return empty string, and avoid advancing the index
		if (!strlen ($line)) {
			return false;
		}
		
		# Advance the index, which is incremented globally across the record; starting from 1
		$this->field880subfield6Index++;
		
		# Assemble the subfield for use in the 880 line
		$indexFormatted = str_pad ($this->field880subfield6Index, 2, '0', STR_PAD_LEFT);	// E.g. /records/150141/ (tests #427, #431)
		$subfield6 = $this->doubleDagger . '6' . $masterField . '-' . $indexFormatted . '/(N';		// Space after $6 not permitted, e.g. /records/150141/ (test #432); Needs /(N ('Script identification code' for Cyrillic) as per: https://www.loc.gov/marc/bibliographic/ecbdcntf.html , e.g. /records/1062/ (tests #759, #760)
		
		# Insert the subfield after the indicators; this is similar to insertSubfieldAfterMarcFieldThenIndicators but without the initial MARC field number; e.g. /records/150141/ (test #429)
		# ‡6880-xx‡.. should not have space after the ‡6 or before the following subfield, e.g. /records/150141/ (test #432) /records/22095/ (test #758)
		if (preg_match ('/^([0-9#]{2}) (.+)$/', $line)) {	// Can't get a single regexp that makes the indicator block optional
			$line = preg_replace ('/^([0-9#]{2}) (.+)$/', "\\1 {$subfield6}\\2", $line);	// I.e. a macro block result line that includes the two indicators at the start (e.g. a 100), e.g. '1# $afoo'
		} else {
			$line = preg_replace ('/^(.+)$/', "{$subfield6}\\1", $line);	// I.e. a macro block result line that does NOT include the two indicators at the start (e.g. a 490), e.g. '$afoo'
		}
		
		# Register the link so that the reciprocal link can be added within the master field; this is registered either as an array (representing parts of a multiline string) or a string (for a standard field)
		$fieldKey = $masterFieldIgnoringMutation . '_' . $fieldInstance;	// e.g. 700_0; this uses the master field, ignoring the mutation, so that $this->field880subfield6ReciprocalLinks is indexed by the master field; this ensures reliable lookup in records such as /records/68500/ where a mutation exists in the middle of a master field (i.e. 700, 700, 710, 700, 700)
		$linkToken = $this->doubleDagger . '6' . '880' . '-' . $indexFormatted;
		if ($multilineSubfieldIndex !== false) {		// i.e. has supplied value
			$this->field880subfield6ReciprocalLinks[$fieldKey][$multilineSubfieldIndex] = $linkToken;
		} else {
			$this->field880subfield6ReciprocalLinks[$fieldKey] = $linkToken;
		}
		
		# Return the line
		return $line;
	}
	
	
	# Macro for 240 (*to) to strip leading articles, as required by AACR2, taking account of (*lto), e.g. /records/6897/ (test #761); NB the leading article count is always 0 under AACR2, e.g. /records/13989/ (test #358)
	private function macro_stripLeadingArticle240 ($to, $ignored)
	{
		# Obtain the *lto language, if any
		$lto = $this->xPathValue ($this->xml, '/*/tg/lto[1]');
		
		# Set the language; this should explicitly *not* fall back on the record language, because *to will generally not match the record language, e.g. /records/6897/ (test #761)
		# *to is assumed to be in English unless an *lto is specified, so an English *to in a Russian record needs *lto=English adding
		$language = ($lto ? $lto : 'English');
		
		# Obtain the non-filing character (leading article) count, e.g. 4 in /records/6897/ (test #761), 0 in /records/1165/ (test #762)
		$nfCount = $this->macro_nfCount ($to, $language);
		
		# Determine if the *to starts with a [ bracket
		$hasBracket = (substr ($to, 0, 1) == '[');
		
		# If there is an nfcount, strip that number of characters from the *to, e.g. 4 in /records/6897/ (test #761), no stripping in /records/1165/ (test #762); upper-case the first character, e.g. /records/13989/ (test #777)
		if ($nfCount) {
			$to = mb_substr ($to, $nfCount);
			$to = mb_ucfirst ($to);		// Supplied in application.php as a polyfill function
			
			# Restore bracket if present; the nfCount will have included this, e.g. '[The ' is 5, so all will be stripped
			if ($hasBracket) {
				$to = '[' . $to;	// No examples, so no test available, but tested using dummy data
			}
		}
		
		# Return the *to, as potentially amended
		return $to;
	}
	
	
	# Macro for generating the 245 field; tests have full coverage as noted in the generate245 class
	private function macro_generate245 ($value, $flag, &$errorHtml)
	{
		# If running in transliteration mode, require a supported language, i.e. is in Russian or *lpt contains Russian
		$languageMode = 'default';
		if ($flag == 'transliterated') {
			$languageMode = $this->languageModeTitle ();
			if ($languageMode == 'default') {return false;}		// E.g. /records/178029/ (test #846)
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
	
	
	# Helper function to determine the language mode based on the record title, i.e. is in Russian or *lpt contains Russian
	private function languageModeTitle ()
	{
		# Return true if language mode is Russian, e.g. /records/210651/ (test #165)
		$language = $this->getTransliterationLanguage ($this->xml);
		if ($language == 'Russian') {return 'Russian';}
		
		# Return true if the *lpt contains Russian, e.g. /records/172050/ (test #845)
		if ($lpt = $this->xPathValue ($this->xml, '/*/tg/lpt')) {
			$lptLanguages = explode (' = ', $lpt);
			if (in_array ('Russian', $lptLanguages)) {return 'Russian';}
		}
		
		# Return false, indicating default language mode, e.g. /records/178029/ (test #846)
		return 'default';
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
		if ($b = $this->generate250b ($value, $this->xml)) {
			$subfields[] = "/{$this->doubleDagger}b" . $b;	# Bibcheck notes that space-slash ( /) is required (as shown in the MARC spec), e.g. /records/3421/ (test #567)
		}
		
		# Return false if no subfields; e.g. /records/1031/ (test #436)
		if (!$subfields) {return false;}
		
		# Compile the overall string; e.g. /records/45901/ (test #437)
		$value = implode (' ', $subfields);
		
		# Ensure the value ends with a dot or punctuation; e.g. /records/4432/ , /records/2549/ (test #438)
		$value = $this->macro_dotEnd ($value, $extendedCharacterList = true);
		
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
		
		# Deal with optional volume number, e.g. /records/31402/ (test #704)
		if (strlen ($volumeNumber)) {
			
			# Strip any trailing ,. character in $a, and re-trim, e.g. /records/20040/ (test #454)
			$string = preg_replace ('/^(.+)[.,]$/', '\1', $string);
			$string = trim ($string);
			
			# Add space-semicolon before $v if not already present, e.g. /records/20040/ (test #454)
			if (mb_substr ($string, -1) != ';') {	// Normalise to end ";"
				$string .= ' ;';
			}
			if (mb_substr ($string, -2) != ' ;') {	// Normalise to end " ;", e.g. /records/31402/ (test #455)
				$string = preg_replace ('/;$/', ' ;', $string);
			}
			
			# Add the volume number; Bibcheck requires: "490: Subfield v must be preceeded by a space-semicolon", e.g. /records/31402/ (test #704)
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
			533 => "^Printout\.(.+)$",			// Actually implemented below, but has to be defined here to avoid it also becoming a standard 500, e.g. /records/142020/ (test #716)
			538 => '^(Mode of access: .+)$',	// 538 - System Details Note; see: https://www.loc.gov/marc/bibliographic/bd538.html ; now no records, so test removed
			561 => '^Provenance: (.+)$',		// 561 - Provenance; see: https://www.loc.gov/marc/bibliographic/bd561.html , e.g. /records/17120/ (test #804, #805)
			-1  => '^SPRI has (.+)$',			// Should be excluded from 500, as will be picked up in macro_generate852, e.g. /records/123440/ (test #815)
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
	private function macro_generate505Note ($note, $transliterate = false)
	{
		# End if the note is not a content note, e.g. other notes in /records/2652/ (test #731)
		if (!preg_match ('/^Contents: (.+)$/', $note, $matches)) {
			return false;
		}
		
		# Use only the extracted section, removing "Contents: " which is assumed to be added by the library catalogue GUI; e.g. /records/1488/ (test #591)
		$note = $matches[1];
		
		# Transliterate if required, e.g. /records/109111/ (test #848), excluding known English, e.g. /records/183257/ (test #851)
		if ($transliterate) {
			$whitelist = array (183257, 197702, 204261, 210284, 212106, 212133, 212246);	// NB If updating, the same list of numbers should also be updated in macro_generate505Note
			if (in_array ($this->recordId, $whitelist)) {return false;}	// E.g. /records/183257/ (test #851)
			$note = $this->macro_transliterate ($note, 'Russian');	// E.g. /records/109111/ (test #848); NB no handling of $nonTransliterable as $whitelist already excludes such records (which would otherwise required massive whitelist strings)
		}
		
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
	
	
	# Macro to create 700 fields from Enhanced mode content notes (see also generate505Note), e.g. /records/4660/ (test #736)
	private function macro_contentNote700 ($note, $parameter_ignored, &$errorHtml)
	{
		# End if the note is not a content note, e.g. other notes in /records/2652/ (test #732, though there are two further catches below, so hard to test properly)
		if (!preg_match ('/^Contents: (.+)$/', $note, $matches)) {
			return false;
		}
		
		# Use only the extracted section, removing "Contents: " which is assumed to be added by the library catalogue GUI, e.g. /records/1488/ (test #733)
		$note = $matches[1];
		
		# End if not enhanced format, e.g. /records/1488/ (test #734)
		if (!$enhancedFormat = substr_count ($note, ' / ')) {return false;}
		
		# Prefix the first block, as it will not have the separator, e.g. /records/4660/ (test #735)
		$note = "{$this->doubleDagger}t" . $note;
		
		# Define replacements
		$replacements = array (
			' / '	=> "{$this->doubleDagger}r",
			' -- '	=> "{$this->doubleDagger}t",
		);
		$note = strtr ($note, $replacements);
		
		# Pass into the subfield parser
		$subfields = $this->parseSubfieldsToPairs ($note);
		
		# Throw error if there is not an identical number of r and t blocks
		if (count ($subfields['r']) != count ($subfields['t'])) {
			$errorHtml .= 'Content note does not have matching number of r and t blocks';
			return false;
		}
		
		# Compile each line, e.g. /records/4660/ (test #736)
		# Note: no attempt has been made to convert author names into "Surname, Forename" from "Forename Surname" / "Initials. Surname" / etc., as this is too complex to do reliably
		$lines = array ();
		for ($i = 0; $i < count ($subfields['r']); $i++) {
			
			# If there are multiple authors (separated by ' and ' and variants in other languages), split and create separate lines, e.g. /records/2652/ (test #737)
			$splitRegexp = '/ (and|og) /';	// E.g. Danish in /records/4660/ (test #739), but not "et" as can be used in "et al", e.g. /records/145748/ (test #740)
			if (preg_match ($splitRegexp, $subfields['r'][$i])) {
				$authors = preg_split ($splitRegexp, $subfields['r'][$i]);
				foreach ($authors as $author) {
					$lines[] = '1# ' . $this->macro_dotEnd ("{$this->doubleDagger}a" . $this->generateAuthors->spaceOutInitials ($author), '?.-)') . $this->macro_dotEnd ("{$this->doubleDagger}t{$subfields['t'][$i]}", true);	// spaceOutInitials applied, e.g. /records/145748/ (test #765); "700: Subfield _t must be preceded by a question mark, full stop, hyphen or closing parenthesis." e.g. /records/212487/ (test #780)
				}
			} else {
				$lines[] = '1# ' . $this->macro_dotEnd ("{$this->doubleDagger}a" . $this->generateAuthors->spaceOutInitials ($subfields['r'][$i]), '?.-)') . $this->macro_dotEnd ("{$this->doubleDagger}t{$subfields['t'][$i]}", true);	// spaceOutInitials applied, e.g. /records/12059/ (test #764); "700: Subfield _t must be preceded by a question mark, full stop, hyphen or closing parenthesis." e.g. /records/212487/ (test #780)
			}
		}
		
		# Compile to multiline, e.g. /records/4660/ (test #738)
		$string = implode ("\n700 ", $lines);
		
		# Return the string
		return $string;
	}
	
	
	# Helper function for 533 - Reproduction Note; see: http://www.loc.gov/marc/bibliographic/bd533.html , e.g. /records/142020/ (test #715)
	private function macro_generate533Note ($note)
	{
		# End if the note is not a reproduction note, e.g. /records/1150/ (test #718)
		if (!preg_match ('/^Printout\.(.+)$/', $note, $matches)) {
			return false;
		}
		
		# Replace | with double-dagger, e.g. /records/142020/ (test #717)
		$string = str_replace ('|', $this->doubleDagger, $note);
		
		# Return the string, e.g. /records/142020/ (test #715)
		return $string;
	}
	
	
	# Macro for generating the 541 field, which looks at *acq groups; it may generate a multiline result, e.g. /records/3959/ (test #456); see: https://www.loc.gov/marc/bibliographic/bd541.html
	/* #!# Original spec has notes which may help deal with problems below:
		If record is of type *ser and has multiple *o fields, separate 541 field required for each
		If record has multiple *date fields, separate 541 field required for each
		If record has multiple *acc/*ref fields, separate 541 field required for each
		If record has multiple *pr fields, separate 541 field required for each
	*/
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
				
				# Compile the line, without any space or other separator, e.g. /records/176629/ (test #890)
				$resultLine = implode ('', $subfields);
				
				# If there is a $c, add semicolon separator between it and the following subfield, e.g. /records/1038/ (test #889); negative case in /records/72738/ (test #892)
				$resultLine = preg_replace ("/({$this->doubleDagger}c)([^{$this->doubleDagger}]+)({$this->doubleDagger})/", '\1\2;\3', $resultLine);
				
				# "Field 541 ends with a period unless another mark of punctuation is present. If the final subfield is subfield $5, the mark of punctuation precedes that subfield.", e.g. /records/168419/ (test #891)
				$resultLine = $this->macro_dotEnd ($resultLine, $extendedCharacterList = true);
				
				# Add the institution to which field applies, i.e. SPRI
				$resultLine .= "{$this->doubleDagger}5" . 'UkCU-P';
				
				# Register the line
				$resultLines[] = $resultLine;
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
	
	
	# Macro for generating 583: Action Note, e.g. /records/171183/ (test #720); see: https://www.loc.gov/marc/bibliographic/bd583.html
	private function macro_generate583 ($value)
	{
		# Start a list of results
		$resultLines = array ();
		
		# Loop through each *acc then each *acc/*con in the record; e.g. multiple *acc in /records/10519/ (test #724); multiple *con in an *acc block in /records/12376/ (test #725)
		$accIndex = 1;
		while ($this->xPathValue ($this->xml, "//acc[$accIndex]")) {
			$conIndex = 1;
			while ($con = $this->xPathValue ($this->xml, "//acc[$accIndex]/con[$conIndex]")) {
				
				# Split the string by space-colon-space; this assumes that /reports/invalidcon/ is clear; e.g. /records/171183/ (test #721)
				$parts = explode (' : ', $con, 3);
				
				# Construct the line; see: https://www.loc.gov/marc/bibliographic/pda-part2.pdf
				$line  = "{$this->doubleDagger}3" . trim ($parts[0]);	// E.g. /records/171183/ (test #721)
				$line .= " {$this->doubleDagger}a" . 'condition reviewed';
				$line .= " {$this->doubleDagger}c" . '20150101';	// Date chosen as reasonable
				$line .= " {$this->doubleDagger}l" . trim ($parts[1]);	// E.g. /records/171183/ (test #721)
				$line .= " {$this->doubleDagger}2" . 'pda';		// E.g. /records/171183/ (test #722)
				$line .= " {$this->doubleDagger}5" . 'UkCU-P';	// E.g. /records/171183/ (test #722)
				if (isSet ($parts[2])) {
					$line .= " {$this->doubleDagger}z" . trim ($parts[2]);	// E.g. /records/4960/ (test #744)
				}
				
				# Register the line
				$resultLines[] = $line;
				
				# Next *con
				$conIndex++;
			}
			
			# Next *acc
			$accIndex++;
		}
		
		# End if no lines (i.e. no *con), e.g. /records/171184/ (test #723)
		if (!$resultLines) {return false;}
		
		# Implode the list, e.g. multiple in /records/12376/ (test #256)
		$result = implode ("\n" . '583 ## ', $resultLines);
		
		# Return the result
		return $result;
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
	
	
	# Macro to look up a *ks (UDC) value, e.g. /records/166245/ (test #475); this may end up with no 650 at all if no *ks groups after status token are skipped, e.g. /records/1041/ (test #793)
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
		if (preg_match ('/^(.+)\[(.+)\]$/', $value, $matches)) {
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
		$string = $value . ' -- ' . $this->udcTranslations[$value] . ($description ? ": {$description}" : false);
		
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
		$string = $value . ' -- ' . $mappings[$value];
		
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
		
		# Undo Muscat escaped asterisks @* ; there is only one example and it is not used, but manual tests confirm it is fine
		$lookupTable = $this->unescapeMuscatAsterisks ($lookupTable);
		
		# Convert to TSV
		$lookupTable = trim ($lookupTable);
		require_once ('csv.php');
		$lookupTableRaw = csv::tsvToArray ($lookupTable, $firstColumnIsId = true);
		
		# Define the fallback value in case that is needed
		if (!isSet ($lookupTableRaw[''])) {
			$lookupTableRaw[''] = $lookupTableRaw[$fallbackKey];	// E.g. *ser with no *freq falls back to "No *freq" in /records/1003/ (test #486)
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
	
	
	# Helper function to unescape Muscat asterisks; there is only one example and it is not used, but manual tests confirm it is fine
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
					$result = $this->macro_dotEnd ($result) . ' ' . $this->volPrefix ($this->pOrPt['citation']);
				}
				
				# Add dot at end, e.g. /records/214872/ (test #595); see also equivalent tests for /art/in
				$result = $this->macro_dotEnd ($result);
			}
			
		# /art/in case
		} else if ($this->recordType == '/art/in') {
			
			# For genuine analytics, use the 245 of the host record, but prefix with "In: " ; e.g. /records/5472/ (test #538)
			if ($this->hostRecord) {
				
				# Obtain the 245 of the host record, e.g. /records/5472/ (test #538)
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
						$result = str_replace ($splitPoint, $possibleDot . ' ' . $this->volPrefix ($this->pOrPt['volumeList']) . $splitPoint, $result);	// Dot is added; this is safe because, in Muscat, the *t does not normally end with a dot, except for specific cases - see /reports/tdot/; "Vol. " / "Vols. " added, e.g. /records/5472/ (test #677)
						
					# Otherwise (i.e. if no split point), simply append; no instances found so no test
					} else {
						$result .= ' ' . $this->volPrefix ($this->pOrPt['volumeList']);
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
					$result = $this->macro_dotEnd ($result) . ' ' . $this->volPrefix ($this->pOrPt['volumeList']);
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
		
		# Strip out any $6880 linking field; e.g. /records/22095/ (test #554)
		$result = preg_replace ("/({$this->doubleDagger}6880-[0-9]{2})({$this->doubleDagger}|$)/", '\2', $result);
		
		# Strip subfield indicators, e.g. /records/22095/ (test #491)
		$result = $this->stripSubfields ($result);
		
		# Assign as $a, e.g. /records/1109/ (test #540)
		$result = "{$this->doubleDagger}a" . $result;
		
		# Return the result
		return $result;
	}
	
	
	# Function to prefix Vol. to the volume list / citation
	private function volPrefix ($volumeListOrCitation)
	{
		# Add a "Vol." / "Vols. " prefix when starting with a number; e.g. /art/j records: /records/214872/ (test #542) and negative test /records/215150/ (test #543); /art/in record: /records/1218/ (test #545)
		$separator = '; ';	// Semicolon is necessary for 500 mode; in 773 mode, semicolon rather than comma is chosen because there could be e.g. '73(1,5)' which would cause 'Vols. ' to appear rather than 'Vol. ', e.g. /records/6100/ (test #675)
		$prefix = (preg_match ('/^[0-9]/', $volumeListOrCitation) ? (substr_count ($volumeListOrCitation, $separator) ? 'Vols. ' : 'Vol. ') : '');	// E.g. /records/1668/ (test #521); multiple in /records/6100/ (test #675, for 773) (test #676, for 500) ; negative case /records/1300/ (test #522)
		return $prefix . $volumeListOrCitation;
	}
	
	
	# Function to provide subfield stripping, e.g. /records/22095/ (test #491)
	public function stripSubfields ($string)
	{
		return preg_replace ("/({$this->doubleDagger}[a-z0-9])/", '', $string);
	}
	
	
	# Function to look up the host record, if any
	private function lookupHostRecord ()
	{
		# Up-front, obtain the host ID (if any) from *kg, used in both 773 and 500, e.g. /records/1129/ (test #493); if more than one, the first is chosen, e.g. /records/1896/ (test #763)
		#!# Need to determine what happens when *k2[2]/kg is present, e.g. /records/1896/
		if (!$hostId = $this->xPathValue ($this->xml, '//k2[1]/kg')) {return NULL;}
		
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
	
	
	# Macro to generate the 773 (Host Item Entry) field; see: https://www.loc.gov/marc/bibliographic/bd773.html ; e.g. /records/1129/ (test #493)
	private function macro_generate773 ($value_ignored, $transliterate = false, &$errorHtml = false, $mode500 = false)
	{
		# Start a result
		$result = '';
		
		# Only relevant if there is a host record (i.e. has a *kg which exists); records will usually be /art/in or /art/j only, but there are some /doc records, e.g. /records/1129/ (test #493), or negative case /records/2075/ (test #494)
		#!# At present this leaves tens of thousands of journal analytics without links (because they don't have explicit *kg fields) - these are pseudo-analytics, i.e. generate everything except the $w, so that the $w could be manually added post-migration, e.g. /records/116085/ - see /reports/artjnokg/ and its postmigrationDescriptions commentary
		if (!$this->hostRecord) {return false;}
		
		# Parse out the host record
		$marc = $this->parseMarcRecord ($this->hostRecord);
		
		# If transliteration substitution is required, look up 880 equivalents and substitute where present, e.g. /records/59148/ (test #841)
		if ($transliterate) {
			$marc = $this->transliterationSubstitution ($marc, $fieldsHavingTransliteration /* returned by reference */, $errorHtml);
			
			# End if transliterated data is not present, e.g. /records/67559/ (test #842)
			$fieldsIn773Implementation = array ('LDR', 001, 100, 110, 111, 245, 260);		// I.e. fields used elsewhere in this function below
			if (!array_intersect ($fieldsIn773Implementation, $fieldsHavingTransliteration)) {
				return false;
			}
		}
		
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
			#!# /records/2073/ gets "FrenchFrench" - it has /art/tg/lang and /art/in/tg/lang both French
			$xPath = '//lang[1]';	// Choose first only
			#!# Surely this should be the language of the host record, not the current record (though they may often be the same anyway), as we are combining subfield values of the host and therefore stripping leading articles from that? - probably need to do an xPath read of the host record for /toplevel/tg/lang
			$language = $this->xPathValue ($this->xml, $xPath);
			if (!$language) {$language = 'English';}
			$subfields[] = $this->combineSubfieldValues ('t', $marc['245'], array ('a', 'b'), ' ', $language);	// Space separator only, as already has : in master 245; e.g. /records/67559/ (test #529), /records/59148/ (test #530). Will automatically have a . (which replaces /) e.g. /records/59148/ (test #531)
		}
		
		# Add 773 ‡d: Copy in the 260 (Place, publisher, and date of publication) from the host record, omitting subfield codes; *art/*in records only; e.g. /records/59148/ (test #667)
		# 773 ‡d does not include 260 $6880 field if present in source, e.g. /records/59148/ (test #668)
		if ($this->recordType == '/art/in') {
			if (isSet ($marc['260'])) {
				
				# Create a local variable for clarity
				$host260Line = $marc['260'][0]['subfields'];	// 260 is always single line (i.e. no multiline support), so can safely use just $marc['260'][0]
				// application::dumpData ($host260Line);
				
				# Extract the 260 $a values, e.g. /records/9066/ (test #854), stripping off the space-colon which each ends with - see $results[$pgIndex] assembly of $a in macro_generate260, e.g. /records/9066/ (test #855)
				$host260aValues = array ();
				foreach ($host260Line['a'] as $host260aValue) {
					$host260aValues[] = preg_replace ('/ :$/', '', $host260aValue);		// E.g. /records/9066/ (test #855)
				}
				$host260a = implode ('; ', $host260aValues);	// E.g. multiple ("Stockholm; Berlin") in /records/9066/ (test #856) ; single ("London") in /records/2614/ (test #857)
				
				# Before extracting the 260 $b values, remove the comma from the last (e.g. /records/9066/ (test #859) which will be present if there is a $c - see {$this->doubleDagger}c handling in macro_generate260; NB $a will always be present so comma extraction always happens from $b
				if (isSet ($host260Line['b'])) {
					if (isSet ($host260Line['c'])) {
						$lastSubfieldBIndex = count ($host260Line['b']) - 1;
						$host260Line['b'][$lastSubfieldBIndex] = preg_replace ('/,$/', '', $host260Line['b'][$lastSubfieldBIndex]);		// E.g. multiple in /records/9066/ (test #859) ; single in /records/2614/ (test #860) does not end up with "John Murray,, 1828"
					}
				}
				
				# Extract the 260 $b values, e.g. /records/2614/ (test #858); intermediate items have space-semicolon, as that is put between $a+$b groups - see `implode (' ;', $results)` in macro_generate260
				# NB Semicolon rather than comma is used, as otherwise hard to disambiguate when commas present in one token, e.g. /records/7008/ would combine "Jacob Dybwad" and "Longmans, Green, and Co." to make "Jacob Dybwad, Longmans, Green, and Co."
				$host260bValues = array ();
				foreach ($host260Line['b'] as $host260bValue) {
					$host260bValues[] = preg_replace ('/ ;$/', '', $host260bValue);		// E.g. /records/9066/ (test #861)
				}
				$host260b = implode ('; ', $host260bValues);	// E.g. multiple in /records/9066/ (test #862); single in /records/2614/ (test #863)
				
				# Compile the value; e.g. /records/103259/ (test #864), separating by space-colon-space; e.g. /records/67559/ (test #532)
				# Confirmed that, if reaching this point, $marc['260'][0]['subfields'] always contains both $a and $b subfields
				$subfieldD = '';
				if (($host260a || $host260b)) {
					$subfieldD = "{$this->doubleDagger}d" . $host260a . ' : ' . $host260b;
				}
				
				# Add date if present; e.g. /records/103259/ (test #865) no date so not added in /records/1331/ (test #866)
				if (isSet ($host260Line['c'][0])) {
					if (($host260a || $host260b)) {
						$subfieldD .= ', ';		// Single comma between $b and $c, e.g. /records/103259/ (test #867)
					}
					$subfieldD .= $host260Line['c'][0];
				}
				
				# Register subfield $d, e.g. /records/9066/ (test #868)
				$subfields[] = $subfieldD;
			}
		}
		
		# Add 773 ‡g: *pt (Related parts) [of child record, i.e. not host record]: analytic volume designation (if present), followed - if *art/*j - by (meaningful) date (if present)
		if (in_array ($this->recordType, array ('/art/in', '/art/j'))) {
			$gComponents = array ();
			
			# When generating a 500, use citation (e.g. /records/1109/ (test #673)), otherwise use volume list (e.g. /records/1668/ (test #674))
			$volumeListOrCitation = ($mode500 ? $this->pOrPt['citation'] : $this->pOrPt['volumeList']);
			if ($volumeListOrCitation) {	// E.g. /records/1668/ creates $g (test #514), but /records/54886/ has no $g (test #515)
				$gComponents[] = $this->volPrefix ($volumeListOrCitation);	// Tests in volPrefix function
			}
			
			# /art/j has date, e.g. /records/4844/ (test #519), /records/54886/ has no $g (test #515) as it is an *art/*in, /records/54657/ (test #682) has citation list and date
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
	private function combineSubfieldValues ($parentSubfield, $field, $onlySubfields = array (), $implodeSubfields = ', ', $stripLeadingArticleLanguage = false)
	{
		# Assign the field values, e.g. /records/2494/ (test #685)
		$fieldValues = array ();
		foreach ($field[0]['subfields'] as $subfield => $subfieldValues) {	// Only [0] used, as it is known that all fields using this function are non-repeatable fields
			
			# Skip if required, e.g. /records/1222/ (test #684) uses only $c of source 245
			if ($onlySubfields && !in_array ($subfield, $onlySubfields)) {continue;}
			
			# Add the field value(s) for this subfield, e.g. /records/2494/ (test #685); combine multiple values within the subfield itself (i.e. repeatable subfields - (R)) by comma-space (no examples now available, but confirmed working previously)
			$fieldValues[] = implode (', ', $subfieldValues);
		}
		
		# Fix up punctuation (tests in each clause)
		$totalFieldValues = count ($fieldValues);
		foreach ($fieldValues as $index => $fieldValue) {
			
			# Avoid double commas after joining; e.g. /records/2614/ (test #687)
			if (($index + 1) != $totalFieldValues) {	// Do not consider last in loop; no test for last in loop as no examples exist
				if (mb_substr ($fieldValue, -1) == ',') {
					$fieldValue = mb_substr ($fieldValue, 0, -1);
				}
			}
			
			# Avoid ending a field with " /", e.g. /records/2616/ (test #688)
			if (mb_substr ($fieldValue, -1) == '/') {
				$fieldValue = trim (mb_substr ($fieldValue, 0, -1)) . '.';
			}
			
			# Register the amended value
			$fieldValues[$index] = $fieldValue;
		}
		
		#!# Need to handle cases like /records/191969/ having a field value ending with :
		
		# Compile the value, combining with the specified string, e.g. /records/2614/ (test #689)
		$value = implode ($implodeSubfields, $fieldValues);
		
		# Strip leading article if required; e.g. English: /records/3075/ (test #690), /records/3324/ , German: /records/5472/ (test #691), French but no stripping required: /records/5476/ (test #692)
		if ($stripLeadingArticleLanguage) {
			$value = $this->stripLeadingArticle ($value, $stripLeadingArticleLanguage);
		}
		
		# Compile the result, prepending with the parent subfield, e.g. /records/5487/ (test #693)
		$result = "{$this->doubleDagger}{$parentSubfield}" . $value;
		
		# Return the result
		return $result;
	}
	
	
	# Function to strip a leading article; e.g. English: /records/3075/ (test #690), German: /records/5472/ (test #691)
	private function stripLeadingArticle ($string, $language)
	{
		# End if language not supported, e.g. /records/15131/ - though can't really test as this is a negative
		if (!isSet ($this->leadingArticles[$language])) {return $string;}
		
		# Strip from start if present
		$list = implode ('|', $this->leadingArticles[$language]);
		$string = preg_replace ("/^({$list})(.+)$/i", '\2', $string);	// E.g. English: /records/3075/ (test #690), German: /records/5472/ (test #691)
		$string = mb_ucfirst ($string);	// E.g. /records/3075/ (test #694)
		
		# Return the amended string
		return $string;
	}
	
	
	# Macro to parse out a MARC record into subfields, e.g. /records/5472/ (test #695) and many tests make use of the processed subfields
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
		
		# Return the record
		return $record;
	}
	
	
	# Function to parse subfields into key-value pairs, e.g. /records/5472/ (test #695) and many tests make use of the processed subfields
	public function parseSubfieldsToPairs ($line, $knownSingular = false)
	{
		# Tokenise, e.g. /records/35733/ (test #384)
		$tokens = $this->tokeniseToSubfields ($line);
		
		# Convert to key-value pairs
		$subfields = array ();
		$subfield = false;
		foreach ($tokens as $index => $string) {
			
			# Register then skip subfield indicators
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
	
	
	# Function to look up 880 equivalents and substitute where present, e.g. /records/59148/ (test #841)
	private function transliterationSubstitution ($record, &$fieldsHavingTransliteration, &$errorHtml)
	{
		# Start a list of fields containing transliteration data, to be returned by reference
		$fieldsHavingTransliteration = array ();
		
		# Loop through each field (except 880) then each line
		foreach ($record as $fieldNumber => $field) {
			if ($fieldNumber == 880) {continue;}	// 880 itself is never a source
			foreach ($field as $lineIndex => $line) {
				
				# Skip if no subfield 6
				if (!isSet ($line['subfields'][6])) {continue;}
				
				# Register presence of this field
				$fieldsHavingTransliteration[] = $fieldNumber;
				
				# Extract the token number used for matching, e.g. `245 $6880-01 $a...` will get 01
				$tokenIndex = str_replace ('880-', '', $line['subfields'][6][0]);
				$token = $fieldNumber . '-' . $tokenIndex . '/(N';
				
				# Find the 880 entry for this token
				$line880Matched = array ();
				foreach ($record[880] as $line880Index => $line880) {
					if ($line880['subfields'][6][0] == $token) {
						$line880Matched = $line880;
						unset ($line880Matched['subfields'][6][0]);	// Remove the token subfield itself
						break;
					}
				}
				
				# Report cases of mismatched lines, which should not happen
				if (!$line880Matched) {
					$errorHtml .= "No matching 880 line found for line {$lineIndex} in transliteration substitution.";
					continue;
				}
				
				# Transplant the matched line structure in, replacing the original with the contents of its cyrillic-orientated equivalent
				$record[$fieldNumber][$lineIndex] = $line880Matched;
			}
		}
		
		# Return the amended record, retaining the 880 field so that it can be used to detect the presence of transliteration data
		return $record;
	}
	
	
	# Macro to lookup periodical locations, which may generate a multiline result, e.g. /records/1102/ (test #621); see: https://www.loc.gov/marc/bibliographic/bd852.html
	# Note that the algorithm here is a simplified replacement of doc/852 locations flowchart.xlsx (which was created before work to clean up 'Not in SPRI' records)
	# No spaces are added between subfields, which $b and $c (library and location) are sensitive to for matching
	private function macro_generate852 ($value_ignored)
	{
		# Determine any "SPRI has "... notes, which will be added at the end
		$notes = $this->spriHasNotes852 ();
		
		# Get the locations (if any), e.g. single location in /records/1102/ (test #621), multiple locations in /records/3959/ (test #622)
		$locations = $this->xPathValues ($this->xml, '//loc[%i]/location');
		
		# If no locations, allocate $cSPRIACQ and end at this point, e.g. /records/1331/ (test #648) - this is the normal scenario for *status = RECEIVED, ON ORDER, etc.
		if (!$locations) {
			$result  = "{$this->doubleDagger}2camdept";
			$result .= "{$this->doubleDagger}b" . 'SCO';
			$result .= "{$this->doubleDagger}c" . 'SPRIACQ';	// NB No hyphen
			$result .= $notes;	// If any
			return $result;
		}
		
		# For the special case of "Not in SPRI" being present (in any *location), then create a single 852 value, with the other location(s) noted if any, e.g. /records/7976/ (test #649); /reports/notinspriinspri/ confirms there are now no cases of items "Not in SPRI" also having a SPRI location
		if (in_array ('Not in SPRI', $locations)) {	// NB Manually validated in the database that this is always present as the full string, not a match
			$otherLocations = array_diff ($locations, array ('Not in SPRI'));	// I.e. unset the entry containing this value
			$result  = "{$this->doubleDagger}2camdept";
			$result .= "{$this->doubleDagger}bSPRI-NIS";
			if ($otherLocations) {		// $x notes other location(s) if any; e.g. /records/7976/ (test #649); none in e.g. /records/1302/ (test #650)
				$result .= "{$this->doubleDagger}x" . implode ("{$this->doubleDagger}x", $otherLocations);		// Multiple in e.g. /records/31021/ (test #651)
			}
			$result .= "{$this->doubleDagger}zNot in SPRI";
			$result .= $notes;	// If any
			return $result;
		}
		
		# If the location is '??', treat it as 'UNASSIGNED', e.g. /records/34671/ (test #745); see also post-migration report at /reports/locationunassigned/
		$locationCodes = $this->locationCodes;	// Make a local copy, in case ?? => UNASSIGNED needs to be added
		$locationCodes['\?\?'] = 'UNASSIGNED';
		
		# Report any that do not have a matching location; NB /reports/locationauthoritycontrol/ ensures authority control in terms of always having a space after or end-of-string
		foreach ($locations as $location) {
			if (!preg_match ('@^(' . implode ('|', array_keys ($locationCodes)) . ')@', $location)) {
				$this->errorHtml .= 'The record contains an invalid *location value: ' . htmlspecialchars ($location);
				return false;
			}
		}
		
		# Loop through each location to create a result line
		$resultLines = array ();
		foreach ($locations as $index => $location) {
			
			# Split the value out into values for ‡c (location code) and ‡h (classification, which may or may not exist)
			$locationCode = false;
			$locationName = false;
			$classification = false;
			foreach ($locationCodes as $startsWith => $code) {
				if (preg_match ("|^({$startsWith})(.*)|", $location, $matches)) {
					$locationCode = $code;
					$locationName = $matches[1];	// I.e. non-regexp version of locationCode, e.g. "Electronic Resource (online)"
					$classification = trim ($matches[2]);		# "Cupboard 223" would have "223"; this is doing: "Remove the portion of *location that maps to a Voyager location code (i.e. the portion that appears in the location codes list) - the remainder will be referred to as *location_trimmed"
					break;
				}
			}
			
			# Start the record with 852 7# ‡2camdept (which is the source indicator), without space before, e.g. /records/3959/ (test #623)
			$result  = "{$this->doubleDagger}2camdept";
			
			# Add institution as $b, e.g. /records/31500/ (test #743)
			$result .= "{$this->doubleDagger}b" . 'SCO';
			
			# Add corresponding Voyager location code to record: ‡c SPRI-XXX, e.g. /records/31500/ (test #654)
			$result .= "{$this->doubleDagger}c" . $locationCode;
			
			# In the case of Shelved with ..., add clear description for use in $c, and do not use a classification, e.g. /records/1032/ (test #625)
			if ($isShelvedWith = preg_match ('/^Shelved with (pamphlets|monographs)$/', $location, $matches)) {
				$result .= "{$this->doubleDagger}c" . 'Issues shelved individually with ' . $matches[1];
			}
			
			# Online items get $h (and does not get $c disambiguation check); now no records, so test removed
			if ($locationName == 'Electronic Resource (online)') {
				$result .= "{$this->doubleDagger}h" . $locationName;
			} else {
				
				# In the case of location codes where there is a many-to-one relationship (e.g. "Library Office" and "Librarian's Office" both map to SPRI-LIO), except for SPRI-SER, then add the original location verbatim, so that it can be disambiguated; e.g. /records/2023/ (test #656); negative case (i.e. non-ambigous) in /records/1711/ (test #658)
				if (!in_array ($locationCode, array ('SPRI-SER', 'SPRI-SHF', 'SPRI-PAM'))) {	// E.g. /records/211109/ (test #657)
					$locationCodeCounts = array_count_values ($locationCodes);
					if ($locationCodeCounts[$locationCode] > 1) {
						$result .= "{$this->doubleDagger}c" . $locationName;	// E.g. SPRI-LIO in /records/2023/ (test #656)
					}
				}
			}
			
			# Does *location_original start with a number? This is to deal with cases like "141 C", in which the creation of "SPRI-SER" in the MARC record is implicit
			if (!$isShelvedWith) {		// "Shelved with ..." items do not get $h, e.g. /records/1032/ (test #653)
				
				# If starts with a number (rather than e.g. Shelf / Pam / etc.), it is shelved with periodicals, e.g. /records/20534/ (test #748); example with location split across parts of the library at /records/19822/ (test #775); Basement example at /records/165908/ (test #771) and its child /records/180007/ (test #772); Russian example at /records/33585/ (test #773) and its child /records/137033/ (test #774)
				if (preg_match ('/^([0-9]|Basement|Russian)/', $location)) {
					
					# For real serial analytics, provide human-readable text to look up; otherwise (i.e. /ser) put the real value
					if ($this->recordType == '/art/j' && $this->hostRecord) {	// E.g. /records/20557/ (test #749)
						
						# Add to record a helpful string ‡z, rather than ‡h with a hard-coded location (which would then become problematic to maintain)
						$result .= "{$this->doubleDagger}z" . 'See related holdings for SPRI location';	// E.g. /records/20557/ (test #750); Basement example at /records/180007/ (test #772); Russian example at /records/137033/ (test #774)
					} else {
						
						# Add to record: ‡h <*location_original> (i.e. the full string), e.g. /records/20534/ gets "‡h82 A-B"; Basement example at /records/165908/ (test #771); Russian example at /records/33585/ (test #773)
						$result .= "{$this->doubleDagger}h" . $location;
					}
					
				# E.g. Shelf, e.g. /records/100567/ (test #766)
				} else {
					
					# "Is *location_trimmed empty?; If no, add location to record, e.g. /records/100567/ (test #767), empty example at: /records/31500/ (test #647)
					if (strlen ($classification)) {
						
						# For analytics from a monograph (book), provide human-readable text to look up, e.g. /records/100568/ (test #768); otherwise put the real value, e.g. /records/100567/ (test #769)
						if ($this->recordType == '/art/in' && $this->hostRecord) {
							
							# Add to record a helpful string ‡z, rather than ‡h with a hard-coded location (which would then become problematic to maintain), e.g. /records/100568/ (test #768)
							$result .= "{$this->doubleDagger}z" . 'See related holdings for SPRI location';
						} else {
							
							# Being a book or standalone pamphlet, use ‡h <*location_trimmed>"; e.g. /records/100567/ (test #769) (which has "‡h(*7) : 551.7" which comes from "Shelf (*7) : 551.7")
							$result .= "{$this->doubleDagger}h" . $classification;
						}
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
					$result .= "{$this->doubleDagger}z" . 'Item(s) missing';
					break;
				}
			}
			
			# Add any notes, e.g. /records/1288/ (test #817); will be added to each line, as cannot disambiguated, e.g. /records/7455/ (test #820)
			$result .= $notes;	// If any
			
			# Add the item record creation status, as a non-standard field $9 which will be stripped upon final import
			if ($itemRecords = $this->itemRecordsCreation ($location)) {
				$result .= "{$this->doubleDagger}9" . "Create {$itemRecords} item record" . ($itemRecords > 1 ? 's' : '');
			}
			
			# Register this result
			$resultLines[] = trim ($result);
		}
		
		# Implode the list as a multiline if multiple, e.g. /records/3959/ (test #622)
		$result = implode ("\n" . '852 7# ', $resultLines);
		
		# Return the result
		return $result;
	}
	
	
	# Helper function to find and assemble "SPRI has "... notes; see: /report/multiplecopiesvalues/ ; e.g. /records/123440/ (test #815), /records/1288/ (test #817), /records/122355/ (test #819)
	private function spriHasNotes852 ()
	{
		# Define the note types; $z is public note, $x is non-public note; see: https://www.loc.gov/marc/bibliographic/bd852.html
		# NB: Re *local: SM fields spreadsheet and spri_errors_in_file spreadsheet both clearly show that *local should be public, and 876 has always had local as $z (public note), but tables/muscatSchema.xml defines *local as "additional note, not for publication" - though *priv exists which is explicit
		$notes = '';
		$noteTypes = array (
			'note'  => 'z',		// E.g. /records/123440/ (test #815)
			'local' => 'z',		// E.g. /records/1288/ (test #817)
			'priv'  => 'x',		// E.g. /records/122355/ (test #819)
		);
		
		# Loop through each and obtain the notes
		foreach ($noteTypes as $muscatField => $subfield) {
			if ($noteValues = $this->xPathValues ($this->xml, "//{$muscatField}[%i]")) {	// E.g. //note[2] in /records/123440/
				foreach ($noteValues as $note) {
					
					# Add $x/$z if found
					if (preg_match ('/^SPRI has /', $note)) {
						$notes .= "{$this->doubleDagger}{$subfield}" . $note;
					}
				}
			}
		}
		
		# Return the assembled string
		return $notes;
	}
	
	
	# Function to determine the item record creation status, for use as a private note in 852
	private function itemRecordsCreation ($location)
	{
		# No records for items with a SPRI-ELE location, as they do not physically exist, all of which have been confirmed to have that as the only location, e.g. /records/213625/ (test #924)
		if (substr_count ($location, 'Digital Repository') || substr_count ($location, 'Electronic Resource (online)')) {return false;}
		
		# Assume a count of 1 where a count is returned but the figure is not overriden by more detailed count algorithm below, e.g. /records/1008/ (test #933)
		$count = 1;
		
		# *doc records
		if ($this->recordType == '/doc') {
			
			# Check for "N vols." in the *v, e.g. /records/13420/ (test #920)
			if ($v = $this->xPathValue ($this->xml, '/doc/v')) {
				
				# Normalise cases of "v." to "vols." prior to test, e.g. "12 v." in /records/197517/ (test #923) , "2 v. in 1" in /records/58001/
				$v = preg_replace ('/^([1-9][0-9]*) v\.(| in 1)$/', '\1 vols.\2', $v);
				
				# Test, for "N vols." pattern and variants - tests as noted, e.g. /records/13420/ (test #920), #921 (test #921), #922 (test #922)
				if (preg_match ('/([1-9][0-9]*|three) vols/', $v, $mainMatches)) {
					
					# Interpret variants; see all using: `SELECT value, GROUP_CONCAT(recordId) FROM catalogue_processed WHERE field = 'v' AND `value` LIKE '%vols%' GROUP BY value ORDER BY value;`
					switch (true) {
						case preg_match ('/^([1-9][0-9]*) vols\.?$/', $v, $matches):		$count = $matches[1]; break;	// E.g. /records/13394/ (without dot), /records/2116/ (with dot)
						case preg_match ('/(vols|vols\.|bound) (in|.in|as) ([1-9][0-9]*)/', $v, $matches):	$count = $matches[3]; break;	// E.g. /records/18824/ (in) (test #921), /records/11680/ (as), /records/5346/ , /records/3495/
						case preg_match ('/(in|.in|as) one/', $v, $matches):	$count = 1; break;	// E.g. /records/18824/ (in), /records/11680/ (as), /records/5346/ , /records/3495/
						case $v == '18-24 vols.':	$count = 7; break;	// E.g. /records/2345/
						case preg_match ('/([1-9][0-9]*) vols\.? (and|\+) atlas/', $v, $matches):	$count = ($matches[1] + 1); break;	// E.g. /records/2988/ (test #922)
						case preg_match ('/([1-9][0-9]*) vols. plus ([1-9][0-9]*) index vols./', $v, $matches):	$count = ($matches[1] + $matches[2]); break;	// E.g. /records/173662/
						case preg_match ('/([1-9][0-9]*) parts/', $v, $matches):	$count = $matches[1]; break;	// E.g. /records/168916/
						case preg_match ('/in (slipcase|box|slip case)/', $v, $matches):	$count = 1; break;	// E.g. /records/53204/ , /records/2557/
						case preg_match ('/bound together/', $v, $matches):	$count = ($mainMatches[1] - 1); break;	// E.g. /records/5961/ , /records/2070/
						case $v == '5 vols. (+ 3 vols. index)':	$count = 8; break;	// E.g. /records/4054/
						case $v == '51 vols. plus 2 index vols.':	$count = 53; break;	// E.g. /records/173662/
						case $v == '62 vols. and index':	$count = 63; break;	// E.g. /records/72167/
						case $v == 'Three vols.':	$count = 3; break;	// E.g. /records/11548/
						case $v == 'Three vols.':	$count = 3; break;	// E.g. /records/11548/
						default:	$count = $mainMatches[1];	// Default to X vols as per main match, e.g. /records/2268/ , /records/13420/ (test #920)
					}
				}
			}
			
			# Return the count, e.g. /records/13420/ (test #920)
			return $count;
		}
		
		# *ser records, e.g. /records/1000/ (test #925)
		if ($this->recordType == '/ser') {
			
			# Count the total number of tokens in all *hold, e.g. single *hold in /records/1029/ (test #926), multiple *hold in /records/3339/ (test #927)
			if ($holdValues = $this->xPathValues ($this->xml, '//hold[%i]')) {
				$count = 0;
				foreach ($holdValues as $holdValue) {
					$count += count (explode (';', $holdValue));
				}
			}
			
			# Return the count, e.g. /records/1000/ (test #925)
			return $count;
		}
		
		# *art records: determine based on a set of criteria which have been created following database querying
		# E.g. 'Pam' in /records/1107/ (test #928), 'Special Collection' in /records/1590/ (test #929), '??' in /records/2579/ (test #931)
		if (($this->recordType == '/art/in') || ($this->recordType == '/art/j')) {
			
			# If there is a host record, no item record created, e.g. /records/1109/ (test #928)
			if ($this->hostRecord) {return false;}
			
			# Whitelist of locations, except where 'bound in'
			/* Equivalent SQL query gives 23,615 opt-ins with:
				SELECT
					catalogue_processed.id,recordId,value,xPath,title
				FROM catalogue_processed
				JOIN fieldsindex ON fieldsindex.id = catalogue_processed.id AND location NOT LIKE '%Not in SPRI%'
				WHERE
						field = 'location'
					AND xPath LIKE '/art%'
					AND fieldslist NOT LIKE '%@kg@%'		-- *kg indicates an actual parent
					AND value NOT REGEXP '(^Basement Seligman|Bound in|^Periodical$)'
					AND (
						   value REGEXP "^(Atlas|Archives|Basement BB Roberts Cabinet|Bibliographers' Office|Folio|Librarian's Office|Map Room|Pam|Pam |Picture Library Store|Reference|Russian REZ.IS|Shelf|Shelved with monographs|Special Collection|Theses)"
						OR value = '??'
					)
				;
			*/
			if (preg_match ("/^(Archives|Atlas|Basement BB Roberts Cabinet|Bibliographers' Office|Folio|Librarian's Office|Map Room|Pam|Pam |Picture Library Store|Reference|Russian REZ.IS|Shelf|Shelved with monographs|Special Collection|Theses)/", $location) || ($location == '??')) {
				#!# /records/1189/ has a note "Bound with"
				if (!substr_count ($location, 'Bound in')) {		// E.g. /records/1350/ (test #930); NB 'Not in SPRI' will already have stopped execution in the calling code so is not listed here but is needed in the equivalent SQL above
					return $count = 1;
				}
			}
		}
		
		# No scenario matched, so no item record creation, e.g. /records/3979/ (test #932)
		return false;
	}
	
	
	# Macro to generate a list of URLs for use in 530/856, e.g. 856 in /records/213625/ (test #913), 530 in /records/6765/ (test #919)
	private function macro_generateUrlsList ($enabled)
	{
		# End if not enabled, i.e. if previous guard macro returned false; e.g. /records/213625/ (test #918)
		if (!$enabled) {return false;}
		
		# Start a list of entries, each of which will get $u
		$u = array ();
		
		# Add any DOIs, which get both a "$uurn:doi:" -prefixed version and a URL equivalent, e.g. /records/188509/ (test #498), /records/2175/ (test #499)
		if ($dois = $this->xPathValues ($this->xml, '//doi[%i]/doifld')) {
			foreach ($dois as $doi) {
				$u[] = 'urn:doi:' . $doi;	// E.g. /records/188509/ (test #498)
				$u[] = 'https://dx.doi.org/' . $doi;	// E.g. /records/2175/ (test #499)
			}
		}
		
		# Add any plain URLs, dealing with IDN conversion where necessary, e.g. /records/213625/ (test #913), multiple in /records/197739/ (test #915)
		if ($urls = $this->xPathValues ($this->xml, '//url[%i]/urlgen')) {
			foreach ($urls as $url) {
				$u[] = $this->idnConversion ($url);	// IDN conversion where necessary, e.g. /records/197739/ (test #584)
			}
		}
		
		# If no $u values, return false, e.g. /records/197740/ (test #916)
		if (!$u) {return false;}
		
		# Compile $u values to string, which will the MARC parser with then prepend the first $u to; no space between multiple $u subfields, e.g. /records/6765/ (test #910)
		$result = implode ("{$this->doubleDagger}u", $u);
		
		# Return the value
		return $result;
	}
	
	
	# Helper function to convert an internationalised domain name to Unicode; see: https://en.wikipedia.org/wiki/Internationalized_domain_name#Top-level_domain_implementation
	private function idnConversion ($url)
	{
		# Convert if required, e.g. /records/197739/ (test #584)
		$hostname = parse_url ($url, PHP_URL_HOST);
		if (preg_match ('/^xn--/', $hostname)) {
			$utf8DomainName = idn_to_utf8 ($hostname, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);	// options and variant set to avoid warnings in PHP 7.2/7.3; see: https://bugs.php.net/75609
			$url = str_replace ($hostname, $utf8DomainName, $url);	// Substitute the domain within the overall URL
		}
		
		# Return the possibly-modified URL
		return $url;
	}
	
	
	# Macro to generate 916, which is based on *acc/*ref *acc/*date pairs, and *acc/*recr, e.g. /records/1424/ (test #794)
	private function macro_generate916 ($value)
	{
		# Define the supported *acc/... fields that can be included
		$supportedFields = array ('ref', 'date', 'recr');
		
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
		
		# Compile the components, separated by semicolon, e.g. /records/3776/ (test #586)
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
		foreach ($suppressReasons as $token) {
			if (isSet ($this->suppressionScenarios[$token])) {
				$resultLines[] = 'Suppression reason: ' . $token . ' (' . $this->suppressionScenarios[$token][0] . ')';
			}
			if (isSet ($this->ignorationScenarios[$token])) {
				$resultLines[] = 'Ignoration reason: ' . $token . ' (' . $this->ignorationScenarios[$token][0] . ')';
			}
		}
		
		# Implode the list, e.g. /records/1122/ (tests #613; no test for multiple, as no data, but verified manually that this works)
		$result = implode (" {$this->doubleDagger}a", $resultLines);
		
		# Return the result line, e.g. /records/1026/ (test #611)
		return $result;
	}
	
	
	# Macro to determine cataloguing status; this uses values from both *ks OR *status, but the coexistingksstatus report is marked clean, ensuring that no data is lost
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
			'die ' => 'Afrikaans German',
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
	
	
	# Lookup table for diacritics
	private function diacriticsTable ()
	{
		# Diacritics; see also report_diacritics() and report_diacritics_view(); most are defined at http://www.ssec.wisc.edu/~tomw/java/unicode.html and this is useful: http://illegalargumentexception.blogspot.co.uk/2009/09/java-character-inspector-application.html
		$diacritics = array (
			
			// ^a acute
			'a^a' => chr(0xc3).chr(0xa1),			//  0x00E1
			'c^a' => chr(0xc4).chr(0x87),			//  0x0107
			'e^a' => chr(0xc3).chr(0xa9),			//  0x00E9, e.g. /records/148511/ (test #711)
			'g^a' => chr(0xc7).chr(0xb5),			//  0x01F5
			'i^a' => chr(0xc3).chr(0xad),			//  0x00ED
			'n^a' => chr(0xc5).chr(0x84),			//  0x0144
			'o^a' => chr(0xc3).chr(0xb3),			//  0x00F3
			'r^a' => chr(0xc5).chr(0x95),			//  0x0155
			's^a' => chr(0xc5).chr(0x9b),			//  0x015B
			'u^a' => chr(0xc3).chr(0xba),			//  0x00FA
			'y^a' => chr(0xc3).chr(0xbd),			//  0x00FD
			'z^a' => chr(0xc5).chr(0xba),			//  0x017A
			'A^a' => chr(0xc3).chr(0x81),			//  0x00C1
			'C^a' => chr(0xc4).chr(0x86),			//  0x0106
			'E^a' => chr(0xc3).chr(0x89),			//  0x00C9
			'I^a' => chr(0xc3).chr(0x8d),			//  0x00CD
			'O^a' => chr(0xc3).chr(0x93),			//  0x00D3
			'S^a' => chr(0xc5).chr(0x9a),			//  0x015A
			'U^a' => chr(0xc3).chr(0x9a),			//  0x00DA
			'Z^a' => chr(0xc5).chr(0xb9),			//  0x0179
			
			// ^g grave
			'a^g' => chr(0xc3).chr(0xa0),			//  0x00E0
			'e^g' => chr(0xc3).chr(0xa8),			//  0x00E8
			'i^g' => chr(0xc3).chr(0xac),			//  0x00EC
			'o^g' => chr(0xc3).chr(0xb2),			//  0x00F2
			'u^g' => chr(0xc3).chr(0xb9),			//  0x00F9
			'A^g' => chr(0xc3).chr(0x80),			//  0x00C0
			'E^g' => chr(0xc3).chr(0x88),			//  0x00C8
			
			// ^c for Polish/Lithuanian Ogonek; see: http://www.twardoch.com/download/polishhowto/ogonek.html ]
			'a^c' => chr(0xc4).chr(0x85),			//  0x0105,	// http://www.fileformat.info/info/unicode/char/0105/index.htm ; see also http://scriptsource.org/cms/scripts/page.php?item_id=character_detail&key=SEQ0061_0327
			'i^c' => chr(0xc4).chr(0xaf),			//  0x012f,	// http://www.fileformat.info/info/unicode/char/12f/index.htm
			
			// ^c for Dene but treated as Ogonek
			'u^c' => chr(0xc5).chr(0xb3),			//  0x0173,	// http://www.fileformat.info/info/unicode/char/0173/index.htm ; see also http://scriptsource.org/cms/scripts/page.php?item_id=character_detail&key=SEQ0075_0327
			
			// ^c cedilla
			'c^c' => chr(0xc3).chr(0xa7),			//  0x00E7
			'e^c' => chr(0xc8).chr(0xa9),			//  0x0229
			'k^c' => chr(0xc4).chr(0xb7),			//  0x0137
			'l^c' => chr(0xc4).chr(0xbc),			//  0x013C
			's^c' => chr(0xc5).chr(0x9f),			//  0x015F
			't^c' => chr(0xc5).chr(0xa3),			//  0x0163
			'C^c' => chr(0xc3).chr(0x87),			//  0x00C7
			
			// ^u umlaut
			'a^u' => chr(0xc3).chr(0xa4),			//  0x00E4
			'e^u' => chr(0xc3).chr(0xab),			//  0x00EB
			'h^u' => chr(0xE1).chr(0xb8).chr(0xa7),	//  0x1E27
			'i^u' => chr(0xc3).chr(0xaf),			//  0x00EF
			'o^u' => chr(0xc3).chr(0xb6),			//  0x00F6
			'u^u' => chr(0xc3).chr(0xbc),			//  0x00FC
			'y^u' => chr(0xc3).chr(0xbf),			//  0x00FF
			'A^u' => chr(0xc3).chr(0x84),			//  0x00C4
			'O^u' => chr(0xc3).chr(0x96),			//  0x00D6
			'U^u' => chr(0xc3).chr(0x9c),			//  0x00DC
			
			// ^m macron (i.e. horizontal line over letter/number)
			'a^m' => chr(0xc4).chr(0x81),			//  0x0101
			'e^m' => chr(0xc4).chr(0x93),			//  0x0113
			'i^m' => chr(0xc4).chr(0xab),			//  0x012B
			'o^m' => chr(0xc5).chr(0x8d),			//  0x014D
			'u^m' => chr(0xc5).chr(0xab),			//  0x016B
			'y^m' => chr(0xc8).chr(0xb3),			//  0x0233
			'A^m' => chr(0xc4).chr(0x80),			//  0x0100
			'O^m' => chr(0xc5).chr(0x8c),			//  0x014C
			'U^m' => chr(0xc5).chr(0xaa),			//  0x016A
			'2^m' => '2' . chr(0xCC).chr(0x85),		//  0x0305; records 119571 and 125394, which have e.g. 112^m1 which should be 1121 where there is a line over the 2; see http://en.wikipedia.org/wiki/Overline#Unicode for Unicode handling
			
			// Standalone overline character used in a formula; only appears in record 149163
			'V^m' => 'V' . chr(0xe2).chr(0x80).chr(0xbe),		//	0x203E, // http://www.fileformat.info/info/unicode/char/203e/index.htm
			
			// ^z '/' (stroke) through letter
			'a^z' => chr(0xe2).chr(0xb1).chr(0xa5),	//  0x2C65,	// http://www.fileformat.info/info/unicode/char/2c65/index.htm
			'd^z' => chr(0xc4).chr(0x91),			//  0x0111,	// http://www.fileformat.info/info/unicode/char/0111/index.htm which does indeed have the stroke not across the middle
			'j^z' => chr(0xc9).chr(0x89),			//  0x0249,	// http://www.fileformat.info/info/unicode/char/0249/index.htm
			'l^z' => chr(0xc5).chr(0x82),			//  0x0142
			'o^z' => chr(0xc3).chr(0xb8),			//  0x00F8
			'L^z' => chr(0xc5).chr(0x81),			//  0x0141
			'O^z' => chr(0xc3).chr(0x98),			//  0x00D8
			
			// ^h for circumflex ('h' stands for 'hat')
			'a^h' => chr(0xc3).chr(0xa2),			//  0x00E2
			'e^h' => chr(0xc3).chr(0xaa),			//  0x00EA
			'g^h' => chr(0xc4).chr(0x9d),			//  0x011D
			'i^h' => chr(0xc3).chr(0xae),			//  0x00EE
			'o^h' => chr(0xc3).chr(0xb4),			//  0x00F4
			'u^h' => chr(0xc3).chr(0xbb),			//  0x00FB
			'y^h' => chr(0xc5).chr(0xb7),			//  0x0177
			'A^h' => chr(0xc3).chr(0x82),			//  0x00C2
			'E^h' => chr(0xc3).chr(0x8a),			//  0x00CA
			'I^h' => chr(0xc3).chr(0x8e),			//  0x00CE
			'U^h' => chr(0xc3).chr(0x9b),			//  0x00DB
			
			// ^v for 'v' (caron) over a letter
			'a^v' => chr(0xc7).chr(0x8e),			//  0x01CE
			'c^v' => chr(0xc4).chr(0x8d),			//  0x010D
			'e^v' => chr(0xc4).chr(0x9b),			//  0x011B
			'g^v' => chr(0xc7).chr(0xa7),			//  0x01E7
			'i^v' => chr(0xc7).chr(0x90),			//  0x01D0
			'n^v' => chr(0xc5).chr(0x88),			//  0x0148
			'o^v' => chr(0xc7).chr(0x92),			//  0x01D2
			'r^v' => chr(0xc5).chr(0x99),			//  0x0159
			's^v' => chr(0xc5).chr(0xa1),			//  0x0161
			'u^v' => chr(0xc7).chr(0x94),			//  0x01D4
			'z^v' => chr(0xc5).chr(0xbe),			//  0x017E
			'C^v' => chr(0xc4).chr(0x8c),			//  0x010C
			'D^v' => chr(0xc4).chr(0x8e),			//  0x010E
			'R^v' => chr(0xc5).chr(0x98),			//  0x0158
			'S^v' => chr(0xc5).chr(0xa0),			//  0x0160
			'Z^v' => chr(0xc5).chr(0xbd),			//  0x017D
			
			// ^o for 'o' (ring) over a letter
			'a^o' => chr(0xc3).chr(0xa5),			//  0x00E5
			'u^o' => chr(0xc5).chr(0xaf),			//  0x016F
			'A^o' => chr(0xc3).chr(0x85),			//  0x00C5
			
			// ^t tilde
			'a^t' => chr(0xc3).chr(0xa3),			//  0x00E3
			'e^t' => chr(0xe1).chr(0xba).chr(0xbd),	//  0x1EBD
			'i^t' => chr(0xc4).chr(0xa9),			//  0x0129
			'n^t' => chr(0xc3).chr(0xb1),			//  0x00F1
			'o^t' => chr(0xc3).chr(0xb5),			//  0x00F5
			'u^t' => chr(0xc5).chr(0xa9),			//  0x0169
			'O^t' => chr(0xc3).chr(0x95),			//  0x00D5
			'U^t' => chr(0xc5).chr(0xa8),			//  0x0168
			' ^t' => ' ~',	// E.g. /records/207146/ (test #712)
		);
		
		# Capitals (have same meaning - data is too extensive to fix up manually)
		$diacritics += array (
			
			// ^A acute (upper-case)
			'A^A' => $diacritics['A^a'],
			'E^A' => $diacritics['E^a'],
			'I^A' => $diacritics['I^a'],
			'O^A' => $diacritics['O^a'],
			'S^A' => $diacritics['S^a'],
			
			// ^G grave (upper-case)
			'A^G' => $diacritics['A^g'],
			
			// ^U umlaut (upper-case)
			'A^U' => $diacritics['A^u'],
			'O^U' => $diacritics['O^u'],
			'U^U' => $diacritics['U^u'],
			
			// ^M macron (i.e. horizontal line over letter) (upper-case)
			'O^M' => $diacritics['O^m'],
			
			// ^Z '/' through letter (upper-case)
			'L^Z' => $diacritics['L^z'],
			'O^Z' => $diacritics['O^z'],	// E.g. /records/4932/ (test #713)
			
			// ^H for circumflex ('h' stands for 'hat') (upper-case)
			'I^H' => $diacritics['I^h'],
			
			// ^V for 'v' over a letter (upper-case)
			'C^V' => $diacritics['C^v'],
			'S^V' => $diacritics['S^v'],
			
			// ^O for 'o' over a letter (upper-case)
			'A^O' => $diacritics['A^o'],
		);
		
		# Return the array
		return $diacritics;
	}
	
	
	# Function to define suppression scenarios
	private function suppressionScenarios ()
	{
		# Records to suppress, defined as a set of scenarios represented by a token
		#!# Check whether locationCode locations with 'Periodical' are correct to suppress
		#!# Major issue: problem with e.g. /records/3929/ where two records need to be created, but not both should be suppressed; there are around 1,000 of these
		#!# Needs review - concern that this means that items with more than one location could get in the suppression bucket; see e-mail 19/12/2016
		return $suppressionScenarios = array (
			
			'EXPLICIT-SUPPRESS' => array (
				# 21,196 records
				'Record marked specifically to suppress, e.g. pamphlets needing review, etc.',
				# NB This has been achieved using a BCPL routine to mark the records as such
				"   field = 'status' AND value = '{$this->suppressionStatusKeyword}'
				"),
				
			'MISSING-QQ' => array (
				# 496 records
				'Missing with ?',
				"   field = 'location' AND value IN('??', 'Pam ?')
				"),
				
			'PICTURELIBRARY-VIDEO' => array (
				# 162 records
				'Picture Library Store videos',
				"   field = 'location' AND value LIKE 'Picture Library Store : Video%'
				"),
				
		);
	}
	
	
	# Function to define ignoration scenarios
	private function ignorationScenarios ()
	{
		# Records to suppress, defined as a set of scenarios represented by a token
		return $ignorationScenarios = array (
			
			'DESTROYED-COPIES' => array (
				# 1,422 records
				'Item has been destroyed during audit',
				"   field = 'location' AND value = 'Destroyed during audit'
				"),
				
			'IGS-IGNORED' => array (
				# 44 records
				'IGS locations',
				"   field = 'location' AND value IN('IGS', 'International Glaciological Society', 'Basement IGS Collection')
				"),
				
			'ELECTRONIC-REMOTE' => array (
				# 10 records
				'Digital records',
				"   field = 'location' AND value = 'Digital Repository'
				"),
				
			'STATUS-RECEIVED' => array (
				# 3,428 records
				'Item is being processed, i.e. has been accessioned and is with a bibliographer for classifying and cataloguing',
				"   field = 'status' AND value = 'RECEIVED'
				"),
				
			'STATUS-ORDER-CANCELLED' => array (
				# 0 records
				'Order cancelled by SPRI, but record retained for accounting/audit purposes in the event that the item arrives',
				"   field = 'status' AND value = 'ORDER CANCELLED'
				"),
				
			'STATUS-ON-ORDER' => array (
				# 576 records (563 records old + 13 records recent); see also: /reports/onorderold/ which matches
				'Item on order >1 year ago so unlikely to be fulfilled, but item remains desirable and of bibliographic interest',
				"   field = 'status' AND value = 'ON ORDER'
				"),
				
			'IGNORE-NIS' => array (
				# 7,478 records
				'Items held not in SPRI',
				"   field = 'location' AND value = 'Not in SPRI'
				"),
				
			'IGNORE-UL' => array (
				# 1,289 records
				'Items held at the UL (i.e. elsewhere)',
				"   field = 'location' AND value LIKE 'Cambridge University%'
				"),
				
		);
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
