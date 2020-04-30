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
    public function __construct() {
        $this->open("people.db");
        $this->exec(<<<END_SQL
        CREATE TABLE IF NOT EXISTS "People" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            "name" VARCHAR, 
            "age" INTEGER, 
            "email" VARCHAR
        );
        END_SQL);
    }

    public function begin(): bool {
        return $this->exec('BEGIN');
    }

    public function commit(): bool {
        return $this->exec('COMMIT');
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

class PeopleTable {
    // Sanitises the string fields of a Person and returns it as a new object.
    private static function sanitize(Person $person): Person {
        $name = SQLite3::escapeString($person->name);
        $age = $person->age;
        $email = SQLite3::escapeString($person->email);

        return new Person($name, $age, $email);
    }

    // Creates a new person in the database, and returns its ID
    public static function create(SQLite3 $db, Person $person): int {
        $sanitized = static::sanitize($person);

        $db->exec(<<<END_SQL
        INSERT INTO "People" ("name", "age", "email")
        VALUES ('$sanitized->name', $sanitized->age, '$sanitized->email');
        END_SQL);

        return $db->lastInsertRowID();
    }

    // Reads a person's data in the database based on its ID
    public static function read(SQLite3 $db, int $id): ?Person {
        $query_result = $db->querySingle(<<<END_SQL
        SELECT * FROM "People"
        WHERE "id" = $id;
        END_SQL, true);

        if (count($query_result) == 0) {
            return NULL;
        }

        return Person::from_array($query_result);
    }

    // Updates a whole person all at once
    // Individual update methods have intentionally been left out for the example
    public static function update(SQLite3 $db, int $id, Person $person): bool {
        $sanitized = static::sanitize($person);

        return $db->exec(<<<END_SQL
        UPDATE "People"
        SET "name" = '$sanitized->name', 
            "age" = $sanitized->age, 
            "email" = '$sanitized->email'
        WHERE "id" = $id;
        END_SQL);
    }

    public static function delete(SQLite3 $db, int $id): bool {
        return $db->exec(<<<END_SQL
        DELETE FROM "People"
        WHERE "id" = $id;
        END_SQL);
    }   
}

$pid = 0;

Database::do(function($db) use (&$pid) {
    $pid = PeopleTable::create($db, new Person('Niko', 28, 'slench102@gmail.com'));
    echo "New person created with ID $pid\n";
    $person = PeopleTable::read($db, $pid);
    $person->greet();
});

// testing out multiple separate transactions
Database::do(fn($db) => PeopleTable::update($db, $pid, new Person('Niko', 28, 'contact@wausoft.eu')));

Database::do(function($db) use (&$pid) {
    $person = PeopleTable::read($db, $pid);
    $person->greet(); 
});