<?php

# Class to manage Muscat data conversion
require_once ('frontControllerApplication.php');
class muscatConversion extends frontControllerApplication
{
	# Define the registry of reports; those prefixed with 'listing_' return data rather than record numbers; listings can be suffixed with a status (e.g. _info)
	private $reports = array (
		'q0naming_problem' => 'records without a *q0',
		'missingcategory_problem' => 'records without a category (*doc/*art/*ser)',
		'missingd_problem' => 'records without a *d that are not *ser and either no status or status is GLACIOPAMS',
		'missingacc_info' => 'records without a *acc',
		'missingt_problem' => 'records without a *t',
		'emptytvariants_problem' => 'records with an empty *t/*tt/*tc',
		'failinglocupgrade_problem' => 'records whose Library of Congress Cryrillic title upgrade will fail due to defective *t problems',
		'sermissingr_problem' => '*ser records without a *r, except where location is Not in SPRI',
		'artwithoutlocstatus_problem' => '*art records where there is no *loc and no *status',
		'tcnotone_problem' => 'records without exactly one *tc',
		'tgmismatch_problemok' => 'records whose *tg count does not match *t (not all will be errors)',
		'missingrpl_info' => 'records without a *rpl',
		'missingrplstatus_problem' => 'records in SPRI without a *rpl and without a *status, that are not *ser',
		'rploncewitho_info' => 'records having only one *rpl and *rpl is O',
		'rpl3charaz09_problem' => 'records having *rpl not matching [A-Z0-9]{1,3}',
		'locwithoutlocation_problem' => '*loc records where there is no *location',
		'status_info' => 'records with a status field',
		'multiplestatus_problem' => 'records with more than one *status field',
		'statusparallel_problem' => 'records with *status = Parallel',
		'statusglaciopams_info' => 'records with a *status field where the status is not GLACIOPAMS',
		'statuslocationglaciopams_problem' => 'records with a *status field and *location where the status is not GLACIOPAMS',
		'doclocationperiodical_info' => '*doc records with one *location, which is Periodical',
		'doclocationlocationperiodical_info' => '*doc records with two or more *locations, at least one of which is Periodical',
		'inwithj_problem' => '*in records which have a *j',
		'artnotjt_problem' => '*art records with a *j where *t does not immediately follow *j',
		'sernonuniquet_problemok' => '*ser records where t is not unique',
		'artbecomedoc_info' => 'records classified as articles which need to become documents',
		'arttoplevelp_problem' => '*art records with a top-level *p',
		'artwithk2_info' => 'linked analytics: *art records with *k2',
		'docwithkb_info' => '*doc records with *kb',
		'artinnokg_info' => 'records with *in but no *kg',
		'artinnokglocation_info' => "records with *in but no *kg, excluding records where the location is 'Pam*' or 'Not in SPRI'",
		'loclocfiltered1_info' => "records with two or more locations, having first filtered out any locations whose location is 'Not in SPRI'",
		'loclocfiltered2_info' => "records with two or more locations, having first filtered out any locations whose location is 'Not in SPRI'/'Periodical'/'Basement IGS Collection'/'Basement Seligman *'",
		'externallocations_info' => "records where no location is 'Not in SPRI', having first filtered out any matching a whitelist of internal locations",
		'loclocloc_info' => 'records with three or more locations',
		'singleexternallocation_problem' => 'records with only one location, which is not on the whitelist',
		'arttitlenoser' => 'articles without a matching serial title, that are not pamphlets or in the special collection',
		'notinspri_info' => 'items not in SPRI',
		'loccamuninotinspri_info' => 'records with location matching Cambridge University, not in SPRI',
		'loccamuniinspri_info' => 'records with location matching Cambridge University, in SPRI',
		'onordercancelled_info' => 'items on order or cancelled',
		'invalidacquisitiondate_problem' => 'items with an invalid acquisition date',
		'onorderold_info' => 'Items on order before 2013/09/01',
		'onorderrecent_info' => 'Items on order since 2013/09/01',
		'ordercancelled_info' => 'items where the order is cancelled',
		'absitalics_info' => 'records with italics in the abstract',
		'isbninvalid_problem' => 'records with invalid ISBN number, excluding ones known to be wrong in the printed original publication',
		'urlinvalid_problem' => 'records with a badly-formatted URL',
		'ndnd_problem' => 'records with two adjacent *nd entries',
		'misformattedad_problem' => 'records where ed/eds/comp/comps indicator is not properly formatted',
		'orphanedrole_problem' => 'records where *role is not followed by *n',
		'emptyauthor_problem' => 'records with an empty *a',
		'specialcharscasse' => 'records with irregular case-sensitivity of special characters',
		'unknowndiacritics_problem' => 'records with unknown diacritics',
	//	'emptyabstract' => 'records without abstracts',
		'locationunknown_info' => 'records where location is unknown, for records whether the status is not present or is GLACIOPAMS',
		'multiplesourcesser_info' => 'records with multiple sources (*ser)',
		'multiplesourcesdocart_info' => 'records with multiple sources (*doc/*art)',
		'multiplecopies_info' => 'records where there appear to be multiple copies, in notes field',
		'multiplein_problem' => 'records containing more than one *in field',
		'multiplej_problem' => 'records containing more than one *j field',
		'multipletopt_problem' => 'records containing more than one top-level *t field',
		'multipletoptt_problem' => 'records containing more than one top-level *tt field',
		'invaliddatestring_problem' => 'records with an invalid date string (though some are valid)',
		'serlocloc_problem' => '*ser records with two or more locations (though some are valid)',
		'artinperiodical_info' => '*art/*in records with location=Periodical',
		'multipleal_info' => 'records with multiple *al values',
		'541ccombinations_info' => 'records with combinations of multiple *fund/*kb/*sref values (for 541c)',
		'541ccombinations2_info' => 'records with combinations of multiple *fund/*kb/*sref values (for 541c), excluding sref+fund',
		'unrecognisedks_problem' => 'records with unrecognised *ks values',
		'malformedks_problem' => 'records with malformed *ks values',
		'offprints_info' => 'records that contain photocopy/offprint in *note/*local/*priv',
		'duplicatedlocations_problem' => 'records with more than one identical location',
		'unmatchedbrackets_problem' => 'unmatched { and } brackets',
		'nestedbrackets_problem' => 'nested { { and } } brackets',
		'subscriptssuperscripts_problem' => 'records still containing superscript brackets',
		'italicbracketsorder_problem' => 'records with italics within a subscript/superscript character',
		'translationnote_info' => 'records containing a note regarding translation',
		'multipletrees_problem' => 'records with two or more parent trees',
		'kgnotart_info' => 'records with a *kg that are not an *art',
		'langnott_info' => 'records with a *lang but no *tt, having first filtered out any locations whose *lang is English',
		'doctsperiodicaltitle_problem' => '*doc records whose (first) *ts does not match the start of a periodical title',
		'transliteratedenglish_problem' => 'records whose titles are being transliterated but appear to be in English',
		'transliteratefailure_problem' => 'records whose reverse transliteration is not reversible',
		'voyagerrecords_info' => 'records with an equivalent already in Voyager, targetted for merging',
		'nohostlang_problem' => 'records whose *in contains a *lang but the main part does not',
		'bibcheckerrors_problem' => 'records with Bibcheck errors',
		'multiplelocationsmissing_problem' => 'records with multiple locations but marked as missing',
		'overloadedkstokens_problem' => 'records with overloaded token values in *ks (except MISSING* and PGA)',
		'notemissing_problem' => "records with a note containing the word 'missing' without a *ks MISSING; not all will actually be missing",
		'emptyauthorcontainers_problem' => "records with empty author containers",
	);
	
	# Listing (values) reports
	private $listings = array (
		'multiplecopiesvalues_info' => 'listing: records where there appear to be multiple copies, in notes field - unique values',
		'diacritics_info' => 'listing: counts of diacritics used in the raw data',
		'journaltitles_info' => 'listing: journal titles',
		'seriestitles_info' => 'listing: series titles',
/* problem: */
#!# Need to create an equivalent report which shows counts
		'seriestitlemismatches1' => "listing: articles without a matching serial (journal) title in another record, that are not pamphlets or in the special collection (loc = 'Periodical')",
/* problem: */
		'seriestitlemismatches2' => "listing: articles without a matching serial (journal) title in another record, that are not pamphlets or in the special collection (loc is empty)",
		'seriestitlemismatches3' => 'listing: articles without a matching serial (journal) title in another record, that are not pamphlets or in the special collection (loc = other)',
		'languages' => 'listing: languages',
		'transliterations' => 'listing: transliterations',
		'paralleltitlelanguages' => 'listing: records with parallel titles, filtered to Russian',
		'distinctn1notfollowedbyn2' => 'Distinct values of all *n1 fields that are not immediately followed by a *n2 field',
		'distinctn2notprecededbyn1' => 'Distinct values of all *n2 fields that are not immediately preceded by a *n1 field',
		'kwunknown' => 'records where kw is unknown, showing the bibliographer concerned',
		'doclocationperiodicaltsvalues' => '*doc records with one *location, which is Periodical - distinct *ts values',
		'unrecognisedksvalues' => 'records with unrecognised *ks values - distinct *ks values',
		'volumenumbers_info' => 'volume number results arising from 490 macro',
		'voyagerlocations' => 'Muscat locations that do not map to Voyager locations',
		'translationnotevalues' => 'records containing a note regarding translation - distinct values',
		'mergestatus' => 'records with a merge status',
	);
	
	# Define the types
	private $types = array (
		'muscatview' => array (	// Sharded records
			'label'		=> '<img src="/images/icons/page_white.png" alt="" border="0" /> Muscat editing view',
			'title'		=> 'The data as it would be seen if editing in Muscat',
			'errorHtml'	=> "The 'muscatview' version of record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('recordId', 'field', 'value'),
			'idField'	=> 'recordId',
			'orderBy'	=> 'recordId, line',
			'class'		=> 'regulated',
		),
		'rawdata' => array (	// Sharded records
			'label'		=> '<img src="/images/icons/page_white_text.png" alt="" border="0" /> Raw data',
			'title'		=> 'The raw data as exported by Muscat',
			'errorHtml'	=> "There is no such record <em>%s</em>. Please try searching again.",
			'fields'	=> array ('recordId', 'field', 'value'),
			'idField'	=> 'recordId',
			'orderBy'	=> 'recordId, line',
			'class'		=> 'compressed',	// 'regulated'
		),
		'processed' => array (	// Sharded records
			'label'		=> '<img src="/images/icons/page.png" alt="" border="0" /> Processed version',
			'title'		=> 'The data as exported by Muscat',
			'errorHtml'	=> "The 'processed' version of record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('recordId', 'field', 'value'),
			'idField'	=> 'recordId',
			'orderBy'	=> 'recordId, line',
			'class'		=> 'compressed',
		),
		'xml' => array (
			'label'		=> '<img src="/images/icons/page_white_code.png" alt="" border="0" /> Muscat as XML',
			'title'		=> 'Representation of the Muscat data as XML, via the defined Schema',
			'errorHtml'	=> "The XML representation of the Muscat record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('id', 'xml'),
			'idField'	=> 'id',
			'orderBy'	=> 'id',
			'class'		=> false,
		),
		'marc' => array (
			'label'		=> '<img src="/images/icons/page_white_code_red.png" alt="" border="0" /> Muscat as MARC',
			'title'		=> 'Representation of the XML data as MARC21, via the defined parser description',
			'errorHtml'	=> "The MARC21 representation of the Muscat record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('id', 'mergeType', 'mergeVoyagerId', 'marc', 'bibcheckErrors'),
			'idField'	=> 'id',
			'orderBy'	=> 'id',
			'class'		=> false,
		),
	);
	
	# Fieldsindex fields
	private $fieldsIndexFields = array (
		'title' => 'tc',
		'region' => 'ks',
		'surname' => 'n1',
		'forename' => 'n2',
		'journaltitle' => '/art/j/tg/t',
		'seriestitle' => '/doc/ts',
		'year' => 'd',
		'language' => 'lang',
		'abstract' => 'abs',
		'keyword' => 'kw',
		'isbn' => 'isbn',
		'location' => 'location',
		'anywhere' => '*',
	);
	
	# Define supported languages
	private $supportedReverseTransliterationLanguages = array (
		'Russian' => 'BGN PCGN 1947',	// Filename becomes bgn_pcgn_1947.xml
	);
	
	# Define the file sets and their labels
	private $filesets = array (
		'migrate'	=> 'Migrate to Voyager',
		'suppress'	=> 'Suppress from OPAC',
		'ignore'	=> 'Ignore record',
	);
	
	# Define the merge types
	private $mergeTypes = array (
		'TIP'	=> 'exact title match and ISSN match',
		'TP'	=> 'exact title, but not ISSN',
		'IP'	=> 'ISSN match, but not exact title',
		'P'		=> 'probable match, unconfirmed',
		'C'		=> 'probable match, confirmed',
	);
	
	# Define the location codes
	private $locationCodes = array (
		'[0-9]{1,3} ?[A-Z]'							=> 'SPRI-SER',
		'Periodical'								=> 'SPRI-SER',
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
		'Russian Gallery'							=> 'SPRI-RUS',
		'Russian'									=> 'SPRI-RUS',
		'Shelf'										=> 'SPRI-SHF',
		'Special Collection'						=> 'SPRI-SPC',
		'Theses'									=> 'SPRI-THE',
		'Digital Repository'						=> 'SPRI-ELE',
		'F:/public/session'							=> 'SPRI-ELE',
		'F:/public/session/electronic publications'	=> 'SPRI-ELE',
		'Online'									=> 'SPRI-ELE',
		'World Wide Web'							=> 'SPRI-ELE',
		'WWW'										=> 'SPRI-ELE',
		"Friends' Room"								=> 'SPRI-FRI',
		"Museum Working Collection"					=> 'SPRI-MUS',
		'153-158 Wubbold Room'						=> 'SPRI-SER',
		// SPRI-NIS defined in code below
	);
	
	# Define known *ks values to be ignored
	private $overloadedKsTokens = array ('AGI', 'AGI', 'AGI1', 'AK', 'AK1', 'AM', 'AM/HL', 'BL', 'C', 'C?', 'CC', 'D', 'D?', 'GLEN', 'GLEN', 'HL', 'HS', 'HSO', 'HS1', 'HS (RS)', 'HS(RS)', 'HS/RUS', 'HSSB', 'HSSB1', 'HSSB2', 'HSSB3', 'IW', 'IW', 'IW1', 'IWO', 'JHR', 'JHRprob', 'JHR1', 'JHRO', 'JP', 'JW', 'JW1', 'LASTPGA', 'MG', 'MISSING', 'MPO', 'MPP', 'NOM', 'NOM1', 'NOMO', 'OM', 'PARTIAL RECORD', 'PGA', 'PGA1', 'RF', 'RF', 'RS', 'SS', 'WM', 'Y', );
	
