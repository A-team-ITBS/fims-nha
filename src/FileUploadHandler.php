<?php

namespace PHPMaker2024\FIMS;

/**
 * Class for file upload
 */
class FileUploadHandler
{
    public static $options = [];

    // Perform file upload
    public function __invoke()
    {
        global $Language, $TokenName, $TokenNameKey, $TokenValue, $TokenValueKey;
        $Language = Container("app.language");

        // Set up upload parameters
        $uploadId = Param("id", "");
        $uploadTable = Param("table", "");
        $sessionIdEncrypted = Param("session", "");
        $sessionId = Decrypt($sessionIdEncrypted);
        if (EmptyString($sessionIdEncrypted) || EmptyString($sessionId)) {
            WriteJson(["files" => [["error" => "Invalid session"]]]);
            return false;
        }
        $acceptFileTypes = Param("acceptFileTypes", "");
        $arExt = explode(",", $acceptFileTypes);
        $allowedExt = Config("UPLOAD_ALLOWED_FILE_EXT");
        if ($allowedExt != "") {
            $arAllowedExt = explode(",", $allowedExt);
            $acceptFileTypes = implode(",", array_intersect($arExt, $arAllowedExt)) ?: $allowedExt; // Make sure $acceptFileTypes is a subset of $allowedExt
        } elseif ($acceptFileTypes == "") {
            $acceptFileTypes = "[\s\S]+"; // Allow all file types
        }
        $fileTypes = '/\\.(' . str_replace(",", "|", $acceptFileTypes) . ')$/i';
        $maxFileSize = Param("maxFileSize");
        if ($maxFileSize != null) {
            $maxFileSize = (int)$maxFileSize;
        }
        $maxNumberOfFiles = Param("maxNumberOfFiles");
        if ($maxNumberOfFiles != null) {
            $maxNumberOfFiles = (int)$maxNumberOfFiles;
            if ($maxNumberOfFiles < 1) {
                $maxNumberOfFiles = null;
            }
        }
        $params = ["rnd" => Random()];
        GenerateCsrf();
        if ($TokenNameKey && $TokenName) {
            $params[$TokenNameKey] = $TokenName;
        }
        if ($TokenValueKey && $TokenValue) {
            $params[$TokenValueKey] = $TokenValue;
        }
        if ($uploadId != "") {
            $params["id"] = $uploadId;
        }
        if ($uploadTable != "") {
            $params["table"] = $uploadTable;
        }
        if ($sessionId != "") {
            $params["session"] = $sessionIdEncrypted; // Add id/table/session for display and delete
        }
        $url = UrlFor(Config("API_JQUERY_UPLOAD_ACTION"), [], $params);
        $uploaddir = UploadTempPathRoot();
        $uploadurl = UploadTempPathRoot(false);
        $inlineFileTypes = array_merge(explode(",", Config("IMAGE_ALLOWED_FILE_EXT")), (Config("EMBED_PDF") || !Config("DOWNLOAD_PDF_FILE")) ? ["pdf"] : []);
        $options = array_replace_recursive([
            "param_name" => $uploadId,
            "delete_type" => "POST", // POST or DELETE, set this option to POST for server not supporting DELETE requests
            "user_dirs" => true,
            "download_via_php" => 1,
            "script_url" => $url,
            "upload_dir" => $uploaddir,
            "upload_url" => $uploadurl,
            "max_file_size" => $maxFileSize,
            "max_number_of_files" => $maxNumberOfFiles,
            "accept_file_types" => $fileTypes,
            "inline_file_types" => '/\.(' . implode("|", $inlineFileTypes) . ')$/i',
            "image_library" => 0, // Set to 0 to use the GD library to scale and orient images
            "image_versions" => [
                "" => [
                    "auto_orient" => true // Automatically rotate images based on EXIF meta data
                ],
                Config("UPLOAD_THUMBNAIL_FOLDER") => [
                    "max_width" => Config("UPLOAD_THUMBNAIL_WIDTH"),
                    "max_height" => Config("UPLOAD_THUMBNAIL_HEIGHT"),
                    "jpeg_quality" => 100,
                    "png_quality" => 9
                ]
            ]
        ], self::$options);
        $error_messages = [
            1 => $Language->phrase("UploadError1"),
            2 => $Language->phrase("UploadError2"),
            3 => $Language->phrase("UploadError3"),
            4 => $Language->phrase("UploadError4"),
            6 => $Language->phrase("UploadError6"),
            7 => $Language->phrase("UploadError7"),
            8 => $Language->phrase("UploadError8"),
            'post_max_size' => $Language->phrase("UploadErrorPostMaxSize"),
            'max_file_size' => $Language->phrase("UploadErrorMaxFileSize"),
            'min_file_size' => $Language->phrase("UploadErrorMinFileSize"),
            'accept_file_types' => $Language->phrase("UploadErrorAcceptFileTypes"),
            'max_number_of_files' => $Language->phrase("UploadErrorMaxNumberOfFiles"),
            'max_width' => $Language->phrase("UploadErrorMaxWidth"),
            'min_width' => $Language->phrase("UploadErrorMinWidth"),
            'max_height' => $Language->phrase("UploadErrorMaxHeight"),
            'min_height' => $Language->phrase("UploadErrorMinHeight")
        ];
        if (ob_get_length()) {
            ob_end_clean();
        }
        $upload_handler = new CustomUploadHandler($uploadId, $uploadTable, $sessionId, $options, true, $error_messages);
        return true;
    }
}
