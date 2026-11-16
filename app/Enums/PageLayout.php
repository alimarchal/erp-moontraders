<?php

namespace App\Enums;

/**
 * The layouts a page can be shown in. Classic is the page as it was; A and B are the
 * redesigns. A super admin picks one per page on Settings → Appearance.
 */
enum PageLayout: string
{
    case Classic = 'classic';
    case OptionA = 'a';
    case OptionB = 'b';
}
