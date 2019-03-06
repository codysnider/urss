begin;

alter table ttrss_archived_feeds add column created datetime;
update ttrss_version set schema_version = 136;
alter table ttrss_archived_feeds alter column created set not null;

update ttrss_archived_feeds set created = NOW();

commit;
