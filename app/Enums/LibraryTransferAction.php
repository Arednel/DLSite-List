<?php

namespace App\Enums;

enum LibraryTransferAction: string
{
    case Apply = 'apply';
    case Cancel = 'cancel';
    case Cleanup = 'cleanup';
    case WithoutImages = 'without_images';
    case ContinueExport = 'continue_export';
    case Refresh = 'refresh';
    case Retry = 'retry';
}
