Date: Fri, 11 Oct 2013 11:08:59 +0100
From: Paul Taylor-Crush
To: SPRI Webmaster
Subject: MARC BibCheck - Perl Mods

Hi,

Attached is the perl module UClint_RDA.pm, this is the module used by the
BibCheck system to do the actual MARC checking. Also attached are UClint_RDA
dependencies, CodeData.pm and RDA_vocabulary.pm, they just contain arrays and
hashes of controlled terms used in MARC. UCLint_RDA.pm is an very extended
version of http://search.cpan.org/~eijabb/MARC-Lint_1.47/ , and as such expects
the data passed to it to be "MARC objects" created by MARC-Record pm (also
needed for UClint_RDA.pm). When I was adding new checks a few months ago I wrote
a little script, lint_test.pl,  that opens a file of MARC records them through
the checks, I've attached that too but I don't think it will much good for you
as the input file needs to be MARC exchange format (.mrc extension). If you are
going to incorporate the checks into your export routine I think you'll have to
use MARC::Record->new() to create a new "MARC object" and then populate the
object with each of the fields.

The following is taken from the MARC-Record documentation.

Creating a record
To create a new MARC record, you‘ll need to first create a MARC::Record object,
add a leader (though
MARC::Record can create leaders automatically if you don‘t specifically define
one), and then create
and add MARC::Field objects to your MARC::Record object. For example:
 1 ## Example C1
 2
 3 ## create a MARC::Record object.
 4 use MARC::Record;
 5 my $record = MARC::Record->new();
 6
 7 ## add the leader to the record. optional.
 8 $record->leader(’00903pam 2200265 a 4500’);
 9
 10 ## create an author field.
 11 my $author = MARC::Field->new(
 12 ’100’,1,’’,
 13 a => ’Logan, Robert K.’,
 14 d => ’1939-’
 15 );
 16 $record->append_fields($author);
 17
 18 ## create a title field.
 19 my $title = MARC::Field->new(
 20 ’245’,’1’,’4’,
 21 a => ’The alphabet effect /’,
 22 c => ’Robert K. Logan.’
 23 );
 24 $record->append_fields($title);



Questions?

Paul T-C

-- 
Paul Taylor-Crush
libraries@cambridge
Cambridge University Library
West Road
Cambridge
CB3 9DR




    [ Part 2, Text/PLAIN (charset: windows-1252) (Name: ]
    [ "RDA_vocabulary.pm") ~5.9 KB. ]
    [ Unable to print this part. ]


    [ Part 3, Text/PLAIN (charset: windows-1252) (Name: "CodeData.pm") ~22 ]
    [ KB. ]
    [ Unable to print this part. ]


    [ Part 4, Text/PLAIN (charset: windows-1252) (Name: "UClint_RDA.pm") ]
    [ 8,005 lines. ]
    [ Unable to print this part. ]


    [ Part 5, Text/PLAIN (charset: windows-1252) (Name: "lint_test.pl") ]
    [ ~1.1 KB. ]
    [ Unable to print this part. ]
