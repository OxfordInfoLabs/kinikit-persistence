DROP TABLE IF EXISTS person;

CREATE TABLE person
(
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255)
);

DROP TABLE IF EXISTS person_attribute;

CREATE TABLE person_attribute
(
    person_id INTEGER,
    attribute VARCHAR(255),
    PRIMARY KEY (person_id, attribute)
);

