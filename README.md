Muscat conversion system
========================

This is a PHP application to assist with the conversion from Muscat to MARC21.

[![DOI](https://zenodo.org/badge/39077026.svg)](https://zenodo.org/badge/latestdoi/39077026)

A [talk about the project](https://www.youtube.com/watch?v=kVIvFswvRXI) was given to the [CILIP CIG (MDG) Conference 2020: Metadata and Discovery](https://www.cilip.org.uk/events/EventDetails.aspx?id=1332403) conference on 9th September 2020.


Screenshot
----------

![Screenshot](screenshot.png)


Usage
-----

1. Clone the repository.
2. Run `composer install` to install the dependencies.
3. Download and install the famfamfam icon set in /images/icons/
4. Add the Apache directives in httpd.conf (and restart the webserver) as per the example given in .httpd.conf.extract.txt; the example assumes mod_macro but this can be easily removed.
5. Create a copy of the index.html.template file as index.html, and fill in the parameters.
6. Install the software dependencies as per the install script `install.sh`
7. Access the page in a browser at a URL which is served by the webserver.


Dependencies
------------

System software:

* Lingua Translit CPAN module
* Enchant for PHP
* MarcEdit
* Mono
* Bibcheck lint checker

Other:

* [FamFamFam Silk Icons set](http://www.famfamfam.com/lab/icons/silk/)


Bug marker notes
----------------

Bug markers are defined as follows:

* `#!#` General, unresolved
* `#!#C` Code-purity -related, not essential to fix
* `#!#I` Interface-related, and do not affect conversion correctness
* `#!#M` Merging-related, unproblematic as merging is disabled in final release
* `#!#H` Indicates hard-coded language (Russian), will not be fixed as other transliterations deemed out of scope


Author
------

Martin Lucas-Smith, Department of Geography, University of Cambridge, 2012-19, 2024.


License
-------

GPL3.

