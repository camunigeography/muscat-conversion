# The MARC record processing phase


## Chunking operation and ordering

The way the MARC records processing phase works is as follows:

The MARC records are done in chunks of 500 records. For each record, its aim is to take the XML structure and outputs the MARC record. It works through each type of record in order (`/doc`, then `/ser`, then `/art/j`, then `/art/in`). These are done in numerical order. Thus, the first 500 `/doc` records are done, then the next 500 `/doc` records are done, etc., until all `/doc` records are done. Then we move on to `/ser`, and so on.

However, there is a special consideration that applies when a record has a `*kg`. If a record has a `*kg`, then it tries to look up this host MARC record, so that the 773 (and 500) field can be generated. 773 and 500 take bits of the host's marc fields, e.g. the 100 field.

Therefore, the host MARC record *has* to exist (i.e. has a finalised MARC record) by the time the child record is created. However, sometimes the host doesn't yet exist, because it's later in the series of records. This only happens a few hundred times.

In this scenario, i.e. for the few hundred times it happens, the child is marked in a list of records to reprocess (i.e. the MARC record generation is run a second time) at the end. There is then a final wash-up chunk of records which does this fixup. Because this is expected to be a low number (a few hundred), this is done as a single chunk, which will be fine in memory terms.

E.g. Record 2000 could have a host no. 4000, which doesn't yet exist (because the records are processed in numerical order within a record type, and 4000 comes after 2000). Record 2000 is thus marked in a list of records to reprocess, and in that second-phase reprocessing run, record 4000 will be guaranteed to be present by then.
