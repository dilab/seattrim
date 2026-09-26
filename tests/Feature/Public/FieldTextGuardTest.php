<?php

use Illuminate\Support\Facades\File;

/**
 * Keeps hero text and buttons on the marketing field in the shared components (<x-public.page-hero>,
 * <x-public.field-button>) so type, spacing and contrast stay consistent. In page files, white text is
 * only allowed on a solid dark background declared on the same line.
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
