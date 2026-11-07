<?php

namespace PHPMaker2024\FIMS;

// RowSummary
enum RowSummary: int
{
    case DETAIL = 0;
    case GROUP = 1;
    case PAGE = 2;
    case GRAND = 3;
}
