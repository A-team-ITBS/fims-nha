<?php

namespace PHPMaker2024\FIMS;

// RowTotal
enum RowTotal: int
{
    case HEADER = 0;
    case FOOTER = 1;
    case SUM = 2;
    case AVG = 3;
    case MIN = 4;
    case MAX = 5;
    case CNT = 6;
}
