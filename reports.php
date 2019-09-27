<?php

# Class containing reports
class reports
{
	# Define the registry of reports; those prefixed with 'listing_' return data rather than record numbers; listings can be suffixed with a status (e.g. _info)
	private $reportsList = array (
		'q0naming_problem' => 'records without a *q0',
		'missingcategory_problem' => 'records without a category (*doc/*art/*ser)',
		'missingd_problem' => 'records without a *d that are not *ser and either no status or status is GLACIOPAMS',
		'missingacc_info' => 'records without a *acc',
		'missingt_problem' => 'records without a *t',
		'multiplet_problem' => 'records with multiple adjacent *t values',
		'emptytvariants_problem' => 'records with an empty *t/*tt/*tc',
		'sermissingr_problem' => '*ser records without a *r, except where location is Not in SPRI',
		'artwithoutlocstatus_problem' => '*art records where there is no *loc and no *status',
		'nolocationnostatus_problem' => 'records with neither a location nor a status',
		'tcnotone_problem' => 'records without exactly one *tc',
		'tgmismatch_problemok' => 'records whose *tg count does not match *t (not all will be errors)',
		'missingrpl_info' => 'records without a *rpl',
		'missingrplstatus_postmigration' => 'records in SPRI without a *rpl and without a *status, that are not *ser',
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
		'singleexternallocation_problem' => 'records with only one external location, which is not on the whitelist',
		'arttitlenoser_problem' => 'articles without a matching serial title, that are not pamphlets or in the special collection or Not in SPRI',
		'locationauthoritycontrol_problem' => 'locations not passing authority control',
		'notinspri_info' => 'items not in SPRI',
		'notinspriinspri_problem' => 'items not in SPRI also having a SPRI location',
		'notinsprimissing_problem' => 'items not in SPRI also with MISSING',
		'loccamuninotinspri_info' => 'records with location matching Cambridge University, not in SPRI',
		'loccamuniinspri_info' => 'records with location matching Cambridge University, in SPRI',
		'onordercancelled_info' => 'items on order or cancelled',
		'invalidstatus_problem' => 'items with an invalid *status',
		'invalidacquisitiondate_problem' => 'items with an invalid acquisition date',
		'emptyacq_problem' => 'items with an empty *acq container',
		'onorderold_info' => 'Items on order before the threshold acquisition date',
		'onorderrecent_info' => 'Items on order since the threshold acquisition date',
		'ordercancelled_info' => 'items where the order is cancelled',
		'absitalics_info' => 'records with italics in the abstract',
		'isbninvalid_problem' => 'records with invalid ISBN number, excluding ones known to be wrong in the printed original publication',
		'urlinvalid_problem' => 'records with a badly-formatted URL',
		'ndnd_problem' => 'records with two adjacent *nd entries',
		'misformattedad_problem' => 'records where ed/eds/comp/comps indicator is not properly formatted',
		'orphanedrole_problem' => 'records where *role is not followed by *n',
		'emptyauthor_problem' => 'records with an empty *a',
		'specialcharscase_problem' => 'records with irregular case-sensitivity of special characters',
		'unknowndiacritics_problem' => 'records with unknown diacritics',
		'locationunknown_info' => 'records where location is unknown, for records whether the status is not present or is GLACIOPAMS',
		'multiplesourcesser_info' => 'records with multiple sources (*ser)',
		'multiplesourcesdocart_info' => 'records with multiple sources (*doc/*art)',
		'multiplecopies_info' => 'records where there appear to be multiple copies, in notes field',
		'mismatchedmultiplecopies_problem' => 'records where there appear to be multiple copies, in notes field, not matching the location count',
		'multiplein_problem' => 'records containing more than one *in field',
		'multiplej_problem' => 'records containing more than one *j field',
		'multipletopt_problem' => 'records containing more than one top-level *t field',
		'multipletoptt_problem' => 'records containing more than one top-level *tt field',
		'invaliddaterangestring_problem' => 'records with an invalid date range string',
		'ndsyntax_problem' => 'records with invalid syntax for a non-numeric date',
		'multipledate_info' => 'records with more than one *d',
		'multipleppt_problem' => 'records with more than one *p or *pt',
		'serlocloc_problem' => '*ser records with two or more locations (though some are valid)',
		'artinperiodical_info' => '*art/*in records with location=Periodical',
		'multipleal_info' => 'records with multiple *al values',
		'541ccombinations_info' => 'records with combinations of multiple *fund/*kb/*sref values (for 541c)',
		'541ccombinations2_info' => 'records with combinations of multiple *fund/*kb/*sref values (for 541c), excluding sref+fund',
		'unrecognisedks_problem' => 'records with unrecognised *ks values',
		'malformedks_problem' => 'records with malformed *ks values',
		'multipleadjacentks_problem' => 'records with multiple adjacent *ks values',
		'malformedn2_problem' => 'records with a malformed *n2 value',
		'coexistingksstatus_problem' => 'records with both a *ks status and a *status',
		'statuscodeksderived_info' => 'records with a cataloguing status code coming from *ks',
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
		'transliteratedenglish_problem' => 'records with strings being transliterated but which appear to be in English',
		'transliteratefailure_problem' => 'records whose reverse transliteration is not reversible',
		'transliterateem_problem' => 'records whose transliteration contains an incorrect <em> following transliteration',
		'voyagerrecords_info' => 'records with an equivalent already in Voyager, targetted for merging',
		'nohostlang_problem' => 'records whose *in or *j contains a *lang but the main part does not',
		'emptylang_problem' => 'records with an empty *lang',
		'bibcheckerrors_problem' => 'records with Bibcheck errors',
		'multiplelocationsmissing_problem' => 'records with multiple locations but marked as missing without disambiguation',
		'notemissing_problem' => "records with a note containing the word 'missing' without a *ks MISSING; not all will actually be missing",
		'missingphysical_postmigration' => "records with a note containing the word 'missing' indicating items for reviewing of physical documents (i.e. not actually data work)",
		'emptyauthorcontainers_problem' => "records with empty author containers",
		'backslashg_problem' => 'records with \g remaining',
		'bracketednfcount_info' => 'records with a bracketed title starting with a leading article, for checking the nfcount',
		'russianbracketedtitle_postmigration' => 'records marked *lang=Russian with a fully-bracketed title',
		'russianldottitles_problem' => 'records (Russian) with L. in title to be checked individually',
		'paralleltitlemismatch_problem' => 'records (Russian) whose parallel title component count does not match that of the title',
		'paralleltitlesync_problem' => 'records where parallel titles and *lpt presence are not in sync',
		'emptyvalue_problem' => 'records with empty scalar values',
		'sernotitle_problem' => '*ser records with no title',
		'sernonuniquetitle_problem' => '*ser records whose title is not unique',
		'periodicalpam_problem' => 'records with location= both Periodical and Pam',
		'russianvolumenumbers_info' => 'Russian records with a volume number',
		'longtitles_problem' => 'records with long titles (>512 characters), that are not on the whitelist',
		'artjwithoutvolume_problem' => 'articles in journals without a volume designation and no useful date',
		'docpt_problem' => '*doc records with a *pt',
		'artnopt_problem' => '*art records without a *pt, where the record has a SPRI location',
		'docnop_problem' => '*doc records without a *p',
		'artform_problem' => '*art records with a *form',
		'agwithonlyad_problem' => '*ag records containing only an *ad',
		'tdot_problem' => '*t values ending with a dot',
		'ptspacecolonspace_problem' => '*pt values containing space-colon-space',
		'multiplepdot_info' => 'multiple p dot',
		'problematicpdot_problem' => 'problematic p. cases, assuming that multiplepdot report is cleared',
		'pnodot_problem' => 'report for p not followed by a dot in *p / *pt',
		'pcolonspace_problem' => '*p values containing colon-space rather than space-colon-space',
		'sermultipler_problem' => '*ser records with multiple *r',
		'artjnokg_postmigration' => '/art/j records with no *kg in the Pamphlets',
		'tslasheditors_problem' => '*t with explicit slash also having *e',
		'emptydashwithspri_problem' => 'records containing a field with an empty dash, with a SPRI location',
		'emptydashwithoutspri_problem' => 'records containing a field with an empty dash, without a SPRI location',
		'invalidcon_problem' => 'records with an invalid *con syntax',
		'othertransliterations_postmigration' => 'records with names for transliteration in other languages (e.g. Yakut, Chinese, etc.) for upgrading',
		'locationunassigned_postmigration' => 'records with location = ??',
		'towithoutlto_problem' => 'records with *to without *lto defined, having whitelisted English *to in a non-Russian record',
		'ltowithoutto_problem' => 'records with *lto present but no *to',
		'multipleto_problem' => 'records with more than one *to/*lto',
		'ntnoneslashupgrade_problem' => 'records with *nt=None which have not had / upgrading for 245',
		'ntcyrillicinscope_info' => 'non-Russian records with Cyrillic *nt = BGNRus/LOCRus',
		'ntcyrillicunsupported_postmigration' => 'non-Russian records with all Cyrillic *nt for types never supported',
		'locrusnodiacritics_postmigration' => 'records with *nt=LOCRus that need diacritics adding',
		'article245_problem' => 'records with a suspected wrong language due to leading article mismatch in 245',
		'titleroundbrackets_problem' => 'records whose title is in round brackets',
		'totsdotend_problem' => '*to/*ts records ending with a dot',
		'physicalmisformat_problem' => 'physical description with incorrect syntax',
		'paralleltitlesyntax_problem' => 'incorrect syntax for a parallel title',
		'provenancenote_problem' => 'incorrect syntax for a provenance note',
		'onmigration_postmigration' => 'records with a note regarding post-migration instructions',
		'basementshelf0875_postmigration' => 'records with 087.5 but not Basement Shelf',
		'openst_problem' => 'records with a *st but the holding statement is open',
		'basementseligman_postmigration' => 'records in Basement Seligman which need a parent record created',
		'seriestitlemismatches1records_postmigration' => "listing: articles without a matching serial (journal) title in another record (that are neither pamphlets nor in the special collection), where location=Periodical: record numbers",
		'transfer541_postmigration' => 'records with multiple *locations whose 541 needs to be made specific to the location',
		'volumeenumeration_postmigration' => 'records whose item records will need enumeration (*doc with *v)',
		'voyagerbelievedmatch_postmigration' => 'records with possible but unconfirmed Voyager matches needing merging',
		'hostwithitem_problem' => 'records with both 773 (host entry) and 852 (item record) fields',
		'conwithoutacc_info' => 'records with a condition report but no accession number',
		'adcomma_problem' => 'records with a rogue comma at the end of the *ad',
		'splitlang_info' => 'records having a Russian *lang (thus transliterable) with different *lang vs *in/*lang',
		'locationperiodical_postmigration' => 'records with *location=Periodical remaining',
		'artkgart_postmigration' => 'records with an *art joining (via *kg) to an *art',
		'multipleholdingssamelocation_postmigration' => 'records with multiple holdings at the same location',
	);
	
	# Listing (values) reports
	private $listingsList = array (
		'multiplecopiesvalues_postmigration' => 'listing: records where there appear to be multiple copies, in notes field - unique values',
		'diacritics_info' => 'listing: counts of diacritics used in the raw data',
		'journaltitles_info' => 'listing: journal titles',
		'seriestitles_info' => 'listing: series titles',
		'seriestitlemismatches1_problem_countable' => "listing: articles without a matching serial (journal) title in another record (that are neither pamphlets nor in the special collection), where location=Periodical",
		'seriestitlemismatches2_problem_countable' => "listing: articles without a matching serial (journal) title in another record (that are neither pamphlets nor in the special collection), where location is empty",
		'seriestitlemismatches3_postmigration_countable' => 'listing: articles without a matching serial (journal) title in another record (that are neither pamphlets nor in the special collection), where location is other (not Periodical, not empty)',
		'languages_info' => 'listing: languages',
		'transliterations_problem_countable' => 'listing: transliterations',
		'paralleltitlelanguages_info' => 'listing: records with parallel titles, filtered to Russian',
		'distinctn1notfollowedbyn2_info' => 'Distinct values of all *n1 fields that are not immediately followed by a *n2 field',
		'distinctn2notprecededbyn1_info' => 'Distinct values of all *n2 fields that are not immediately preceded by a *n1 field',
		'kwunknown_info' => 'records where kw is unknown, showing the bibliographer concerned',
		'doclocationperiodicaltsvalues_info' => '*doc records with one *location, which is Periodical - distinct *ts values',
		'unrecognisedksvalues_info' => 'records with unrecognised *ks values - distinct *ks values',
		'volumenumbers_info' => 'volume number results arising from the 490 macro',
		'voyagerlocations_info' => 'Muscat locations that do not map to Voyager locations',
		'translationnotevalues_info' => 'records containing a note regarding translation - distinct values',
		'mergestatus_info' => 'records with a merge status',
		'periodicalpamgrouped_problem' => 'records with location= both Periodical and Pam, grouped',
		'russianitalics_postmigration' => 'records in Russian containing italicised string which may or may not be in Russian',
		'tests_problem_countable' => 'automated tests',
	);
	
	
	# Constructor
	public function __construct ($muscatConversion, $marcConversion)
	{
		# Create main property handles
		$this->muscatConversion = $muscatConversion;
		$this->settings = $muscatConversion->settings;
		$this->databaseConnection = $muscatConversion->databaseConnection;
		$this->baseUrl = $muscatConversion->baseUrl;
		
		# MARC conversion property handles for properties used in multiple reports
		$this->marcConversion = $marcConversion;
		$this->locationCodes = $marcConversion->getLocationCodes ();
		$this->ksStatusTokens = $marcConversion->getKsStatusTokens ();
		$this->acquisitionDate = $marcConversion->getAcquisitionDate ();
		$this->transliterationNameMatchingFields = $marcConversion->getTransliterationNameMatchingFields ();
		
		# Compile an SQL REGEXP clause for the location codes
		$singleBackslash = '\\';
		$this->locationCodesRegexpSql = '^(' . str_replace ($singleBackslash, str_repeat ($singleBackslash, 2), implode ('|', array_keys ($this->locationCodes))) . ')';
		
	}
	
	
	
