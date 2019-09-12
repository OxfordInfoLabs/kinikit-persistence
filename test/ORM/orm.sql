
DROP TABLE IF EXISTS address;

CREATE TABLE address (id integer PRIMARY KEY AUTOINCREMENT, name VARCHAR(20), street_1 VARCHAR(50),
street_2 VARCHAR(50), phone_number VARCHAR(50), country_code VARCHAR(5));

INSERT INTO address(name, street_1, street_2, phone_number, country_code)
VALUES ('Oxil', 'Lincoln House', 'Pony Road', '01865 777777', 'GB'),
('Home', '3 Some Street', 'Somewhere', '01865 111111', 'GB'),
('Holiday Home', '22 Some Lane', 'Nice', '5654646', 'FR');
