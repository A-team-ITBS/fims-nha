<?php

namespace PHPMaker2024\FIMS;

// Allow
enum Allow: int
{
    case ADD = 1;
    case DELETE = 2;
    case EDIT = 4;
    case LIST = 8;
    case ADMIN = 16;
    case VIEW = 32;
    case SEARCH = 64;
    case IMPORT = 128;
    case LOOKUP = 256;
    case PUSH = 512;
    case EXPORT = 1024;
    case ALL = 2047;
}
