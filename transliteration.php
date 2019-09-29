<?php

# Class to handle raw transliteration; NB wrapper code (e.g. batching) should not be added here
class transliteration
{
	# Define supported languages
	private $supportedReverseTransliterationLanguages = array (
		'Russian' => 'BGN PCGN 1947',	// Filename becomes bgn_pcgn_1947.xml
	);
	
	# A numbered token pattern for substring protection consisting of a safe string not likely to be present in the data and which will not be affected by any transliteration operation
	private $protectedSubstringsPattern = '<||%i||>';		// %i represents an index that will be generated, e.g. '<||367||>', which acts as a token representing the value of $replacements[367]
	private $protectedSubstringsRegexp = '<\|\|[0-9]+\|\|>';	// Equivalent, as regexp
	
	# Other internal properties
	private $databaseConnection;
	private $settings;
	private $applicationRoot;
	private $cpanDir;
	private $transliterableFullStringsInBrackets;
	
	
	# Constructor
	public function __construct ($muscatConversion)
	{
		# Create property handles
		$this->databaseConnection = $muscatConversion->databaseConnection;
		$this->settings = $muscatConversion->settings;
		$this->applicationRoot = $muscatConversion->applicationRoot;
		
		# Ensure the transliteration module is present
		$this->cpanDir = $this->applicationRoot . '/libraries/transliteration/cpan';
		if (!is_dir ($this->cpanDir)) {
			$html  = "\n<div class=\"graybox\">";
			$html .= "\n<p class=\"warning\">The transliteration module was not found. The Webmaster needs to ensure that {$this->cpanDir} is present.</p>";
			$html .= "\n</div>";
			echo $html;
			return true;
		}
		
		# Load transliterable full strings in brackets
		$this->transliterableFullStringsInBrackets = $this->loadTransliterableFullStringsInBrackets ();
		
		# Load whitelisted italicised Russian
		$this->transliterationItalicisedRussian = $this->loadTransliterationItalicisedRussian ();
	}
	
	
	
	# Getter for protectedSubstringsRegexp
	public function getProtectedSubstringsRegexp ()
	{
		return $this->protectedSubstringsRegexp;
	}
	
	
	# Getter for supportedReverseTransliterationLanguages
	public function getSupportedReverseTransliterationLanguages ()
	{
		return $this->supportedReverseTransliterationLanguages;
	}
	
	
	# Getter for transliterableFullStringsInBrackets
	public function getTransliterableFullStringsInBrackets ()
	{
		return $this->transliterableFullStringsInBrackets;
	}
	
	
	/* 
	 * Entry point functions
	 */
	
	
	
