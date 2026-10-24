<?php
/** School logos belong to an isolated persistent directory, never the image layer. */
function SchoolSafeSchoolLogoUploadDirectory(): string
{
    return 'assets/school-logos/';
}

/** Keep legacy read compatibility for schools whose logo predates persistent storage. */
function SchoolSafeSchoolLogoPath(int $school): string
{
    if ($school < 1) return '';
    foreach ([SchoolSafeSchoolLogoUploadDirectory().'school_logo_'.$school.'.jpg',
              'assets/school_logo_'.$school.'.jpg'] as $path) {
        if (is_file($path) && is_readable($path) && filesize($path) > 0
            && ($image = @getimagesize($path)) && ($image[2] ?? 0) === IMAGETYPE_JPEG) return $path;
    }
    return '';
}

function SchoolSafeSchoolLogoUrl(int $school): string
{
    $path = SchoolSafeSchoolLogoPath($school);
    if ($path === '') return '';
    return '/'.$path.'?v='.rawurlencode((string)filemtime($path));
}