	# Getter for reportsList
	public function getReportsList ()
	{
		return $this->reportsList;
	}
	
	
	# Getter for listingsList
	public function getListingsList ()
	{
		return $this->listingsList;
	}
	
	
	# Post-migration task descriptions
	public function postmigrationDescriptions ()
	{
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
		# Return text for each post-migration task
		return array (
			
			'missingrplstatus' =>
				'Records that have a broad subject heading.',
			
			'missingphysical' =>
				"Record whose note contains the word 'missing' - these need to be checked physically to see whether they are actually missing pages / not present or just that 'missing' relates to the content of the item itself.",
			
			'russianbracketedtitle' =>
				'Items to be checked physically to see if a better title, in Cyrillic, can be added.',
			
			'othertransliterations' =>
				'Records with names for transliteration in other languages (e.g. Yakut, Chinese, etc.) for upgrading by an expert speaker in that language; Muscat practice has never supported Yakut, Chinese, Japanese, etc.',
			
			'artjnokg' =>
				"In case of an *art/*j Pam without a SPRI host, could subsequently be linked to a UL host by creating a 773 including a {$this->doubleDagger}w.",
			
			'locationunassigned' =>
				'Valid records but the item needs to be found.',
			
			'ntcyrillicunsupported' =>
				'Some records have *nt other than none/BGNRus/LOCRus, e.g. FRRus. These were considered out of scope for coding, so no attempt was made even to consider upgrading them. The name authority (1xx/7xx) needs to be upgraded and the 880 added.',
			
			'locrusnodiacritics' =>
				'All records using *nt = LOCRus have this string entered without diacritics. These items need to be checked and where relevant, the diacritics added.',
			
			'seriestitlemismatches3' =>
				'These are analytic records for which SPRI does not have a parent, which means we have no name authority for the parent, making matching pre-migration unreliable.',
			
			'basementshelf0875' =>
				"Highlights books to be reclassified after migration so that they can be shelved in the children's collection.",
			
			'basementseligman' =>
				'Items with location = Basement Seligman (01-92) do not yet have a parent record.',
			
			'seriestitlemismatches1records' =>
				'Items needing a parent record',
			
			'multiplecopiesvalues' =>
				'Notes such as "SPRI has..." need to be checked against the rest of the data, e.g. "SPRI has three copies" should be reflected as three locations.',
			
			'onmigration' =>
				'These records have a private note giving instructions for specific post-migration actions.',
			
			'transfer541' =>
				'The 541 field (Immediate Source of Acquisition Note) is not being transferred to the holding record on load, but kept in the bib with $5 subfield. Where there are multiple holdings, it could be a potential task for a volunteer to check and update provenance details post-migration.',
			
			'volumeenumeration' =>
				'*doc records with *v are multi-volume works, and will be getting separate item records for each volume in Alma, but details are needed for each item record.',
			
			'voyagerbelievedmatch' =>
				'These serial records have a set of possible (or likely) matches with Voyager numbers, but will need manual checking to assign the correct match.',
			
			'russianitalics' =>
				'This report aims to facilitate manual inspection of Russian records where the title is in Russian but contains a section in italics. Currently, these italicised section are all protected, under the standard rule for italics. However, in some cases, these are names in Russian and should be upgraded to LoC and also converted to Cyrillic as if an unprotected string.',
			
			'artkgart' =>
				'These records need to be checked for their odd structure. Note that 141 of them have one parent, Materialy.',
			
			'multipleholdingssamelocation' =>
				'Records with multiple holdings at the same $c location have not had any more than one holding created. This list of records will need to have to holdings added post-migration.',
			
			'locationperiodical' =>
				'Records with location=Periodical which have not been able to be matched with any parent, which thus have no location. These need to be found.',
			
		);
	}
	
	
	
