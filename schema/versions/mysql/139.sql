begin;

create table ttrss_app_passwords (id integer not null primary key auto_increment,
    title varchar(250) not null,
    pwd_hash text not null,
    service varchar(100) not null,
    created datetime not null,
    last_used datetime default null,
    owner_uid integer not null references ttrss_users(id) on delete cascade) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

update ttrss_version set schema_version = 139;

commit;
