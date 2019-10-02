DROP TABLE IF EXISTS example;
CREATE TABLE example (id integer PRIMARY KEY AUTOINCREMENT, name VARCHAR(20), last_modified DATE);

INSERT INTO example(name, last_modified) VALUES ('Mark', '2010-01-01');
INSERT INTO example(name, last_modified) VALUES ('John', '2012-01-01');
INSERT INTO example(name, last_modified) VALUES ('Dave', '2014-01-01');


DROP TABLE IF EXISTS example_parent;
DROP TABLE IF EXISTS example_child;
DROP TABLE IF EXISTS example_child2;
DROP TABLE IF EXISTS example_child_with_parent_key;
DROP TABLE IF EXISTS example_child_with_parent_and_type;
DROP TABLE IF EXISTS example_many_to_many_link;
DROP TABLE IF EXISTS example_recursive;

CREATE TABLE example_parent (id integer PRIMARY KEY AUTOINCREMENT, name VARCHAR(20), child_id INTEGER);
CREATE TABLE example_child (id integer PRIMARY KEY AUTOINCREMENT, description VARCHAR(20), child2_id INTEGER);
CREATE TABLE example_child2 (id integer PRIMARY KEY AUTOINCREMENT, profession VARCHAR(20));
CREATE TABLE example_child_with_parent_key (id integer PRIMARY KEY AUTOINCREMENT, description VARCHAR(20), parent_id INTEGER);
CREATE TABLE example_many_to_many_link (example_parent_id integer, example_child2_id integer);
CREATE TABLE example_child_with_parent_and_type (id integer PRIMARY KEY AUTOINCREMENT, description VARCHAR(20), parent_id INTEGER, type VARCHAR(20) );
CREATE TABLE example_recursive (id integer PRIMARY KEY AUTOINCREMENT, note VARCHAR(255), parent_id);


-- Create some example parents
INSERT INTO example_parent (name, child_id) VALUES ('Mary Jones', NULL);
INSERT INTO example_parent (name, child_id) VALUES ('Jane Walsh', 1);
INSERT INTO example_parent (name, child_id) VALUES ('James Smith', 2);
INSERT INTO example_parent (name, child_id) VALUES ('Heather Wright', 3);

-- Create some example regular child entries
INSERT INTO example_child (description) VALUES ('Washing');
INSERT INTO example_child (description) VALUES ('Swimming');
INSERT INTO example_child (description, child2_id) VALUES ('Cooking', 1);
INSERT INTO example_child (description, child2_id) VALUES ('Laughing', 2);
INSERT INTO example_child (description, child2_id) VALUES ('Smiling', 3);
INSERT INTO example_child (description, child2_id) VALUES ('Walking', 4);
INSERT INTO example_child (description, child2_id) VALUES ('Dancing', 5);

INSERT INTO example_child2 (profession) VALUES ('Doctor');
INSERT INTO example_child2 (profession) VALUES ('Teacher');
INSERT INTO example_child2 (profession) VALUES ('Nurse');
INSERT INTO example_child2 (profession) VALUES ('Author');
INSERT INTO example_child2 (profession) VALUES ('Car Mechanic');



-- Create some with parent key
INSERT INTO example_child_with_parent_key (description, parent_id) VALUES ('Washing', NULL);
INSERT INTO example_child_with_parent_key (description, parent_id) VALUES ('Swimming', 1);
INSERT INTO example_child_with_parent_key (description, parent_id) VALUES ('Cooking', 2);
INSERT INTO example_child_with_parent_key (description, parent_id) VALUES ('Laughing', 3);
INSERT INTO example_child_with_parent_key (description, parent_id) VALUES ('Smiling', 3);
INSERT INTO example_child_with_parent_key (description, parent_id) VALUES ('Walking', 3);
INSERT INTO example_child_with_parent_key (description, parent_id) VALUES ('Dancing', 4);

-- Create some with parent key and type
INSERT INTO example_child_with_parent_and_type (description, parent_id, type) VALUES ('Happy',1, 'NOTE');
INSERT INTO example_child_with_parent_and_type (description, parent_id, type) VALUES ('Excited',1, 'NOTE');
INSERT INTO example_child_with_parent_and_type (description, parent_id, type) VALUES ('Grumpy',2, 'NOTE');
INSERT INTO example_child_with_parent_and_type (description, parent_id, type) VALUES ('Sad',2, 'NOTE');
INSERT INTO example_child_with_parent_and_type (description, parent_id, type) VALUES ('Extrovert',1, 'COMMENT');
INSERT INTO example_child_with_parent_and_type (description, parent_id, type) VALUES ('Musician',2, 'COMMENT');
INSERT INTO example_child_with_parent_and_type (description, parent_id, type) VALUES ('Introvert',2, 'COMMENT');

-- Create some many to many link relationships
INSERT INTO example_many_to_many_link (example_parent_id, example_child2_id)
VALUES (1, 1), (1, 2), (1, 3), (2, 1), (2, 4), (2, 5), (3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6), (3, 7);


INSERT INTO example_recursive (note, parent_id)
VALUES ('Top level', null),
 ('First level', 1),
 ('Second level', 2),
 ('Third level', 3),
 ('Fourth level', 4),
 ('Fifth level', 5);

