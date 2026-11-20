<?php

import('plugins.generic.jatsWizard.classes.WizardEngine');
import('plugins.generic.jatsWizard.classes.JatsHtmlExporter');
import('lib.pkp.classes.handler.PKPHandler');
import('classes.handler.Handler');
import('lib.pkp.classes.file.PrivateFileManager');
import('lib.pkp.classes.db.DAORegistry');

class WizardHandler extends Handler
{
    /** @var Submission * */
    public $submission;
    /** @var MarkupPlugin The Texture plugin */
    protected $_plugin;
    /** @var WizardEngine */
    protected $engine;
    /** @var SubmissionFile */
    protected $submissionFile;

    function __construct()
    {
        parent::__construct();
        $this->_plugin = PluginRegistry::getPlugin('generic', JATS_WIZARD_PLUGIN_NAME);

        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
            ['wizard', 'engine', 'unpackxml', 'unpackhtml', 'downloadhtmlzip', 'createGalleyForm', 'createGalley']
        );
        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            ['flipbookForm', 'createFlipbook', 'revalidateEpubForm', 'revalidateEpub']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }
    /**
     * Get the plugin.
     * @return WizardPlugin
     */
    function getPlugin()
    {
        return $this->_plugin;
    }

    private function getPublicationCitations()
    {
        if (!$this->submission) {
            return null;
        }

        $publicationId = $this->submission->getData('currentPublicationId');
        $publication = $publicationId ? Services::get('publication')->get($publicationId) : null;
        if (!$publication) {
            JatsWizardPlugin::log('WARNING', 'Current publication is unavailable while loading citation metadata.', [
                'submissionId' => $this->submission->getId(),
                'publicationId' => $publicationId,
            ]);
            return null;
        }

        $citations = $publication->getData('citationsRaw');
        JatsWizardPlugin::log('INFO', 'Loaded citation metadata for wizard.', [
            'submissionId' => $this->submission->getId(),
            'publicationId' => $publication->getId(),
            'citationLength' => strlen((string) $citations),
        ]);

        return $citations;
    }

    function initialize($request)
    {

        parent::initialize($request);


        $this->engine = new WizardEngine($request, $this->_plugin);
        $submissionFileId = $request->getUserVar('submissionFileId');
        $submissionId = $request->getUserVar('submissionId');

        $this->submissionId = $submissionId;
        if ($submissionFileId) {
            $this->submissionFile = Services::get('submissionFile')->get($submissionFileId);
        }
        if ($submissionId) {
            $this->submission = Services::get('submission')->get($submissionId);
            $this->engine->setSubmission($this->submission);
        }

        $this->setupTemplate($request);


        $wizardBaseUrl = $request->getBaseUrl() . '/' . $this->_plugin->getPluginPath() . '/wizard';
        //$this->wizardBaseUrl = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath();
        $wizardBaseDir = $this->_plugin->getPluginPath() . '/wizard';
        define('WIZARD_URL', $wizardBaseUrl);
        define('WIZARD_DIR', $wizardBaseDir);
    }

    public function wizard($args, $request)
    {

        if (!$this->submissionFile) {
            $request->redirect(null, 'index'); // Redirige a inicio si falta parámetro
            return;
        }

        if (!$this->submission) {
            $request->redirect(null, 'index'); // Redirige a inicio si falta parámetro
            return;
        }

        $wizardToken = $request->getUserVar('wizardToken');
        if (!$wizardToken) {
            $wizardToken = uniqid('jatswiz_');
            $request->redirect(null, null, 'wizard', null, array(
                'submissionFileId' => $request->getUserVar('submissionFileId'),
                'submissionId' => $request->getUserVar('submissionId'),
                'stageId' => $request->getUserVar('stageId'),
                'wizardToken' => $wizardToken
            ));
            return;
        }
        $this->engine->setWizardToken($wizardToken);

        $fileManager = new PrivateFileManager();
        $filePath = $fileManager->getBasePath() . '/' . $this->submissionFile->getData('path');
        //$this->engine->clean();
        $this->engine->ensureWorkdir($this->submissionFile->getData('submissionId'), $request->getUserVar('submissionFileId'));
        $this->engine->setSubmissionFile($filePath, $this->submissionFile->getLocalizedData('name'));

        $citations = $this->getPublicationCitations();
        $this->engine->startWizard($citations);
    }

    /**
     * Ruta central del wizard (equivalente al viejo index.php?op=...)
     */
    public function engine($args, $request)
    {
        try {
            $wizardToken = $request->getUserVar('wizardToken');
            if (!$wizardToken) {
                JatsWizardPlugin::log('ERROR', 'No wizardToken provided in engine request.');
                throw new Exception("Falta el identificador de sesión (wizardToken).");
            }
            $this->engine->setWizardToken($wizardToken);

            $workdir = $_SESSION['jatsWizardStates'][$wizardToken]['workdir'] ?? null;
            if (empty($_SESSION['jatsWizardStates'][$wizardToken]) || !$workdir || !file_exists($workdir . '/src/marked_data.json')) {
                JatsWizardPlugin::log('INFO', 'Session expired or invalid. Redirecting to workflow.');
                $request->redirect(null, 'workflow', 'index', $this->submission->getId(), '5');
                return;
            }
            
            if (!is_dir($workdir)) {
                JatsWizardPlugin::log('ERROR', 'Workdir does not exist', ['workdir' => $workdir]);
                throw new Exception("El directorio de trabajo de la sesión no existe. Vuelva a lanzar el asistente.");
            }
            
            if (!$this->submission) {
                JatsWizardPlugin::log('ERROR', 'Submission not loaded properly.');
                throw new Exception("El artículo no se cargó correctamente en el contexto del asistente.");
            }

        $op = $request->getUserVar('op');
        // get citationsRaw of summission
        //$citations = $this->submission->getCitationsRaw();
        switch ($op) {
            case 'ping':
                header('Content-Type: application/json');
                echo json_encode(['status' => 'ok', 'time' => time()]);
                return;
            case 'front':
                //header("Content-Type: application/xml; charset=UTF-8");
                echo $this->engine->generateFromXml(true);
                return;
            case 'start':
                $citations = $this->getPublicationCitations();
                if (trim((string) $citations) === '') {
                    JatsWizardPlugin::log('WARNING', 'Blocked conversion because publication metadata citations are empty.', [
                        'submissionId' => $this->submission->getId(),
                    ]);
                    $this->engine->startWizard($citations);
                    return;
                }
                if (!empty($opts = $request->getUserVar('opts'))) {
                    $this->engine->setOptions($request);
                }
                $this->engine->convert($citations);
                // redirect to engine after starting incluidng submissionFileId and submissionId
                $request->redirect(null, null, null, null, array(
                    'submissionFileId' => $request->getUserVar('submissionFileId'),
                    'submissionId' => $this->submission->getId(),
                    'wizardToken' => $wizardToken,
                ));
                break;
            case 'session':
                header('Content-Type: application/json');
                $sessionData = $_SESSION['jatsWizardStates'][$wizardToken] ?? [];
                unset($sessionData['workdir']);
                $sessionData['marked_data'] = $this->engine->getMarkedData();
                echo json_encode($sessionData);
                break;

            case 'upload_doc':
                if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
                    $postMaxSize = ini_get('post_max_size');
                    throw new Exception("El archivo excede el tamaño máximo permitido por la petición POST (post_max_size = {$postMaxSize} en php.ini). Aumente post_max_size y upload_max_filesize.");
                }

                if (!empty($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    switch ($_FILES['file']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                            $uploadMax = ini_get('upload_max_filesize');
                            throw new Exception("El archivo subido supera el límite permitido por PHP (upload_max_filesize = {$uploadMax} en php.ini).");
                        case UPLOAD_ERR_FORM_SIZE:
                            throw new Exception("El archivo supera el tamaño máximo permitido por el formulario.");
                        case UPLOAD_ERR_PARTIAL:
                            throw new Exception("El archivo solo se subió parcialmente.");
                        case UPLOAD_ERR_NO_FILE:
                            throw new Exception("No se seleccionó ningún archivo para subir.");
                        default:
                            throw new Exception("Error al subir el archivo (código PHP: " . $_FILES['file']['error'] . ").");
                    }
                }

                if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                    throw new Exception("No se ha recibido el archivo subido.");
                }

                $this->engine->uploadDoc($_FILES['file']);
                $this->engine->convert();
                $this->engine->startWizard();
                break;
            case 'pdf':
                $this->engine->generatePublication('pdf');
                header('Content-Type: application/pdf');
                readfile($this->engine->getWorkdir() . '/article.pdf');
                return;
            case 'html':
                $this->engine->generatePublication('html');
                header('Content-Type: text/html; charset=UTF-8');
                $html = file_get_contents($this->engine->getWorkdir() . '/article.html');
                // Replace every src="img/..." with the full URL to the image
                $html = preg_replace_callback('/src="([^"]+)"/', function ($matches) use ($wizardToken) {
                    return 'src="' . $_SESSION['jatsWizardStates'][$wizardToken]['engineBaseUrl'] . '&op=img&img=' . $matches[1] . '"';
                }, $html);
                // replace href="style.css" with full URL
                $html = str_replace('href="style.css"', 'href="' . $_SESSION['jatsWizardStates'][$wizardToken]['engineBaseUrl'] . '&op=css"', $html);
                echo $html;
                return;
            case 'reconvert':
                $this->engine->getMarkedData();
                if (!empty($request->getUserVar('secs'))) {
                    $this->engine->updateMarkedData([
                        'secs' => (array) json_decode($request->getUserVar('secs')),
                    ]);
                }
                if ($request->getUserVar('renamedSecs') !== null) {
                    $renamedSecs = array_filter((array) json_decode($request->getUserVar('renamedSecs')), function ($title) {
                        return trim((string) $title) !== '';
                    });
                    $this->engine->updateMarkedData([
                        'renamedSecs' => $renamedSecs,
                    ]);
                }
                if ($request->getUserVar('hiddenFiguresTables') !== null) {
                    $this->engine->updateMarkedData([
                        'hiddenFiguresTables' => (array) json_decode($request->getUserVar('hiddenFiguresTables')),
                    ]);
                }
                if ($request->getUserVar('renamedFiguresTables') !== null) {
                    $renamedFiguresTables = array_filter((array) json_decode($request->getUserVar('renamedFiguresTables')), function ($title) {
                        return trim((string) $title) !== '';
                    });
                    $this->engine->updateMarkedData([
                        'renamedFiguresTables' => $renamedFiguresTables,
                    ]);
                }
                if (!empty($request->getUserVar('csl'))) {
                    $this->engine->updateMarkedData([
                        'csl' => json_decode($request->getUserVar('csl')),
                    ]);
                }
                if (!empty($request->getUserVar('regenerateCsl'))) {
                    $citations = $this->getPublicationCitations();
                } else {
                    $citations = null;
                }

                $xml = $this->engine->convert($citations, !empty($request->getUserVar('debug')));
                header("Content-Type: application/xml; charset=UTF-8");
                echo $xml;
                break;
            case 'debug':
                $this->engine->convert(null, true);
                return;
            case 'css':
                header('Content-Type: text/css; charset=UTF-8');
                echo file_get_contents($this->engine->getWorkdir() . '/style.css');
                return;
            case 'xml':
                header('Content-Type: application/xml');
                echo file_get_contents($this->engine->getXmlPath());
                return;
            case 'csl':
                header('Content-Type: application/json; charset=UTF-8');
                $workdir = $this->engine->getWorkdir();
                if (file_exists($workdir . '/src/csl.json')) {
                    readfile($workdir . '/src/csl.json');
                } elseif (file_exists($workdir . '/article.json')) {
                    readfile($workdir . '/article.json');
                } else {
                    $marked = $this->engine->getMarkedData();
                    echo json_encode($marked['csl'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
                return;
            case 'download_zip':
            case 'workdir':
                $workdir = $this->engine->getWorkdir();
                if (!file_exists($workdir . '/src/front.xml')) {
                    try {
                        $this->engine->generateFromXml();
                    } catch (Exception $e) {
                        JatsWizardPlugin::log('WARNING', 'Could not generate front.xml for workdir zip', ['error' => $e->getMessage()]);
                    }
                }

                $marked = $this->engine->getMarkedData();
                if (!empty($marked['csl']) && !file_exists($workdir . '/src/csl.json')) {
                    file_put_contents($workdir . '/src/csl.json', json_encode($marked['csl'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                if (!file_exists($workdir . '/src/citations.ref') && !file_exists($workdir . '/src/csl.json') && $this->submission) {
                    $rawCitations = $this->getPublicationCitations();
                    if (!empty($rawCitations)) {
                        file_put_contents($workdir . '/src/citations.ref', $rawCitations);
                    }
                }

                $zipfile = $this->engine->zipWorkdir();
                if (!file_exists($zipfile)) {
                    throw new Exception("No se pudo generar el archivo ZIP del workdir.");
                }

                $nameData = $this->engine->getMarkedData('name');
                $base = pathinfo((string)$nameData, PATHINFO_FILENAME);
                $customFilename = $request->getUserVar('filename');
                $downloadName = $customFilename ?: (($base ? $base . '.workdir.zip' : 'workdir.zip'));

                while (ob_get_level()) {
                    ob_end_clean();
                }

                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . addslashes(basename($downloadName)) . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($zipfile));
                readfile($zipfile);
                return;
            case 'ojs_zip':
                $zipfile = $this->engine->zipWorkdir();
                $this->saveMark($zipfile, $request, true);
                break;
            case 'save_mark':
                $zipfile = $this->engine->zipWorkdir();
                $this->saveMark($zipfile, $request, false);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success']);
                return;
            case 'markedData':
                header('Content-Type: application/json');
                $markedData = $this->engine->getMarkedData();
                echo json_encode($markedData, JSON_PRETTY_PRINT);
                return;
            case 'img':
                $img = basename($request->getUserVar('img'));
                $path = $this->engine->getImagePath($img);

                if (!file_exists($path)) {
                    throw new Exception("Image not found: " . $img);
                }

                $type = mime_content_type($path);
                header("Content-Type: $type");
                readfile($path);
                return;

            case 'preview':
                return $this->engine->preview();
            case 'download_doc':
                $docx = $this->engine->getDocxPath();
                header('Content-Description: File Transfer');
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Disposition: attachment; filename="' . $this->engine->getMarkedData('name') . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($docx));
                readfile($docx);
                return;
            case 'clean-csl':
                $this->engine->cleanCslData();
                return;
            case 'clean':
                $this->engine->clean();
                $request->redirect(null, 'workflow', 'access', $this->submission->getId());
                break;

            default:
                $this->engine->startWizard($this->getPublicationCitations());
            }
        } catch (Exception $e) {
            JatsWizardPlugin::log('ERROR', 'Wizard Engine Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            $op = $request->getUserVar('op');
            if (empty($op) || in_array($op, ['start', 'upload_doc', 'clean'])) {
                $GLOBALS['JATS_ERROR'] = $e->getMessage();
                $citations = $this->getPublicationCitations();
                $this->engine->startWizard($citations, true);
            } else {
                header('HTTP/1.1 500 Internal Server Error');
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => $e->getMessage()]);
            }
        }
    }

    /* ===============================
     *       OPERACIONES INTERNAS
     * =============================== */




    function unpackxml($args, $request)
    {
        return $this->unpack('xml', $request);
    }

    /**
     * Imports the article as a native OJS HTML galley file (index HTML + dependent images).
     */
    function unpackhtml($args, $request)
    {
        $submissionFileId = $request->getUserVar('submissionFileId');
        $package = $this->_buildHtmlPackage($request, $submissionFileId . '.html', $submissionFileId . '.epub');
        // _import_dir() redirects and exits, so the temporary files are removed at shutdown
        register_shutdown_function(function () use ($package) {
            (new PrivateFileManager())->rmtree($package['root']);
        });
        return $this->_import_dir($package['outDir'], $request, 'html');
    }

    /**
     * Downloads a ZIP with index.html and the images/ folder.
     */
    function downloadhtmlzip($args, $request)
    {
        $package = $this->_buildHtmlPackage($request, 'index.html');
        $zipPath = $package['root'] . '/package.zip';
        try {
            $package['exporter']->zip($package['outDir'], $zipPath);
            $filename = 'article-' . (int)$package['submissionFile']->getData('submissionId') . '-html.zip';
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($zipPath));
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private');
            readfile($zipPath);
        } finally {
            (new PrivateFileManager())->rmtree($package['root']);
        }
        exit;
    }

    /**
     * Unzips the markup session ZIP into a private temp dir and converts its article.xml to HTML.
     * @return array root, outDir, exporter and submissionFile
     */
    private function _buildHtmlPackage($request, $htmlName, $epubName = null)
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $submissionFile = $this->submissionFile;
        if (!$submission || !$submissionFile || $submissionFile->getData('submissionId') != $submission->getId()) {
            throw new Exception('Invalid submission file.');
        }

        $fileManager = new PrivateFileManager();
        $zipFile = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');
        $root = sys_get_temp_dir() . '/jatshtml-' . bin2hex(random_bytes(8));
        $srcDir = $root . '/src';
        $outDir = $root . '/out';
        if (!mkdir($srcDir, 0700, true) || !mkdir($outDir, 0700, true)) {
            throw new Exception('Could not create the temporary directory.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new Exception('The markup session file is not a valid ZIP.');
            }
            $zip->extractTo($srcDir);
            $zip->close();
            if (!is_file($srcDir . '/article.xml')) {
                throw new Exception('article.xml was not found in the markup session ZIP.');
            }

            $exporter = new JatsHtmlExporter(Core::getBaseDir() . '/' . $this->_plugin->getPluginPath() . '/assets/xslt');
            $exporter->export($srcDir . '/article.xml', $srcDir, $outDir, $htmlName, substr($submission->getLocale(), 0, 2), $epubName);
        } catch (Exception $e) {
            $fileManager->rmtree($root);
            throw $e;
        }

        return ['root' => $root, 'outDir' => $outDir, 'exporter' => $exporter, 'submissionFile' => $submissionFile];
    }

    function unpack($extension, $request)
    {
        $submissionFileId = $request->getUserVar('submissionFileId');
        $fileManager = new PrivateFileManager();
        $submissionFile = Services::get('submissionFile')->get($submissionFileId);
        if (!$submissionFile) {
            $request->redirect(null, 'index'); // Redirige a inicio si no existe el fichero
            return;
        }
        $filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');
        // Unzip the file to $basedir

        $basedir = sys_get_temp_dir() . '/' . $submissionFileId;
        if (!is_dir($basedir)) {
            mkdir($basedir, 0777, true);
        }
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $zip->extractTo($basedir);
            $zip->close();
        } else {
            throw new Exception("ERROR: no se pudo abrir el archivo ZIP.");
        }
        //Rename $basedir/article.xml to $submissionFileId.xml
        $articleXmlPath = $basedir . '/article.' . $extension;
        // Load XML Document
        $jatsXML = new DOMDocument();
        $jatsXML->load($articleXmlPath);
        $jatsXML->getElementsByTagName('article')->item(0)->setAttribute('article-type', 'research-article');
        $submission = Services::get('submission')->get($submissionFile->getData('submissionId'));
        $jatsXML->getElementsByTagName('article')->item(0)->setAttribute('xml:lang', substr($submission->getLocale(), 0, 2));
        $jatsXML->save($articleXmlPath);

        if (file_exists($articleXmlPath)) {
            rename($articleXmlPath, $basedir . '/' . $submissionFileId . '.' . $extension);
        } else {
            throw new Exception("ERROR: no se encontró el fichero article." . $extension);
        }
        return $this->_import_dir(sys_get_temp_dir() . '/' . $request->getUserVar('submissionFileId'), $request, $extension);
    }


    function saveMark($zipfile, $request, $endSession = true)
    {

        if (!$this->submissionFile) {
            if ($endSession) {
                $request->redirect(null, 'index'); // Redirige si no existe el fichero
            }
            return;
        }
        $fileManager = new PrivateFileManager();
        $filePath = $fileManager->getBasePath() . '/' . $this->submissionFile->getData('path');
        $fileInfo = pathinfo($filePath);

        if ($fileInfo['extension'] == 'zip') {
            $submissionId = $this->submissionFile->getData('submissionId');
            $submission = Services::get('submission')->get($submissionId);
            $genreId = $this->submissionFile->getData('genreId');

            $submissionDir = Services::get('submissionFile')->getSubmissionDir($submission->getData('contextId'), $submissionId);

            // Copia el nuevo ZIP a un nuevo fichero físico
            $newFileId = Services::get('file')->add(
                $zipfile,
                $submissionDir . DIRECTORY_SEPARATOR . uniqid() . '.zip'
            );

            // Clona los datos del fichero anterior
            $params = [
                'fileId' => $newFileId,
                'mimetype' => 'application/zip',
                'name' => $this->submissionFile->getData('name'), // conservar el nombre original
            ];

            // Crear nueva versión
            Services::get('submissionFile')->edit($this->submissionFile, $params, $request);

            // Limitar a 2 versiones máximo
            //$this->_limitFileVersions($this->submissionFile->getId(), 2);
        } else {
            $submissionId = $this->submissionFile->getData('submissionId');
            $submission = Services::get('submission')->get($submissionId);

            $genreId = $this->submissionFile->getData('genreId');

            $submissionDir = Services::get('submissionFile')->getSubmissionDir($submission->getData('contextId'), $submissionId);
            $newFileId = Services::get('file')->add(
                $zipfile,
                $submissionDir . DIRECTORY_SEPARATOR . uniqid() . '.zip'
            );
            $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
            $newSubmissionFile = $submissionFileDao->newDataObject();
            $nameData = $this->engine->getMarkedData('name');
            $base = pathinfo((string)$nameData, PATHINFO_FILENAME);
            
            $newName = [];
            $locale = $this->submissionFile->getData('locale') ?: 'es_ES';
            $newName[$locale] = ($base ?: 'sesion_marcado') . '.mark.zip';

            $newSubmissionFile->setAllData(
                [
                    'fileId' => $newFileId,
                    'assocType' => $this->submissionFile->getData('assocType'),
                    'assocId' => $this->submissionFile->getData('assocId'),
                    'fileStage' => $this->submissionFile->getData('fileStage'),
                    'mimetype' => 'application/zip',
                    'locale' => $locale,
                    'genreId' => $genreId,
                    'name' => $newName,
                    'submissionId' => $submissionId,
                ]
            );

            $newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, $request);
        }
        unlink($zipfile);
        if ($endSession) {
            $this->engine->clean();
            $request->redirect(null, 'workflow', 'access', $submissionId);
        }
    }


    /**
     * Limita el número de versiones de un archivo de envío
     * @param int $submissionFileId ID del archivo de envío
     * @param int $maxVersions Número máximo de versiones a mantener
     */
    private function _limitFileVersions_old($submissionFileId, $maxVersions = 2)
    {
        // Obtener el archivo actual
        $currentFile = Services::get('submissionFile')->get($submissionFileId);
        if (!$currentFile) {
            return;
        }

        $submissionId = $currentFile->getData('submissionId');
        $fileStage = $currentFile->getData('fileStage');
        $currentBaseName = $this->_getBaseName($currentFile->getLocalizedData('name'));

        // Obtener todos los archivos del submission usando SQL directo (compatible OJS 3.3)
        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
        $result = $submissionFileDao->retrieve(
            'SELECT * FROM submission_files WHERE submission_id = ? AND file_stage = ? ORDER BY submission_file_id DESC',
            [(int) $submissionId, (int) $fileStage]
        );

        // Recopilar archivos que coincidan con el nombre base
        $versions = [];
        while ($row = $result->current()) {

            $file = $submissionFileDao->_fromRow((array) $row);
            $fileBaseName = $this->_getBaseName($file->getLocalizedData('name'));
            echo $file->getLocalizedData('name') . "<br/>";
            if ($fileBaseName === $currentBaseName) {
                $versions[] = $file;
            }
            $result->next();
        }
        // Si hay más versiones que el máximo permitido, eliminar las antiguas
        if (count($versions) > $maxVersions) {
            // Ya están ordenadas por ID descendente (más reciente primero)
            for ($i = $maxVersions; $i < count($versions); $i++) {
                $oldFile = $versions[$i];

                // Eliminar el archivo físico
                $fileService = Services::get('file');
                if ($oldFile->getData('fileId')) {
                    $fileService->delete($oldFile->getData('fileId'));
                }

                // Eliminar el registro de la base de datos
                $submissionFileDao->deleteById($oldFile->getId());
            }
        }
    }
    /**
     * Obtiene el nombre base de un archivo sin extensión .mark
     * @param string $filename Nombre del archivo
     * @return string Nombre base
     */
    private function _getBaseName($filename)
    {
        // Eliminar .mark.zip o .zip del final
        $basename = preg_replace('/\.(mark\.)?zip$/', '', $filename);
        return $basename;
    }

    function _import_dir($basedir, $request, $extension = 'xml')
    {
        // Aquí implementarás el callback (por ejemplo, mostrar resultado del wizard)
        // Por ahora, solo un mensaje ejemplo
        $submissionFileId = $request->getUserVar('submissionFileId');
        $submissionFile = Services::get('submissionFile')->get($submissionFileId);
        $fileManager = new PrivateFileManager();
        if (!$submissionFileId) {
            throw new Exception("ERROR: falta el parámetro submissionFileId o no es válido.");
        }

        $tmpfname = $basedir . '/' . $submissionFileId . '.' . $extension;
        if (!file_exists($tmpfname)) {
            throw new Exception("ERROR: no se encontró el fichero " . $tmpfname);
        }
        $submissionId = $submissionFile->getData('submissionId');
        $submission = Services::get('submission')->get($submissionId);

        $genreId = $submissionFile->getData('genreId');

        $submissionDir = Services::get('submissionFile')->getSubmissionDir($submission->getData('contextId'), $submissionId);

        $isHtml = ($extension === 'html');
        $galley = null;
        $epubFile = $basedir . '/' . $submissionFileId . '.epub';
        $epubGalley = null;
        if ($isHtml) {
            $galley = $this->_createHtmlGalley($request, $submission, 'HTML');
            if (is_file($epubFile)) {
                $epubGalley = $this->_createHtmlGalley($request, $submission, 'ePub');
                $dispatcher = $request->getDispatcher();
                $epubUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'article', 'download', [$submission->getBestId(), $epubGalley->getBestGalleyId()]);
                $assetsUrl = $request->getBaseUrl() . '/' . $this->_plugin->getPluginPath() . '/assets/vendor/';
                $html = file_get_contents($tmpfname);
                $html = str_replace(['@@JV_EPUB_URL@@', '@@JV_ASSETS_URL@@'], [htmlspecialchars($epubUrl), htmlspecialchars($assetsUrl)], $html);
                file_put_contents($tmpfname, $html);
            }
        }

        // Add new JATS XML file
        $newFileId = Services::get('file')->add(
            $tmpfname,
            $submissionDir . '/' . uniqid() . '.' . $extension
        );

        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
        $newSubmissionFile = $submissionFileDao->newDataObject();
        $newName = [];
        foreach ($submissionFile->getData('name') as $localeKey => $name) {
            $newName[$localeKey] = pathinfo($name)['filename'] . '.' . $extension;
        }

        $newSubmissionFile->setAllData(
            [
                'fileId' => $newFileId,
                'assocType' => $isHtml ? ASSOC_TYPE_REPRESENTATION : $submissionFile->getData('assocType'),
                'assocId' => $isHtml ? $galley->getId() : $submissionFile->getData('assocId'),
                'fileStage' => $isHtml ? SUBMISSION_FILE_PROOF : $submissionFile->getData('fileStage'),
                'mimetype' => $isHtml ? 'text/html' : 'application/xml',
                'locale' => $submissionFile->getData('locale'),
                'genreId' => $genreId,
                'name' => $newName,
                'submissionId' => $submissionId,
            ]
        );

        $newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, $request);
        if ($galley) {
            $galley->setFileId($newSubmissionFile->getId());
            DAORegistry::getDAO('ArticleGalleyDAO')->updateObject($galley);
        }
        unlink($tmpfname);

        if ($epubGalley) {
            import('plugins.generic.jatsWizard.classes.JatsEpubValidator');
            @set_time_limit(600);
            $epubGalley->setData('jvValidation', json_encode(JatsEpubValidator::validate($epubFile)));
            $epubFileId = Services::get('file')->add($epubFile, $submissionDir . '/' . uniqid() . '.epub');
            $epubSubmissionFile = $submissionFileDao->newDataObject();
            $epubName = [];
            foreach ($submissionFile->getData('name') as $localeKey => $name) {
                $epubName[$localeKey] = pathinfo($name)['filename'] . '.epub';
            }
            $epubSubmissionFile->setAllData([
                'fileId' => $epubFileId,
                'assocType' => ASSOC_TYPE_REPRESENTATION,
                'assocId' => $epubGalley->getId(),
                'fileStage' => SUBMISSION_FILE_PROOF,
                'mimetype' => 'application/epub+zip',
                'locale' => $submissionFile->getData('locale'),
                'genreId' => $genreId,
                'name' => $epubName,
                'submissionId' => $submissionId,
            ]);
            $epubSubmissionFile = Services::get('submissionFile')->add($epubSubmissionFile, $request);
            $epubGalley->setFileId($epubSubmissionFile->getId());
            DAORegistry::getDAO('ArticleGalleyDAO')->updateObject($epubGalley);
            unlink($epubFile);
        }

        $extensiones = ['xml', 'png', 'jpeg', 'jpg', 'gif'];

        // Iterador recursivo para recorrer todos los ficheros
        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basedir . '/', RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $extensiones = ['png', 'jpeg', 'jpg', 'gif'];
        foreach ($iterador as $archivo) {
            if ($archivo->isFile()) {
                $extension = strtolower(pathinfo($archivo->getFilename(), PATHINFO_EXTENSION));
                if (in_array($extension, $extensiones)) {
                    $archive_path = $archivo->getPathname();
                    $archive_name = $archivo->getFilename();
                    $this->_attachSupplementaryFile($request, $submission, $submissionFileDao, $newSubmissionFile, $fileManager, $archive_name, $archive_path);
                }
            }
        }

        $request->redirect(null, 'workflow', 'access', $submissionId);
    }

    /**
     * Creates the "HTML" galley of the latest publication, replacing a previous one made by this export.
     */
    private function _createHtmlGalley($request, $submission, $label = 'HTML')
    {
        $publication = $submission->getLatestPublication();
        $galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
        foreach ($galleyDao->getByPublicationId($publication->getId())->toArray() as $old) {
            if ($old->getLabel() === $label) {
                Services::get('galley')->delete($old);
            }
        }
        $galley = $galleyDao->newDataObject();
        $galley->setData('publicationId', $publication->getId());
        $galley->setLabel($label);
        $galley->setLocale($submission->getLocale());
        return Services::get('galley')->add($galley, $request);
    }

    private function _attachSupplementaryFile(Request $request, Submission $submission, SubmissionFileDAO $submissionFileDao, SubmissionFile $newSubmissionFile, PrivateFileManager $fileManager, string $originalName, string $filepath)
    {
        $mimeType = mime_content_type($filepath);

        // Determine genre
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByDependenceAndContextId(true, $request->getContext()->getId());
        $supplGenreId = null;
        while ($genre = $genres->next()) {
            if (($mimeType == "image/png" || $mimeType == "image/jpeg") && $genre->getKey() == "IMAGE") {
                $supplGenreId = $genre->getId();
            }
        }

        if (!$supplGenreId) {
            return;
        }

        $submissionDir = Services::get('submissionFile')->getSubmissionDir($submission->getData('contextId'), $submission->getId());
        $newFileId = Services::get('file')->add(
            $filepath,
            $submissionDir . '/' . uniqid() . '.' . $fileManager->parseFileExtension($originalName)
        );

        // Set file
        $newSupplementaryFile = $submissionFileDao->newDataObject();
        $newSupplementaryFile->setAllData([
            'fileId' => $newFileId,
            'assocId' => $newSubmissionFile->getId(),
            'assocType' => ASSOC_TYPE_SUBMISSION_FILE,
            'fileStage' => SUBMISSION_FILE_DEPENDENT,
            'submissionId' => $submission->getId(),
            'genreId' => $supplGenreId,
            'name' => array_fill_keys(array_keys($newSubmissionFile->getData('name')), basename($originalName))
        ]);

        Services::get('submissionFile')->add($newSupplementaryFile, $request);
        unlink($filepath);
    }
    /**
     * Create galley form
     * @param $args array
     * @param $request PKPRequest
     * @return JSONMessage JSON object
     */
    public function createGalleyForm($args, $request)
    {

        import('plugins.generic.jatsWizard.classes.grid.form.JatsWizardArticleGalleyForm');

        $galleyForm = new JatsWizardArticleGalleyForm($request, $this->getPlugin(), $this->submission->getLatestPublication(), $this->submission);

        $galleyForm->initData();
        return new JSONMessage(true, $galleyForm->fetch($request));
    }
    /**
     * @param $args
     * @param $request PKPRequest
     * @return JSONMessage
     */
    public function createGalley($args, $request)
    {

        import('plugins.generic.jatsWizard.classes.grid.form.JatsWizardArticleGalleyForm');
        $galleyForm = new JatsWizardArticleGalleyForm($request, $this->getPlugin(), $this->submission->getLatestPublication(), $this->submission);
        $galleyForm->readInputData();

        if ($galleyForm->validate()) {
            $galleyForm->execute();
            return $request->redirectUrlJson($request->getDispatcher()->url(
                $request,
                ROUTE_PAGE,
                null,
                'workflow',
                'access',
                null,
                array(
                    'submissionId' => $request->getUserVar('submissionId'),
                    'stageId' => $request->getUserVar('stageId')
                )
            ));
        }

        return new JSONMessage(false);
    }

    private function getFlipbookSource($request)
    {
        $publicationId = (int) $request->getUserVar('publicationId');
        $publication = $publicationId ? Services::get('publication')->get($publicationId) : null;
        if (!$this->submission || !$publication || (int) $publication->getData('submissionId') !== (int) $this->submission->getId()) {
            throw new Exception('Invalid FlipBook publication.');
        }

        $sourceType = $request->getUserVar('sourceType');
        if ($sourceType === 'galley') {
            $sourceGalleyId = (int) $request->getUserVar('sourceGalleyId');
            $galley = DAORegistry::getDAO('ArticleGalleyDAO')->getById($sourceGalleyId);
            if (!$galley || (int) $galley->getData('publicationId') !== $publicationId) {
                throw new Exception('The selected PDF galley is not part of this publication.');
            }
            $sourceFile = $galley->getFile();
        } elseif ($sourceType === 'file') {
            $sourceFileId = (int) $request->getUserVar('sourceFileId');
            $sourceFile = Services::get('submissionFile')->get($sourceFileId);
            if (!$sourceFile || (int) $sourceFile->getData('submissionId') !== (int) $this->submission->getId() ||
                (int) $sourceFile->getData('fileStage') === SUBMISSION_FILE_DEPENDENT) {
                throw new Exception('The selected PDF does not belong to this submission.');
            }
        } else {
            throw new Exception('Unsupported FlipBook source.');
        }

        if (!$sourceFile || $sourceFile->getData('mimetype') !== 'application/pdf') {
            throw new Exception('The selected source is not a PDF.');
        }

        return [$publication, $sourceFile];
    }

    public function flipbookForm($args, $request)
    {
        list($publication, $sourceFile) = $this->getFlipbookSource($request);
        import('plugins.generic.jatsWizard.classes.grid.form.JatsWizardFlipbookForm');
        $form = new JatsWizardFlipbookForm($request, $this->_plugin, $this->submission, $publication, $sourceFile);
        $form->initData();
        return new JSONMessage(true, $form->fetch($request));
    }

    public function createFlipbook($args, $request)
    {
        list($publication, $sourceFile) = $this->getFlipbookSource($request);
        import('plugins.generic.jatsWizard.classes.grid.form.JatsWizardFlipbookForm');
        $form = new JatsWizardFlipbookForm($request, $this->_plugin, $this->submission, $publication, $sourceFile);
        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        $stageId = (int) $request->getUserVar('stageId') ?: WORKFLOW_STAGE_ID_PRODUCTION;
        return $request->redirectUrlJson($request->getDispatcher()->url(
            $request,
            ROUTE_PAGE,
            null,
            'workflow',
            'access',
            [$this->submission->getId(), $stageId]
        ));
    }

    private function getEpubValidationTarget($request)
    {
        $publicationId = (int) $request->getUserVar('publicationId');
        $publication = $publicationId ? Services::get('publication')->get($publicationId) : null;
        if (!$this->submission || !$publication || (int) $publication->getData('submissionId') !== (int) $this->submission->getId()) {
            throw new Exception('Invalid ePub publication.');
        }

        $galleyId = (int) $request->getUserVar('galleyId');
        $galley = DAORegistry::getDAO('ArticleGalleyDAO')->getById($galleyId, $publicationId);
        $file = $galley ? $galley->getFile() : null;
        if (!$file || $file->getData('mimetype') !== 'application/epub+zip') {
            throw new Exception('The selected galley is not an ePub.');
        }

        return [$galley, $file];
    }

    public function revalidateEpubForm($args, $request)
    {
        list($galley, $file) = $this->getEpubValidationTarget($request);
        import('plugins.generic.jatsWizard.classes.grid.form.JatsEpubValidationForm');
        $form = new JatsEpubValidationForm($this->_plugin, $galley, $file);
        return new JSONMessage(true, $form->fetch($request));
    }

    public function revalidateEpub($args, $request)
    {
        list($galley, $file) = $this->getEpubValidationTarget($request);
        import('plugins.generic.jatsWizard.classes.grid.form.JatsEpubValidationForm');
        $form = new JatsEpubValidationForm($this->_plugin, $galley, $file);
        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        $stageId = (int) $request->getUserVar('stageId') ?: WORKFLOW_STAGE_ID_PRODUCTION;
        return $request->redirectUrlJson($request->getDispatcher()->url(
            $request,
            ROUTE_PAGE,
            null,
            'workflow',
            'access',
            [$this->submission->getId(), $stageId]
        ));
    }
}
