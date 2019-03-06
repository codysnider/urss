begin;

alter table ttrss_archived_feeds add column created datetime;
update ttrss_archived_feeds set created = NOW();
alter table ttrss_archived_feeds change created created datetime not null;

update ttrss_version set schema_version = 136;

commit;