	# Function to reverse-transliterate a string from BGN/PCGN latin to Cyrillic; basic test of transliteration in /records/6653/ (test #107)
	# This is batch-safe following introduction of word-boundary protection algorithm in b5265809a8dca2a1a161be2fcc26c13c926a0cda
	#!# The same issue about previous crosstalk in unsafe batching presumably applies to line-by-line conversions, i.e. C (etc.) will get translated later in the same line; need to check on this
	/*
		Files are at
		/root/.cpan/build/Lingua-Translit-0.22-th0SPW/xml/
		
		Lingua Translit documentation:
		https://www.netzum-sorglos.de/software/lingua-translit/developer-documentation.html or https://github.com/gitpan/Lingua-Translit/blob/master/developer-manual__eng.pdf
		http://search.cpan.org/~alinke/Lingua-Translit/lib/Lingua/Translit.pm#ADDING_NEW_TRANSLITERATIONS
		
		XML transliteration file:
		/transliteration/bgn_pcgn_1947.xml
		
		Instructions for root install:
		Make changes to the XML file then run, as root:
		cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/xml/ && make all-tables && cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/ && make clean && perl Makefile.PL && make && make install
		
		# Example use:
		echo "hello" | translit -r -t "BGN PCGN 1947"
	*/
	public function transliterateBgnLatinToCyrillicBatch ($data /* triads of id,title_latin,lpt */, $language, &$cyrillicPreSubstitutions = array (), &$protectedPartsPreSubstitutions = array ())
	{
		# Ensure language is supported
		if (!isSet ($this->supportedReverseTransliterationLanguages[$language])) {return $stringLatin;}
		
		# Protect string portions (e.g. English language, HTML portions, parallel title portions, [Titles fully in square brackets like this]) prior to transliteration, e.g. /records/139647/ (test #823)
		$latinStrings = array ();
		$protectedParts = array ();
		$errors = array ();
		$nonTransliterable = array ();	// NB Not actually used yet
		foreach ($data as $id => $entry) {
			$latinStrings[$id] = $this->protectSubstrings ($entry['title_latin'], $entry['lpt'], $protectedParts[$id], $error /* passed back by reference */, $nonTransliterable[$id] /* passed back by reference */);
			if ($error) {
				$errors[$id] = $error;
			}
		}
		
		# End if error
		#!# Currently no error handling by client code
		//if ($errors) {return false;}
		
		/* Note:
		 * Ideally we would use:
		 *   $t = Transliterator::create("Russian-Latin/BGN", Transliterator::REVERSE);
		 *   $reverseTransliteration = $t->transliterate ($stringLatin);
		 * which uses Unicode CLDR
		 * See: http://www.larryullman.com/2012/02/01/transliteration-in-php-5-4/
		 * Unfortunately, http://cldr.unicode.org/index/cldr-spec/transliteration-guidelines states:
		 * "Unicode CLDR provides other transliterations based on the U.S. Board on Geographic Names (BGN) transliterations. These are currently unidirectional � to Latin only. The goal is to make them bidirectional in future versions of CLDR."
		 * and the current implementation of Russiah-Latin/BGN only has 'direction="forward"':
		 * http://unicode.org/cldr/trac/browser/trunk/common/transforms/Russian-Latin-BGN.xml
		 * Ticket raised at: https://unicode-org.atlassian.net/browse/CLDR-9086
		 */
		
		# Compile the strings to a single text string block
		$separator = "\n\n";
		$latinStringBlock = implode ($separator, $latinStrings);
		
		# Perform transliteration of the block
		# Note that this uses a local copy of Lingua::Translit (not any copy by root), which has the "BGN PCGN 1947" XML file in /tables/reverseTransliteration.xml provided by compileReverseTransliterator ()
		# If testing this command from the command-line, run using the webserver's user to ensure the correct environment
		# www-data$ echo "hello" | PERL5LIB=/path/to/muscat-conversion/libraries/transliteration/cpan/Lingua-Translit-0.22/lib/perl5/ /path/to/muscat-conversion/libraries/transliteration/cpan/bin/translit -t "BGN PCGN 1947"
		$perl5libLocation = $this->cpanDir . '/Lingua-Translit-0.22/lib/perl5/';	// See: https://perlmaven.com/how-to-change-inc-to-find-perl-modules-in-non-standard-locations
		$command = "PERL5LIB={$perl5libLocation} {$this->cpanDir}/bin/translit -trans '{$this->supportedReverseTransliterationLanguages[$language]}'";	//  --reverse
		$cyrillicBlock = application::createProcess ($command, $latinStringBlock);
		
		# Extract the strings back to an array, restoring the index
		$cyrillicStringsZeroIndexed = explode ($separator, $cyrillicBlock);
		$i = 0;
		$cyrillicStrings = array ();
		foreach ($data as $id => $entry) {
			$cyrillicStrings[$id] = $cyrillicStringsZeroIndexed[$i];
			$i++;
		}
		
		# Cache the pre-substitution cyrillic and protected parts, so that these can be batch-spellchecked; these are returned back by reference
		$cyrillicPreSubstitutions = $cyrillicStrings;
		$protectedPartsPreSubstitutions = $protectedParts;
		
		# Reinstate protected substrings, e.g. /records/139647/ (test #823)
		foreach ($cyrillicStrings as $id => $cyrillicString) {
			$cyrillicStrings[$id] = $this->reinstateProtectedSubstrings ($cyrillicString, $protectedParts[$id]);
		}
		
		# Return the transliterations
		return $cyrillicStrings;
	}
	
	
	# Function to transliterate from Library of Congress (ALA LC) to Cyrillic; this is only run in a non-batched context; see: https://www.loc.gov/catdir/cpso/romanization/russian.pdf
	# Useful tool at: https://www.translitteration.com/transliteration/en/russian/ala-lc/
	public function transliterateLocLatinToCyrillic ($stringLatin, $lpt, &$error = '', &$nonTransliterable = false)
	{
		# Protect string portions (e.g. English language, HTML portions, parallel title portions, [Titles fully in square brackets like this]) prior to transliteration, e.g. /records/139647/ (test #823)
		$stringLatin = $this->protectSubstrings ($stringLatin, $lpt, $protectedParts, $error /* passed back by reference */, $nonTransliterable /* passed back by reference */);
		if ($error) {return false;}
		
		# Transliterate, first loading if necessary the Library of Congress transliterations definition, copied from https://github.com/umpirsky/Transliterator/blob/master/src/Transliterator/data/ru/ALA_LC.php
		if (!isSet ($this->locTransliterationDefinition)) {
			$this->locTransliterationDefinition = require_once ('tables/ALA_LC.php');
		}
		$cyrillic = str_replace ($this->locTransliterationDefinition['lat'], $this->locTransliterationDefinition['cyr'], $stringLatin);
		
		# Reinstate protected substrings
		$cyrillic = $this->reinstateProtectedSubstrings ($cyrillic, $protectedParts);
		
		# Return the transliteration; e.g. /records/6653/ (test #107)
		return $cyrillic;
	}
	
	
	# Function to transliterate from Cyrillic to BGN/PCGN latin; this is used for reversibility checking - see /reports/transliteratefailure/ and /reports/transliterations/?filter=1
	# See: https://www.gov.uk/government/uploads/system/uploads/attachment_data/file/501620/ROMANIZATION_SYSTEM_FOR_RUSSIAN.pdf and earlier edition http://web.archive.org/web/20151005154715/https://www.gov.uk/government/uploads/system/uploads/attachment_data/file/320274/Russian_Romanisation.pdf
	public function transliterateCyrillicToBgnLatin ($cyrillic)
	{
		# Use the built-in transliterator
		$forwardBgnTransliterations = transliterator_transliterate ('Russian-Latin/BGN', $cyrillic);
		
		//# Experimental change to use the custom-written BGN PCGN 1947 transliteration but in reverse; doesn't work due to ambiguity; see: https://www.netzum-sorglos.de/software/lingua-translit/developer-documentation.html
		//$command = "{$this->cpanDir}/bin/translit -trans 'BGN PCGN 1947' --reverse";
		//$forwardBgnTransliterations = application::createProcess ($command, $cyrillic);
		
		# Convert soft-sign/hard-sign and use of middle-dot to their simpler representations in Muscat
		$muscatRepresentations = array (
			chr(0xCA).chr(0xB9) => "'",		// Soft sign -> Muscat quote
			chr(0xCA).chr(0xBA) => "''",	// Hard sign -> Muscat double quote
			'TGF·0,5Pr₄NF·16H₂O' => 'TGF·0,5Pr₄NF·16H₂O',	// Special case overriding middle dot handling (in next line) for /records/100714/ *t (shard 100714:19), purely for /reports/transliterations/?filter=1 checking - does not affect transliteration, which is handled by @@ (test #821)
			chr(0xC2).chr(0xB7) => '',		// Remove middle dot, which Muscat does not use; see https://unicode.org/cldr/trac/changeset/12203 which is used by PHP7, and note about optional status of middle dot ("The use of this digraph is optional") in https://en.wikipedia.org/wiki/BGN/PCGN_romanization_of_Russian
		);
		$forwardBgnTransliterations = strtr ($forwardBgnTransliterations, $muscatRepresentations);
		
		# Return the data
		return $forwardBgnTransliterations;
	}
	
	
	# Function to transliterate from Cyrillic to Library of Congress (ALA LC), e.g. /records/1043/ (test #991); see: https://www.loc.gov/catdir/cpso/romanization/russian.pdf
	public function transliterateCyrillicToLocLatin ($cyrillic)
	{
		# Load the Library of Congress transliterations definition, copied from https://github.com/umpirsky/Transliterator/blob/master/src/Transliterator/data/ru/ALA_LC.php
		if (!isSet ($this->locTransliterationDefinition)) {
			$this->locTransliterationDefinition = require_once ('tables/ALA_LC.php');
		}
		
		# Transliterate and return, e.g. /records/1043/ (test #991)
		return str_replace ($this->locTransliterationDefinition['cyr'], $this->locTransliterationDefinition['lat'], $cyrillic);
	}
	
	
	# Function to compile the reverse transliteration file
	public function compileReverseTransliterator ($definition, $language, &$errorHtml = '')
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
		#!#C PL_FILES may be needed to get it to read the local Tables.pm, but a workaround has been put in in the installer for now
		$command = "cd {$translitDir}/xml/ && make all-tables && cd {$translitDir}/ && make clean && perl Makefile.PL INSTALL_BASE={$translitDir} && make && make install";
		exec ($command, $output, $unixReturnValue);
		if ($unixReturnValue != 0) {
			$errorHtml = "Error (return status: <em>{$unixReturnValue}</em>) recompiling the transliterations: <tt>" . application::htmlUl ($output) . "</tt>";
			return false;
		}
		
