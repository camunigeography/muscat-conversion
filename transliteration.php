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
	
	
	
	# Constructor
	public function __construct ($muscatConversion)
	{
		# Create property handles
		$this->muscatConversion = $muscatConversion;
		$this->databaseConnection = $muscatConversion->databaseConnection;
		
		# Ensure the transliteration module is present
		$this->cpanDir = $this->muscatConversion->applicationRoot . '/libraries/transliteration/cpan';
		if (!is_dir ($this->cpanDir)) {
			$html  = "\n<div class=\"graybox\">";
			$html .= "\n<p class=\"warning\">The transliteration module was not found. The Webmaster needs to ensure that {$this->cpanDir} is present.</p>";
			$html .= "\n</div>";
			echo $html;
			return true;
		}
		
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
	
	
	
	/* 
	 * Entry point functions
	 */
	
	
	# Function to reverse-transliterate a string from BGN/PCGN latin to Cyrillic
	# This function is not batch-safe, and is designed to accept only a single string at a time
	/*
		Files are at
		/root/.cpan/build/Lingua-Translit-0.22-th0SPW/xml/
		
		Documentation at
		http://www.lingua-systems.com/translit/downloads/lingua-translit-developer-manual-eng.pdf
		
		XML transliteration file:
		/transliteration/bgn_pcgn_1947.xml
		
		Instructions for root install:
		Make changes to the XML file then run, as root:
		cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/xml/ && make all-tables && cd /root/.cpan/build/Lingua-Translit-0.22-th0SPW/ && make clean && perl Makefile.PL && make && make install
		
		Lingua Translit documentation:
		http://www.lingua-systems.com/translit/downloads/lingua-translit-developer-manual-eng.pdf
		http://search.cpan.org/~alinke/Lingua-Translit/lib/Lingua/Translit.pm#ADDING_NEW_TRANSLITERATIONS
		
		# Example use:
		echo "hello" | translit -r -t "BGN PCGN 1947"
	*/
	public function transliterateBgnLatinToCyrillic ($stringLatin, $lpt, $language, &$cyrillicPreSubstitution = false, &$protectedPartsPreSubstitution = false, &$nonTransliterable = false)
	{
		# Ensure language is supported
		if (!isSet ($this->supportedReverseTransliterationLanguages[$language])) {return $stringLatin;}
		
		# Protect string portions (e.g. English language, HTML portions, parallel title portions, [Titles fully in brackets like this]) prior to transliteration
		$stringLatin = $this->protectSubstrings ($stringLatin, $lpt, $protectedParts, $error /* passed back by reference */, $nonTransliterable /* passed back by reference */);
		if ($error) {return false;}
		
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
		 * Ticket raised at: http://unicode.org/cldr/trac/ticket/9086
		 */
		
		# Perform transliteration
		$command = "{$this->cpanDir}/bin/translit -trans '{$this->supportedReverseTransliterationLanguages[$language]}'";	//  --reverse
		$cyrillic = application::createProcess ($command, $stringLatin);
		
		# Cache the pre-substitution cyrillic and protected parts, so that these can be batch-spellchecked; these are returned back by reference
		$cyrillicPreSubstitution = $cyrillic;
		$protectedPartsPreSubstitution = $protectedParts;
		
		# Reinstate protected substrings
		$cyrillic = $this->reinstateProtectedSubstrings ($cyrillic, $protectedParts);
		
		# Return the transliteration
		return $cyrillic;
	}
	
	
	# Function to transliterate from Library of Congress (ALA LC) to Cyrillic; this is only run in a non-batched context; see: https://www.loc.gov/catdir/cpso/romanization/russian.pdf
	public function transliterateLocLatinToCyrillic ($stringLatin, $lpt, &$error = '', &$nonTransliterable = false)
	{
		# Protect string portions (e.g. English language, HTML portions, parallel title portions, [Titles fully in brackets like this]) prior to transliteration
		$stringLatin = $this->protectSubstrings ($stringLatin, $lpt, $protectedParts, $error /* passed back by reference */, $nonTransliterable /* passed back by reference */);
		if ($error) {return false;}
		
		# Transliterate, first loading if necessary the Library of Congress transliterations definition, copied from https://github.com/umpirsky/Transliterator/blob/master/src/Transliterator/data/ru/ALA_LC.php
		if (!isSet ($this->locTransliterationDefinition)) {
			$this->locTransliterationDefinition = require_once ('tables/ALA_LC.php');
		}
		$cyrillic = str_replace ($this->locTransliterationDefinition['lat'], $this->locTransliterationDefinition['cyr'], $stringLatin);
		
		# Reinstate protected substrings
		$cyrillic = $this->reinstateProtectedSubstrings ($cyrillic, $protectedParts);
		
		# Return the transliteration
		return $cyrillic;
	}
	
	
	# Function to transliterate from Cyrillic to BGN/PCGN latin
	# See: https://www.gov.uk/government/uploads/system/uploads/attachment_data/file/501620/ROMANIZATION_SYSTEM_FOR_RUSSIAN.pdf and earlier edition http://web.archive.org/web/20151005154715/https://www.gov.uk/government/uploads/system/uploads/attachment_data/file/320274/Russian_Romanisation.pdf
	public function transliterateCyrillicToBgnLatin ($cyrillic)
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
	
	
	# Function to transliterate from Cyrillic to Library of Congress (ALA LC); see: https://www.loc.gov/catdir/cpso/romanization/russian.pdf
	public function transliterateCyrillicToLocLatin ($cyrillic)
	{
		# Load the Library of Congress transliterations definition, copied from https://github.com/umpirsky/Transliterator/blob/master/src/Transliterator/data/ru/ALA_LC.php
		if (!isSet ($this->locTransliterationDefinition)) {
			$this->locTransliterationDefinition = require_once ('tables/ALA_LC.php');
		}
		
		# Transliterate and return
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
	
	
	
	/* 
	 * Helper functions
	 */
	
	
	# Function to protect string portions (e.g. English language, HTML portions, parallel title portions, [Titles fully in brackets like this]) prior to transliteration; can be undone with a simple strtr()
	private function protectSubstrings ($string, $lpt, &$protectedParts, &$error = '', &$nonTransliterable = false)
	{
		# Initialise a list of protected parts, which will be passed back by reference
		$protectedParts = array ();
		
		# Start an array of replacements
		$replacements = array ();
		
		# Handle parallel titles, e.g. "Title in Russian = Equivalent in English = Equivalent in French"; see: /fields/lpt/values/
		if ($lpt) {
			if (!$nonTransliterableParts = $this->nonTransliterablePartsInParallelTitle ($string, $lpt, $error /* passed back by reference */)) {
				return false;	// $error will now be written to
			}
			$replacements = array_merge ($replacements, array_values ($nonTransliterableParts));
		}
		
		# Protect parts in italics, which are Latin names that a publisher would not translate
		preg_match_all ('|(<em>.+</em>)|uU', $string, $italicisedNameMatches);		// Uses /U ungreedy, to avoid "a <em>b</em> c <em>d</em> e" becoming "a  e"
		$replacements = array_merge ($replacements, $italicisedNameMatches[1]);
		
		# Add in HTML tags for protection; NB in theory all <em> and </em> tags will have been swallowed already so in practice these are not necessary here
		$tags = array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>', );
		$replacements = array_merge ($replacements, $tags);
		
		# Protect known strings to protect from transliteration (Latin abbreviations, Order names, Roman numeral pattern regexps)
		$replacements = array_merge ($replacements, $this->transliterationProtectedStrings ());
		
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
		
		# Do not transliterate [Titles fully in brackets like this]; e.g. /records/31750/ ; this should take effect after parallel titles have been split off - the Russian part is the only part in the scope of transliteration, with other languages to be ignored by 880; however, [A] = B should logically never exist, and indeed this does not appear in the data
		#!# Bug that the other $parallelTitles will be lost if the string is returned
		if ($this->titleFullyInBrackets ($string)) {
			// $error should not be given a string, as this scenario is not an error, e.g. /records/75010/ , /records/167609/ , /records/178982/
			$nonTransliterable = true;	// Flag that this is not transliterable, passed back by reference
			$replacements = array ($string);	// Overwrite any other replacements up to this point, as they can be ignored as irrelevant
		}
		
		# At this point, all strings are known to be fixed strings, not regexps
		
		# For performance reasons, reduce complexity of the preg_replace below by doing a basic substring match first
		foreach ($replacements as $index => $replacement) {
			if (!substr_count ($string, $replacement)) {
				unset ($replacements[$index]);
			}
		}
		
		# If no replacements, return the string unmodified
		if (!$replacements) {
			return $string;
		}
		
		# Create a token for each protected part; this is passed back by reference, for easy restoration
		$i = 0;
		foreach ($replacements as $replacement) {
			$key = str_replace ('%i', $i++, $this->protectedSubstringsPattern);
			$protectedParts[$key] = $replacement;	// e.g. '<||12||>' => 'Fungi'
		}
		
		# Convert each pattern to be word-boundary -based; the word boundary has to be defined manually rather than using \b because some strings start/end with a bracket
		$replacements = array ();
		$delimiter = '/';
		foreach ($protectedParts as $replacementToken => $fixedString) {
			#!# Hyphen in post- word boundary needs review
			$search = $delimiter . '(^|\s|\()' . preg_quote ($fixedString, $delimiter) . '($|\s|\)|\.|-|,|:)' . $delimiter;
			$replacements[$search] = '\1' . $replacementToken . '\2';	// \1 and \2 are the word boundary strings (e.g. a space) which need to be restored
		}
		
		# Perform protection, by replacing with the numbered token
		$string = preg_replace (array_keys ($replacements), array_values ($replacements), $string);
		
		# Return the protected string
		return $string;
	}
	
	
	# Function to handle extraction of parallel titles
	private function nonTransliterablePartsInParallelTitle ($russianAsTransliteratedLatin, $lpt, &$error = '')
	{
		# Tokenise the definition
		$parallelTitleSeparator = ' = ';
		$parallelTitleLanguages = explode ($parallelTitleSeparator, $lpt);
		$parallelTitleComponents = explode ($parallelTitleSeparator, $russianAsTransliteratedLatin);
		
		# Ensure the counts match; this is looking for the same problem as the paralleltitlemismatch report
		if (count ($parallelTitleLanguages) != count ($parallelTitleComponents)) {
			$error = 'Transliteration requested with parallel titles list whose token count does not match the title';
			return false;
		}
		
		# Convert to key/value pairs; the list at /fields/lpt/values/ confirms there are no duplications (e.g. Russian = English = Russian)
		$parallelTitles = array_combine ($parallelTitleLanguages, $parallelTitleComponents);
		
		# Set the supported language as the part to be transliterated
		#!# Currently hard-coded support for Russian only
		if (!isSet ($parallelTitles['Russian'])) {
			$error = 'Transliteration requested with parallel titles list that does not include Russian';
			return false;
		}
		
		# Return the portions that are not for transliteration, so they can be protected
		unset ($parallelTitles['Russian']);
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
		$replacements = array ();
		
		# Protect species Order names (which will not be in italics)
		$replacements = array_merge ($replacements, array_values ($this->getSpeciesOrderNames ()));
		
		# Protect a defined list of species names, chemical formulae, latin abbreviations, and other strings
		$definedList = $this->muscatConversion->oneColumnTableToList ('transliterationProtectedStrings.txt', true);
		$replacements = array_merge ($replacements, $definedList);
		
		# Protect Roman numerals, by defining dynamic replacement patterns; note that standard latin characters rather than 'real' Unicode symbols are used, as per the recommendation in the Unicode standard - see: https://en.wikipedia.org/wiki/Numerals_in_Unicode#Roman_numerals_in_Unicode
		#!# There is still the potential for "Volume I." at the end of a sentence, but that I. cannot be disambiguated from I. as an initial
		$replacements[] = '/' . '(?:^|\s|\()' . '[IVXLCDM]+[-IVXLCDM]*' . '(?:$|\s|\)|,)' . '/';
		$replacements[] = '/' . '(?:^|\s|\()' . '[IVXLCDM]+[-IVXLCDM]+' . '(?:$|\s|\)|,|\.)' . '/';	// Allow space if more than one; e.g. /records/144193/ which includes "Dactylopteriformes. - XXXVII."
		
		# Roman numeral special handling for I and V: Is a Roman numeral, EXCEPT treated as a letter when at start of phrase + space, or space before + dot
		
		
		
		# Cache
		$this->transliterationProtectedStrings = $replacements;
		
		# Return the list
		return $replacements;
	}
	
	
	# Function to determine a [Title fully in brackets like this]; e.g. /records/31750/
	private function titleFullyInBrackets ($title)
	{
		# Check for [...] ; the regexp should match the MySQL equivalent in createTransliterationsTable ()
		$literalBackslash = '\\';
		return (preg_match ('/' . "^{$literalBackslash}[([^{$literalBackslash}]]+){$literalBackslash}]$" . '/', $title));
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
	
	
	# Function to reinstate protected substrings
	private function reinstateProtectedSubstrings ($cyrillic, $protectedParts)
	{
		return $cyrillic = strtr ($cyrillic, $protectedParts);
	}
	
	
}

?>