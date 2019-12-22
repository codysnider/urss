begin;

create table ttrss_app_passwords (id serial not null primary key,
    title varchar(250) not null,
    pwd_hash text not null,
    service varchar(100) not null,
    created timestamp not null,
    last_used timestamp default null,
    owner_uid integer not null references ttrss_users(id) on delete cascade);

update ttrss_version set schema_version = 139;

commit;
