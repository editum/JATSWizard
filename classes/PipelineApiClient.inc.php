<?php
/**
 * @file classes/PipelineApiClient.inc.php
 *
 * Client for docxtojats-pipeline API
 */

class PipelineApiClient {
    private $baseUrl;

    public function __construct($baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Call /doc/tojats endpoint
     * @param string $docPath Absolute path to local docx
     * @param string $workdir Directory where to extract the zip response
     * @param array $options Additional options (e.g. normalize, removeSections)
     * @throws Exception
     */
    public function convertDocToJats($docPath, $workdir, $options = []) {
        $url = $this->baseUrl . '/doc/tojats';

        if (!file_exists($docPath)) {
            throw new Exception("Input file does not exist: " . $docPath);
        }

        $ch = curl_init();
        
        $postFields = [
            'doc_to_jats_form[inputFile]' => new CURLFile($docPath)
        ];

        if (!empty($options['frontXmlFile'])) {
            $postFields['doc_to_jats_form[frontXmlFile]'] = new CURLFile($options['frontXmlFile']);
        }
        if (!empty($options['bibliographyFile'])) {
            $postFields['doc_to_jats_form[bibliographyFile]'] = new CURLFile($options['bibliographyFile']);
        }
        if (!empty($options['removeSections'])) {
            $postFields['doc_to_jats_form[removeSections]'] = implode(' ', $options['removeSections']);
        }
        if (!empty($options['normalize'])) {
            $postFields['doc_to_jats_form[normalize]'] = '1';
        }
        if (!empty($options['automarkStyle'])) {
            $postFields['doc_to_jats_form[citationStyle]'] = $options['automarkStyle'];
        }
        if (!empty($options['automarkSetMixedCitations'])) {
            $postFields['doc_to_jats_form[setBibliographyMixedCitations]'] = '1';
        }
        if (!empty($options['automarkSetFiguresTitles'])) {
            $postFields['doc_to_jats_form[setFigureTitles]'] = '1';
        }
        if (!empty($options['automarkSetTablesTitles'])) {
            $postFields['doc_to_jats_form[setTableTitles]'] = '1';
        }
        if (!empty($options['automarkSetTitlesReferences'])) {
            $postFields['doc_to_jats_form[replaceTitlesWithReferences]'] = '1';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json, application/zip']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        JatsWizardPlugin::log('DEBUG', 'PipelineApiClient calling tojats', ['url' => $url, 'file' => $docPath]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            $parsed = @json_decode($response, true);
            $msg = $parsed ? ($parsed['errors'] ?? $response) : $response;
            if (is_array($msg)) $msg = json_encode($msg);
            throw new Exception("Pipeline API error ($httpCode): " . $msg);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'jats_zip_');
        file_put_contents($tmpZip, $response);

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) === true) {
            $zip->extractTo($workdir);
            $zip->close();
            unlink($tmpZip);
        } else {
            unlink($tmpZip);
            throw new Exception("Failed to open the returned ZIP file from Pipeline");
        }
    }

    /**
     * Call /doc/jatsPublisher endpoint
     * @param string $zipPath Absolute path to local zip file containing XML and images
     * @param string $workdir Directory where to extract the result zip
     * @throws Exception
     */
    public function publishJats($zipPath, $workdir) {
        $url = $this->baseUrl . '/doc/jatsPublisher';

        if (!file_exists($zipPath)) {
            throw new Exception("Zip file does not exist: " . $zipPath);
        }

        $ch = curl_init();
        
        $postFields = [
            'upload_zip_file_form[inputFile]' => new CURLFile($zipPath, 'application/zip', basename($zipPath))
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json, application/zip']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        JatsWizardPlugin::log('DEBUG', 'PipelineApiClient calling jatsPublisher', ['url' => $url, 'file' => $zipPath]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            $parsed = @json_decode($response, true);
            $msg = $parsed ? ($parsed['errors'] ?? $response) : $response;
            if (is_array($msg)) $msg = json_encode($msg);
            throw new Exception("Pipeline API error ($httpCode): " . $msg);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'jats_pub_zip_');
        file_put_contents($tmpZip, $response);

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) === true) {
            $zip->extractTo($workdir);
            $zip->close();
            unlink($tmpZip);
        } else {
            unlink($tmpZip);
            throw new Exception("Failed to open the returned ZIP file from Pipeline");
        }
    }
}
