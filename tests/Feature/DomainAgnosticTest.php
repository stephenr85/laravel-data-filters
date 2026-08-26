<?php

/**
 * `rushing/*` is an open foundation under the ADR-0092 vendor seam: it may be composed by the paid
 * Splicewire engine above it and must know nothing about it. This is that seam, guarded.
 *
 * The guard used to grep raw file contents for `Fragment` / `Silo` / `Tag`, which cannot tell a
 * dependency from a sentence. Both hits it produced were docblock prose — one an illustrative
 * `#[Filterable(..., model: Fragment::class)]` snippet, one a line of extraction history recording
 * which cone this operator was lifted out of. Neither creates an edge: composer does not read
 * comments, and the autoloader does not resolve them.
 *
 * That matters in both directions. A word-list over prose is *noisy* — `\bTag\b` matches the English
 * word — and a noisy guard gets silenced rather than obeyed. It is also *weak*: it can be satisfied
 * by renaming a comment, which is the one repair that changes nothing real. So the predicate now runs
 * over code tokens with comments stripped, and is paired with the check the word list was only ever
 * approximating — that `src/` imports no symbol from a domain namespace above this package.
 */
$sourceFiles = function (): array {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS)
    );

    $paths = [];

    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            $paths[$file->getPathname()] = $file->getFilename();
        }
    }

    return $paths;
};

/** The file's source with every comment and docblock removed, so only what executes is matched. */
$codeOnly = function (string $path): string {
    $code = '';

    foreach (token_get_all(file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
};

it('names no host domain types in code', function () use ($sourceFiles, $codeOnly) {
    $offenders = [];

    foreach ($sourceFiles() as $path => $filename) {
        $code = $codeOnly($path);

        foreach (['Fragment', 'Silo', 'Tag'] as $hostType) {
            if (preg_match('/\b'.$hostType.'\b/', $code)) {
                $offenders[] = $filename.' references '.$hostType;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('imports no symbol from a namespace above this package', function () use ($sourceFiles, $codeOnly) {
    $offenders = [];

    foreach ($sourceFiles() as $path => $filename) {
        preg_match_all('/^\s*use\s+([A-Za-z0-9_\\\\]+)/m', $codeOnly($path), $matches);

        foreach ($matches[1] as $import) {
            $root = strtok(ltrim($import, '\\'), '\\');

            // `Schemastud\` is deliberately absent: `schemastud/laravel-data-schemas` is a declared
            // `require` of this package and sits beside/below it, not above. The seam this guards is
            // the paid engine (`Splicewire\`) and the consuming host (`App\`).
            if (in_array($root, ['Splicewire', 'App'], true)) {
                $offenders[] = $filename.' imports '.$import;
            }
        }
    }

    expect($offenders)->toBe([]);
});
