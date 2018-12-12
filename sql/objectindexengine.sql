DROP TABLE IF EXISTS kinikit_object_index;

CREATE TABLE kinikit_object_index (
  object_class VARCHAR(100) NOT NULL,
  object_pk    TEXT         NOT NULL,
  field_name   VARCHAR(100) NOT NULL,
  field_value  TEXT,
  value_class  VARCHAR(255),
  last_modified DATETIME,
  PRIMARY KEY (object_class, object_pk, field_name)
);



DROP TABLE IF EXISTS kinikit_object_index_history;

CREATE TABLE kinikit_object_index_history (
  version_timestamp DATETIME,
  session_ref       VARCHAR(1024),
  object_class      VARCHAR(100) NOT NULL,
  object_pk         TEXT         NOT NULL,
  field_name        VARCHAR(100) NOT NULL,
  field_value       TEXT,
  value_class       VARCHAR(255)
);

CREATE INDEX idx_kinikit_object_index_history ON kinikit_object_index_history (object_class, object_pk, field_name, version_timestamp);


