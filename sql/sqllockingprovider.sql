DROP TABLE IF EXISTS kinikit_object_locking;

CREATE TABLE kinikit_object_locking(
	object_class		VARCHAR(255),
	object_pk			VARCHAR(255),
	last_modified		DATETIME,
	PRIMARY KEY (object_class, object_pk)
);