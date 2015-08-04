BEGIN;

alter table ttrss_entries add column tsvector_combined tsvector;
create index ttrss_entries_tsvector_combined_idx on ttrss_entries using gin(tsvector_combined);

UPDATE ttrss_version SET schema_version = 128;

COMMIT;
