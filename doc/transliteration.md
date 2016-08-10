# Transliteration


This article documents the transliteration procedures within the conversion process.

## Aims

Transliteration is currently only applied to Russian records. Other languages have been deemed out-of-scope, though code has been written with a view to avoid hard-coding Russian.

The aim is that all such records:

1. Contain real Cyrillic characters (Unicode), which are not present in the original records, and;
2. Transliterated sections use the modern Library of Congress (LoC) transliteration, not the BGN/PCGN 1947 system which is no longer widely used.

These involve two distinct processes.

Code handling raw transliteration is in `/transliteration.php`.

## Phases

### Phase 0: Preparations in processed table

At a relatively early part of the overall conversion process, processed versions of the records are created by the `createProcessedTable` routine. This takes a copy of the raw Muscat data, which is sharded into components (where each shard is a line such as *t in Muscat) and performs a range of general character conversions on it such as diacritic conversion, symbol processing, etc.

Two of the parts of this of relevance, in later phases, to transliteration, are as follows:

1. A flag field `topLevel` is added to the processed records table, marks each shard as being within the top level part of the record (value: `1`) or within in an `*in` or `*j` (value: `0`).

2. The record of the language (`firstLanguage`) of the record is determined, e.g. `Russian`, basically taking the first `*lang` if present.


### Phase 1: Upgrade BGN/PCGN strings to LoC

The aim of this first phase is to get eliminate BGN/PCGN 1947 strings as early as possible in the overall conversion process, and upgrade them to their LoC equivalents.

Immediately after the processed record processing, the upgrade of BGN/PCGN to LoC is started.

This involves the following steps:

1. The routine `createTransliterationsTable` is entered. This involves the following stages:
  
 a. Creates a table, `transliterations` removing any existing one from a previous import run. Each entry contains:
  
    * `id` Shard ID, e.g. `1262:7`
	* `recordId` Record ID, e.g. `1262`
	* `field` Field, e.g. `t` (i.e. *t)
	* `lpt` Field, for Parallel title languages (i.e. *lpt); examples: `Russian = French`, `English = Russian`, matching `... = ...` in *t
	* `title_latin` Title (latin characters), unmodified from original data, e.g. `Za polyarnym krugom`
	* `title_latin_tt` *tt if present, e.g. `Beyond the Arctic Circle`
	* `title` Reverse-transliterated title in UTF-8 Cyrillic, e.g. `За полярным кругом`
	* `title_forward` Forward transliteration from generated Cyrillic (BGN/PCGN) as a test, e.g. `Za polyarnym krugom`
	* `forwardCheckFailed` Flag for whether forward check failed
	* `title_loc` Forward transliteration from generated Cyrillic (Library of Congress), e.g. `Za poli͡arnym krugom`
	
 b. A list of Muscat fields which may contain transliterated strings is created. Currently this is:

    * `*t` only
	* [This list is expected to be expanded]
	
 c. Records that have one of the fields, e.g. `*t` at top level (marked `topLevel = 1`) have each such shard copied from the processed table to the transliterations table, into the `title_latin` field.
	* Note that [Titles fully in brackets like this] are excluded from this process
	
 d. The parallel title (`*lpt`) property associated with the top-level `*t` is added. This is done by looking up the `*lpt` field in the processed table with a matching `recordId`, and adding it where the `title_latin` contains the lpt delimeter, ` = `.
	
 e. Similarly, `*tt` where present (at top level) is copied into `title_latin_tt`.
	
 f. Thus we now have a table containing shards with field `title_latin` containing the BGN/PCGN string that needs to be upgraded (and `title_latin_tt` for records having `*tt`).
	
2. The routine `transliterateTransliterationsTable` is entered. For each shard:

 a. **Transliterate BGN/PCGN to Cyrillic.** The `title_latin` is transliterated to Cyrillic and stored as `title` using `transliterateBgnLatinToCyrillic`, using the Lingua Translit program and using the XML definition defined in `/tables/reverseTransliteration.xml` which is written by the interface at `/transliterator.html`. This takes around 20 minutes. It is not currently batched.
  
  * This section has `protectSubstrings` applied. Further details are below.

 b. **Transliterate back from Cyrillic to BGN/PCGN as a reversibility check.** As a reversibility check, the new Cyrillic in `title` is forward-transliterated back and stored in `title_forward`, using `transliterateCyrillicToBgnLatin`. This takes around 15 seconds, and is batched (all shards processed at once).
 
 c. **Transliterate from the newly-created Cyrillic to LoC.** The Cyrillic in `title` is forward-transliterated into Library of Congress (LoC) and stored in `title_loc`, using `transliterateCyrillicToLocLatin`. This takes only a second, and is batched (all shards processed at once); it is likely to be efficient as there is a one-to-one character mapping.

 d. The three new values are added into the `transliterations` table for each shard.
 
 e. Thus we now have a table containing an associative lookup of all transliteration variants.

