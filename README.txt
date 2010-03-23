This project is to extend John Blyberg's SOPAC Drupal module, giving it functionality useful to University libraries.  SOPAC currently supports public libraries well, but these extensions aim to add things like detailed journal information, course reserves information, and ideally replicate the functionality of any WebOpac module offered by III.  Right now I'm just copying in my whole "Libraries" directory (I use symlinks from /usr/local/lib/locum, etc), but later once I get this thing panned out & de-coupled properly, this repo will only contain the necessary overrides (locum-hooks.php, locum_iii_2007/, etc)

Setup
---------------
1) First, follow all the steps in http://thesocialopac.net/getting-started, *up until harvest*
  * Note: I'm developing on Mac and pushing to CentOS.  If you're like me & not using Debian, check out setup_ref.txt for some tips. 
2) After setting up the {scas} db, import the additional SQL:
  $ mysql -uscasuser -pscaspassword < /usr/local/lib/locum/sql/locum_university_init.sql 
3) Harvest