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

    public static function from_array(array $hash): self {
        return new Person($hash['name'], $hash['age'], $hash['email']);
    }

    public function to_array(): array {
        return (array) $this;
    }

    public function greet() {
        echo "Hi! My name is $this->name, I'm $this->age years old, and my email is $this->email\n";
    }
}

class Database extends SQLite3 {
    // Sanitises the string fields of a Person and returns it as a new object.
    private static function sanitize_person(Person $person): Person {
        $name = SQLite3::escapeString($person->name);
        $age = $person->age;
        $email = SQLite3::escapeString($person->email);

        return new Person($name, $age, $email);
    }

    public function __construct() {
        $this->open("people.db");// or die("Could not open a connection to people.db");
        $this->exec(<<<END_SQL
        CREATE TABLE IF NOT EXISTS "People" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            "name" VARCHAR, 
            "age" INTEGER, 
            "email" VARCHAR
        );
        END_SQL);
    }

    public function begin() {
        $this->exec('BEGIN');
    }

    public function commit() {
        $this->exec('COMMIT');
    }

    // Creates a new person in the database, and returns its ID
    public function create(Person $person): int {
        $sanitized = self::sanitize_person($person);

        $this->exec(<<<END_SQL
        INSERT INTO "People" ("name", "age", "email")
        VALUES ('$sanitized->name', $sanitized->age, '$sanitized->email');
        END_SQL);

        return $this->lastInsertRowID(); // placeholder for now
    }

    // Reads a person's data in the database based on its ID
    public function read(int $id): ?Person {
        $query_result = $this->querySingle(<<<END_SQL
        SELECT * FROM "People"
        WHERE "id" = $id;
        END_SQL, true);

        if (count($query_result) == 0) {
            return NULL;
        }

        return Person::from_array($query_result);
    }

    // Updates a whole person all at once
    public function update_person(int $id, Person $person) {
        $sanitized = self::sanitize_person($person);

        $this->exec(<<<END_SQL
        UPDATE "People"
        SET "name" = '$sanitized->name', 
            "age" = $sanitized->age, 
            "email" = '$sanitized->email'
        WHERE "id" = $id;
        END_SQL);
    }

    public function delete(int $id) {
        $this->exec(<<<END_SQL
        DELETE FROM "People"
        WHERE "id" = $id;
        END_SQL);
    }

    // Convenient self-closing wrapper
    public static function do(callable $body) {
        $db = new Database();
        $db->begin();
        $body($db);
        $db->commit();
        $db->close();
    }
}

$pid = 0;

Database::do(function($db) use (&$pid) {
    $pid = $db->create(new Person('Niko', 28, 'slench102@gmail.com'));
    $person = $db->read($pid);
    $person->greet();
});

Database::do(function($db) use (&$pid) {
    $db->update_person($pid, new Person('Niko', 28, 'contact@wausoft.eu'));
});

Database::do(function($db) use (&$pid) {
    $person = $db->read($pid);
    $person->greet(); 
});