This project is to extend John Blyberg's SOPAC Drupal module, giving it functionality useful to University libraries.  SOPAC currently supports public libraries well, but these extensions aim to add things like detailed journal information, course reserves information, and ideally replicate the functionality of any WebOpac module offered by III.  Right now I'm just copying in my whole "Libraries" directory (I use symlinks from /usr/local/lib/locum, etc), but later once I get this thing panned out & de-coupled properly, this repo will only contain the necessary overrides (locum-hooks.php, locum_iii_2007/, etc)

Setup (will vary depending on your machine, this is for my Mac).
-----------------
1) 
[symlinks]
/usr/local/lib/insurge -> /home/lefnire/sopac/sites/all/libraries/sopac/insurge
/usr/local/lib/locum -> /home/lefnire/sopac/sites/all/libraries/sopac/locum
/usr/local/etc/scas.php -> /home/lefnire/sopac/sites/all/libraries/sopac/scas.php
/usr/local/sphinx/etc/sphinx.conf -> /home/lefnire/sopac/sites/all/libraries/sopac/sphinx.conf

2) 
*if using vbox, these can't be symlinks because chmod is necessary (can't use chmod when storing files on vbox shared), remember to update them in git
  /etc/mysql/my.cnf -> /home/lefnire/sopac/sites/all/libraries/sopac/my.cnf
  /var/lib/mysql/locum_init.sql -> /usr/local/lib/locum/sql/locum_init.sql
  chown mysql:mysql /var/lib/mysql/locum_init.sql

*if MDB2 error:
  (see http://thesocialopac.net/node/14#comment-117)
  mysqladmin -uroot -proot drop scas && mysqladmin -uroot -proot create scas
  mysql -uroot -proot < /usr/local/lib/insurge/sql/scas_insurge.sql
  mysql -uroot -proot < /usr/local/lib/locum/sql/scas_locum.sql
  mysql -uroot -proot < /var/lib/mysql/locum_init.sql
  
3) add to my.cnf: 
max_heap_table_size = 200M
init_file = /var/lib/mysql/locum_init.sql
  

3) Set blocks to "show on listed pages" (rather than "except on listed pages")
