# Status allocation and item record creation

## Library location values

In Muscat, records can have one or more *location values, which represent a physical location.

### Standard case

Each *location value is translated to a UL code for use in the 852 $c (Shelving location). These are defined in the `locationCodes` list.

For instance 'Map Room' has the code 'SPRI-MAP'.

### Not in SPRI

Sometimes, a record has been catalogued that does not physically exist in SPRI but in the past was considered to have value to the reader to come up in searches on a particular topic. These are given *location = 'Not in SPRI' and may optionally have one or more other *location values which are indications as to what library they actually exist at.

Records that are 'Not in SPRI' never have a SPRI location also, as proven by the `notinspriinspri` report.

### *location=Periodical handling

Where a record has originally been marked in the Muscat data as *location = Periodical, special handling takes place at a very early stage during the import routine.

*location=Periodical is a special concept in the Muscat system for some *in records, to indicate to the OPAC should dynamically determine the shelf location from its *kg parent and display that, avoiding the need to hard-code it. 'Periodical' is not a real shelf location as such.

For the import, the actual shelf location is substituted to the actual looked-up shelf location in the `processPeriodicalLocations` routine.. Only when *location=Periodical cannot be matched with a parent does it remain.

Numbers of location=Periodical cases are as follows (March 2019):

| Type    | location=Periodical | location=[Other]  | Note |
| ------- | ------------------- | ----------------- | ---- |
| /art/in | 693                 | 29,149            | - |
| /art/j  | 83,103 -> 103       | 37,983 -> 120,983 | Change between `catalogue_rawdata` and `catalogue_processed` due to `processPeriodicalLocations` routine |

As per the matching routine in `titlesMatchingTemporaryTables` used by various reports, those remaining with location=Periodical either have an explicit *kg match, or are pamphlets or are in the special collection.

## Filter tokens

Every record is given at least one filter token.

Filter tokens are strings created and then allocated to (a) the record metadata, (b) 852 field(s), and (c) the 917 field.

Filter tokens are derived from either *status or *location (or both). The `nolocationnostatus` report establishes that all records have either a location nor a status (or both).

  * A *location field is the location of each holding for the record.
  * A *status field (of which there can only be one in a record, as established in the `multiplestatus` report) is used for records prior to full cataloguing (i.e. ON ORDER, RECEIVED, or ORDER CANCELLED)
  * A *status may also exist where the record is specifically marked to be suppressed. This is the only scenario where both *location and *status both exist.

| Filter token					| Based on 	| Description/scenario |
| ----------------------------- | ---------	| ----------------------------------------------------- |
| SUPPRESS-EXPLICITLY			| *status  	| Record marked specifically to suppress, e.g. pamphlets needing review, etc. |
| SUPPRESS-MISSINGQ				| *location | Missing: '??' or 'Pam ?' |
| SUPPRESS-PICTURELIBRARYVIDEO	| *location | Picture Library Store videos |
| IGNORE-DESTROYEDCOPIES		| *location | Item has been destroyed during audit |
| IGNORE-IGS					| *location | IGS locations |
| IGNORE-ELECTRONICREMOTE		| *location | Digital records |
| IGNORE-STATUSRECEIVED			| *status  	| Item is being processed, i.e. has been accessioned and is with a bibliographer for classifying and cataloguing |
| IGNORE-STATUSORDERCANCELLED	| *status  	| Order cancelled by SPRI, but record retained for accounting/audit purposes in the event that the item arrives |
| IGNORE-STATUSONORDER			| *status  	| Item on order >1 year ago so unlikely to be fulfilled, but item remains desirable and of bibliographic interest |
| IGNORE-NOTINSPRI				| *location | Items held not in SPRI |
| IGNORE-LOCATIONUL				| *location | Items held at the UL, i.e. elsewhere |
| MIGRATE						| -        	| No problem |

MIGRATE is allocated automatically if no problem-related token (SUPPRESS-* or IGNORE-*) has been allocated.

Migrate is added to the 852 $0 (but not does not generate a 917 $a problem).

If there is more than one, then they are comma-separated in the metadata storage in `catalogue_marc`, e.g. `MIGRATE, MIGRATE` (two locations, each migrated), or `MIGRATE, IGNORE-DESTROYEDCOPIES` (one location migrated, the other to be ignored due to a destroyed copy).

Filter tokens are used in three locations:

  * The record metadata, as the `filterTokens` field in `catalogue_marc` - this is then used to allocate the status category and thus the download bucket
  * As one or more $0 subfields in the 852 field, where an 852 exists (i.e. item record(s) to be created for each location), so that each item record can independently be determined as migrate/suppress/ignore when there is more than one location - automatically stripped during subsequent processing by the UL
  * As one or more $a subfields in the 917 (locally-specific data) field, each such subfield stating the Suppression reason or Ignoration reason (but migrate is not created) - this 917 $a subfield is intended as an indicator for cataloguers to remove as records are cleaned