3. The `upgradeTransliterationsToLoc` routine is then entered. This does only one job:
 
 a. **Overwrite each transliterable shard with the new LoC value.**
 
 b. **Thus transliterated strings in the processed table now are LoC (for all supported fields).** BGN/PCGN transliterations have been wiped out (for each supported field). Even though this has come from Cyrillic, this is a fully-reversible process.

### Phase 2: Add Cyrillic 880 fields into the MARC records

The eventual MARC records need an 880 field containing the Cyrillic equivalent (known as the 'alternate graphic representation').

Conversion in the records of LoC to Cyrillic is done dynamically using `macro_transliterate`. It takes the new LoC reverse transliteration in the record and converts it into Cyrillic. The transliterations table is not used.

This section has `protectSubstrings` applied. Further details are below.

### Phase 3: Create transliterations report

There is a transliterations report at `/reports/transliterations/?filter=1` which aims to spot cases where the reversibility check failed, as well as show spelling problems.

This is basically just a dynamic read of the `transliterations` table.

At the point the a page of the transliterations table is loaded, the spell-checker dynamically reads the generated Cyrillic (from BGN/PCGN) and checks for errors. These are marked with a red wavy underline, by wrapping words in a `<span>` tag, visible in supported browsers. (Firefox has native support, and Chrome requires the `--enable-experimental-web-platform-features` flag to be enabled.)

The spell-checker library function (application::spellcheck) is supplied with a protected strings list from `transliterationProtectedStrings`. However, this implementation is deficient and instead needs `protectSubstrings` applied.

As the spell-checker is resource-intensive, and is given (by default) 1,000 record titles to check at once, the library function is equipped with support for caching via a database table, which works as follows:

  * The database handle is supplied to it.
  * This creates a table `spellcheckcache` if this does not already exist
  * The database table is indexed by spellchecked word, with two associated fields: an `isCorrect` flag and a set of `suggestions` (stored as a pipe-separated list)
  * The entire table is retrieved and loaded into an array in memory.
  * The spell-checker works through all the runtime-supplied strings.
  * If present in the cache, this is used; if not it is looked up using `enchant`.
  * New words are inserted added to cache, and the database table is replaced with the new cache list.


## Protected strings

Any conversion involving conversion of a reverse-transliterated string (i.e. currently in BGN/PCGN or LoC) to  Cyrillic needs some strings protected from conversion.

Conversion from Cyrillic back to Latin never needs string protection, as the conversion table will naturally ignore Latin characters and only consider the Cyrillic characters, which also have a one-to-one mapping.

There is therefore a routine called `protectSubstrings` which takes the string and caches parts of the string away, replacing with a unique token, such as `<||367||>` .

This works by:

1. Creating a list of protected strings (a small number of which are defined as regexps), consisting of:
  
  * Parts in italics, which are Latin names that a publisher would not translate
  * HTML tags (italics / superscript / subscript)
  * Known strings for protection, loaded by a subroutine `transliterationProtectedStrings`, consisting of of:
    * Species order names, which come from a set of `*ks` values in the `udctranslations` database table
    * A defined list of known special strings, in `/tables/transliterationProtectedStrings.txt`, consisting of:
	  * Species names
	  * Chemical formulae
	  * Latin abbreviations and phrases
	  * Acronyms
	  * Names
	  * Organisation names
	  * Other strings to protect
    * Roman numerals and pairs of Roman numerals, which are specified as a set of regexps (surrounded by `/` terminators) that are evaluated in the following stage. This avoids having to supply every Roman numeral possibility.

2. The regexps resulting from step 1 now need to be turned into fixed strings, so within `protectSubstrings`, the following pre-processing is done:
  
  * The protected strings list is looped through.
  * Where a protected string begins and ends with the regexp terminator (`/`):
    * The regexp definition is removed from the list
	* The regexp is pre-tested against the incoming string.
	* If it matches, the portion of the incoming string that matches is added to the protected strings list, as it is by this point a fixed string.
  * At this point, all strings are known to be fixed strings, not regexps.
  
3. For performance reasons, a basic substring match is done first, to reduce complexity of a later regexp match. This means that most of the (large number of) strings in the protected strings list are discarded for the string to be tested.

4. If there are no matches, the test string is returned unmodified and the `protectSubstrings` routine ended. An empty list of `protectedParts` is passed back by reference.

5. A token is created for each protected part; this is passed back by reference, e.g. `'<||12||>' => 'Fungi'`, for easy restoration by the caller of `protectedParts`.

6. Each pattern is converted to be word-boundary -based; the word boundary has to be defined manually rather than using `\b` because some strings start/end with a bracket.

7. A single `preg_replace` is performed against the string, to substitute in the tokens.

8. At this point, the modified string, containing the tokens in place of the protected sections, is passed back, together with the list of `protectedParts` passed back by reference.


## TODO:

* There are still reversibility failures at `/reports/transliterations/?filter=1` relating to roman numerals. These should be added as additional regexps at the end of `transliterationProtectedStrings`.

