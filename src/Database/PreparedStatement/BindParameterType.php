<?php

namespace Kinikit\Persistence\Database\PreparedStatement;

enum BindParameterType {
    case TYPE_INT;
    case TYPE_FLOAT;
    case TYPE_BOOL;
    case TYPE_STRING;
    case TYPE_BLOB;
    case TYPE_DATE;
    case TYPE_DATETIME;
    case TYPE_ARRAY;
    case TYPE_OTHER;
}