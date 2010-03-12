--
-- You need to put something like this in your my.cnf file under [mysqld] :
-- 
-- max_heap_table_size     = 200M
-- init_file               = /path/to/locum_init.sql
-- 

-- Change this to whatever your DB name is
USE scas;

-- No need to change anything below here
DROP TABLE IF EXISTS locum_facet_heap;

CREATE TABLE locum_facet_heap ENGINE=MEMORY CHARACTER SET utf8 COLLATE utf8_general_ci SELECT locum_bib_items.bnum, series, mat_code, loc_code, lang, pub_year, TRUNCATE(pub_year/10,0)*10 AS pub_decade, bib_lastupdate FROM locum_bib_items LEFT JOIN locum_availability on locum_bib_items.bnum = locum_availability.bnum WHERE active = '1';
ALTER TABLE locum_facet_heap ADD PRIMARY KEY (bnum);
ALTER TABLE locum_facet_heap ADD INDEX USING BTREE (series);
ALTER TABLE locum_facet_heap ADD INDEX USING BTREE (mat_code);
ALTER TABLE locum_facet_heap ADD INDEX USING BTREE (loc_code);
ALTER TABLE locum_facet_heap ADD INDEX USING BTREE (lang);
ALTER TABLE locum_facet_heap ADD INDEX USING BTREE (pub_year);
ALTER TABLE locum_facet_heap ADD INDEX USING BTREE (pub_decade);
ALTER TABLE locum_facet_heap ADD INDEX USING BTREE (bib_lastupdate);