Because filter tokens are added to the record metadata, this means that filtering takes place correctly irrespective of whether 852 item record lines exist.

### Filter token algorithm

Filter token generation always takes place, even if no 852 lines result. This is because the code triggers creation of the registry and separately decides whether to format the tokens into an 852 $0.

The filter token algorithm is as follows:

  1. An empty master registry of filter tokens for this record is initialised when `convertToMarc` is started
  2. The filter token creation routine is initiated within the `generate852` macro (as this runs before the 917 `showSuppressionReason` macro, which uses the same tokens later)
  3. Within the `generate852` macro, filter token(s) generation is triggered (as `filterTokenCreation`) when the first of these occurs, after which no further processing is done:
      * If there are no *location fields (in which case there will be a *status); no 852 lines are created (but the tokens are still registered)
      * If any *location is 'Not in SPRI'; again, no 852 lines are created (but the tokens are still registered)
      * Within each *location line; an 852 may be created if the location has item records
  4. Each time the filter token(s) generation routine (`filterTokenCreation`) is triggered, this proceeds as follows:
      1. An empty list of filter tokens is initialised
	  2. If there is a *status, the relevant token (as per the above table) is added to the filter token list; NB this will mean that if there is a SUPPRESS-EXPLICITLY token in a record with multiple *location, each 852 will get that SUPPRESS-EXPLICITLY
	  3. A loop considers each *location (which could be multiple in the 'Not in SPRI' case, or one in the *location case)
	  4. For each such location, if that location matches one of the IGNORE-/SUPPRESS- scenarios listed in the table above, that token is added to the filter token list
	  5. All the IGNORE- and SUPRESS- scenarios will now have been added to the filter token list
	  6. If there are no filter tokens (i.e. a normal record with no problems found), MIGRATE is allocated to the (empty) list
	  7. All the tokens from this iteration are added to the master registry
	  8. The tokens from this iteration are returned to the calling triggering code (step 3 above), for potential use if there is an item record (i.e. if 852 is created)

## 852 Item record lines