	# Caches
	private $lookupTablesCache = array ();
	
	
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName' => 'Muscat conversion project',
			'authentication' => true,
			'administrators' => true,
			'hostname' => 'localhost',
			'database' => 'muscatconversion',	// Requires SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX
			'username' => NULL,
			'password' => NULL,
			'table' => false,	// Not used
			'chunkEvery' => 2500,
			'debugMode' => false,
			'paginationRecordsPerPageDefault' => 50,
			'div' => strtolower (__CLASS__),
			'useFeedback' => false,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available tasks
		$actions = array (
			'reports' => array (
				'description' => false,
				'url' => 'reports/',
				'tab' => 'Reports',
				'icon' => 'asterisk_orange',
			),
			'reportdownload' => array (
				'description' => 'Export',
				'url' => 'reports/',
				'export' => true,
			),
			'records' => array (
				'description' => 'Records',
				'url' => 'records/',
				'tab' => 'Records',
				'icon' => 'application_double',
			),
			'fields' => array (
				'description' => false,
				'url' => 'fields/',
				'tab' => 'Fields',
				'icon' => 'chart_organisation',
			),
			'search' => array (
				'description' => 'Search the catalogue',
				'url' => 'search/',
				'tab' => 'Search',
				'icon' => 'magnifier',
			),
			'statistics' => array (
				'description' => 'Statistics',
				'url' => 'statistics/',
				'tab' => 'Statistics',
				'icon' => 'chart_pie',
			),
			'import' => array (
				'description' => 'Import',
				'url' => 'import/',
				'tab' => 'Import',
				'icon' => 'database_refresh',
				'administrator' => true,
			),
			'schema' => array (
				'description' => 'Schema',
				'subtab' => 'Schema',
				'icon' => 'tag',
				'parent' => 'admin',
				'administrator' => true,
				'allowDuringImport' => true,
			),
			'marcparser' => array (
				'description' => 'MARC21 parser definition',
				'subtab' => 'MARC21 parser definition',
				'url' => 'marcparser.html',
				'icon' => 'chart_line',
				'parent' => 'admin',
				'administrator' => true,
				'allowDuringImport' => true,
			),
			'transliterator' => array (
				'description' => 'Reverse-transliteration definition',
				'subtab' => 'Reverse-transliteration definition',
				'url' => 'transliterator.html',
				'icon' => 'arrow_refresh',
				'parent' => 'admin',
				'administrator' => true,
				'allowDuringImport' => true,
			),
			'merge' => array (
				'description' => 'Merge definition',
				'subtab' => 'Merge definition',
				'url' => 'merge.html',
				'icon' => 'arrow_merge',
				'parent' => 'admin',
				'administrator' => true,
				'allowDuringImport' => true,
			),
			'export' => array (
				'description' => 'Export MARC21 output',
				'tab' => 'Export',
				'url' => 'export/',
				'icon' => 'database_go',
				'administrator' => true,
				'allowDuringImport' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Username' PRIMARY KEY,
			  `active` enum('','Yes','No') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='System administrators';
			
			CREATE TABLE IF NOT EXISTS `marcparserdefinition` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key' PRIMARY KEY,
			  `definition` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Parser definition',
			  `savedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Automatic timestamp'
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC parser definition';
			
			CREATE TABLE IF NOT EXISTS `reversetransliterationdefinition` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key' PRIMARY KEY,
			  `definition` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Parser definition',
			  `savedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Automatic timestamp'
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC parser definition';
		";
	}
	
	
	# Additional processing
	public function main ()
	{
		# Add other settings
		$this->exportsDirectory = $this->applicationRoot . '/exports/';
		$this->exportsProcessingTmp = $this->applicationRoot . '/exports-tmp/';
		$this->cpanDir = $this->applicationRoot . '/libraries/transliteration/cpan';
		
		# Determine the import lockfile location
		$this->lockfile = $this->exportsProcessingTmp . 'lockfile.txt';
		
		# Ensure an import is not running
		if ($importHtml = $this->importInProgress ()) {
			$allowedDuringImport = (isSet ($this->actions[$this->action]['allowDuringImport']) && $this->actions[$this->action]['allowDuringImport']);
			if (!$allowedDuringImport) {
				echo $importHtml;
				return false;
			}
		}
		
		# Determine and show the export date
		$isExportType = (isSet ($this->actions[$this->action]['export']) && $this->actions[$this->action]['export']);
		if (!$isExportType) {
			$this->exportDateDescription = $this->getExportDate ();
			echo "\n<p id=\"exportdate\">{$this->exportDateDescription}</p>";
		}
		
		# Determine which reports are informational reports
		$this->reportStatuses = $this->getReportStatuses ();
		
		# Merge the listings array into the main reports list
		$this->reports += $this->listings;
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
	}
	
	
	# Function to get the export date
	private function getExportDate ()
	{
		$tableStatus = $this->databaseConnection->getTableStatus ($this->settings['database'], 'catalogue_rawdata');
		return $tableStatus['Comment'];
	}
	
	
	# Function to determine the status of each report
	private function getReportStatuses ()
	{
		# Start a list of informational reports
		$reportStatuses = array ();
		
		# Loop through each report and each listing, detecting the status, and rewriting the name
		$this->reports  = $this->parseReportNames ($this->reports , $reportStatuses);
		$this->listings = $this->parseReportNames ($this->listings, $reportStatuses);
		
		# Return the status list
		return $reportStatuses;
	}
	
	
	# Helper function to strip any flag from report key names
	private function parseReportNames ($reportsRaw, &$reportStatuses)
	{
		# Loop through each report, detecting whether each report is informational, and rewriting the name
		$reports = array ();	// Array of report key names without flag appended
		foreach ($reportsRaw as $key => $value) {
			if (preg_match ('/^(.+)_(info|problem|problemok)$/', $key, $matches)) {
				$key = $matches[1];
				$reportStatuses[$key] = $matches[2];
				$reports[$key] = $value;	// Register under new name
			} else {
				$reportStatuses[$key] = NULL;	// Unknown status
			}
			$reports[$key] = $value;	// Recreated list, with any _info stripped
		}
		
		# Return the rewritten list
		return $reports;
	}
	
	
	# Home page
	public function home ()
	{
		# Welcome
		$html  = "\n<h2>Welcome</h2>";
		$html .= $this->reportsJumplist ();
		$html .= "\n<p>This administrative system enables Library staff at SPRI to get an overview of problems with Muscat records so that they can be prepared for eventual export to Voyager.</p>";
		$html .= "\n<h3>Reports available</h3>";
		$html .= $this->reportsTable ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to list the reports
	public function reports ($id = false)
	{
		# Start the HTML
		$html  = '';
		
		# If no specified report, create a listing of reports
		if (!$id) {
			
			# Compile the HTML
			$html .= "\n<h2>Reports</h2>";
			$html .= $this->reportsJumplist ();
			$html .= "\n<p>This page lists the various reports that check for data errors or provide an informational overview of aspects of the data.</p>";
			$html .= $this->reportsTable ();
			
			# Show the HTML and end
			echo $html;
			return true;
		}
		
		# Ensure the report ID is valid
		if (!isSet ($this->reports[$id])) {
			$html .= "\n<h2>Reports</h2>";
			$html .= $this->reportsJumplist ($id);
			$html .= "\n<p>There is no such report <em>" . htmlspecialchars ($id) . "</em>. Please check the URL and try again.</p>";
			echo $html;
			return false;
		}
		
		# Show the title
		$html .= "\n<h2>Report: " . htmlspecialchars (ucfirst ($this->reports[$id])) . '</h2>';
		$html .= $this->reportsJumplist ($id);
		
		# View the report
		$html .= $this->viewResults ($id);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create a reports list
	private function reportsTable ()
	{
		# Get the list of reports
		$reports = $this->getReports ();
		
		# Get the counts
		$counts = $this->getCounts ();
		
		# Get the total number of records
		$stats = $this->getStats ();
		$totalRecords = $stats['totalRecords'];
		
		# Mark problem reports with no errors as OK
		foreach ($this->reportStatuses as $key => $status) {
			if ($status == 'problem' || $status == 'problemok') {
				if (array_key_exists ($key, $counts)) {
					if ($counts[$key] == 0 || $status == 'problemok') {
						$this->reportStatuses[$key] = 'ok';
					}
				}
			}
		}
		
		# Convert to an HTML list
		$table = array ();
		foreach ($reports as $report => $description) {
			$key = $report . ($this->reportStatuses[$report] ? ' ' . $this->reportStatuses[$report] : '');	// Add CSS class if status known
			$link = $this->reportLink ($report);
			$table[$key]['Description'] = "<a href=\"{$link}\">" . ucfirst (htmlspecialchars ($description)) . '</a>';
			$table[$key]['Problems?'] = ($this->isListing ($report) ? '<span class="faded right">n/a</span>' : ($counts[$report] ? '<span class="warning right">' . number_format ($counts[$report]) : '<span class="success right">' . 'None') . '</span>');
			$percentage = ($counts[$report] ? round (100 * ($counts[$report] / $totalRecords), 2) . '%' : '-');
			$table[$key]['%'] = ($this->isListing ($report) ? '<span class="faded right">n/a</span>' : '<span class="comment right">' . ($percentage === '0%' ? '0.01%' : $percentage) . '</span>');
		}
		
		# Compile the HTML
		$html  = application::htmlTable ($table, array (), 'reports lines', $keyAsFirstColumn = false, false, $allowHtml = true, false, false, $addRowKeyClasses = true);
		
		# Note the data date
		$html .= "\n<p class=\"comment\"><br />{$this->exportDateDescription}.</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine if the specified report is a listing type
	private function isListing ($report)
	{
		return (array_key_exists ($report, $this->listings));
	}
	
	
	# Function to get the counts
	private function getCounts ()
	{
		# Get the list of reports
		$reports = $this->getReports ();
		
		# Get the counts
		$query = "SELECT report, COUNT(*) AS total FROM reportresults GROUP BY report;";
		$data = $this->databaseConnection->getPairs ($query);
		
		# Ensure that each report type has a count
		$counts = array ();
		foreach ($reports as $id => $description) {
			$counts[$id] = (isSet ($data[$id]) ? $data[$id] : 0);
		}
		
		# Return the counts
		return $counts;
	}
	
	
	# Function to create a reports jumplist
	private function reportsJumplist ($current = false)
	{
		# Determine the front reports page link
		$frontpage = $this->reportLink ();
		
		# Get the counts
		$counts = $this->getCounts ();
		
		# Create the list
		$droplist = array ();
		$droplist[$frontpage] = '';
		foreach ($this->reports as $report => $description) {
			$link = $this->reportLink ($report);
			$description = (strlen ($description) > 50 ? mb_substr ($description, 0, 50) . '...' : $description);	// Truncate
			$droplist[$link] = ucfirst ($description) . ($this->isListing ($report) ? '' : ' (' . number_format ($counts[$report]) . ')');
		}
		
		# Create a link to the selected item
		$selected = $this->reportLink ($current);
		
		# Compile the HTML and register a processor
		$html  = pureContent::htmlJumplist ($droplist, $selected, $this->baseUrl . '/', $name = 'reportsjumplist', $parentTabLevel = 0, $class = 'reportsjumplist', 'Switch to: ');
		pureContent::jumplistProcessor ($name);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to link a report
	private function reportLink ($record = false)
	{
		return $this->baseUrl . '/reports/' . ($record ? htmlspecialchars ($record) . '/' : '');
	}
	
	
	# Function to get the list of reports
	public function getReports ()
	{
		# Ensure each report exists
		foreach ($this->reports as $report => $description) {
			$methodName = 'report_' . $report;
			if (!method_exists ($this, $methodName)) {
				unset ($this->reports[$report]);
			}
		}
		
		# Return the list
		return $this->reports;
	}
	
	
	# Function to view results of a report
	private function viewResults ($id)
	{
		# Determine the description
		$description = 'This report shows ' . $this->reports[$id] . '.';
		
		# Start the HTML With the description
		$html  = "\n<div class=\"graybox\">";
		if (!$this->isListing ($id)) {
			$html .= "\n<p id=\"exportlink\" class=\"right\"><a href=\"{$this->baseUrl}/reports/{$id}/{$id}.csv\">Export as CSV</a></p>";
		}
		$html .= "\n<p><strong>" . htmlspecialchars ($description) . '</strong></p>';
		$html .= "\n</div>";
		
		# Show the records for this query (having regard to any page number supplied via the URL)
		if ($this->isListing ($id)) {
			$viewMethod = "report_{$id}_view";
			$html .= $this->{$viewMethod} ();
		} else {
			$baseLink = '/reports/' . $id . '/';
			$html .= $this->recordListing ($id, false, array (), $baseLink, true);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to list the records
	public function records ($id = false)
	{
		# Start the HTML
		$html = '';
		
		# If no ID, show the search form
		if (!$id) {
			if ($id = $this->recordSearchForm ($html)) {
				
				# State if not found
				if (!$this->getRecords ($id, false, $convertEntities = true)) {
					$html .= "\n<p>There is no such record <em>" . htmlspecialchars ($id) . '</em>. Please try searching again.</p>';
					echo $html;
					return false;
				}
				
				# Redirect to the found record
				$url = $_SERVER['_SITE_URL'] . $this->recordLink ($id);
				application::sendHeader (301, $url, $html);
				echo $html;
				return true;
			}
			
			# Show the HTML and end
			echo $html;
			return true;
		}
		
		# Enable jQuery, needed for previous/next keyboard navigation, and tabbing
		$html .= "\n\n\n" . '<script type="text/javascript" src="//code.jquery.com/jquery.min.js"></script>';
		
		# Add previous/next links
		$previousNextLinks = $this->previousNextLinks ($id);
		$html .= "\n<p>Record #<strong>{$id}</strong>:</p>";
		$html .= $previousNextLinks;
		
		# Get the data, in order, starting with the most basic version, ending if any fail
		$tabs = array ();
		$i = 0;
		foreach ($this->types as $type => $attributes) {
			if (!$tabs[$type] = $this->recordFieldValueTable ($id, $type, $errorHtml)) {
				if ($i == 0) {	// First one is the master record; if it does not exist, assume this is actually a genuinely non-existent record
					$errorHtml = "There is no such record <em>{$id}</em>.";
				}
				$html .= "\n<p>{$errorHtml}</p>";
				echo $html;
				return false;
			}
			$i++;
		}
		
		# Compile the labels, whose ordering is used for the tabbing
		$labels = array ();
		$typesReverseOrder = array_reverse ($this->types, true);
		$i = 1;
		foreach ($typesReverseOrder as $type => $attributes) {
			$labels[$type] = "<span accesskey=\"" . $i++ . "\" title=\"{$attributes['title']}\">" . $attributes['label'] . '</span>';
		}
		
		# Load into tabs and render
		require_once ('jquery.php');
		$jQuery = new jQuery (false, false, false, $jQueryLoaded = true);
		$jQuery->tabs ($labels, $tabs);
		$html .= $jQuery->getHtml ();
		
		// $html .= application::dumpData ($record, false, true);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create previous/next record links
	private function previousNextLinks ($id)
	{
		# Start the HTML
		$html = '';
		
		# Get the data
		$query = "SELECT
			(SELECT MAX(id) AS id FROM catalogue_rawdata WHERE id < {$id}) AS previous,
			(SELECT MIN(id) AS id FROM catalogue_rawdata WHERE id > {$id}) AS next
		;";
		$data = $this->databaseConnection->getOne ($query);
		
		# Create a list
		$list = array ();
		$list[] = ($data['previous'] ? '<a id="previous" href="' . "{$this->baseUrl}/records/{$data['previous']}/" . '"><img src="/images/icons/control_rewind_blue.png" alt="Previous record" border="0" /></a>' : '');
		$list[] = '#' . $id;
		$list[] = ($data['next'] ? '<a id="next" href="' . "{$this->baseUrl}/records/{$data['next']}/" . '"><img src="/images/icons/control_fastforward_blue.png" alt="Next record" border="0" /></a>' : '');
		
		# Compile the HTML
		$html = application::htmlUl ($list, 0, 'previousnextlinks');
		
		# Add keyboard navigation; see: http://stackoverflow.com/questions/12682157/
		$html .= '
			<script language="javascript" type="text/javascript">
				$(function() {
					var keymap = {};
					keymap[ 37 ] = "#previous";		// Left
					keymap[ 39 ] = "#next";			// Right
					
					$( document ).on( "keyup", function(event) {
						var href;
						var selector = keymap[ event.which ];
						// if the key pressed was in our map, check for the href
						if ( selector ) {
							window.location = $( selector ).attr( "href" );
						}
					});
				});
			</script>
		';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a record field/value table
	private function recordFieldValueTable ($id, $type, &$errorHtml = false)
	{
		# Get the data or end
		$linkFields = ($type != 'xml');
		if (!$record = $this->getRecords ($id, $type, $convertEntities = true, $linkFields)) {
			$errorHtml = sprintf ($this->types[$type]['errorHtml'], $id);
			return false;
		}
		
		# Render the result
		switch ($type) {
			
			# Text records
			case 'marc':
				# Uncomment this block to compute the MARC on-the-fly for testing purposes
			/*
			*/
				$data = $this->getRecords ($id, 'xml');
				$marcParserDefinition = $this->getMarcParserDefinition ();
				$mergeDefinition = $this->parseMergeDefinition ($this->getMergeDefinition ());
				$record['marc'] = $this->convertToMarc ($marcParserDefinition, $data['xml'], $errorString, $mergeDefinition, $record['mergeType'], $record['mergeVoyagerId'], $marcPreMerge /* passed back by reference */);		// Overwrite with dynamic read, maintaining other fields (e.g. merge data)
				$output  = "\n<p>The MARC output uses the <a target=\"_blank\" href=\"{$this->baseUrl}/marcparser.html\">parser definition</a> to do the mapping from the XML representation.</p>";
				if ($record['bibcheckErrors']) {
					$output .= "\n<pre>" . "\n<p class=\"warning\">Bibcheck " . (substr_count ($record['bibcheckErrors'], "\n") ? 'errors' : 'error') . ":</p>" . $record['bibcheckErrors'] . "\n</pre>";
				}
				if ($errorString) {
					$output .= "\n<p class=\"warning\">{$errorString}</p>";
				}
			/*
			*/
				$output .= "\n<div class=\"graybox marc\">";
				$output .= "\n<p id=\"exporttarget\">Target <a href=\"{$this->baseUrl}/export/\">export</a> group: <strong>" . $this->migrationStatus ($id) . "</strong></p>";
				if ($record['mergeType']) {
					$output .= "\n<p>Note: this record has <strong>merge data</strong>, shown underneath.</p>";
				}
				$output .= "\n<pre>" . $this->highlightSubfields (htmlspecialchars ($record[$type])) . "\n</pre>";
				if ($record['mergeType']) {
					$output .= "\n<h3>Merge data</h3>";
					$output .= "\n<p>Merge type: {$record['mergeType']}" . (isSet ($this->mergeTypes[$record['mergeType']]) ? " ({$this->mergeTypes[$record['mergeType']]})" : '') . "\n<br />Voyager ID: #{$record['mergeVoyagerId']}.</p>";
					$output .= "\n<h4>Pre-merge record from Muscat:</h4>";
					$output .= "\n<pre>" . $this->highlightSubfields (htmlspecialchars ($marcPreMerge)) . "\n</pre>";
					$output .= "\n<h4>Existing Voyager record:</h4>";
					$output .= "\n<pre>" . $this->highlightSubfields (htmlspecialchars ($this->existingVoyagerRecord ($record['mergeType'], $record['mergeVoyagerId']))) . "\n</pre>";
				}
				$output .= "\n</div>";
				break;
				
			case 'xml':
				# Uncomment this block to compute the XML on-the-fly for testing purposes
			/*
				$data = $this->getRecords ($id, 'processed');
				$schemaFlattenedXmlWithContainership = $this->getSchema (true);
				$record = array ();
				$record['xml'] = xml::dropSerialRecordIntoSchema ($schemaFlattenedXmlWithContainership, $data, $errorHtml, $debugString);
			*/
				$output = "\n<div class=\"graybox\">" . "\n<pre>" . htmlspecialchars ($record[$type]) . "\n</pre>\n</div>";
				break;
				
			# Tabular records
			default:
				$class = $this->types[$type]['class'];
				foreach ($record as $index => $row) {
					$showHtmlTags = array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>');
					foreach ($showHtmlTags as $htmlTag) {
						$record[$index]['value'] = str_replace ($htmlTag, '<span style="color: #903;"><tt>' . htmlspecialchars ($htmlTag) . '</tt></span>', $record[$index]['value']);	// Show HTML as visible HTML
					}
				}
				$output = application::htmlTable ($record, array (), 'lines record' . ($class ? " {$class}" : ''), $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true, false, $addCellClasses = true);
				break;
		}
		
		# Return the HTML/XML
		return $output;
	}
	
	
	# Function to show an existing Voyager record
	private function existingVoyagerRecord ($mergeType, $mergeVoyagerId)
	{
		# Start the HTML by stating the merge target
		$html  = "\n<p>Original Voyager record:</p>";
		
		# If the merge voyager ID is not yet a pure integer (i.e. not yet a one-to-one lookup), state this and end
		if (!ctype_digit ($mergeVoyagerId)) {
			return 'There is not yet a one-to-one match, so no Voyager record can be displayed.';
		}
		
		# Look up Voyager record, or end (e.g. no match)
		if (!$voyagerRecordShards = $this->databaseConnection->select ($this->settings['database'], 'catalogue_external', array ('voyagerId' => $mergeVoyagerId))) {
			return "Error: the specified Voyager record (#{$mergeVoyagerId}) could not be found in the external datasource.";
		}
		
		# Construct the record lines
		$recordLines = array ();
		foreach ($voyagerRecordShards as $shard) {
			$hasIndicators = (!preg_match ('/^(LDR|00[0-9])$/', $shard['field']));
			$recordLines[] = $shard['field'] . ($hasIndicators ? ' ' . $shard['indicators'] : '') . ' ' . $shard['data'];
		}
		
		# Implode to string
		$record = implode ("\n", $recordLines);
		
		# Return the HTML
		return $record;
	}
	
	
	# Function to obtain the migration status for a MARC record
	private function migrationStatus ($id)
	{
		# Obtain the status
		$status = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_marc', 'status', $conditions = array ('id' => $id));
		
		# Assign label
		$label = $this->filesets[$status];
		
		# Return the label for the status
		return $label;
	}
	
	
	# Function to provide subfield highlighting
	private function highlightSubfields ($string)
	{
		return preg_replace ("/({$this->doubleDagger}[a-z0-9])/", '<strong>\1</strong>', $string);
	}
	
	
	# Function to display the schema
	public function schema ()
	{
		# Read the schema
		$xml = $this->getSchema (false, true);
		
		# Convert to HTML
		$html = "\n<pre>" . htmlspecialchars ($xml) . '</pre>';
		
		# Surround with a presentational box
		$html = "\n<div class=\"graybox\">{$html}</div>";
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to define the schema
	private function getSchema ($flattenedWithContainership = false, $formatted = false)
	{
		# Define the structure
		$structure = "
<?xml version='1.0' encoding='UTF-8' ?>
<root>
	<q0 /><!-- ID -->
	<status /><!-- automatically generated when item is ordered. Unless automatically deleted during cataloguing, this field can be updated to show the current status of an order if there is any reason for failure to supply. The same applies to *art and *ser order records. See 3.2.9.3 -->
	
	<doc><!-- a whole document (*doc) consisting of a book, report, volume of conference proceedings, letter etc.; -->
		<ag>
			<a><!-- author(s) -->
				<n1 />
				<n2 />
				<nd />
			</a>
			<ad /><!-- authorial detail (usually 'and others' 'ed.' 'eds.') - repeatable field -->
			<al><!-- alternative spelling of transliterated name or alternative name -->
				<n1 />
				<n2 />
				<nd />
			</al>
			<aff /><!-- affiliation -->
		</ag>
		<tg><!-- title group, an invisible field which groups all *t entries for searching -->
			<t /><!-- title -->
			<tt /><!-- translation of title (if *t is not in English) -->
			<ta /><!-- amendment to misspelled title (supplied by bibliographer) -->
			<to /><!-- title of original (if publication is a translation) -->'
			<tc /><!-- UNKNOWN -->
		</tg>
		<lang /><!-- name of language (e.g. 'French' 'German'). Give more than one language if bi- or multi-lingual (e.g. French * English)  'English' is assumed and never entered by the bibliographers. If left blank Muscat defaults to English for indexing purposes -->
		<lpt /><!-- Languages of parallel title -->
		<e><!-- editor(s) of whole work, use n for name in indirect order (*e edited by *n Smith/Bob); in practice this has also been used to add non-author contributors - this field in practice contains messy data often -->
			<role />
			<n><!-- Name field (used in conjunction with *e and *ee fields) -->
				<n1 />
				<n2 />
				<nd />
			</n>
		</e>
		<edn /><!-- edition -->
		<ee><!-- editors of this edition, use *n for name in indirect order -->
			<role />
			<n><!-- Name field (used in conjunction with *e and *ee fields) -->
				<n1 />
				<n2 />
				<nd />
			</n>
		</ee>
		<pg><!-- where two or more publishers, from different places, have collaborated -->
			<pl /><!-- place of publication -->
			<pu /><!-- name of publisher -->
		</pg>
		<d /><!-- date of publication -->
		<v /><!-- number of volumes, if more than one, e.g. 4 v. -->
		<vno /><!-- volume number, e.g., Vol.2 -->
		<p /><!-- pagination etc. -->
		<pt /><!-- part of work referred to -->
		<form /><!-- form of document, if non-book format -->
		<size /><!-- size of document -->
		<ts /><!-- title of series and number in series -->
		<issn /><!-- International Standard Serial Number -->
		<isbn /><!-- International Standard Book Number (entered with no spaces or hyphens) -->
		<notes>
			<note /><!-- additional miscellaneous information (public note, for publication in PGA, SPRILIB) -->
			<priv /><!-- additional information for library staff only (private note) -->
			<local /><!-- additional note, not for publication, but of use to library users on site, e.g. 'SPRI has 2 copies', 'CD-ROM kept in Library Office', etc. -->
		</notes>
		<abs /><!-- annotation or abstract -->
		<k><!-- UDC classification numbers -->
			<ks />
			<kw /><!-- UDC translations. Added automatically by running c-tranudc (see Section ?) -->
		</k>
		<k2>
			<ka /><!-- Voyager merge data: Type of match and other comments -->
			<kb />
			<kc /><!-- Voyager merge data: Serial id/control number -->
			<kd />
			<ke /><!-- Analytic record link: (redundant for processing purposes) used in host record that Muscat uses for GUI purposes to link to a lookup -->
			<kf /><!-- Analytic record link: (redundant for processing purposes) internal Muscat GUI representation of kg -->
			<kg /><!-- Analytic record link: used in analytic (child) record that is basically a join to the q0 (ID) of the host -->
		</k2>
		<kb /><!-- Exchange -->
		<loc><!-- location in SPRI library -->
			<location />
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</loc>
		<url>
			<urlfull /><!-- URL mirror -->
			<urlgen /><!-- URL mirror -->
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</url>
		<urlft><!-- URL of related website (used with *form Online publication) -->
			<urlfull /><!-- URL mirror -->
			<urlgen /><!-- URL mirror -->
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</urlft>
		<rpl /><!-- giving code letter to enable records to be sorted into subject sections in PGA. See 3.2.8. -->
		<!-- Acquisition fields: -->
		<acq><!-- left blank	 -->
			<ref /><!-- Annnn	(Order number added automatically. The initial letter changes each year,	i.e. A for 1995, B for 1996, C for 1997, etc.) -->
			<date /><!-- yyyy/mm/dd -->
			<o /><!-- supplier	(give surname first, e.g. Bull, Colin not Colin Bull. Do not insert 'requested' or other info before the name. Any other details relating to suppliers should be inserted in the *priv field.) -->
			<!--<o /> donor	(as above, but add (gift) after name) -->
			<pr /><!-- price	(enter pound symbol as GBP, $ as USD, etc.) -->
			<fund /><!-- code	(see 3.2.9.2) -->
			<sref /><!-- Supplier reference -->
			<recr /><!-- bibliographer's initials -->
		</acq>
		<acc><!-- accession data -->
			<ref /><!-- accession number (i.e. number stamped on book, not database record number) -->
			<date /><!-- yyyy/mm/dd -->
			<con /><!-- condition or conservation note -->
			<recr /><!-- bibliographer's initials -->
		</acc>
		<doi>
			<doifld />
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</doi>
	</doc>
	
	<art><!-- a part document (*art) consisting of a paper in a journal (even if the paper takes the whole of one issue in a journal), a book chapter or conference paper -->
		<ag>
			<a><!-- author(s) repeatable field -->
				<n1 />
				<n2 />
				<nd />
			</a>
			<ad /><!-- authorial detail (usually 'and others' 'ed.' 'eds.') -->
			<al><!-- alternative spelling of transliterated name or alternative name -->
				<n1 />
				<n2 />
				<nd />
			</al>
			<aff /><!-- affiliation -->
		</ag>
		<tg><!-- title group, an invisible field which groups all *t entries for searching -->
			<t /><!-- title -->
			<tt /><!-- translation of title (if *t is not in English) -->
			<ta /><!-- amendment to misspelled title (supplied by bibliographer) -->
			<to /><!-- title of original (if publication is a translation) -->'
			<tc /><!-- UNKNOWN -->
		</tg>
		<lang /><!-- name of language (e.g. 'French' 'German') -->
		<lpt /><!-- Languages of parallel title -->
		<e><!-- editor(s) of whole work, use n for name in indirect order (*e edited by *n Smith/Bob); in practice this has also been used to add non-author contributors (sometimes for art/in/ records that relates to the *in level) - this field in practice contains messy data often -->
			<role />
			<n><!-- Name field (used in conjunction with *e and *ee fields) -->
				<n1 />
				<n2 />
				<nd />
			</n>
		</e>
		<in><!-- information about the document (i.e. book or conference proceedings etc.) in which the article occurs, which can be followed by: -->
<!-- redundant -->
			<ag>
				<a><!-- author(s) -->
					<n1 />
					<n2 />
					<nd />
				</a>
				<ad /><!-- authorial detail ..... -->
				<al><!-- alternative spelling of transliterated name or alternative name -->
					<n1 />
					<n2 />
					<nd />
				</al>
				<aff /><!-- affiliated author (not used at present) -->
			</ag>
			<tg><!-- title group, an invisible field which groups all *t entries for searching -->
				<t /><!-- title -->
				<tt /><!-- translation of title (if *t is not in English) -->
				<ta /><!-- amendment to misspelled title (supplied by bibliographer) -->
				<to /><!-- title of original (if publication is a translation) -->'
				<tc /><!-- UNKNOWN -->
			</tg>
			<lang /><!-- name of language (e.g. 'French' 'German'). Give more than one language if bi- or multi-lingual (e.g. French * English)  'English' is assumed and never entered by the bibliographers. If left blank Muscat defaults to English for indexing purposes -->
			<edn /><!-- edition -->
			<ee><!-- editors of this edition, use *n for name in indirect order -->
				<role />
				<n><!-- Name field (used in conjunction with *e and *ee fields) -->
					<n1 />
					<n2 />
					<nd />
				</n>
			</ee>
			<vno /><!-- the volume number of the work, in multi-volume works -->
			<pg><!-- where two or more publishers, from different places, have collaborated -->
				<pl /><!-- place of publication -->
				<pu /><!-- name of publisher -->
			</pg>
			<d /><!-- date of publication -->
<!-- /redundant -->
			<pt /><!-- part of work referred to -->
			<p /><!-- pagination -->
<!-- redundant -->
			<form /><!-- form of document, if non-book format -->
			<ts /><!-- title of series and number in series -->
			<issn /><!-- International Standard Serial Number -->
			<isbn /><!-- International Standard Book Number (entered with no spaces or hyphens) -->
<!-- /redundant -->
			<notes>
				<note /><!-- additional miscellaneous information (public note, for publication in PGA, SPRILIB) -->
				<priv /><!-- additional information for library staff only (private note) -->
				<local /><!-- additional note, not for publication, but of use to library users on site, e.g. 'SPRI has 2 copies', 'CD-ROM kept in Library Office', etc. -->
			</notes>
			<abs /><!-- annotation or abstract -->
			<k><!-- UDC classification numbers -->
				<ks />
				<kw /><!-- UDC translations. Added automatically by running c-transudc -->
			</k>
			<k2>
				<ka /><!-- Voyager merge data: Type of match and other comments -->
				<kb />
				<kc /><!-- Voyager merge data: Serial id/control number -->
				<kd />
				<ke /><!-- Analytic record link: (redundant for processing purposes) used in host record that Muscat uses for GUI purposes to link to a lookup -->
				<kf /><!-- Analytic record link: (redundant for processing purposes) internal Muscat GUI representation of kg -->
				<kg /><!-- Analytic record link: used in analytic (child) record that is basically a join to the q0 (ID) of the host -->
			</k2>
<!-- redundant -->
			<loc><!-- location in SPRI library -->
				<location />
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</loc>
<!-- /redundant -->
			<url>
				<urlfull /><!-- URL mirror -->
				<urlgen /><!-- URL mirror -->
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</url>
			<urlft><!-- URL of related website (used with *form Online publication) -->
				<urlfull /><!-- URL mirror -->
				<urlgen /><!-- URL mirror -->
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</urlft>
			<rpl /><!-- giving code letter to enable records to be sorted into subject sections in PGA. See 3.2.8. -->
<!-- redundant -->
			<!-- Acquisition fields: -->
			<acq><!-- left blank	 -->
				<ref /><!-- Annnn	(Order number added automatically. The initial letter changes each year,	i.e. A for 1995, B for 1996, C for 1997, etc.) -->
				<date /><!-- yyyy/mm/dd -->
				<o /><!--  supplier	(give surname first, e.g. Bull, Colin not Colin Bull. Do not insert 'requested' or other info before the name. Any other details relating to suppliers should be inserted in the *priv field.) -->
				<!--<o /> donor	(as above, but add (gift) after name) -->
				<pr /><!-- price	(enter pound symbol as GBP, $ as USD, etc.) -->
				<fund /><!-- code	(see 3.2.9.2) -->
				<sref /><!-- Supplier reference -->
				<recr /><!-- bibliographer's initials -->
			</acq>
<!-- /redundant -->
			<acc><!-- accession data -->
<!-- redundant -->
				<ref /><!-- accession number (i.e. number stamped on book, not database record number) -->
				<date /><!-- yyyy/mm/dd -->
				<con /><!-- condition or conservation note -->
<!-- /redundant -->
				<recr /><!-- bibliographer's initials -->
			</acc>
			<doi>
				<doifld />
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</doi>
		</in>
		<j><!-- information about the periodical in which the article occurs -->
			<tg><!-- title group, an invisible field which groups all *t entries for searching -->
				<t /><!-- title -->
				<tt /><!-- translation of title (if *t is not in English) -->
				<ta /><!-- amendment to misspelled title (supplied by bibliographer) -->
				<to /><!-- title of original (if publication is a translation) -->'
				<tc /><!-- UNKNOWN -->
			</tg>
			<lang /><!-- name of language (e.g. 'French' 'German'). Give more than one language if bi- or multi-lingual (e.g. French * English)  'English' is assumed and never entered by the bibliographers. If left blank Muscat defaults to English for indexing purposes -->
			<pg><!-- where two or more publishers, from different places, have collaborated -->
				<pl /><!-- place of publication -->
				<pu /><!-- name of publisher -->
			</pg>
			<d /><!-- date of publication -->
			<pt /><!-- part of work referred to -->
			<p /><!-- pagination -->
			<form /><!-- form of document, if non-book format -->
			<issn /><!-- International Standard Serial Number -->
			<isbn /><!-- International Standard Book Number (entered with no spaces or hyphens) -->
			<notes>
				<note /><!-- additional miscellaneous information (public note, for publication in PGA, SPRILIB) -->
				<priv /><!-- additional information for library staff only (private note) -->
				<local /><!-- additional note, not for publication, but of use to library users on site, e.g. 'SPRI has 2 copies', 'CD-ROM kept in Library Office', etc. -->
			</notes>
			<abs /><!-- annotation or abstract -->
			<k><!-- UDC classification numbers -->
				<ks />
				<kw /><!-- UDC translations. Added automatically by running c-transudc -->
			</k>
			<k2>
				<ka /><!-- Voyager merge data: Type of match and other comments -->
				<kb />
				<kc /><!-- Voyager merge data: Serial id/control number -->
				<kd />
				<ke /><!-- Analytic record link: (redundant for processing purposes) used in host record that Muscat uses for GUI purposes to link to a lookup -->
				<kf /><!-- Analytic record link: (redundant for processing purposes) internal Muscat GUI representation of kg -->
				<kg /><!-- Analytic record link: used in analytic (child) record that is basically a join to the q0 (ID) of the host -->
			</k2>
			<loc><!-- location in SPRI library -->
				<location />
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</loc>
			<url>
				<urlfull /><!-- URL mirror -->
				<urlgen /><!-- URL mirror -->
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</url>
			<urlft><!-- URL of related website (used with *form Online publication) -->
				<urlfull /><!-- URL mirror -->
				<urlgen /><!-- URL mirror -->
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</urlft>
			<rpl /><!-- giving code letter to enable records to be sorted into subject sections in PGA. See 3.2.8. -->
			<!-- Acquisition fields: -->
			<acq><!-- left blank	 -->
				<ref /><!-- Annnn	(Order number added automatically. The initial letter changes each year,	i.e. A for 1995, B for 1996, C for 1997, etc.) -->
				<date /><!-- yyyy/mm/dd -->
				<o /><!-- supplier	(give surname first, e.g. Bull, Colin not Colin Bull. Do not insert 'requested' or other info before the name. Any other details relating to suppliers should be inserted in the *priv field.) -->
				<!--<o /> donor	(as above, but add (gift) after name) -->
				<pr /><!-- price	(enter pound symbol as GBP, $ as USD, etc.) -->
				<fund /><!-- code	(see 3.2.9.2) -->
				<sref /><!-- Supplier reference -->
				<recr /><!-- bibliographer's initials -->
			</acq>
			<acc><!-- accession data -->
				<ref /><!-- accession number (i.e. number stamped on book, not database record number) -->
				<date /><!-- yyyy/mm/dd -->
				<con /><!-- condition or conservation note -->
				<recr /><!-- bibliographer's initials -->
			</acc>
			<doi>
				<doifld />
				<doslink /><!-- GUI field: not required for voyager -->
				<winlink /><!-- GUI field: not required for voyager -->
			</doi>
		</j>
	</art>
	
	<ser><!-- a periodical (*ser) -->
		<ag>
			<a><!-- author(s) -->
				<n1 />
				<n2 />
				<nd />
			</a>
			<ad /><!-- authorial detail (usually 'and others' 'ed.' 'eds.') - repeatable field -->
			<al><!-- alternative spelling of transliterated name or alternative name -->
				<n1 />
				<n2 />
				<nd />
			</al>
			<aff /><!-- affiliation -->
		</ag>
		<tg><!-- title group, an invisible field which groups all *t entries for searching -->
			<t /><!-- title -->
			<tt /><!-- translation of title (if *t is not in English) -->
			<ta /><!-- amendment to misspelled title (supplied by bibliographer) -->
			<to /><!-- title of original (if publication is a translation) -->'
			<tc /><!-- UNKNOWN -->
		</tg>
		<lang /><!-- name of language (e.g. 'French' 'German'). Give more than one language if bi- or multi-lingual (e.g. French * English)  'English' is assumed and never entered by the bibliographers. If left blank Muscat defaults to English for indexing purposes -->
		<lpt /><!-- Languages of parallel title -->
		<ft /><!-- former title -->
		<st /><!-- subsequent title -->
		<pg><!-- where two or more publishers, from different places, have collaborated -->
			<pl /><!-- place of publication -->
			<pu /><!-- name of publisher -->
		</pg>
		<abs /><!-- annotation or abstract -->
		<issn /><!-- International Standard Serial Number -->
		<isbn /><!-- International Standard Book Number (entered with no spaces or hyphens) -->
		<r /><!-- range of holdings -->
		<freq /><!-- frequency -->
		<form /><!-- form of document, if non-book format -->
		<size /><!-- size of document -->
		<note /><!-- additional miscellaneous information (public note, for publication in PGA, SPRILIB) -->
		<priv /><!-- additional information for library staff only (private note) -->
		<notes>
			<note /><!-- additional miscellaneous information (public note, for publication in PGA, SPRILIB) -->
			<priv /><!-- additional information for library staff only (private note) -->
			<local /><!-- additional note, not for publication, but of use to library users on site, e.g. 'SPRI has 2 copies', 'CD-ROM kept in Library Office', etc. -->
		</notes>
		<k><!-- UDC classification numbers -->
			<ks />
			<kw /><!-- UDC translations. Added automatically by running c-transudc -->
		</k>
		<k2><!-- left blank, but essential if *k2 included -->
			<ka /><!-- Voyager merge data: Type of match and other comments -->
			<kb />
			<kc /><!-- Voyager merge data: Serial id/control number -->
			<kd />
			<ke /><!-- Analytic record link: (redundant for processing purposes) used in host record that Muscat uses for GUI purposes to link to a lookup -->
			<kf /><!-- Analytic record link: (redundant for processing purposes) internal Muscat GUI representation of kg -->
			<kg /><!-- Analytic record link: used in analytic (child) record that is basically a join to the q0 (ID) of the host -->
		</k2>
		<kb /><!-- Exchange -->
		<loc><!-- location in SPRI library -->
			<location />
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</loc>
		<url>
			<urlfull /><!-- URL mirror -->
			<urlgen /><!-- URL mirror -->
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</url>
		<urlft><!-- URL of related website (used with *form Online publication) -->
			<urlfull /><!-- URL mirror -->
			<urlgen /><!-- URL mirror -->
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</urlft>
		<hold /><!-- holdings (Supplements to journals should be notated as follows in the holdings field: *hold 1-2, 3 (+ supp.), 4-8, etc. Note the space after the '+') -->
		<!-- Acquisition fields: -->
		<acq><!-- left blank	 -->
			<ref /><!-- Annnn	(Order number added automatically. The initial letter changes each year,	i.e. A for 1995, B for 1996, C for 1997, etc.) -->
			<date /><!-- yyyy/mm/dd -->
			<o /><!-- supplier	(give surname first, e.g. Bull, Colin not Colin Bull. Do not insert 'requested' or other info before the name. Any other details relating to suppliers should be inserted in the *priv field.) -->
			<!--<o /> donor	(as above, but add (gift) after name) -->
			<pr /><!-- price	(enter pound symbol as GBP, $ as USD, etc.) -->
			<fund /><!-- code	(see 3.2.9.2) -->
			<sref /><!-- Supplier reference -->
			<recr /><!-- bibliographer's initials -->
		</acq>
		<acc><!-- accession data -->
			<ref /><!-- accession number (i.e. number stamped on book, not database record number) -->
			<date /><!-- yyyy/mm/dd -->
			<con /><!-- condition or conservation note -->
			<recr /><!-- bibliographer's initials -->
		</acc>
		<doi>
			<doifld />
			<doslink /><!-- GUI field: not required for voyager -->
			<winlink /><!-- GUI field: not required for voyager -->
		</doi>
		<doslink /><!-- GUI field: not required for voyager -->
		<winlink /><!-- GUI field: not required for voyager -->
	</ser>
</root>
		";
		
		# Trim the structure to prevent parser errors
		$structure = trim ($structure);
		
		# Remove spaces if not formatted
		#!# This is rather hacky
		if (!$formatted) {
			$structure = str_replace (array ("\n", "\r", "\t"), '', $structure);
		}
		
		# Flatten if required
		if ($flattenedWithContainership) {
			require_once ('xml.php');
			$structure = xml::flattenedXmlWithContainership ($structure);
		}
		
		# Return the structure
		return $structure;
	}
	
	
	# Function to link a record
	private function recordLink ($id)
	{
		return $this->baseUrl . '/records/' . htmlspecialchars ($id) . '/';
	}
	
	
	# Function to provide a record search
	private function recordSearchForm (&$html)
	{
		# Introductory text and form
		$html .= "\n<p>You can use this form to go to a specific record, by entering a record number:</p>";
		$resultRecord = $this->recordForm ($html);
		
		# Browsing mode
		$html .= "\n<p><br /><br /><strong>Or browse</strong> through the records:</p>";
		$resultBrowse = $this->recordBrowser ($html);
		
		# End if no result
		if (!$resultRecord) {return false;}
		
		# Get the ID
		return $resultRecord['q'];
	}
	
	
	# Function to create a record search form
	private function recordForm (&$html, $miniform = false)
	{
		# Cache _GET and remove the action, to avoid ultimateForm thinking the form has been submitted
		#!# This is a bit hacky, but is necessary because we set name=false in the ultimateForm constructor
		$get = $_GET;	// Cache
		#!# This general scenario is best dealt with in future by adding a 'getIgnore' parameter to the ultimateForm constructor
		if (isSet ($_GET['action'])) {unset ($_GET['action']);}
		if (isSet ($_GET['item'])) {unset ($_GET['item']);}
		if (isSet ($_GET['thousand'])) {unset ($_GET['thousand']);}
		
		# Run the form module
		$form = new form (array (
			'displayRestrictions' => false,
			'get' => true,
			'name' => false,
			'nullText' => false,
			'div' => 'ultimateform ' . ($miniform ? 'miniform recordsearch' : 'largesearch'),
			'submitTo' => $this->baseUrl . '/records/',
			'display'		=> 'template',
			'displayTemplate' => ($miniform ? '<!--{[[PROBLEMS]]}-->' : '{[[PROBLEMS]]}') /* Slightly hacky way of ensuring the problems list doesn't appear twice on the page */ . '<p>{q} {[[SUBMIT]]}</p>',
			'submitButtonText' => 'Go!',
			'submitButtonAccesskey' => false,
			'formCompleteText' => false,
			'requiredFieldIndicator' => false,
			'reappear' => true,
		));
		$form->search (array (
			'name'		=> 'q',
			'size'		=> ($miniform ? 9 : 15),
			'maxlength'	=> 6,
			'title'		=> 'Search',
			'required'	=> (!$miniform),
			'placeholder' => ($miniform ? 'Record #' : 'Record number'),
			'autofocus'	=> (!$miniform),
			'regexp' => '^([0-9]+)$',
		));
		
		# Process the form
		$result = $form->process ($html);
		
		# Reinstate GET
		$_GET = $get;
		unset ($get);
		
		# Return the result
		return $result;
	}
	
	
	# Function to create a record browser, which exists mainly for the purpose of getting all the records into search engine indexes
	private function recordBrowser (&$html)
	{
		# Determine the number of records and therefore the number of listings by thousand records
		$data = $this->getStats ();
		$highestNumberedRecord = $data['highestNumberedRecord'];
		$thousands = floor ($highestNumberedRecord / 1000);
		
		# Check if a valid 'thousands' value has been supplied in the URL
		if (isSet ($_GET['thousand'])) {
			
			# If the 'thousands' value is invalid, throw a 404
			if (!ctype_digit ($_GET['thousand']) || ($_GET['thousand'] > $thousands)) {
				application::sendHeader (404);
				$html .= "\n<p>The record set you specified is invalid. Please check the URL or <a href=\"{$this->baseUrl}/records/\">pick from the index</a>.</p>";
				return false;
			}
			
			# List the values within this thousand
			$thousand = $_GET['thousand'];	// Validated as ctype_digit above
			$query = "SELECT id FROM fieldsindex WHERE FLOOR(id/1000) = '{$thousand}';";
			$ids = $this->databaseConnection->getPairs ($query);
			$html .= "\n<p>Records for {$thousand},xxx [or <a href=\"{$this->baseUrl}/records/\">reset</a>]:</p>";
			$html .= $this->listNumbers ($ids);
			return true;
		}
		
		# Create the list
		$list = array ();
		for ($i = 1; $i <= $thousands; $i++) {
			$list[] = $i;
		}
		
		# Render the HTML
		$html .= $this->listNumbers ($list, 'k', ',xxx');
		
		# Return success
		return true;
	}
	
	
	# Function to link through to an ID listing of record numbers
	private function listNumbers ($numbers, $linkSuffix = false, $visibleSuffix = false)
	{
		# Create a link for each thousand, with the first record being 1,000 (i.e. 1)
		$list = array ();
		foreach ($numbers as $i) {
			$list[] = "<li><a href=\"{$this->baseUrl}/records/{$i}{$linkSuffix}/\">{$i}{$visibleSuffix}</a></li>";
		}
		
		# Compile the HTML
		$html  = "\n<div class=\"graybox\">";
		$html .= application::splitListItems ($list, 4);
		$html .= "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Overriding function
	public function showTabs ($current, $class = 'tabs')
	{
		# Show the record number form
		$recordFormHtml = '';
		$this->recordForm ($recordFormHtml, true);
		$html = $recordFormHtml;
		
		# Run the standard tabs function
		$html .= parent::showTabs ($current, $class);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get records (or a single record)
	private function getRecords ($ids /* or single ID */, $type = false, $convertEntities = false, $linkFields = false)
	{
		# Determine the type whose table will be looked up from
		$type = ($type ? $type : 'rawdata');
		
		# Determine if this is a sharded table (i.e. a record is spread across multiple entries)
		$isSharded = ($this->types[$type]['idField'] == 'recordId');
		
		# If only a single record is requested, make into an array of one for consistency with multiple-record processing
		$singleRecordId = (is_array ($ids) ? false : $ids);
		if ($singleRecordId) {$ids = array ($ids);}
		
		# Get the raw data, or end
		if (!$records = $this->databaseConnection->select ($this->settings['database'], "catalogue_{$type}", $conditions = array ($this->types[$type]['idField'] => $ids), $this->types[$type]['fields'], false, $this->types[$type]['orderBy'])) {return false;}
		
		# Regroup by the record ID
		$records = application::regroup ($records, $this->types[$type]['idField'], true, $regroupedColumnKnownUnique = (!$isSharded));
		
		# If the table is a sharded table, process the shards
		if ($isSharded) {
			
			# Get the descriptions
			$descriptions = false;
		/*
			if ($type == 'rawdata') {
				$descriptions = $this->getFieldDescriptions ();
			}
		*/
			
			# Process each record
			foreach ($records as $recordId => $record) {
				foreach ($record as $index => $row) {
					
					# If showing descriptions, add the description field, and shift the record's value to the end
					if ($descriptions) {
						$records[$recordId][$index]['description'] = $descriptions[$row['field']];
						$value = $row['value'];
						unset ($records[$recordId][$index]['value']);
						$records[$recordId][$index]['value'] = $value;
					}
					
					# Handle entity conversions if required
					if ($convertEntities) {
						
						# Convert entities e.g. & becomes &amp; - only those changes afterwards (below) will be allowed through HTML
						$records[$recordId][$index]['value'] = htmlspecialchars ($records[$recordId][$index]['value']);
						
						# Allow italics, subscripts and superscripts in records, by converting back entity versions to proper HTML
						// $italicsPermittedInFields = array ('local', 't', 'tc');	// Find using SELECT DISTINCT (field) FROM catalogue_processed WHERE `value` LIKE '%<em>%';
						// if (in_array ($row['field'], $italicsPermittedInFields)) {
						$records[$recordId][$index]['value'] = str_replace (array ('&lt;em&gt;', '&lt;/em&gt;', '&lt;sub&gt;', '&lt;/sub&gt;', '&lt;sup&gt;', '&lt;/sup&gt;'), array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>'), $records[$recordId][$index]['value']);
						// }
					}
					
					# Convert the field name to a link if required
					if ($linkFields) {
						$link = $this->fieldLink ($row['field'], $convertEntities);
						$records[$recordId][$index]['field'] = "<a href=\"{$link}\">{$row['field']}</a>";
					}
					
					# Hyperlink values if required
					if ($linkFields) {
						if ($row['field'] == 'kg') {
							if (strlen ($row['value']) && ctype_digit ($row['value'])) {
								$records[$recordId][$index]['value'] = "<a href=\"{$this->baseUrl}/records/{$row['value']}/\">{$row['value']}</a>";
							}
						}
					}
					
					//application::dumpData ($record);
				}
			}
		}
		
		# If a single record, just return that item
		$recordOrRecords = ($singleRecordId ? $records[$singleRecordId] : $records);
		
		# Return the record (or false)
		return $recordOrRecords;
	}
	
	
	# Fields traversal
	public function fields ($field = false)
	{
		# Start the HTML with the title
		$html = '';
		
		# Get the distinct fields
		$fields = $this->getDistinctFields ();
		
		# If not on a specific field, show the listing as a sortable table
		if (!$field) {
			
			# Compile the HTML
			$html .= "\n<h2>Fields</h2>";
			$html .= "\n<p>The table below shows all the fields in use across all records.</p>";
			$html .= "\n<p>If you find any unrecognised fields, go into the listing for that field to find the records containing.</p>";
			$html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';
			$html .= application::htmlTable ($fields, array (), $class = 'fieldslisting lines compressed sortable" id="sortable', $keyAsFirstColumn = false, false, $allowHtml = true, false, $addCellClasses = true);
			
			# Show the HTML and end
			echo $html;
			return true;
		}
		
		# If a field is specified in the URL, validate it and list its contents
		if (!isSet ($fields[$field])) {
			$html .= "\n<h2>Fields</h2>";
			$html .= "\n<p>There is no such field <em>" . htmlspecialchars ($field) . "</em>.</p>";
			$html .= "\n<p>Please select from the <a href=\"{$this->baseUrl}/fields/\">listing</a> and try again.</p>";
			echo $html;
			return true;
		}
		
		# Determine whether to show a list of field totals or list of distinct values
		$showDistinctValues = (isSet ($_GET['values']) && $_GET['values'] == '1');
		
		# Determine whether to show a list of records for a distinct value
		$showDistinctRecords = ($showDistinctValues && isSet ($_GET['value']) && strlen ($_GET['value']));
		
		# Show subtabs
		$link = $this->fieldLink ($field);
		$links = array (
			'/'					=> $this->fieldsDroplist ($fields, $showDistinctValues, $field),
			"{$link}"			=> "<a href=\"{$link}\"><strong>Records</strong>: <em>{$field}</em></a>",
			"{$link}values/"	=> "<a href=\"{$link}values/\"><strong>Distinct values</strong>: <em>{$field}</em></a>",
		);
		$selected = $link . ($showDistinctValues ? 'values/' : '');;
		$html .= application::htmlUl ($links, 0, 'fieldstabs tabs subtabs right', true, false, false, false, $selected);
		
		# Show list of records for a distinct value if required
		if ($showDistinctRecords) {
			
			# Overwrite the value with a correct version that maintains URL encoding properly, specifically of + signs, by emulating it from the original REQUEST_URI
			# The problem arises because mod_rewrite decodes at the point of shifting the path fragment (e.g. "/attributes/harddisk/a+%2B+b/" results in "value=a   b"
			# See: http://stackoverflow.com/a/10999987/180733 , in particular: "In fact it is impossible to do using a rewrite rule alone. Apache decodes the URL before putting it through rewrite, but it doesn't understand plus signs"
			preg_match ('|^' . $this->baseUrl . "/fields/{$field}/values/(.+)/|", $_SERVER['REQUEST_URI'], $matches);	// e.g. /library/catalogue/fields/n1/values/a+b/
			$_GET['value'] = urldecode ($matches[1]);	// Overwrite, with the URLdecoding doing what Apache would natively do
			$value = $_GET['value'];
			
			# Define a query
			$query = "SELECT DISTINCT recordId FROM catalogue_processed WHERE field = :field AND value = BINARY :value;";
			$preparedStatementValues = array ('field' => $field, 'value' => $value);
			
			# Create a listing
			$html .= "\n<h2>Records for field with value <em>" . htmlspecialchars ($value) . "</em> in field <em>*{$field}</em></h2>";
			$html .= "\n<p>Below are records with the value <em>" . htmlspecialchars ($value) . "</em> in field <em>*{$field}</em>:</p>";
			$valueLinkEncoded = htmlspecialchars (urlencode ($value));
			$html .= $this->recordListing (false, $query, $preparedStatementValues, "/fields/{$field}/values/{$valueLinkEncoded}/");
			
		# Show distinct values if required
		} else if ($showDistinctValues) {
			
			# Get the total number of results as a known total to seed the paginator, overriding automatic counting (which will fail for a GROUP BY query)
			$countingQuery = "SELECT COUNT( DISTINCT(BINARY value) ) AS total FROM catalogue_processed WHERE field = :field;";
			$preparedStatementValues = array ('field' => $field);
			$knownTotalAvailable = $this->databaseConnection->getOneField ($countingQuery, 'total', $preparedStatementValues);
			
			# Dynamically select unique values
			$query = "SELECT
					value AS title,
					COUNT(*) AS instances
				FROM `catalogue_processed`
				WHERE field = :field
				GROUP BY BINARY value
				ORDER BY " . $this->databaseConnection->trimSql ('value') . "
			;";
			
			# Show the distinct values for this query (having regard to any page number supplied via the URL)
			$html .= "\n<h2>Distinct values for field <em>*{$field}</em></h2>";
			$html .= $this->recordListing (false, $query, $preparedStatementValues, "/fields/{$field}/values/", false, false, $view = 'valuestable', false, $knownTotalAvailable, 'distinct value');
			
		} else {
			
			# Get the total number of results as a known total to seed the paginator, overriding automatic counting (as the DISTINCT will give a wrong result)
			$countingQuery = "SELECT COUNT( DISTINCT(recordId) ) AS total FROM catalogue_processed WHERE field = :field;";
			$preparedStatementValues = array ('field' => $field);
			$knownTotalAvailable = $this->databaseConnection->getOneField ($countingQuery, 'total', $preparedStatementValues);
			
			# Define the query for records having this field
			$query = "SELECT DISTINCT(recordId) AS recordId FROM catalogue_processed WHERE field = :field ORDER BY recordId;";
			
			# Show the records for this query (having regard to any page number supplied via the URL)
			$html .= "\n<h2>Records containing field <em>*{$field}</em></h2>";
			$html .= "\n<p>Below are records which contain a field <em>{$field}</em> ('<em>" . htmlspecialchars ($fields[$field]['Description']) . "</em>'):</p>";
			$html .= $this->recordListing (false, $query, $preparedStatementValues, "/fields/{$field}/", false, false, 'listing', false, $knownTotalAvailable);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create a fields droplist
	private function fieldsDroplist ($fields, $showDistinctValues, $currentField)
	{
		# Create an array of field links
		$droplist = array ();
		$droplist[$this->baseUrl . '/fields/'] = 'Fields:';
		foreach ($fields as $field => $attributes) {
			$link = $this->fieldLink ($field) . ($showDistinctValues ? 'values/' : '');
			$droplist[$link] = $field;
		}
		
		# Determine the current field link
		$selected = $this->fieldLink ($currentField) . ($showDistinctValues ? 'values/' : '');
		
		# Compile the HTML and register a processor
		$html  = pureContent::htmlJumplist ($droplist, $selected, $this->baseUrl . '/', $name = 'fieldsdroplist', $parentTabLevel = 0, $class = 'fieldsjumplist', false);
		pureContent::jumplistProcessor ($name);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to format a list of records as a hyperlinked list
	private function recordList ($records, $fullInfo = false)
	{
		# Table mode
		if ($fullInfo) {
			foreach ($records as $recordId => $record) {
				$link = $this->recordLink ($recordId);
				$records[$recordId]['id'] = "<a href=\"{$link}\">{$recordId}</a>";
			}
			$headings = $this->databaseConnection->getHeadings ($this->settings['database'], 'fieldsindex');
			$headings['recordId'] = '#';
			$html = application::htmlTable ($records, $headings, 'lines', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true);
			
		# List mode
		} else {
			$list = array ();
			foreach ($records as $record => $label) {
				$link = $this->recordLink ($record);
				$list[$record] = "<a href=\"{$link}\">" . htmlspecialchars ($label) . "</a>";
				$list[$record] = "<li>{$list[$record]}</li>";
			}
			$html = application::splitListItems ($list, 4);
		}
		
		# Compile the HTML
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the distinct fields
	private function getDistinctFields ()
	{
		# Get the distinct fields
		$query = "SELECT
			field AS Field, COUNT(*) AS Instances
			FROM catalogue_rawdata
			GROUP BY field
			ORDER BY field
		;";
		$data = $this->databaseConnection->getData ($query);
		
		# Reindex by field name
		$fields = array ();
		foreach ($data as $id => $field) {
			$fieldname = $field['Field'];
			$fields[$fieldname] = $field;
		}
		
		# Get the descriptions
		$descriptions = $this->getFieldDescriptions ();
		
		# Add a link to view each
		foreach ($fields as $fieldname => $field) {
			$link = $this->fieldLink ($fieldname);
			$fields[$fieldname]['Records'] = "<a href=\"{$link}\">Records: <strong>{$fieldname}</strong></a>";
			$fields[$fieldname]['Distinct values'] = "<a href=\"{$link}values/\">Distinct values: <strong>{$fieldname}</strong></a>";
		}
		
		# Merge in the descriptions
		foreach ($fields as $fieldname => $field) {
			$fields[$fieldname]['Description'] = (isSet ($descriptions[$fieldname]) ? $descriptions[$fieldname] : '?');
		}
		
		# Add commas to long numbers (which is not efficient to do in MySQL)
		foreach ($fields as $fieldname => $field) {
			$fields[$fieldname]['Instances'] = '<span class="right">' . number_format ($field['Instances']) . '</span>';
		}
		
		# Return the list
		return $fields;
	}
	
	
	# Function to get the field descriptions
	private function getFieldDescriptions ()
	{
		# Return the definitions
		return array (
			'a' => 'Author(s)',
			'abs' => 'Abstract',
			'acc' => 'Accession number',
			'acq' => 'Acquisition number',
			'ad' => "Authorial detail (usually 'and others', 'ed.', 'eds.' etc",
			'aff' => 'Affiliation',
			'ag' => 'Other affiliations',
			'al' => 'Alternative spelling of transliterated name or alternative name',
			'art' => 'Article (record type)',
			'con' => 'Condition',
			'd' => 'Date',
			'date' => 'Date of accession/acquisition (depending on context)',
			'doc' => 'Document (record type)',
			'doi' => 'DOI',
			'doifld' => 'DOI',
			'doslink' => 'DOI',
			'e' => 'Additional contributions to work (introduction, translation, editing etc.)',
			'edn' => 'Edition',
			'ee' => 'Further contributors of this specific edition',
			'form' => 'Form of item, if non-book',
			'freq' => 'Frequency of title (serials only)',
			'ft' => 'Former title',
			'fund' => 'Purchase fund ',
			'hold' => 'Current holdings (serials only)',
			'in' => '"In" larger text (i.e. Chapter "in" edited work or article "in" journal)',
			'isbn' => 'ISBN',
			'issn' => 'ISSN',
			'j' => 'Denotes start of journal information, but has no information entered directly into this field',
			'k' => 'UDC classification number',
			'k2' => 'Left blank but essential if using *kb',
			'ke' => 'Analytic record link: (redundant for processing purposes) used in host record that Muscat uses for GUI purposes to link to a lookup',
			'kf' => 'Analytic record link: (redundant for processing purposes) internal Muscat GUI representation of kg',
			'kg' => 'Analytic record link: used in analytic (child) record that is basically a join to the q0 (ID) of the host',
			'kb' => 'Details of how item is received (exchange, gift etc. Mostly applied to serials)',
			'ks' => 'UDC Keywords (hidden mirror)',
			'kw' => 'UDC Keywords',
			'lang' => 'Language type',
			'loc' => 'Location',
			'local' => 'Local note',
			'location' => 'Location (mirror of *loc)',
			'n' => 'Name field (used in conjunction with *e and *ee fields)',
			'n1' => 'Name field (used in conjunction with *e and *ee fields)',
			'n2' => 'Name field (used in conjunction with *e and *ee fields)',
			'nd' => 'Seems to denote titles such as "Jr." and "Sir". ',
			'note' => 'Note field',
			'notes' => 'Denotes start of note type field',
			'o' => 'Origin (where item came from)',
			'p' => 'Pagination',
			'pg' => 'Precedes publication details',
			'pl' => 'Place of publication',
			'pr' => 'Price',
			'priv' => 'Private note',
			'pt' => 'Part (pagination, volume etc.)',
			'pu' => 'Publisher',
			'q0' => "Muscat's unique record identifier",
			'r' => 'Range (serials only)',
			'recr' => 'Responsible bibliographer for record (bibliographer initials)',
			'ref' => 'Reference (Accession and/or aquisition number)',
			'role' => 'Additional information about contributors to work',
			'rpl' => 'PGA category',
			'ser' => 'Serial (record type)',
			'size' => 'Size of item',
			'sref' => 'Supplier reference',
			'st' => 'Subsequent title',
			'status' => 'Item status (on order/received etc.)',
			't' => 'Title',
			'ta' => 'Amendment to misspelled title (supplied by bibliographer)',
			'tc' => '[Unknown]',
			'tg' => 'Title group',
			'to' => 'Title of original (if publication is a translation)',
			'ts' => 'Title of series',
			'tt' => 'Translation of title ',
			'url' => 'URL ',
			'urlft' => 'URL mirror',
			'urlfull' => 'URL mirror',
			'urlgen' => 'URL mirror',
			'v' => 'Number of volumes',
			'vno' => 'Volume number',
			'winlink' => 'Linking field for URLs',
		);
	}
	
	
	# Function to export a report download
	public function reportdownload ($id)
	{
		# Get the data
		$query = "
			SELECT
				id AS 'Record number',
				ExtractValue(xml, '//status') AS 'Status',
				CONCAT_WS(', ', ExtractValue(xml, '*/ag/a[1]/n1'), ExtractValue(xml, '*/ag/a[1]/n2')) AS 'Author 1',
				ExtractValue(xml, '*/ag/a/*') AS 'All authors',
				ExtractValue(xml, '*/tg/tc') AS 'Title fields',
				ExtractValue(xml, '//pg[1]/pu') AS 'Publisher',
				ExtractValue(xml, '*/d[1]') AS 'Date of publication',
				ExtractValue(xml, '*/isbn[1]') AS 'ISBN',
				ExtractValue(xml, '//acq[1]/pr') AS 'Price',
				ExtractValue(xml, '//acq[1]/sref') AS 'Supplier reference',
				ExtractValue(xml, '//acq[1]/ref') AS 'Order number',
				ExtractValue(xml, '//acq[1]/o') AS 'Supplier/donor',
				ExtractValue(xml, '//acq[1]/recr') AS 'Bibliographer',
				ExtractValue(xml, '//acq[1]/date') AS 'Date (acq)',
				ExtractValue(xml, '//notes[1]/note') AS 'Note',
				ExtractValue(xml, '//notes[1]/priv') AS 'Note (private)',
				ExtractValue(xml, '//notes[1]/local') AS 'Note (local)'
				
			FROM catalogue_xml
			LEFT OUTER JOIN (
				SELECT recordId FROM reportresults WHERE report = '{$id}' ORDER BY recordId
			) AS matches
			ON catalogue_xml.id = matches.recordId
			WHERE recordId IS NOT NULL
		;";
		$data = $this->databaseConnection->getData ($query);
		
		// application::dumpData ($data);
		// die;
		
		# Convert to CSV
		require_once ('csv.php');
		csv::serve ($data, $id);
	}
	
	
	# Function to create a record listing based on a query, with pagination
	private function recordListing ($id, $query, $preparedStatementValues = array (), $baseLink, $listingIsProblemType = false, $queryString = false, $view = 'listing' /* listing/record/table/valuestable */, $tableViewTable = false, $knownTotalAvailable = false, $entityName = 'record')
	{
		# Assemble the query, determining whether to use $id or $query
		if ($id) {
			$query = "SELECT * FROM reportresults WHERE report = '{$id}' ORDER BY recordId;";
			$preparedStatementValues = array ();
		}
		
		# Enable a listing type switcher, if in a supported view mode, which can override the view
		$listingTypeSwitcherHtml = false;
		$switcherSupportedViewTypes = array ('listing', 'record');
		if (in_array ($view, $switcherSupportedViewTypes)) {
			$listingTypes = array (
				'listing'	=> 'application_view_columns',
				'record'	=> 'application_tile_vertical',
			);
			$view = application::preferenceSwitcher ($listingTypeSwitcherHtml, $listingTypes);
			
			# In record mode, use a separate pagination memory
			if ($view == 'record') {
				$this->settings['paginationRecordsPerPageDefault'] = 25;
				$this->settings['paginationRecordsPerPagePresets'] = array (5, 10, 25, 50, 100);
				$this->settings['cookieName'] = 'fullrecordsperpage';
			}
		}
		
		# Load the pagination class
		require_once ('pagination.php');
		$pagination = new pagination ($this->settings, $this->baseUrl);
		
		# Determine what page
		$page = $pagination->currentPage ();
		
		# Create a form to set the number of pagination records per page
		$paginationRecordsPerPage = $pagination->recordsPerPageForm ($recordsPerPageFormHtml);
		
		# Get the data, via pagination
		list ($dataRaw, $totalAvailable, $totalPages, $page, $actualMatchesReachedMaximum) = $this->databaseConnection->getDataViaPagination ($query, $tableViewTable, true, $preparedStatementValues, array (), $paginationRecordsPerPage, $page, false, $knownTotalAvailable);
		// application::dumpData ($this->databaseConnection->error ());
		
		# Start the HTML for the record listing
		$html  = '';
		
		# Show the listing of problematic records, or report a clean record set
		if ($listingIsProblemType) {
			if (!$dataRaw) {
				$html .= "\n<p class=\"success\">{$this->tick}" . " All {$entityName}s are correct - congratulations!</p>";
				return $html;
			} else {
				$html .= "\n<p class=\"warning\">" . '<img src="/images/icons/exclamation.png" /> The following ' . (($totalAvailable == 1) ? "{$entityName} match" : number_format ($totalAvailable) . " {$entityName}s match") . ':</p>';
			}
		} else {
			if (!$dataRaw) {
				$html .= "\n<p>There are no {$entityName}s.</p>";
			} else {
				$html .= "\n<p>" . ($totalAvailable == 1 ? "There is one {$entityName}" : 'There are ' . number_format ($totalAvailable) . " {$entityName}s") . ':</p>';
			}
		}
		
		# Add pagination links and controls
		if ($dataRaw) {
			$html .= $listingTypeSwitcherHtml;
			$html .= $recordsPerPageFormHtml;
			$paginationLinks = pagination::paginationLinks ($page, $totalPages, $this->baseUrl . $baseLink, $queryString);
			$html .= $paginationLinks;
		}
		
		# Compile the listing
		$data = array ();
		if ($dataRaw) {
			switch (true) {
				
				# List mode
				case ($view == 'listing'):
					
					# List mode needs just id=>id format
					foreach ($dataRaw as $index => $record) {
						$recordId = $record['recordId'];
						$data[$recordId] = $recordId;
					}
					$html .= $this->recordList ($data);
					$html  = "\n<div class=\"graybox\">" . $html . "\n</div>";	// Surround with a box
					break;
				
				# Record mode
				case ($view == 'record'):
					
					# Record mode shows each record
					foreach ($dataRaw as $index => $record) {
						$recordId = $record['recordId'];
						$data[$recordId] = $this->recordFieldValueTable ($recordId, 'rawdata');
						$data[$recordId] = "\n<h3>Record <a href=\"{$this->baseUrl}/records/{$recordId}/\">#{$recordId}</a>:</h3>" . "\n<div class=\"graybox\">" . $data[$recordId] . "\n</div>";	// Surround with a box
					}
					$html .= implode ($data);
					break;
					
				# Table view
				case ($view == 'table'):
					
					# Replace each label with the record title, since table view shows shows titles
					$titles = $this->getRecordTitles (array_keys ($dataRaw));
					foreach ($dataRaw as $recordId => $label) {
						$data[$recordId] = $dataRaw[$recordId];
						$data[$recordId]['title'] = $titles[$recordId];
					}
					$html .= $this->recordList ($data, true);
					$html .= $paginationLinks;	// Show the pagination links at the end again, since the page will be relatively long
					$html  = "\n<div class=\"graybox\">" . $html . "\n</div>";	// Surround with a box
					break;
					
				# Table view but showing values rather than records
				case ($view == 'valuestable'):
					
					# Generate the HTML
					$html .= $this->valuesTable ($dataRaw, false, $baseLink, false, false);
					break;
					
				# Self-defined
				case (preg_match ('/^callback\(([^)]+)\)/', $view, $matches)):	// e.g. callback(foo) will run $this->foo ($data);
					
					# Pass the data to the callback to generate the HTML
					$callbackMethod = $matches[1];
					$html .= $this->$callbackMethod ($dataRaw);
					break;
			}
		}
		
		# Surround the listing with a div for clearance purposes
		$html = "\n<div class=\"listing\">" . $html . "\n</div>";
		
		# Add the pagination controls again at the end, for long pages
		if ($view != 'listing' || count ($dataRaw) > 50) {
			$html .= $paginationLinks;
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the record titles
	private function getRecordTitles ($ids)
	{
		# Get the titles
		$data = $this->databaseConnection->selectPairs ($this->settings['database'], 'catalogue_processed', $conditions = array ('field' => 'tc', 'recordId' => $ids), $columns = array ('recordId', 'value'), true, $orderBy = 'recordId');
		return $data;
	}
	
	
	# Function to link a field
	private function fieldLink ($fieldname, $convertEntities = true)
	{
		return $this->baseUrl . '/fields/' . ($convertEntities ? htmlspecialchars ($fieldname) : $fieldname) . '/';
	}
	
	
	# Function to get stats data
	private function getStats ()
	{
		# Get the data and return it
		$data = $this->databaseConnection->selectOne ($this->settings['database'], 'statistics', array ('id' => 1));
		unset ($data['id']);
		return $data;
	}
	
	
	# Function to show stats
	public function statistics ()
	{
		# Get the data
		$data = $this->getStats ();
		
		# Apply number format
		foreach ($data as $key => $value) {
			if (ctype_digit ($value)) {
				$data[$key] = number_format ($value);
			}
		}
		
		# Compile the HTML
		$headings = $this->databaseConnection->getHeadings ($this->settings['database'], 'statistics');
		$html = application::htmlTableKeyed ($data, $headings);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to import the file, clearing any existing import
	# Needs privileges: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
	public function import ()
	{
		# Ensure the transliteration module is present
		if (!is_dir ($this->cpanDir)) {
			$html  = "\n<div class=\"graybox\">";
			$html .= "\n<p class=\"warning\">The transliteration module was not found. The Webmaster needs to ensure that {$this->cpanDir} is present.</p>";
			$html .= "\n</div>";
			echo $html;
			return true;
		}
		
		# Import files
		$importFiles = array ('muscatview', 'rawdata');
		
		# Define the import types
		$importTypes = array (
			'full'					=> 'FULL import (c. 4.25 hours)',
			'xml'					=> 'Regenerate XML only (c. 6 minutes)',
			'marc'					=> 'Regenerate MARC only (c. 65 minutes)',
			'external'				=> 'Regenerate external Voyager records only (c. ? minutes)',
			'outputstatus'			=> 'Regenerate output status only (c. 7 minutes)',
			'reports'				=> 'Regenerate reports only (c. 4 minutes)',
			'listings'				=> 'Regenerate listings reports only (c. 2 hours)',
		);
		
		# Define the introduction HTML
		$fileCreationInstructionsHtml  = "\n\t" . '<p>Open a Muscat terminal and type the following. Note that this can take a while to create.</p>';
		$fileCreationInstructionsHtml .= "\n\t" . '<p>Be aware that you may have to wait until your colleagues are not using Muscat to do an export, as exporting may lock Muscat access.</p>';
		$fileCreationInstructionsHtml .= "\n\t\t\t" . "<tt>c-extract first 00001 last 999999 to gctemp</tt><br />";
		$today = date ('Ymd');
		$fileCreationInstructionsHtml .= "\n\t\t\t" . "<tt>c-list from gctemp to muscat{$today}muscatview.txt</tt><br />";
		$fileCreationInstructionsHtml .= "\n\t\t\t" . "<tt>c-list_allfields from gctemp to muscat{$today}rawdata.txt</tt>";
		
		# Run the import UI
		$this->importUi ($importFiles, $importTypes, $fileCreationInstructionsHtml, 'txt');
	}
	
	
	# Function to do the actual import
	public function doImport ($exportFiles, $importType, &$html)
	{
		# Start the HTML
		$html = '';
		
		# Ensure that GROUP_CONCAT fields do not overflow
		$sql = "SET SESSION group_concat_max_len := @@max_allowed_packet;";		// Otherwise GROUP_CONCAT truncates the combined strings
		$this->databaseConnection->execute ($sql);
		
		# Skip the main import if required
		if ($importType == 'full') {
			
			# Add each of the two Muscat data formats
			foreach ($exportFiles as $type => $exportFile) {
				$tableComment = $this->processMuscatFile ($exportFile, $type);
			}
			
			# Create the processed table
			#  Dependencies: catalogue_rawdata
			$this->createProcessedTable ();
			
			# Parse special characters
			#   Dependencies: catalogue_processed
			$this->specialCharacterParsing ();
			
			# Perform mass data fixes
			#   Dependencies: catalogue_processed
			$this->massDataFixes ();
			
			# Create the UDC translations table
			$this->createUdcTranslationsTable ();
			
			# Finish character processing stage
			$html .= "\n<p>{$this->tick} The character processing has been done.</p>";
			
			# Create the XML table; also available as a standalone option below
			#   Depencies: catalogue_processed
			$this->createXmlTable ();
			
			# Create the fields index table
			#  Dependencies: catalogue_processed and catalogue_xml
			$this->createFieldsindexTable ();
			
			# Create the statistics table
			$this->createStatisticsTable ($tableComment);
			
			# Confirm output
			$html .= "\n<p>{$this->tick} The data has been imported.</p>";
		}
		
		# Run option to create XML table only (included in the 'full' option above) if required
		if ($importType == 'xml') {
			$this->createXmlTable ();
		}
		
		# Create the external records
		if (($importType == 'full') || ($importType == 'external')) {
			if ($this->createExternalRecords ()) {
				$html .= "\n<p>{$this->tick} The external records currently in Voyager have been imported.</p>";
			}
		}
		
		# Create the MARC records
		if (($importType == 'full') || ($importType == 'marc')) {
			if ($this->createMarcRecords ()) {
				$html .= "\n<p>{$this->tick} The MARC versions of the records have been generated.</p>";
			}
		}
		
		# Run option to set the MARC record status (included within the 'marc' (and therefore 'full') option above) if required
		if ($importType == 'outputstatus') {
			$this->marcRecordsSetStatus ();
		}
		
		# Run (pre-process) the reports
		if (($importType == 'full') || ($importType == 'reports')) {
			$this->runReports ();
			$html .= "\n<p>{$this->tick} The <a href=\"{$this->baseUrl}/reports/\">reports</a> have been generated.</p>";
		}
		
		# Run (pre-process) the reports
		if (($importType == 'full') || ($importType == 'listings')) {
			$this->runListings ();
			$html .= "\n<p>{$this->tick} The <a href=\"{$this->baseUrl}/reports/\">listings reports</a> have been generated.</p>";
		}
		
		# Signal success
		return true;
	}
	
	
	# Function to process each of the Muscat files into the database
	private function processMuscatFile ($exportFile, $type)
	{
		# Parse the file to a CSV
		$csvFilename = $this->exportsProcessingTmp . "catalogue_{$type}.csv";
		$this->parseFileToCsv ($exportFile, $csvFilename);
		
		# Insert the CSV data into the database
		$tableComment = 'Data from Muscat dated: ' . $this->dateString ($exportFile);
		$this->insertCsvToDatabase ($csvFilename, $type, $tableComment);
		
		# Add indexing for performance
		$sql = "ALTER TABLE {$this->settings['database']}.catalogue_{$type} ADD INDEX (`recordId`);";
		$this->databaseConnection->execute ($sql);
		$sql = "ALTER TABLE {$this->settings['database']}.catalogue_{$type} ADD INDEX (`field`);";
		$this->databaseConnection->execute ($sql);
		
		# Return the table comment
		return $tableComment;
	}
	
	
	# Function to get the export date as a string
	private function dateString ($exportFile)
	{
		# Determine the filename
		$basename = pathinfo ($exportFile, PATHINFO_FILENAME);
		$basename = preg_replace ('/^(muscatview|rawdata)/', '', $basename);
		$date = date_create_from_format ('Ymd', $basename);
		$string = date_format ($date, 'jS F Y');
		
		# Return the string
		return $string;
	}
	
	
	# Main parser
	private function parseFileToCsv ($exportFile, $csvFilename)
	{
		# Create the file, doing a zero-byte write to create the file; the operations which follow are appends
		file_put_contents ($csvFilename, '');
		
		# Start a string to be used to write a CSV
		$csv = '';
		
		# Start a container for the current record
		$record = array ();
		
		# Read the file, one line at a time (file_get_contents would be too inefficient for a 220MB file if converted to an array of 190,000 records)
		$handle = fopen ($exportFile, 'rb');
		$recordCounter = 0;
		while (($line = fgets ($handle, 4096)) !== false) {
			
			# If the line is empty, this signifies the end of the record, so compile and process the record
			if (!strlen (trim ($line))) {
				
				# Compile the record, adding it to the CSV string
				$csv .= $this->addRecord ($record);
				
				# Every 1,000 records, append the data to the CSV to avoid large memory usage
				$recordCounter++;
				if (($recordCounter % $this->settings['chunkEvery']) == 0) {
					file_put_contents ($csvFilename, $csv, FILE_APPEND);
					$csv = '';
				}
				
				# Reset the record container and move on
				$record = array ();
				continue;
			}
			
			# Add the line to the record
			$record[] = $line;
			
			# Break if the volume of lines has been reached
			if ($this->settings['debugMode']) {
				if ($recordCounter == $this->settings['debugMode']) {break;}
			}
		}
		
		# Compile the final record, adding it to the CSV string
		$csv .= $this->addRecord ($record);
		
		# Write the remaining CSV data and clear memory
		file_put_contents ($csvFilename, $csv, FILE_APPEND);
		unset ($csv);
		
		# Close the file being imported
		fclose ($handle);
	}
	
	
	# Function to add a record
	private function addRecord ($record)
	{
		# Combine lines
		$record = $this->trimCombineCarryoverLines ($record);
		
		# Rearrange to insertable datastructure, or end if the record is invalid (e.g. documentation records at start)
		if (!$record = $this->convertToCsv ($record)) {return false;}
		
		# Return the record
		return $record;
	}
	
	
	# Function to combine lines that contain carried-over text
	private function trimCombineCarryoverLines ($record)
	{
		# Define a line number above that we can join onto if necessary
		$lineNumberAboveJoinable = 0;
		
		# Work through each line
		foreach ($record as $lineNumber => $line) {
			
			# Determine if the line has a key. before trimming to avoid the situation of an intended carry-over string that begins with a * in the text
			$keyed = (mb_substr ($line, 0, 1) == '*');
			
			# Trim the line, which will be guaranteed not-empty after trimming
			$record[$lineNumber] = trim ($record[$lineNumber]);
			
			# Skip if the line is the '#' terminator line at the end
			#!# Need a check that it is the last line in the record
			if ($record[$lineNumber] == '#') {
				unset ($record[$lineNumber]);
				continue;
			}
			
			#!# Need to register cases where there isn't a *q0 at the start, which also ensures against -1 offsets
			
			# Join lines, separating by space, that do not begin with a key, and remove the orphaned carry-over
			if (!$keyed) {
				$record[$lineNumberAboveJoinable] .= ' ' . $record[$lineNumber];
				unset ($record[$lineNumber]);
				continue;
			}
			
			# Having confirmed a line that is retained, register this line number in case it is needed
			$lineNumberAboveJoinable = $lineNumber;
		}
		
		# Reindex the lines
		$record = array_values ($record);
		
		# Return the cleaned record
		return $record;
	}
	
	
	# Rearrange to insertable datastructure
	private function convertToCsv ($lines)
	{
		# Define the number of the first real record in the data
		$firstRealRecord = 1000;
		
		# Loop through each line, and split
		$record = array ();
		foreach ($lines as $lineNumber => $line) {
			
			# Split by the first whitespace
			preg_match ("/^\*([a-z0-9]+)\s*(.*)$/", $line, $matches);
			
			# Ensure the first line is *q0
			if ($lineNumber == 0) {
				if ($matches[1] != 'q0') {
					#!# Report the problem
					return false;
				}
				
				# Determine the ID, as *q0
				$recordId = $matches[2];
			}
			
			# Skip the documentation records (within range 1-999)
			if ($recordId < $firstRealRecord) {return false;}
			
			# Assemble the line as one of the inserts
			$record[$lineNumber] = array (
				'recordId' => $recordId,
				'line' => $lineNumber,
				'field' => $matches[1],
				'value' => $matches[2],
			);
		}
		
		# Convert to CSV
		require_once ('csv.php');
		$csv = csv::dataToCsv ($record, '', ',', array (), $includeHeaderRow = ($recordId == $firstRealRecord));
		
		# Return the CSV
		return $csv;
	}
	
	
	# Function to insert the CSV into the database
	private function insertCsvToDatabase ($csvFilename, $type, $tableComment)
	{
		# Compile the table structure
		require_once ('csv.php');
		
		/*
		# Compile the SQL
		$sql = csv::filesToSql (dirname ($csvFilename), "catalogue_{$type}.csv", $fieldLabels = array (), $tableComment, $prefix = '', $names = array (), $errorsHtml, $highMemory = false);
		if ($errorsHtml) {
			echo "\n" . application::dumpData ($errorsHtml, false, true);
			return false;
		}
		*/
		
		# Compile the SQL; this is done manually rather than using csv::filesToSql as that is slow (it has to compute the structure) and doesn't cope well with having two CSVs in the same directory with one for each table
		$sql = "
			-- {$tableComment}
			DROP TABLE IF EXISTS `catalogue_{$type}`;
			CREATE TABLE `catalogue_{$type}` (
				id INT(11) NOT NULL AUTO_INCREMENT,
				`recordId` INT(6),
				`line` INT(3),
				`field` VARCHAR(8) COLLATE utf8_unicode_ci,
				`value` TEXT,
				PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='{$tableComment}';
			
			-- Data in catalogue_{$type}.csv
			LOAD DATA LOCAL INFILE '{$this->exportsProcessingTmp}catalogue_{$type}.csv'
			INTO TABLE `catalogue_{$type}`
			FIELDS TERMINATED BY ','
			ENCLOSED BY '\"'
			ESCAPED BY '\"'
			LINES TERMINATED BY '\\n'
			IGNORE 1 LINES
			(`recordId`,`line`,`field`,`value`)
			;
		";
		
		# Save the SQL to a file
		$sqlFilename = $this->exportsProcessingTmp . "catalogue_{$type}.sql";
		file_put_contents ($sqlFilename, $sql);
		
		# Execute the SQL
		$this->databaseConnection->runSql ($this->settings, $sqlFilename, $isFile = true);
	}
	
	
	# Function to create the fields index table
	#   Dependencies: catalogue_processed and catalogue_xml
	private function createFieldsindexTable ()
	{
		# Now create the fields index table, based on the results of a query that combines them
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.fieldsindex;";
		$this->databaseConnection->execute ($sql);
		# CREATE TABLE AS ... wrongly results in a VARCHAR(344) column, resulting in record #195245 and others being truncated; length of at least VARCHAR(565) (as of 20/11/2012) is needed
		# $sql = "CREATE TABLE fieldsindex (PRIMARY KEY (id))
		# 	ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci	/* MyISAM forced, so that FULLTEXT search can be used */
		# 	AS
		# 	(SELECT
		# 		recordId AS id,
		# 		CONCAT('@', GROUP_CONCAT(`field` SEPARATOR '@'),'@') AS fieldslist
		# 	FROM {$this->settings['database']}.catalogue_processed
		# 	GROUP BY recordId
		# );";
		$sql = "CREATE TABLE fieldsindex (
			  id INT(6) NOT NULL COMMENT 'Record ID',
			  fieldslist VARCHAR(1024) NOT NULL COMMENT 'Fields list',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Summary statistics';
		";
		$this->databaseConnection->execute ($sql);
		$sql = "INSERT INTO fieldsindex (SELECT
				recordId AS id,
				CONCAT('@', GROUP_CONCAT(`field` SEPARATOR '@'),'@') AS fieldslist
			FROM {$this->settings['database']}.catalogue_processed
			GROUP BY recordId
		);";
		$this->databaseConnection->execute ($sql);
		
		# Add search fields, using cross-update technique at http://www.electrictoolbox.com/article/mysql/cross-table-update/ and http://dba.stackexchange.com/questions/21152/how-to-update-one-table-based-on-another-tables-values-on-the-fly
		$sql = "ALTER TABLE fieldsindex
			ADD title TEXT NULL COMMENT 'Title of work',
			ADD titleSortfield TEXT NULL COMMENT 'Title of work (sort index)',
			ADD region TEXT NULL COMMENT 'Region',
			ADD surname TEXT NULL COMMENT 'Author surname',
			ADD forename TEXT NULL COMMENT 'Author forename',
			ADD journaltitle TEXT NULL COMMENT 'Journal title in article records',
			ADD seriestitle TEXT NULL COMMENT 'Series title',
			ADD `year` TEXT NULL COMMENT 'Year (four digits)',
			ADD `language` TEXT NULL COMMENT 'Language',
			ADD abstract TEXT NULL COMMENT 'Abstract',
			ADD keyword TEXT NULL COMMENT 'Keyword',
			ADD isbn TEXT NULL COMMENT 'ISBN',
			ADD location TEXT NULL COMMENT 'Location',
			ADD anywhere TEXT NULL COMMENT 'Text anywhere within record',
			ADD FULLTEXT INDEX relevanceindex (anywhere)
		;";
		$this->databaseConnection->execute ($sql);
		foreach ($this->fieldsIndexFields as $field => $source) {
			if (is_null ($source)) {continue;}	// Skip fields marked as NULL
			$concatSeparator = '@';
			if ($source == 'ks') {$concatSeparator = '|';}	// Regions have '@' in them
			if ($source == 'lang') {$concatSeparator = ', ';}	// So that this is listed in report_languages_view nicely and so the search works directly from that page
			
			# Define inner select to retrieve the data, either an XPath-based lookup, or a standard field read
			if (substr_count ($source, '/')) {
				$innerSelectSql = "
					SELECT
						id,
						REPLACE( REPLACE( REPLACE( REPLACE( REPLACE( EXTRACTVALUE(xml, '{$source}'), '&amp;', '&'), '&lt;', '<'), '&gt;', '>'), '&quot;', '\"'), '&apos;', \"'\") AS value	/* Decode entities; see: https://stackoverflow.com/questions/30194976/ */
					FROM catalogue_xml
				";
			} else {
				$innerSelectSql = "
					SELECT
						recordId AS id,
						" . ($source != 'lang' ? "CONCAT('{$concatSeparator}', " : '') . "GROUP_CONCAT(value SEPARATOR '{$concatSeparator}')" . ($source != 'lang' ? ", '{$concatSeparator}')" : '') . " AS value
					FROM catalogue_processed "
					. ($source == '*' ? '' : "WHERE field = '{$source}'")
					. " GROUP BY recordId
				";
			}
			
			# Insert the values
			$sql = "
				UPDATE fieldsindex f
				INNER JOIN (
					{$innerSelectSql}
				) AS c
				ON f.id = c.id
				SET f.{$field} = c.value;";
			$this->databaseConnection->execute ($sql);
		}
		$sql = "UPDATE fieldsindex SET titleSortfield = " . $this->databaseConnection->trimSql ('title') . ';';
		$this->databaseConnection->execute ($sql);
	}
	
	
	# Function to create the processed data table
	private function createProcessedTable ()
	{
		# Now create the processed table, which will be used for amending the raw data e.g. to convert special characters
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_processed;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE catalogue_processed LIKE catalogue_rawdata;";
		$this->databaseConnection->execute ($sql);
		$sql = "INSERT INTO catalogue_processed SELECT * FROM catalogue_rawdata;";
		$this->databaseConnection->execute ($sql);
		
		# Add a field to store the original pre-transliteration value
		$sql = "ALTER TABLE catalogue_processed ADD preTransliterationUpgradeValue TEXT NULL DEFAULT NULL COMMENT 'Value before transliteration changes' AFTER value;";
		$this->databaseConnection->execute ($sql);
	}
	
	
	# Function to create the statistics table
	private function createStatisticsTable ($tableComment)
	{
		# Now create the statistics table; this is pre-compiled for performance
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.statistics;";
		$this->databaseConnection->execute ($sql);
		$sql = "
			CREATE TABLE `statistics` (
			  `id` int(1) NOT NULL COMMENT 'Identifier (always 1)',
			  `totalRecords` int(11) NOT NULL COMMENT 'Total records',
			  `totalDataEntries` int(11) NOT NULL COMMENT 'Total data entries',
			  `averageDataEntriesPerRecord` int(3) NOT NULL COMMENT 'Average data entries per record',
			  `highestNumberedRecord` int(11) NOT NULL COMMENT 'Highest-numbered record',
			  `exportDate` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Export date',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Summary statistics';";
		$this->databaseConnection->execute ($sql);
		$sql = "
			INSERT INTO statistics VALUES (
				1,
				(SELECT COUNT(DISTINCT(recordId)) FROM catalogue_rawdata),
				(SELECT COUNT(*) FROM catalogue_rawdata),
				(SELECT ROUND( (SELECT COUNT(*) FROM catalogue_rawdata) / (SELECT COUNT(DISTINCT(recordId)) FROM catalogue_rawdata) )),
				(SELECT MAX(recordId) FROM catalogue_rawdata),
				'{$tableComment}'
			)
		;";
		$this->databaseConnection->execute ($sql);
	}
	
	
	# Run parsing of special escape characters (see section 3.4 of the Library Manual); see http://lists.mysql.com/mysql/193376 for notes on backslash handling in MySQL
	private function specialCharacterParsing ()
	{
		# Start a list of queries
		$queries = array ();
		
		# Define backslash characters for clarity
		# http://lists.mysql.com/mysql/193376 : "LIKE or REGEXP pattern is parsed twice, while the REPLACE pattern is parsed once"
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		$replaceBlackslash	= $mysqlBacklash;							// http://lists.mysql.com/mysql/193376 shows that REPLACE expects a single MySQL backslash
		$likeBackslash		= $mysqlBacklash /* . $mysqlBacklash # seems to work only with one */;			// http://lists.mysql.com/mysql/193376 shows that LIKE expects a single MySQL backslash
		$regexpBackslash	= $mysqlBacklash . $mysqlBacklash;			// http://lists.mysql.com/mysql/193376
		
		# Undo Muscat escaped asterisks @*, e.g. /records/19682/ and many *ks / *location values; this is basically an SQL version of unescapeMuscatAsterisks ()
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'@*','*');";
		
		# Italics, e.g. /records/205430/
		# "In order to italicise a Latin name in the middle of a line of Roman text, prefix the words to be italicised by '\v' and end the words with '\n'"
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBlackslash}v','<em>');";
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBlackslash}n','</em>');";	// \n does not mean anything special in REPLACE()
		# Also convert \V and \N similarly
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBlackslash}V','<em>');";
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBlackslash}N','</em>');";	// \n does not mean anything special in REPLACE()
		
		# Correct the use of }o{ which has mistakenly been used to mean \gdeg, except for V}o{ which is a Ordinal indicator: https://en.wikipedia.org/wiki/Ordinal_indicator
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'}o{','{$replaceBlackslash}gdeg') WHERE value NOT LIKE '%V}o{%';";	// NB Have manually checked that record with V}o{ has no other use of }/{ characters
		
		# Diacritics (query takes 135 seconds)
		$diacritics = $this->diacriticsTable ();
		$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($diacritics, 'value', "'") . ';';
		
		# Greek characters; see also report_specialcharscase which enables the librarians to normalise \gGamMA to \gGamma
		# Assumes this catalogue rule has been eliminated: "When '\g' is followed by a word, the case of the first letter is significant. The remaining letters can be in either upper or lower case however. Thus '\gGamma' is a capital gamma, and the forms '\gGAMMA', '\gGAmma' etc. will also represent capital gamma."
		$greekLetters = $this->greekLetters ();
		$greekLettersReplacements = array ();
		foreach ($greekLetters as $letterCaseSensitive => $unicodeCharacter) {
			$greekLettersReplacements["{$replaceBlackslash}g{$letterCaseSensitive}"] = $unicodeCharacter;
		}
		$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($greekLettersReplacements, 'value', "'") . ';';
		
		# Quantity/mathematical symbols
		$specialCharacters = array (
			'deg'		=> chr(0xC2).chr(0xB0),
			'min'		=> chr(0xE2).chr(0x80).chr(0xB2),
			'sec'		=> chr(0xE2).chr(0x80).chr(0xB3),
			'<-'		=> chr(0xE2).chr(0x86).chr(0x90),		// http://www.fileformat.info/info/unicode/char/2190/index.htm
			'->'		=> chr(0xE2).chr(0x86).chr(0x92),		// http://www.fileformat.info/info/unicode/char/2192/index.htm
			'+ or -'	=> chr(0xC2).chr(0xB1),					// http://www.fileformat.info/info/unicode/char/00b1/index.htm
			'>='		=> chr(0xE2).chr(0x89).chr(0xA5),		// http://www.fileformat.info/info/unicode/char/2265/index.htm
			'<='		=> chr(0xE2).chr(0x89).chr(0xA4),		// http://www.fileformat.info/info/unicode/char/2264/index.htm
			' '			=> chr(0xC2).chr(0xA0),					// http://www.fileformat.info/info/unicode/char/00a0/index.htm
		);
		$specialCharactersReplacements = array ();
		foreach ($specialCharacters as $letter => $unicodeCharacter) {
			$specialCharactersReplacements["{$replaceBlackslash}g{$letter}"] = $unicodeCharacter;
		}
		$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($specialCharactersReplacements, 'value', "'") . ';';
		
		# Subscripts and superscripts, e.g. "H{2}SO{4} will print out as H2SO4 with both 2 and 4 as subscripts"
		$subscriptsSuperscriptsReplacements = $this->getSubscriptsSuperscriptsReplacementsDefinition ();
		$subscriptsSuperscriptsReplacementsChunks = array_chunk ($subscriptsSuperscriptsReplacements, $chunksOf = 25, true);
		foreach ($subscriptsSuperscriptsReplacementsChunks as $subscriptsSuperscriptsReplacementsChunk) {
			$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($subscriptsSuperscriptsReplacementsChunk, 'value', "'") . ';';
		}
		
		// file_put_contents ("{$_SERVER['DOCUMENT_ROOT']}{$this->baseUrl}/debug-muscat.wri", print_r ($queries, true));
		// application::dumpData ($queries);
		// die;
		
		# Run each query
		foreach ($queries as $query) {
			$result = $this->databaseConnection->query ($query);
			// application::dumpData ($this->databaseConnection->error ());
		}
	}
	
	
	# Function to create a key/value replacement pairs for subscripts {...} and superscripts }...{
	private function getSubscriptsSuperscriptsReplacementsDefinition ()
	{
		/*
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'}e{',CHAR(0xE284AF USING utf8)) WHERE `value` LIKE '%}e{%';";	// Natural exponent U+212F
		
		# Subscript: "Subscripts are entered by prefixing the number to be dropped by { and typing } after the number e.g. H{2}SO{4} will print out as H2SO4 with both 2 and 4 as subscripts."; http://en.wikipedia.org/wiki/Unicode_subscripts_and_superscripts#Superscripts_and_subscripts_block and http://www.decodeunicode.org/en/u+2072/properties
		# Superscript: "For superscripts, the procedure is reversed e.g. 4840 m}2{ will print out as 4840 m with 2 as superscript."
		// Find using: SELECT * FROM catalogue_processed WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(value,'{1}',''),'{2}',''),'{3}',''),'{4}',''),'{5}',''),'{6}',''),'{7}',''),'{8}','') REGEXP ('}([^{ ]+){') AND value NOT REGEXP ('}(1|2|-2|2-|2+|3|-3|4|5|6|-6|-8|10|11|13|14|15|18|21|22|26|32|34|36|39|41|53|85|90|137|129|210|230|226|228|234|238|241|.|~|-|\\+|a|c|B|E|o|p|ere|er|re|e\\^gre|ieme|r|me|ne|st|-1|\\\\vo\\\\n){');
		// Find using: SELECT * FROM catalogue_processed WHERE value REGEXP ('{[0-9n=()+-]+}') AND `value` NOT REGEXP ('{(0|1|2|3|4|6|7|8|10|11|12|14|15|17|18|21|22|23|25|30|31|35|37|43|45|50|60|86|90|137|200|210|238|239|240|241|500|700|0001|1010|1120|2021)}');
		for ($i = 0; $i <= 9; $i++) {
			$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{{$i}}',CHAR(0xE2828{$i} USING utf8)) WHERE `value` LIKE '%{{$i}}%';";	// Subscript
			$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'}{$i}{',CHAR(0xE281B{$i} USING utf8)) WHERE `value` LIKE '%}{$i}{%';";	// Superscript
		}
		*/
		
		# Determine the unicode code points for characters 1...9+- for both subscripts and superscripts; see: http://en.wikipedia.org/wiki/Unicode_subscripts_and_superscripts#Superscripts_and_subscripts_block
		
		# Subscripts 0-9 and then special characters
		$unicodeSubscripts = array ();
		$oneDigitNumbers = range (0, 9);
		foreach ($oneDigitNumbers as $oneDigitNumber) {
			$unicodeSubscripts[$oneDigitNumber] = chr(0xE2).chr(0x82).chr(0x80 + $oneDigitNumber);
		}
		$unicodeSubscripts['+'] = chr(0xE2).chr(0x82).chr(0x8A);
		$unicodeSubscripts['-'] = chr(0xE2).chr(0x82).chr(0x8B);
		$unicodeSubscripts['='] = chr(0xE2).chr(0x82).chr(0x8C);
		$unicodeSubscripts['('] = chr(0xE2).chr(0x82).chr(0x8D);
		$unicodeSubscripts[')'] = chr(0xE2).chr(0x82).chr(0x8E);
		$unicodeSubscripts['n'] = chr(0xE2).chr(0x82).chr(0x99);
		
		# Letter/number combinations whose component characters can be represented, or partially represented, as real Unicode
		$unicodeSubscripts['++'] = $unicodeSubscripts['+'] . $unicodeSubscripts['+'];
		$unicodeSubscripts['+++'] = $unicodeSubscripts['+'] . $unicodeSubscripts['+'] . $unicodeSubscripts['+'];
		$unicodeSubscripts['2+'] = $unicodeSubscripts[2] . $unicodeSubscripts['+'];
		$unicodeSubscripts['2-'] = $unicodeSubscripts[2] . $unicodeSubscripts['-'];
		$unicodeSubscripts['239,240'] = $unicodeSubscripts[2] . $unicodeSubscripts[8] . $unicodeSubscripts[9] . '<sub>,</sub>' . $unicodeSubscripts['2'] . $unicodeSubscripts['4'] . $unicodeSubscripts['0'];
		$unicodeSubscripts['1c'] = $unicodeSubscripts[1] . '<sub>c</sub>';	// e.g. /records/81582/
		$unicodeSubscripts['2d'] = $unicodeSubscripts[2] . '<sub>d</sub>';
		$unicodeSubscripts['O2'] = '<sub>O</sub>' . $unicodeSubscripts[2];
		$unicodeSubscripts['16:0'] = $unicodeSubscripts[1] . $unicodeSubscripts[6] . '<sub>:</sub>' . $unicodeSubscripts[0];
		$unicodeSubscripts['22:6'] = $unicodeSubscripts[2] . $unicodeSubscripts[2] . '<sub>:</sub>' . $unicodeSubscripts[6];
		$unicodeSubscripts['31:9'] = $unicodeSubscripts[3] . $unicodeSubscripts[1] . '<sub>:</sub>' . $unicodeSubscripts[9];
		$unicodeSubscripts['31:9'] = $unicodeSubscripts[0] . '<sub>.</sub>' . $unicodeSubscripts[1] . $unicodeSubscripts[2] . $unicodeSubscripts[0];
		$unicodeSubscripts['10T0'] = $unicodeSubscripts[1] . $unicodeSubscripts[0] . '<sub>T</sub>' . $unicodeSubscripts[0];
		$unicodeSubscripts['14-15'] = $unicodeSubscripts[1] . $unicodeSubscripts[4] . $unicodeSubscripts['-'] . $unicodeSubscripts[1] . $unicodeSubscripts[5];
		$unicodeSubscripts['16-17'] = $unicodeSubscripts[1] . $unicodeSubscripts[6] . $unicodeSubscripts['-'] . $unicodeSubscripts[1] . $unicodeSubscripts[7];
		$unicodeSubscripts['20-22'] = $unicodeSubscripts[2] . $unicodeSubscripts[0] . $unicodeSubscripts['-'] . $unicodeSubscripts[2] . $unicodeSubscripts[2];
		$unicodeSubscripts['1/2'] = $unicodeSubscripts[1] . '<sub>/</sub>' . $unicodeSubscripts[2];
		$unicodeSubscripts['GISP2'] = '<sub>GISP</sub>' . $unicodeSubscripts[2];
		$unicodeSubscripts['s=0'] = '<sub>s=</sub>' . $unicodeSubscripts[0];
		$unicodeSubscripts['1g'] = $unicodeSubscripts[1] . '<sub>g</sub>';
		$unicodeSubscripts['0.120'] = $unicodeSubscripts[0] . '<sub>.</sub>' . $unicodeSubscripts[1] . $unicodeSubscripts[2] . $unicodeSubscripts[0];
		$unicodeSubscripts['H+'] = '<sub>H</sub>' . $unicodeSubscripts['+'];
		$unicodeSubscripts['Na+'] = '<sub>Na</sub>' . $unicodeSubscripts['+'];
		$unicodeSubscripts['8-x'] = $unicodeSubscripts[8] . $unicodeSubscripts['-'] . '<sub>x</sub>';
		$unicodeSubscripts['18-x'] = $unicodeSubscripts[1] . $unicodeSubscripts[8] . $unicodeSubscripts['-'] . '<sub>x</sub>';
		$unicodeSubscripts['2+x'] = $unicodeSubscripts[2] . $unicodeSubscripts['+'] . '<sub>x</sub>';
		$unicodeSubscripts['2<em>n</em>+1'] = $unicodeSubscripts[2] . '<em>' . $unicodeSubscripts['n'] . '</em>' . $unicodeSubscripts['+'] . $unicodeSubscripts[1];	// /records/129835/
		$unicodeSubscripts['CO3'] = '<sub>CO</sub>' . $unicodeSubscripts[3];	// /records/127819/
		
		# Subscripts not representable as real Unicode codepoints, e.g. shown as {h}, represented as HTML
		$subscriptsNonUnicodeable = array ('a', 'A', 'adv', 'an', 'apex', 'A' . chr(0xCE).chr(0xA3), 'b', 'B', 'c', 'C', 'CO', 'd', 'D', 'DN', 'DR', 'dry', 'e', 'E', 'eff', 'eq', 'ex', 'f', 'g', 'h', 'H', 'hfa', 'HOMA', 'i', 'I', 'II', 'IIIC', 'ice', 'Ic', 'IC', 'inorg', 'Jan', 'k', 'l', 'L', 'lip', 'm', 'max', 'MAX', 'min', 'Nd', 'o', 'org', 'p', 'Pb', 'p/c', 'PAR', 'POC', 'q', 'Q', 'r', 'R', 'RC', 'Re', 'rs', 's', 'S', 'sal', 'sant', 'sas', 'SL', 'ST', 'St', 'SW', 't', 'T', 'u', 'v', 'VGT', 'VI', 'w', 'W', 'w.e', 'x', 'X', 'xs', 'y', 'z', 'Z', chr(0xCE).chr(0xB4), chr(0xCE).chr(0xB6), chr(0xCE).chr(0xB8), chr(0xCE).chr(0x94) . '<em>t</em>' /* delta, for /records/78099/ */ , 'f,T=O', chr(0xCE).chr(0xB8), chr(0xCE).chr(0xB7), );	$this->databaseConnection->query ("INSERT INTO catalogue_processed VALUES (NULL, 9189, 35, 'notes', '', NULL), (NULL, 9189, 36, 'note', 'Have you not heard that the bird is the word?', NULL);");
		foreach ($subscriptsNonUnicodeable as $subscriptNonUnicodeable) {
			$unicodeSubscripts[$subscriptNonUnicodeable] = '<sub>' . $subscriptNonUnicodeable . '</sub>';	// HTML tags will be stripped in final record
		}
		
		# Superscripts: more awkward than subscripts as code points include three ASCII-position characters; see: http://en.wikipedia.org/wiki/Unicode_subscripts_and_superscripts#Superscripts_and_subscripts_block
		$unicodeSuperscripts = array ();
		foreach ($oneDigitNumbers as $oneDigitNumber) {
			switch ($oneDigitNumber) {
				case 1:
					$unicodeSuperscripts[$oneDigitNumber] = chr(0xC2).chr(0xB9);
					break;
				case 2:
				case 3:
					$unicodeSuperscripts[$oneDigitNumber] = chr(0xC2).chr(0xB0 + $oneDigitNumber);
					break;
				default:
					$unicodeSuperscripts[$oneDigitNumber] = chr(0xE2).chr(0x81).chr(0xB0 + $oneDigitNumber);
			}
		}
		$unicodeSuperscripts['+'] = chr(0xE2).chr(0x81).chr(0xBA);
		$unicodeSuperscripts['-'] = chr(0xE2).chr(0x81).chr(0xBB);
		$unicodeSuperscripts['='] = chr(0xE2).chr(0x81).chr(0xBC);
		$unicodeSuperscripts['('] = chr(0xE2).chr(0x81).chr(0xBD);
		$unicodeSuperscripts[')'] = chr(0xE2).chr(0x81).chr(0xBE);
		$unicodeSuperscripts['n'] = chr(0xE2).chr(0x81).chr(0xBF);
		$unicodeSuperscripts['.'] = chr(0xC2).chr(0xB7);	// Middle dot
		$unicodeSuperscripts['TM'] = chr(0xE2).chr(0x84).chr(0xA2);	// Trademark
		
		# Letter/number combinations whose component characters can be represented as real Unicode
		$unicodeSuperscripts['2+'] = $unicodeSuperscripts[2] . $unicodeSuperscripts['+'];
		$unicodeSuperscripts['2-'] = $unicodeSuperscripts[2] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['3+'] = $unicodeSuperscripts[3] . $unicodeSuperscripts['+'];
		$unicodeSuperscripts['3-'] = $unicodeSuperscripts[3] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['-n'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts['n'];
		$unicodeSuperscripts['++'] = $unicodeSuperscripts['+'] . $unicodeSuperscripts['+'];	// e.g. /records/79712/
		$unicodeSuperscripts['+++'] = $unicodeSuperscripts['+'] . $unicodeSuperscripts['+'] . $unicodeSuperscripts['+'];	// e.g. /records/111029/
		$unicodeSuperscripts['1/3'] = $unicodeSuperscripts[1] . '<sup>/</sup>' . $unicodeSuperscripts[3];	// e.g. /records/169424/
		$unicodeSuperscripts['-1/12'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[1] . '<sup>/</sup>' . $unicodeSuperscripts[1] . $unicodeSuperscripts[2];	// e.g. /records/120554/
		$unicodeSuperscripts['4.17'] = $unicodeSuperscripts[4] . '<sup>.</sup>' . $unicodeSuperscripts[1] . $unicodeSuperscripts[7];	// e.g. /records/199372/
		$unicodeSuperscripts['238-240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[8] . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];	// e.g. /records/212674/
		$unicodeSuperscripts['239+240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[9] . $unicodeSuperscripts['+'] . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];	// e.g. /records/206908/
		$unicodeSuperscripts['239,240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[9] . '<sup>,</sup>' . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];	// e.g. /records/167346/
		$unicodeSuperscripts['239.240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[9] . '<sup>.</sup>' . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];
		$unicodeSuperscripts['-1h-1'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[1] . '<sup>h</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['2s-1'] = $unicodeSuperscripts[2] . '<sup>s</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['-2s-1'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . '<sup>s</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['3y-1'] = $unicodeSuperscripts[3] . '<sup>y</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['16:0'] = $unicodeSuperscripts[1] . $unicodeSuperscripts[6] . '<sup>:</sup>' . $unicodeSuperscripts[0];
		$unicodeSuperscripts['22:6'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[2] . '<sup>:</sup>' . $unicodeSuperscripts[6];
		$unicodeSuperscripts['-1.6'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[1] . '<sup>.</sup>' . $unicodeSuperscripts[6];
		$unicodeSuperscripts['1.2'] = $unicodeSuperscripts[1] . '<sup>.</sup>' . $unicodeSuperscripts[2];
		$unicodeSuperscripts['12-'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[2] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['8-'] = $unicodeSuperscripts[8] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['1/2'] = $unicodeSuperscripts[1] . '<sup>/</sup>' . $unicodeSuperscripts[2];
		$unicodeSuperscripts['-0.75'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[0] . '<sup>.</sup>' . $unicodeSuperscripts[7] . $unicodeSuperscripts[5];
		$unicodeSuperscripts['110m'] = $unicodeSuperscripts[1] . $unicodeSuperscripts[1] . $unicodeSuperscripts[1] . $unicodeSuperscripts[0] . '<sup>m</sup>';
		$unicodeSuperscripts['1.095'] = $unicodeSuperscripts[1] . '<sup>.</sup>' . $unicodeSuperscripts[0] . $unicodeSuperscripts[9] . $unicodeSuperscripts[5];
		$unicodeSuperscripts['-0.0131 ' . chr(0xcf).chr(0x81) . 's'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[0] . '<sup>.</sup>' . $unicodeSuperscripts[0] . $unicodeSuperscripts[1] . $unicodeSuperscripts[3] . $unicodeSuperscripts[1] . '<sup> ' . chr(0xcf).chr(0x81) . 's</sup>';	// /records/193112/
		
		# Ordinal indicators; NB only a and o have proper Unicode characters: https://en.wikipedia.org/wiki/Ordinal_indicator#Usage
		$unicodeSuperscripts['a'] = chr(0xC2).chr(0xAA);	// FEMININE ORDINAL INDICATOR (U+00AA); see: http://www.fileformat.info/info/unicode/char/00aa/index.htm
		$unicodeSuperscripts['o'] = chr(0xC2).chr(0xBA);	// MASCULINE ORDINAL INDICATOR (U+00BA); see: http://www.fileformat.info/info/unicode/char/00ba/index.htm
		
		# Superscripts with no Unicode codepoints, represented as HTML
		$superscriptsNonUnicodeable = array ('b', 'B', 'c', 'dry', 'DN', 'e', 'E', 'eme', 'er', chr(0xC3).chr(0xA8) . 're' /* ère */, 'ieme', 'l', 'max', 'me', 'ne', 'p', 'r', 'R', 're', 'st', 't', 'T', 'Th', 'th', 'tot', '~', ',', chr(0xC3).chr(0xB2), );		// E.g. shown as }e{
		foreach ($superscriptsNonUnicodeable as $superscriptNonUnicodeable) {
			$unicodeSuperscripts[$superscriptNonUnicodeable] = '<sup>' . $superscriptNonUnicodeable . '</sup>';	// HTML tags will be stripped in final record
		}
		
		# Define subscripts and superscripts known to be in the data, e.g. {+}, {-}, }+{, }-{, etc.; all characters in these listings must have been defined above; the two groupings will be processed in paired order
		$groupings = array (
			array (		// 0-9, +, -, n, etc.
				array_keys ($unicodeSubscripts),
				array_keys ($unicodeSuperscripts)
			),
			array (
				$subscriptsNonUnicodeable,
				$superscriptsNonUnicodeable
			),
			array (
				array (),
				range (10, 99)
			),
			array (
				range (-99, -1),
				range (-99, -1)
			),
			array (
				array ('10', '11', '12', '13', '14', '15', '16', '17', '18', '20', '21', '22', '23', '25', '26', '27', '28', '29', '30', '31', '33', '35', '37', '40', '43', '45', '50', '60', '63', '64', '86', '90', '115', '128', '137', '200', '210', '238', '241', '500', '700', '0001', '1010', '1120', '2021', ),
				array ('103', '118', '125', '127', '129', '134', '137', '143', '144', '181', '187', '188', '204', '206', '207', '210', '222', '226', '228', '230', '231', '232', '234', '235', '238', '239', '240', '241', '548', '552', '990', )
			),
		);
		
		# Assemble key/value pairs of search=>replace, e.g. {+} => +, ordered by grouping
		$replacements = array ();
		foreach ($groupings as $grouping) {
			foreach ($grouping[0] as $subscript) {
				$find = '{' . $subscript . '}';
				$replacements[$find] = strtr ($subscript, $unicodeSubscripts);
			}
			foreach ($grouping[1] as $superscript) {
				$find = '}' . $superscript . '{';
				$replacements[$find] = strtr ($superscript, $unicodeSuperscripts);	// Definition of 0-9 will also catch 10-99
			}
		}
		
		# Add ambiguous groupings arising from the lack of robust reversibility in the original {..} }..{ specification; these take precedence over all the above
		$ambiguousGroupings['}.{H}+{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/129785/
		$ambiguousGroupings['10}-2{s}-1{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/129785/
		$ambiguousGroupings['10}-6{S}-1{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[6] . 'S' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/136780/
		$ambiguousGroupings['10}-6{-10}-7{s}-1{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[6] . '-10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[7] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/155417/
		$ambiguousGroupings['m}-2{a}-1{'] = 'm' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'a' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/167154/
		$ambiguousGroupings['m}-2{d}-1{'] = 'm' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'd' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/174762/
		$ambiguousGroupings['L}-1{h}-1{'] = 'L' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'h' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/186659/
		$ambiguousGroupings['m}-2{s}-1{'] = 'm' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'g' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/188187/
		$ambiguousGroupings['m}2{g}-1{'] = 'm' . $unicodeSuperscripts[2] . 'g' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/188222/
		$ambiguousGroupings['m}2{s}-1{'] = 'm' . $unicodeSuperscripts[2] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/189759/
		$ambiguousGroupings['O}+{(}2{P'] = 'O' . $unicodeSuperscripts['+'] . '(' . $unicodeSuperscripts[2] . 'P';	// /records/124061/
		$ambiguousGroupings['km}3{y}-1{'] = 'km' . $unicodeSuperscripts['3'] . 'y' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/193483/
		$replacements = array_merge ($ambiguousGroupings, $replacements);	// Insert before all others
		
		// application::dumpData ($replacements);
		
		# Return the replacements
		return $replacements;
	}
	
	
	# Lookup table for diacritics
	private function diacriticsTable ()
	{
		# Diacritics; see also report_diacritics() and report_diacritics_view(); most are defined at http://www.ssec.wisc.edu/~tomw/java/unicode.html and this is useful: http://illegalargumentexception.blogspot.co.uk/2009/09/java-character-inspector-application.html
		$diacritics = array (
			
			// ^a acute
			'a^a' => chr(0xc3).chr(0xa1),			//  0x00E1
			'c^a' => chr(0xc4).chr(0x87),			//  0x0107
			'e^a' => chr(0xc3).chr(0xa9),			//  0x00E9
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
			' ^t' => ' ~',
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
			'O^Z' => $diacritics['O^z'],
			
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
	
	
	# Lookup table for greek letters
	private function greekLetters ()
	{
		# Greek letters \g___; Unicode references from http://www.utf8-chartable.de/unicode-utf8-table.pl?start=896&number=128
		$greekLetters = array (
			'alpha'				=> chr(0xce).chr(0xb1),
			'beta'				=> chr(0xce).chr(0xb2),
			'gamma'				=> chr(0xce).chr(0xb3),
			'delta'				=> chr(0xce).chr(0xb4),
			'epsilon'			=> chr(0xce).chr(0xb5),
			'zeta'				=> chr(0xce).chr(0xb6),
			'eta'				=> chr(0xce).chr(0xb7),
			'theta'				=> chr(0xce).chr(0xb8),
			'iota'				=> chr(0xce).chr(0xb9),
			'kappa'				=> chr(0xce).chr(0xba),
			'lambda'			=> chr(0xce).chr(0xbb),
			'mu'				=> chr(0xce).chr(0xbc),
			'nu'				=> chr(0xce).chr(0xbd),
			'xi'				=> chr(0xce).chr(0xbe),
			'omicron'			=> chr(0xce).chr(0xbf),
			'pi'				=> chr(0xcf).chr(0x80),
			'rho'				=> chr(0xcf).chr(0x81),
			'sigma'				=> chr(0xcf).chr(0x83),
			'tau'				=> chr(0xcf).chr(0x84),
			'upsilon'			=> chr(0xcf).chr(0x85),
			'phi'				=> chr(0xcf).chr(0x86),
			'chi'				=> chr(0xcf).chr(0x87),
			'psi'				=> chr(0xcf).chr(0x88),
			'omega'				=> chr(0xcf).chr(0x89),
			'Alpha'				=> chr(0xce).chr(0x91),
			'Beta'				=> chr(0xce).chr(0x92),
			'Gamma'				=> chr(0xce).chr(0x93),
			'Delta'				=> chr(0xce).chr(0x94),
			'Epsilon'			=> chr(0xce).chr(0x95),
			'Zeta'				=> chr(0xce).chr(0x96),
			'Eta'				=> chr(0xce).chr(0x97),
			'Theta'				=> chr(0xce).chr(0x98),
			'Iota'				=> chr(0xce).chr(0x99),
			'Kappa'				=> chr(0xce).chr(0x9a),
			'Lambda'			=> chr(0xce).chr(0x9b),
			'Mu'				=> chr(0xce).chr(0x9c),
			'Nu'				=> chr(0xce).chr(0x9d),
			'Xi'				=> chr(0xce).chr(0x9e),
			'Omicron'			=> chr(0xce).chr(0x9f),
			'Pi'				=> chr(0xce).chr(0xa0),
			'Rho'				=> chr(0xce).chr(0xa1),
			'Sigma'				=> chr(0xce).chr(0xa3),
			'Tau'				=> chr(0xce).chr(0xa4),
			'Upsilon'			=> chr(0xce).chr(0xa5),
			'Phi'				=> chr(0xce).chr(0xa6),
			'Chi'				=> chr(0xce).chr(0xa7),
			'Psi'				=> chr(0xce).chr(0xa8),
			'Omega'				=> chr(0xce).chr(0xa9),
		);
		
		# Return the array
		return $greekLetters;
	}
	
	
	# Function to fix up data en-masse
	private function massDataFixes ()
	{
		# Remove "pub. " and "pub." from *loc
		$queries[] = "UPDATE `catalogue_processed` SET value = REPLACE(value, 'pub. ', '') WHERE field = 'location';";	// # 304 rows
		$queries[] = "UPDATE `catalogue_processed` SET value = REPLACE(value, 'pub.', '') WHERE field = 'location';";	// # 13927 rows
		
		# Run each query
		foreach ($queries as $query) {
			$result = $this->databaseConnection->query ($query);
			// application::dumpData ($this->databaseConnection->error ());
		}
	}
	
	
	# Function to initialise the transliteration table; takes about 3 seconds to run
	#   Depencies: catalogue_processed, catalogue_xml
	private function initialiseTransliterationsTable ()
	{
		# Create the table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.transliterations;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `transliterations` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`shardId` INT(11) NULL COMMENT 'Processed shard ID (catalogue_processed.id)',
			`recordId` INT(11) NULL COMMENT 'Record ID',
			`field` VARCHAR(255) NULL COMMENT 'Field',
			`title` TEXT COLLATE utf8_unicode_ci NOT NULL COMMENT 'Reverse-transliterated title',
			`title_latin` TEXT COLLATE utf8_unicode_ci COMMENT 'Title (latin characters), unmodified from original data',
			`title_latin_tt` TEXT COLLATE utf8_unicode_ci COMMENT '*tt if present',
			`title_forward` TEXT COLLATE utf8_unicode_ci COMMENT 'Forward transliteration from generated Cyrillic (BGN/PCGN)',
			`forwardCheckFailed` INT(1) NULL COMMENT 'Forward check failed?',
			`title_loc` TEXT COLLATE utf8_unicode_ci COMMENT 'Forward transliteration from generated Cyrillic (Library of Congress)',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of transliterations'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Define supported language
		#!# Need to remove explicit dependency
		$language = 'Russian';
		
		# Get the titles from record IDs whose language is transliterable
		#!# This currently catches records where any *lang is Russian rather than the first
		#!# What happens with titles within a *t block?
		$query = "
			INSERT INTO transliterations (recordId, field, title_latin, title_latin_tt)
				SELECT
					id AS recordId,
					't' AS field,
					REPLACE( REPLACE( REPLACE( REPLACE( REPLACE( EXTRACTVALUE(xml, '*/tg/t')   , '&amp;', '&'), '&lt;', '<'), '&gt;', '>'), '&quot;', '\"'), '&apos;', \"'\")
						AS title_latin,
					REPLACE( REPLACE( REPLACE( REPLACE( REPLACE( EXTRACTVALUE(xml, '*/tg/tt')   , '&amp;', '&'), '&lt;', '<'), '&gt;', '>'), '&quot;', '\"'), '&apos;', \"'\")
						AS title_latin_tt
				FROM catalogue_xml
				WHERE
					id IN (
						SELECT recordId FROM catalogue_processed WHERE field = 'lang' AND value = '{$language}'
					)
		;";
		$data = $this->databaseConnection->query ($query);
		
		# Add in the shard IDs by matching the hierarchically-obtained transliterations.title_latin values with the values in the processed table; this is the only way to ensure that top-level *t values are obtained
		#!# Need to ensure there are no failures left, once /reports/failinglocupgrade/ is clear following fixing of /reports/missingt/ , /reports/emptytvariants/ , /reports/multipletopt/
		$query = "
			UPDATE transliterations
			LEFT JOIN catalogue_processed ON
				    transliterations.recordId = catalogue_processed.recordId
				AND catalogue_processed.field = 't'
				AND transliterations.title_latin = catalogue_processed.value
			SET shardId = catalogue_processed.id
		;";
		$data = $this->databaseConnection->query ($query);
		
		# Trigger a transliteration run
		$this->transliterateTransliterationsTable ();
	}
	
	
	# Function to run the transliterations in the transliteration table; this never alters title_latin which should be set only in initialiseTransliterationsTable, read from the post- second-pass XML records
	private function transliterateTransliterationsTable ()
	{
		# Obtain the raw values
		$data = $this->databaseConnection->selectPairs ($this->settings['database'], 'transliterations', array (), array ('id', 'title_latin'));
		
		# Transliterate the strings; this has to be done manually because the batcher is not safe for protected strings
		#!# The same issue about crosstalk in unsafe batching presumably applies to line-by-line conversions, i.e. C (etc.) will get translated later in the same line; need to check on this
		$language = 'Russian';
		$dataTransliterated = array ();
		foreach ($data as $id => $string) {
			$dataTransliterated[$id] = $this->transliterateBgnLatinToCyrillic ($string, $language);
		}
		
		# Do a comparison check by forward-transliterating the generated Cyrillic
		$forwardBgnTransliterations = $this->batchTransliterateStrings ($dataTransliterated, 'transliterateCyrillicToBgnLatin');
		
		# Add new Library of Congress (LoC) transliteration from the generated Cyrillic
		$forwardLocTransliterations = $this->batchTransliterateStrings ($dataTransliterated, 'transliterateCyrillicToLocLatin');
		
		# Compile the conversions
		$conversions = array ();
		foreach ($data as $id => $string) {
			$conversions[$id] = array (
				'title'					=> $dataTransliterated[$id],
				'title_forward'			=> $forwardBgnTransliterations[$id],
				'forwardCheckFailed'	=> (strtolower ($string) != strtolower ($forwardBgnTransliterations[$id]) ? 1 : NULL),	// Case-insensitive comparison pending upstream fix on http://unicode.org/cldr/trac/ticket/9316
				'title_loc'				=> $forwardLocTransliterations[$id],
			);
		}
		
		# Insert the data
		$this->databaseConnection->updateMany ($this->settings['database'], 'transliterations', $conversions, $chunking = 5000);
		
		# Signal success
		return true;
	}
	
	
	# Function to batch-transliterate an array strings; this is basically a wrapper which packages the strings list as a TSV which is then transliterated as a single string, then sent back unpacked
	# Datasets involving protected strings must not be batched, i.e. must not use this, because if e.g. line X contains e.g. Roman numeral 'C' and line Y contains 'Chukotke' this will result in replacements like: '<||1267||>hukotke', i.e. cross-talk between the lines
	private function batchTransliterateStrings ($strings, $transliterationFunction)
	{
		# Define supported language
		#!# Need to remove explicit dependency
		$language = 'Russian';
		
		# Compile the strings to a TSV string, tabs never appearing in the original data, so it is safe to use \t as the separator
		$tsv = array ();
		foreach ($strings as $id => $string) {
			$tsv[] = $id . "\t" . $string;
		}
		$tsv = implode ("\n", $tsv);
		
		# Reverse-transliterate the whole file
		$tsvTransliteratedRaw = $this->{$transliterationFunction} ($tsv, $language);
		
		# Convert back to key-value pairs
		require_once ('csv.php');
		$tsvTransliteratedRaw = 'id' . "\t" . 'string' . "\n" . $tsvTransliteratedRaw;		// Add header row for csv::tsvToArray()
		$dataTransliterated = csv::tsvToArray ($tsvTransliteratedRaw, true);
		foreach ($dataTransliterated as $id => $subArray) {
			$dataTransliterated[$id] = $subArray['string'];	// Flatten the array
		}
		
		# Return the transliterated data
		return $dataTransliterated;
	}
	
	
	# Function to reverse-transliterate a string from BGN/PCGN latin to Cyrillic
	/*
		Files are at
		/root/.cpan/build/Lingua-Translit-0.22-th0SPW/xml/
		
		Documentation at
		http://www.lingua-systems.com/translit/downloads/lingua-translit-developer-manual-eng.pdf
		
		XML transliteration file:
		/transliteration/bgn_pcgn_1947.xml
		
		Instructions for root instanll:
		Make changes to the XML file then run, as root:
		cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/xml/ && make all-tables && cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/ && make clean && perl Makefile.PL && make && make install
		
		Lingua Translit documentation:
		http://www.lingua-systems.com/translit/downloads/lingua-translit-developer-manual-eng.pdf
		http://search.cpan.org/~alinke/Lingua-Translit-0.22/lib/Lingua/Translit.pm#ADDING_NEW_TRANSLITERATIONS
		
		# Example use:
		echo "hello" | translit -r -t "BGN PCGN 1947"
	*/
	public function transliterateBgnLatinToCyrillic ($string, $language)
	{
		# Ensure language is supported
		if (!isSet ($this->supportedReverseTransliterationLanguages[$language])) {return $string;}
		
		# Protect string portions (e.g. English language, HTML portions) prior to transliteration
		$string = $this->protectSubstrings ($string, $protectedParts);
		
		/* Note:
		 * Ideally we would use:
		 *   $t = Transliterator::create("Russian-Latin/BGN", Transliterator::REVERSE);
		 *   $reverseTransliteration = $t->transliterate ($string);
		 * which uses Unicode CLDR
		 * See: http://www.larryullman.com/2012/02/01/transliteration-in-php-5-4/
		 * Unfortunately, http://cldr.unicode.org/index/cldr-spec/transliteration-guidelines states:
		 * "Unicode CLDR provides other transliterations based on the U.S. Board on Geographic Names (BGN) transliterations. These are currently unidirectional — to Latin only. The goal is to make them bidirectional in future versions of CLDR."
		 * and the current implementation of Russiah-Latin/BGN only has 'direction="forward"':
		 * http://unicode.org/cldr/trac/browser/trunk/common/transforms/Russian-Latin-BGN.xml
		 * Ticket raised at: http://unicode.org/cldr/trac/ticket/9086
		 */
		
		# Perform transliteration
		$command = "{$this->cpanDir}/bin/translit -trans '{$this->supportedReverseTransliterationLanguages[$language]}'";	//  --reverse
		$reverseTransliteration = application::createProcess ($command, $string);
		
		# Reinstate protected substrings
		$reverseTransliteration = strtr ($reverseTransliteration, $protectedParts);
		
		# Return the transliteration
		return $reverseTransliteration;
	}
	
	
	# Function to transliterate from Cyrillic to BGN/PCGN latin
	# See: https://www.gov.uk/government/uploads/system/uploads/attachment_data/file/501620/ROMANIZATION_SYSTEM_FOR_RUSSIAN.pdf and earlier edition http://web.archive.org/web/20151005154715/https://www.gov.uk/government/uploads/system/uploads/attachment_data/file/320274/Russian_Romanisation.pdf
	private function transliterateCyrillicToBgnLatin ($cyrillic)
	{
		# Use the built-in transliterator
		$forwardBgnTransliterations = transliterator_transliterate ('Russian-Latin/BGN', $cyrillic);
		
		//# Experimental change to use the custom-written BGN PCGN 1947 transliteration but in reverse; doesn't work due to ambiguity; see: http://www.lingua-systems.com/translit/manuals-api.html
		//$command = "{$this->cpanDir}/bin/translit -trans 'BGN PCGN 1947' --reverse";
		//$forwardBgnTransliterations = application::createProcess ($command, $cyrillic);
		
		# Convert soft-sign/hard-sign to their simpler representations in Muscat
		$muscatRepresentations = array (
			chr(0xCA).chr(0xB9) => "'",		// Soft sign -> Muscat quote
			chr(0xCA).chr(0xBA) => "''",	// Hard sign -> Muscat double quote
		);
		$forwardBgnTransliterations = strtr ($forwardBgnTransliterations, $muscatRepresentations);
		
		# Return the data
		return $forwardBgnTransliterations;
	}
	
	
	# Function to transliterate from Library of Congress (ALA LC) to Cyrillic; see: https://www.loc.gov/catdir/cpso/romanization/russian.pdf
	public function transliterateLocLatinToCyrillic ($locLatin)
	{
		# Load the Library of Congress transliterations definition, copied from https://github.com/umpirsky/Transliterator/blob/master/src/Transliterator/data/ru/ALA_LC.php
		if (!isSet ($this->locTransliterationDefinition)) {
			$this->locTransliterationDefinition = require_once ('tables/ALA_LC.php');
		}
		
		# Protect string portions (e.g. English language, HTML portions) prior to transliteration
		$locLatin = $this->protectSubstrings ($locLatin, $protectedParts);
		
		# Transliterate and return
		$reverseTransliteration = str_replace ($this->locTransliterationDefinition['lat'], $this->locTransliterationDefinition['cyr'], $locLatin);
		
		# Reinstate protected substrings
		$reverseTransliteration = strtr ($reverseTransliteration, $protectedParts);
		
		# Return the transliteration
		return $reverseTransliteration;
	}
	
	
	# Function to transliterate from Cyrillic to Library of Congress (ALA LC); see: https://www.loc.gov/catdir/cpso/romanization/russian.pdf
	private function transliterateCyrillicToLocLatin ($cyrillic)
	{
		# Load the Library of Congress transliterations definition, copied from https://github.com/umpirsky/Transliterator/blob/master/src/Transliterator/data/ru/ALA_LC.php
		if (!isSet ($this->locTransliterationDefinition)) {
			$this->locTransliterationDefinition = require_once ('tables/ALA_LC.php');
		}
		
		# Transliterate and return
		return str_replace ($this->locTransliterationDefinition['cyr'], $this->locTransliterationDefinition['lat'], $cyrillic);
	}
	
	
	# Function to protect string portions (e.g. English language, HTML portions) prior to transliteration; can be undone with a simple strtr()
	private function protectSubstrings ($string, &$protectedParts)
	{
		# Start an array of replacements
		$replacements = array ();
		
		# Protect parts in italics, which are Latin names that a publisher would not translate
		preg_match_all ('|(<em>.+</em>)|uU', $string, $italicisedNameMatches);		// Uses /U ungreedy, to avoid "a <em>b</em> c <em>d</em> e" becoming "a  e"
		$replacements = array_merge ($replacements, $italicisedNameMatches[1]);
		
		# Protect known strings to protect from transliteration (Latin abbreviations and Order names)
		$replacements = array_merge ($replacements, $this->transliterationProtectedStrings ());
		
		# Add in HTML tags for protection; in theory all <em> and </em> tags will have been swallowed already
		$tags = array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>', );
		$replacements = array_merge ($replacements, $tags);
		
		# Create dynamic replacements
		foreach ($replacements as $index => $matchString) {
			if (preg_match ('|^/.+/i?$|', $matchString)) {	// e.g. a pattern /(X-XI)/i against string 'Foo X-Xi Bar' would add 'X-Xi' to the replacements list
				unset ($replacements[$index]);	// Remove the pattern itself from the replacement list, as it should not be treated as a literal
				
				# Create a test string based on the string (but do not modify the test itself); this doubles-up any spaces, so that preg_match_all can match adjacent matches (e.g. see /records/120782/ ) due to "After the first match is found, the subsequent searches are continued on from end of the last match."
				$testString = preg_replace ("/\s+/", '  ', $string);
				
				# Perform the match
				if (preg_match_all ($matchString, $testString, $matches, PREG_PATTERN_ORDER)) {
					foreach ($matches[0] as $match) {
						$replacements[] = trim ($match);	// Trim so that overlapping strings e.g. "XVII- XIX" which has matches "XVII- " and " XIX" in /records/120782/ are both picked up
					}
				}
			}
		}
		
		# Define a numbered token pattern consisting of a safe string not likely to be present in the data and which will not be affected by any transliteration operation
		$pattern = '<||%i||>';	// %i represents an index that will be generated, e.g. ' <||367||> ', which acts as a token representing the value of $replacements[367]
		
		# Create a token for each protected part
		$protectedParts = array ();
		$i = 0;
		foreach ($replacements as $replacement) {
			$key = str_replace ('%i', $i++, $pattern);
			$protectedParts[$key] = $replacement;
		}
		
		# Perform protection, by replacing with the numbered token
		$string = strtr ($string, array_flip ($protectedParts));
		
		# Return the protected string
		return $string;
	}
	
	
	# Function to create a list of strings to protect from transliteration
	private function transliterationProtectedStrings ()
	{
		# Use cache if present
		if (isSet ($this->transliterationProtectedStrings)) {
			return $this->transliterationProtectedStrings;
		}
		
		# Start a list
		$replacements = array ();
		
		# Protect species Order names (which will not be in italics)
		$replacements = array_merge ($replacements, array_values ($this->getSpeciesOrderNames ()));
		
		# Protect species names, chemical formulae, latin abbreviations, and other strings
		$speciesNames = $this->oneColumnTableToList ('transliterationProtectedStrings.txt');
		$replacements = array_merge ($replacements, $speciesNames);
		
		# Protect Roman numerals, by defining dynamic replacement patterns
		#!# There is still the potential for "Volume I." at the end of a sentence, but that I. cannot be disambiguated from I. as an initial
		$replacements[] = '/' . '(?:\s|\(|^)' . '[IVXLCDM]+[-IVXLCDM]*' . '(?:\s|\)|$)' . '/';
		$replacements[] = '/' . '(?:\s|\(|^)' . '[IVXLCDM]+[-IVXLCDM]+' . '(?:\s|\)|$|\.)' . '/';	// Allow space if more than one; e.g. /records/144193/ which includes "Dactylopteriformes. - XXXVII."
		
		# Cache
		$this->transliterationProtectedStrings = $replacements;
		
		# Return the list
		return $replacements;
	}
	
	
	# Helper function to process a list file to an array
	private function oneColumnTableToList ($filename)
	{
		# Process the file
		$lookupTable = file_get_contents ($this->applicationRoot . '/tables/' . $filename);
		$lookupTable = trim ($lookupTable);
		$lookupTable = str_replace ("\r\n", "\n", $lookupTable);
		$result = explode ("\n", $lookupTable);
		
		# Strip empty lines and comments
		foreach ($result as $index => $line) {
			if (!strlen (trim ($line)) || preg_match ('/^#/', $line)) {unset ($result[$index]);}
		}
		$result = array_values ($result);	// Reindex
		
		# Return the list
		return $result;
	}
	
	
	# Function to obtain species Order names
	private function getSpeciesOrderNames ()
	{
		# Obtain the data from the UDC table
		$query = "SELECT * FROM udctranslations WHERE ks REGEXP '^(582|593|594|595|597|598|599)\\\\.'";
		$orders = $this->databaseConnection->getPairs ($query);
		
		# Return the list
		return $orders;
	}
	
	
	# Function to create XML records
	#   Depencies: catalogue_processed
	#!# This is currently not idempotent, in that re-running it will cause the BGN/PCGN transliterations to be lost from the second time
	private function createXmlTable ()
	{
		# Clean out the XML table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_xml;";
		$this->databaseConnection->execute ($sql);
		
		# Create the new XML table
		$sql = "
			CREATE TABLE IF NOT EXISTS catalogue_xml (
				id int(11) NOT NULL COMMENT 'Record number',
				xml text COLLATE utf8_unicode_ci COMMENT 'XML representation of Muscat record',
				langauge VARCHAR(255) NULL COLLATE utf8_unicode_ci COMMENT 'Record language',
				parallelTitleLanguages VARCHAR(255) NULL COLLATE utf8_unicode_ci COMMENT 'Parallel title languages',
			  PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='XML representation of Muscat records';
		";
		$this->databaseConnection->execute ($sql);
		
		# Cross insert the IDs
		$query = "INSERT INTO catalogue_xml (id) (SELECT DISTINCT(recordId) FROM catalogue_processed);";
		$this->databaseConnection->execute ($query);
		
		# Create the XML for each record
		$this->processXmlRecords ();
		
		# Replace location=Periodical in the processed records with the real, looked-up values
		$this->processPeriodicalLocations ();
		
		# Invalid XML records containing location=Periodical, to force regeneration
		$query = "UPDATE catalogue_xml SET xml = NULL WHERE xml LIKE '%<location>Periodical</location>%';";
		$this->databaseConnection->execute ($query);
		
		# Perform a second-pass of the XML processing, to fix up location=Periodical cases; a relatively small number will legitimately remain after this
		$this->processXmlRecords ();
		
		# Add the language lookups
		$query = "UPDATE catalogue_xml SET langauge = ExtractValue(xml, '/*/lang[1]');";
		$this->databaseConnection->execute ($query);
		
		# Add the parallel title language lookups
		$query = "UPDATE catalogue_xml
			LEFT JOIN catalogue_processed ON
				    catalogue_xml.id = catalogue_processed.recordId
				AND field = 'lang'
				AND value LIKE '% = %'
				AND value REGEXP '(" . implode ('|', array_keys ($this->supportedReverseTransliterationLanguages)) . ")'
			SET parallelTitleLanguages = value
		;";
		$this->databaseConnection->execute ($query);
		
		# Upgrade the transliterations to Library of Congress; this includes a third-pass of the XML records
		$this->upgradeTransliterationsToLocInXml ();
	}
	
	
	# Function to transliterations to Library of Congress (LoC)
	/* 
		This is done as follows:
		1) Pick out transliterable parts of the XML, which is therefore in a hierarchical context to avoid e.g. the wrong *t being picked
		2) Match those back to processed record shards
		3) Create reverse-transliterations from the original latin characters to Cyrillic
		4) Forward transliterate the generated Cyrillic into Library of Congress transliterations
		5) Update the processed table with the new LoC transliterations
		6) Invalidate the XML records containing transliterations
		7) Regenerating the invalidated XML records
	*/
	private function upgradeTransliterationsToLocInXml ()
	{
		# Create the transliteration table; actual transliteration of records into MARC is done on-the-fly
		#   Dependencies: catalogue_processed, catalogue_xml
		$this->initialiseTransliterationsTable ();
		
		# Upgrade the processed record shards containing transliteration to use the new Library of Congress transliterations, and save the original BGN/PCGN value
		$query = "UPDATE catalogue_processed
			INNER JOIN transliterations ON catalogue_processed.id = transliterations.shardId
			SET
				preTransliterationUpgradeValue = value,
				value = title_loc
		;";
		$this->databaseConnection->execute ($query);
		
		# Invalidate XML records containing transliterations
		$query = "UPDATE catalogue_xml
			INNER JOIN transliterations ON catalogue_xml.id = transliterations.recordId
			SET xml = NULL
		;";
		$this->databaseConnection->execute ($query);
		
		# Perform a third-pass of the XML processing, to pick up the new transliterations for the invalidated records
		$this->processXmlRecords ();
	}
	
	
	# Function to do the XML record processing, called from within the main XML table creation function; this will process about 1,000 records a second
	private function processXmlRecords ()
	{
		# Get the schema
		$schemaFlattenedXmlWithContainership = $this->getSchema (true);
		
		# Allow large queries for the chunking operation
		$maxQueryLength = (1024 * 1024 * 32);	// i.e. this many MB
		$query = 'SET SESSION max_allowed_packet = ' . $maxQueryLength . ';';
		$this->databaseConnection->execute ($query);
		
		# Process the records in chunks
		$chunksOf = 500;	// Change max_allowed_packet above if necessary
		while (true) {	// Until the break
			
			# Get the next chunk of record IDs to update, until all are done
			$query = "SELECT id FROM catalogue_xml WHERE xml IS NULL AND id >= 1000 LIMIT {$chunksOf};";	// Records 1-999 are internal documentation records
			if (!$ids = $this->databaseConnection->getPairs ($query)) {break;}
			
			# Get the records for this chunk, using the processed data (as that includes character conversions)
			$records = $this->getRecords ($ids, 'processed');
			
			# Arrange as a set of inserts
			$inserts = array ();
			foreach ($records as $id => $record) {
				$xml = xml::dropSerialRecordIntoSchema ($schemaFlattenedXmlWithContainership, $record, $errorHtml, $debugString);
				if ($errorHtml) {
					$html  = "<p class=\"warning\">Record <a href=\"{$this->baseUrl}/records/{$id}/\">{$id}</a> could not be converted to XML:</p>";
					$html .= "\n" . $errorHtml;
					$html .= "\n<div class=\"graybox\">\n<h3>Crashed record:</h3>" . "\n<pre>" . htmlspecialchars ($xml) . "\n</pre>\n</div>";
					$html .= "\n<div class=\"graybox\">\n<h3>Stack debug:</h3>" . nl2br ($debugString) . "\n</div>";
					// $html .= "\n<div class=\"graybox\">\n<h3>Target schema:</h3>" . application::dumpData ($schemaFlattenedXmlWithContainership, false, true) . "\n</div>";
					echo $html;
					$xml = "<q0>{$id}</q0>";
				}
				$inserts[$id] = array (
					'id' => $id,
					'xml' => $xml,
				);
			}
			
			# Update these records
			// if (!$this->databaseConnection->insertMany ($this->settings['database'], 'catalogue_xml', $inserts, false, $onDuplicateKeyUpdate = true)) {
			if (!$this->databaseConnection->replaceMany ($this->settings['database'], 'catalogue_xml', $inserts)) {
				echo "<p class=\"warning\">Error generating XML, stopping at batch ({$id}):</p>";
				echo application::dumpData ($this->databaseConnection->error (), false, true);
				return false;
			}
		}
	}
	
	
	# Function to replace location=Periodical in the processed records with the real, looked-up values; dependencies: catalogue_processed and catalogue_xml
	private function processPeriodicalLocations ()
	{
		# Create the table, clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.periodicallocations;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `periodicallocations` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`recordId` int(6) NOT NULL COMMENT 'Record number',
			`title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Title (/ser/tg/t)',
			PRIMARY KEY (id),
			INDEX(recordId),
			INDEX(title)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of periodical locations'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Insert the data
		$sql = "
			INSERT INTO `periodicallocations` (recordId, title)
			SELECT
				id AS recordId,
				EXTRACTVALUE(xml, '//ser/tg/t') AS title
			FROM catalogue_xml
			WHERE EXTRACTVALUE(xml, '//ser') != ''	/* i.e. is a *ser */
		";
		$this->databaseConnection->execute ($sql);
		
		# Fix entities; e.g. see /records/23956/ ; see: https://stackoverflow.com/questions/30194976/
		$sql = "
			UPDATE `periodicallocations`
			SET title = REPLACE( REPLACE( REPLACE( REPLACE( REPLACE( title   , '&amp;', '&'), '&lt;', '<'), '&gt;', '>'), '&quot;', '\"'), '&apos;', \"'\")
		;";
		$this->databaseConnection->execute ($sql);
		
		# Create the table of matches, clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.periodicallocationmatches;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `periodicallocationmatches` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`recordId` int(6) NOT NULL COMMENT 'Record number of child',
			`title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Title in child',
			`parentRecordId` int(6) NULL COMMENT 'Record number of parent',
			`parentLocation` varchar(255) COLLATE utf8_unicode_ci NULL COMMENT 'Parent location',
			`parentTitle` varchar(255) COLLATE utf8_unicode_ci NULL COMMENT 'Parent title',
			PRIMARY KEY (id),
			INDEX(recordId),
			INDEX(parentRecordId)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of periodical location matches'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Insert the data for each grouping; note that the periodicallocations table is no longer needed after this
		#!# For the inexact matching, there is the problem that the partial join could find the wrong record; these are being fixed up to add a consistent separator in Muscat; the match below will then need to be adjusted to do an exact match against the section on the child side before the ' ;', e.g.: "SELECT * FROM `catalogue_processed` WHERE `field` IN ('ts','t') AND `value` LIKE 'Seismological Bulletin%';"
		$groupings = array (
			'//art/j/tg/t'	=> true,		// for /art/j records; requires exact match
			'//doc/ts[1]'		=> false,	// for /doc records; requires at least partial match, e.g. "Annals of Glaciology 9" in child record's (first) /doc/ts matches "Annals of Glaciology" in parent (periodicallocations.title); 82 matches; /records/209527/ is an example with two *ts values - the first is used in Muscat as the match
		);
		foreach ($groupings as $titleField => $isExactMatch) {
			$sql = "
				INSERT INTO `periodicallocationmatches` (recordId, title, parentRecordId, parentLocation, parentTitle)
				SELECT
					child.recordId,
					EXTRACTVALUE(xml, '{$titleField}') AS title,
					periodicallocations.recordId AS parentRecordId,
					parent.value AS parentLocation,
					periodicallocations.title AS parentTitle	/* Necessary to enable HAVING, but useful for debugging anyway */
				FROM catalogue_processed AS child
				LEFT JOIN catalogue_xml ON child.recordId = catalogue_xml.id
				LEFT JOIN " . ($isExactMatch ? "periodicallocations ON periodicallocations.title = EXTRACTVALUE(xml, '{$titleField}')" : "periodicallocations ON EXTRACTVALUE(xml, '{$titleField}') LIKE CONCAT(periodicallocations.title, '%')") . "
				LEFT JOIN catalogue_processed AS parent ON periodicallocations.recordId = parent.recordId AND parent.field = 'Location'
				WHERE child.field = 'location' AND child.value = 'Periodical'
				AND LENGTH(EXTRACTVALUE(xml, '{$titleField}')) > 0
				" . (!$isExactMatch ? "HAVING periodicallocations.title IS NOT NULL	/* Necessary to strip out LEFT JOIN non-matches; INNER JOIN runs too slowly */" : '') . "
			;";
			$this->databaseConnection->execute ($sql);
		}
		
		# Replace location=Periodical in the processed records with the real, looked-up values
		$sql = "
			UPDATE catalogue_processed
			LEFT JOIN periodicallocationmatches ON catalogue_processed.recordId = periodicallocationmatches.recordId
			SET value = parentLocation
			WHERE
				    field = 'Location' AND value = 'Periodical'
				AND parentLocation IS NOT NULL
			;";
		$this->databaseConnection->execute ($sql);
	}
	
	
	# Function to create MARC records
	private function createMarcRecords ()
	{
		# Create the volume numbers table, used for observation of the effect of the generate490 macro
		$this->createVolumeNumbersTable ();
		
		# Clean out the MARC table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_marc;";
		$this->databaseConnection->execute ($sql);
		
		# Create the new MARC table
		$sql = "
			CREATE TABLE IF NOT EXISTS catalogue_marc (
				id int(11) NOT NULL COMMENT 'Record number',
				type ENUM('/art/in','/art/j','/doc','/ser') DEFAULT NULL COMMENT 'Type of record',
				status ENUM('migrate','suppress','ignore') NULL DEFAULT NULL COMMENT 'Status',
				mergeType VARCHAR(255) NULL DEFAULT NULL COMMENT 'Merge type',
				mergeVoyagerId VARCHAR(255) NULL DEFAULT NULL COMMENT 'Voyager ID for merging',
				marcPreMerge TEXT NULL COLLATE utf8_unicode_ci COMMENT 'Pre-merged MARC representation of local Muscat record',
				marc TEXT COLLATE utf8_unicode_ci COMMENT 'MARC representation of Muscat record',
				bibcheckErrors TEXT NULL COLLATE utf8_unicode_ci COMMENT 'Bibcheck errors, if any',
			  PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC representation of Muscat records'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Cross insert the IDs
		$query = "INSERT INTO catalogue_marc (id) (SELECT DISTINCT(recordId) FROM catalogue_rawdata);";
		$this->databaseConnection->execute ($query);
		
		# Determine the type
		$query = "UPDATE catalogue_marc
			JOIN catalogue_xml ON catalogue_marc.id = catalogue_xml.id
			SET type = CASE
				WHEN LENGTH( EXTRACTVALUE(xml, '//art/in')) > 0 THEN '/art/in'
				WHEN LENGTH( EXTRACTVALUE(xml, '//art/j' )) > 0 THEN '/art/j'
				WHEN LENGTH( EXTRACTVALUE(xml, '//doc'   )) > 0 THEN '/doc'
				WHEN LENGTH( EXTRACTVALUE(xml, '//ser'   )) > 0 THEN '/ser'
			END
		;";
		$this->databaseConnection->execute ($query);
		
		# Add in the supress/migrate/ignore status for each record; also available as a standalone option in the import
		$this->marcRecordsSetStatus ();
		
		# Add in the Voyager merge data fields, retrieving the resulting data
		$mergeData = $this->marcRecordsSetMergeFields ();
		
		# Get the schema
		if (!$marcParserDefinition = $this->getMarcParserDefinition ()) {return false;}
		
		# Get the merge definition
		if (!$mergeDefinition = $this->parseMergeDefinition ($this->getMergeDefinition ())) {return false;}
		
		# Allow large queries for the chunking operation
		$maxQueryLength = (1024 * 1024 * 32);	// i.e. this many MB
		$query = 'SET SESSION max_allowed_packet = ' . $maxQueryLength . ';';
		$this->databaseConnection->execute ($query);
		
		# Start a list of records which require a second-parse arising from 773 processing where the host does not exist at time of processing
		$this->marcSecondPass = array ();
		
		# Process records in the given order, so that processing of field 773 will have access to *doc/*ser processed records up-front
		$recordProcessingOrder = array ('/doc', '/ser', '/art/j', '/art/in', 'secondpass');
		foreach ($recordProcessingOrder as $recordType) {
			
			# Process the records in chunks
			$chunksOf = 500;	// Change max_allowed_packet above if necessary
			while (true) {	// Until the break
				
				# For the standard processing groups phase, look up from the database as usual
				if ($recordType != 'secondpass') {
					
					# Get the next chunk of record IDs to update for this type, until all are done
					$query = "SELECT
						id
					FROM catalogue_marc
					WHERE
						    type = '{$recordType}'
						AND marc IS NULL
						AND id >= 1000
					LIMIT {$chunksOf}
					;";
					if (!$ids = $this->databaseConnection->getPairs ($query)) {break;}	// Break the while (true) loop and move to next record type
					
				} else {
					
					# For the second pass, use the second pass list that has been generated in the standard processing phase, once only
					if (!$this->marcSecondPass) {break;}	// Break the while (true) loop
					$ids = $this->marcSecondPass;
					$this->marcSecondPass = false;	// Ensure once only
				}
				
				# Get the records for this chunk
				$records = $this->getRecords ($ids, 'xml');
				
				# Arrange as a set of inserts
				$inserts = array ();
				foreach ($records as $id => $record) {
					$mergeType      = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeType'] : false);
					$mergeVoyagerId	= (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeVoyagerId'] : false);
					$marcPreMerge = NULL;	// Reset for next iteration
					$marc = $this->convertToMarc ($marcParserDefinition, $record['xml'], $errorString, $mergeDefinition, $mergeType, $mergeVoyagerId, $marcPreMerge);
					if ($errorString) {
						$html  = "<p class=\"warning\">Record <a href=\"{$this->baseUrl}/records/{$id}/\">{$id}</a> could not be converted to MARC:</p>";
						$html .= "\n<p><img src=\"/images/icons/exclamation.png\" class=\"icon\" /> {$errorString}</p>";
						echo $html;
					}
					$inserts[$id] = array (
						'id' => $id,
						'marcPreMerge' => $marcPreMerge,
						'marc' => $marc,
					);
				}
				
				# Insert the records (or update for the second pass); ON DUPLICATE KEY UPDATE is a dirty but useful method of getting a multiple update at once (as this doesn't require a WHERE clause, which can't be used as there is more than one record to be inserted)
				if (!$this->databaseConnection->insertMany ($this->settings['database'], 'catalogue_marc', $inserts, false, $onDuplicateKeyUpdate = true)) {
					echo "<p class=\"warning\">Error generating MARC, stopping at batched ({$id}):</p>";
					echo application::dumpData ($this->databaseConnection->error (), false, true);
					return false;
				}
			}
		}
		
		# Generate the output files
		foreach ($this->filesets as $fileset => $label) {
			$this->createMarcExport ($fileset);
		}
		
		# Signal success
		return true;
	}
	
	
	# Function to import existing Voyager records
	private function createExternalRecords ()
	{
		# Load the Voyager records file
		$voyagerRecordsFile = file_get_contents ($this->applicationRoot . '/tables/' . 'spri_serials_recs_with_spri_mfhd_attached.mrk');
		
		# Parse the Voyager records file into line-by-line shards
		$shards = $this->parseVoyagerRecordFile ($voyagerRecordsFile);
		
		# Clean out the external records table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_external;";
		$this->databaseConnection->execute ($sql);
		
		# Create the new external records table
		$sql = "
			CREATE TABLE IF NOT EXISTS catalogue_external (
				id int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
				voyagerId INT(11) NOT NULL COMMENT 'Voyager ID',
				field VARCHAR(3) NOT NULL COMMENT 'Field code',
				indicators VARCHAR(2) NOT NULL COMMENT 'First and second indicator',
				data VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Data',
			  PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Existing Voyager records';
		";
		$this->databaseConnection->execute ($sql);
		
		# Insert the new data
		$this->databaseConnection->insertMany ($this->settings['database'], 'catalogue_external', $shards);
		
		# Signal success
		return true;
	}
	
	
	# Function to parse the Voyager record file
	private function parseVoyagerRecordFile ($voyagerRecordsFile)
	{
		# Normalise newlines
		$voyagerRecordsFile = str_replace ("\r\n", "\n", trim ($voyagerRecordsFile));
		
		# Replace Voyager encodings with MARC characters
		$replacements = array ('\\' => '#', '$'=> $this->doubleDagger);
		$voyagerRecordsFile = strtr ($voyagerRecordsFile, $replacements);
		
		# Split by double-newline
		$records = explode ("\n\n", $voyagerRecordsFile);
		
		# Convert each line from Voyager to pure MARC format
		foreach ($records as $index => $record) {
			
			# For LDR, 001, 005, 008, add two spaces to act as a virtual two indicators, so that the regexp below matches for all lines
			$record = preg_replace ('/^(=(?:LDR|00.)  )(.+)/m', '\1  \2', $record);
			
			# Perform matches
			$matches = array ();
			$result = preg_match_all ('/^=([0-9|LDR]{3})  ([ \\0-9]{2})(.+)$/m', $record, $matches, PREG_SET_ORDER);	// Tested previously to confirm no errors
			$records[$index] = $matches;
		}
		
		# Reindex by field 001, which is the actual ID
		$voyager = array ();
		foreach ($records as $index => $record) {
			$voyagerId = $record[1][3];	// Second line, match \3 (i.e. index 4)
			$voyager[$voyagerId] = $record;
		}
		
		# Arrange as record shards
		$shards = array ();
		foreach ($voyager as $voyagerId => $record) {
			foreach ($record as $index => $line) {
				$shards[] = array (
					'voyagerId'		=> $voyagerId,
					'field'			=> $line['1'],
					'indicators'	=> $line['2'],
					'data'			=> $line['3'],
				);
			}
		}
		
		# Return the record shards
		return $shards;
	}
	
	
	# Function to set the status of each MARC record
	private function marcRecordsSetStatus ()
	{
		# NB Unfortunately CASE does not seem to support compound statements, so these three statements are basically a CASE in reverse; see: http://stackoverflow.com/a/18170014/180733
		
		# Default to migrate
		$query = "UPDATE catalogue_marc SET status = 'migrate';";
		$this->databaseConnection->execute ($query);
		
		# Records to suppress
		$query = "UPDATE catalogue_marc
			LEFT JOIN catalogue_processed ON catalogue_marc.id = catalogue_processed.recordId
			LEFT JOIN catalogue_xml ON catalogue_marc.id = catalogue_xml.id
			SET status = 'suppress'
			WHERE
			   (field = 'status' AND value = 'RECEIVED')	-- 8339 records
				OR (
					    EXTRACTVALUE(xml, '//status') IN('O/P', 'ON ORDER', 'ON ORDER (O/P)', 'ON ORDER (O/S)')
					AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'
					AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) >= UNIX_TIMESTAMP('2015-01-01 00:00:00')
					)	-- 36 records
				OR (
					    field = 'location'
					AND value NOT REGEXP \"^(" . implode ('|', array_keys ($this->locationCodes)) . ")\"
					AND (
						   value IN('', '-', '??', 'Not in SPRI', 'Periodical')
						OR value LIKE '%?%'
						OR value LIKE '%Cambridge University%'
						OR value LIKE 'Picture Library Store : Video%'
						)
					)	-- 10006 records
				OR (
					    field IN('note', 'local', 'priv')
					AND (
						   value LIKE '%offprint%'
						OR value LIKE '%photocopy%'
						)
					AND value NOT LIKE '%out of copyright%'
					)	-- 1793 records
				OR (
					    EXTRACTVALUE(xml, '//doc/tg/t') = ''
					AND EXTRACTVALUE(xml, '//art/tg/t') = ''
					AND EXTRACTVALUE(xml, '//ser/tg/t') = ''
					)	-- 172 records
				-- 100292 records in total
		;";
		$this->databaseConnection->execute ($query);
		
		# Records to ignore (highest priority)
		$query = "UPDATE catalogue_marc
			LEFT JOIN catalogue_processed ON catalogue_marc.id = catalogue_processed.recordId
			LEFT JOIN catalogue_xml ON catalogue_marc.id = catalogue_xml.id
			SET status = 'ignore'
			WHERE
				   (field = 'status' AND value = 'ORDER CANCELLED')
				OR (
					    EXTRACTVALUE(xml, '//status') IN('O/P', 'ON ORDER', 'ON ORDER (O/P)', 'ON ORDER (O/S)')
					AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'
					AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) < UNIX_TIMESTAMP('2015-01-01 00:00:00')
					)
				OR (field = 'location' AND value IN('IGS', 'International Glaciological Society', 'Basement IGS Collection'))
			-- 1846 records in total
		;";
		$this->databaseConnection->execute ($query);
	}
	
	
	# Function to add in the Voyager merge data fields
	private function marcRecordsSetMergeFields ()
	{
		# Records to suppress
		$query = "UPDATE catalogue_marc
			LEFT JOIN catalogue_xml ON catalogue_marc.id = catalogue_xml.id
			SET
				mergeType = REPLACE (EXTRACTVALUE(xml, '//ka'), 'Matchtype: ', ''),
				mergeVoyagerId = EXTRACTVALUE(xml, '//kc')
			WHERE
				   EXTRACTVALUE(xml, '//ka') != ''
				OR EXTRACTVALUE(xml, '//kc') != ''
		;";
		$this->databaseConnection->execute ($query);
		
		# Read the values back and return them
		$query = "SELECT id, mergeType, mergeVoyagerId FROM {$this->settings['database']}.catalogue_marc WHERE mergeType IS NOT NULL";
		$mergeData = $this->databaseConnection->getData ($query, "{$this->settings['database']}.catalogue_marc");
		return $mergeData;
	}
	
	
	# Function to create a table of UDC translations
	private function createUdcTranslationsTable ()
	{
		# Create the table, clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.udctranslations;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS udctranslations (
			`ks` VARCHAR(20) NOT NULL COMMENT '*ks',
			`kw` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '*kw equivalent, looked-up',
			PRIMARY KEY (`ks`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of UDC translations'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Parse the table file
		$udcTranslations = $this->parseUdcTranslationTable ();
		
		# Arrange the values
		$inserts = array ();
		foreach ($udcTranslations as $ks => $kw) {
			$inserts[] = array (
				'ks'	=> $ks,
				'kw'	=> $kw,
			);
		}
		
		# Insert the data
		$this->databaseConnection->insertMany ($this->settings['database'], 'udctranslations', $inserts);
	}
	
	
	# Function to parse the UDC translation table
	private function parseUdcTranslationTable ()
	{
		# Load the file, and normalise newlines
		$lookupTable = file_get_contents ($this->applicationRoot . '/tables/' . 'UDCMAP.TXT');
		$lookupTable = str_replace ("\r\n", "\n", $lookupTable);
		
		# Undo Muscat escaped asterisks @*
		$lookupTable = $this->unescapeMuscatAsterisks ($lookupTable);
		
		# Remove line-breaks that are not the end of a line
		$lookupTable = preg_replace ("/([^#])\n/sm", '\1 ', $lookupTable);
		
		# Parse into lines
		/*  Example lines:
		 *  *k 803.98 * *ksub Danish language #
		 *  *k 93"15" * *ksub Sixteenth century #
		 *  *k 39(091) * *ksub Ethnohistory #
		 *  *k 77.041.5 * * ksub Portrait phtography, portraits #
		 */
		preg_match_all ("/^\*k\s([^\s]+) \* \*k\s?(?:sub|geo) ([^#]+) #/sm", $lookupTable, $matches, PREG_SET_ORDER);
		
		# Do a duplicates check
		$ids = array ();
		foreach ($matches as $match) {
			$ids[] = $match[1];
		}
		$duplicates = array_diff_assoc ($ids, array_unique ($ids));
		if ($duplicates) {
			echo "\<p class=\"warning\">The following duplicates were found in the UDC loading phase: <em>" . implode ('</e>, <em>', $duplicates) . ' .</em></p>';
		}
		
		# Arrange as $ks => $kw
		$udcTranslations = array ();
		foreach ($matches as $match) {
			$ks = $match[1];
			$kw = $match[2];
			$udcTranslations[$ks] = $kw;
		}
		
		# Split off any trailing *... sections
		foreach ($udcTranslations as $ks => $kw) {
			if (substr_count ($kw, ' * ')) {
				list ($kw, $supplementaryTerm) = explode (' * ', $kw, 2);
				$udcTranslations[$ks] = $kw;
			}
		}
		
		# Split off any (...) sections
		$bracketExceptions = array ('(*501)', '(*52)');
		foreach ($udcTranslations as $ks => $kw) {
			if (in_array ($ks, $bracketExceptions)) {continue;}		// Skip listed exceptions
			if (substr_count ($kw, '(')) {
				$udcTranslations[$ks] = preg_replace ('/( ?\(([^)]+)\))/', '', $kw);
			}
		}
		
		# Return the matches; should be 3463 results
		return $udcTranslations;
	}
	
	
	# Function to unescape Muscat asterisks
	private function unescapeMuscatAsterisks ($string)
	{
		return str_replace ('@*', '*', $string);
	}
	
	
	# Function to create the volume numbers table, used for observation of the effect of the generate490 macro
	private function createVolumeNumbersTable ()
	{
		# Create the table, clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.volumenumbers;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS volumenumbers (
			id int(11) NOT NULL COMMENT 'Automatic key',
			ts VARCHAR(255) NOT NULL COMMENT '*ts value',
			result VARCHAR(255) DEFAULT NULL COMMENT 'Result of translation',
			matchedRegexp VARCHAR(255) DEFAULT NULL COMMENT 'Result of translation',
			PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of volume numbers'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Insert the data
		$sql = "
			INSERT INTO volumenumbers (id, ts)
			SELECT
				id,
				EXTRACTVALUE(xml, '//ts') AS ts
			FROM catalogue_xml
			WHERE EXTRACTVALUE(xml, '//ts') != ''	/* i.e. is a *ser */
		";
		$this->databaseConnection->execute ($sql);
		
		# Obtain the values
		$data = $this->databaseConnection->selectPairs ($this->settings['database'], 'volumenumbers', array (), array ('id', 'ts'));
		
		# Generate the result
		$updates = array ();
		foreach ($data as $recordId => $ts) {
			$result = $this->macro_generate490 ($ts, NULL, NULL, NULL, $matchedRegexp);
			$updates[$recordId] = array (
				'result' => $result,
				'matchedRegexp' => $matchedRegexp,
			);
		}
		
		# Update the table to add the results of the macro generation
		$this->databaseConnection->updateMany ($this->settings['database'], 'volumenumbers', $updates);
	}
	
	
	# Function to generate the MARC21 output as text
	private function createMarcExport ($fileset)
	{
		# Clear the current file(s)
		$directory = $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl;
		$filenameMarcTxt = $directory . "/spri-marc-{$fileset}.txt";
		if (file_exists ($filenameMarcTxt)) {
			unlink ($filenameMarcTxt);
		}
		$filenameMarcExchange = $directory . "/spri-marc-{$fileset}.mrk";
		if (file_exists ($filenameMarcExchange)) {
			unlink ($filenameMarcExchange);
		}
		
		# Get the total records in the table
		$totalRecords = $this->databaseConnection->getTotal ($this->settings['database'], 'catalogue_marc', "WHERE status='{$fileset}'");
		
		# Start the output
		$marcText = '';
		
		# Chunk the records
		$offset = 0;
		$limit = 1000;
		$recordsRemaining = $totalRecords;
		while ($recordsRemaining > 0) {
			
			# Get the records
			$query = "SELECT id,marc FROM {$this->settings['database']}.catalogue_marc WHERE status='{$fileset}' LIMIT {$offset},{$limit};";
			$data = $this->databaseConnection->getPairs ($query);
			
			# Add each record
			foreach ($data as $id => $record) {
				$marcText .= trim ($record) . "\n\n";
			}
			
			# Decrement the remaining records
			$recordsRemaining = $recordsRemaining - $limit;
			$offset += $limit;
		}
		
		# Save the file, in the standard MARC format
		file_put_contents ($filenameMarcTxt, $marcText);
		
		# Copy, so that a Voyager-specific formatted version can be created
		copy ($filenameMarcTxt, $filenameMarcExchange);
		
		# Reformat to Voyager input style; this process is done using shelled-out inline sed/perl, rather than preg_replace, to avoid an out-of-memory crash
		exec ("sed -i 's" . "/{$this->doubleDagger}\([a-z0-9]\)/" . '\$\1' . "/g' {$filenameMarcExchange}");		// Replace double-dagger(s) with $
		exec ("sed -i '/^LDR /s/#/\\\\/g' {$filenameMarcExchange}");												// Replace all instances of a # marker in the LDR field with \
		exec ("sed -i '/^008 /s/#/\\\\/g' {$filenameMarcExchange}");												// Replace all instances of a # marker in the 008 field with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}) #(.) (.+)$/' . '\1 \\\\\2 \3' . "/' {$filenameMarcExchange}");		// Replace # marker in position 1 with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}) (.)# (.+)$/' . '\1 \2\\\\ \3' . "/' {$filenameMarcExchange}");		// Replace # marker in position 2 with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}|LDR) (.+)$/' . '\1  \2' . "/' {$filenameMarcExchange}");				// Add double-space after LDR and each field number
		exec ("perl -pi -e 's" . '/^([0-9]{3})  (.)(.) (.+)$/' . '\1  \2\3\4' . "/' {$filenameMarcExchange}");		// Remove space after first and second indicators
		exec ("perl -pi -e 's" . '/^(.+)$/' . '=\1' . "/' {$filenameMarcExchange}");								// Add = at start of each line
		
		# Create a binary version
		$this->marcBinaryConversion ($fileset, $directory);
		
		# Check the output
		$errorsFilename = $this->marcLintTest ($fileset, $directory);
		
		# Extract the errors from this error report, and add them to the MARC table
		$this->attachBibcheckErrors ($errorsFilename);
	}
	
	
	# Function to convert the MARC text to binary format
	private function marcBinaryConversion ($fileset, $directory)
	{
		# Clear file if it currently exists
		$filename = "{$directory}/spri-marc-{$fileset}.mrc";
		if (file_exists ($filename)) {
			unlink ($filename);
		}
		
		# Define and execute the command for converting the text version to binary; see: http://marcedit.reeset.net/ and http://marcedit.reeset.net/cmarcedit-exe-using-the-command-line and http://blog.reeset.net/?s=cmarcedit
		$command = "mono /usr/local/bin/marcedit/cmarcedit.exe -s {$directory}/spri-marc-{$fileset}.mrk -d {$filename} -pd -make";
		exec ($command, $output, $unixReturnValue);
		foreach ($output as $line) {
			if (preg_match ('/^0 records have been processed/', $line)) {
				echo "<p class=\"warning\">Error in creation of MARC binary file (spri-marc-{$fileset}.mrc): <tt>" . htmlspecialchars ($line) . "</tt></p>";
				break;
			}
		}
	}
	
	
	# Function to do a Bibcheck lint test
	private function marcLintTest ($fileset, $directory)
	{
		# Define the filename for the raw (unfiltered) errors file and the main filtered version
		$errorsFilename = "{$directory}/spri-marc-{$fileset}.errors.txt";
		$errorsUnfilteredFilename = str_replace ('errors.txt', 'errors-unfiltered.txt', $errorsFilename);
		
		# Clear file(s) if currently existing
		if (file_exists ($errorsFilename)) {
			unlink ($errorsFilename);
		}
		if (file_exists ($errorsUnfilteredFilename)) {
			unlink ($errorsUnfilteredFilename);
		}
		
		# Define and execute the command for converting the text version to binary, generating the errors listing file
		$command = "cd {$this->applicationRoot}/libraries/bibcheck/ ; perl lint_test.pl {$directory}/spri-marc-{$fileset}.mrc 2>> errors.txt ; mv errors.txt {$errorsUnfilteredFilename}";
		shell_exec ($command);
		
		# Strip whitelisted errors and save a filtered version
		$errorsString = file_get_contents ($errorsUnfilteredFilename);
		$errorsString = $this->stripBibcheckWhitelistErrors ($errorsString);
		file_put_contents ($errorsFilename, $errorsString);
		
		# Return the filename
		return $errorsFilename;
	}
	
	
	# Function to strip whitelisted errors from the Bibcheck reports
	private function stripBibcheckWhitelistErrors ($errorsString)
	{
		# Define errors to whitelist
		$whitelistErrorRegexps = array (
			'008: Check place code xxu - please set code for specific state \(if known\).',
			'541: Subfield _[0-9] is not allowed.',
		);
		
		# Split the file into individual reports
		$delimiter = str_repeat ('=', 63);	// i.e. the ===== line
		$reportsUnfiltered = explode ($delimiter, $errorsString);
		
		# Filter out lines for each report
		$reports = array ();
		foreach ($reportsUnfiltered as $index => $report) {
			
			# Strip out lines matching a whitelisted error type
			$lines = explode ("\n", $report);
			foreach ($lines as $lineIndex => $line) {
				foreach ($whitelistErrorRegexps as $whitelistErrorRegexp) {
					if (preg_match ('/' . addcslashes ($whitelistErrorRegexp, '/') . '/', $line)) {
						unset ($lines[$lineIndex]);
						break;	// Break out of regexps loop and move to next line
					}
				}
			}
			$report = implode ("\n", $lines);
			
			# If there are no errors remaining in this report, skip re-registering the report
			if (preg_match ('/\^{25}$/D', trim ($report))) {		// i.e. no errors if purely whitespace between ^^^^^ line and the end
				continue;	// Skip to next report
			}
			
			# Re-register the report
			$reports[$index] = $report;
		}
		
		# Reconstruct as a single listing
		$errorsString = implode ($delimiter, $reports);
		
		# Return the new listing
		return $errorsString;
	}
	
	
	# Function to extract errors from a Bibcheck error report and attach them to the MARC records in the database
	private function attachBibcheckErrors ($errorsFilename)
	{
		# Extract from the report
		# The report is in the format of the extract shown below; the SPRI value in 001 and the errors between the ^^^^^ and the ===== line need to be captured:
		/*
			===============================================================
			
			LDR 00803nas a2200265 a 4500
			001     SPRI1021
			005     20160318210532.0
			007     ta
			008     160318uuuuuuuuuxx  u |  |   |0   a|eng d
			040    _aUkCU-P
			       _beng
			       _eaacr
			041 0  _aeng
			245 00 _aCanada. Dept. of Mines and Technical Surveys. Mines Branch. [Reports]
			260    _a[S.l.] :
			       _b[s.n.]
			300    _av.
			546    _aEnglish
			650 07 _2udc
			       _a551.1/.4 -- Geology.
			650 07 _2udc
			       _a553 -- Economic geology.
			650 07 _2udc
			       _a553.042 -- Mineral resources.
			650 07 _2udc
			       _a622 -- Mining.
			651  7 _2udc
			       _a(*41) -- Canada.
			780 10 _tCanada. Dept. of Mines and Resources. Bureau of Mines. [Reports]
			852 7  _2camdept
			       _x??
			866  0 _aunknown
			917    _aUnenhanced record from Muscat, imported 2015
			948 3  _a20160318
			       _dMISSING
			^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
			245: Must end with a full stop.
			
			
			===============================================================
			
			LDR 01216nam a2200241 a 4500
			001     SPRI1334
			...
			
		*/
		$errorsString = file_get_contents ($errorsFilename);
		preg_match_all ("/\sSPRI([0-9]+)(?U).+\^{25,}\s+((?U).+)\s+(?:={25,}|$)/sD", $errorsString, $errors, PREG_SET_ORDER);
		
		# End if none
		if (!$errors) {return;}
		
		# Assemble updates
		$updates = array ();
		foreach ($errors as $error) {
			$id = $error[1];
			$updates[$id] = array ('bibcheckErrors' => $error[2]);
		}
		
		# Do the update
		$this->databaseConnection->updateMany ($this->settings['database'], 'catalogue_marc', $updates);
	}
	
	
	# Function to run the reports
	private function runReports ()
	{
		# Clean out the report results table
		$query = "DROP TABLE IF EXISTS {$this->settings['database']}.reportresults;";
		$this->databaseConnection->query ($query);
		
		# Create the new results table
		$query = "CREATE TABLE IF NOT EXISTS reportresults (
			`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
			`report` varchar(40) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Report type',
			`recordId` int(6) NOT NULL COMMENT 'Record number',
			PRIMARY KEY (`id`),
			KEY `report` (`report`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Results table' AUTO_INCREMENT=1 ;
		";
		$this->databaseConnection->query ($query);
		
		# Run each report and insert the results
		$reports = $this->getReports ();
		foreach ($reports as $report => $description) {
			$reportFunction = 'report_' . $report;
			
			# Skip listing type reports, which implement data handling directly, and which are handled separately in runListings ()
			if ($this->isListing ($report)) {continue;}
			
			# Assemble the query and insert the data
			$query = $this->$reportFunction ();
			$query = "INSERT INTO reportresults (report,recordId) (" . $query . ');';
			$result = $this->databaseConnection->query ($query);
			
			# Handle errors
			if ($result === false) {
				echo "<p class=\"warning\">Error generating report <em>{$report}</em>:</p>";
				echo application::dumpData ($this->databaseConnection->error (), false, true);
			}
		}
	}
	
	
	# Function to run the listing reports
	private function runListings ()
	{
		# Run each listing report
		foreach ($this->listings as $report => $description) {
			$reportFunction = 'report_' . $report;
			$this->$reportFunction ();
		}
	}
	
	
	# Search
	public function search ()
	{
		/*
		
		Main fields that people want to search within are:
		
		- Author (original=a/ee, compiled as: n1 for surname and n2 for forename)
		- Title (t and tt become tc, which is a superset)
		- Date (d)
		
		So use
		
		Surname (n1)
		Forename (n2)
		Title (tc)
		Journal (t - technically must follow j, but for now without)
		Date (d), must be four-digits
		Keyword (kw)
		Text within abstract (abs)
		ISBN (isbn)
		Region (ks, filtered on (*[0-9]+) ) - precompile at
			- Arctic - beginning *3 or *4 or *6
			- Antarctic - begins with *7 or *8
			- Polar - begins *2
			- Russian - begins *5 but not *55/*56/*57/*58
		*/
		
		# Determine whether to do case-sensitive matching
		#!# Currently only implemented for the anywhere key
		$caseSensitivity = (isSet ($_GET['casesensitive']) && ($_GET['casesensitive'] == '1') ? 'BINARY ' : '');
		
		# Determine whether to force matching for a complete value rather than part of a value, i.e. whether 'science' matches only 'science' rather than 'academy of science' also
		#!# Currently only implemented for the anywhere key
		$completeMatch = (isSet ($_GET['completematch']) && ($_GET['completematch'] == '1'));
		
		# Define the search clause templates
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		$searchClauses = array (
			'title'		=> "title LIKE :title",
			'title_transliterated'		=> "title_transliterated LIKE :title_transliterated",
			'region'	=> array (
				'Polar regions'						=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[2][0-9]*{$mysqlBacklash})'",				// *2
				'   Arctic'							=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[3|4|5|6][0-9]*{$mysqlBacklash})'",		// *3 or *4 or *5 or *6
				'   North America'					=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[40][0-9]*{$mysqlBacklash})'",				// *40
				'   Russia'							=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[50|51|52|53][0-9]*{$mysqlBacklash})'",	// *50 - *53
				'   European Arctic'				=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[55|56|57|58][0-9]*{$mysqlBacklash})'",	// *55/*56/*57/*58
				'   Arctic Ocean'					=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[6][0-9]*{$mysqlBacklash})'",				// *6
				'   Antarctic and Southern Ocean'	=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[7|8][0-9]*{$mysqlBacklash})'",			// *7/*8
				'Non-polar regions'					=> "region REGEXP '{$mysqlBacklash}([2|3|4|5|6|7|8|9][0-9]*{$mysqlBacklash})'",	// run from (2) to (97) NB without *
			),
			'surname'		=> "surname LIKE :surname",
			'forename'		=> "forename LIKE :forename",
			'journaltitle'	=> "journaltitle = :journaltitle",
			'seriestitle'	=> "seriestitle = :seriestitle",
			'year'			=> "year LIKE :year",
			'language'		=> "language LIKE :language",
			'abstract'		=> "abstract LIKE :abstract OR keyword LIKE :keyword",
			'isbn'			=> "isbn LIKE :isbn",
			'location'		=> "location LIKE :location",
			'anywhere'		=> "anywhere LIKE {$caseSensitivity} :anywhere",
		);
		
		# Clear application variables out of _GET
		unset ($_GET['action']);
		if (isSet ($_GET['page'])) {
			$page = $_GET['page'];	// Cache for later
			unset ($_GET['page']);
		}
		
		# Start the HTML
		$html  = '';
		$html .= "\n<p>This search will find records that match all the query terms you enter. It is not case sensitive.</p>";
		$html .= "\n<p><a href=\"./\">Reset</a></p>";
		
		# Create the search form
		$result = $this->searchForm ($html, $searchClauses);
		
		# Show results if submitted
		if ($result) {
			
			# Cache a build of the query string
			$queryStringComplete = http_build_query ($result);
			
			# Compile a search token for the match which contains all terms, so that these do not include the % markers
			$allTerms = implode (' ', $result);
			
			# Create a list of constraints
			$constraints = array ();
			foreach ($result as $key => $value) {
				$searchClause = (is_array ($searchClauses[$key]) ? $searchClauses[$key][$value] : $searchClauses[$key]);
				if ($completeMatch) {
					$searchClause = str_replace (' LIKE ', ' = BINARY ', $searchClause);
					$result[$key] = $this->literalLikeValue ($value);
				} else {
					if (substr_count ($searchClause, 'LIKE')) {$result[$key] = '%' . $this->literalLikeValue ($value) . '%';}	// Text matches should be %search%
				}
				if (!substr_count ($searchClause, ' :')) {unset ($result[$key]);}	// Do not supply a value if there is no placeholder
				$constraints[$key] = $searchClause;
			}
			
			# Duplicate abstract to keyword (this is because PDO doesn't permit a named parameter to be used twice, i.e. "abstract LIKE :abstract OR keywordk LIKE :abstract")
			if (isSet ($result['abstract'])) {$result['keyword'] = $result['abstract'];}
			
			# Construct the query
			if ($completeMatch) {
				$query = "SELECT
						id,
						title
					FROM fieldsindex
					WHERE \n    (" . implode (")\nAND (", $constraints) . ')
					ORDER BY titleSortfield
				;';
			} else {
				
				# Add in the allterms token
				$result['allterms'] = $allTerms;
				
				# Construct the query
				$query = "SELECT
						id,
						title,
					MATCH(anywhere) AGAINST(:allterms) AS relevance
					FROM fieldsindex
					WHERE \n    (" . implode (")\nAND (", $constraints) . ')
					ORDER BY titleSortfield
				;';
			}
			
			# Restore $_GET['page']
			if (isSet ($page)) {$_GET['page'] = $page;}
			
			# Display the results
			$baseLink = '/search/';
			$html .= $this->recordListing (false, $query, $result, $baseLink, false, $queryStringComplete, 'table', "{$this->settings['database']}.fieldsindex");
			
			// application::dumpData ($query);
			// application::dumpData ($result);
			// application::dumpData ($this->databaseConnection->error ());
			// application::dumpData ($data);
			
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to provide the search form
	private function searchForm (&$html, $searchClauses)
	{
		# Start the HTML
		$html = '';
		
		# Run the form module
		$form = new form (array (
			'displayRestrictions' => false,
			'get' => true,
			'name' => false,
			'nullText' => false,
			'submitButtonText' => 'Search!',
			'formCompleteText' => false,
			'requiredFieldIndicator' => false,
			'reappear' => true,
			'id' => 'searchform',
			'submitTo' => $this->baseUrl . '/search/',
			'databaseConnection' => $this->databaseConnection,
		));
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => 'fieldsindex',
			'exclude' => array ('id', 'fieldslist', 'keyword', 'titleSortfield'),
			'textAsVarchar' => true,
			'attributes' => array (
				'region' => array ('type' => 'select', 'nullText' => 'Any', 'values' => array_keys ($searchClauses['region']), ),
				'year' => array ('regexp' => '^([0-9]{4})$', 'size' => 7, 'maxlength' => 4, ),
				'abstract' => array ('title' => 'Keyword; or<br />Text within abstract', ),		// Keyword is piggy-backed onto abstract in the search phase
			),
		));
		$formHtml = '';
		$result = $form->process ($formHtml);
		
		# Filter to those filled-in
		if ($result) {
			foreach ($result as $key => $value) {
				if (!strlen ($value)) {
					unset ($result[$key]);
				}
			}
		}
		
		# If there is a result, show the hiding system first
		if ($result) {
			$html .= '
				<!-- http://docs.jquery.com/Effects/toggle -->
				<script src="http://code.jquery.com/jquery-latest.js"></script>
				<script>
					$(document).ready(function(){
						$("a#showform").click(function () {
							$("#searchform").toggle();
						});
					});
				</script>
				<style type="text/css">#searchform {display: none;}</style>
			';
			$html .= "\n" . '<p><a id="showform" name="showform"><img src="/images/icons/pencil.png" alt="" border="0" /> <strong>Refine/filter this search</strong></a> if you wish.</p>';
		}
		
		# Add the form HTML
		$html .= $formHtml;
		
		# Return the result
		return $result;
	}
	
	
	# Function to ensure a value passed for use in LIKE is literal
	private function literalLikeValue ($term)
	{
		# Turn backlashes and wildcards into literals
		$replacements = array (
			'\\' => '\\\\',
			'%' => '\\%',
			'_' => '\\_',
		);
		$term = strtr ($term, $replacements);
		
		# Return the term
		return $term;
	}
	
	
	# MARC21 parser definition
	public function marcparser ()
	{
		# Start the HTML
		$html  = '';
		
		# Get the supported macros
		$supportedMacros = $this->getSupportedMacros ();
		
		# Display a flash message if set
		#!# Flash message support needs to be added to ultimateForm natively, as this is a common use-case
		$successMessage = 'The definition has been updated.';
		if ($flashValue = application::getFlashMessage ('submission', $this->baseUrl . '/')) {
			$message = "\n" . "<p>{$this->tick} <strong>" . $successMessage . '</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'display' => 'paragraphs',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->heading ('p', "Here you can define the translation of the Muscat data's XML representation to MARC21 as interpreted by the <a href=\"http://www.lib.cam.ac.uk/libraries/login/bibstandard/bibstandards.htm\" target=\"_blank\">local standards</a>.");
		$form->heading ('p', 'The parser uses <a target="_blank" href="http://msdn.microsoft.com/en-us/library/ms256122.aspx">XPath operators</a>, enclosed in { } brackets, used to target parts of the <a target="_blank" href="' . $this->baseUrl . '/schema.html">schema</a>.');
		$form->heading ('p', 'Control characters may exist at the start of the line:<br />A = All (non-optional) must result in a match for the line to be displayed (ignoring indicator block macros);<br />E = Any (<em>E</em>ither) of the values must result in a match for the line to be displayed (ignoring indicator block macros);<br />R = Vertically-repeatable field.');
		$form->heading ('p', "A subfield can be set as optional by adding ?, e.g. {$this->doubleDagger}b?{//acq/ref} . Optional blocks found to be empty are removed before an A (all) control character is considered.");
		$form->heading ('p', "A subfield can be set as horizontally-repeatable by adding R, e.g. {$this->doubleDagger}b?R{//acq/ref} . Horizontal repeatability of a subfield takes precendence over vertical repeatability.");
		$form->heading ('p', 'Macros available, written as <tt>{xpath..|macro:<em>macroname</em>}</tt>, are: <tt>' . implode ('</tt>, <tt>', $supportedMacros) . '</tt>. (Those for use in the two indicator positions are prefixed with <tt>indicators</tt>).');
		$form->heading ('p', 'Lines starting with # are comments.');
		$form->heading ('p', 'Macro blocks preceeded with i indicates that this is an indicator block macro; these are for use with control character(s) A/E, as detailed above.');
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Parser definition',
			'required'	=> true,
			'rows'		=> 30,
			'cols'		=> 120,
			'default'	=> $this->getMarcParserDefinition (),
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				$record = '';	// Bogus record - good enough for checking parsing
				$this->convertToMarc ($unfinalisedData['definition'], $record, $errorString);
				if ($errorString) {
					$form->registerProblem ('compilefailure', $errorString);
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Save the latest version to the filesystem as a backup for versioning purposes
			$prefixWarning = "# IMPORTANT: This file is NOT actively used by the importer, but is created when updating the definition purely for the purposes of creating an file version that can be checked into the versioning repository.\n";
			file_put_contents ($this->applicationRoot . '/tables/marcparser.txt', $prefixWarning . $result['definition']);
			
			# Save the latest version to the database
			$this->databaseConnection->insert ($this->settings['database'], 'marcparserdefinition', array ('definition' => $result['definition']));
			
			# Set a flash message
			$function = __FUNCTION__;
			$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/{$this->actions[$function]['url']}";
			$redirectMessage = "\n{$this->tick}" . ' <strong>' . $successMessage . '</strong></p>';
			application::setFlashMessage ('submission', '1', $redirectTo, $redirectMessage, $this->baseUrl . '/');
			
			# Confirm success, resetting the HTML, and show the submission
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# MARC21 output page
	public function export ()
	{
		# End if no output
		$directory = $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl;
		if (!file_exists ("{$directory}/spri-marc-migrate.txt")) {
			$html = "\n<p>There is no MARC output yet. Please <a href=\"{$this->baseUrl}/import/\">run an import</a> first.</p>";
			echo $html;
			return;
		}
		
		# Get the fileset counts
		$query = "SELECT status, COUNT(*) AS total FROM catalogue_marc GROUP BY status;";
		$totals = $this->databaseConnection->getPairs ($query);
		
		# Compile the HTML
		$html  = "\n<h3>Downloads</h3>";
		$html .= "\n<table class=\"lines spaced\">";
		foreach ($this->filesets as $fileset => $label) {
			$html .= "\n\t<tr>";
			$html .= "\n\t\t<td><strong>{$label}</strong>:<br />" . number_format ($totals[$fileset]) . ' records</td>';
			$html .= "\n\t\t<td><a class=\"actions\" href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.txt\">MARC21 data (text)</a></td>";
			$html .= "\n\t\t<td><a class=\"actions\" href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.mrk\"><strong>MARC21 text (text, .mrk)</strong></a></td>";
			$html .= "\n\t\t<td><a class=\"actions\" href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.mrc\">MARC21 data (binary .mrc)</a></td>";
			$html .= "\n\t\t<td><a class=\"actions\" href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.errors.txt\">Errors</a></li>";
			$html .= "\n\t</tr>";
		}
		$html .= "\n</table>";
		
		# Get the totals
		$totalsQuery = "SELECT status, COUNT(*) AS total FROM {$this->settings['database']}.catalogue_marc WHERE bibcheckErrors IS NOT NULL GROUP BY status;";
		$totals = $this->databaseConnection->getPairs ($totalsQuery);
		
		# Show errors
		$html .= "\n<h3>Errors</h3>";
		foreach ($this->filesets as $fileset => $label) {
			$html .= "\n<h4>Errors: {$label}" . (isSet ($totals[$fileset]) ? " ({$totals[$fileset]})" : '') . '</h4>';
			$filename = $directory . "/spri-marc-{$fileset}.errors.txt";
			$errors = file_get_contents ($filename);
			$errorsHtml = htmlspecialchars ($errors);
			$errorsHtml = preg_replace ("/(\s)(SPRI)([0-9]+)/", '\1\2<a href="' . $this->baseUrl . '/records/\3/"><strong>\3</strong></a>', $errorsHtml);
			$html .= "\n<div class=\"graybox\">";
			$html .= "\n<pre>";
			$html .= $errorsHtml;
			$html .= "\n</pre>";
			$html .= "\n</div>";
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to return the MARC parser definition
	private function getMarcParserDefinition ()
	{
		# Get the latest version
		$query = "SELECT definition FROM {$this->settings['database']}.marcparserdefinition ORDER BY id DESC LIMIT 1;";
		if (!$definition = $this->databaseConnection->getOneField ($query, 'definition')) {
			echo "\n<p class=\"warning\"><strong>Error:</strong> The MARC21 parser definition could not be retrieved.</p>";
			return false;
		}
		
		# Return the string
		return $definition;
	}
	
	
	# Function to convert the data to MARC format
	# NB XPath functions can have PHP modifications in them using php:functionString - may be useful in future http://www.sitepoint.com/php-dom-using-xpath/ http://cowburn.info/2009/10/23/php-funcs-xpath/
	private function convertToMarc ($marcParserDefinition, $recordXml, &$errorString = '', $mergeDefinition = array (), $mergeType = false, $mergeVoyagerId = false, &$marcPreMerge = NULL)
	{
		# Ensure the error string is clean for each iteration
		$errorString = '';
		
		# Create fresh containers for 880 reciprocal links for this record
		$this->field880subfield6ReciprocalLinks = array ();
		$this->field880subfield6Index = 0;
		$this->field880subfield6FieldInstanceIndex = array ();
		
		# Ensure the line-by-line syntax is valid, extract macros, and construct a data structure representing the record
		if (!$datastructure = $this->convertToMarc_InitialiseDatastructure ($recordXml, $marcParserDefinition, $errorString)) {return false;}
		
		# End if not all macros are supported
		if (!$this->convertToMarc_MacrosAllSupported ($datastructure, $errorString)) {return false;}
		
		# Load the record as a valid XML object
		$xml = $this->loadXmlRecord ($recordXml);
		
		# Up-front, process author fields
		require_once ('generateAuthors.php');
		$languageModes = array_merge (array ('default'), array_keys ($this->supportedReverseTransliterationLanguages));		// Feed in the languages list, with 'default' as the first
		$generateAuthors = new generateAuthors ($this, $xml, $languageModes);
		$authorsFields = $generateAuthors->getValues ();
		
		# Perform XPath replacements
		if (!$datastructure = $this->convertToMarc_PerformXpathReplacements ($datastructure, $xml, $authorsFields, $errorString)) {return false;}
		
		# Expand vertically-repeatable fields
		if (!$datastructure = $this->convertToMarc_ExpandVerticallyRepeatableFields ($datastructure, $errorString)) {return false;}
		
		# Process the record
		$recordId = $this->xPathValue ($xml, '//q0');
		$record = $this->convertToMarc_ProcessRecord ($datastructure, $errorString, $recordId);
		
		# Determine the length, in bytes, which is the first five characters of the 000 (Leader), padded
		$bytes = mb_strlen ($record);
		$bytes = str_pad ($bytes, 5, '0', STR_PAD_LEFT);
		$record = preg_replace ('/^LDR (_____)/m', "LDR {$bytes}", $record);
		
		# Report any UTF-8 problems
		if (!htmlspecialchars ($record)) {
			$errorString .= "UTF-8 conversion failed in record <a href=\"{$this->baseUrl}/records/{$recordId}/\">#{$recordId}</a>.";
			return false;
		}
		
		# If required, merge with an existing Voyager record, returning by reference the pre-merge record, and below returning the merged record
		if ($mergeType) {
			$marcPreMerge = $record;	// Save to argument returned by reference
			$record = $this->mergeWithExistingVoyager ($record, $recordId, $mergeDefinition, $mergeType, $mergeVoyagerId, $errorString);
		}
		
		# Return the record
		return $record;
	}
	
	
	# Function to perform merge of a MARC record with an existing Voyager record
	private function mergeWithExistingVoyager ($record, $recordId, $mergeDefinition, $mergeType, $mergeVoyagerId, &$errorString)
	{
		# End if merge type is unsupported; this will result in an empty record
		#!# Need to ensure this is reported during the import also
		if (!isSet ($this->mergeTypes[$mergeType])) {
			$errorString = "Merge failed for Muscat record #{$recordId}!";
			return false;
		}
		
		# Perform merge based on the specified strategy
		
		#!# TODO; just needs to modify $record
		//application::dumpData ($mergeDefinition[$mergeType]);
		
		
		# Return the merged record
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
	private function convertToMarc_PerformXpathReplacements ($datastructure, $xml, $authorsFields, &$errorString = '')
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
								$value[$index] = $this->processMacros ($xml, $subValue, $macros, $authorsFields);
							}
						} else {
							$value = $this->processMacros ($xml, $value, $macros, $authorsFields);
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
	private function convertToMarc_ProcessRecord ($datastructure, $errorString, $recordId)
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
			#!# Example at: /records/194996/ This is caused by a generateAuthors multiline with a field number mutation in one of the lines but not all
			if (!isSet ($outputLines[$lineOutputKey])) {
				echo "<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$recordId}/\">record #{$recordId}</a>:</strong> line output key {$lineOutputKey} does not exist in the output lines.</p>";
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
		return preg_replace ('/^([0-9]{3}) (.{2}) (.+)$/', "\\1 \\2 {$insert} \\3", $line);
	}
	
	
	# Reverse-transliteration definition
	public function transliterator ()
	{
		# Start the HTML
		$html  = '';
		
		# Define the language
		$language = 'Russian';
		
		# Display a flash message if set
		#!# Flash message support needs to be added to ultimateForm natively, as this is a common use-case
		$successMessage = 'The definition has been updated.';
		if ($flashValue = application::getFlashMessage ('submission', $this->baseUrl . '/')) {
			$html .= "\n<div class=\"graybox flashmessage\">";
			$html .= "\n" . "<p>{$this->tick} <strong>" . $successMessage . '</strong></p>';
			$html .= "\n" . "<p>You can <a href=\"{$this->baseUrl}/reports/transliterations/\">view the <strong>transliterated titles report</strong></a>, showing all results.</p>";
			$html .= '</div>';
		}
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'display' => 'paragraphs',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->heading ('p', "Here you can define the reverse-transliteration definition.");
		$form->heading ('p', 'Character set numbers - useful references for copying and pasting: <a href="http://en.wikipedia.org/wiki/Russian_alphabet" target="_blank">Russian</a> and <a href="http://en.wikipedia.org/wiki/List_of_Unicode_characters#Basic_Latin" target="_blank">Basic latin</a>.');
		$form->heading ('p', 'Forward transliteration specification: <a href="https://en.wikipedia.org/wiki/BGN/PCGN_romanization_of_Russian" target="_blank">BGN/PCGN romanization of Russian (1947)</a>.');
		$form->heading ('p', 'Character tester: <a href="http://graphemica.com/" target="_blank">Graphemica character tester</a>.');
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Reverse-transliteration definition',
			'required'	=> true,
			'rows'		=> 40,
			'cols'		=> 120,
			'default'	=> $this->getReverseTransliterationDefinition (),
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				require_once ('xml.php');
				if (!xml::isValid ($unfinalisedData['definition'], $errors)) {
					$form->registerProblem ('invalidxml', 'The definition was not valid XML, as per the following error(s):' . application::htmlUl ($errors));
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Save the latest version
			$this->databaseConnection->insert ($this->settings['database'], 'reversetransliterationdefinition', array ('definition' => $result['definition']));
			
			# Save an archive copy to the tables directory
			$prefixWarning = "<!-- IMPORTANT: This file is NOT actively used by the importer, but is created when updating the definition purely for the purposes of creating an file version that can be checked into the versioning repository. -->\n";
			file_put_contents ($this->applicationRoot . '/tables/reverseTransliteration.xml', $prefixWarning . $result['definition']);
			
			# Compile the reverse transliterator
			if (!$this->compileReverseTransliterator ($result['definition'], $language, $errorHtml)) {
				echo "\n<p class=\"warning\">{$errorHtml}</p>";
				return false;
			}
			
			# Perform transliteration to regenerate the transliterations report
			$this->transliterateTransliterationsTable ();
			
			# Set a flash message
			$function = __FUNCTION__;
			$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/{$this->actions[$function]['url']}";
			$redirectMessage = "\n{$this->tick}" . ' <strong>' . $successMessage . '</strong></p>';
			application::setFlashMessage ('submission', '1', $redirectTo, $redirectMessage, $this->baseUrl . '/');
			
			# Confirm success, resetting the HTML, and show the submission
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to compile the reverse transliteration file
	private function compileReverseTransliterator ($definition, $language, &$errorHtml = '')
	{
		# Reinstall local CPAN each time, as eventually the perl5/perl5/perl5/perl5/... problem will bite otherwise
		$command = "cd {$this->cpanDir} && cd ../ && rm -rf cpan/ && ./install.sh";
		exec ($command, $output, $unixReturnValue);
		
		# Define the local CPAN directory and the translit compilation directory
		$translitDir = "{$this->cpanDir}/Lingua-Translit-0.22";
		
		# Define a reverse transliteration definition file name; e.g. 'BGN PCGN 1947' should be bgn_pcgn_1947.xml
		$filename = str_replace (' ', '_', strtolower ($this->supportedReverseTransliterationLanguages[$language])) . '.xml';
		
		# Write out the file
		$reverseTransliterationFile = $translitDir . '/xml/' . $filename;
		if (!file_put_contents ($reverseTransliterationFile, $definition)) {
			$errorHtml = 'Error saving the transliteration file.';
			return false;
		}
		
		# Compile the transliterations
		/* Equivalent for a root build is:
			cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/xml/
			make all-tables
			cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/
			make clean
			perl Makefile.PL
			make
			make install
		*/
		#!# PL_FILES may be needed to get it to read the local Tables.pm, but a workaround has been put in in the installer for now
		$command = "cd {$translitDir}/xml/ && make all-tables && cd {$translitDir}/ && make clean && perl Makefile.PL INSTALL_BASE={$translitDir} && make && make install";
		exec ($command, $output, $unixReturnValue);
		if ($unixReturnValue != 0) {
			$errorHtml = "Error (return status: <em>{$unixReturnValue}</em>) recompiling the transliterations: <tt>" . application::htmlUl ($output) . "</tt>";
			return false;
		}
		
		# Signal success
		return true;
	}
	
	
	# Function to return the reverse-transliteration definition
	private function getReverseTransliterationDefinition ()
	{
		# Get the latest version
		$query = "SELECT definition FROM {$this->settings['database']}.reversetransliterationdefinition ORDER BY id DESC LIMIT 1;";
		if (!$definition = $this->databaseConnection->getOneField ($query, 'definition')) {
			echo "\n<p class=\"warning\"><strong>Error:</strong> The reverse-transliteration definition could not be retrieved.</p>";
			return false;
		}
		
		# Return the string
		return $definition;
	}
	
	
	# Merge definition
	public function merge ()
	{
		# Start the HTML
		$html  = '';
		
		# Display a flash message if set
		#!# Flash message support needs to be added to ultimateForm natively, as this is a common use-case
		$successMessage = 'The definition has been updated.';
		if ($flashValue = application::getFlashMessage ('submission', $this->baseUrl . '/')) {
			$message = "\n" . "<p>{$this->tick} <strong>" . $successMessage . '</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'display' => 'paragraphs',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->heading ('p', "Here you can define the translation for merging.");
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Merge definition',
			'required'	=> true,
			'rows'		=> 30,
			'cols'		=> 120,
			'default'	=> $this->getMergeDefinition (),
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				$this->parseMergeDefinition ($unfinalisedData['definition'], $errorString);
				if ($errorString) {
					$form->registerProblem ('compilefailure', $errorString);
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Save the latest version
			$this->databaseConnection->insert ($this->settings['database'], 'mergedefinition', array ('definition' => $result['definition']));
			
			# Save an archive copy to the tables directory
			file_put_contents ($this->applicationRoot . '/tables/mergeDefinition.tsv', $result['definition']);
			
			# Set a flash message
			$function = __FUNCTION__;
			$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/{$this->actions[$function]['url']}";
			$redirectMessage = "\n{$this->tick}" . ' <strong>' . $successMessage . '</strong></p>';
			application::setFlashMessage ('submission', '1', $redirectTo, $redirectMessage, $this->baseUrl . '/');
			
			# Confirm success, resetting the HTML, and show the submission
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to process the merge definition
	private function parseMergeDefinition ($tsv, &$errorString = '')
	{
		# Convert the TSV to an associative array
		$tsv = trim ($tsv);
		require_once ('csv.php');
		$mergeDefinitionRaw = csv::tsvToArray ($tsv, $firstColumnIsId = true, $firstColumnIsIdIncludeInData = false, $errorMessage, $skipRowsEmptyFirstCell = true);
		
		# Rearrange by strategy
		$mergeDefinition = array ();
		foreach ($this->mergeTypes as $mergeType => $label) {
			foreach ($mergeDefinitionRaw as $marcFieldCode => $attributes) {
				$mergeDefinition[$mergeType][$marcFieldCode] = (strlen ($attributes[$mergeType]) ? $attributes['ACTION'] : false);
			}
		}
		
		# Return the definition
		return $mergeDefinition;
	}
	
	
	# Function to return the merge definition
	private function getMergeDefinition ()
	{
		# Get the latest version
		$query = "SELECT definition FROM {$this->settings['database']}.mergedefinition ORDER BY id DESC LIMIT 1;";
		if (!$definition = $this->databaseConnection->getOneField ($query, 'definition')) {
			echo "\n<p class=\"warning\"><strong>Error:</strong> The merge definition could not be retrieved.</p>";
			return false;
		}
		
		# Return the string
		return $definition;
	}
	
	
	# Function to get a list of supported macros
	private function getSupportedMacros ()
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
	
	
	# Function to process strings through macros; macros should return a processed string, or false upon failure
	private function processMacros ($xml, $string, $macros, $authorsFields)
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
				$string = $this->{$macroMethod} ($string, $xml, NULL, $authorsFields);
			} else {
				$string = $this->{$macroMethod} ($string, $xml, $parameter, $authorsFields);
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
		
		# Determine the subfield, by performing a validation; see: https://github.com/davemontalvo/ISBN-Tools/blob/master/isbn_tools.php ; seems to permit EANs like 5391519681503 in /records/211150/
		require_once ('ISBN-Tools/isbn_tools.php');
		$isValid = (validateISBN ($value));
		$subfield = $this->doubleDagger . ($isValid ? 'a' : 'z');
		
		# Assemble the return value, adding qualifying information if required
		$string = $subfield . $value;
		if ($q) {
			$string .= "{$this->doubleDagger}q" . $q;
		}
		
		# Return the value
		return $string;
	}
	
	
	# URL fixing
	#!# Ideally get rid of this once the data is fixed up
	private function macro_urlFix ($value)
	{
		# Add http:// if not at start
		if (!preg_match ('~^(http|https|ftp)://~', $value)) {
			$value = 'http://' . $value;
		}
		
		# Return the value, possibly modified
		return $value;
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
		return ucfirst ($value);
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
	private function macro_implode ($value, $xml, $parameter)
	{
		# Return empty string if no values
		if (!$value) {return '';}
		
		# Implode
		
		return implode ($parameter, $value);
	}
	
	
	# Macro to create 260;  $a and $b are grouped as there may be more than one publisher, e.g. /records/76743/ ; see: https://www.loc.gov/marc/bibliographic/bd260.html
	private function macro_generate260 ($value_ignored, $xml)
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
		
		# Add $c if present
		if ($dValues = $this->xPathValues ($xml, '(//d)[%i]', false)) {
			if ($result) {$result .= ',';}
			$result .= "{$this->doubleDagger}c" . implode (', ', $dValues);
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
		
		# Obtain *p or *pt
		$pValues = $this->xPathValues ($xml, '(//p)[%i]', false);	// Multiple *p, e.g. /records/6002/ , /records/15711/
		$p = ($pValues ? implode ('; ', $pValues) : '');
		$pt = $this->xPathValue ($xml, '//pt');
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
		
		# Add space between the number and the 'p.' or 'v.' ; e.g. /records/49133/ for p. ; NB No actual cases for v. in the data
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
			$result = ($recordType == '/ser' ? 'v.' : '1 volume (unpaged)');	// e.g. /records/1000/ , /records/1332/
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
	public function macro_nfCount ($value, $xml, $language = false)
	{
		# Get the leading articles list, indexed by language
		$leadingArticles = $this->leadingArticles ();
		
		# If a forced language is not specified, obtain the language value for the record
		if (!$language) {
			$xPath = '//lang[1]';	// Choose first only
			$language = $this->xPathValue ($xml, $xPath);
		}
		
		# If no language specified, choose 'English'
		if (!strlen ($language)) {$language = 'English';}
		
		# End if the language is not in the list of leading articles
		if (!isSet ($leadingArticles[$language])) {return '0';}
		
		# Work through each leading article, and if a match is found, return the string length
		foreach ($leadingArticles[$language] as $leadingArticle) {
			if (preg_match ("/^{$leadingArticle} /i", $value)) {	// Case-insensitive match
				return (string) (strlen ($leadingArticle) + 1); // Include the space
			}
		}
		
		# Return '0' by default
		return '0';
	}
	
	
	# Macro to set an indicator based on the presence of a 100/110 field; e.g. /records/1844/
	private function macro_indicator1xxPresent ($defaultValue, $xml, $setValueIfAuthorsPresent, $authorsFields)
	{
		# If authors field present, return the new value
		if (strlen ($authorsFields['default'][100]) || strlen ($authorsFields['default'][110]) || strlen ($authorsFields['default'][111])) {
			return $setValueIfAuthorsPresent;
		}
		
		# Otherwise return the default
		return $defaultValue;
	}
	
	
	# Lookup table for leading articles in various languages; note that Russian has no leading articles; see: https://en.wikipedia.org/wiki/Article_%28grammar%29#Variation_among_languages
	private function leadingArticles ()
	{
		# Define the leading articles
		$leadingArticles = array (
			'a' => 'English glg Hungarian Portuguese',
			'al-' => 'ara',
			'an' => 'English',
			'ane' => 'enm',
			'das' => 'German',
			'de' => 'Danish Swedish',
			'dem' => 'German',
			'den' => 'Danish German Swedish',
			'der' => 'German',
			'det' => 'Danish German Swedish',
			'die' => 'German',
			'een' => 'Dutch',
			'ei' => 'Norwegian',
			'ein' => 'German Norwegian',
			'eine' => 'German',
			'einem' => 'German',
			'einen' => 'German',
			'einer' => 'German',
			'eines' => 'German',
			'eit' => 'Norwegian',
			'el' => 'Spanish',
			'els' => 'Catalan',
			'en' => 'Danish Norwegian Swedish',
			'et' => 'Danish Norwegian',
			'ett' => 'Swedish',
			'gl' => 'Italian',
			'gli' => 'Italian',
			'ha' => 'Hebrew',
			'het' => 'Dutch',
			'ho' => 'grc',
			'il' => 'Italian mlt',
			'l' => 'Catalan French Italian mlt',
			'la' => 'Catalan French Italian Spanish',
			'las' => 'Spanish',
			'le' => 'French Italian',
			'les' => 'Catalan French',
			'lo' => 'Italian Spanish',
			'los' => 'Spanish',
			'os' => 'Portuguese',
			#!# Codes still present
			'ta' => 'grc',
			'ton' => 'grc',
			'the' => 'English',
			'um' => 'Portuguese',
			'uma' => 'Portuguese',
			'un' => 'Catalan Spanish French Italian',
			'una' => 'Catalan Spanish Italian',
			'une' => 'French',
			'uno' => 'Italian',
			'y' => 'wel',
		);
		
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
		$sortByStringLength = create_function ('$a, $b', 'return strlen ($b) - strlen ($a);');
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
					$recordId = $this->xPathValue ($xml, '//q0');
					echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$recordId}/\">record #{$recordId}</a>:</strong> the record included a language note but the language '<em>{$language}</em>'.</p>";
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
	public function macro_transliterateSubfields ($value, $xml, $applyToSubfields, $authorsFields_ignored, $language = false)
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
		#!# Old transliteration needs to be upgraded in catalogue_processed and here in MARC generation
		$output = $this->transliterateBgnLatinToCyrillic ($value, $language);
		
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
		$string .= '00000';
		
		# Position 17: Encoding level: One-character alphanumeric code that indicates the fullness of the bibliographic information and/or content designation of the MARC record. 
		$string .= '#';
		
		# Position 18: Descriptive cataloguing form
		$string .= 'a';	// Denotes AACR2
		
		# Position 19: Multipart resource record level
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
	#!# Copied from generate008 class
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
			'Videorecording'		=> 'vu#|u||u|',
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
			$recordId = $this->xPathValue ($xml, '//q0');
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$recordId}/\">record #{$recordId}</a>:</strong> " . htmlspecialchars ($error) . '.</p>';
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
	private function macro_generateAuthors ($value, $xml, $arg, $authorsFields)
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
		return $authorsFields[$languageMode][$fieldNumber];
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
			foreach ($lines as $multilineSubfield => $line) {
				$values[] = $this->construct880Subfield6Line ($line[2], $line[1], $this->field880subfield6FieldInstanceIndex[$masterField], $multilineSubfield);
			}
			
			# Compile the result back to a multiline string
			$value = implode ("\n" . '880 ', $values);
			
		} else {
			
			# Render the line
			$value = $this->construct880Subfield6Line ($value, $masterField, $this->field880subfield6FieldInstanceIndex[$masterField]);
		}
		
		# Return the modified value
		return $value;
	}
	
	
	# Helper function to render a 880 subfield 6 line
	private function construct880Subfield6Line ($line, $masterField, $fieldInstance, $multilineSubfield = false)
	{
		# Advance the index, which is incremented globally across the record; starting from 1
		$this->field880subfield6Index++;
		
		# Assemble the subfield
		$indexFormatted = str_pad ($this->field880subfield6Index, 2, '0', STR_PAD_LEFT);
		$subfield6 = $this->doubleDagger . '6 ' . $masterField . '-' . $indexFormatted;
		
		# Insert the subfield after the indicators; this is similar to insertSubfieldAfterMarcFieldThenIndicators but without the initial MARC field number
		$line = preg_replace ('/^(.{2}) (.+)$/', "\\1 {$subfield6} \\2", $line);
		
		# Register the link so that the reciprocal link can be added within the master field; this is registered either as an array (representing parts of a multiline string) or a string (for a standard field)
		$fieldKey = $masterField . '_' . $fieldInstance;	// e.g. 700_0
		$linkToken = $this->doubleDagger . '6 ' . '880' . '-' . $indexFormatted;
		if ($multilineSubfield !== false) {		// i.e. has supplied value
			$this->field880subfield6ReciprocalLinks[$fieldKey][$multilineSubfield] = $linkToken;
		} else {
			$this->field880subfield6ReciprocalLinks[$fieldKey] = $linkToken;
		}
		
		# Return the line
		return $line;
	}
	
	
	# Macro for generating the 245 field
	private function macro_generate245 ($value, $xml, $flag, $authorsFields)
	{
		# If running in transliteration mode, require a supported language
		$languageMode = 'default';
		if ($flag == 'transliterated') {
			if (!$languageMode = $this->getTransliterationLanguage ($xml)) {return false;}
		}
		
		# Subclass, due to the complexity of this field
		require_once ('generate245.php');
		$generate245 = new generate245 ($this, $xml, $authorsFields, $languageMode);
		if (!$value = $generate245->main ($error)) {
			$recordId = $this->xPathValue ($xml, '//q0');
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$recordId}/\">record #{$recordId}</a>:</strong> " . htmlspecialchars ($error) . '.</p>';
		}
		
		# Return the value
		return $value;
	}
	
	
	# Macro for generating the 250 $b subfield
	private function macro_generate250b ($value, $xml, $ignored, $authorsFields)
	{
		# Use the role-and-siblings part of the 245 processor
		require_once ('generate245.php');
		$generate245 = new generate245 ($this, $xml, $authorsFields);
		
		# Create the list of subvalues if there is *ee?; e.g. /records/3887/ , /records/7017/ (has multiple *ee and multiple *n within this) , /records/45901/ , /records/168490/
		$subValues = array ();
		$eeIndex = 1;
		while ($this->xPathValue ($xml, "//ee[$eeIndex]")) {		// Check if *ee container exists
			$subValues[] = $generate245->roleAndSiblings ("//ee[$eeIndex]");
			$eeIndex++;
		}
		
		# Return false if no subvalues
		if (!$subValues) {return false;}
		
		# Implode values
		$value = implode ('; ', $subValues);
		
		# Ensure the value ends with a dot (if punctuation not already present)
		$value = $this->macro_dotEnd ($value, NULL, $extendedCharacterList = true);
		
		# Return the value
		return $value;
	}
	
	
	# Macro for generating the 490 field
	private function macro_generate490 ($ts, $xml, $ignored, $authorsFieldsIgnored, &$matchedRegexp = false)
	{
		# Obtain the *ts value or end
		if (!$ts) {return false;}
		
		# Series titles: 
		# Decided not to treat "Series [0-9]+$" as a special case that avoids the splitting into $a... ;$v...
		# This is because there is clear inconsistency in the records, e.g.: "Field Columbian Museum, Zoological Series 2", "Burt Franklin Research and Source Works Series 60"
		
		# Load the regexp list
		$regexpsBase = $this->oneColumnTableToList ('volumeRegexps.txt');
		
		# Add implicit boundaries to each regexp
		$regexps = array ();
		foreach ($regexpsBase as $index => $regexp) {
			$regexps[$index] = '^(.+)\s+(' . $regexp . ')$';
		}
		
		# Ensure the matched regexp is reset
		$matchedRegexp = false;
		
		# Normalise any trailing volume number strings
		$i = 0;
		foreach ($regexps as $index => $regexp) {
			$i++;
			
			# Find the first match, then stop
			$delimeter = '~';	// Known not to be in the list
			if (preg_match ($delimeter . $regexp . $delimeter, $ts, $matches)) {	// Regexps are permitted to have their own captures; matches 3 onwards are just ignored
				$seriesTitle = $matches[1];
				$volumeNumber = $matches[2];
				$matchedRegexp = $i . ': ' . $regexpsBase[$index];	// Pass back by reference the matched regexp
				break;	// Relevant regexp found
			}
			
			# If no match, treat as simple series title without volume number
			$seriesTitle = $ts;
			$volumeNumber = NULL;
		}
		
		# If there is a *vno, add that
		if ($xml) {		// I.e. if running in MARC generation context, rather than for report generation
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
	
	
	# Macro for generating the 541 field, which looks at *acq groups; see: https://www.loc.gov/marc/bibliographic/bd541.html
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
		if (in_array ($value, $this->overloadedKsTokens)) {return false;}
		
		# Ensure the value is in the table
		if (!isSet ($this->udcTranslations[$value])) {
			$recordId = $this->xPathValue ($xml, '//q0');
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$recordId}/\">record #{$recordId}</a>:</strong> 650 UDC field '<em>{$value}</em>' is not a valid UDC code.</p>";
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
			$recordId = $this->xPathValue ($xml, '//q0');
			echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$recordId}/\">record #{$recordId}</a>:</strong> 650 PGA field {$value} is not a valid PGA code letter.</p>";
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
		$lookupTable = $this->unescapeMuscatAsterisks ($lookupTable);
		
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
		$diacriticsTable = $this->diacriticsTable ();
		
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
			if (strlen ($values[$field]) != $expectedLength) {
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
	
	
	# Macro to generate the 773 (Host Item Entry) field; see: http://www.loc.gov/marc/bibliographic/bd773.html ; e.g. /records/2071/
	#!# 773 is not currently being generated for /art/j analytics (generally *location=Periodical); this is because of the *kg check below; the spec needs to define some implementation for this; for *location=Pam, the same information goes in a 500 field rather than a 773; again this needs a spec
	private function macro_generate773 ($value, $xml)
	{
		# Start a result
		$result = '';
		
		# Get *kg or end; records will usually be /art/in or /art/j only, but there are some /doc records
		if (!$hostId = $this->xPathValue ($xml, '//k2/kg')) {return false;}	// Data checked that no records contain more than one *kg
		
		# Obtain the processed MARC record; note that createMarcRecords processes the /doc records before /art/in records
		if (!$hostRecord = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_marc', 'marc', $conditions = array ('id' => $hostId))) {
			
			# If the record exists as XML, this simply means the host MARC record has not yet been processed, therefore register for the child for reprocessing in the second pass phase; otherwise this is a genuine error
			$recordId = $this->xPathValue ($xml, '//q0');
			if ($hostRecordXmlExists = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_xml', 'id', $conditions = array ('id' => $hostId))) {
				$this->marcSecondPass[] = $recordId;
			} else {	// Real error, to be reported
				echo "\n<p class=\"warning\"><strong>Error in <a href=\"{$this->baseUrl}/records/{$recordId}/\">record #{$recordId}</a>:</strong> Cannot match *kg, as there is no host record <a href=\"{$this->baseUrl}/records/{$hostId}/\">#{$hostId}</a>.</p>";
			}
			return false;
		}
		
		# Parse out the host record
		$marc = $this->parseMarcRecord ($hostRecord);
		
		# Obtain the record type
		$recordType = $this->recordType ($xml);
		
		# Start a list of subfields
		$subfields = array ();
		
		# Add 773 ‡a: Copy in the 1XX (Main entry heading) from the host record, omitting subfield codes; *art/*in records only
		if ($recordType == '/art/in') {
			if (isSet ($marc['100'])) {
				$subfields[] = $this->combineSubfieldValues ('a', $marc['100']);
			}
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
					$marc['260'][0]['subfields']['_'][0] = rtrim ($marc['260'][0]['subfields']['b'][0]) . ', ' . $marc['260'][0]['subfields']['c'][0];	// Make a virtual field, $_
					unset ($marc['260'][0]['subfields']['b']);
					unset ($marc['260'][0]['subfields']['c']);
				}
				
				# Split by colon
				$subfields[] = $this->combineSubfieldValues ('d', $marc['260'], array (), ': ');
			}
		}
		
		# Add 773 ‡g: *pt (Related parts) [of child record, i.e. not host record]; *art/*j only
		if ($recordType == '/art/j') {
			if ($pt = $this->xPathValue ($xml, '/art/j/pt')) {	// e.g. /records/14527/
				$subfields[] = "{$this->doubleDagger}g" . $pt;
			}
		}
		
		# Add 773 ‡w: Copy in the 001 (Record control number) from the host record; this will need to be modified in the target Voyager system post-import
		#!# For one of the merge strategies, this number will be known
		$subfields[] = "{$this->doubleDagger}w" . $marc['001'][0]['line'];
		
		# Compile the result
		$result = implode (' ', $subfields);
		
		# Return the result
		return $result;
	}
	
	
	# Function to combine subfield values in a line to a single string
	private function combineSubfieldValues ($parentSubfield, $field, $onlySubfields = array (), $implodeSubfields = ', ', $stripLeadingArticleLanguage = false)
	{
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
		$string = preg_replace ("/^((?:{$list}) )(.+)$/i", '\2', $string);
		$string = ucfirst ($string);
		
		# Return the amended string
		return $string;
	}
	
	
	# Macro to parse out a MARC record into subfields
	private function parseMarcRecord ($marc)
	{
		# Parse the record
		preg_match_all ('/^([LDR0-9]{3}) (?:([#0-9]{2}) )?(.+)$/mu', $marc, $matches, PREG_SET_ORDER);
		
		# Convert to key-value pairs; in the case of repeated records, the value is converted to an array
		$record = array ();
		foreach ($matches as $match) {
			$fieldNumber = $match[1];
			$record[$fieldNumber][] = array (
				'line'			=> $match[3],
				'indicators'	=> $match[2],
				'subfields'		=> $this->parseSubfieldsToPairs ($match[3]),
			);
		}
		
		// application::dumpData ($record);
		
		# Return the record
		return $record;
	}
	
	
	# Function to parse subfields into key-value pairs
	private function parseSubfieldsToPairs ($line)
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
			$subfields[$subfield][] = $string;
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
	
	
	# Macro to determine cataloguing status
	private function macro_cataloguingStatus ($value, $xml)
	{
		# Return *ks if on the list; separate multiple values with semicolon, e.g. /records/205603/
		$ksValues = $this->xPathValues ($xml, '//k[%i]/ks');
		$results = array ();
		foreach ($ksValues as $ks) {
			if (in_array ($ks, $this->overloadedKsTokens)) {
				$results[] = $ks;
			}
		}
		if ($results) {
			return implode ('; ', $results);
		}
		
		# Otherwise return *status; e.g. /records/1373/
		$status = $this->xPathValue ($xml, '//status');
		return $status;
	}
	
	
	
	/* Reports */
	
	
	# Naming report
	private function report_q0naming ()
	{
		# Define the query
		$query = "
			SELECT
				'q0naming' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist NOT LIKE '%@q0@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Naming report
	private function report_missingcategory ()
	{
		# Define the query
		$query = "
			SELECT
				'missingcategory' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist NOT REGEXP '@(doc|art|ser)@'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without a *d that are not *ser and either no status or status is GLACIOPAMS
	private function report_missingd ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'missingd' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist NOT LIKE '%@d@%'
				AND fieldslist NOT LIKE '%@ser@%'
				AND (
					   fieldslist NOT LIKE '%@status@%'
					OR (field = 'status' AND value = 'GLACIOPAMS')
				)
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without a *acc
	private function report_missingacc ()
	{
		# Define the query
		$query = "
			SELECT
				'missingacc' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist NOT LIKE '%@acc@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without a *t
	private function report_missingt ()
	{
		# Define the query
		$query = "
			SELECT
				'missingt' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE EXTRACTVALUE(xml, 'count(/*/tg/t)') = 0
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an empty *t/*tt/*tc
	private function report_emptytvariants ()
	{
		# Define the query
		$query = "
			SELECT
				'emptytvariants' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field IN ('t', 'tt', 'tc')
				AND value = ''
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose Library of Congress Cryrillic title upgrade will fail due to defective *t problems
	private function report_failinglocupgrade ()
	{
		# Define the query
		$query = "
			SELECT
				'failinglocupgrade' AS report,
				recordId
			FROM transliterations
			WHERE
				shardId IS NULL
				/* The following filters can be applied to remove known problems, present in /reports/missingt/ , /reports/emptytvariants/ , /reports/multipletopt/ ; when those reports are fixed, the present report should give no errors */
				-- AND recordId NOT IN (SELECT id FROM catalogue_xml WHERE EXTRACTVALUE(xml, 'count(/*/tg/t)') > 1)
				-- AND title NOT LIKE '[[%'
				-- AND title != ''
		";
		
		# Return the query
		return $query;
	}
	
	
	# *ser records without a *r, except where location is Not in SPRI
	private function report_sermissingr ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'sermissingr' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@ser@%'
				AND fieldslist NOT LIKE '%@r@%'
				AND field = 'location'
				AND value != 'Not in SPRI'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *art records where there is no *loc and no *status
	private function report_artwithoutlocstatus ()
	{
		# Define the query
		$query = "
			SELECT
				'artwithoutloc' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@art@%'
			  AND fieldslist NOT LIKE '%@loc@%'
			  AND fieldslist NOT LIKE '%@status@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without exactly one *tc
	private function report_tcnotone ()
	{
		# Define the query
		$query = "
			SELECT
				'tcnotone' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE ((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@tc@',''))) / LENGTH('@tc@')) != 1
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose *tg count does not match *t
	private function report_tgmismatch ()
	{
		# Define the query; uses substring count method in comments at: http://www.thingy-ma-jig.co.uk/blog/17-02-2010/mysql-count-occurrences-string
		$query = "
			SELECT
				'tgmismatch' AS report,
				id
			FROM fieldsindex
			WHERE
				((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@t@',''))) / LENGTH('@t@')) !=
				((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@tg@',''))) / LENGTH('@tg@'))
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without a *rpl
	private function report_missingrpl ()
	{
		# Define the query
		$query = "
			SELECT
				'missingrpl' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist NOT LIKE '%@rpl@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records in SPRI without a *rpl and without a *status, that are not *ser
	private function report_missingrplstatus ()
	{
		# Define the query
		$query = "
			SELECT
				'missingrplstatus' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist NOT LIKE '%@ser@%'
				AND fieldslist NOT LIKE '%@rpl@%'
				AND fieldslist NOT LIKE '%@status@%'
				AND field = 'location'
				AND value != 'Not in SPRI'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records having only one *rpl and *rpl is O
	private function report_rploncewitho ()
	{
		# Define the query
		$query = "
			SELECT
				'rploncewitho' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@rpl@%'
				AND ((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@rpl@',''))) / LENGTH('@rpl@')) = 1
				AND field = 'rpl'
				AND value = 'O'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records having *rpl not matching [A-Z0-9]{1,3}
	private function report_rpl3charaz09 ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'rpl3charaz09' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    field = 'rpl'
				AND value NOT REGEXP '^[A-Z0-9]{1,3}$'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *loc records where there is no *location
	private function report_locwithoutlocation ()
	{
		# Define the query
		/*
		$query = "
			SELECT
				'locwithoutlocation' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@loc@%'
			  AND fieldslist NOT LIKE '%@location@%'
			";
		*/
		$query = "
			SELECT DISTINCT
				'locwithoutlocation' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@loc@%'
				AND fieldslist NOT LIKE '%@location@%'
				AND
					(
						fieldslist NOT LIKE '%@status@%'
						OR 
						(
							    fieldslist LIKE '%@status@%'
							AND field = 'status'
							AND value != 'GLACIOPAMS'
						)
					)
			";
		
		# Return the query
		return $query;
	}
	
	
	# records where the number of { doesn't match the number of }
	private function report_unmatchedbrackets ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'unmatchedbrackets' AS report,
				recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				(LENGTH(value)-LENGTH(REPLACE(value,'{','')))/LENGTH('{') !=	/* i.e. substr_count('{') */
				(LENGTH(value)-LENGTH(REPLACE(value,'}','')))/LENGTH('}')		/* i.e. substr_count('}') */
			";
		
		# Return the query
		return $query;
	}
	
	
	# records where brackets are nested, e.g. { { text } }
	private function report_nestedbrackets ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'nestedbrackets' AS report,
				recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    value REGEXP '{([^}]*){'
				AND value REGEXP '}([^{]*)}'
				AND (LENGTH(value)-LENGTH(REPLACE(value,'{','')))/LENGTH('{') = 2
				AND (LENGTH(value)-LENGTH(REPLACE(value,'}','')))/LENGTH('}') = 2
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a *status field
	private function report_status ()
	{
		# Define the query
		$query = "
			SELECT
				'status' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				fieldslist LIKE '%@status@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with more than one *status field
	private function report_multiplestatus ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplestatus' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				fieldslist REGEXP '@status@.*status@'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with *status = Parallel
	private function report_statusparallel ()
	{
		# Define the query
		$query = "
			SELECT
				'statusparallel' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				field = 'status'
				AND value LIKE '%PARALLEL%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a *status field where the status is not GLACIOPAMS
	private function report_statusglaciopams ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'statusglaciopams' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@status@%'
				AND field = 'status'
				AND value != 'GLACIOPAMS'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a *status field and *location where the status is not GLACIOPAMS
	private function report_statuslocationglaciopams ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'statuslocationglaciopams' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@status@%'
				AND fieldslist LIKE '%@location@%'
				AND field = 'status'
				AND value != 'GLACIOPAMS'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records with one *location, which is Periodical
	private function report_doclocationperiodical ()
	{
		# Define the query
		$query = "
			SELECT
				'doclocationperiodical' AS report,
				catalogue_xml.id AS recordId
			FROM catalogue_xml
			LEFT JOIN fieldsindex ON catalogue_xml.id = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@doc@%'
				AND (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@location@','')))/LENGTH('@location@') = 1		/* NOT two locations, i.e. exactly one */
				AND EXTRACTVALUE(xml, '//location') = 'Periodical'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records with two or more *locations, at least one of which is Periodical
	private function report_doclocationlocationperiodical ()
	{
		# Define the query
		$query = "
			SELECT
				'doclocationlocationperiodical' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@doc@%'
				AND fieldslist LIKE '%@location@%'
				AND (fieldslist REGEXP '@location@.*@location@' OR fieldslist LIKE '%@location@location@%')	/* At least two locations */
				AND field = 'location'
				AND value = 'Periodical'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *in records which have a *j
	private function report_inwithj ()
	{
		# Define the query
		$query = "
			SELECT
				'inwithj' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@in@%'
			  AND fieldslist LIKE '%@j@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *art records with a *j where *t does not immediately follow *j
	private function report_artnotjt ()
	{
		# Define the query
		$query = "
			SELECT
				'artnotjt' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@art@%'
			  AND fieldslist LIKE '%@j@%'
			  AND fieldslist NOT LIKE '%@j@tg@t@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *ser records where t is not unique
	private function report_sernonuniquet ()
	{
		# Define the query
		$query = "
			SELECT
				'sernonuniquet' AS report,
				id AS recordId
			FROM fieldsindex
			INNER JOIN (
				SELECT title
				FROM fieldsindex
				WHERE fieldslist LIKE '%@ser@%'
				GROUP BY title
				HAVING COUNT(id) > 1
			) AS subquerytable
			ON fieldsindex.title = subquerytable.title
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records classified as articles which need to become documents
	private function report_artbecomedoc ()
	{
		# Define the query
		$query = "
			SELECT
				'artbecomedoc' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@art@%'
				AND fieldslist NOT LIKE  '%@winlink@%'
				AND fieldslist LIKE  '%@j@tg@t@%'			/* Looking for articles in journals */
				AND fieldslist NOT LIKE  '%@status@%'		/* It has been processed */
				AND fieldslist LIKE  '%@loc@%'
				AND field = 'location'
				AND value NOT LIKE 'Pam%'
				AND value NOT LIKE 'Special Collection%'
				AND value NOT LIKE 'Basement%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *art records with a top-level *p
	private function report_arttoplevelp ()
	{
		# Define the query
		$query = "
			SELECT
				'arttoplevelp' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist REGEXP '@art@.+@p@.*@?(in|j)@'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with *in but no *kg
	private function report_artinnokg ()
	{
		# Define the query
		$query = "
			SELECT
				'artinnokg' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@in@%'
			  AND fieldslist NOT LIKE '%@kg@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with *in but no *kg, excluding records where the location is 'Pam*' or 'Not in SPRI'
	private function report_artinnokglocation ()
	{
		# Define the query
		$query = "
			SELECT
				'artinnokglocation' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@in@%'
				AND fieldslist NOT LIKE '%@kg@%'
				AND fieldslist REGEXP '@location@'
				AND field = 'location'
				AND (
					    value NOT LIKE 'Pam%'
					AND value != 'Not in SPRI'
				)
			";
		
		# Return the query
		return $query;
	}
	
	
	# Linked analytics: *art records with *k2
	private function report_artwithk2 ()
	{
		# Define the query
		$query = "
			SELECT
				'artwithk2' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@art@%'
			  AND fieldslist LIKE '%@k2@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records with *kb
	private function report_docwithkb ()
	{
		# Define the query
		$query = "
			SELECT
				'docwithkb' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@doc@%'
			  AND fieldslist LIKE '%@kb@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with two or more locations, having first filtered out any locations whose location is 'Not in SPRI'
	private function report_loclocfiltered1 ()
	{
		# Define the query
		$query = "
			SELECT
				'loclocfiltered1' AS report,
				recordId
			FROM (
				/* Subquery to create fields index */
				SELECT
					recordId,
					CONCAT('@', GROUP_CONCAT(`field` SEPARATOR '@'),'@') AS fieldslist
				FROM (
					/* Subquery to create records but with whitelisted terms taken out */
					SELECT
						recordId,field	/* Limit for efficiency */
					FROM catalogue_rawdata
					WHERE NOT (field = 'location' AND value = 'Not in SPRI')
				) AS rawdata_filtered
				GROUP BY recordId
			) AS fieldsindex
			WHERE fieldslist REGEXP '@location.*@location'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with two or more locations, having first filtered out any locations whose location is 'Not in SPRI' or 'Periodical' or 'Basement IGS Collection'
	private function report_loclocfiltered2 ()
	{
		# Define the query
		$query = "
			SELECT
				'loclocfiltered2' AS report,
				recordId
			FROM (
				/* Subquery to create fields index */
				SELECT
					recordId,
					CONCAT('@', GROUP_CONCAT(`field` SEPARATOR '@'),'@') AS fieldslist
				FROM (
					/* Subquery to create records but with whitelisted terms taken out */
					SELECT
						recordId,field	/* Limit for efficiency */
					FROM catalogue_rawdata
					WHERE NOT (field = 'location' AND (
						value IN('Not in SPRI', 'Periodical', 'Basement IGS Collection')
						OR
						value LIKE 'Basement Seligman %'
					))
				) AS rawdata_filtered
				GROUP BY recordId
			) AS fieldsindex
			WHERE fieldslist REGEXP '@location.*@location'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records where no location is 'Not in SPRI', having first filtered out any matching a whitelist of internal locations
	/*
		i.e.
		1) Ignore locations within a record that are on the whitelist of internal locations
		2) Ignore any records which have a location 'Not in SPRI'
		3) List records that have any locations left
		
		Note that "Not in SPRI" should be treated as a special case beyond the general rule:
		- Example: locations are: "Not in SPRI" and "Library of Congress" --> DOES NOT appear in report
		- Example: locations are: "Periodical" and "Library of Congress" --> DOES appear in report
	*/
	private function report_externallocations ()
	{
		# Define the query
		$query = "
		SELECT
			'externallocations' AS report,
			recordId
		FROM
			(
				/* Obtain list of records with all locations grouped into one value, but filter out known fine records */
				SELECT
					recordId,
					CONCAT('|', GROUP_CONCAT(value SEPARATOR '||'), '|') AS all_locations
				FROM catalogue_processed
				WHERE
					    field = 'location'
					AND value NOT REGEXP \"^(" . implode ('|', array_keys ($this->locationCodes)) . ")\"
					AND value NOT REGEXP '^(IGS|International Glaciological Society|Shelved with)'
					AND value != '??'	/* Not done in the regexp to avoid possible backlash-related errors */
				GROUP BY recordId
			) AS rawdata_combined
			WHERE
				/* If 'Not in SPRI' is present anywhere, then any other location values are irrelevant */
				    all_locations NOT LIKE '%|Not in SPRI|%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with only one location, which is not on the whitelist
	private function report_singleexternallocation ()
	{
		# Define the query
		$query = "
			SELECT
				'singleexternallocation' AS report,
				recordId
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist NOT REGEXP '@location.*@location@'
				AND field = 'location'
				AND value NOT REGEXP \"^(" . implode ('|', array_keys ($this->locationCodes)) . ")\"
				AND value NOT REGEXP '^(IGS|International Glaciological Society|Shelved with)'
				AND value != 'Not in SPRI'
				AND value != '??'	/* Not done in the regexp to avoid possible backlash-related errors */
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with three or more locations
	private function report_loclocloc ()
	{
		# Define the query
		#!# NB This requires the XML representation to have been compiled first
		$query = "
			SELECT
				'loclocloc' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist REGEXP '@location.*@location.*@location'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Articles without a matching serial title in another record: orphaned works, that are not pamphlets or in the special collection
	private function report_arttitlenoser ()
	{
		# Define the query; see: http://stackoverflow.com/a/367865 and http://stackoverflow.com/a/350180
		/*
			16525 - no *exact* match
			
			
		*/
		$query = "
			SELECT
				'arttitlenoser' AS report,
				id AS recordId
			FROM (
				
				/* Subquery to extract the serial title within the record, where the record is not a pamphlet */
				/* NB As of 8/May/2014: matches 89,603 records */
				SELECT
					id,
					EXTRACTVALUE(xml, 'art/j/tg/t') AS title
				FROM catalogue_xml
					WHERE
						EXTRACTVALUE(xml, 'art/j') != ''							/* I.e. is in journal */
					AND EXTRACTVALUE(xml, 'art/j/loc/location') NOT LIKE 'Pam %'	/* I.e. has a location which is not pamphlet */
					AND EXTRACTVALUE(xml, 'art/j/loc/location') NOT LIKE 'Special Collection %'	/* I.e. has a location which is not in the special collection (historic materials, bound copies together, early pamphlets) */
					AND EXTRACTVALUE(xml, 'status') = ''
				) AS articles
				
			LEFT OUTER JOIN (
				
				/* Subquery to extract the title from the parent serials */
				SELECT
					EXTRACTVALUE(xml, 'ser/tg/t') AS title
				FROM catalogue_xml
					WHERE EXTRACTVALUE(xml, 'ser/tg/t') != ''		/* Implicit within this that it is a serial */
				) AS serials
				
			ON (articles.title = serials.title)
			WHERE serials.title IS NULL
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items not in SPRI
	private function report_notinspri ()
	{
		# Define the query
		$query = "
			SELECT
				'notinspri' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@location@%'
				AND field = 'location'
				AND value LIKE 'Not in SPRI'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with location matching Cambridge University, not in SPRI
	private function report_loccamuninotinspri ()
	{
		# Define the query
		$query = "
			SELECT
				'loccamuninotinspri' AS report,
				id AS recordId
				FROM catalogue_xml
				WHERE
					    EXTRACTVALUE(xml, '//location') LIKE '%Cambridge University%'
					AND EXTRACTVALUE(xml, '//location') LIKE '%Not in SPRI%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with location matching Cambridge University, in SPRI
	private function report_loccamuniinspri ()
	{
		# Define the query
		$query = "
			SELECT
				'loccamuniinspri' AS report,
				id AS recordId
				FROM catalogue_xml
				WHERE
					    EXTRACTVALUE(xml, '//location') LIKE '%Cambridge University%'
					AND EXTRACTVALUE(xml, '//location') NOT LIKE '%Not in SPRI%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items on order or cancelled
	private function report_onordercancelled ()
	{
		# Define the query
		$query = "
			SELECT
				'onordercancelled' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@status@%'
				AND field = 'status'
				AND value IN ('On Order', 'On Order (O/P)', 'On Order (O/S)', 'Order Cancelled')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items with an invalid acquisition date
	private function report_invalidacquisitiondate ()
	{
		# Define the query
		$query = "
			SELECT
				'invalidacquisitiondate' AS report,
				id AS recordId
				FROM catalogue_xml
				WHERE
					(
						    EXTRACTVALUE(xml, '//acq[1]/date') REGEXP '.+'
						AND EXTRACTVALUE(xml, '//acq[1]/date') NOT REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'	-- Require YYYY/MM/DD
						AND EXTRACTVALUE(xml, '//acq[1]/date') NOT REGEXP '^[0-9]{4}$'						-- But also permit year only
					)
					OR
					(
						    EXTRACTVALUE(xml, '//acq[2]/date') REGEXP '.+'
						AND EXTRACTVALUE(xml, '//acq[2]/date') NOT REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'	-- Require YYYY/MM/DD
						AND EXTRACTVALUE(xml, '//acq[2]/date') NOT REGEXP '^[0-9]{4}$'						-- But also permit year only
					)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items on order before 2013/09/01
	private function report_onorderold ()
	{
		# Define the query
		$query = "
			SELECT
				'onorderold' AS report,
				id AS recordId
				FROM catalogue_xml
				WHERE
					    EXTRACTVALUE(xml, '//status') IN ('On Order', 'On Order (O/P)', 'On Order (O/S)')
					AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'
					AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) < UNIX_TIMESTAMP('2013-09-01 00:00:00')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items on order since 2013/09/01
	private function report_onorderrecent ()
	{
		# Define the query
		$query = "
			SELECT
				'onorderrecent' AS report,
				id AS recordId
				FROM catalogue_xml
				WHERE
					    EXTRACTVALUE(xml, '//status') IN ('On Order', 'On Order (O/P)', 'On Order (O/S)')
					AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'
					AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) > UNIX_TIMESTAMP('2013-09-01 00:00:00')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items where the order is cancelled
	private function report_ordercancelled ()
	{
		# Define the query
		$query = "
			SELECT
				'ordercancelled' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@status@%'
				AND field = 'status'
				AND value = 'Order Cancelled'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with italics in the abstract
	private function report_absitalics ()
	{
		# Define the query
		$backslash = '\\';	// PHP representation of one literal backslash
		$query = "
			SELECT DISTINCT
				'absitalics' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    field = 'abs'
				AND value REGEXP '{$backslash}{$backslash}{$backslash}{$backslash}v.+{$backslash}{$backslash}{$backslash}{$backslash}n'	/* MySQL backslash needed twice in REGEXP to make literal */
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with invalid ISBN number, excluding ones known to be wrong in the printed original publication; NB this does *not* perform a full check-digit check - see macro_validisbn for that
	private function report_isbninvalid ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'isbninvalid' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    field = 'isbn'
				AND value NOT REGEXP '^(97(8|9))?[[:digit:]]{9}([[:digit:]]|X)$'		/* http://stackoverflow.com/questions/14419628/regexp-mysql-function */
				AND value NOT REGEXP '^5[0-9]{12}'	/* Multimedia value EANs */
				-- Exclude records whose ISBN is known to be wrong in the printed original publication
				AND recordId NOT IN(
					77910,109306,115464,131811,131938,132811,133375,136691,140640,142959,
					150974,152975,152981,155343,156438,157302,162789,163738,163880,165289,
					168457,169539,169814,170019,171279,171559,173677,177847,178624,178860,
					179102,179187,179772,181433,183078,185531,185716,190314,194602,205837
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a badly-formatted URL
	private function report_urlinvalid ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'urlinvalid' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    field = 'urlgen'
				AND value IS NOT NULL
				AND value NOT REGEXP '^(http|https|ftp)://.+$'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with two adjacent *nd entries
	private function report_ndnd ()
	{
		# Define the query
		$query = "
			SELECT
				'ndnd' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@nd@nd%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records where ed/eds/comp/comps indicator is not properly formatted
	private function report_misformattedad ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'misformattedad' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				field = 'ad'
				AND (
					(value REGEXP '[[:<:]]eds[[:>:]]'   AND value NOT LIKE '%eds.%')
					OR
					(value REGEXP '[[:<:]]comps[[:>:]]' AND value NOT LIKE '%comps.%')
					OR
					(value REGEXP '[[:<:]]ed[[:>:]]'    AND value NOT LIKE '%ed.%')
					OR
					(value REGEXP '[[:<:]]comp[[:>:]]'  AND value NOT LIKE '%comp.%')
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records where *role is not followed by *n
	private function report_orphanedrole ()
	{
		# Define the query
		$query = "
			SELECT
				'orphanedrole' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				    fieldslist LIKE '%@role%'
				AND fieldslist NOT LIKE '%@role@n%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an empty *a
	private function report_emptyauthor ()
	{
		# Define the query
		$query = "
			SELECT
				'emptyauthor' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				   fieldslist LIKE '%@a@a%'
				OR (fieldslist LIKE  '%@a@%' AND fieldslist NOT LIKE '%@a@n%')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with irregular case-sensitivity of special characters
	# Want to avoid this currently-permitted inconsistency: "When '\g' is followed by a word, the case of the first letter is significant. The remaining letters can be in either upper or lower case however. Thus '\gGamma' is a capital gamma, and the forms '\gGAMMA', '\gGAmma' etc. will also represent capital gamma."
	private function report_specialcharscase ()
	{
		# Define the query
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		$regexpBackslash	= $mysqlBacklash . $mysqlBacklash;			// http://lists.mysql.com/mysql/193376
		
		# Create the SQL clauses for each greek letter; each clause for that letter does a case insensitive match (which will find \galpha or \gAlpha or \galPHa), then excludes the perfect cases of \galpha \gAlpha
		$sqlWhere = array ();
		$greekLetters = $this->greekLetters ();
		foreach ($greekLetters as $letterCaseSensitive => $unicodeCharacter) {
			$letterLcfirst = lcfirst ($letterCaseSensitive);
			$letterUcfirst = ucfirst ($letterCaseSensitive);
			$sqlWhere[] = "(value REGEXP '{$regexpBackslash}g{$letterLcfirst}' AND value NOT REGEXP BINARY '{$regexpBackslash}g{$letterLcfirst}' AND value NOT REGEXP BINARY '{$regexpBackslash}g{$letterUcfirst}')";
		}
		$sqlWhere = array_unique ($sqlWhere);	// The greek letters list is both lower and case - combine the pairs
		
		# Compile the query
		$query = "
			SELECT DISTINCT
				'specialcharscase' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    value REGEXP '{$regexpBackslash}g([a-z]+)'	/* Optimisation */
				AND (
				" . implode ("\n OR ", $sqlWhere) . "
				)
		";
		
		/* Generates: 
		
		SELECT DISTINCT
		'specialcharscase' AS report,
		recordId
		FROM catalogue_rawdata
		WHERE
		value REGEXP '\\\\g([a-z]+)'
		AND (
		(value REGEXP '\\\\galpha' AND value NOT REGEXP BINARY '\\\\galpha' AND value NOT REGEXP BINARY '\\\\gAlpha')
		OR (value REGEXP '\\\\gbeta' AND value NOT REGEXP BINARY '\\\\gbeta' AND value NOT REGEXP BINARY '\\\\gBeta')
		OR (value REGEXP '\\\\ggamma' AND value NOT REGEXP BINARY '\\\\ggamma' AND value NOT REGEXP BINARY '\\\\gGamma')
		OR (value REGEXP '\\\\gdelta' AND value NOT REGEXP BINARY '\\\\gdelta' AND value NOT REGEXP BINARY '\\\\gDelta')
		OR (value REGEXP '\\\\gepsilon' AND value NOT REGEXP BINARY '\\\\gepsilon' AND value NOT REGEXP BINARY '\\\\gEpsilon')
		OR (value REGEXP '\\\\gzeta' AND value NOT REGEXP BINARY '\\\\gzeta' AND value NOT REGEXP BINARY '\\\\gZeta')
		OR (value REGEXP '\\\\geta' AND value NOT REGEXP BINARY '\\\\geta' AND value NOT REGEXP BINARY '\\\\gEta')
		OR (value REGEXP '\\\\gtheta' AND value NOT REGEXP BINARY '\\\\gtheta' AND value NOT REGEXP BINARY '\\\\gTheta')
		OR (value REGEXP '\\\\giota' AND value NOT REGEXP BINARY '\\\\giota' AND value NOT REGEXP BINARY '\\\\gIota')
		OR (value REGEXP '\\\\gkappa' AND value NOT REGEXP BINARY '\\\\gkappa' AND value NOT REGEXP BINARY '\\\\gKappa')
		OR (value REGEXP '\\\\glambda' AND value NOT REGEXP BINARY '\\\\glambda' AND value NOT REGEXP BINARY '\\\\gLambda')
		OR (value REGEXP '\\\\gmu' AND value NOT REGEXP BINARY '\\\\gmu' AND value NOT REGEXP BINARY '\\\\gMu')
		OR (value REGEXP '\\\\gnu' AND value NOT REGEXP BINARY '\\\\gnu' AND value NOT REGEXP BINARY '\\\\gNu')
		OR (value REGEXP '\\\\gxi' AND value NOT REGEXP BINARY '\\\\gxi' AND value NOT REGEXP BINARY '\\\\gXi')
		OR (value REGEXP '\\\\gomicron' AND value NOT REGEXP BINARY '\\\\gomicron' AND value NOT REGEXP BINARY '\\\\gOmicron')
		OR (value REGEXP '\\\\gpi' AND value NOT REGEXP BINARY '\\\\gpi' AND value NOT REGEXP BINARY '\\\\gPi')
		OR (value REGEXP '\\\\grho' AND value NOT REGEXP BINARY '\\\\grho' AND value NOT REGEXP BINARY '\\\\gRho')
		OR (value REGEXP '\\\\gsigma' AND value NOT REGEXP BINARY '\\\\gsigma' AND value NOT REGEXP BINARY '\\\\gSigma')
		OR (value REGEXP '\\\\gtau' AND value NOT REGEXP BINARY '\\\\gtau' AND value NOT REGEXP BINARY '\\\\gTau')
		OR (value REGEXP '\\\\gupsilon' AND value NOT REGEXP BINARY '\\\\gupsilon' AND value NOT REGEXP BINARY '\\\\gUpsilon')
		OR (value REGEXP '\\\\gphi' AND value NOT REGEXP BINARY '\\\\gphi' AND value NOT REGEXP BINARY '\\\\gPhi')
		OR (value REGEXP '\\\\gchi' AND value NOT REGEXP BINARY '\\\\gchi' AND value NOT REGEXP BINARY '\\\\gChi')
		OR (value REGEXP '\\\\gpsi' AND value NOT REGEXP BINARY '\\\\gpsi' AND value NOT REGEXP BINARY '\\\\gPsi')
		OR (value REGEXP '\\\\gomega' AND value NOT REGEXP BINARY '\\\\gomega' AND value NOT REGEXP BINARY '\\\\gOmega')
		);
		
		*/
		
		# Return the query
		return $query;
	}
	
	
	# Records with unknown diacritics
	private function report_unknowndiacritics ()
	{
		# Define backslashes
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		
		# Find cases matching .^.
		$query = "
			SELECT DISTINCT
				'unknowndiacritics' AS report,
				recordId
			FROM catalogue_processed
			WHERE
			    value REGEXP '.{$mysqlBacklash}^.'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records where location is unknown, for records whether the status is not present or is GLACIOPAMS
	private function report_locationunknown ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'locationunknown' AS report,
				catalogue_processed.recordId
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				(
					   fieldslist NOT LIKE '%@status@%'
					OR (field = 'status' AND value = 'GLACIOPAMS')
				)
				AND
				(
					   fieldslist NOT LIKE '%@location@%'
					OR (
						    field = 'location'
						AND (
							   value LIKE '%?%'
							OR value = '-'
							OR value = ''
						)
					)
				)
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records where there appear to be multiple copies, in notes field
	private function report_multiplecopies ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'multiplecopies' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    field IN('note', 'local')
				AND value LIKE 'SPRI has%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing more than one *in field
	private function report_multiplein ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplein' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE ((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@in@',''))) / LENGTH('@in@')) > 1
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing more than one *j field
	private function report_multiplej ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplej' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE ((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@j@',''))) / LENGTH('@j@')) > 1
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing more than one top-level *t field
	private function report_multipletopt ()
	{
		# Define the query
		$query = "
			SELECT
				'multipletopt' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE EXTRACTVALUE(xml, 'count(/*/tg/t)') > 1	-- This will catch both /*/tg/t[2] and /*/tg[2]/t
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing more than one top-level *tt field
	private function report_multipletoptt ()
	{
		# Define the query
		$query = "
			SELECT
				'multipletoptt' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE EXTRACTVALUE(xml, 'count(/*/tg/tt)') > 1
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an invalid date string
	private function report_invaliddatestring ()
	{
		# Find cases matching .^.
		$query = "
			SELECT DISTINCT
				'invaliddatestring' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'r'
				AND value REGEXP '([0-9]{3}[-0-9])'
				AND (
					   value NOT REGEXP '[-0-9]$'
					OR value REGEXP '([0-9]{4})-([0-9]{2})([^0-9])'
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# *ser records with two or more locations
	private function report_serlocloc ()
	{
		# Define the query
		$query = "
			SELECT
				'serlocloc' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				    fieldslist LIKE '%@ser@%'
				AND (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@location@','')))/LENGTH('@location@') > 1
			";
		
		# Return the query
		return $query;
	}
	
	
	# *art/*in records with location=Periodical
	private function report_artinperiodical ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'artinperiodical' AS report,
				recordId
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@art@%'
				AND fieldslist LIKE '%@in@%'
				AND field = 'location'
				AND value = 'Periodical'
			";
		
		# Return the query
		return $query;
	}
	
	
	# records with multiple *al values
	private function report_multipleal ()
	{
		# Define the query
		$query = "
			SELECT
				'multipleal' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				(LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@al@','')))/LENGTH('@al@') > 1
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with combinations of multiple *fund/*kb/*sref values (for 541c)
	private function report_541ccombinations ()
	{
		# Define the query
		$query = "
			SELECT
				'541ccombinations' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				   (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@fund@','')))/LENGTH('@fund@') > 1
				OR (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@kb@','')))/LENGTH('@kb@') > 1
				OR (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@sref@','')))/LENGTH('@sref@') > 1
				OR (fieldslist LIKE '%@fund@%' AND fieldslist LIKE '%@kb@%')
				OR (fieldslist LIKE '%@kb@%' AND fieldslist LIKE '%@sref@%')
				OR (fieldslist LIKE '%@fund@%' AND fieldslist LIKE '%@sref@%')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with combinations of multiple *fund/*kb/*sref values (for 541c), excluding sref+fund
	private function report_541ccombinations2 ()
	{
		# Define the query
		$query = "
			SELECT
				'541ccombinations2' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				   (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@fund@','')))/LENGTH('@fund@') > 1
				OR (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@kb@','')))/LENGTH('@kb@') > 1
				OR (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@sref@','')))/LENGTH('@sref@') > 1
				OR (fieldslist LIKE '%@fund@%' AND fieldslist LIKE '%@kb@%')
				OR (fieldslist LIKE '%@kb@%' AND fieldslist LIKE '%@sref@%')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with unrecognised *ks values
	private function report_unrecognisedks ()
	{
		# Define the query
		$query = "
			SELECT
				'unrecognisedks' AS report,
				recordId
			FROM (
				/* Create a table of ks values with any [...] portion stripped */
				SELECT
					recordId,
					IF (INSTR(value,'[') > 0, LEFT(value,LOCATE('[',value) - 1), value) AS value
				FROM catalogue_processed
				WHERE field = 'ks'
				AND value != ''
				) AS ksValues
			LEFT JOIN udctranslations ON ksValues.value = udctranslations.ks
			WHERE
				    value NOT IN ('" . implode ("', '", $this->overloadedKsTokens) . "')
				AND ks IS NULL
			GROUP BY recordId
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with malformed *ks values
	private function report_malformedks ()
	{
		# Define the query
		$query = "
			SELECT
				'malformedks' AS report,
				recordId
			FROM `catalogue_processed`
			WHERE
				    field = 'ks'
				AND (
					   (value LIKE '%[%' AND value NOT REGEXP '^(.+)\\\\[(.+)\\\\]$')
					OR value = ''
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records that contain photocopy/offprint in *note/*local/*priv
	private function report_offprints ()
	{
		# Define the query; this should reflect the same clause as marcRecordsSetStatus
		$query = "
			SELECT DISTINCT
				'offprints' AS report,
				recordId
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    field IN('note', 'local', 'priv')
				AND (
					   value LIKE '%offprint%'
					OR value LIKE '%photocopy%')
				AND value NOT LIKE '%out of copyright%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with more than one identical location
	private function report_duplicatedlocations ()
	{
		# Define the query
		$query = "
			SELECT
				'duplicatedlocations' AS report,
				recordId
			FROM catalogue_processed
			WHERE field = 'location'
			GROUP BY recordId,value
			HAVING COUNT(value) > 1
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records still containing superscript brackets
	private function report_subscriptssuperscripts ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'subscriptssuperscripts' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				   value LIKE '%{%'
				OR value LIKE '%}%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with italics within a subscript/superscript character
	private function report_italicbracketsorder ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'italicbracketsorder' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				   value LIKE '%{<em>%'
				OR value LIKE '%</em>}%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing a note regarding translation
	private function report_translationnote ()
	{
		# Define the query
		$query = "
			SELECT
				'translationnote' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'note'
				AND value LIKE '%translat%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with two or more parent trees
	private function report_multipletrees ()
	{
		# Define the query
		$query = "
			SELECT
				'multipletrees' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist REGEXP '@(art|doc|ser).*@(art|doc|ser)@'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a *kg that are not an *art
	private function report_kgnotart ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'kgnotart' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				    fieldslist LIKE '%@kg@%'
				AND fieldslist NOT LIKE '%@art@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a *lang but no *tt, having first filtered out any locations whose *lang is English
	private function report_langnott ()
	{
		# Define the query
		$query = "
			SELECT
				'langnott' AS report,
				recordId
			FROM (
				/* Subquery to create fields index */
				SELECT
					recordId,
					CONCAT('@', GROUP_CONCAT(`field` SEPARATOR '@'),'@') AS fieldslist
				FROM (
					/* Subquery to create records but with whitelisted terms taken out */
					SELECT
						recordId,field	
					FROM catalogue_rawdata
					WHERE NOT (field = 'lang' AND value = 'English')
				) AS rawdata_filtered
				GROUP BY recordId
			) AS fieldsindex
			WHERE
				    fieldslist LIKE '%@lang@%'
				AND fieldslist NOT LIKE '%@tt@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records whose (first) *ts does not match the start of a periodical title, based on processPeriodicalLocations ()
	private function report_doctsperiodicaltitle ()
	{
		# Define the query
		$query = "
			SELECT
				'doctsperiodicaltitle' AS report,
				child.recordId
			FROM catalogue_processed AS child
			LEFT JOIN catalogue_xml ON child.recordId = catalogue_xml.id
			LEFT JOIN periodicallocations ON EXTRACTVALUE(xml, '//doc/ts[1]') LIKE CONCAT(periodicallocations.title, '%')
			LEFT JOIN catalogue_processed AS parent ON periodicallocations.recordId = parent.recordId AND parent.field = 'Location'
			WHERE child.field = 'location' AND child.value = 'Periodical'
			AND LENGTH(EXTRACTVALUE(xml, '//doc/ts[1]')) > 0
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose titles are being transliterated but appear to be in English
	private function report_transliteratedenglish ()
	{
		# Define the query
		$query = "
			SELECT
				'transliteratedenglish' AS report,
				id AS recordId
			FROM (
				SELECT
					id,
					title,
					IF (INSTR(title_latin,'[') > 0, LEFT(title_latin,LOCATE('[',title_latin) - 1), title_latin) AS title_latin
				FROM transliterations
			) AS transliterations_firstParts
			WHERE `title_latin` REGEXP '(the | of )'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose reverse transliteration is not reversible
	private function report_transliteratefailure ()
	{
		# Define the query
		$query = "
			SELECT
				'transliteratefailure' AS report,
				recordId
			FROM transliterations
			WHERE forwardCheckFailed = 1
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an equivalent already in Voyager, targetted for merging
	private function report_voyagerrecords ()
	{
		# Define the query
		$query = "
			SELECT
				'voyagerrecords' AS report,
				id AS recordId
			FROM
				catalogue_marc
			WHERE mergeVoyagerId IS NOT NULL
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose *in contains a *lang but the main part does not
	private function report_nohostlang ()
	{
		# Define the query
		$query = "
			SELECT
				'nohostlang' AS report,
				id AS recordId
			FROM
				catalogue_xml
			WHERE
				    ExtractValue(xml, '/*/lang') = ''
				AND ExtractValue(xml, '/*/*/lang') != ''
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with Bibcheck errors
	private function report_bibcheckerrors ()
	{
		# Define the query
		$query = "
			SELECT
				'bibcheckerrors' AS report,
				id AS recordId
			FROM
				catalogue_marc
			WHERE
				bibcheckErrors IS NOT NULL
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with multiple locations but marked as missing
	private function report_multiplelocationsmissing ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplelocationsmissing' AS report,
				fieldsindex.id AS recordId
			FROM catalogue_xml
			LEFT JOIN fieldsindex ON catalogue_xml.id = fieldsindex.id
			WHERE
				    ExtractValue(xml, '//k/ks') LIKE '%MISSING%'
				AND fieldslist REGEXP '@location@.*location@'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with overloaded token values in *ks (except MISSING* and PGA)
	private function report_overloadedkstokens ()
	{
		# Define the query
		$query = "
			SELECT
				'overloadedkstokens' AS report,
				id AS recordId
			FROM catalogue_processed
			WHERE
				    field = 'ks'
				AND value IN('" . implode ("', '", $this->overloadedKsTokens) . "')
				AND value != 'PGA'
				AND value NOT LIKE '%MISSING%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a note containing the word 'missing' without a *ks MISSING; not all will actually be missing
	private function report_notemissing ()
	{
		# Define the query
		$query = "
			SELECT
				'notemissing' AS report,
				recordId
			FROM catalogue_processed
            LEFT JOIN catalogue_xml ON catalogue_processed.recordId = catalogue_xml.id
			WHERE
				    field IN('note', 'priv', 'local')
				AND value LIKE '%missing%'
                AND xml NOT LIKE '%<ks>MISSING%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with empty author containers
	private function report_emptyauthorcontainers ()
	{
		# Define the query
		$query = "
			SELECT
				'emptyauthorcontainers' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE xml regexp '<(a|ad|al|ag|aff)>[[:space:]]*</(a|ad|al|ag|aff)>'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with multiple sources (*ser)
	private function report_multiplesourcesser ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplesourcesser' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				fieldslist LIKE '%@ser@%' AND
				(
				   fieldslist LIKE '%@o@o@%'
				OR fieldslist REGEXP '@o@.*@o@'
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with multiple sources (*doc/*art)
	private function report_multiplesourcesdocart ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplesourcesdocart' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				(fieldslist LIKE '%@doc@%' OR fieldslist LIKE '%@art@%') AND
				(
				   fieldslist LIKE '%@o@o@%'
				OR fieldslist REGEXP '@o@.*@o@'
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Report showing instances of diacritics; NB takes about 33 minutes to run
	private function report_diacritics ()
	{
		# Define the diacritics
		$diacritics = array (
			'a' => 'acute',
			'g' => 'grave',
			'c' => 'cedilla',
			'u' => 'umlaut',
			'm' => 'macron (i.e. horizontal line over letter)',
			'j' => "ligature ('join', i.e. line above two letters)",
			'z' => "'/' through letter",
			'h' => "for circumflex ('h' stands for 'hat')",
			'v' => "for 'v' over a letter",
			'o' => "for 'o' over a letter",
			't' => 'tilde',
		);
		
		# Add capitalised versions
		foreach ($diacritics as $diacritic => $description) {
			$diacriticCapitalised = ucfirst ($diacritic);
			$descriptionCapitalised = $description . ' (upper-case)';
			$diacritics[$diacriticCapitalised] = $descriptionCapitalised;
		}
		
		# Create the table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.listing_diacritics;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `listing_diacritics` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`diacritic` varchar(2) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Diacritic modifier',
			`description` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Description',
			`letter` varchar(2) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Letter',
			`instances` int(11) NOT NULL COMMENT 'Instances',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of diacritic modifier instances'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Construct a set of SQL statements; this has to be chunked due to http://bugs.mysql.com/44626
		$azRanges = array (range ('a', 'z'), range ('A', 'Z'));
		foreach ($diacritics as $diacritic => $description) {
			foreach ($azRanges as $azRange) {
				
				# Construct the queries for each range
				$subqueries = array ();
				foreach ($azRange as $letter) {
					$subqueries[] = "SELECT '{$diacritic}' AS diacritic, \"{$description}\" AS description, '{$letter}' AS letter, COUNT(*) AS instances FROM catalogue_rawdata WHERE value LIKE BINARY '%{$letter}^{$diacritic}%'";
				}
				$query = implode ("\n UNION ", $subqueries);
				
				# Run the query
				$query = "INSERT INTO listing_diacritics (diacritic, description, letter, instances) \n {$query};";
				$result = $this->databaseConnection->execute ($query);
			}
		}
		
		# Return the result
		return true;
	}
	
	
	# View for report_diacritics
	private function report_diacritics_view ()
	{
		# Start the HTML
		$html = '';
		
		# Obtain the data
		if (!$data = $this->databaseConnection->select ($this->settings['database'], 'listing_diacritics', array (), array (), true, $orderBy = 'id')) {	// ORDER BY id will maintain a-z,A-Z ordering of letters
			return $html = "\n<p>There is no data. Please re-run the report generation from the <a href=\"{$this->baseUrl}/import/\">import page</a>.</p>";
		}
		
		# Regroup by diacritic
		$data = application::regroup ($data, 'diacritic');
		
		# Get the diacritics lookup table
		$diacritics = $this->diacriticsTable ();
		
		# Show each diacritic modifer, in a large table
		$html .= "\n" . '<table class="lines diacritics">';
		$html .= "\n\t" . '<tr>';
		$onSecondLine = false;
		foreach ($data as $diacritic => $instances) {
			
			# Create new line at first capitalised diacritic modifier
			if (!$onSecondLine) {
				if (strtoupper ($diacritic) == $diacritic) {
					$html .= "\n\t" . '</tr>';
					$html .= "\n\t" . '<tr>';
					$onSecondLine = true;
				}
			}
			
			# Start with the heading
			$html .= "\n\t" . '<td>';
			$instancesCopy = array_values ($instances);
			$firstInstance = array_shift ($instancesCopy);
			$html .= "\n<h3><strong>^{$diacritic}</strong><br />" . htmlspecialchars ($firstInstance['description']) . '</h3>';
			
			# Add the inner data table
			$table = array ();
			foreach ($instances as $instance) {
				if ($instance['instances'] == 0) {continue;}	// This is done at this level, rather than the getData stage, so that all diacritic modifiers end up being listed
				$unicodeSymbol = $diacritics["{$instance['letter']}^{$diacritic}"];
				$letter = "<a href=\"{$this->baseUrl}/search/?casesensitive=1&anywhere=" . urlencode ($unicodeSymbol) . "\"><strong>{$instance['letter']}</strong>^{$diacritic}</a>";
				$table[$letter] = array (
					'muscat'	=> $letter,
					'unicode'	=> $unicodeSymbol,
					'instances'	=> $instance['instances'],
				);
			}
			$html .= application::htmlTable ($table, array (), $class = 'lines compressed small', $keyAsFirstColumn = false, false, $allowHtml = true, false, $addCellClasses = true, false, array (), false, $showHeadings = false);
			$html .= "\n\t" . '</td>';
		}
		$html .= "\n\t" . '</tr>';
		$html .= "\n" . '</table>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Report showing instances of journal titles
	private function report_journaltitles ()
	{
		return $this->createFieldListingReport ('journaltitle');
	}
	
	
	# Report showing instances of series titles
	private function report_seriestitles ()
	{
		return $this->createFieldListingReport ('seriestitle');
	}
	
	
	# Function to create a report all values of a specified field
	private function createFieldListingReport ($field)
	{
		# Create the table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.listing_{$field}s;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `listing_{$field}s` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Series title',
			`instances` int(11) NOT NULL COMMENT 'Instances',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of series title instances'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Select the data and insert it into the new table
		$query = "SELECT
				{$field} AS title,
				COUNT(*) AS instances
			FROM `fieldsindex`
			WHERE {$field} IS NOT NULL AND {$field} != ''
			GROUP BY {$field}
			ORDER BY " . $this->databaseConnection->trimSql ($field);
		$query = "INSERT INTO listing_{$field}s (title, instances) \n {$query};";
		$result = $this->databaseConnection->execute ($query);
		
		# Return the result
		return true;
	}
	
	
	# View for report_seriestitles
	private function report_journaltitles_view ()
	{
		return $html = $this->reportListing ('listing_journaltitles', 'distinct journal titles', 'journaltitle');
	}
	
	
	# View for report_seriestitles
	private function report_seriestitles_view ()
	{
		return $html = $this->reportListing ('listing_seriestitles', 'distinct series titles', 'seriestitle');
	}
	
	
	# Helper function to get a listing
	private function reportListing ($table, $description, $searchField, $idField = false, $query = false)
	{
		# Start the HTML
		$html = '';
		
		# Obtain the data, using a manually-defined query if necessary
		if ($query) {
			$data = $this->databaseConnection->getData ($query);
		} else {
			$data = $this->databaseConnection->select ($this->settings['database'], $table, array (), array (), true, $orderBy = 'instances DESC,id');
		}
		if (!$data) {
			$errorStatus = $this->databaseConnection->error ();
			if ($errorStatus[0] === '00000') {
				$html  = "\n<p>There are no records.</p>";
			} else {
				$html  = "\n<p>There is no data.</p>";
				$html .= "\n<p>Please re-run the report generation from the <a href=\"{$this->baseUrl}/import/\">import page</a>.</p>";
			}
			return $html;
		}
		
		# If instances data is present, determine total records, by summing the instances values; NB this assumes there is no crossover between entries
		$totalInstances = 0;
		foreach ($data as $entry) {
			if (isSet ($entry['instances'])) {
				$totalInstances += $entry['instances'];
			}
		}
		
		# Show count of matches and of total records
		$html .= "\n<p>There are <strong>" . number_format (count ($data)) . '</strong> ' . $description . ($totalInstances ? ', representing <strong>' . number_format ($totalInstances) . ' records</strong> affected' : '') . ':</p>';
		
		# Render the table
		$html .= $this->valuesTable ($data, $searchField, false, $idField);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to render a table of values
	private function valuesTable ($data, $searchField = false, $linkPrefix = false, $idField = false, $enableSortability = true)
	{
		# Add links to each title if required (search implementation)
		if ($searchField) {
			foreach ($data as $index => $record) {
				$data[$index]['title'] = "<a href=\"{$this->baseUrl}/search/?casesensitive=1&{$searchField}=" . urlencode ($record['title']) . '">' . htmlspecialchars ($record['title']) . '</a>';
			}
		}
		
		# Add links to each title if required (link prefix implementation)
		if ($linkPrefix) {
			foreach ($data as $index => $record) {
				$title = htmlspecialchars ($record['title']);
				#!# Using htmlspecialchars seems to cause double-encoding of &amp; or, e.g. the link becomes buggy on /fields/pu/values/ for value = '("Optimus")'
				# Note that a value containing only a dot is not possible to create a link for: http://stackoverflow.com/questions/3856693/a-url-resource-that-is-a-dot-2e
				$data[$index]['title'] = "<a href=\"{$this->baseUrl}{$linkPrefix}" . urlencode ($record['title']) . "/\">{$title}</a>";
			}
		}
		
		# Add links to record IDs if required
		if ($idField) {
			foreach ($data as $index => $record) {
				$data[$index][$idField] = "<a href=\"{$this->baseUrl}/records/{$record[$idField]}/\">{$record[$idField]}</a>";
			}
		}
		
		# Compile the HTML
		$tableHeadingSubstitutions = array ('id' => '#');
		$html = '';
		if ($enableSortability) {
			$html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="http://www.geog.cam.ac.uk/sitetech/sorttable.js"></script>';
		}
		$html .= application::htmlTable ($data, $tableHeadingSubstitutions, $class = 'reportlisting lines compressed sortable" id="sortable', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record (variant 1)
	private function report_seriestitlemismatches1 ()
	{
		$this->report_seriestitlemismatches (1, $locCondition = "= 'Periodical'");
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record (variant 2)
	private function report_seriestitlemismatches2 ()
	{
		$this->report_seriestitlemismatches (2, $locCondition = "= ''");
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record (variant 3)
	private function report_seriestitlemismatches3 ()
	{
		$this->report_seriestitlemismatches (3, $locCondition = "NOT IN ('', 'Periodical')");
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record; function is used by three variants
	private function report_seriestitlemismatches ($variantNumber, $locCondition)
	{
		# Create the table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.listing_seriestitlemismatches{$variantNumber};";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `listing_seriestitlemismatches{$variantNumber}` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Series title',
			`instances` int(11) NOT NULL COMMENT 'Instances',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of series title mismatches'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Select the data and insert it into the new table
		$query = "
			SELECT
				DISTINCT articles.title,
				COUNT(*) AS instances
			FROM (
				
				/* Subquery to extract the serial title within the record, where the record is not a pamphlet */
				/* NB As of 8/May/2014: matches 89,603 records */
				SELECT
					id,
					EXTRACTVALUE(xml, 'art/j/tg/t') AS title
				FROM catalogue_xml
					WHERE
						EXTRACTVALUE(xml, 'art/j') != ''							/* I.e. is in journal */
					AND EXTRACTVALUE(xml, 'art/j/loc/location') NOT LIKE 'Pam %'	/* I.e. has a location which is not pamphlet */
					AND EXTRACTVALUE(xml, 'art/j/loc/location') NOT LIKE 'Special Collection %'	/* I.e. has a location which is not in the special collection (historic materials, bound copies together, early pamphlets) */
					AND EXTRACTVALUE(xml, 'status') = ''
					AND EXTRACTVALUE(xml, 'art/j/loc/location') {$locCondition}
				) AS articles
				
			LEFT OUTER JOIN (
				
				/* Subquery to extract the title from the parent serials */
				SELECT
					EXTRACTVALUE(xml, 'ser/tg/t') AS title
				FROM catalogue_xml
					WHERE EXTRACTVALUE(xml, 'ser/tg/t') != ''		/* Implicit within this that it is a serial */
				) AS serials
				
			ON (articles.title = serials.title)
			WHERE serials.title IS NULL
			GROUP BY articles.title
			ORDER BY instances DESC, " . $this->databaseConnection->trimSql ('articles.title') . "
		";
		$query = "INSERT INTO listing_seriestitlemismatches{$variantNumber} (title, instances) \n {$query};";
		$result = $this->databaseConnection->execute ($query);
		
		# Return the result
		return true;
	}
	
	
	# View for report_seriestitlemismatches
	private function report_seriestitlemismatches1_view ()
	{
		return $html = $this->reportListing ('listing_seriestitlemismatches1', 'distinct series titles which do not match any parent serial title', 'journaltitle');
	}
	
	
	# View for report_seriestitlemismatches
	private function report_seriestitlemismatches2_view ()
	{
		return $html = $this->reportListing ('listing_seriestitlemismatches2', 'distinct series titles which do not match any parent serial title', 'journaltitle');
	}
	
	
	# View for report_seriestitlemismatches
	private function report_seriestitlemismatches3_view ()
	{
		return $html = $this->reportListing ('listing_seriestitlemismatches3', 'distinct series titles which do not match any parent serial title', 'journaltitle');
	}
	
	
	# Report showing instances of series titles
	private function report_languages ()
	{
		// No action needed - the data is created in the fieldsindex stage
		return true;
	}
	
	
	# View for report_seriestitles
	private function report_languages_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				language AS title,
				COUNT(*) AS instances
			FROM {$this->settings['database']}.fieldsindex
			GROUP BY language
			ORDER BY language
		;";
		
		# Obtain the listing HTML
		$html = $this->reportListing ('fieldsindex', 'language values', 'language', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Report showing instances of transliterations
	private function report_transliterations ()
	{
		// No action needed - the data is created in the fieldsindex stage
		return true;
	}
	
	
	# View for report_transliterations
	private function report_transliterations_view ()
	{
		# Start the HTML
		$html = '';
		
		# Regenerate on demand during testing
		//$this->transliterateTransliterationsTable ();
		
		# Determine total failures
		$totalFailures = $this->databaseConnection->getTotal ($this->settings['database'], 'transliterations', 'WHERE forwardCheckFailed = 1');
		
		# Determine whether to filter to reversibility failures only
		$failuresOnly = (isSet ($_GET['filter']) && $_GET['filter'] == '1');
		if ($failuresOnly) {
			$html .= "\n<p><a href=\"{$this->baseUrl}/reports/transliterations/\">Show all</a> | <strong>Filtering to reversibility failures only ({$totalFailures})</strong></p>";
		} else {
			$html .= "\n<p><strong>Showing all</strong> | <a href=\"{$this->baseUrl}/reports/transliterations/?filter=1\">Filter to reversibility failures only ({$totalFailures})</a></p>";
		}
		
		# Add link to editing the definition
		$html .= "\n<p>You can <a href=\"{$this->baseUrl}/transliterator.html\">edit the reverse-transliteration definition</a>.</p>";
		
		# Define the query
		$query = "SELECT
				*
			FROM {$this->settings['database']}.transliterations
			" . ($failuresOnly ? 'WHERE forwardCheckFailed = 1' : '') . "
		;";
		
		# Default to 1000 per page
		$this->settings['paginationRecordsPerPageDefault'] = 1000;
		
		# Obtain the listing HTML, passing in the renderer callback function name
		$html .= $this->recordListing (false, $query, array (), '/reports/transliterations/', false, false, $view = 'callback(transliterationsRenderer)');
		
		# Return the HTML
		return $html;
	}
	
	
	# Callback to provide a renderer
	private function transliterationsRenderer ($data)
	{
		# Remove internal IDs
		foreach ($data as $id => $record) {
			unset ($data[$id]['id']);
			unset ($data[$id]['shardId']);
		}
		
		# Add English *tt to the Muscat latin field
		foreach ($data as $id => $record) {
			if ($record['title_latin_tt']) {
				$data[$id]['title_latin'] .= '<br /><span class="comment">[' . $record['title_latin_tt'] . ']</span>';
			}
			unset ($data[$id]['title_latin_tt']);
		}
		
		# Add a comparison check, and hide the two fields required for it
		foreach ($data as $id => $record) {
			if ($record['forwardCheckFailed']) {
				$data[$id]['title_latin'] .= '<br /><br /><span class="warning"><strong>Reversibility check failed:</strong></span><br />' . $record['title_forward'];
			}
			unset ($data[$id]['title_forward']);
			unset ($data[$id]['forwardCheckFailed']);
		}
		
		# Extract strings to spellcheck as key/value pairs
		$spellcheck = array ();
		foreach ($data as $id => $record) {
			$spellcheck[$id] = $record['title'];
		}
		
		# Spellcheck the strings
		$spellcheck = application::spellcheck ($spellcheck, 'ru_RU', $this->databaseConnection, $this->settings['database'], $enableSuggestions = true, $this->transliterationProtectedStrings ());
		
		# Substitute the spellchecked HTML versions into the table
		foreach ($spellcheck as $id => $string) {
			$data[$id]['title'] = $string;
		}
		
		# Link each record
		foreach ($data as $id => $record) {
			$data[$id]['recordId'] = "<a href=\"{$this->baseUrl}/records/{$record['recordId']}/\">{$record['recordId']}</a>";
		}
		
		# Show the record and field together
		foreach ($data as $id => $record) {
			$data[$id]['recordId'] .= '&nbsp;*' . $record['field'];
			unset ($data[$id]['field']);
		}
		
		# Render as HTML; records already may contain tags
		$tableHeadingSubstitutions = array (
			'recordId' => '#',
			'title' => 'Voyager (Cyrillic)',
			'title_latin' => 'Muscat (transliteration)',
			'title_loc' => 'Library of Congress Cyrillic',
		);
		$html  = application::htmlTable ($data, $tableHeadingSubstitutions, 'lines', $keyAsFirstColumn = false, false, $allowHtml = true, false, false, false, array (), $compress = true);
		
		# Render the HTML
		return $html;
	}
	
	
	# Report showing records with parallel titles, filtered to Russian
	private function report_paralleltitlelanguages ()
	{
		// No action needed - the data is created dynamically
		return true;
	}
	
	
	# View for report_paralleltitlelanguages
	private function report_paralleltitlelanguages_view ()
	{
		# Start the HTML
		$html = '';
		
		# Define the query
		$query = "SELECT
				id,
				ExtractValue(xml, '/*/tg/t') as topLevelT,
				CAST( (LENGTH( ExtractValue(xml, '/*/tg/t') )-LENGTH(REPLACE( ExtractValue(xml, '/*/tg/t') ,' = ','')))/LENGTH(' = ') AS SIGNED) + 1 AS totalParts,
				ExtractValue(xml, 'count(//lang)') AS totalLang,
				'' AS isDifferent,
				ExtractValue(xml, '//lang[1]') AS lang1,
				ExtractValue(xml, '//lang[2]') AS lang2,
				ExtractValue(xml, '//lang[3]') AS lang3,
				ExtractValue(xml, '//lang[4]') AS lang4
			FROM catalogue_xml
			WHERE
				    ExtractValue(xml, '/*/tg/t') LIKE '% = %'
				AND ExtractValue(xml, '//lang') LIKE '%Russian%'
			ORDER BY
				lang1 = '', lang1,	/* i.e. empty value '' at end */
				lang2 = '', lang2,
			/* Disabled due to '#1038 - Out of sort memory, consider increasing server sort buffer size' */
			--	lang3 = '', lang3,
			--	lang4 = '', lang4,
				id
		;";
		
		# Default to showing all c. 750 records
		$this->settings['paginationRecordsPerPageDefault'] = 1000;
		
		# Compute the total explicitly due to the hard-to-fix bug in the database library
		$countingQuery = "SELECT
			COUNT(*) AS total
			FROM catalogue_xml
			WHERE
				    ExtractValue(xml, '/*/tg/t') LIKE '% = %'
				AND ExtractValue(xml, '//lang') LIKE '%Russian%'
		";
		$knownTotalAvailable = $this->databaseConnection->getOneField ($countingQuery, 'total');
		
		# Obtain the listing HTML, passing in the renderer callback function name
		$html .= $this->recordListing (false, $query, array (), '/reports/paralleltitlelanguages/', false, false, $view = 'callback(paralleltitlelanguagesRenderer)', false, $knownTotalAvailable);
		
		# Return the HTML
		return $html;
	}
	
	
	# Callback to provide a renderer for report_paralleltitlelanguages
	private function paralleltitlelanguagesRenderer ($data)
	{
		# Link each record
		foreach ($data as $id => $record) {
			$data[$id]['id'] = "<a href=\"{$this->baseUrl}/records/{$record['id']}/\">{$record['id']}</a>";
		}
		
		# Compute the isDifferent column for efficiency
		$mismatches = 0;
		foreach ($data as $id => $record) {
			$isDifferent = ($record['totalParts'] != $record['totalLang']);
			$data[$id]['isDifferent'] = ($isDifferent ? '<span class="warning">Mismatch</span>' : '');
			if ($isDifferent) {$mismatches++;}
		}
		
		# Render as HTML; records already may contain tags
		$tableHeadingSubstitutions = array (
			'value'			=> 'Value',
			'totalParts'	=> 'Total parts in title',
			'totalLang'		=> 'Total *lang',
			'lang1'			=> '*lang #1',
			'lang2'			=> '*lang #2',
			'lang3'			=> '*lang #3',
			'lang4'			=> '*lang #4',
		);
		$html  = "\n<p>Mismatches: {$mismatches}.</p>";
		$html .= application::htmlTable ($data, $tableHeadingSubstitutions, 'lines compressed', $keyAsFirstColumn = false, false, $allowHtml = true, false, false, false, array (), $compress = true);
		
		# Render the HTML
		return $html;
	}
	
	
	# Distinct values of all *n1 fields that are not immediately followed by a *n2 field
	private function report_distinctn1notfollowedbyn2 ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for distinct values of all *n1 fields that are not immediately followed by a *n2 field
	private function report_distinctn1notfollowedbyn2_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				value,
				COUNT(recordId) AS instances
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@n1@%'
				AND fieldslist NOT LIKE '%@n1@n2@%'
				AND field = 'n1'
			GROUP BY value
			ORDER BY " . $this->databaseConnection->trimSql ('value') . "
		;";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', false, false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Distinct values of all *n2 fields that are not immediately preceded by a *n1 field
	private function report_distinctn2notprecededbyn1 ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for distinct values of all *n2 fields that are not immediately preceded by a *n1 field
	private function report_distinctn2notprecededbyn1_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				value,
				COUNT(recordId) AS instances
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@n2@%'
				AND fieldslist NOT LIKE '%@n1@n2@%'
				AND field = 'n2'
			GROUP BY value
			ORDER BY " . $this->databaseConnection->trimSql ('value') . "
		;";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', false, false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Records where there appear to be multiple copies, in notes field - unique values
	private function report_multiplecopiesvalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records where there appear to be multiple copies, in notes field - unique values
	private function report_multiplecopiesvalues_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				value AS title,
				COUNT(recordId) AS instances
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    field IN('note', 'local')
				AND value LIKE 'SPRI has%'
			GROUP BY value
			ORDER BY value
		;";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', 'anywhere', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Records where kw is unknown, showing the bibliographer concerned
	private function report_kwunknown ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records where kw is unknown, showing the bibliographer concerned
	private function report_kwunknown_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				id,
				IFNULL( NULLIF( EXTRACTVALUE(xml, '//acc/recr'), ''), '?') AS value
			FROM catalogue_xml
			WHERE
				EXTRACTVALUE(xml, '//k/kw') LIKE '%UNKNOWN%'
			ORDER BY value
		;";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'records', false, $idField = 'id', $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Records with unrecognised *ks values - distinct *ks values
	private function report_unrecognisedksvalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records with unrecognised *ks values - distinct *ks values
	private function report_unrecognisedksvalues_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				DISTINCT value AS title,
				COUNT(recordId) AS instances
			FROM (
				/* Create a table of ks values with any [...] portion stripped */
				SELECT
					recordId,
					IF (INSTR(value,'[') > 0, LEFT(value,LOCATE('[',value) - 1), value) AS value
				FROM catalogue_processed
				WHERE field = 'ks'
				AND value != ''
				) AS ksValues
			LEFT JOIN udctranslations ON ksValues.value = udctranslations.ks
			WHERE
				    value NOT IN ('" . implode ("', '", $this->overloadedKsTokens) . "')
				AND ks IS NULL
			GROUP BY value
			";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', 'anywhere', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Muscat locations that do not map to Voyager locations
	private function report_voyagerlocations ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for Muscat locations that do not map to Voyager locations
	private function report_voyagerlocations_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				value AS title,
				COUNT(recordId) AS instances
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value NOT REGEXP \"^(" . implode ('|', array_keys ($this->locationCodes)) . ")\"
				AND value NOT REGEXP '^(IGS|International Glaciological Society|Shelved with|Not in SPRI)'
			GROUP BY value
			ORDER BY title
		";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'locations', 'location', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# *doc records with one *location, which is Periodical - distinct *ts values
	private function report_doclocationperiodicaltsvalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for report of *doc records with one *location, which is Periodical - distinct *ts values
	private function report_doclocationperiodicaltsvalues_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				EXTRACTVALUE(xml, '//ts') AS value,
				COUNT(catalogue_xml.id) AS instances
			FROM catalogue_xml
			LEFT JOIN fieldsindex ON catalogue_xml.id = fieldsindex.id
			WHERE
				    fieldslist LIKE '%@doc@%'
				AND (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@location@','')))/LENGTH('@location@') = 1		/* NOT two locations, i.e. exactly one */
				AND EXTRACTVALUE(xml, '//location') = 'Periodical'
			GROUP BY value
			ORDER BY value
			";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', false, false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Report showing volume number conversions
	private function report_volumenumbers ()
	{
		// No action needed - the data is created in the MARC creation stage
		return true;
	}
	
	
	# View for report_volume volume number conversions
	private function report_volumenumbers_view ()
	{
		# (Re-)generate the data
		$this->createVolumeNumbersTable ();
		
		# Define a manual query
		$query = "
			SELECT
				id,
				ts,
				result,
				matchedRegexp
			FROM {$this->settings['database']}.volumenumbers
			ORDER BY id
		;";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'volume strings', false, 'id', $query);
		
		# Highlight subfields
		$html = $this->highlightSubfields ($html);
		
		# Return the HTML
		return $html;
	}
	
	
	# Records containing a note regarding translation - distinct values
	private function report_translationnotevalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for report of records containing a note regarding translation - distinct values; for use in diagnosing best regexp for languages041 macro
	private function report_translationnotevalues_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				value AS title,
				COUNT(recordId) AS instances
			FROM catalogue_processed
			WHERE
				    field = 'note'
				AND value LIKE '%translat%'
			GROUP BY value
			ORDER BY instances DESC
		";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', 'anywhere', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Records with a merge status - distinct values
	private function report_mergestatus ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records with a merge status
	private function report_mergestatus_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				id,
				mergeType,
				mergeVoyagerId
			FROM catalogue_marc
			WHERE
				mergeType IS NOT NULL
			ORDER BY mergeType, id
		";
		
		# Default to 2000 per page
		$this->settings['paginationRecordsPerPageDefault'] = 2000;
		
		# Obtain the listing HTML
		$html = $this->recordListing (false, $query, array (), '/reports/mergestatus/', false, false, $view = 'callback(mergestatusRenderer)');
		
		# Return the HTML
		return $html;
	}
	
	
	# Callback to provide a renderer for report_mergestatus
	private function mergestatusRenderer ($data)
	{
		# Link each record
		foreach ($data as $id => $record) {
			$data[$id]['id'] = "<a href=\"{$this->baseUrl}/records/{$record['id']}/\">{$record['id']}</a>";
		}
		
		# Add labels
		foreach ($data as $id => $record) {
			$data[$id]['mergeType'] .= ' &nbsp; <span class="comment">(' . (isSet ($this->mergeTypes[$record['mergeType']]) ? "{$this->mergeTypes[$record['mergeType']]}" : '?') . ')</span>';
		}
		
		# Render as HTML
		$tableHeadingSubstitutions = array (
			'mergeType'			=> 'Merge type',
			'mergeVoyagerId'	=> 'Voyager ID(s)',
		);
		$html  = "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';
		$html .= application::htmlTable ($data, $tableHeadingSubstitutions, 'lines compressed sortable" id="sortable', $keyAsFirstColumn = false, false, $allowHtml = true, false, false, false, array (), $compress = true);
		
		# Render the HTML
		return $html;
	}
	
	
	
/*
	# Records without abstracts
	private function report_emptyabstract ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'misformattedad' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				recordId IN
					(
					-- Subquery to create list of IDs
					SELECT recordId
					FROM catalogue_rawdata
					LEFT JOIN fieldsindex ON recordId = fieldsindex.id
					WHERE
						    fieldslist LIKE '%@status@%'
						AND field = 'status'
						AND value NOT IN('RECEIVED', 'ON ORDER', 'CANCELLED')
					)
				AND
					(
						(field = 'abs' AND (value IS NULL OR value = ''))
						OR
		#!# Won't work:
						(fieldsindex NOT LIKE '%@abs@%')
					)
		";
		
		# Return the query
		return $query;
	}
*/
}


# Useful link: http://stackoverflow.com/questions/4287822/need-a-mysql-query-for-selecting-from-a-table-storing-key-value-pairs


/*
# http://stackoverflow.com/questions/4666042/sql-query-to-get-total-rows-and-total-rows-matching-specific-condition

SELECT
	COUNT(CASE WHEN fields LIKE '%|q0|%' THEN 1 END) as has_q0,
	COUNT(CASE WHEN fields NOT LIKE '%|q0|%' THEN 1 END) as has_no_q0
FROM
	(SELECT recordId, CONCAT('|', GROUP_CONCAT(field SEPARATOR '||'), '|') As fields FROM catalogue GROUP BY recordId) AS records
WHERE 1;

*/


?>
