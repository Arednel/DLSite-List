<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DLSiteScraperController extends Controller
{
    public function Scrape()
    {
        $storageDir = storage_path();

        // Run DLSiteScraper download
        $path = base_path('python/DLSiteScraper.py');
        $modulesPath = base_path('python\modules');

        $process = new Process(['py', $path, $modulesPath, $storageDir]);

        // Set infinite timeout
        $process->setTimeout(0);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
