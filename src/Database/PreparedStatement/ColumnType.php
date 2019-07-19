<?php


namespace Kinikit\Persistence\Database\PreparedStatement;


class ColumnType {

    const SQL_VARCHAR = 12;
    const SQL_TINYINT = -6;
    const SQL_SMALLINT = 5;
    const SQL_INT = 4;
    const SQL_INTEGER = 4;
    const SQL_BIGINT = -5;
    const SQL_FLOAT = 6;
    const SQL_DOUBLE = 8;
    const SQL_REAL = 7;
    const SQL_DECIMAL = 3;
    const SQL_DATE = 9;
    const SQL_TIME = 10;
    const SQL_TIMESTAMP = 11;
    const SQL_BLOB = 99;
    const SQL_UNKNOWN = 0;

}
