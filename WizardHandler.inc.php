<?php

import('plugins.generic.jatsWizard.classes.WizardEngine');
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
    }
    /**
     * Get the plugin.
     * @return WizardPlugin
     */
    function getPlugin()
    {
        return $this->_plugin;
    }

    function initialize($request)
    {

        parent::initialize($request);

        
        $this->engine = new WizardEngine($request, $this->_plugin);
        $submissionFileId = $request->getUserVar('submissionFileId');
        if ($submissionFileId) {
            $this->submissionFile = Services::get('submissionFile')->get($submissionFileId);

            $this->submission = Services::get('submission')->get($this->submissionFile->getData('submissionId'));
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
        $fileManager = new PrivateFileManager();
        $filePath = $fileManager->getBasePath() . '/' . $this->submissionFile->getData('path');

        $this->engine->clean();
        $this->engine->ensureWorkdir($this->submissionFile->getData('submissionId'), $request->getUserVar('submissionFileId'));
        $this->engine->setSubmissionFile($filePath, $this->submissionFile->getLocalizedData('name'));

        $citations = $this->submission->getLatestPublication()->getData('citationsRaw');
        $this->engine->startWizard($citations);
    }

    /**
     * Ruta central del wizard (equivalente al viejo index.php?op=...)
     */
    public function engine($args, $request)
    {
        if (empty($_SESSION['jatsWizardState']) || empty($_SESSION['jatsWizardState']['marked_data'])) {
             $request->redirect(null, 'workflow', 'index', $this->submission->getId(),'5');
            return;
        }
        
        $op = $request->getUserVar('op');
        // get citationsRaw of summission
        //$citations = $this->submission->getCitationsRaw();
        switch ($op) {
            case 'front':
                //header("Content-Type: application/xml; charset=UTF-8");
                echo $this->engine->generateFromXml(true);
                return;
            case 'start':
                if (!empty($opts = $request->getUserVar('opts'))) {
                    $this->engine->setOptions($request);
                }
                $citations = $this->submission->getLatestPublication()->getData('citationsRaw');
                $this->engine->convert($citations);
                // redirect to engine after starting incluidng submissionFileId and submissionId
                $request->redirect(null, null, null, null, array(
                    'submissionFileId' => $request->getUserVar('submissionFileId'),
                    'submissionId' => $this->submission->getId(),
                ));
                break;
            case 'session':
                header('Content-Type: application/json');
                echo json_encode($_SESSION['jatsWizardState']);
                break;

            case 'upload_doc':
                if (empty($_FILES['file']['tmp_name'])) {
                    header("HTTP/1.1 400 Bad Request");
                    echo "No file uploaded";
                    exit;
                }

                $this->engine->uploadDoc($_FILES['file']);
                $this->engine->convert();
                $this->engine->startWizard();
                break;
            case 'pdf':
                $this->engine->generatePublication('pdf');
                header('Content-Type: application/pdf');
                readfile($this->engine->getWorkdir().'/article.pdf');
                return;
            case 'html':
                $this->engine->generatePublication('html');
                header('Content-Type: text/html; charset=UTF-8');
                $html = file_get_contents($this->engine->getWorkdir().'/article.html');
                // Replace every src="img/..." with the full URL to the image
                $html = preg_replace_callback('/src="([^"]+)"/', function ($matches) {
                    return 'src="' . $_SESSION['jatsWizardState']['engineBaseUrl'] . '&op=img&img=' . $matches[1] . '"';
                }, $html);
                // replace href="style.css" with full URL
                $html = str_replace('href="style.css"', 'href="' . $_SESSION['jatsWizardState']['engineBaseUrl'] . '&op=css"', $html);
                echo $html;
                return;
            case 'reconvert':
                if (!empty($request->getUserVar('secs'))) {
                    $this->engine->updateMarkedData([
                        'secs' => (array) json_decode($request->getUserVar('secs')),
                    ]);
                }
                if (!empty($request->getUserVar('csl'))) {
                    $this->engine->updateMarkedData([
                        'csl' => json_decode($request->getUserVar('csl')),
                    ]);
                }
                
                $xml = $this->engine->convert(null, !empty($request->getUserVar('debug')));
                header("Content-Type: application/xml; charset=UTF-8");
                echo $xml;
                break;
            case 'debug':
                $this->engine->convert(null, true);
                return;
            case 'css':
                header('Content-Type: text/css; charset=UTF-8');
                echo file_get_contents($this->engine->getWorkdir().'/style.css');
                return;
            case 'xml':
                header('Content-Type: application/xml');
                echo file_get_contents($this->engine->getXmlPath());
                return;
            case 'ojs_zip':
                $zipfile = $this->engine->zipWorkdir();
                $this->saveMark($zipfile, $request);
                break;
            case 'markedData':
                header('Content-Type: application/json');
                echo json_encode($this->engine->getMarkedData(), JSON_PRETTY_PRINT);
                return;
            case 'img':
                $img = basename($request->getUserVar('img'));
                $path = $this->engine->getImagePath($img);

                if (!file_exists($path)) {
                    header("HTTP/1.1 404 Not Found");
                    echo "Image not found";
                    exit;
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
            case 'clean':
                $this->engine->clean();
                $request->redirect(null, 'workflow', 'access', $this->submission->getId());

            default:
                $this->engine->startWizard();
        }
    }

    /* ===============================
     *       OPERACIONES INTERNAS
     * =============================== */




    function unpackxml($args, $request){
        return $this->unpack('xml', $request);
    }
    function unpackhtml($args, $request){
        return $this->unpack('html', $request);
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
            echo "ERROR: no se pudo abrir el archivo ZIP.";
            exit;
            $request->redirect(null, 'index'); // Redirige a inicio si no se puede abrir el zip
            return;
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
            echo "ERROR: no se encontró el fichero article." . $extension;
            exit;
            $request->redirect(null, 'index'); // Redirige a inicio si no existe el fichero article.xml
            return;
        }
        return $this->_import_dir(sys_get_temp_dir() . '/' . $request->getUserVar('submissionFileId'), $request, $extension);
    }

    
    function saveMark($zipfile, $request)
    {

        if (!$this->submissionFile) {
            $request->redirect(null, 'index'); // Redirige si no existe el fichero
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
            $newName = [];
            foreach ($this->submissionFile->getData('name') as $localeKey => $name) {
                $newName[$localeKey] = pathinfo($name)['filename'] . '.mark.zip';
            }

            $newSubmissionFile->setAllData(
                [
                    'fileId' => $newFileId,
                    'assocType' => $this->submissionFile->getData('assocType'),
                    'assocId' => $this->submissionFile->getData('assocId'),
                    'fileStage' => $this->submissionFile->getData('fileStage'),
                    'mimetype' => 'application/zip',
                    'locale' => $this->submissionFile->getData('locale'),
                    'genreId' => $genreId,
                    'name' => $newName,
                    'submissionId' => $submissionId,
                ]
            );

            $newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, $request);
        }
        unlink($zipfile);
        $request->redirect(null, 'workflow', 'access', $submissionId);
    }

    function _import_dir($basedir, $request, $extension = 'xml')
    {
        // Aquí implementarás el callback (por ejemplo, mostrar resultado del wizard)
        // Por ahora, solo un mensaje ejemplo
        $submissionFileId = $request->getUserVar('submissionFileId');
        $submissionFile = Services::get('submissionFile')->get($submissionFileId);
        $fileManager = new PrivateFileManager();
        if (!$submissionFileId) {
            echo "ERROR: falta el parámetro submissionFileId o no es válido.";
            exit;
            $request->redirect(null, 'index'); // Redirige a inicio si falta parámetro
            return;
        }

        $tmpfname = $basedir . '/' . $submissionFileId . '.' . $extension;
        if (!file_exists($tmpfname)) {
            echo "ERROR: no se encontró el fichero $tmpfname.";
            exit;
            $request->redirect(null, 'index'); // Redirige si no existe el fichero
            return;
        }
        $submissionId = $submissionFile->getData('submissionId');
        $submission = Services::get('submission')->get($submissionId);

        $genreId = $submissionFile->getData('genreId');

        // Add new JATS XML file
        $submissionDir = Services::get('submissionFile')->getSubmissionDir($submission->getData('contextId'), $submissionId);
        $newFileId = Services::get('file')->add(
            $tmpfname,
            $submissionDir . DIRECTORY_SEPARATOR . uniqid() . '.' . $extension
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
                'assocType' => $submissionFile->getData('assocType'),
                'assocId' => $submissionFile->getData('assocId'),
                'fileStage' => $submissionFile->getData('fileStage'),
                'mimetype' => 'application/xml',
                'locale' => $submissionFile->getData('locale'),
                'genreId' => $genreId,
                'name' => $newName,
                'submissionId' => $submissionId,
            ]
        );

        $newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, $request);

        unlink($tmpfname);

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
}
