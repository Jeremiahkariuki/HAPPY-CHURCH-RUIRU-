CREATE DATABASE IF NOT EXISTS church_events_system;
USE church_events_system;

CREATE TABLE users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(50) NOT NULL,
  password_hash varchar(255) NOT NULL,
  role varchar(20) NOT NULL DEFAULT 'user',
  status varchar(20) NOT NULL DEFAULT 'Pending',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY username (username)
);

CREATE TABLE events (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(100) NOT NULL,
  event_date date NOT NULL,
  start_time time DEFAULT NULL,
  end_time time DEFAULT NULL,
  location varchar(100) DEFAULT NULL,
  category varchar(50) DEFAULT NULL,
  status varchar(20) DEFAULT 'Upcoming',
  description text DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
);

CREATE TABLE attendees (
  id int(11) NOT NULL AUTO_INCREMENT,
  full_name varchar(100) NOT NULL,
  phone varchar(20) DEFAULT NULL,
  email varchar(100) DEFAULT NULL,
  event_id int(11) NOT NULL,
  attendance_status varchar(20) DEFAULT 'Registered',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY event_id (event_id),
  CONSTRAINT attendees_ibfk_1 FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
);

CREATE TABLE volunteers (
  id int(11) NOT NULL AUTO_INCREMENT,
  full_name varchar(100) NOT NULL,
  phone varchar(20) DEFAULT NULL,
  email varchar(100) DEFAULT NULL,
  ministry varchar(100) DEFAULT NULL,
  availability varchar(100) DEFAULT NULL,
  notes text DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
);

CREATE TABLE donations (
  id int(11) NOT NULL AUTO_INCREMENT,
  full_name varchar(100) NOT NULL,
  amount decimal(10,2) NOT NULL,
  payment_method varchar(50) DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
);

INSERT INTO users (username, password_hash, role, status) VALUES ('admin', '$2y$10$examplehash', 'admin', 'Approved');
