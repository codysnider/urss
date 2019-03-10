begin;

alter table ttrss_feeds add constraint ttrss_feeds_feed_url_owner_uid_key unique (feed_url(255), owner_uid);

update ttrss_version set schema_version = 137;

commit;
