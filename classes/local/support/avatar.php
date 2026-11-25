<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_tarsuscoach\local\support;

/**
 * The assistant's avatar: the uploaded picture, scaled down and kept as a
 * data URI, so the coach shows it without another request.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class avatar {
    /** Longest side of the stored picture, in pixels. */
    public const SIZE = 160;

    /** Largest picture accepted, in bytes. */
    public const MAX_BYTES = 2097152;

    /**
     * The picture in the system's avatar file area (the setting's upload)
     * becomes the avatar; no picture removes it.
     */
    public static function from_setting(): void {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'mod_tarsuscoach', 'robotavatar', 0, 'sortorder, id', false);
        $file = reset($files);
        if (!$file) {
            config::set('robot_avatar', '');
            return;
        }
        $temp = $file->copy_content_to_temp('mod_tarsuscoach', 'avatar_');
        $uri = $temp ? self::data_uri($temp) : null;
        if ($temp) {
            @unlink($temp);
        }
        if ($uri === null) {
            \core\notification::error(get_string('error_avatar', 'mod_tarsuscoach'));
            return;
        }
        config::set('robot_avatar', $uri);
    }

    /**
     * An image file, downscaled and encoded as a data URI; null when it is
     * no usable image.
     *
     * @param string $path The file.
     * @return string|null
     */
    public static function data_uri(string $path): ?string {
        if (!is_file($path) || filesize($path) > self::MAX_BYTES) {
            return null;
        }
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }
        $mime = (string) ($info['mime'] ?? '');
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return null;
        }
        $data = (string) file_get_contents($path);
        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($data);
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $scale = min(1, self::SIZE / max(1, max($width, $height)));
                $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefill($target, 0, 0, $transparent);
                imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
                ob_start();
                imagepng($target, null, 8);
                $png = (string) ob_get_clean();
                // No imagedestroy(): it does nothing since PHP 8.0 and is
                // deprecated in PHP 8.5; the images are freed on return.
                if ($png !== '') {
                    return 'data:image/png;base64,' . base64_encode($png);
                }
            }
        }
        if (strlen($data) > 300000) {
            return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
