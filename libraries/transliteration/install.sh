# Install local copy of Lingua Translit; run from this directory
if [ ! -f ./bin/translit ] ; then
	
	# Stop on errors
	set -e
	
	sudo apt-get install libxml2-utils
	
	mkdir cpan
	cd cpan/
	wget http://www.cpan.org/authors/id/A/AL/ALINKE/Lingua-Translit-0.22.tar.gz
	tar zxvf Lingua-Translit-*.tar.gz
	cd Lingua-Translit*
	perl Makefile.PL PREFIX=../
	make
	make test
	make install
	
	cd ../../
	sudo chown -R www-data cpan
	sudo chmod g+s cpan
	
fi
