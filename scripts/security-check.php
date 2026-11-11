<?php

// A small regression guard, not a replacement for a full secret scanner.
$root = dirname(__DIR__);
$failures = [];
$tracked = [];
exec('git ls-files --cached --others --exclude-standard', $tracked, $status);
if ($status !== 0) {
    fwrite(STDERR, "Cannot inspect tracked files\n");
    exit(1);
}
foreach ($tracked as $file) {
    if (! is_file($root.'/'.$file)) {
        continue;
    }
    if (preg_match('~(^|/)\.env(?:\.|$)~', $file) && $file !== '.env.example') {
        $failures[] = "$file: environment file tracked";
    }
    if (! preg_match('/\.(php|yml|yaml|md|txt|sh|json|example)$/', $file)) {
        continue;
    }
    $content = file_get_contents($root.'/'.$file);
    if (preg_match('/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/', $content)) {
        $failures[] = "$file: private key material";
    }
    if (preg_match('/APP_KEY=base64:[A-Za-z0-9+\/=]{30,}/', $content)) {
        $failures[] = "$file: hardcoded application key";
    }
}
foreach (glob($root.'/.github/workflows/*.yml') as $file) {
    if (! in_array(basename($file), ['tests.yml', 'deploy.yml', 'staging.yml'], true)) {
        $failures[] = basename($file).': unreviewed legacy workflow';
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php' && $file->getPathname() !== $root.'/public'.DIRECTORY_SEPARATOR.'index.php') {
        $failures[] = $file->getFilename().': unexpected public PHP entry point';
    }
}
if ($failures) {
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}
echo "Security regression checks passed.\n";
