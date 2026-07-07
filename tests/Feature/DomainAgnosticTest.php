<?php

it('names no host domain types anywhere in src', function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS)
    );

    $offenders = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        foreach (['Fragment', 'Silo', 'Tag'] as $hostType) {
            if (preg_match('/\b'.$hostType.'\b/', $contents)) {
                $offenders[] = $file->getFilename().' references '.$hostType;
            }
        }
    }

    expect($offenders)->toBe([]);
});
