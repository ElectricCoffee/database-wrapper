<?php

// The task is to create a database-wrapper capable of starting, stopping, and querying a database in PHP.
// Additionally, a model class needs to be written, which can then later be extended to other classes to give CRUD-like functionality (???)

class Person {
    // Not gonna bother with accessors
    // __get and __set are terrible and manual accessors are annoying to write
    public int $age;
    public string $name;
    public string $email;

    public function __construct($name, $age, $email) {
        $this->name = $name;
        $this->age = $age;
        $this->email = $email;
    }
}

class Database extends SQLite3 {
    public function __construct() {
        $this->open("people.db") or die("Could not open a connection to people.db");
        $this->exec("CREATE TABLE IF NOT EXISTS People (name VARCHAR, age INTEGER, email VARCHAR);");
    }

    public function create(Person $person) {
        $name = sqlite_escape_string($person->name);
        $age = strval($person->age);
        $email = sqlite_escape_string($person->email);

        $this->exec(<<<END_QUERY
        INSERT INTO People (name, age, email)
        VALUES ('$name', $age, '$email');
        END_QUERY);
    }

    public function read(int $id): Person {

    }

    public function update(int $id, Person $person) {

    }

    public function delete(int $id) {

    }
}


$db = new Database();



$db->close();