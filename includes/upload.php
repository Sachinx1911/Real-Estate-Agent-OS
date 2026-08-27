<?php
/**
 * RE360 — image uploads.
 *
 * Phone cameras produce 4–8 MB files. Storing those untouched would fill the
 * hosting quota and make every dashboard load drag one down the wire, so each
 * image is re-encoded down to a sane size before it is saved.
 *
 * Re-encoding is also the security win: the saved file is drawn from scratch
 * by GD, so anything hidden inside the original (a PHP payload appended to a
 * JPEG is the classic trick) does not survive the round trip.
 */

const RE360_IMG_MAX_BYTES = 8 * 1024 * 1024;  // what we accept from the browser
// Square on both sides: project photos are shown in 1:1 slots, so a tall or
// square original must keep its resolution rather than be capped at 4:3.
const RE360_IMG_MAX_W     = 1600;             // what we keep on disk
const RE360_IMG_MAX_H     = 1600;
const RE360_IMG_QUALITY   = 82;

/**
 * Take one uploaded image and store it under uploads/<subdir>/.
 *
 * @param array  $file   one entry of $_FILES
 * @param string $subdir folder under uploads/ (e.g. 'projects')
 * @return array{ok:bool, path?:string, error?:string} path is web-relative
 */
function save_uploaded_image(array $file, string $subdir = 'misc'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was chosen.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        // INI_SIZE and FORM_SIZE are by far the most common, and "try smaller"
        // is the only useful advice for either.
        return ['ok' => false, 'error' => 'Upload failed — the file may be too large for the server.'];
    }
    if ($file['size'] > RE360_IMG_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image is larger than 8 MB. Please pick a smaller one.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'That was not a valid upload.'];
    }

    // Trust the file's contents, never its name or the browser's mime type.
    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        return ['ok' => false, 'error' => 'That file is not an image.'];
    }
    $type = $info[2];
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
    if (!in_array($type, $allowed, true)) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG, WebP or GIF image.'];
    }

    $dir = UPLOADS_PATH . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create the uploads folder. Check that /uploads is writable.'];
    }

    $name = bin2hex(random_bytes(8)) . '_' . time();

    // Without GD we cannot re-encode; keep the original rather than refuse the
    // upload, but only after getimagesize() has vouched for it.
    if (!function_exists('imagecreatefromjpeg')) {
        $ext  = image_type_to_extension($type, false);
        $dest = $dir . '/' . $name . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'Could not save the image. Check that /uploads is writable.'];
        }
        @chmod($dest, 0644);
        return ['ok' => true, 'path' => UPLOADS_URL . '/' . $subdir . '/' . $name . '.' . $ext];
    }

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
        default        => false,
    };
    if (!$src) {
        return ['ok' => false, 'error' => 'That image could not be read. Try re-saving it and upload again.'];
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(RE360_IMG_MAX_W / $w, RE360_IMG_MAX_H / $h, 1);   // never upscale
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    // Photos are saved as JPEG, which has no alpha — flatten transparency onto
    // white so a PNG logo does not come out with a black background.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $dest = $dir . '/' . $name . '.jpg';
    $ok = imagejpeg($dst, $dest, RE360_IMG_QUALITY);
    imagedestroy($src);
    imagedestroy($dst);

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not save the image. Check that /uploads is writable.'];
    }
    @chmod($dest, 0644);
    return ['ok' => true, 'path' => UPLOADS_URL . '/' . $subdir . '/' . $name . '.jpg'];
}

/**
 * Delete a previously stored upload.
 * Only paths inside uploads/ are touched, so a tampered database value cannot
 * reach anything else on disk.
 */
function delete_upload(?string $webPath): void
{
    if (!$webPath) return;
    $full = realpath(BASE_PATH . '/' . ltrim($webPath, '/'));
    $root = realpath(UPLOADS_PATH);
    if ($full && $root && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full)) {
        @unlink($full);
    }
}
