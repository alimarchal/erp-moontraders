<?php

namespace App\Enums;

/**
 * The layouts a page can be shown in. Classic is the page as it was; A to I are the
 * redesigns (not every page offers every one). A super admin picks one per page on Settings → Appearance.
 */
enum PageLayout: string
{
    case Classic = 'classic';
    case OptionA = 'a';
    case OptionB = 'b';
    case OptionC = 'c';
    case OptionD = 'd';
    case OptionE = 'e';
    case OptionF = 'f';
    case OptionG = 'g';
    case OptionH = 'h';
    case OptionI = 'i';
}
