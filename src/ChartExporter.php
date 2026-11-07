<?php

namespace PHPMaker2024\FIMS;

/**
 * Chart exporter class
 */
class ChartExporter
{
    // Export
    public function __invoke()
    {
        global $Language;
        $json = Post("charts", "[]");
        $charts = json_decode($json);
        $files = [];
        foreach ($charts as $chart) {
            $img = false;
            // Charts base64
            if ($chart->streamType == "base64") {
                try {
                    $img = base64_decode(preg_replace('/^data:image\/\w+;base64,/', "", $chart->stream));
                } catch (\Throwable $e) {
                    return $this->serverError($e->getMessage());
                }
            }
            if ($img === false) {
                return $this->serverError(str_replace(["%t", "%e"], [$chart->streamType, $chart->chartEngine], $Language->phrase("ChartExportError1")));
            }

            // Save the file
            $filename = $chart->fileName;
            if ($filename == "") {
                return $this->serverError($Language->phrase("ChartExportError2"));
            }
            $path = UploadTempPath();
            if (!file_exists($path)) {
                return $this->serverError($Language->phrase("ChartExportError3"));
            }
            if (!is_writable($path)) {
                return $this->serverError($Language->phrase("ChartExportError4"));
            }
            $filepath = IncludeTrailingDelimiter($path, true) . $filename;
            file_put_contents($filepath, $img);
            $files[] = $filename;
        }

        // Write success response
        WriteJson(["success" => true, "files" => $files]);
        return true;
    }

    // Send server error
    protected function serverError($msg)
    {
        WriteJson(["success" => false, "error" => $msg]);
        return false;
    }
}
