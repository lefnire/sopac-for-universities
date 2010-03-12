This project is to extend John Blyberg's SOPAC Drupal module, giving it functionality useful to University libraries.  SOPAC currently supports public libraries well, but these extensions aim to add things like detailed journal information, course reserves information, and ideally replicate the functionality of any WebOpac module offered by III.  Right now I'm just copying in my whole "Libraries" directory (I use symlinks from /usr/local/lib/locum, etc), but later once I get this thing panned out & de-coupled properly, this repo will only contain the necessary overrides (locum-hooks.php, locum_iii_2007/, etc)

These are the steps *I* took to get it working on my Mac, will vary depending on your machine. 
Best to follow http://thesocialopac.net/getting-started instead
-----------------

(1) Symlinks based on SOPAC's expected directory locations  -- [http://thesocialopac.net/node/12]

ln -s insurge /usr/local/lib/insurge
ln -s locum_ucsf /usr/local/lib/locum
ln -s scas.php /usr/local/etc/scas.php
ln -s sphinx.conf /usr/local/sphinx/etc/sphinx.conf
ln -s /usr/local/lib/locum/sql/locum_init.sql /var/lib/mysql/locum_init.sql 
chown mysql:mysql /var/lib/mysql/locum_init.sql

mkdir /usr/local/etc
mysql> create database scas;
mysql> grant all privileges on scas.* to scasuser@'localhost' identified by 'scaspassword';
mysql> flush privileges;
echo "<?php $dsn = 'mysql://scasuser:scaspassword@localhost/scas';" > /usr/local/etc/scas.php

(2) Install sphinx (you're gonna hate this part if you're not on debian) [http://thesocialopac.net/node/18]

cd ~
wget http://sphinxsearch.com/downloads/sphinx-0.9.9.tar.gz
tar zxvf sphinx-0.9.9.tar.gz
cd sphinx-0.9.9
./configure --prefix=/usr/local/sphinx
make
make install

mkdir /usr/local/sphinx/lib
cp api/sphinxapi.php /usr/local/sphinx/lib/

echo "sphinx:x:999:999:Sphinx User,,,:/usr/local/sphinx/:/bin/true" >> /etc/passwd
echo "sphinx:x:999:999:Sphinx User,,,:/usr/local/sphinx/:/bin/true" >> /etc/passwd-
echo "sphinx:x:999:" >> /etc/group
echo "sphinx:x:999:" >> /etc/group-
mkdir /usr/local/sphinx/var/run
chown -R sphinx.sphinx /usr/local/sphinx/var
cd /etc/init.d
wget http://www.thesocialopac.net/sites/thesocialopac.net/files/sphinx
chmod +x /etc/init.d/sphinx

cp /usr/local/lib/locum/sphinx/sphinx.conf /usr/local/sphinx/etc/
#replace all instances of "locum_db_user" and "locum_db_pass" with the MySQL username and password you set up in the initial preparation.

(3) Install Insurge -- [http://thesocialopac.net/node/15]

mysql -u root -p < /usr/local/lib/insurge/sql/scas_insurge.sql
# change parent_server to your own server

(4) Install Locum -- [http://thesocialopac.net/node/14]

mysql -u root -p < /usr/local/lib/locum/sql/scas_locum.sql
vim /etc/mysql/my.cnf
 	# Add to [mysqld]:
 	max_heap_table_size = 200M
	init_file = /usr/local/lib/locum/sql/locum_init.sql
vim /usr/local/lib/locum/config/locum.ini
	# change [ils_config] -> ils_server
  
  
(5) When I get the MDB2 error, I just reset my whole database [http://thesocialopac.net/node/14#comment-117]
mysqladmin -uroot -proot drop scas && mysqladmin -uroot -proot create scas
mysql -uroot -proot < /usr/local/lib/insurge/sql/scas_insurge.sql
mysql -uroot -proot < /usr/local/lib/locum/sql/scas_locum.sql
mysql -uroot -proot < /var/lib/mysql/locum_init.sql  
 