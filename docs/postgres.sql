create table messages
(
    id              bigserial       primary key,
    exchange        varchar(250)    not null,
    "queueSender"   varchar(255)    not null,
    "queueConsumers"json            null,
    key             varchar(350)    not null,
    "senderId"      varchar(50)     null,
    "senderType"    varchar(250)    null,
    payload         json            null,
    "createdAt"     timestamp(0)    null,
    "updatedAt"     timestamp(0)    null,
    "deletedAt"     timestamp(0)    null
);
alter table messages owner to rabbitmq;

create table message_faileds
(
    id              bigserial       primary key,
    "messageId"     bigint          not null,
    subject         varchar(255)    not null,
    "queueSender"   varchar(255)    not null,
    "queueConsumer" varchar(255)    not null,
    key             varchar(255)    not null,
    payload         json            null,
    exception       text            null,
    repaired        smallint        default 0  not null,
    rested          smallint        default 0  not null,
    retry           integer         default 0  not null,
    "createdAt"     timestamp(0)    null,
    "updatedAt"     timestamp(0)    null,
    "deletedAt"     timestamp(0)    null
);
alter table message_faileds owner to rabbitmq;

create table exchanges
(
    id              bigserial       primary key,
    name            varchar(250)    not null,
    type            varchar(255)    not null,
    "createdAt"     timestamp(0)    null,
    "updatedAt"     timestamp(0)    null,
    "deletedAt"     timestamp(0)    null
);
alter table exchanges owner to rabbitmq;

create table queues
(
    id              bigserial       primary key,
    name            varchar(250)    not null,
    type            varchar(255)    not null,
    "createdBy"     varchar(50)     null,
    "createdByName" varchar(250)    null,
    "createdAt"     timestamp(0),
    "updatedAt"     timestamp(0),
    "deletedAt"     timestamp(0)
);
alter table queues owner to rabbitmq;

create table keys
(
    id              bigserial       primary key,
    "queueId"       integer         not null,
    name            varchar(250)    not null,
    "createdBy"     varchar(50)     null,
    "createdByName" varchar(250)    null,
    "createdAt"     timestamp(0),
    "updatedAt"     timestamp(0),
    "deletedAt"     timestamp(0)
);
alter table keys owner to rabbitmq;