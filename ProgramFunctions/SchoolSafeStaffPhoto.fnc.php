<?php
/** Return only the connected staff member's native JPEG, with Rosario's own-year rollover fallback. */
function SchoolSafeStaffPhotoUrl(string $picturesPath, int $year, int $staffId, int $rolloverId = 0): string
{
    if ($year < 2 || $staffId < 1) return '';
    foreach ([[$year,$staffId],[$year-1,$rolloverId]] as [$photoYear,$photoId]) {
        if ($photoId < 1) continue;
        // Native uploads include an unpredictable suffix. Never guess another person's ID.
        $paths=glob($picturesPath.$photoYear.'/'.$photoId.'.*jpg') ?: [];
        foreach (array_reverse($paths) as $path) {
            if (is_file($path) && is_readable($path) && filesize($path)>0
                && ($image=@getimagesize($path)) && ($image[2]??0)===IMAGETYPE_JPEG) return $path;
        }
    }
    return '';
}
