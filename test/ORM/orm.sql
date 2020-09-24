
DROP TABLE IF EXISTS address;

CREATE TABLE address (id integer PRIMARY KEY AUTOINCREMENT, name VARCHAR(20), street_1 VARCHAR(50),
street_2 VARCHAR(50), phone_number VARCHAR(50), country_code VARCHAR(5));

INSERT INTO address(name, street_1, street_2, phone_number, country_code)
VALUES ('Oxil', 'Lincoln House', 'Pony Road', '01865 777777', 'GB'),
('Home', '3 Some Street', 'Somewhere', '01865 111111', 'GB'),
('Holiday Home', '22 Some Lane', 'Nice', '5654646', 'FR');



DROP TABLE IF EXISTS contact;

CREATE TABLE contact(id integer PRIMARY KEY AUTOINCREMENT, name VARCHAR(20), primary_address_id INTEGER);

INSERT INTO contact(name, primary_address_id)
VALUES ('Mark', 1), ('Luke', 2), ('John', 3);


DROP TABLE IF EXISTS address_country_collection;

CREATE TABLE address_country_collection (id integer PRIMARY KEY AUTOINCREMENT, country_of_interest);
INSERT INTO address_country_collection (country_of_interest)
VALUES ('GB'),('FR');


DROP TABLE IF EXISTS contact_other_addresses;
CREATE TABLE contact_other_addresses (contact_id INTEGER, address_id INTEGER);

INSERT INTO contact_other_addresses VALUES (1, 2),(1,3),(2,3),(3,1);


DROP TABLE IF EXISTS phone_number;

CREATE TABLE phone_number (id integer PRIMARY KEY AUTOINCREMENT, type VARCHAR(20), number VARCHAR(100), contact_id integer);

INSERT INTO phone_number (type, number, contact_id)
VALUES ('Mobile', '07777 999999', 1),('Home', '01865 222222', 1),
('Mobile', '07666 647282', 2);



DROP TABLE IF EXISTS profile;

CREATE TABLE profile (id integer PRIMARY KEY AUTOINCREMENT,  date_of_birth DATE, instantiated DATETIME, data TEXT, contact_id INTEGER);

INSERT INTO profile (date_of_birth, instantiated, data, contact_id)
VALUES ('1977-12-06', '2019-01-01 14:33:22', '{"test": "Mark", "live": "Luke"}', 1),
('1986-01-13', '2019-01-01 19:36:01', '[1,2,3,4,5]', 3);


DROP TABLE IF EXISTS document;
CREATE TABLE document (id integer PRIMARY KEY AUTOINCREMENT, content VARCHAR(500));

INSERT INTO document (content) VALUES ('Test Document 1'),('Test Document 2');

DROP TABLE IF EXISTS attachment;
CREATE TABLE attachment (id integer PRIMARY KEY AUTOINCREMENT, content VARCHAR(500), parent_id INTEGER, type VARCHAR(20));

INSERT INTO attachment(content, parent_id, type)
VALUES ('Note 1', 1, 'NOTE'), ('Note 2', 1, 'NOTE'), ('Note 3', 2, 'NOTE'),
('Comment 1', 1, 'COMMENT'), ('Comment 2', 2, 'COMMENT'), ('Comment 3', 2, 'Comment');


DROP TABLE IF EXISTS note;

CREATE TABLE note (id integer PRIMARY KEY AUTOINCREMENT, note VARCHAR(500), parent_note_id INTEGER);

INSERT INTO note(note, parent_note_id)
VALUES ('Top Level Note', null),
('First Level Note', 1),
('Second Level Note', 2),
('Third Level Note', 3),
('Fourth Level Note', 4),
('Fifth Level Note', 5),
('Sixth Level Note', 6),
('Seventh Level Note', 7);

DROP VIEW IF EXISTS note_view;
CREATE VIEW note_view
AS SELECT * FROM note;
