-- Create the table
CREATE TABLE nathan (
  id integer AUTO_INCREMENT,
  name varchar(255),
  age integer,
  PRIMARY KEY (id)
);



INSERT INTO nathan (name, age) VALUES ('Nathan', 25);


INSERT INTO nathan (name, age) VALUES ('Mark', 35);


INSERT INTO nathan (name, age) VALUES ('Philip', 58);


UPDATE nathan SET age = 40 WHERE name = 'Mark';




