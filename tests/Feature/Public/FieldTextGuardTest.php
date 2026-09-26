<?php

use Illuminate\Support\Facades\File;

/**
 * The marketing "light field" is pastel in its lower half, so raw white text on it fails contrast.
 * Page files must put field text through <x-public.page-hero> (which brings its own dark band) and
 * field buttons through <x-public.field-button>; white text is only allowed on a solid dark background
 * declared on the same line.
 */
test('page files never place raw white text on the light field', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views/public')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        foreach (explode("\n", File::get($file->getPathname())) as $index => $line) {
            if (preg_match('/\btext-white\b/', $line) !== 1) {
                continue;
            }

            if (preg_match('/\bbg-(ink-900|ink-700|mint-500|brand-500|brand-600)\b/', $line) === 1) {
                continue;
            }

            $offenders[] = $file->getRelativePathname().':'.($index + 1);
        }
    }

    expect($offenders)->toBe([], 'Raw text-white in page files (use x-public.page-hero or x-public.field-button): '.implode(', ', $offenders));
});
