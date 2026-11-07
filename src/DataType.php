<?php

namespace PHPMaker2024\FIMS;

// DataType
enum DataType: int
{
    case NUMBER = 1;
    case DATE = 2;
    case STRING = 3;
    case BOOLEAN = 4;
    case MEMO = 5;
    case BLOB = 6;
    case TIME = 7;
    case GUID = 8;
    case XML = 9;
    case BIT = 10;
    case OTHER = 11;
}