	# Naming report
	public function report_q0naming ()
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
	public function report_missingcategory ()
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
	public function report_missingd ()
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
					OR (field = 'status' AND value = 'SUPPRESS')
					OR (field = 'status' AND value = 'GLACIOPAMS')
				)
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without a *acc
	public function report_missingacc ()
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
	public function report_missingt ()
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
	
	
	# Records with multiple adjacent *t values
	public function report_multiplet ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplet' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@t@t@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an empty *t/*tt/*tc
	public function report_emptytvariants ()
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
	
	
	# *ser records without a *r, except where location is Not in SPRI
	public function report_sermissingr ()
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
	
	
	# *art records where there is no *loc and no real (i.e. not 'SUPPRESS') *status
	public function report_artwithoutlocstatus ()
	{
		# Define the query
		$query = "
			SELECT
				'artwithoutloc' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE fieldslist LIKE '%@art@%'
			  AND fieldslist NOT LIKE '%@loc@%'
			  AND status IS NULL	-- Not using `fieldslist NOT LIKE '%@status@%'` as that includes status=SUPPRESS, though results do not actually change in this report
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with neither a location nor a status
	public function report_nolocationnostatus ()
	{
		# Define the query
		$query = "
			SELECT
				'nolocationnostatus' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				    fieldslist NOT LIKE '%@location@%'
				AND fieldslist NOT LIKE '%@status@%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without exactly one *tc
	public function report_tcnotone ()
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
	public function report_tgmismatch ()
	{
		# Define the query; uses substring count method in comments at: https://www.thingy-ma-jig.co.uk/blog/17-02-2010/mysql-count-occurrences-string
		$query = "
			SELECT
				'tgmismatch' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@t@',''))) / LENGTH('@t@')) !=
				((LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@tg@',''))) / LENGTH('@tg@'))
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records without a *rpl
	public function report_missingrpl ()
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
	
	
	# Records in SPRI without a *rpl and without a real (i.e. not 'SUPPRESS') *status, that are not *ser; this aims to identify records that a broad subject heading, now a non-priority post-migration task
	public function report_missingrplstatus ()
	{
		# Define the query
		$query = "
			SELECT
				'missingrplstatus' AS report,
				recordId
			FROM catalogue_rawdata
			LEFT JOIN fieldsindex ON recordId = fieldsindex.id
			WHERE
				    fieldslist NOT LIKE '%@ser@%'
				AND fieldslist NOT LIKE '%@rpl@%'
				AND status IS NULL	-- Not using `fieldslist NOT LIKE '%@status@%'` as that includes status=SUPPRESS
				AND field = 'location'
				AND value != 'Not in SPRI'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records having only one *rpl and *rpl is O
	public function report_rploncewitho ()
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
	public function report_rpl3charaz09 ()
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
	public function report_locwithoutlocation ()
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
	public function report_unmatchedbrackets ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'unmatchedbrackets' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				(LENGTH(value)-LENGTH(REPLACE(value,'{','')))/LENGTH('{') !=	/* i.e. substr_count('{') */
				(LENGTH(value)-LENGTH(REPLACE(value,'}','')))/LENGTH('}')		/* i.e. substr_count('}') */
			";
		
		# Return the query
		return $query;
	}
	
	
	# records where brackets are nested, e.g. { { text } }
	public function report_nestedbrackets ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'nestedbrackets' AS report,
				recordId
			FROM catalogue_rawdata
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
	public function report_status ()
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
	public function report_multiplestatus ()
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
	public function report_statusparallel ()
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
	public function report_statusglaciopams ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'statusglaciopams' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			WHERE
				    field = 'status'
				AND value != 'GLACIOPAMS'
				AND value != 'SUPPRESS'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a *status field and *location where the status is not GLACIOPAMS
	public function report_statuslocationglaciopams ()
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
				AND value != 'SUPPRESS'
			";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records with one *location, which is Periodical
	public function report_doclocationperiodical ()
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
	public function report_doclocationlocationperiodical ()
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
	public function report_inwithj ()
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
	public function report_artnotjt ()
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
	public function report_sernonuniquet ()
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
	public function report_artbecomedoc ()
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
	public function report_arttoplevelp ()
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
	public function report_artinnokg ()
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
	public function report_artinnokglocation ()
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
	public function report_artwithk2 ()
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
	public function report_docwithkb ()
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
	public function report_loclocfiltered1 ()
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
						recordId, field		/* Limit for efficiency */
					FROM catalogue_rawdata
					WHERE NOT (field = 'location' AND value = 'Not in SPRI')
				) AS rawdata_filtered
				GROUP BY recordId
			) AS indexOfFields
			WHERE fieldslist REGEXP '@location.*@location'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with two or more locations, having first filtered out any locations whose location is 'Not in SPRI' or 'Periodical' or 'Basement IGS Collection'
	public function report_loclocfiltered2 ()
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
			) AS indexOfFields
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
	public function report_externallocations ()
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
						AND value NOT REGEXP \"" . $this->locationCodesRegexpSql . "\"
						AND value NOT REGEXP '^(IGS|International Glaciological Society)'
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
	
	
	# Records with only one external location, which is not on the whitelist
	public function report_singleexternallocation ()
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
				AND (
					-- Single location
						value NOT REGEXP \"" . $this->locationCodesRegexpSql . "\"
					OR
					-- Two locations: A; B
						(
						    value LIKE '%; %'
						AND value NOT REGEXP \"" . preg_replace ('/^\^/', '; ?', $this->locationCodesRegexpSql) . "\"
						)
				)
				-- These have been tested manually that there are no semicolon-containing records also matching these, to keep the query simpler
				AND value NOT REGEXP '^(IGS|International Glaciological Society)'
				AND value != 'Not in SPRI'
				AND value != '??'	/* Not done in the regexp to avoid possible backlash-related errors */
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with three or more locations
	public function report_loclocloc ()
	{
		# Define the query
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
	public function report_arttitlenoser ()
	{
		# Create temporary tables for use in the main query, as dynamic join in main query below is slow due to lack of indexing
		# NB As of 31/Jan/2019 creates 89,638 records in temp_articletitles
		$andConstraint = "AND EXTRACTVALUE(xml, 'art/j/loc/location') NOT LIKE '%Not in SPRI%'";
		$this->titlesMatchingTemporaryTables ($andConstraint);
		
		# Define the query; see: http://stackoverflow.com/a/367865 and http://stackoverflow.com/a/350180
		$query = "
			SELECT
				'arttitlenoser' AS report,
				id AS recordId
			FROM temp_articletitles AS articles
			LEFT JOIN temp_serialtitles AS serials
			ON (articles.title = serials.title)
			WHERE serials.title IS NULL
		";
		
		// NB Temporary tables will be cleaned up later in report_seriestitlemismatches
		
		# Return the query
		return $query;
	}
	
	
	# Locations not passing authority control, e.g. that each has a space after (or ends), so that "Reference 1" and "Reference" are correct, but not "References"
	public function report_locationauthoritycontrol ()
	{
		# Remove the numeric type from the location codes list for the purposes of this test
		$locationNamesRegexps = array_keys ($this->locationCodes);
		unset ($locationNamesRegexps[0]);	// Numeric one is the first, as noted in the comments
		$singleBackslash = '\\';
		$locationCodesRegexpSql = '^(' . str_replace ($singleBackslash, str_repeat ($singleBackslash, 2), implode ('|', $locationNamesRegexps)) . ')';
		
		# Define the query
		$query = "
			SELECT
				'locationauthoritycontrol' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value     REGEXP \"" . $locationCodesRegexpSql . "\"
				AND value NOT REGEXP \"" . $locationCodesRegexpSql . "( |$)\"
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items not in SPRI
	public function report_notinspri ()
	{
		# Define the query
		$query = "
			SELECT
				'notinspri' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value LIKE 'Not in SPRI'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items not in SPRI also having a SPRI location (which should not happen, i.e. no results)
	public function report_notinspriinspri ()
	{
		# Define the query
		$query = "
			SELECT
				'notinspriinspri' AS report,
				root.recordId
			FROM catalogue_processed AS root
			JOIN fieldsindex ON root.recordId = fieldsindex.id
			JOIN catalogue_processed AS others
				ON root.recordId = others.recordId
				AND others.field = 'location'
				AND others.value != 'Not in SPRI'
			WHERE
				    root.field = 'location'
				AND root.value = 'Not in SPRI'
				AND others.value REGEXP \"" . $this->locationCodesRegexpSql . "\"
			AND fieldslist REGEXP '@location@.+@location@'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items not in SPRI also with MISSING
	public function report_notinsprimissing ()
	{
		# Define the query
		$query = "
			SELECT
				'notinsprimissing' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE
				    xml LIKE BINARY '%MISSING%'
				AND xml LIKE '%Not in SPRI%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with location matching Cambridge University, not in SPRI
	public function report_loccamuninotinspri ()
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
	public function report_loccamuniinspri ()
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
	public function report_onordercancelled ()
	{
		# Define the query
		$query = "
			SELECT
				'onordercancelled' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'status'
				AND value LIKE 'ON ORDER%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items with an invalid *status
	public function report_invalidstatus ()
	{
		# Define the order *status keywords
		$orderStatusKeywords = array (
			'ON ORDER'			=> 'Item is in the acquisition process',
			'ON ORDER (O/P)'	=> 'On order, but out of print',
			'ON ORDER (O/S)'	=> 'On order, but out of stock',
			'RECEIVED'			=> 'Item has arrived at the library but is awaiting further processing before becoming available to users',
		);
		
		# Define the query
		$suppressionStatusKeyword = $this->marcConversion->getSuppressionStatusKeyword ();
		$query = "
			SELECT
				'invalidstatus' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'status'
				AND value NOT IN ('" . implode ("', '", array_keys ($orderStatusKeywords)) . "', '{$suppressionStatusKeyword}')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items with an invalid acquisition date
	public function report_invalidacquisitiondate ()
	{
		# Define the query
		$query = "
			SELECT
				'invalidacquisitiondate' AS report,
				recordId
				FROM catalogue_processed
				WHERE
					    xPath LIKE '%/acq/date'
					AND value != ''
					AND value NOT REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'	-- Require YYYY/MM/DD
					AND value NOT REGEXP '^[0-9]{4}$'					-- But also permit year only
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items with an empty *acq container
	public function report_emptyacq ()
	{
		# Define the query
		$query = "
			SELECT
				'emptyacq' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE xml REGEXP '<acq>[[:space:]]*</acq>'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items on order before the threshold acquisition date
	public function report_onorderold ()
	{
		# Define the query
		$query = "
			SELECT
				'onorderold' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE
				    EXTRACTVALUE(xml, '//status') LIKE 'ON ORDER%'
				AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'
				AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) < UNIX_TIMESTAMP('{$this->acquisitionDate} 00:00:00')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items on order since the threshold acquisition date
	public function report_onorderrecent ()
	{
		# Define the query
		$query = "
			SELECT
				'onorderrecent' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE
				    EXTRACTVALUE(xml, '//status') LIKE 'ON ORDER%'
				AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'
				AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) > UNIX_TIMESTAMP('{$this->acquisitionDate} 00:00:00')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Items where the order is cancelled
	public function report_ordercancelled ()
	{
		# Define the query
		$query = "
			SELECT
				'ordercancelled' AS report,
				catalogue_rawdata.recordId
			FROM catalogue_rawdata
			WHERE
				    field = 'status'
				AND value = 'ORDER CANCELLED'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with italics in the abstract
	public function report_absitalics ()
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
	
	
	# Records with invalid ISBN number, excluding ones known to be wrong in the printed original publication
	public function report_isbninvalid ()
	{
		# Obtain a list of every ISBN present; note that some records have more than one *isbn, so cannot index by recordId
		$isbnShards = $this->databaseConnection->select ($this->settings['database'], 'catalogue_processed', array ('field' => 'isbn'), array ('id', 'recordId', 'value'));
		
		# Remove qualifying information, as per macro_validisbn
		foreach ($isbnShards as $index => $isbnShard) {
			if (preg_match ('/^([0-9X]+) \(([^)]+)\)$/', $isbnShard['value'], $matches)) {
				$isbnShards[$index]['value'] = $matches[1];
			}
		}
		
		# Define a list of ISBNs known to be wrong in the original publication and which should be whitelisted from the report
		$knownIncorrect = array (
			49940, 67995, 77910, 90135, 96094, 102258, 109306, 115464, 115623, 115654, 122077, 127183, 127766, 127792, 128133, 131355, 131811, 131859, 131938, 132789, 132795, 132803, 132811, 133375, 134537, 136691, 138588, 140472, 140640, 142702, 142916, 142959,
			144197, 148754, 150974, 150976, 152587, 152975, 152981, 154635, 155343, 156438, 156583, 156879, 157302, 160744, 161652, 162789, 163738, 163880, 165289,
			165960, 166302, 166337, 167352, 167354, 167446, 167870, 168457, 168462, 168518, 169539, 169565, 169573, 169769, 169814, 170019, 170119, 170623, 171279, 171559, 171767, 171890, 172802, 172959, 172964, 173314, 173677, 173923, 175045, 175056, 176578, 176609, 177690, 177723, 177847, 178624, 178678, 178860,
			179102, 179123, 179187, 179772, 180184, 181433, 183050, 183070, 183078, 183222, 184238, 184308, 185511, 185531, 185538, 185683, 185716, 185755, 186228, 186857, 187041, 187751, 188931, 189452, 189453, 190314, 190400, 190594, 191243, 192590, 194602, 194809, 195370, 197175, 197473, 199672, 201299, 201906, 205837,
			212641, 214798,
		);
		
		# Obtain the ISBN library handle
		$isbn = $this->marcConversion->getIsbn ();
		
		# Find invalid ISBNs at code level by doing a full validation check
		$recordIds = array ();
		foreach ($isbnShards as $isbnShard) {
			if (!$isValid = $isbn->validation->isbn ($isbnShard['value'])) {
				if (in_array ($isbnShard['recordId'], $knownIncorrect)) {continue;}	// Skip whitelisted
				$recordIds[] = $isbnShard['recordId'];
			}
		}
		
		# End if no problems
		if (!$recordIds) {
			return "SELECT 'isbninvalid' AS report, recordId FROM catalogue_rawdata WHERE 1 = 0;";	// A bogus query (effectively a 'return false') returning the right fields but which will produce zero rows
		}
		
		# Compile a query which generate a result of static values; see: http://stackoverflow.com/questions/6156726/mysql-return-static-strings
		$subqueries = array ();
		foreach ($recordIds as $recordId) {
			$subqueries[] = "SELECT 'isbninvalid' AS report, {$recordId} AS recordId";
		}
		$query = implode ("\nUNION ALL\n", $subqueries);
		
		# Return the query
		return $query;
	}
	
	
	# Records with a badly-formatted URL
	public function report_urlinvalid ()
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
	public function report_ndnd ()
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
	public function report_misformattedad ()
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
	public function report_orphanedrole ()
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
	public function report_emptyauthor ()
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
	public function report_specialcharscase ()
	{
		# Define the query
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		$regexpBackslash	= $mysqlBacklash . $mysqlBacklash;			// http://lists.mysql.com/mysql/193376
		
		# Create the SQL clauses for each greek letter; each clause for that letter does a case insensitive match (which will find \galpha or \gAlpha or \galPHa), then excludes the perfect cases of \galpha \gAlpha
		$sqlWhere = array ();
		$greekLetters = $this->muscatConversion->import->greekLetters ();
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
	public function report_unknowndiacritics ()
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
	public function report_locationunknown ()
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
					OR (field = 'status' AND value = 'SUPPRESS')
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
	public function report_multiplecopies ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'multiplecopies' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    field IN('note', 'local')
				AND value LIKE '%opies%'
				AND value NOT LIKE '%SPRI also has%'
			";
		
		# Return the query
		return $query;
	}
	
	
	# Records where there appear to be multiple copies, in notes field, not matching the location count; see also /reports/multiplecopiesvalues/
	public function report_mismatchedmultiplecopies ()
	{
		# Define the query
		$query = "
			SELECT
				'mismatchedmultiplecopies' AS report,
				recordId
			FROM (
				SELECT
					recordId,
					TRIM( REPLACE( SUBSTRING( REPLACE(REPLACE(REPLACE(value, 'SPRI has four ', 'SPRI has 4 '), 'SPRI has three ', 'SPRI has 3 '), 'SPRI has two ', 'SPRI has 2 ') , 1, 11) , 'SPRI has ', '') ) AS title,
					CAST( (LENGTH(fieldslist)-LENGTH(REPLACE(fieldslist,'@location@','')))/LENGTH('@location@') AS UNSIGNED) AS locationCount
				FROM catalogue_processed
				JOIN fieldsindex ON catalogue_processed.recordId = fieldsindex.id
				WHERE
					    field IN('note', 'local', 'priv')
					AND value LIKE 'SPRI has % copies%'
					AND value NOT LIKE '%missing%'
				HAVING
					    title REGEXP '^([0-9]+)$'
					AND title != locationCount
				ORDER BY recordId
			) AS mismatchedmultiplecopies
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing more than one *in field
	public function report_multiplein ()
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
	public function report_multiplej ()
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
	public function report_multipletopt ()
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
	public function report_multipletoptt ()
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
	
	
	# Records with an invalid date range string
	public function report_invaliddaterangestring ()
	{
		# Find cases matching .^.
		$query = "
			SELECT DISTINCT
				'invaliddatestring' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'r'
				AND value REGEXP '([0-9]{3}[-0-9])'										-- Must look like a year, i.e. matches XXXX or XXX-
				AND value NOT REGEXP '^\\\\[([0-9]{4})\\\\]$'										-- [XXXX] is fine
				AND value NOT REGEXP '^([0-9]{4})-([0-9]{2}), ([0-9]{4})$'				-- XXXX-XX, XXXX is fine
				AND value NOT REGEXP '^([0-9]{4})-([0-9]{2}), ([0-9]{4})-$'				-- XXXX-XX, XXXX- is fine
				AND value NOT REGEXP '^([0-9]{4})-([0-9]{2}), ([0-9]{4})-([0-9]{2})$'	-- XXXX-XX, XXXX-XX is fine
				AND value NOT REGEXP '^([0-9]{4})-([0-9]{2}), ([0-9]{4})-([0-9]{2}), ([0-9]{4})-([0-9]{2})$'	-- XXXX-XX, XXXX-XX, XXXX-XX is fine
				AND (
					   value NOT REGEXP '[-0-9]$'										-- Error if does not end X or -
					OR value REGEXP '([0-9]{4})-([0-9]{2})([^0-9])'						-- Error if XXXX-XX then not a number
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with invalid syntax for a non-numeric date
	public function report_ndsyntax ()
	{
		# Define the query
		$query = "
			SELECT
				'ndsyntax' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'd'
				AND value NOT REGEXP '[0-9]'
				AND value != '[n.d.]'
				/* AND value != '?' */
				/* AND value != '-' */
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with more than one *d; this report is for helping determine how to represent 260 repeatable $c ; see: https://www.loc.gov/marc/bibliographic/bd260.html
	public function report_multipledate ()
	{
		# Define the query
		$query = "
			SELECT
				'multipledate' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				fieldslist REGEXP '@d@.+@d@' OR fieldslist REGEXP '@d@d@'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with more than one *p or *pt
	public function report_multipleppt ()
	{
		# Define the query
		$query = "
			SELECT
				'multipleppt' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				   fieldslist REGEXP '@p@p@'
				OR fieldslist REGEXP '@p@.+@p@'
				OR fieldslist REGEXP '@pt@pt@'
				OR fieldslist REGEXP '@pt@.+@pt@'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *ser records with two or more locations
	public function report_serlocloc ()
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
	public function report_artinperiodical ()
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
	public function report_multipleal ()
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
	public function report_541ccombinations ()
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
	public function report_541ccombinations2 ()
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
	public function report_unrecognisedks ()
	{
		# Define the query
		# A check has been done that *ks is never empty, using `SELECT * FROM catalogue_processed WHERE field = 'ks' AND (value = '' OR value IS NULL);`
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
				) AS ksValues
			LEFT JOIN udctranslations ON ksValues.value = udctranslations.ks
			WHERE
				    value NOT IN ('" . implode ("', '", $this->ksStatusTokens) . "')
				AND ks IS NULL
			GROUP BY recordId
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with malformed *ks values
	public function report_malformedks ()
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
	
	
	# Records with multiple adjacent *ks values
	public function report_multipleadjacentks ()
	{
		# Define the query
		$query = "
			SELECT
				'multipleadjacentks' AS report,
				id
			FROM fieldsindex
			WHERE
				fieldslist LIKE '%@ks@ks@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a malformed *n2 value
	public function report_malformedn2 ()
	{
		# Define the query
		$query = "
			SELECT
				'malformedn2' AS report,
				recordId
			FROM `catalogue_processed`
			WHERE
				    field = 'n2'
				AND value REGEXP '\\\\.[^])]$'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records that contain photocopy/offprint in *note/*local/*priv
	public function report_offprints ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'offprints' AS report,
				recordId
			FROM catalogue_processed
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
	public function report_duplicatedlocations ()
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
	public function report_subscriptssuperscripts ()
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
	public function report_italicbracketsorder ()
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
	public function report_translationnote ()
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
	public function report_multipletrees ()
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
	public function report_kgnotart ()
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
	public function report_langnott ()
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
			) AS indexOfFields
			WHERE
				    fieldslist LIKE '%@lang@%'
				AND fieldslist NOT LIKE '%@tt@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records whose (first) *ts does not match the start of a periodical title, based on processPeriodicalLocations ()
	public function report_doctsperiodicaltitle ()
	{
		# Define the query
		$query = "
			SELECT
				'doctsperiodicaltitle' AS report,
				child.recordId
			FROM catalogue_processed AS child
			LEFT JOIN catalogue_xml ON child.recordId = catalogue_xml.id
			LEFT JOIN periodicallocations ON EXTRACTVALUE(xml, '//doc/ts[1]') LIKE CONCAT(periodicallocations.title, '%')
			LEFT JOIN catalogue_processed AS parent ON periodicallocations.recordId = parent.recordId AND parent.field = 'location'
			WHERE child.field = 'location' AND child.value = 'Periodical'
			AND LENGTH(EXTRACTVALUE(xml, '//doc/ts[1]')) > 0
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with strings being transliterated but which appear to be in English; these can then be removed by adding cases in tables/transliterationProtectedStrings.txt or tables/transliterationProtectedShards.txt
	public function report_transliteratedenglish ()
	{
		# Define the query
		$query = "
			SELECT
				'transliteratedenglish' AS report,
				recordId
			FROM (
				SELECT
					recordId,
					title,
-- #!# Check this - /records/66296/ seems to be wrong for instance
					IF (INSTR(title_latin,'[') > 0, LEFT(title_latin,LOCATE('[',title_latin) - 1), title_latin) AS title_latin
				FROM transliterations
			) AS transliterations_firstParts
			WHERE
				    title_latin REGEXP '(the | of )'
				AND title_latin NOT LIKE '% = %'		-- Crude support for parallel titles; basically assumes that if = has been used, the record is likely to be correct
				AND title_latin != title				-- Exclude ones that have not resulted in transliteration due to protected strings
				AND title_latin NOT LIKE '%[%'			-- Exclude ones including square brackets
				AND title_latin NOT LIKE '%<em>%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose reverse transliteration is not reversible
	public function report_transliteratefailure ()
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
	
	
	# Records whose transliteration contains an incorrect <em> following transliteration
	public function report_transliterateem ()
	{
		# Define the query
		$query = "
			SELECT
				'transliterateem' AS report,
				recordId
			FROM catalogue_processed
			WHERE value LIKE BINARY '%<" . chr(0xc4).chr(0x97) . "m>%'		-- U+0117 : LATIN SMALL LETTER E WITH DOT ABOVE: http://www.fileformat.info/info/unicode/char/0117/index.htm
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an equivalent already in Voyager, targetted for merging
	public function report_voyagerrecords ()
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
	
	
	# Records whose *in or *j contains a *lang but the main part does not
	public function report_nohostlang ()
	{
		# Define the query
		$query = "
			SELECT
				'nohostlang' AS report,
				id AS recordId
			FROM
				catalogue_xml
			WHERE
				    ExtractValue(xml, '/*/tg/lang') = ''
				AND ExtractValue(xml, '/*/*/tg/lang') != ''
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an empty *lang
	public function report_emptylang ()
	{
		# Define the query
		$query = "
			SELECT
				'emptylang' AS report,
				recordId
			FROM
				catalogue_processed
			WHERE
				    field = 'lang'
				AND value = ''
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with Bibcheck errors
	public function report_bibcheckerrors ()
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
	
	
	# Records with multiple locations but marked as missing without disambiguation
	public function report_multiplelocationsmissing ()
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
				AND ExtractValue(xml, '//notes/local') NOT LIKE '%missing%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with both a *ks status and a *status; this report is to ensure that macro_cataloguingStatus loses no data, as it chooses one or the other
	public function report_coexistingksstatus ()
	{
		# Define the query
		$query = "
			SELECT
				'coexistingksstatus' AS report,
				catalogue_processed.recordId AS recordId
			FROM catalogue_processed
			LEFT JOIN catalogue_processed AS cp_missing ON catalogue_processed.recordId = cp_missing.recordId AND cp_missing.field = 'status' AND cp_missing.value != 'SUPPRESS'
			WHERE
				    catalogue_processed.field = 'ks'
				AND IF (INSTR(catalogue_processed.value,'[') > 0, LEFT(catalogue_processed.value,LOCATE('[',catalogue_processed.value) - 1), catalogue_processed.value) IN ('" . implode ("', '", $this->ksStatusTokens) . "')
				AND cp_missing.field IS NOT NULL
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a cataloguing status code coming from *ks
	public function report_statuscodeksderived ()
	{
		# Define the query
		$query = "
			SELECT
				'statuscodeksderived' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'ks'
				AND IF (INSTR(value,'[') > 0, LEFT(value,LOCATE('[',value) - 1), value) IN('" . implode ("', '", $this->ksStatusTokens) . "')
				AND value NOT LIKE 'MISSING%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a note containing the word 'missing' without a *ks MISSING; a whitelist excludes those that are not actually missing
	public function report_notemissing ()
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
				AND recordId NOT IN (
					" . implode (', ', $this->getNoteMissing ()) . "
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a note containing the word 'missing' indicating items for reviewing of physical documents (i.e. not actually data work)
	public function report_missingphysical ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'missingphysical' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				recordId IN (
					" . implode (', ', $this->getNoteMissing ()) . "
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Function to return the notemissing list
	private function getNoteMissing ()
	{
		# List of records where the word 'missing' appears but for a different reason that is not relevant for exclusion purposes, but needs post-migration record work or physical changes
		return array (
			1383, 1505, 1768, 2876, 3432, 3841, 3977, 4960, 5053, 5675,
			6786, 7137, 8015, 8384, 8537, 8550, 10224, 12034, 12189, 12571, 13849, 14580,
			16071, 19824, 20296, 23801, 23855, 23860, 28936, 29518, 29673, 31106,
			37067, 40626, 40644, 40851, 41809, 41973, 46545, 47315, 50061, 51959,
			51975, 52245, 52800, 56034, 57540, 58223, 61770, 64232, 67889, 68283,
			83687, 84133, 84295, 84331, 84346, 88409, 105785, 109544, 116681, 117144, 119322,
			121376, 132172, 133074, 134320, 134477, 137784, 134795, 145945, 146265, 150833, 150867,
			151620, 161098, 164668, 165710, 168597, 169401, 169943, 170621, 171496, 172091, 174600, 176826,
			179417, 181335, 184673, 189412, 190165, 191528, 196214, 198135, 209397, 210665, 214682, 215328,
		);
	}
	
	
	# Records with empty author containers
	public function report_emptyauthorcontainers ()
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
	
	
	# Records with \g remaining; this report is to determine that specialCharacterParsing supports all \g types
	public function report_backslashg ()
	{
		# Define the query
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		$likeBackslash		= $mysqlBacklash /* . $mysqlBacklash # seems to work only with one */;			// http://lists.mysql.com/mysql/193376 shows that LIKE expects a single MySQL backslash
		$query = "
			SELECT DISTINCT
				'backslashg' AS report,
				recordId
			FROM
				catalogue_processed
			WHERE
				value LIKE '%{$likeBackslash}g%' ESCAPE '|'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a bracketed title starting with a leading article, for checking the nfcount; this is to try to deal with the issue that titles starting [ , e.g. /records/9196/ (test #359)
	public function report_bracketednfcount ()
	{
		# Get the leading articles list, indexed by language
		$leadingArticles = $this->muscatConversion->marcConversion->leadingArticles ($groupByLanguage = false);
		
		# Define the query
		$query = "
			SELECT DISTINCT
				'bracketednfcount' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field IN('t', 'tc', 'to')
				AND value LIKE '[%'
				AND value REGEXP \"" . '^' . '\\\\[' . '(' . implode ('|', array_keys ($leadingArticles)) . ')' . "\"
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records marked *lang=Russian with a fully-bracketed title; see createTransliterationsTable ()
	# These represent records for a post-migration task where someone needs to research what the titles should actually be
	public function report_russianbracketedtitle ()
	{
		# Define the query
		#!#H Hardcoded language value
		$query = "
			SELECT
				'russianbracketedtitle' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE
				id IN (
					SELECT recordId FROM catalogue_processed WHERE field = 'lang' AND value = 'Russian'
				)
				AND LEFT(EXTRACTVALUE(xml, '*/tg/t') , 1) = '['
				AND RIGHT(EXTRACTVALUE(xml, '*/tg/t') , 1) = ']'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records (Russian) with L. in title to be checked individually; this is to check whether this is an initial of a name or L. for Linnaeus
	public function report_russianldottitles ()
	{
		# Define the query
		$query = "
			SELECT
				'russianldottitles' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 't'
				AND  IF( LOCATE(' / ', value), SUBSTR(value, 0, LOCATE(' / ', value)), value ) /* i.e. section before optional / - see: https://stackoverflow.com/a/3471224/180733 */  LIKE BINARY '%L.%'
				AND recordLanguage = 'Russian'
				AND recordId NOT IN (
					2968, 8249, 11369, 14883, 15923, 20439, 22852, 26047, 27641, 27648,
					32528, 37510, 37952, 37969, 44884, 45763, 45779, 48105, 48300, 48876,
					55456, 55457, 55458, 55460, 55462, 55907, 60875, 60882, 61024, 61025,
					63978, 65138, 65492, 68381, 78533, 80552, 80556, 89897, 90009, 90384,
					90688, 95569, 96208, 96730, 109206, 109808, 109811, 110973, 121155, 126067,
					135942, 136135, 136136, 136137, 136138, 136139, 136140, 136141, 136142, 136143,
					136144, 136226, 136438, 137371, 141472, 151603, 158531, 160640, 163045, 164418,
					164419, 164420, 164421, 164527, 168001, 168238, 168748, 168750, 169566, 182580,
					189597, 189598, 189599, 189600, 189601, 189647, 189649, 189650, 189651, 195000,
					196210, 199199, 202068, 208508, 209325, 211060, 212808
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records (Russian) whose parallel title component count does not match that of the title
	public function report_paralleltitlemismatch ()
	{
		# Define the query
		$query = "
			SELECT
				'paralleltitlemismatch' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE
				id IN (
					SELECT DISTINCT recordId FROM catalogue_processed WHERE recordLanguage = 'Russian' AND ((field = 't' AND xPath REGEXP '^/(art|doc|ser)/tg/t$') OR field = 'lpt') AND value LIKE '% = %'
				)
				AND
					(LENGTH( ExtractValue(xml, '/*/tg/t') )-LENGTH(REPLACE( ExtractValue(xml, '/*/tg/t') ,' = ','')))/LENGTH(' = ') !=
					(LENGTH( ExtractValue(xml, '/*/tg/lpt') )-LENGTH(REPLACE( ExtractValue(xml, '/*/tg/lpt') ,' = ','')))/LENGTH(' = ')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records where parallel titles and *lpt presence are not in sync
	public function report_paralleltitlesync ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'paralleltitlesync' AS report,
				recordId
			FROM transliterations
			WHERE
				field = 't'
				AND
					(lpt IS NULL and title_latin LIKE '% = %')
					OR
					(lpt IS NOT NULL AND title_latin NOT LIKE '% = %')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with empty scalar values
	public function report_emptyvalue ()
	{
		# Define the query
		#!# Whitelist of tags need to be checked in all cases where they are handled
		$query = "
			SELECT DISTINCT
				'emptyvalue' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    (value = '' OR value IS NULL)
				/* Whitelist of tags that are containers */
				AND field NOT IN('doc', 'art', 'j', 'in', 'ser', 'ag', 'a', 'al', 'tg', 'e', 'n', 'ee', 'pg', 'notes', 'k', 'k2', 'loc', 'url', 'urlft', 'acq', 'acc', 'doi')
				/* Whitelisted tags often empty considered acceptable */
				AND field NOT IN('kw')
		";
		
		# Return the query
		return $query;
	}
	
	
	# *ser records with no title
	public function report_sernotitle ()
	{
		# Define the query
		$query = "
			SELECT
				'sernotitle' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    xPath LIKE '/ser/tg/t'
				AND value = ''
		";
		
		# Return the query
		return $query;
	}
	
	
	# *ser records whose title is not unique
	public function report_sernonuniquetitle ()
	{
		# Define the query
		$query = "
			SELECT
				'sernonuniquetitle' AS report,
				periodicallocations.recordId
			FROM periodicallocations
			WHERE title IN (
				SELECT title
				FROM (
					SELECT
						COUNT(*) AS `Rows`,
						title
					FROM periodicallocations
					GROUP BY title
					HAVING Rows > 1
				) AS duplicateTitles
			)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with location= both Periodical and Pam
	# NB: Some records within this have been manually marked with MPP such that this error is generated: "Error in record #<recordId>: 650 UDC field 'MPP' is not a valid UDC code.", e.g. /records/11000/
	public function report_periodicalpam ()
	{
		# Define the query; use of IN() is not best-practice but this runs sufficiently quickly and query is simple
		$query = "
			SELECT
				'periodicalpam' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
				    field = 'location'
				AND value = 'Periodical'
				AND recordId IN(
					SELECT recordId
					FROM catalogue_rawdata
					WHERE
						    field = 'location'
						AND (value REGEXP '^Pam' OR value REGEXP '^Pam ')
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with location= both Periodical and Pam, grouped
	public function report_periodicalpamgrouped ()
	{
		// No action needed - the data is read dynamically in the associated _view method
		return true;
	}
	
	
	# View for report_periodicalpamgrouped
	public function report_periodicalpamgrouped_view ()
	{
		# Start the HTML
		$html = '';
		
		# Get the data; query is based on the same thing in report_periodicalpam () but with the joins to add in the journal name, item and locations; takes 4 seconds
		$query = "
			SELECT
				IFNULL(journalTitles.value, '[No title]') AS journalTitle,
				catalogue_rawdata.recordId,
				itemTitles.value AS itemTitle,
				GROUP_CONCAT(DISTINCT locations.value SEPARATOR \"\n\") AS locations
			FROM catalogue_rawdata
			LEFT JOIN catalogue_processed AS journalTitles ON catalogue_rawdata.recordId = journalTitles.recordId AND journalTitles.field = 't' AND journalTitles.xPath LIKE '/art/%/tg/t'
			LEFT JOIN catalogue_processed AS itemTitles ON catalogue_rawdata.recordId = itemTitles.recordId AND itemTitles.field = 't' AND itemTitles.xPath LIKE '/%/tg/t'
			LEFT JOIN catalogue_processed AS locations ON catalogue_rawdata.recordId = locations.recordId AND locations.field = 'location'
			WHERE
				    catalogue_rawdata.field = 'location'
				AND catalogue_rawdata.value = 'Periodical'
				AND catalogue_rawdata.recordId IN(
					SELECT recordId
					FROM catalogue_rawdata
					WHERE
						    field = 'location'
						AND (value REGEXP '^Pam' OR value REGEXP '^Pam ')
				)
			GROUP BY recordId, IFNULL(journalTitles.value, '[No title]'), itemTitle		-- To enable GROUP_CONCAT of locations.value
			ORDER BY journalTitle, recordId
		";
		$data = $this->databaseConnection->getData ($query);
		
		# If no data, state this
		if (!$data) {
			return $html = '<p>No matches.</p>';
		}
		
		# Modify columns
		foreach ($data as $index => $record) {
			$data[$index]['recordId'] = "<a href=\"{$this->baseUrl}/records/{$record['recordId']}/\">{$record['recordId']}</a>";
			$data[$index]['locations'] = nl2br ($record['locations']);
		}
		
		# Regroup by journal title
		$data = application::regroup ($data, 'journalTitle');
		
		# Render the HTML
		foreach ($data as $journalTitle => $records) {
			$html .= "\n<h3>" . htmlspecialchars ($journalTitle) . '</h3>';
			$html .= application::htmlTable ($records, array (), 'lines periodicalpamgrouped', $keyAsFirstColumn = false, $keyAsFirstColumn = true, $allowHtml = true, $showColons = false, false, false, array (), $compress = true, $showHeadings = false);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Records in Russian containing italicised string which may or may not be in Russian
	public function report_russianitalics ()
	{
		// No action needed - the data is read dynamically in the associated _view method
		return true;
	}
	
	
	# View for report_russianitalics
	public function report_russianitalics_view ()
	{
		# Start the HTML
		$html = '';
		
		# Get the data; query is based on the same thing in report_periodicalpam () but with the joins to add in the journal name, item and locations; takes 4 seconds
		$query = "
			SELECT
				recordId,
				SUBSTRING_INDEX(SUBSTRING_INDEX(title_latin, '<em>', -1), '</em>', 1) AS extract,	-- https://stackoverflow.com/a/28432157
				title_latin,
				title
			FROM transliterations
			WHERE title_latin LIKE '%<em>%'
		";
		$data = $this->databaseConnection->getData ($query);
		
		# Modify columns
		foreach ($data as $index => $record) {
			$data[$index]['recordId'] = "<a href=\"{$this->baseUrl}/records/{$record['recordId']}/\">{$record['recordId']}</a>";
		}
		
		# Render as HTML
		$html .= "\n<p>This report aims to facilitate manual inspection of Russian records where the title is in Russian but contains a section in italics. Currently, these italicised section are all protected, under the standard rule for italics. However, in some cases, these are names in Russian and should be upgraded to LoC and also converted to Cyrillic as if an unprotected string.</p>";
		$html .= "\n<p>There are " . count ($data) . ' records:</p>';
		$html .= application::htmlTable ($data, array (), 'lines', $keyAsFirstColumn = false, $keyAsFirstColumn = true, $allowHtml = true, $showColons = false, false, false, array (), $compress = true, $showHeadings = false);
		
		# Return the HTML
		return $html;
	}
	
	
	# Russian records with a volume number
	# This report is intended to help in diagnosis of whether 880 490 $v should be transliterated or not (i.e. use transliterateSubfields(av) rather than transliterateSubfields(a); e.g.:
	#   /records/36622/ has *ts ending "; Vyp. 12"
	#   /records/71583/ has *ts ending "; v. 1" which might be Russian
	#   /records/136356/ has *vno "Chast 2"
	#   /records/30602/ which has no *ts but inconsistently (compared to /records/136356/ ) has $vno "Vol.1", though this would not end up with a 880 490 $v
	public function report_russianvolumenumbers ()
	{
		# Define the query
		$query = "
			SELECT
				'russianvolumenumbers' AS report,
				recordId
			FROM catalogue_processed
			LEFT JOIN fieldsindex on catalogue_processed.recordId = fieldsindex.id
			WHERE
					fieldslist LIKE '%@ts@%'
				AND `recordLanguage` LIKE 'Russian'
				AND (
						(`field` = 'ts' AND `value` LIKE '%;%' AND `value` NOT REGEXP '; ?([0-9\(\)]+)$')
					OR
					    (`field` = 'vno')
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with long titles (>512 characters), that are not on the whitelist
	public function report_longtitles ()
	{
		# Define the whitelist
		$knownCorrect = array (
			1150, 2060, 2064, 2083, 2125, 2597, 2834, 3349, 4792, 6433,
			8690, 45836, 52812, 56641, 59671, 59763, 60676, 135449, 135450, 136479,
			142629, 142630, 148887, 149001, 155210, 162508, 192623, 209575
		);
		
		# Define the query
		$query = "
			SELECT
				'longtitles' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 't'
				AND LENGTH(value) > 512
				AND recordId NOT IN (" . implode (', ', $knownCorrect) . ")
		";
		
		# Return the query
		return $query;
	}
	
	
	# Articles in journals without a volume designation and no useful date; NB the year may be being used as a proxy
	public function report_artjwithoutvolume ()
	{
		# Define the query
		$query = "
			SELECT
				'artjwithoutvolume' AS report,
				recordId
			FROM catalogue_processed
			LEFT JOIN catalogue_xml ON catalogue_processed.recordId = catalogue_xml.id
			WHERE
				    xPath = '/art/j/pt'
				AND value like ':%'
				AND (
					   catalogue_xml.xml NOT LIKE '%<d>%'
					OR catalogue_xml.xml LIKE '%<d>%n.%</d>%'
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records with a *pt
	public function report_docpt ()
	{
		# Define the query
		$query = "
			SELECT
				'docpt' AS report,
				recordId
			FROM catalogue_processed
			WHERE xPath = '/doc/pt'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *art records without a *pt, where the record has a SPRI location
	public function report_artnopt ()
	{
		# Define the query
		$query = "
			SELECT
				'artnopt' AS report,
				fieldsindex.id AS recordId
			FROM fieldsindex
			LEFT JOIN catalogue_processed ON fieldsindex.id = catalogue_processed.recordId AND field = 'location'
			WHERE
				    fieldslist LIKE '%@art@%'
				AND fieldslist NOT LIKE '%@pt@%'
				AND value REGEXP \"" . $this->locationCodesRegexpSql . "\"
		";
		
		# Return the query
		return $query;
	}
	
	
	# *doc records without a *p
	public function report_docnop ()
	{
		# Define the query
		$query = "
			SELECT
				'docnop' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				    fieldslist LIKE '%@doc@%'
				AND fieldslist NOT LIKE '%@p@%'
				AND fieldslist NOT LIKE '%@status@%'
				AND location NOT LIKE '%Not in SPRI%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *art records with a *form
	public function report_artform ()
	{
		# Define the query
		$query = "
			SELECT
				'artform' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    xPath LIKE '%/art%'
				AND field = 'form'
				AND recordId NOT IN (2157, 11291, 14191, 47997, 47998, 47999, 48098, 52273, 62491, 194899)
		";
		
		# Return the query
		return $query;
	}
	
	
	# *ag records containing only an *ad, which causes data to be lost in the /ag loop in generate245::statementOfResponsibility (), as tested at /records/1681/ (test #190)
	public function report_agwithonlyad ()
	{
		# Define the query
		$query = "
			SELECT
				'agwithonlyad' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				fieldslist LIKE '%@ag@ad@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *t values ending with a dot
	public function report_tdot ()
	{
		# Define the query
		$query = "
			SELECT
				'tdot' AS report,
				recordId
			FROM catalogue_rawdata
			WHERE
					field LIKE 't'
				AND value LIKE '%.'
			-- Punctuation:
				AND value NOT LIKE '%...'
				AND value NOT LIKE '% ...'
				AND value NOT LIKE '%&c.'
				AND value NOT LIKE '% etc.'
				AND value NOT LIKE '% m.m.'
				AND value NOT LIKE '% m.v.'
				AND value NOT LIKE '% cont.'
				AND value NOT LIKE '% usw.'
				AND value NOT LIKE '% u.s.w.'
				AND value NOT LIKE '% o.a.'
			-- Initials:
				AND value NOT REGEXP BINARY '[A-Z]\.$'
				AND value NOT LIKE '% Shch.'
			-- Names:
				AND value NOT LIKE '% esq.'
				AND value NOT LIKE '% Esq.'
				AND value NOT LIKE '% Jr.'
				AND value NOT LIKE '% al.'
				AND value NOT LIKE '% eds.'
				AND value NOT LIKE '% Ltd.'
				AND value NOT LIKE '% Inc.'
				AND value NOT LIKE '% inc.'
				AND value NOT LIKE '% Co.'
				AND value NOT LIKE '% Jnr.'
				AND value NOT LIKE '% D.Sc.'
				AND value NOT LIKE '% Cantab.'
			-- Places:
				AND value NOT LIKE '% Fla.'
				AND value NOT LIKE '% Calif.'
				AND value NOT LIKE '% Ariz.'
				AND value NOT LIKE '% Ind.'
				AND value NOT LIKE '% Sask.'
				AND value NOT LIKE '% Wash.'
			-- Dates:
				AND value NOT LIKE '%19..'
				AND value NOT LIKE '% yr.'
				AND value NOT LIKE '% e.Kr.'
				AND value NOT LIKE '% n.e.'		-- Russian equivalent of A.D., in /records/173585/
				AND value NOT LIKE '% g.'
				AND value NOT LIKE '% v.'
				AND value NOT LIKE '% vv.'
				AND value NOT LIKE '% gg.'
				AND value NOT LIKE '% r.'
				AND value NOT LIKE '% g.g.'
				AND value NOT LIKE '% a^orh.'
			-- Pagination:
				AND value NOT LIKE '% s.'
				AND value NOT LIKE '% p.'
			-- Species (general):
				AND value NOT LIKE '% spp.'
				AND value NOT LIKE '% sp. nov.'
				AND value NOT LIKE '% subsp. nov.'
				AND value NOT LIKE '% sp.nov.'
				AND value NOT LIKE '% sp.'
				AND value NOT LIKE '% sp.n.'
				AND value NOT LIKE '% sp. n.'
				AND value NOT LIKE '% n.sp.'
				AND value NOT LIKE '% Linn.'
			-- Geographic:
				AND value NOT LIKE '% a.s.l.'
				AND value NOT LIKE '%\gdeg v. d.'
				AND value NOT LIKE '%\gdeg v.d.'
				AND value NOT LIKE '%\gmin v.d.'
				AND value NOT LIKE '% Doc.'
				AND value NOT LIKE '% Journ.'
				AND value NOT LIKE '% LLd.'
				AND value NOT LIKE '% Met.'
				AND value NOT LIKE '% Soc.'
				AND value NOT LIKE '% Mt.'
				AND value NOT LIKE '% mts.'
				AND value NOT LIKE '% gen. n.'
			-- Whitelist:
				AND recordId NOT IN(
					5618, 5664, 8921, 9415, 13565, 28879, 33982, 37885, 42876, 45427,
					49895, 50040, 52695, 63974, 80857, 93092, 114763, 121055, 122189, 123200,
					159027, 161607, 182382, 182985, 194628, 198922
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# *pt values containing space-colon-space; only *p should have this string
	public function report_ptspacecolonspace ()
	{
		# Define the query
		$query = "
			SELECT
				'ptspacecolonspace' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field LIKE 'pt'
				AND value LIKE '% : %'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *p values containing multiple cases of p.dot; this does contain some which will need to be whitelisted; this was used to list cases for resolution in /reports/problematicpdot/
	public function report_multiplepdot ()
	{
		# Define the query
		$query = "
			SELECT
				'multiplepdot' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field IN ('p', 'pt')
				AND value LIKE '%p.%'
				AND (LENGTH(value)-LENGTH(REPLACE(value,'p.','')))/LENGTH('p.') > 1
		";
		
		# Return the query
		return $query;
	}
	
	
	# Problematic p. cases, assuming that multiplepdot report is cleared
	public function report_problematicpdot ()
	{
		# Define the query
		$query = "
			SELECT
				'problematicpdot' AS report,
				recordId
			FROM
				(
					SELECT
					    recordId,
					REPLACE(REPLACE(REPLACE(REPLACE(value, ' p.', 'xxx'), '[n.p.]', 'xxx'), 'Supp.', 'xxx'), 'supp.', 'xxx') AS value
					FROM catalogue_processed
					WHERE field IN ('p', 'pt')
					AND value LIKE '%p.%'
					HAVING value LIKE '%p.%'
				)
				AS modified
			WHERE
			    value NOT REGEXP '[0-9]p\.'
			AND value NOT REGEXP '\[[0-9]\]p\.'
			AND value NOT REGEXP '^p\.'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Report for p not followed by a dot in *p / *pt
	public function report_pnodot ()
	{
		# Define the query
		$query = "
			SELECT
				'pnodot' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field IN ('p','pt')
				AND BINARY value REGEXP '[0-9]p[^.]'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *p values containing colon-space rather than space-colon-space
	public function report_pcolonspace ()
	{
		# Define the query
		$query = "
			SELECT
				'pcolonspace' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field LIKE 'p'
				AND value LIKE '%: %'
				AND value not like '% : %'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *ser records with multiple *r
	public function report_sermultipler ()
	{
		# Define the query
		$query = "
			SELECT
				'sermultipler' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    xPath LIKE '/ser%'
				AND xPathWithIndex LIKE '%/r[2]'
		";
		
		# Return the query
		return $query;
	}
	
	
	# /art/j records with no *kg in the Pamphlets
	#!# This report should be amended to exclude implicit title match - currently it looks only at explicit *kg match
	public function report_artjnokg ()
	{
		# Define the query
		$query = "
			SELECT
				'artjnokg' AS report,
				id AS recordId
			FROM `fieldsindex`
			WHERE
				    fieldslist LIKE '%@art@%'
				AND fieldslist LIKE '%@j@%'
				AND fieldslist NOT LIKE '%@kg@%'
				AND location LIKE '%@Pam %'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *t with explicit slash also having *e
	public function report_tslasheditors ()
	{
		# Define the query
		$query = "
			SELECT
				'tslasheditors' AS report,
				catalogue_processed.recordId AS recordId
			FROM catalogue_processed
			JOIN catalogue_processed AS second ON catalogue_processed.recordId = second.recordId AND second.field = 'e'
			WHERE
				    catalogue_processed.field LIKE 't'
				AND catalogue_processed.value LIKE '% / %'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing a field with an empty dash, with a SPRI location
	public function report_emptydashwithspri ()
	{
		# Define the query
		$query = "
			SELECT
				'emptydashwithspri' AS report,
				root.recordId AS recordId
			FROM catalogue_processed AS root
			JOIN catalogue_processed AS others
				ON root.recordId = others.recordId
				AND others.field = 'location'
			WHERE
				    root.value LIKE '-'
				AND others.field = 'location'
				AND others.value REGEXP \"" . $this->locationCodesRegexpSql . "\"
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an invalid *con syntax
	public function report_invalidcon ()
	{
		# Define the query
		$query = "
			SELECT
				'invalidcon' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field LIKE 'con'
				AND value NOT LIKE '% : %'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with names for transliteration in other languages (e.g. Yakut, Chinese, etc.) for upgrading
	public function report_othertransliterations ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'othertransliterations' AS report,
				recordId FROM catalogue_processed
			WHERE
				    field LIKE 'nt'
				AND value NOT IN('None', 'BGNRus', 'LOCRus')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with location = ??
	public function report_locationunassigned ()
	{
		# Define the query
		$query = "
			SELECT
				'locationunassigned' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value = '??'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with *to without *lto defined, having whitelisted English *to in a non-Russian record; as per macro_stripLeadingArticle240 (), *to is assumed to be in English unless an *lto is specified
	public function report_towithoutlto ()
	{
		# Define the query
		$query = "
			SELECT
				'towithoutlto' AS report,
				fieldsindex.id AS recordId
				-- catalogueT.value as '*t value',
				-- catalogueTo.value as '*to value',
				-- catalogueLto.value as '*lto value',
				-- language AS 'Record language'
			FROM `fieldsindex`
			-- LEFT JOIN catalogue_processed AS catalogueT on fieldsindex.id = catalogueT.recordId AND catalogueT.field = 't'
			-- LEFT JOIN catalogue_processed AS catalogueTo on fieldsindex.id = catalogueTo.recordId AND catalogueTo.field = 'to'
			LEFT JOIN catalogue_processed AS catalogueLto on fieldsindex.id = catalogueLto.recordId AND catalogueLto.field = 'lto'
			WHERE fieldslist LIKE '%@to@%'
			AND catalogueLto.value IS NULL
			AND fieldsindex.id NOT IN (		/* Exclude these whitelisted records, where the *to is in English, and the record is not Russian, so no need to add *lto=English */
				1314, 1921, 2052, 2194, 2346, 3169, 4273, 4618, 6897, 8792,
				9054, 9664, 9665, 9672, 9673, 9701, 10004, 10011, 10519, 10520,
				10853, 10854, 11148, 11637, 11756, 11758, 12199, 12364, 12381, 12386,
				12522, 12799, 13296, 13297, 13531, 14174, 14205, 14620, 15045, 15236,
				15465, 16139, 16483, 18280, 20477, 20859, 21652, 21933, 23164, 24048,
				24649, 25904, 25968, 27081, 29147, 29327, 30631, 30632, 30633, 30639,
				30759, 30903, 32009, 32010, 32128, 32204, 32205, 32206, 32207, 32239,
				33780, 33783, 35401, 37265, 38637, 39601, 39602, 39603, 39851, 40812,
				41777, 41999, 43395, 44399, 45527, 46408, 47175, 49734, 52709, 53512,
				53791, 57922, 59515, 59710, 61656, 67602, 67772, 72608, 74008, 74209,
				76030, 77044, 83998, 89351, 89666, 89771, 98487, 102617, 109301, 109309,
				128087, 137395, 139072, 139639, 139896, 144335, 148438, 150139, 151127, 151620,
				151977, 153704, 153825, 154314, 154521, 156529, 157007, 157995, 159057, 159158,
				162416, 162902, 164002, 165673, 166480, 166879, 166880, 167978, 167979, 168615,
				169981, 172386, 172754, 173300, 174384, 175758, 176529, 178300, 179644, 184900,
				188669, 189170, 194055, 196400, 196431, 196534, 196673, 198097, 198406, 198565,
				201042, 201214, 201543, 201912, 201913, 201916, 202389, 204049, 205099, 206330,
				207476, 208426, 208636, 210841
			)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with *nt=None which have not had / upgrading for 245
	public function report_ntnoneslashupgrade ()
	{
		# Define the query
		$query = "
			SELECT
				'ntnoneslashupgrade' AS report,
				catalogue_processed.recordId
			FROM catalogue_processed
			JOIN catalogue_processed AS processedT ON catalogue_processed.recordId = processedT.recordId AND processedT.xPath REGEXP '^/([^/]+)/tg/t$'
			WHERE
				    catalogue_processed.field = 'nt'
				AND catalogue_processed.value = 'None'
				AND catalogue_processed.xPath LIKE '%/a/%'
				AND processedT.value NOT LIKE '% / %'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Post-migration report for non-Russian records with Cyrillic *nt = BGNRus/LOCRus, supported as per test #728
	public function report_ntcyrillicinscope ()
	{
		# Define the query
		$query = "
			SELECT
				'ntcyrillicinscope' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'nt'
				AND value IS NOT NULL
				AND recordLanguage != 'Russian'
				AND value IN ('BGNRus', 'LOCRus')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Post-migration report for non-Russian records with all Cyrillic *nt for types never supported
	public function report_ntcyrillicunsupported ()
	{
		# Define the query
		$query = "
			SELECT
				'ntcyrillicunsupported' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'nt'
				AND value IS NOT NULL
				AND value NOT IN ('None', 'BGNRus', 'LOCRus')
		";
		
		# Return the query
		return $query;
	}
	
	
	# Post-migration report for records with *nt=LOCRus that need diacritics adding
	public function report_locrusnodiacritics ()
	{
		# Define the query
		$query = "
			SELECT
				'locrusnodiacritics' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'nt'
				AND value = 'LOCRus'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a suspected wrong language due to leading article mismatch in 245
	public function report_article245 ()
	{
		# Get the whitelisted records
		require_once ('generate245.php');
		$secondIndicatorSpecialCases = $this->marcConversion->generate245->getSecondIndicatorSpecialCases ();
		$whitelistedIds = array_keys ($secondIndicatorSpecialCases);
		
		# Define the query
		$query = "
			SELECT
				'article245' AS report,
				id AS recordId
			FROM catalogue_marc
			WHERE
				bibcheckErrors REGEXP '(245: First word, [a-z]+, may be an article, check 2nd indicator|245: First word, [a-z]+, does not appear to be an article, check 2nd indicator)'
			AND id NOT IN (" . implode (', ', $whitelistedIds) . ")
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose title is in round brackets
	public function report_titleroundbrackets ()
	{
		# Define the query
		$query = "
			SELECT
				'titleroundbrackets' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field LIKE 't'
				AND value LIKE '(%'
				AND value LIKE '%)'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *to/*ts records ending with a dot
	public function report_totsdotend ()
	{
		# Define the query
		$query = "
			SELECT
				'totsdotend' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field IN('to', 'ts')
				AND value LIKE '%.'
				AND value NOT LIKE '% gg.'
				AND value NOT LIKE '% g.'
				AND recordId NOT IN (
					-- *ts cases
					11557, 26328, 26336, 26933, 27437, 27438, 27443, 27444, 28578, 29854,
					30411, 43561, 59501, 63387, 68289, 112329, 136241, 153757, 171379, 179695,
					181096, 205665, 214848, 214850,
					-- *to cases
					2346, 8138, 32239, 39665, 51233, 70687, 93368, 124143, 132595, 214254
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Physical description with incorrect syntax
	public function report_physicalmisformat ()
	{
		# Define the query
		$query = "
			SELECT
				'physicalmisformat' AS report,
				id AS recordId
			FROM catalogue_marc
			WHERE
				    bibcheckErrors LIKE '%300: In subfield _a there should be a space between the number and the type of unit - please check.%'
				AND id NOT IN (164582, 203582)	-- Whitelist these two valid cases
		";
		
		# Return the query
		return $query;
	}
	
	
	# Incorrect syntax for a parallel title, using // rather than =
	public function report_paralleltitlesyntax ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'paralleltitlesyntax' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field IN ('t','tc','tt','to')
				AND value LIKE '%//%'
				AND recordId NOT IN (41853, 121719, 121998)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Incorrect syntax for a provenance note
	public function report_provenancenote ()
	{
		# Define the query
		$query = "
			SELECT
				'provenancenote' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field IN('note', 'local', 'priv')
				AND value LIKE 'Provenance%'
				AND value NOT REGEXP BINARY '^Provenance: [A-Z]'
				AND value NOT REGEXP BINARY '^Provenance: \"[A-Z]'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a note regarding post-migration instructions
	public function report_onmigration ()
	{
		# Define the query
		$query = "
			SELECT
				'onmigration' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'priv'
				AND value LIKE 'On migration%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with 087.5 but not Basement Shelf
	public function report_basementshelf0875 ()
	{
		# Define the query
		$query = "
			SELECT
				'basementshelf0875' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value LIKE '%087.5%'
				AND value NOT LIKE 'Basement Shelf%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a *st but the holding statement is open
	public function report_openst ()
	{
		# Define the query
		$query = "
			SELECT
				'openst' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'r'
				AND value LIKE '%-'
				AND recordId IN(
					SELECT id FROM fieldsindex WHERE fieldslist LIKE '%@st@%'
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records in Basement Seligman which need a parent record created
	public function report_basementseligman ()
	{
		# Define the query
		$query = "
			SELECT
				'basementseligman' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value LIKE '%Seligman%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# *lto present but no *to
	public function report_ltowithoutto ()
	{
		# Define the query
		$query = "
			SELECT
				'ltowithoutto' AS report,
				id
			FROM fieldsindex
			WHERE
				    fieldslist LIKE '%@lto@%'
				AND fieldslist NOT LIKE '%@to@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with more than one *to/*lto
	public function report_multipleto ()
	{
		# Define the query; uses substring count method in comments at: https://www.thingy-ma-jig.co.uk/blog/17-02-2010/mysql-count-occurrences-string
		$query = "
			SELECT DISTINCT
				'multipleto' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				   xPathWithIndex LIKE '%/to[2]'
				OR xPathWithIndex LIKE '%/tg/lto[2]'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records containing a field with an empty dash, without a SPRI location
	public function report_emptydashwithoutspri ()
	{
		# Define the query
		$query = "
				SELECT
					'emptydashwithoutspri' AS report,
					root.recordId AS recordId
				FROM catalogue_processed AS root
				JOIN catalogue_processed AS others ON root.recordId = others.recordId AND others.field = 'location'
				WHERE
					    root.value = '-'
					AND root.field NOT IN ('pu', 'pl')
					AND others.value NOT REGEXP \"" . $this->locationCodesRegexpSql . "\"
			UNION
				SELECT
					'emptydashwithoutspri' AS report,
					recordId
				FROM catalogue_processed
				JOIN fieldsindex ON recordId = fieldsindex.id
				WHERE
					    value = '-'
					AND field NOT IN ('pu', 'pl')
					AND fieldslist NOT LIKE '%@location@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with multiple sources (*ser)
	public function report_multiplesourcesser ()
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
	public function report_multiplesourcesdocart ()
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
	public function report_diacritics ()
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
	public function report_diacritics_view ()
	{
		# Start the HTML
		$html = '';
		
		# Obtain the data
		if (!$data = $this->databaseConnection->select ($this->settings['database'], 'listing_diacritics', array (), array (), true, $orderBy = 'id')) {	// ORDER BY id will maintain a-z,A-Z ordering of letters
			return $html = "\n<p>There is no data. Please re-run the report generation from the <a href=\"{$this->baseUrl}/import/\">import page</a>.</p>";
		}
		
		# Regroup by diacritic
		$data = application::regroup ($data, 'diacritic');
		
		# Load the diacritics table
		$diacriticsTable = $this->marcConversion->getDiacriticsTable ();
		
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
				$unicodeSymbol = $diacriticsTable["{$instance['letter']}^{$diacritic}"];
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
	public function report_journaltitles ()
	{
		return $this->createFieldListingReport ('journaltitle', 'Journal title');
	}
	
	
	# Report showing instances of series titles
	public function report_seriestitles ()
	{
		return $this->createFieldListingReport ('seriestitle', 'Series title');
	}
	
	
	# Function to create a report all values of a specified field
	private function createFieldListingReport ($field, $description)
	{
		# Create the table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.listing_{$field}s;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `listing_{$field}s` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '{$description}',
			`instances` int(11) NOT NULL COMMENT 'Instances',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of series title instances'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Select the data and insert it into the new table
		$query = "SELECT
				{$field} AS title,
				COUNT(*) AS instances
			FROM fieldsindex
			WHERE {$field} IS NOT NULL AND {$field} != ''
			GROUP BY {$field}
			ORDER BY " . $this->databaseConnection->trimSql ($field);
		$query = "INSERT INTO listing_{$field}s (title, instances) \n {$query};";
		$result = $this->databaseConnection->execute ($query);
		
		# Return the result
		return true;
	}
	
	
	# View for report_journaltitles
	public function report_journaltitles_view ()
	{
		return $html = $this->reportListing ('listing_journaltitles', 'distinct journal titles', 'journaltitle');
	}
	
	
	# View for report_seriestitles
	public function report_seriestitles_view ()
	{
		return $html = $this->reportListing ('listing_seriestitles', 'distinct series titles', 'seriestitle');
	}
	
	
	# Helper function to get a listing
	private function reportListing ($table, $description, $searchField, $idField = false, $query = false, $tableHeadingSubstitutions = array ('id' => '#'))
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
		$html .= $this->muscatConversion->valuesTable ($data, $searchField, false, $idField, true, $tableHeadingSubstitutions);
		
		# Return the HTML
		return $html;
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record (variant 1)
	public function report_seriestitlemismatches1 ()
	{
		$this->report_seriestitlemismatches (1, $locCondition = "= 'Periodical'");
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record (variant 1): record numbers
	public function report_seriestitlemismatches1records ()
	{
		return $query = $this->report_seriestitlemismatches (1, $locCondition = "= 'Periodical'", $captureRecordNumbersReport = 'seriestitlemismatches1records');
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record (variant 2)
	public function report_seriestitlemismatches2 ()
	{
		$this->report_seriestitlemismatches (2, $locCondition = "= ''");
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record (variant 3)
	public function report_seriestitlemismatches3 ()
	{
		$this->report_seriestitlemismatches (3, $locCondition = "NOT IN ('', 'Periodical')");
	}
	
	
	# Listing of articles without a matching serial (journal) title in another record; function is used by three variants
	private function report_seriestitlemismatches ($variantNumber, $locCondition, $captureRecordNumbersReport = false)
	{
		# Create the table
		$query = "DROP TABLE IF EXISTS {$this->settings['database']}.listing_seriestitlemismatches{$variantNumber};";
		$this->databaseConnection->execute ($query);
		$query = "CREATE TABLE IF NOT EXISTS `listing_seriestitlemismatches{$variantNumber}` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Series title',
			`instances` int(11) NOT NULL COMMENT 'Instances',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of series title mismatches'
		;";
		$this->databaseConnection->execute ($query);
		
		# Create temporary tables for use in the main query, as dynamic join in main query below is slow due to lack of indexing; confirmed no loc[5] exist
		$andConstraint = "AND (
			   EXTRACTVALUE(xml, 'art/j/loc[1]/location') {$locCondition}
			OR EXTRACTVALUE(xml, 'art/j/loc[2]/location') {$locCondition}
			OR EXTRACTVALUE(xml, 'art/j/loc[3]/location') {$locCondition}
			OR EXTRACTVALUE(xml, 'art/j/loc[4]/location') {$locCondition}
		)";
		$this->titlesMatchingTemporaryTables ($andConstraint);
		
		# Select the data and insert it into the new table
		$query = "
			SELECT
				DISTINCT articles.title,
				COUNT(*) AS instances
			FROM temp_articletitles AS articles
			LEFT JOIN temp_serialtitles AS serials
			ON (articles.title = serials.title)
			WHERE serials.title IS NULL
			GROUP BY articles.title
			ORDER BY instances DESC, " . $this->databaseConnection->trimSql ('articles.title') . "
		;";
		$query = "INSERT INTO listing_seriestitlemismatches{$variantNumber} (title, instances) \n {$query};";
		$result = $this->databaseConnection->execute ($query);
		
		# Capture record numbers if a record report is required
		if ($captureRecordNumbersReport) {
			
			# Assemble the query
			$query = "
				SELECT
					'{$captureRecordNumbersReport}' AS report,
					id AS recordId
				FROM temp_articletitles
			";
			
			// NB Temporary tables will be cleaned up later in report_seriestitlemismatches
			
			# Return the query
			return $query;
		}
		
		# Remove the temporary tables
		$this->titlesMatchingTemporaryTables ($andConstraint, true);
		
		# Implement countability, by adding an entry, without reference to recordId, into the report results table
		$query = "
			INSERT INTO reportresults (report,recordId)
				SELECT
					-- Get as many rows as exist, but ignore the actual data in them:
					'seriestitlemismatches{$variantNumber}' AS report,
					-1 AS recordId
				FROM listing_seriestitlemismatches{$variantNumber}
		;";
		$this->databaseConnection->execute ($query);
		
		# Return the result
		return true;
	}
	
	
	# Function to create temporary tables for use in arttitlenoser and seriestitlemismatches reports
	private function titlesMatchingTemporaryTables ($andConstraint, $dropping = false)
	{
		# Clean out
		$query = "DROP TABLE IF EXISTS {$this->settings['database']}.temp_articletitles;";
		$this->databaseConnection->execute ($query);
		$query = "DROP TABLE IF EXISTS {$this->settings['database']}.temp_serialtitles;";
		$this->databaseConnection->execute ($query);
		
		# End if only dropping
		if ($dropping) {return;}
		
		# Create a temporary table to extract the serial title within the record, where the record is not a pamphlet; as of 31/Jan/2019 creates 93,343 records for seriestitlemismatches3
		$query = "
			CREATE TABLE temp_articletitles (
				id INT NOT NULL,
				title VARCHAR(255) NOT NULL,
				PRIMARY KEY (id),
				INDEX (title)
			) ENGINE=MyISAM
			AS
				SELECT
					id,
					EXTRACTVALUE(xml, 'art/j/tg/t') AS title
				FROM catalogue_xml
					WHERE
						EXTRACTVALUE(xml, 'art/j') != ''							/* I.e. is in journal */
					AND EXTRACTVALUE(xml, 'art/j/k2/kg') = ''						/* I.e. does not have an explicit match, e.g. to deal with 'Les Alpes : revue du Club alpin suisse' and 'Die Alpen : Monatsschrift des Schweizer Alpenclub' both matching to /records/12961/ which has 'Die Alpen = Les Alpes = Le Alpi = Las Alps : Monatsschrift des Schweizer Alpenclub = revue du Club alpin Suisse = rivista del Club alpino svizzero = survista del Club alpin svizzer' */
					AND EXTRACTVALUE(xml, 'art/j/loc/location') NOT LIKE 'Pam %'	/* I.e. has a location which is not pamphlet */
					AND EXTRACTVALUE(xml, 'art/j/loc/location') NOT LIKE 'Special Collection %'	/* I.e. has a location which is not in the special collection (historic materials, bound copies together, early pamphlets) */
					AND EXTRACTVALUE(xml, 'status') = ''
					{$andConstraint}
		;";
		$this->databaseConnection->execute ($query);
		
		# Create a temporary table (as dynamic join in main query below is slow due to lack of indexing) to extract the title from the parent serials; as of 31/Jan/2019 creates 3,333 records for seriestitlemismatches3
		$query = "
			CREATE TABLE temp_serialtitles (
				title VARCHAR(255) NOT NULL,
				INDEX (title)
			) ENGINE=MyISAM
			AS
				/* Subquery to extract the title from the parent serials */
				SELECT
					EXTRACTVALUE(xml, 'ser/tg/t') AS title
				FROM catalogue_xml
				WHERE EXTRACTVALUE(xml, 'ser/tg/t') != ''		/* Implicit within this that it is a serial */
		;";
		$this->databaseConnection->execute ($query);
		
		# Fix entities in each table due to SQL ExtractValue returning the raw entities, e.g. /records/4137/ ha "Geographical Magazine (Trubner &amp; Co)" before conversion; see: https://stackoverflow.com/questions/30194976/
		$tables = array ('temp_articletitles', 'temp_serialtitles');
		foreach ($tables as $table) {
			$query = "
				UPDATE `{$table}`
				SET title = REPLACE( REPLACE( REPLACE( REPLACE( REPLACE( title, '&amp;', '&'), '&lt;', '<'), '&gt;', '>'), '&quot;', '\"'), '&apos;', \"'\")
			;";
			$this->databaseConnection->execute ($query);
		}
	}
	
	
	# View for report_seriestitlemismatches
	public function report_seriestitlemismatches1_view ()
	{
		return $html = $this->reportListing ('listing_seriestitlemismatches1', 'distinct series titles which do not match any parent serial title', 'journaltitle');
	}
	
	
	# View for report_seriestitlemismatches
	public function report_seriestitlemismatches2_view ()
	{
		return $html = $this->reportListing ('listing_seriestitlemismatches2', 'distinct series titles which do not match any parent serial title', 'journaltitle');
	}
	
	
	# View for report_seriestitlemismatches
	public function report_seriestitlemismatches3_view ()
	{
		return $html = $this->reportListing ('listing_seriestitlemismatches3', 'distinct series titles which do not match any parent serial title', 'journaltitle');
	}
	
	
	# Records with multiple *locations whose 541 needs to be made specific to the location
	public function report_transfer541 ()
	{
		# Define the query
		$query = "
			SELECT
				'transfer541' AS report,
				catalogue_marc.id AS recordId
			FROM catalogue_marc
			JOIN catalogue_processed ON catalogue_marc.id = catalogue_processed.recordId AND field = 'location'
			WHERE
				    marc LIKE '%541 0# %'
				AND xPathWithIndex LIKE '%[2]%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records whose item records will need enumeration (*doc with *v)'
	public function report_volumeenumeration ()
	{
		# Define the query
		$query = "
			SELECT DISTINCT
				'volumeenumeration' AS report,
				recordId
			FROM catalogue_processed
			WHERE xPath = '/doc/v'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with possible but unconfirmed Voyager matches needing merging
	public function report_voyagerbelievedmatch ()
	{
		# Define the query
		$query = "
			SELECT
				'voyagerbelievedmatch' AS report,
				id AS recordId
			FROM catalogue_marc
			WHERE mergeVoyagerId LIKE '%?%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with both 773 (host entry) and 852 (item record) fields
	public function report_hostwithitem ()
	{
		# Define the query
		$query = "
			SELECT
				'hostwithitem' AS report,
				id AS recordId
			FROM catalogue_marc
			WHERE
				    marc LIKE '%773 0# %'
				AND marc LIKE '%852 7# %'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a condition report but no accession number
	public function report_conwithoutacc ()
	{
		# Define the query
		$query = "
			SELECT
				'conwithoutacc' AS report,
				id AS recordId
			FROM fieldsindex
			WHERE
				    fieldslist LIKE '%@con@%'
				AND fieldslist NOT LIKE '%@ref@%'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with a rogue comma at the end of the *ad
	public function report_adcomma ()
	{
		# Define the query
		$query = "
			SELECT
				'adcomma' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'ad'
				AND value LIKE '%,'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records having a Russian *lang (thus transliterable) with different *lang vs *in/*lang
	public function report_splitlang ()
	{
		# Define the query
		$query = "
			SELECT
				'splitlang' AS report,
				id AS recordId
			FROM catalogue_xml
			WHERE
				    ExtractValue(xml, '/*/tg/lang')    != ''
				AND ExtractValue(xml, '/*/in/tg/lang') != ''
				AND ExtractValue(xml, '/*/tg/lang')    != ExtractValue(xml, '/*/in/tg/lang')
				AND (
					   ExtractValue(xml, '/*/tg/lang')    LIKE '%Russian%'
					OR ExtractValue(xml, '/*/in/tg/lang') LIKE '%Russian%'
				)
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with *location=Periodical remaining
	public function report_locationperiodical ()
	{
		# Define the query
		$query = "
			SELECT
				'locationperiodical' AS report,
				recordId
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value = 'Periodical'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with an *art joining (via *kg) to an *art
	public function report_artkgart ()
	{
		# Define the query
		$query = "
			SELECT
				'artkgart' AS report,
				catalogue_processed.recordId
			    -- ,catalogue_processed.field,
			    -- catalogue_processed.value,
			    -- catalogue_processed.xPath,
			    -- lookup.xPath
			FROM catalogue_processed
			JOIN catalogue_processed AS lookup ON catalogue_processed.value = lookup.recordId
			WHERE
				    catalogue_processed.xPath LIKE '/art%'
			    AND catalogue_processed.field = 'kg'
			    AND lookup.xPath = '/art'
		";
		
		# Return the query
		return $query;
	}
	
	
	# Records with multiple holdings at the same location
	public function report_multipleholdingssamelocation ()
	{
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
		# Get the location codes
		$locationCodes = array_unique (array_values ($this->locationCodes));
		
		# Create SQL fragments, essentially an SQL equivalent of substr_count
		$whereOr = array ();
		foreach ($locationCodes as $locationCode) {
			if ($locationCode == 'IGNORE') {continue;}	// Skip IGNORE
			$whereOr[] = "(LENGTH(marc)-LENGTH(REPLACE(marc,'{$this->doubleDagger}c{$locationCode}','')))/LENGTH('{$this->doubleDagger}c{$locationCode}') > 1";
		}
		
		# Compile the SQL where clause
		$where = '(' . implode (") OR\n\t\t\t\t(", $whereOr) . ')';
		
		# Define the query
		$query = "
			SELECT
				'multipleholdingssamelocation' AS report,
				id
			FROM catalogue_marc
			WHERE
				{$where}
		";
		
		# Return the query
		return $query;
	}
	
	
	# Report showing instances of series titles
	public function report_languages ()
	{
		// No action needed - the data is created in the fieldsindex stage
		return true;
	}
	
	
	# View for report_seriestitles
	public function report_languages_view ()
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
	public function report_transliterations ()
	{
		# Implement countability, defining problem cases as reversibility failures
		$sql = "
			INSERT INTO reportresults (report,recordId)
				SELECT
					'transliterations' AS report,
					recordId
				FROM transliterations
				WHERE forwardCheckFailed = 1
		;";
		$this->databaseConnection->execute ($sql);
		
		// No action needed - the data is created in the fieldsindex phase
		return true;
	}
	
	
	# View for report_transliterations
	public function report_transliterations_view ()
	{
		# Start the HTML
		$html = '';
		
		# Regenerate on demand during testing
		//ini_set ('max_execution_time', 0);
		//$this->createTransliterationsTable ();
		
		# Start a list of SQL constraints for the listing
		$where = array ();
		
		# Determine whether to filter to reversibility failures only
		$enableFilter = (isSet ($_GET['filter']) && $_GET['filter'] == '1');
		$filterConstraint = 'forwardCheckFailed = 1';
		if ($enableFilter) {
			$where[] = $filterConstraint;
		}
		
		# Get the XPath types
		$xPathTypesQuery = "SELECT xPath, COUNT(*) AS total FROM transliterations " . ($enableFilter ? 'WHERE ' . $filterConstraint : '') . " GROUP BY xPath ORDER BY xPath;";
		$xPathTypes = $this->databaseConnection->getPairs ($xPathTypesQuery);
		
		# Determine if a valid XPath filter has been specified
		$xPathFilter = (isSet ($_GET['xpath']) && isSet ($xPathTypes[$_GET['xpath']]) ? $_GET['xpath'] : false);
		if ($xPathFilter) {
			$where[] = "xpath = '{$xPathFilter}'";
		}
		
		# Determine whether to filter to unmatched text only
		$enableUnmatched = false;
		$enableUnmatchedField = substr ($xPathFilter, strrpos ($xPathFilter, '/') + 1);	// Extract field from end of path
		$unmatchingInScope = (in_array ($enableUnmatchedField, $this->transliterationNameMatchingFields));
		if ($unmatchingInScope) {
			$unmatchedOptionValues = array ('0', '1', '2', '3', '4', '<2', '<3', '<4', '<5');	// Assumes level of 5 is OK
			if ($enableUnmatched = (isSet ($_GET['unmatched']) && in_array ($_GET['unmatched'], $unmatchedOptionValues) ? $_GET['unmatched'] : false)) {
				preg_match ('/(<?)([0-9]+)/', $enableUnmatched, $matches);	// Split into optional < and number
				$where[] = 'inNameAuthorityList >= 0 AND inNameAuthorityList ' . ($matches[1] ? ($matches[1]) : '=') . ' ' . $matches[2];
			}
		}
		
		# Determine totals
		$table = 'transliterations';
		$totalRecords = $this->databaseConnection->getTotal ($this->settings['database'], $table);
		$totalFailures = $this->databaseConnection->getTotal ($this->settings['database'], $table, 'WHERE ' . $filterConstraint);
		
		# Show top-level filter controls
		if ($enableFilter) {
			$html .= "\n<p><a href=\"{$this->baseUrl}/reports/transliterations/" . ($xPathFilter ? "?xpath={$xPathFilter}" : '') . "\">Show all (" . number_format ($totalRecords) . ")</a> | <strong>Filtering to reversibility failures only (" . number_format ($totalFailures) . ")</strong></p>";
		} else {
			$html .= "\n<p><strong>Showing all (" . number_format ($totalRecords) . ")</strong> | <a href=\"{$this->baseUrl}/reports/transliterations/?filter=1" . ($xPathFilter ? "&amp;xpath={$xPathFilter}" : '') . "\">Filter to reversibility failures only (" . number_format ($totalFailures) . ")</a></p>";
		}
		
		# Add links to XPaths for filtering
		$xPathTypesListByField = array ();
		$xPathTypes = array_merge (array ('' => ($enableFilter ? $totalFailures : $totalRecords)), $xPathTypes);
		foreach ($xPathTypes as $xPathType => $total) {
			$field = ($xPathType ? '*' . substr ($xPathType, strrpos ($xPathType, '/') + 1) : '');	// e.g. 't' or 'pu', or '' for no filter
			$xPathTypesListByField[$field][$xPathType] = '';
			if ($xPathFilter != $xPathType) {	// Do not hyperlink any currently-selected item
				$xPathTypesListByField[$field][$xPathType] .= "<a href=\"{$this->baseUrl}/reports/transliterations/" . ($xPathType || $enableFilter ? '?' : '') . ($enableFilter ? 'filter=1' . ($xPathType ? '&amp;' : '') : '') . ($xPathType ? "xpath={$xPathType}" : '') . '">';
			} else {
				$xPathTypesListByField[$field][$xPathType] .= '<strong>';
			}
			$xPathTypesListByField[$field][$xPathType] .= ($xPathType == '' ? 'No type filter' : $xPathType);
			$xPathTypesListByField[$field][$xPathType] .= ' (' . number_format ($total) . ')';
			if ($xPathFilter != $xPathType) {
				$xPathTypesListByField[$field][$xPathType] .= '</a>';
			} else {
				$xPathTypesListByField[$field][$xPathType] .= '</strong>';
			}
		}
		
		# Compile the HTML
		foreach ($xPathTypesListByField as $field => $xPathTypesList) {
			$xPathTypesListByField[$field] = implode (' &nbsp; <span class="faded">|</span> &nbsp; ', $xPathTypesList);
		}
		$html .= "\n<p>Then filter by type:<br />";
		$html .= "\n" . application::htmlTableKeyed ($xPathTypesListByField, array (), $omitEmpty = false, 'lines', $allowHtml = true);
		$html .= '</p>';
		
		# Determine query string for pagination consistency if required
		$parameters = $_GET;
		$internalParameters = array ('action', 'item', 'page');
		foreach ($internalParameters as $internalParameter) {
			if (isSet ($parameters[$internalParameter])) {
				unset ($parameters[$internalParameter]);
			}
		}
		$queryString = http_build_query ($parameters);
		
		# For transliteration name matching fields, add level filtering control
		if ($unmatchingInScope) {
			$optionsHtml = array ();
			foreach ($unmatchedOptionValues as $option) {
				if ($enableUnmatched == $option) {
					$optionsHtml[$option] = '<strong>' . htmlspecialchars ($option) . '</strong>';
				} else {
					$parameters['unmatched'] = $option;
					$optionsHtml[$option] = '<a href="' . htmlspecialchars ($_SERVER['SCRIPT_URL'] . '?' . str_replace ('%2F', '/', http_build_query ($parameters))) . '">' . htmlspecialchars ($option) . '</a>';
				}
			}
			$html .= "\n<p id=\"semanticmatcheslinks\">For *{$enableUnmatchedField} require semantic matches: " . implode (' ', $optionsHtml) . ' match(es).</p>';
		}
		
		# Add link to editing the definition
		$html .= "\n<p>You can <a href=\"{$this->baseUrl}/transliterator.html\">edit the reverse-transliteration definition</a>.</p>";
		
		# Define the query
		$query = "SELECT
				*
			FROM {$this->settings['database']}.{$table}
			" . ($where ? 'WHERE ' . implode (' AND ', $where) : '') . "
		;";
		
		# Default to 1000 per page
		$this->settings['paginationRecordsPerPageDefault'] = 1000;
		
		# Obtain the listing HTML, passing in the renderer callback function name
		$html .= $this->muscatConversion->recordListing (false, $query, array (), '/reports/transliterations/', false, $queryString, $view = 'callback(transliterationsRenderer)');
		
		# Return the HTML
		return $html;
	}
	
	
	# Callback to provide a renderer
	public function transliterationsRenderer ($data)
	{
		# Remove internal fields
		foreach ($data as $id => $record) {
			unset ($data[$id]['title']);
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
		
		# Show whether the generated Cyrillic is in the name authority list, where the data exists
		foreach ($data as $id => $record) {
			if (in_array ($record['field'], $this->transliterationNameMatchingFields)) {
				switch (true) {
					case $data[$id]['inNameAuthorityList'] == '-1'                  : $cssClass = 'present';  break;	// LoC
					case $data[$id]['inNameAuthorityList'] == '-9999'               : $cssClass = 'absent';   break;	// No match
					case in_array ($data[$id]['inNameAuthorityList'], range (0, 4)) : $cssClass = 'absent';   break;	// 0-4 matches in Russian Wikipedia
					case $data[$id]['inNameAuthorityList'] >= 5                     : $cssClass = 'probable'; break;	// 5+ matches in Russian Wikipedia
					default: $cssClass = NULL; // No data, e.g. field relevant
				}
				if ($cssClass) {
					$data[$id]['title_spellcheck_html'] = "\n\t\t\t" . "<span class=\"whitelisting {$cssClass}\">" . $data[$id]['title_spellcheck_html'] . '</span>';
					if ($data[$id]['inNameAuthorityList'] >= 0) {
						$data[$id]['title_spellcheck_html'] .= "\n\t\t\t" . '<a href="https://www.google.co.uk/search?q=' . htmlspecialchars ('"' . trim (strip_tags ($data[$id]['title_spellcheck_html'])) . '"') . '" target="_blank" class="noarrow"><img src="/images/icons/magnifier.png" alt="" class="icon" /></a>' . "<span class=\"small faded\">{$data[$id]['inNameAuthorityList']}</span>";
						$data[$id]['title_spellcheck_html'] .= "\n\t\t\t" . '&nbsp; <img class="whitelist" data-id="' . $record['id'] . '" src="/images/icons/thumb_up.png" title="Mark name as OK" alt="" class="icon" />';
					}
				}
			}
			unset ($data[$id]['inNameAuthorityList']);
		}
		
		# Link each record
		foreach ($data as $id => $record) {
			$data[$id]['recordId'] = "<a href=\"{$this->baseUrl}/records/{$record['recordId']}/\">{$record['recordId']}</a>";
			$data[$id]['recordId'] .= '<span class="faded">' . preg_replace ("/^{$record['recordId']}/", '', $record['id']) . '</span>';
			unset ($data[$id]['id']);
		}
		
		# Show the record and field together
		foreach ($data as $id => $record) {
			$data[$id]['recordId'] .= '&nbsp;*' . $record['field'];
			unset ($data[$id]['field']);
		}
		
		# Start the HTML with the dynamic clickability
		$html  = "\n" . '<script src="//code.jquery.com/jquery-3.1.1.min.js"></script>';
		$html .= "
		<script type=\"text/javascript\">
			$(function() {
				$('img.whitelist').click(function() {
					
					var id = $(this).data('id');
					var text = $(this).siblings('.whitelisting').text().trim();
					
					$.ajax({
						type: 'GET',
						data: 'do=whitelist&id=' + id,
						success: function(data) {
							
							// Set the class for each matching text
							var cssClass = (data.result == 1 ? 'probable' : 'absent');
							$('span.whitelisting').filter(function() {
								return $(this).text().trim() === text;
							}).removeClass('absent').removeClass('probable').addClass(cssClass);
							
							// Toggle thumbs up/down adjacent to each matching text
							var imgSrc = (data.result == 1 ? 'thumb_down.png' : 'thumb_up.png');
							$('span.whitelisting').filter(function() {
								return $(this).text() === text;
							}).siblings('img.whitelist').attr('src', '/images/icons/' + imgSrc);
						},
						error: function() {
							alert ('Error: could not whitelist this item!');
						},
						url: '{$this->baseUrl}/data.json',
						cache: false
					});
				});
			});
		</script>
		";
		
		# Add the table as HTML; records already may contain tags
		$tableHeadingSubstitutions = array (
			'recordId' => '#',
			'title_spellcheck_html' => 'Generated Cyrillic (from BGN/PCGN)',
			'title_latin' => 'Muscat (transliteration, as entered)',
			'title_loc' => 'Library of Congress Cyrillic (Voyager)',
		);
		$html .= application::htmlTable ($data, $tableHeadingSubstitutions, 'lines', $keyAsFirstColumn = false, false, $allowHtml = true);
		
		# Render the HTML
		return $html;
	}
	
	
	# Report showing records with parallel titles, filtered to Russian
	public function report_paralleltitlelanguages ()
	{
		// No action needed - the data is created dynamically
		return true;
	}
	
	
	# View for report_paralleltitlelanguages
	public function report_paralleltitlelanguages_view ()
	{
		# Start the HTML
		$html = '';
		
		# Define the query; NB have checked no results with equivalent /*/*/tg/t together with /*/*/tg/lang
		# NB MySQL does not appear to support (//lang)[n] but an explicit top-part is wanted here anyway - see https://stackoverflow.com/questions/57600620/how-to-use-xpath-pathn-syntax-in-mysqls-extractvalue
		$query = "SELECT
				id,
				ExtractValue(xml, '/*/tg/t') as topLevelT,
				CAST( (LENGTH( ExtractValue(xml, '/*/tg/t') )-LENGTH(REPLACE( ExtractValue(xml, '/*/tg/t') ,' = ','')))/LENGTH(' = ') AS SIGNED) + 1 AS totalParts,
				ExtractValue(xml, 'count(//lang)') AS totalLang,
				'' AS isDifferent,
				ExtractValue(xml, '/*/tg/lang[1]') AS lang1,
				ExtractValue(xml, '/*/tg/lang[2]') AS lang2,
				ExtractValue(xml, '/*/tg/lang[3]') AS lang3,
				ExtractValue(xml, '/*/tg/lang[4]') AS lang4
			FROM catalogue_xml
			WHERE
				    ExtractValue(xml, '/*/tg/t') LIKE '% = %'
				AND ExtractValue(xml, '/*/tg/lang') LIKE '%Russian%'
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
				AND ExtractValue(xml, '/*/tg/lang') LIKE '%Russian%'
		";
		$knownTotalAvailable = $this->databaseConnection->getOneField ($countingQuery, 'total');
		
		# Obtain the listing HTML, passing in the renderer callback function name
		$html .= $this->muscatConversion->recordListing (false, $query, array (), '/reports/paralleltitlelanguages/', false, false, $view = 'callback(paralleltitlelanguagesRenderer)', false, $knownTotalAvailable);
		
		# Return the HTML
		return $html;
	}
	
	
	# Callback to provide a renderer for report_paralleltitlelanguages
	public function paralleltitlelanguagesRenderer ($data)
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
	public function report_distinctn1notfollowedbyn2 ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for distinct values of all *n1 fields that are not immediately followed by a *n2 field
	public function report_distinctn1notfollowedbyn2_view ()
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
	public function report_distinctn2notprecededbyn1 ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for distinct values of all *n2 fields that are not immediately preceded by a *n1 field
	public function report_distinctn2notprecededbyn1_view ()
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
	public function report_multiplecopiesvalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records where there appear to be multiple copies, in notes field - unique values
	public function report_multiplecopiesvalues_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				value AS title,
				COUNT(recordId) AS instances
			FROM catalogue_processed
			WHERE
				    field IN('note', 'local', 'priv')
				AND value LIKE 'SPRI has %'
			GROUP BY value
			ORDER BY value
		;";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', 'anywhere', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Records where kw is unknown, showing the bibliographer concerned
	public function report_kwunknown ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records where kw is unknown, showing the bibliographer concerned
	public function report_kwunknown_view ()
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
	public function report_unrecognisedksvalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records with unrecognised *ks values - distinct *ks values
	public function report_unrecognisedksvalues_view ()
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
				) AS ksValues
			LEFT JOIN udctranslations ON ksValues.value = udctranslations.ks
			WHERE
				    value NOT IN ('" . implode ("', '", $this->ksStatusTokens) . "')
				AND ks IS NULL
			GROUP BY value
			";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'values', 'anywhere', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# Muscat locations that do not map to Voyager locations
	public function report_voyagerlocations ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for Muscat locations that do not map to Voyager locations
	public function report_voyagerlocations_view ()
	{
		# Define a manual query
		$query = "
			SELECT
				value AS title,
				COUNT(recordId) AS instances
			FROM catalogue_processed
			WHERE
				    field = 'location'
				AND value NOT REGEXP \"" . $this->locationCodesRegexpSql . "\"
				AND value NOT REGEXP '^(IGS|International Glaciological Society|Not in SPRI)'
			GROUP BY value
			ORDER BY title
		";
		
		# Obtain the listing HTML
		$html = $this->reportListing (NULL, 'locations', 'location', false, $query);
		
		# Return the HTML
		return $html;
	}
	
	
	# *doc records with one *location, which is Periodical - distinct *ts values
	public function report_doclocationperiodicaltsvalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for report of *doc records with one *location, which is Periodical - distinct *ts values
	public function report_doclocationperiodicaltsvalues_view ()
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
	public function report_volumenumbers ()
	{
		// No action needed - the data is created in the MARC creation stage
		return true;
	}
	
	
	# View for report_volume volume number conversions
	public function report_volumenumbers_view ()
	{
		# Start the HTML
		$html = '';
		
		# Regenerate on demand during testing
		// $this->muscatConversion->import->createVolumeNumbersTable ();
		
		# Determine totals
		$table = 'volumenumbers';
		$totalRecords = $this->databaseConnection->getTotal ($this->settings['database'], $table);
		$filterConstraint = "WHERE a REGEXP '[0-9]'";
		$totalFailures = $this->databaseConnection->getTotal ($this->settings['database'], $table, $filterConstraint);
		
		# Determine whether to filter to reversibility failures only
		$totalRecords = number_format ($totalRecords);
		$enableFilter = (isSet ($_GET['filter']) && $_GET['filter'] == '1');
		if ($enableFilter) {
			$html .= "\n<p><a href=\"{$this->baseUrl}/reports/volumenumbers/\">Show all ($totalRecords)</a> | <strong>Filtering to cases of \$a containing numbers only (" . number_format ($totalFailures) . ")</strong></p>";
		} else {
			$html .= "\n<p><strong>Showing all ($totalRecords)</strong> | <a href=\"{$this->baseUrl}/reports/volumenumbers/?filter=1\">Filtering to cases of \$a containing numbers only (" . number_format ($totalFailures) . ")</a></p>";
		}
		
		# Define a manual query
		$query = "
			SELECT
				recordId AS id,
				line,
				ts,
				a,
				v,
				result,
				matchedRegexp
			FROM {$this->settings['database']}.{$table}
			" . ($enableFilter ? $filterConstraint : '') . "
			ORDER BY a, recordId
		;";
		
		# Obtain the headings
		$tableHeadingSubstitutions = $this->databaseConnection->getHeadings ($this->settings['database'], $table);
		
		# Obtain the listing HTML and highlight the subfields
		$reportListing = $this->reportListing (NULL, 'volume strings', false, 'id', $query, $tableHeadingSubstitutions);
		$reportListing = $this->muscatConversion->highlightSubfields ($reportListing);
		
		# Add the report
		$html .= $reportListing;
		
		# Return the HTML
		return $html;
	}
	
	
	# Records containing a note regarding translation - distinct values
	public function report_translationnotevalues ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for report of records containing a note regarding translation - distinct values; for use in diagnosing best regexp for languages041 macro
	public function report_translationnotevalues_view ()
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
	public function report_mergestatus ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for records with a merge status
	public function report_mergestatus_view ()
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
		$html = $this->muscatConversion->recordListing (false, $query, array (), '/reports/mergestatus/', false, false, $view = 'callback(mergestatusRenderer)');
		
		# Return the HTML
		return $html;
	}
	
	
	# Callback to provide a renderer for report_mergestatus
	public function mergestatusRenderer ($data)
	{
		# Link each record
		foreach ($data as $id => $record) {
			$data[$id]['id'] = "<a href=\"{$this->baseUrl}/records/{$record['id']}/\">{$record['id']}</a>";
		}
		
		# Add labels
		$mergeTypes = $this->marcConversion->getMergeTypes ();
		foreach ($data as $id => $record) {
			$data[$id]['mergeType'] .= ' &nbsp; <span class="comment">(' . (isSet ($mergeTypes[$record['mergeType']]) ? "{$this->mergeTypes[$record['mergeType']]}" : '?') . ')</span>';
		}
		
		# Render as HTML
		$tableHeadingSubstitutions = array (
			'mergeType'			=> 'Merge type',
			'mergeVoyagerId'	=> 'Voyager ID(s)',
		);
		$html  = "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';
		$html .= application::htmlTable ($data, $tableHeadingSubstitutions, 'lines compressed sortable" id="sortable', $keyAsFirstColumn = false, false, $allowHtml = true, false, false, false, array (), $compress = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Automated tests
	public function report_tests ()
	{
		// No action needed - the view is created dynamically
		return true;
	}
	
	
	# View for automated tests
	public function report_tests_view ()
	{
		# Start the HTML
		$html = '';
		
		# Add a button to regenerate the MARC data
		$regenerateParameter = 'regenerate';
		$html .= "\n" . '<form id="regenerate" method="post"><input type="hidden" name="' . $regenerateParameter . '" value="1" /><input type="submit" value="Regenerate? (Slow)" /></form>';
		$regenerateMarcData = (isSet ($_POST[$regenerateParameter]));
		
		# Regenerate the test data, regenerating the underlying MARC records if required
		if (!$this->muscatConversion->import->runTests ($errorHtml, $regenerateMarcData, $importMode = false)) {
			$html = "\n<p class=\"warning\">The tests are not correctly defined, with the test harness reporting an error: <tt>{$errorHtml}</tt></p>";
			return $html;
		}
		
		# Show test warnings if any; actual errors will have caused runTests to return false, so execution will not have reached this far
		if ($errorHtml) {
			$html .= $errorHtml;
		}
		
		# Add a filter form
		$fields = array (
			'result' => array (
				'title'		=> 'Pass/fail',
				'values'	=> array (1 => 'Pass', 0 => 'Fail'),
			),
			'marcField' => array (
				'title'		=> 'MARC field',
				'size'		=> 10,
				'maxlength'	=> 3,
			),
			'description' => array (
				'title'		=> 'Description',
				'size'		=> 25,
				'like'		=> true,
			),
		);
		$conditions = $this->muscatConversion->filteringControls ($fields, $this->baseUrl . '/tests/', $html);
		
		# Obtain the data
		$data = $this->databaseConnection->select ($this->settings['database'], 'tests', $conditions, array (), true, false, false, true, array ('description'));
		
		# End if no results
		if (!$data) {
			$html .= "\n<br />\n<p><em>There are no results.</em></p>";
			return $html;
		}
		
		# Count passing tests
		$totalPassed = 0;
		
		# Format fields
		foreach ($data as $id => $test) {
			
			# Add to pass counter if passed
			if ($test['result']) {
				$totalPassed += 1;
			}
			
			# Add hash before ID to enable quicker page-searchability
			$data[$id]['id'] = '<span class="comment">#</span>' . $data[$id]['id'];
			
			# Pass/fail icon
			$data[$id]['result'] = ($test['result'] ? '<img src="/images/icons/tick.png" title="Passed" alt="Tick" class="icon" />' : '<img src="/images/icons/cross.png" title="Failed" alt="Cross" class="icon" />');
			
			# Link record
			$data[$id]['recordId'] = "<a href=\"{$this->baseUrl}/records/{$test['recordId']}/\">{$test['recordId']}</a>";
			
			# Description
			$data[$id]['description'] = htmlspecialchars ($test['description']);
			$data[$id]['description'] = preg_replace ('|(/reports/[^/]+/)|', "<a href=\"{$this->baseUrl}\\1\">\\1</a>", $data[$id]['description']);
			
			# MARC field
			$data[$id]['marcField'] = ($data[$id]['negativeTest'] ? '!' : '') . ($data[$id]['indicatorTest'] ? 'i' : '') . $data[$id]['marcField'];
			
			# Expected
			$data[$id]['expected'] = '<tt>' . str_replace ('  ', ' &nbsp;', htmlspecialchars ($test['expected'])) . '</tt>';
			
			# Found lines
			if ($test['found']) {
				$found = htmlspecialchars ($test['found']);
				if ($test['result'] || $data[$id]['negativeTest']) {
					$matched = $test['expected'];
					if ($isRegexpTest = preg_match ('|^/|', $test['expected'], $matches)) {
						
						# Determine the extract from the full line to use for highlighting purposes
						if ($test['indicatorTest']) {
							$extract = substr ($found, 4, 2);		// Indicator test: The two indicator characters, starting from position 5 (i.e. '4', zero-indexed)
						} else if (preg_match ('/^(LDR|0)/', $found)) {
							$extract = substr ($found, 4);			// LDR/0xx fields: Strip off the fieldnumber and its space (4 characters)
						} else {
							$extract = substr ($found, 4 + 3);		// Standard fields: Strip off the fieldnumber and its space (4 characters) and the indicators (3 characters)
						}
						
						# Do the match
						if (preg_match ($test['expected'], $extract, $matches)) {
							$matched = $matches[0];
						}
					}
					$found = str_replace (htmlspecialchars ($matched), '<span class="found' . ($data[$id]['negativeTest'] ? ' negative' : '') . '">' . str_replace ('  ', ' &nbsp;', $matched) . '</span>', $found);
				}
				$data[$id]['found'] = '<tt>' . nl2br (application::str_truncate ($found, 700, "{$this->baseUrl}/records/{$test['recordId']}/")) . '</tt>';
			} else {
				$data[$id]['found'] = '<span class="comment">[Record or field not present.]</span>';
			}
			
			# Remove internal fields
			unset ($data[$id]['negativeTest']);
			unset ($data[$id]['indicatorTest']);
		}
		
		# Show pass (and fail) rate
		$totalTests = count ($data);
		$percentagePassed = round (($totalPassed / $totalTests) * 100, 1);
		$totalFailed = $totalTests - $totalPassed;
		$percentageFailed = round (($totalFailed / $totalTests) * 100, 1);
		$html .= "\n<div class=\"graybox\">";
		$html .= "\n<p class=\"success\">Tests passing: {$totalPassed} / {$totalTests} ({$percentagePassed}%).</p>";
		$html .= "\n<p class=\"warning\">Tests failing: {$totalFailed} / {$totalTests} ({$percentageFailed}%).</p>";
		$html .= "\n</div>";
		
		# Render the HTML
		$headings = $this->databaseConnection->getHeadings ($this->settings['database'], 'tests');
		$html .= application::htmlTable ($data, $headings, $class = 'tests graybox', $keyAsFirstColumn = false, false, $allowHtml = true, false, $addCellClasses = true);
		
		# Return the HTML
		return $html;
	}
}

?>
