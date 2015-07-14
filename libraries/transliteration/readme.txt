Files are at
/root/.cpan/build/Lingua-Translit-0.21-th0SPW/xml/

Documentation at
http://www.lingua-systems.com/translit/downloads/lingua-translit-developer-manual-eng.pdf

XML transliteration file:
/transliteration/bgn_pcgn_1947.xml

Make changes to the XML file then run, as root:
cd /root/.cpan/build/Lingua-Translit-0.21-th0SPW/xml/ && make all-tables && cd /root/.cpan/build/Lingua-Translit-0.21-th0SPW/ && make clean && perl Makefile.PL && make && make install

Lingua Translit documentation:
http://www.lingua-systems.com/translit/downloads/lingua-translit-developer-manual-eng.pdf
http://search.cpan.org/~alinke/Lingua-Translit-0.21/lib/Lingua/Translit.pm#ADDING_NEW_TRANSLITERATIONS

Character set numbers - useful references for copying and pasting:
- Russian: http://en.wikipedia.org/wiki/Russian_alphabet
- Basic latin: http://en.wikipedia.org/wiki/List_of_Unicode_characters