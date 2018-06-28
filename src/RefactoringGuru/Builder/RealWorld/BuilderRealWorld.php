<?php

namespace RefactoringGuru\Builder\RealWorld;

/**
 * EN: Builder Design Pattern
 *
 * Intent: Separate the construction of a complex object from its representation
 * so that the same construction process can create different representations.
 *
 * Example: One of the best applications of the Builder pattern is an SQL query
 * builder. The Builder interface defines the common steps required to build a
 * generic SQL query. On the other hand, Concrete Builders, corresponding to
 * different SQL dialects, implement these steps by returning parts of SQL
 * queries that can be executed in a particular database engine.
 *
 * RU: Паттерн Строитель
 *
 * Назначение: Отделяет построение сложного объекта от его представления так, 
 * что один и тот же процесс построения может создавать разные представления объекта.
 *
 * Пример: Одним из лучших приложений паттерна Строителя является построитель
 * запросов SQL. Интерфейс Строителя определяет общие шаги, необходимые для построения
 * основного SQL-запроса. В тоже время Конкретные Строители, соответствующие
 * различным диалектам SQL, реализуют эти шаги, возвращая части SQL-запросов, 
 * которые могут быть выполнены в данном движке базы данных.
 */

/**
 * EN:
 * The Builder interface declares a set of methods to assemble an SQL query.
 *
 * All of the construction steps are returning the current builder object to
 * allow chaining: $builder->select(...)->where(...).
 *
 * RU:
 * Интерфейс Строителя объявляет набор методов для сборки SQL-запроса.
 *
 * Все шаги построения возвращают текущий объект строителя, чтобы обеспечить 
 * цепочку: $builder->select(...)->where(...).
 */
interface SQLQueryBuilder
{
    public function select(string $table, array $fields): SQLQueryBuilder;

    public function where(string $field, string $value, string $operator = '='): SQLQueryBuilder;

    public function limit(int $start, int $offset): SQLQueryBuilder;

    // EN: +100 other SQL syntax methods...
    //
    // RU: +100 других методов синтаксиса SQL...

    public function getSQL(): string;
}

/**
 * EN:
 * Each Concrete Builder corresponds to a specific SQL dialect and may implement
 * the builder steps a little bit differently from the others.
 *
 * This Concrete Builder can build SQL queries compatible with MySQL.
 *
 * RU: Каждый Конкретный Строитель соответствует определенному диалекту SQL
 * и может реализовать шаги построения немного иначе, чем другие.
 *
 * Этот Конкретный Строитель может создавать SQL-запросы, совместимые с MySQL.
 */
class MysqlQueryBuilder implements SQLQueryBuilder
{
    protected $query;

    protected function reset()
    {
        $this->query = new \stdClass();
    }

    /**
     * EN:
     * Build a base SELECT query.
     *
     * RU:
     * Построение базового запроса SELECT.
     */
    public function select(string $table, array $fields): SQLQueryBuilder
    {
        $this->reset();
        $this->query->base = "SELECT " . implode(", ", $fields) . " FROM " . $table;
        $this->query->type = 'select';

        return $this;
    }

    /**
     * EN:
     * Add a WHERE condition.
     *
     * RU:
     * Добавление условия WHERE.
     */
    public function where(string $field, string $value, string $operator = '='): SQLQueryBuilder
    {
        if (!in_array($this->query->type, ['select', 'update'])) {
            throw new \Exception("WHERE can only be added to SELECT OR UPDATE");
        }
        $this->query->where[] = "$field $operator '$value'";

        return $this;
    }

    /**
     * EN:
     * Add a LIMIT constraint.
     *
     * RU:
     * Добавление ограничения LIMIT.
     */
    public function limit(int $start, int $offset): SQLQueryBuilder
    {
        if (!in_array($this->query->type, ['select'])) {
            throw new \Exception("LIMIT can only be added to SELECT");
        }
        $this->query->limit = " LIMIT " . $start . ", " . $offset;

        return $this;
    }

    /**
     * EN:
     * Get the final query string.
     *
     * RU:
     * Получение окончательной строки запроса.
     */
    public function getSQL(): string
    {
        $query = $this->query;
        $sql = $query->base;
        if (!empty($query->where)) {
            $sql .= " WHERE " . implode(' AND ', $query->where);
        }
        if (isset($query->limit)) {
            $sql .= $query->limit;
        }
        $sql .= ";";
        return $sql;
    }
}

/**
 * EN:
 * This Concrete Builder is compatible with PostgreSQL. While Postgres is very
 * similar to Mysql, it still has several differences. To reuse the common code,
 * we extend it from the MySQL builder, while overriding some of the building
 * steps.
 *
 * RU:
 * Этот Конкретный Строитель совместим с PostgreSQL. Хотя Postgres очень похож на Mysql,
 * в нем все же есть ряд отличий. Чтобы повторно использовать общий код, мы расширяем его
 * от MySQL builder, переопределяя некоторые шаги построения.
 */
class PostgresQueryBuilder extends MysqlQueryBuilder
{
    /**
     * Among other things, PostgresSQL has slightly different LIMIT syntax.
     */
    public function limit(int $start, int $offset): SQLQueryBuilder
    {
        parent::limit($start, $offset);

        $this->query->limit = " LIMIT " . $start . " OFFSET " . $offset;

        return $this;
    }

    // + tons of other overrides...
}


/**
 * Note that the client code uses the builder object directly. A designated
 * Director class is not necessary in this case, because the client code needs
 * different queries almost every time, so the sequence of the construction
 * steps cannot be easily reused.
 *
 * Since all our query builders create products of the same type (which is a
 * string), we can interact with all builders using their common interface.
 * Later, if we implement a new Builder class, we will be able to pass its
 * instance to the existing client code without breaking it thanks to the
 * SQLQueryBuilder interface.
 */
function clientCode(SQLQueryBuilder $queryBuilder)
{
    // ...

    $query = $queryBuilder
        ->select("users", ["name", "email", "password"])
        ->where("age", 18, ">")
        ->where("age", 30, "<")
        ->limit(10, 20)
        ->getSQL();

    print($query);

    // ...
}


/**
 * The application selects the proper query builder type depending on a current
 * configuration or the environment settings.
 */
// if ($_ENV['database_type'] == 'postgres') {
//     $builder = new PostgresQueryBuilder(); } else {
//     $builder = new MysqlQueryBuilder(); }
//
// clientCode($builder);


print("Testing MySQL query builder:\n");
clientCode(new MysqlQueryBuilder());

print("\n\n");

print("Testing PostgresSQL query builder:\n");
clientCode(new PostgresQueryBuilder());
