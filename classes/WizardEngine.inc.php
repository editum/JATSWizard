<?php
import('plugins.generic.jatsWizard.classes.JATSFront');
import('plugins.generic.jatsWizard.classes.PipelineApiClient');
/**
 * WizardEngine
 * -------------------------
 * Motor del asistente de marcado integrado en OJS.
 * Reemplaza completamente toda la lógica antigua:
 *
 *  - Sin OUTPUTDIR
 *  - Sin SESSION nativo
 *  - Sin opinfo.json
 *  - Sin index.php externo
 */

class WizardEngine
{
    /** @var string Token de sesión único para soporte multipestaña */
    public $wizardToken;
    /** @var PipelineApiClient Cliente HTTP para el pipeline */
    private $apiClient;
    private $request;
    private $submission;
    private $baseUrl;
    private $plugin;

    public function setWizardToken($token)
    {
        $this->wizardToken = $token;
    }

    public function __construct($request, $plugin)
    {

        $context = $request->getContext();

        $pipelineUrl = $plugin->getSetting($context->getId(), 'pipelineUrl');
        if ($pipelineUrl === null) {
            $pipelineUrl = 'http://revistas.test.um.es/jats-pipeline';
        }
        
        $this->apiClient = new PipelineApiClient($pipelineUrl);

        $this->request = $request;

        define('JATSWIZARD_ASSETS_URL', $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/assets');

        $this->plugin = $plugin;
    }
    public function setSubmission($submission)
    {
        $this->submission = $submission;
    }
    /**
     * Inicializa sesión de trabajo:
     *  - Crea workdir
     *  - Copia el docx del submission
     *  - Guarda front.xml
     */
    public function ensureWorkdir($submissionId, $submissionFileId)
    {

        if (empty($_SESSION['jatsWizardStates'][$this->wizardToken])) {
            $_SESSION['jatsWizardStates'][$this->wizardToken] = [
                'sessionId' => $submissionId . '/' . $submissionFileId,
            ];
        } else if ($_SESSION['jatsWizardStates'][$this->wizardToken]['sessionId'] !== $submissionId . '/' . $submissionFileId) {
            $this->clearWorkdir();
            $_SESSION['jatsWizardStates'][$this->wizardToken] = ['sessionId' => $submissionId . '/' . $submissionFileId];
        }

        $_SESSION['jatsWizardStates'][$this->wizardToken]['engineBaseUrl'] = $this->request->getDispatcher()->url(
            $this->request,
            ROUTE_PAGE,
            null,
            'jatsWizard',
            'engine',
            null,
            array(
                'submissionId' => $submissionId,
                'submissionFileId' => $submissionFileId,
                'wizardToken' => $this->wizardToken,
            )
        );

        $this->cleanupOrphanedWorkdirs();

        if (isset($_SESSION['jatsWizardStates'][$this->wizardToken]['workdir']) && is_dir($_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'])) {
            return $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'jatswiz-');
        if (file_exists($tmp)) {
            unlink($tmp);
        }
        mkdir($tmp, 0777, true);
        mkdir($tmp . '/src', 0777, true);
        $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'] = $tmp;
        return $tmp;
    }

    public function cleanupOrphanedWorkdirs()
    {
        $tmpDir = sys_get_temp_dir();
        $folders = glob($tmpDir . DIRECTORY_SEPARATOR . 'jatswiz-*', GLOB_ONLYDIR);
        if (is_array($folders)) {
            $threshold = time() - (24 * 3600); // 24 hours old
            foreach ($folders as $folder) {
                if (filemtime($folder) < $threshold) {
                    $this->_deleteDir($folder);
                }
            }
        }
    }

    public function clearWorkdir()
    {
        if (isset($_SESSION['jatsWizardStates'][$this->wizardToken]['workdir']) && is_dir($_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'])) {
            $this->_deleteDir($_SESSION['jatsWizardStates'][$this->wizardToken]['workdir']);
            unset($_SESSION['jatsWizardStates'][$this->wizardToken]['workdir']);
        }
    }
    private function _deleteDir($dirPath)
    {
        if (!is_dir($dirPath)) {
            return;
        }
        $files = array_diff(scandir($dirPath), array('.', '..'));
        foreach ($files as $file) {
            $fullPath = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                $this->_deleteDir($fullPath);
            } else {
                unlink($fullPath);
            }
        }
        rmdir($dirPath);
    }
    public function setSubmissionFile($filePath, $submissionName)
    {

        $fileInfo = pathinfo($filePath);
        if ($fileInfo['extension'] === 'docx') {
            // DOCX → nueva sesión
            $this->loadDocx($filePath, $submissionName);
        } elseif ($fileInfo['extension'] === 'zip') {
            // ZIP → cargar sesión antigua
            $this->loadZip($filePath);
        } else {
            throw new Exception("Tipo de archivo no soportado");
        }
    }
    public function setOptions($request)
    {
        $opts = [
            'normalize' => $request->getUserVar('normalize') !== null,
        ];
        if ($request->getUserVar('do-automark') === null) {
            $opts['automarkStyle'] = null;
            $opts['automarkSetMixedCitations'] = false;
            $opts['automarkSetFiguresTitles'] = false;
            $opts['automarkSetTablesTitles'] = false;
            $opts['automarkSetTitlesReferences'] = false;
        } else {
            $opts['automarkStyle'] = $request->getUserVar('automark-citation-style');
            $opts['automarkSetFiguresTitles'] = $request->getUserVar('automark-set-figures-titles') !== null;
            $opts['automarkSetTablesTitles'] = $request->getUserVar('automark-set-tables-titles') !== null;
            $opts['automarkSetTitlesReferences'] = $request->getUserVar('automark-set-title-references') !== null;
        }
        if ($request->getUserVar('scielo-specific-use') !== null) {
            // Aplicar reglas SciELO
            $opts['automarkSetMixedCitations'] = true;
            $this->updateMarkedData(['specific-use' => 'scielo']);
        }
        $this->updateMarkedData(['opts' => $opts]);
    }
    public function getFileName()
    {
        return $_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data']['name'];
    }
    public function getMarkedData($part = null)
    {
        //Force Load fron disk
        $workdir = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
        $_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'] = json_decode(file_get_contents($workdir . '/src/marked_data.json'), true);
        if ($part === null) {
            return $_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'];
        }
        return isset($_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'][$part]) ? $_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'][$part] : null;
    }
    public function getDocxPath()
    {
        return $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'] . '/src/article.docx';
    }
    public function getXmlPath()
    {
        return $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'] . '/article.xml';
    }
    public function getImagePath($img)
    {
        return $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'] . '/' . $img;
    }
    public function clearPublications()
    {
        $workdir = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
        $formats = ['html', 'pdf'];
        foreach ($formats as $format) {
            if (file_exists($workdir . '/article.' . $format)) {
                unlink($workdir . '/article.' . $format);
            }
        }
        if (file_exists($workdir . '/style.css')) {
            unlink($workdir . '/style.css');
        }
    }
    public function generatePublication($format)
    {
        if (file_exists($this->getWorkdir() . '/article.' . $format)) {
            return;
        }
        $zipPath = $this->zipWorkdir();
        
        try {
            $this->apiClient->publishJats($zipPath, $this->getWorkdir());
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
        if (!file_exists($this->getWorkdir() . '/article.' . $format)) {
            throw new Exception("Error al generar archivos de publicación en formato " . $format);
        }
    }
    public function loadDocx($docxPath, $submissionName)
    {
        copy($docxPath, $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'] . '/src/article.docx');
        $marked_data = array(
            'name' => $submissionName,
            'csl' => array(),
            'version' => 1,
            'secs' => array(),
            'opts' => array(),
        );
        file_put_contents(
            $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'] . '/src/marked_data.json',
            json_encode($marked_data, JSON_PRETTY_PRINT)
        );
        $_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'] = $marked_data;
    }


    /**
     * Procesa un ZIP antiguo o nuevo
     */
    public function loadZip($zipPath)
    {
        $workdir = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception("No se pudo abrir ZIP"); //TODO: Interacionalizar
        }
        $zip->extractTo($workdir);
        $zip->close();

        $jsonContent = @file_get_contents($workdir . '/src/marked_data.json');
        if ($jsonContent === false) {
            JatsWizardPlugin::log('ERROR', 'marked_data.json not found in ZIP', ['workdir' => $workdir]);
            throw new Exception("El ZIP no contiene una sesión válida (archivo no encontrado)");
        }

        $marked = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            JatsWizardPlugin::log('ERROR', 'Invalid JSON in marked_data.json', ['error' => json_last_error_msg()]);
            throw new Exception("El ZIP no contiene una sesión válida (JSON corrupto)");
        }
        $_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'] = $marked;
    }

    public function generateFromXml($return = false)
    {
        $workdir = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];

        // Extraer metadatos JATS front del submission
        $jatsFront = new JATSFront($this->getMarkedData('specific-use'));
        $jatsFront->setDocumentMeta($this->request, $this->submission);
        if ($return) {
            return $jatsFront->saveXML();
        }
        file_put_contents($workdir . '/src/front.xml', $jatsFront->saveXML());
    }

    public function startWizard($citations = null)
    {
        $workdir = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
        if (!file_exists($workdir . '/article.xml')) {
            $GLOBALS['JATS_CITATIONS'] = $citations;
            require($this->plugin->getPluginPath() . '/templates/start.html.php');
        } else {
            require($this->plugin->getPluginPath() . '/templates/wizard.html.php');
        }
    }
    public function preview()
    {
        require($this->plugin->getPluginPath() . '/templates/visor.html.php');
    }
    /**
     * Ejecuta docxtojats
     */
    public function convert($textCitations = null, $debug = false)
    {

        $this->generateFromXml();
        $this->clearPublications();
        $workdir = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
        $marked = $this->getMarkedData();
        $opts = $marked['opts'];

        $secs = (array) $marked['secs'];

        $options = [];

        if (!empty($textCitations)) {
            $citationsFile = $workdir . '/src/citations.ref';

            file_put_contents($citationsFile, $textCitations);
            $options['bibliographyFile'] = $citationsFile;
            unset($marked['csl']);
        } else if (!empty($marked['csl'])) {
            $cslPath = $workdir . '/src/csl.json';
            file_put_contents($cslPath, json_encode($marked['csl'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $options['bibliographyFile'] = $cslPath;
        }

        $options['normalize'] = !empty($opts['normalize']);
        $options['automarkStyle'] = $opts['automarkStyle'] ?? null;
        $options['automarkSetMixedCitations'] = !empty($opts['automarkSetMixedCitations']);
        $options['automarkSetFiguresTitles'] = !empty($opts['automarkSetFiguresTitles']);
        $options['automarkSetTablesTitles'] = !empty($opts['automarkSetTablesTitles']);
        $options['automarkSetTitlesReferences'] = !empty($opts['automarkSetTitlesReferences']);
        $options['removeSections'] = !empty($secs) ? array_keys($secs) : [];
        $options['frontXmlFile'] = $workdir . '/src/front.xml';

        try {
            $this->apiClient->convertDocToJats($this->getDocxPath(), $workdir, $options);
        } catch (Exception $e) {
            JatsWizardPlugin::log('ERROR', 'Error from PipelineApiClient in convert()', ['error' => $e->getMessage()]);
            throw new Exception("Error en la conversión: " . $e->getMessage());
        }
        try {

            if (!empty($textCitations)) {
                if (file_exists($workdir . '/article.json')) {
                    $csl = json_decode(file_get_contents($workdir . '/article.json'), true);
                    //unlink($workdir . '/article.json');
                    $this->updateMarkedData(['csl' => $csl]);
                } else {
                    JatsWizardPlugin::log('ERROR', 'Error generating bibliographic references', ['cmdline' => $cmdline]);
                    throw new Exception("Error al generar referencias bibliográficas");
                }
            }
            if (file_exists($workdir . '/article.xml')) {
                $xml = file_get_contents($workdir . '/article.xml');
                $xml = str_replace(" & ", " &amp; ", $xml);
                file_put_contents($workdir . '/article.xml', $xml);
                $jats = new JATSFront($this->getMarkedData('specific-use'), $workdir . '/article.xml');
                $jats->ensureArticleAttributes($this->submission);
                $jats->adjustSpecificUse();
                $jats->removeEmptyNodes();
                $xml = $jats->saveXML();
                file_put_contents($workdir . '/article.xml', $xml);
                //echo $xml;exit;
                return $xml;
            } else {
                throw new Exception("Error al generar JATS XML");
            }
        } catch (Exception $e) {
            JatsWizardPlugin::log('ERROR', 'Exception formatting output', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw new Exception("Error al formatear la respuesta del motor: " . $e->getMessage());
        }
    }
    public function updateMarkedData($data)
    {
        foreach ($data as $k => $v) {
            $_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'][$k] = $v;
        }
        file_put_contents(
            $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'] . '/src/marked_data.json',
            json_encode($_SESSION['jatsWizardStates'][$this->wizardToken]['marked_data'], JSON_PRETTY_PRINT)
        );
    }

    public function uploadDoc($file)
    {
        $workdir = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
        $name = basename($file['name']);
        move_uploaded_file($file['tmp_name'], $workdir . '/src/article.docx');
        $data = $this->getMarkedData();
        $data['version'] += 1;
        $this->updateMarkedData($data);
    }

    public function zipWorkdir()
    {
        // Zip all content of $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
        $work = $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];

        $zipPath = sys_get_temp_dir() . '/' . basename($work) . '.zip';

        // Add all files of workdir to zip respecting structure of dirs
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new Exception("No se pudo crear ZIP de sesión");
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($work),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($work) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        return $zipPath;
    }

    public function getWorkdir()
    {
        return $_SESSION['jatsWizardStates'][$this->wizardToken]['workdir'];
    }


    /**
     * Renderiza template .tpl
     */
    public function render($template, $vars = array())
    {
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        foreach ($vars as $k => $v) {
            $templateMgr->assign($k, $v);
        }
        return $templateMgr->fetch($template);
    }

    public function clean()
    {

        $this->clearWorkdir();
        unset($_SESSION['jatsWizardStates'][$this->wizardToken]);
        //echo "<pre>";print_r($_SESSION['jatsWizardStates'][$this->wizardToken]);echo "</pre>";exit;
    }
}