		# Signal success
		return true;
	}
	
	
	
	/* 
	 * Helper functions
	 */
	
	
	# Function to protect string portions (e.g. English language, HTML portions, parallel title portions, [Titles fully in square brackets like this]) prior to transliteration; can be undone with a simple strtr(), e.g. /records/139647/ (test #823)
	private function protectSubstrings ($string, $lpt, &$protectedParts, &$error = '', &$nonTransliterable = false)
	{
		# Initialise a list of protected parts, which will be passed back by reference
		$protectedParts = array ();
		
		# Start an array of replacements
		$replacements = array ();
		
		# Handle parallel titles, e.g. "Title in Russian = Equivalent in English = Equivalent in French"; see: /fields/lpt/values/ ; e.g. /records/65712/ (test #441)
		if ($lpt) {
			if (!$nonTransliterableParts = $this->nonTransliterablePartsInParallelTitle ($string, $lpt, $error /* passed back by reference */)) {
				# This is a check for data consistency; as of Aug/2019 it is not triggered
				return false;	// $error will now be written to
			}
			$replacements = array_merge ($replacements, array_values ($nonTransliterableParts));
		}
		
		# Protect parts in italics, which are Latin names that a publisher would not translate, e.g. /records/1664/ (tests #993 and #994)
		$italicTagsAlso = false;
		preg_match_all ('|(<em>.+</em>)|uU', $string, $italicisedNameMatches);		// Uses /U ungreedy, to avoid "a <em>b</em> c <em>d</em> e" becoming "a  e", e.g. /records/17256/ (tests #46 and #992)
		if (array_intersect ($italicisedNameMatches[1], $this->transliterationItalicisedRussian)) {		// Except for specific phrases, in which case filter out, e.g. <em>Kueste</em> should have 'Kueste' transliterated and the <em> </em> protected, e.g. /records/12328/ (test #1118), /records/76594/ (test #1119), /records/24085/ (test #1120); negative example (unaffected) in /records/1661/ (test #1121)
			$italicisedNameMatches[1] = array_diff ($italicisedNameMatches[1], $this->transliterationItalicisedRussian);
			$italicTagsAlso = true;
		}
		$replacements = array_merge ($replacements, $italicisedNameMatches[1]);
		
		# Protect HTML tags, protecting the tag string itself, not its contents; e.g. /records/114278/ (test #973)
		$tags = array ('<sub>', '</sub>', '<sup>', '</sup>', );
		if ($italicTagsAlso) {
			$tags = array_merge ($tags, array ('<em>', '</em>'));
		}
		$replacements = array_merge ($replacements, $tags);
		
		# Protect known strings to protect from transliteration (Latin abbreviations, Order names, Roman numeral pattern regexps), e.g. /records/72688/ (test #995), /records/195773/ (test #837)
		$replacements = array_merge ($replacements, $this->transliterationProtectedStrings ());
		
		# Protect Roman numerals, dynamically based on the specific string
		$replacements = array_merge ($replacements, $this->protectRomanNumeralsIV ($string));
		
		# Create dynamic replacements; e.g. /records/131979/ (test #996)
		# These must have a single capture bracket-set () from which the extraction is taken (rather than the full string), e.g. match "/(XVII)-pervaya/" will match source string "XVII-pervaya" but extract "XVII" as the result for protection; e.g. /records/206607/ (test #6)
		foreach ($replacements as $index => $matchString) {
			if (preg_match ('|^/.+/i?$|', $matchString)) {	// e.g. a pattern /(X-XI)/i against string 'Foo X-XI Bar' would add 'X-XI' to the replacements list
				unset ($replacements[$index]);	// Remove the pattern itself from the replacement list, as it should not be treated as a literal
				
				# Create a test string based on the string (but do not modify the test itself); this doubles-up any spaces, so that preg_match_all can match adjacent matches (e.g. see /records/120782/ ) due to "After the first match is found, the subsequent searches are continued on from end of the last match."
				$testString = preg_replace ("/\s+/", '  ', $string);
				
				# Perform the match
				if (preg_match_all ($matchString, $testString, $matches, PREG_PATTERN_ORDER)) {
					foreach ($matches[1] as $match) {
						$replacements[] = trim ($match);	// Trim so that overlapping strings e.g. "XVII- XIX" which has matches "XVII- " and " XIX" in /records/120782/ (test #997) are both picked up
					}
				}
			}
		}
		
		# Do not transliterate [Titles fully in square brackets like this]; e.g. /records/31750/ (test #822)
		# This should take effect after parallel titles have been split off - the Russian part is the only part in the scope of transliteration, with other languages to be ignored by 880; however, [A] = B should logically never exist, and indeed this does not appear in the data
		# Strings in square brackets that are amongst other text are not handled automatically, and so need to be added to the transliteration protected strings list, with the brackets included, e.g. /records/139647/ (test #823); these are handled manually, as they cannot be assumed to be in English, e.g. /records/14186/ (test #824)
		#!# Bug that the other $parallelTitles will be lost if the string is returned
		if ($this->titleFullyInBrackets ($string)) {
			// $error should not be given a string, as this scenario is not an error, e.g. /records/75010/ , /records/167609/ , /records/178982/
			$nonTransliterable = true;	// Flag that this is not transliterable, passed back by reference
			$replacements = array ($string);	// Overwrite any other replacements up to this point, as they can be ignored as irrelevant
		}
		
		# At this point, all strings are known to be fixed strings, not regexps
		
		# For performance reasons, reduce complexity of the preg_replace below by doing a basic substring match first, to trim the list of c. 1,000 strings down to those present
		foreach ($replacements as $index => $replacement) {
			$replacement = preg_replace ('/^@@/', '', $replacement);	// Mid-word replacements - see below
			if (!substr_count ($string, $replacement)) {
				unset ($replacements[$index]);
			}
		}
		
		# If no replacements, return the string unmodified, e.g. /records/1053/ (test #998)
		if (!$replacements) {
			return $string;
		}
		
		# Create a token for each protected part; this is passed back by reference, for easy restoration, e.g. /records/72688/ (test #1001)
		$i = 0;
		foreach ($replacements as $replacement) {
			$key = str_replace ('%i', $i++, $this->protectedSubstringsPattern);
			$protectedParts[$key] = $replacement;	// e.g. '<||12||>' => 'Fungi'
		}
		
		# If the whole string matches a protected string, then treat as non-transliterable, e.g. /records/214774/ (test #840)
		# The comparison is done with punctuation trimmed, e.g. 490 field in /records/16319/ (test #853)
		$punctuationTrimming = ' .,:;';
		foreach ($replacements as $replacement) {
			if (trim ($replacement, $punctuationTrimming) == trim ($string, $punctuationTrimming)) {
				$nonTransliterable = true;	// E.g. /records/214774/ (test #840)
			}
		}
		
		# Convert each pattern to be word-boundary -based, e.g. /records/1058/ (test #999), except for specific cases like italics and tags, e.g. /records/1664/ (tests #993 and #994); the word boundary has to be defined manually rather than using \b because some strings start/end with a bracket, e.g. /records/180415/ (test #1000)
		$replacements = array ();
		$delimiter = '/';
		foreach ($protectedParts as $replacementToken => $fixedString) {
			
			# Enable word boundaries by default, e.g. /records/1058/ (test #999)
			$useWordBoundaries = true;
			
			# Determine whether a protected part is italics, as this does not have a word boundary requirement, as the italics are an explicit part of the string, e.g. /records/1664/ (tests #993 and #994)
			if (preg_match ('|^<em>.+</em>$|', $fixedString)) {
				$useWordBoundaries = false;
			}
			
			# Handle mid-word strings, which do not have a word boundary requirement, stripping out the @@ token, e.g. /records/100714/ (test #821)
			if (preg_match ('/^@@/', $fixedString)) {
				$useWordBoundaries = false;
				$fixedString = preg_replace ('/^@@/', '', $fixedString);
				$protectedParts[$replacementToken] = $fixedString;
			}
			
			# Tags themselves do not have a word boundary requirement, e.g. /records/114278/ (test #973)
			if (in_array ($fixedString, $tags)) {
				$useWordBoundaries = false;
			}
			
			# Define the replacement now as a regexp, with the word boundary where enabled
			#!# Hyphen in post- word boundary needs review
			$search = $delimiter . ($useWordBoundaries ? '(^|\s|\(|")' : '') . preg_quote ($fixedString, $delimiter) . ($useWordBoundaries ? '($|\s|\)|\.|-|,|:|")' : '') . $delimiter;		// Defined list rather than \b so that brackets etc. work; e.g. /records/180415/ (test #1000)
			$replacements[$search] = '\1' . $replacementToken . '\2';	// \1 and \2 are the word boundary strings (e.g. a space) which need to be restored
		}
		
		# Perform protection, by replacing with the numbered token, e.g. /records/139647/ (test #823)
		$string = preg_replace (array_keys ($replacements), array_values ($replacements), $string);
		
		# Return the protected string; NB $protectedParts are returned by reference
		return $string;
	}
	
	
	# Function to handle extraction of parallel titles, e.g. /records/65712/ (test #441)
	private function nonTransliterablePartsInParallelTitle ($russianAsTransliteratedLatin, $lpt, &$error = '')
	{
		# Tokenise the definition
		$parallelTitleSeparator = ' = ';
		$parallelTitleLanguages = explode ($parallelTitleSeparator, $lpt);
		$parallelTitleComponents = explode ($parallelTitleSeparator, $russianAsTransliteratedLatin);
		
		# Ensure the counts match; this is looking for the same problem as /reports/paralleltitlemismatch/
		if (count ($parallelTitleLanguages) != count ($parallelTitleComponents)) {
			$error = 'Transliteration requested with parallel titles list whose token count does not match the title';
			return false;
		}
		
		# Convert to key/value pairs; the list at /fields/lpt/values/ confirms there are no duplications (e.g. Russian = English = Russian)
		$parallelTitles = array_combine ($parallelTitleLanguages, $parallelTitleComponents);
		
		# Set the supported language as the part to be transliterated
		#!#H Currently hard-coded support for Russian only
		if (!isSet ($parallelTitles['Russian'])) {
			$error = 'Transliteration requested with parallel titles list that does not include Russian';
			return false;
		}
		
		# Remove the portions that are not for transliteration, leaving only those to be protected, e.g. /records/65712/ (test #1002)
		unset ($parallelTitles['Russian']);
		
		# Return the list
		return $parallelTitles;
	}
	
	
	# Function to create a list of strings to protect from transliteration
	private function transliterationProtectedStrings ()
	{
		# Use cache if present
		if (isSet ($this->transliterationProtectedStrings)) {
			return $this->transliterationProtectedStrings;
		}
		
		# Start a list
		$protectedStrings = array ();
		
		# Protect species Order names (which will not be in italics); e.g. /records/1264/ (test #1003)
		$protectedStrings = array_merge ($protectedStrings, $this->getSpeciesOrderNames ());
		
		# Protect a defined list of species names, chemical formulae, latin abbreviations, and other strings; e.g. /records/72688/ (test #1004)
		$definedList = application::textareaToList ($this->applicationRoot . '/tables/' . 'transliterationProtectedStrings.txt', true, true, true);
		$protectedStrings = array_merge ($protectedStrings, $definedList);
		
		# Protect Roman numerals, by defining dynamic replacement patterns; note that these cannot have spaces due to the doubling-spaces algorithm in protectSubstrings
		# Note that standard latin characters rather than 'real' Unicode symbols are used, as per the recommendation in the Unicode standard - see: https://en.wikipedia.org/wiki/Numerals_in_Unicode#Roman_numerals_in_Unicode
		#!# There is still the potential for "Volume I." at the end of a sentence, but that I. cannot be disambiguated from I. as an initial
		# Roman numeral followed by hyphen then space is protected, e.g. /records/180099/ (test #7)
		$protectedStrings[] = '/' . '(?:^|\s|\()' . '([XLCDM]+[-IVXLCDM]*)' . '(?:$|\s|\)|,)' . '/';		// Word boundary used to avoid e.g. "Vladimir" being treated as non-translitered 'V' + transliterated 'ladimir', e.g. /records/85867/ (test #1006); standalone letter without "." after (e.g. as in "M.L." in author) not treated as Roman numeral, e.g. /records/4578/ (test #1009)
		$protectedStrings[] = '/' . '(?:^|\s|\()' . '([IVXLCDM]+[-IVXLCDM]+)' . '(?:$|\s|\)|,|\.)' . '/';	// Allow space if more than one; e.g. /records/144193/ which includes "Dactylopteriformes. - XXXVII."
		$protectedStrings[] = '/' . '(?:^|\s|\()' . '([IVXLCDM]+[-IVXLCDM]*)' . '(?:-)(?:nachale|nachalo|nachala|pervoy|pervoĭ|pervaya|pervai͡a|seredine|seredina|go)' . '(?:$|\s|\)|,)' . '/';
		$protectedStrings[] = '/' . '(?:^|\s|\()' . '([IVXLCDM]+[-IVXLCDM]+)' . '(?:-)(?:nachale|nachalo|nachala|pervoy|pervoĭ|pervaya|pervai͡a|seredine|seredina|go)' . '(?:$|\s|\)|,|\.)' . '/';	// E.g. LoC variants pervoĭ/pervai͡ /records/206607/ (test #6), /records/206529/ (test #1007); and others which are the same in BGN/PCGN vs LoC (e.g. seredine): /records/61945/ (test #1019)
		
		# Roman numeral special handling for I and V: Is a Roman numeral, EXCEPT treated as a letter when at start of phrase + space, or space before + dot
		/* Can't get this to work, so handled instead by protectRomanNumeralsIV below
		$protectedStrings[] = '/([=?!.] [IV] (*SKIP)(*FAIL)|(?: )[IV](?: ))/';	// See: https://regex101.com/r/9Xz0ce/3 and https://stackoverflow.com/questions/57431509/
		*/
		
		# Cache
		$this->transliterationProtectedStrings = $protectedStrings;
		
		# Return the list
		return $protectedStrings;
	}
	
	
	# Function to protect (standalone) I and V Roman numerals when in the middle of a sentence; this is a dynamic function that registers a protected string specific to the incoming source string
	# See also: https://regex101.com/r/9Xz0ce/3 and https://stackoverflow.com/questions/57431509/
	private function protectRomanNumeralsIV ($string)
	{
		# Protect (standalone) I and V Roman numerals when in the middle of a sentence
		# Capitals I and V are found to be words when at the start of the sentence, e.g. /records/5017/ (test #1011) and /records/84560/ (test #1015), but occur as Roman numerals when in the middle of a sentence, e.g. /records/102516/ (test #1014) and /records/28472/ (test #1016)
		if (preg_match ('/ ([IV]) /', $string, $matches)) {
			if (!preg_match ('/[=?!.] ([IV]) /', $string)) {
				return array ($matches[1]);
			}
		}
		
		# Return no result
		return array ();
	}
	
	
	# Function to determine a [Title fully in square brackets like this]; e.g. /records/31750/ (test #822)
	private function titleFullyInBrackets ($title)
	{
		# Omit special cases, e.g. *t example in /records/7826/ (test #1022) and *pu example in /records/29343/ (test #1023)
		if (in_array ($title, $this->transliterableFullStringsInBrackets)) {return false;}
		
		# Check for [...] ; the regexp should match the MySQL equivalent in createTransliterationsTable ()
		$literalBackslash = '\\';
		return (preg_match ('/' . "^{$literalBackslash}[([^{$literalBackslash}]]+){$literalBackslash}]$" . '/', $title));
	}
	
	
	# Function to obtain species Order names; e.g. /records/1264/ (test #1003)
	private function getSpeciesOrderNames ()
	{
		# Obtain the data from the UDC table
		$query = "SELECT * FROM udctranslations WHERE ks REGEXP '^(582|593|594|595|597|598|599)\\\\.'";
		$orders = $this->databaseConnection->getPairs ($query);
		$orders = array_values ($orders);
		
		# Return the list
		return $orders;
	}
	
	
	# Function to reinstate protected substrings, e.g. /records/139647/ (test #823)
	public function reinstateProtectedSubstrings ($cyrillic, $protectedParts)
	{
		return $cyrillic = strtr ($cyrillic, $protectedParts);
	}
	
	
	# Function to define a list of full strings [in square brackets] that should be transliterated, because they are simply not in the publication itself but otherwise known
	# E.g. *t example in /records/7826/ (test #1022) and *pu example in /records/29343/ (test #1023)
	public function loadTransliterableFullStringsInBrackets ()
	{
		# Identified by inspection of list `SELECT * FROM catalogue_processed WHERE value LIKE '[%' AND recordLanguage LIKE 'Russian' AND value NOT IN ('[n.d]',  '[n.p.]' , '[n.pub.]', '[Anon.]', '[Leningrad]', '[St. Petersburg]', '[Moscow]') AND field NOT IN('d', 'p','note', 'tc') LIMIT 500;`
		return application::textareaToList ($this->applicationRoot . '/tables/' . 'transliterableFullStringsInBrackets.txt', true, true);
	}
	
	
	# Function to define a list of whitelisted Russian phrases inside italics, e.g. /records/12328/ (test #1118), /records/76594/ (test #1119), /records/24085/ (test #1120); negative example (unaffected) in /records/1661/ (test #1121)
	# These are cases where, in a Russian record, a phrase in italics is actually in Russian, e.g. <em>Slava</em>, rather than a protected string like a <em>Ship name</em> that is in Latin
	# See "1909 Russian italics" Excel file
	public function loadTransliterationItalicisedRussian ()
	{
		# Obtain the list
		$list = application::textareaToList ($this->applicationRoot . '/tables/' . 'transliterationItalicisedRussian.txt', true, true);
		
		# Add italics to each
		foreach ($list as $index => $string) {
			$list[$index] = '<em>' . $string . '</em>';
		}
		
		# Return the list
		return $list;
	}
}

?>
