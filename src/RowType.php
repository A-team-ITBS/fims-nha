<?php

namespace PHPMaker2024\FIMS;

// RowType
enum RowType: int
{
    case HEADER = 0;
    case VIEW = 1;
    case ADD = 2;
    case EDIT = 3;
    case SEARCH = 4;
    case MASTER = 5;
    case AGGREGATEINIT = 6;
    case AGGREGATE = 7;
    case DETAIL = 8;
    case TOTAL = 9;
    case PREVIEW = 10;
    case PREVIEWFIELD = 11;
}