Two resources giving a good overview of the split between Bibliographic records and Item records can be found on Wikipedia:

  * [Functional Requirements for Bibliographic Records](https://en.wikipedia.org/wiki/Functional_Requirements_for_Bibliographic_Records)
  * [Library terminology informally explained](https://www.w3.org/2001/sw/wiki/Library_terminology_informally_explained)

Item record lines (852) are created for each physical item (being a repeatable field), according to the following principles:

  * Item records are created for each physical location (*location) that exists (i.e. not 'Not in SPRI').
  * Item records are created for migrate/suppress record, but are skipped for ignore records.

Where the record has a host (i.e. a *kg or with a title-based match), and the (first) *location of that host matches the current *location under consideration as an 852, that 852 is skipped, as there will be a 773. Examples:

E.g. one location, but with a host with the same location (record 20557):

```
773 0# ‡7nnas ‡tJournal of Glaciology. ‡gVol. 1(1) (1947) ‡wSPRI-20534
```

E.g. two locations, and a *kg whose location does not match either, so three in total (record 27094):

```
773 0# ‡7nnas ‡tMeddelelser om Grønland. ‡gVol. 137(1) (1955) ‡wSPRI-4496
852 7# ‡2camdept‡bSCO‡cSPRI-PAM‡h(*38) : 528.37‡9Create 1 item record‡0Migrate
852 7# ‡2camdept‡bSCO‡cSPRI-BMT‡hBound in Basement Seligman 59‡0Migrate
```

The following metadata is added to each 852 on an independent basis, in order to inform the UL of how to process this item record, and these tokens will then be stripped by the UL:

  * Item records contain a fake $9 field stating the number of item records required for this location.
  * Item records contain one or more fake $0 field(s), listing the filter token(s), as described above.

## 917 Locally-specific data line

The 917 field is locally-specific data which will be used by cataloguers. Every record has a 917 as it also exists for several reasons. This is not a repeatable field.

Filter tokens, added as $a, are useful in this field, as they enable a cataloguer to know what work is needed on a record so it can be unsuppressed. The $a is repeatable, so multiple filter tokens appear as $a.

Tokens are considered as follows:

  * MIGRATE requires no action, so no $a is generated.
  * IGNORE-* records will not have an $a generated: such records will either not be picked up by the UL at all (as the download bucket will be ignored), or for multiple location records with mixed filter token statuses, will not have an 852 line when there are multiple locations (so that additional location will not have an item record generated).
  * SUPPRESS-* records will have $a generated.

Accordingly, the 917 $a algorithm will only contain SUPPRESS-* tokens, and ignores all others.

## Status categories

Records are sorted into one of three status categories:

  1. Migrate - send to the UL and make visible in the OPAC
  2. Suppress - send to the UL but suppress from being visible in the OPAC, pending cataloguing work, e.g. due to being marked as missing
  3. Ignore - do not send to the UL, i.e. discard the record, e.g. due to not actually being held at SPRI

If an item has more than one location (e.g. migrate + ignore), migrate dominates over suppress, and suppress dominates over ignore.

Thus a record marked as to be migrated may still contain a suppression 852 line, though can never contain an ignore line (as item records are skipped for ignore records).

## Download buckets from status

Once the status has been assigned, this is then put into a download bucket:

  * 1a: Migrate
  * 1b: Migrate, with item record(s)
  * 2a: Suppress
  * 2b: Suppress, with item record(s)
  * 3: Ignore

## Export page

Each of the migrate and suppress types are then further divided into two sub-buckets, for ease of subsequent processing by the UL:

  * Serial records ( /ser )
  * Monographs & articles ( /doc , /art/in , /art/j )

Two types of download format are generated:

  * MARC21 text file
  * MARC21 binary .mrc file

Accordingly, there are 16 downloads, representing all the combinations of:

  * Migrate vs suppress
  * Item records vs no item records
  * Serials vs monotographs/articles
  * MARC21 text vs binary .mrc output format

There are thus 16 download boxes on the export page.


## Examples

Migrate with item (record 1000, 2 item records):

```
852 7# ‡2camdept‡bSCO‡cSPRI-MAP‡9Create 2 item records‡0Migrate
917 ## ‡aUnenhanced record from Muscat, imported 2019
```


Migrate (record 1109, no item records):

```
917 ## ‡aUnenhanced record from Muscat, imported 2019
```


Suppress with item (record 1026, 1 item record):

```
852 7# ‡2camdept‡bSCO‡cUNASSIGNED‡9Create 1 item record‡0Suppress: SUPPRESS-MISSINGQ (Missing with ?)
917 ## ‡aUnenhanced record from Muscat, imported 2019 ‡aSuppression reason: SUPPRESS-MISSINGQ (Missing with ?)
```


Suppress (record 1166, no item records):

```
917 ## ‡aUnenhanced record from Muscat, imported 2019 ‡aSuppression reason: SUPPRESS-EXPLICITLY (Record marked specifically to suppress, e.g. pamphlets needing review, etc.)
```


Ignore (record 1282):

```
917 ## ‡aUnenhanced record from Muscat, imported 2019
```


One location plus status = suppress, both causing suppress with item (record 1645):

```
852 7# ‡2camdept‡bSCO‡cSPRI-PAM‡h?‡9Create 1 item record‡0Suppress: SUPPRESS-EXPLICITLY (Record marked specifically to suppress, e.g. pamphlets needing review, etc.) ‡0Suppress: SUPPRESS-MISSINGQ (Missing with ?)
917 ## ‡aUnenhanced record from Muscat, imported 2019 ‡aSuppression reason: SUPPRESS-EXPLICITLY (Record marked specifically to suppress, e.g. pamphlets needing review, etc.) ‡aSuppression reason: SUPPRESS-MISSINGQ (Missing with ?)
```


Migrate plus ignore, giving overall migrate with item record and no mention of ignore (record 118221):

```
852 7# ‡2camdept‡bSCO‡cSPRI-SHF‡h629.783‡9Create 1 item record‡0Migrate
917 ## ‡aUnenhanced record from Muscat, imported 2019
```


Two locations, both to migrate (record 1104):

```
852 7# ‡2camdept‡bSCO‡cSPRI-PAM‡h92[Scott, R.F.]‡zSPRI has two copies. Copy A detached from book. Copy B is photocopy with photocopy of picture showing Burnside bungalow today attached‡9Create 1 item record‡0Migrate
852 7# ‡2camdept‡bSCO‡cSPRI-PAM‡h91(08) : (*7)[1910-13 Scott][F]‡zSPRI has two copies. Copy A detached from book. Copy B is photocopy with photocopy of picture showing Burnside bungalow today attached‡9Create 1 item record‡0Migrate
917 ## ‡aUnenhanced record from Muscat, imported 2019
```


Not in SPRI, causing ignore (record 7976):

```
917 ## ‡aUnenhanced record from Muscat, imported 2019
```
