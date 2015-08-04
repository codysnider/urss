BEGIN;

alter table ttrss_feeds add column feed_language varchar(100);
update ttrss_feeds set feed_language = '';
alter table ttrss_feeds change feed_language feed_language varchar(100) not null;
alter table ttrss_feeds alter column feed_language set default '';

UPDATE ttrss_version SET schema_version = 128;

COMMIT;
