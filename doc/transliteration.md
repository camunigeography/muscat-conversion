# Transliteration


This article documents the transliteration procedures within the conversion process.

## Aims

Transliteration is currently only applied to Russian records. Other languages have been deemed out-of-scope, though code has been written with a view to avoid hard-coding Russian.

The aim is that all such records:

1. Contain real Cyrillic characters (Unicode), which are not present in the original records, and;
2. Transliterated sections use the modern Library of Congress (LoC) transliteration, not the BGN/PCGN 1947 system which is no longer widely used.

These involve two distinct processes.

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

The spell-checker dynamically reads the generated Cyrillic (from BGN/PCGN) and checks for errors.

The spell-checker library function is supplied with a protected strings list from `transliterationProtectedStrings`. However, this implementation is deficient and instead needs `protectSubstrings` applied.


## Protected strings

Any conversion involving conversion of a reverse-transliterated string (i.e. currently in BGN/PCGN or LoC) to  Cyrillic needs some strings protected from conversion.

Conversion from Cyrillic back to Latin never needs string protection.

There is therefore a routine called `protectSubstrings` which takes the string and caches parts of the string away, replacing with a unique token, such as `<||367||>` .

#!# Details TODO


