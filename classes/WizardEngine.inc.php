<?php
import('plugins.generic.jatsWizard.classes.JATSFront');
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
    /** @var string Ruta al binario docxtojats */
    private $converter;
    private $request;
    private $submission;
    private $baseUrl;
    private $plugin;

    public function __construct($request, $plugin)
    {

        $context = $request->getContext();

        $pipelinePath = $plugin->getSetting($context->getId(), 'pipelinePath');
        if ($pipelinePath === null) {
            $this->converter = '/opt/docxtojats-pipeline/bin/console';
        } else {
            $this->converter = $pipelinePath;
        }

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

        if (empty($_SESSION['jatsWizardState'])) {
            $_SESSION['jatsWizardState'] = [
                'sessionId' => $submissionId . '/' . $submissionFileId,
            ];
        } else if ($_SESSION['jatsWizardState']['sessionId'] !== $submissionId . '/' . $submissionFileId) {
            $this->clearWorkdir();
            $_SESSION['jatsWizardState'] = ['sessionId' => $submissionId . '/' . $submissionFileId];
        }

        $_SESSION['jatsWizardState']['engineBaseUrl'] = $this->request->getDispatcher()->url(
            $this->request,
            ROUTE_PAGE,
            null,
            'jatsWizard',
            'engine',
            null,
            array(
                'submissionId' => $submissionId,
                'submissionFileId' => $submissionFileId,
            )
        );

        if (isset($_SESSION['jatsWizardState']['workdir']) && is_dir($_SESSION['jatsWizardState']['workdir'])) {
            return $_SESSION['jatsWizardState']['workdir'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'jatswiz-');
        if (file_exists($tmp)) {
            unlink($tmp);
        }
        mkdir($tmp, 0777, true);
        mkdir($tmp . '/src', 0777, true);
        $_SESSION['jatsWizardState']['workdir'] = $tmp;
        return $tmp;
    }
    public function clearWorkdir()
    {
        if (isset($_SESSION['jatsWizardState']['workdir']) && is_dir($_SESSION['jatsWizardState']['workdir'])) {
            $this->_deleteDir($_SESSION['jatsWizardState']['workdir']);
            unset($_SESSION['jatsWizardState']['workdir']);
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
        return $_SESSION['jatsWizardState']['marked_data']['name'];
    }
    public function getMarkedData($part = null)
    {
        if ($part === null) {
            return $_SESSION['jatsWizardState']['marked_data'];
        }
        return isset($_SESSION['jatsWizardState']['marked_data'][$part]) ? $_SESSION['jatsWizardState']['marked_data'][$part] : null;
    }
    public function getDocxPath()
    {
        return $_SESSION['jatsWizardState']['workdir'] . '/src/article.docx';
    }
    public function getXmlPath()
    {
        return $_SESSION['jatsWizardState']['workdir'] . '/article.xml';
    }
    public function getImagePath($img)
    {
        return $_SESSION['jatsWizardState']['workdir'] . '/' . $img;
    }
    public function clearPublications()
    {
        $workdir = $_SESSION['jatsWizardState']['workdir'];
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
        $cmd = array();
        $cmd[] = escapeshellcmd($this->converter);
        $cmd[] = 'jats:publish';
        $cmd[] = escapeshellarg($this->getXmlPath());
        $cmdline = implode(' ', $cmd) . " 2>&1; echo $?";
        shell_exec($cmdline);
        if (!file_exists($this->getWorkdir() . '/article.' . $format)) {
            echo "Error al generar archivos de publicación en formato " . $format;
            exit;
        }
    }
    public function loadDocx($docxPath, $submissionName)
    {
        copy($docxPath, $_SESSION['jatsWizardState']['workdir'] . '/src/article.docx');
        $marked_data = array(
            'name' => $submissionName,
            'csl' => array(),
            'version' => 1,
            'secs' => array(),
            'opts' => array(),
        );
        file_put_contents(
            $_SESSION['jatsWizardState']['workdir'] . '/src/marked_data.json',
            json_encode($marked_data, JSON_PRETTY_PRINT)
        );
        $_SESSION['jatsWizardState']['marked_data'] = $marked_data;
    }


    /**
     * Procesa un ZIP antiguo o nuevo
     */
    public function loadZip($zipPath)
    {
        $workdir = $_SESSION['jatsWizardState']['workdir'];

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception("No se pudo abrir ZIP"); //TODO: Interacionalizar
        }
        $zip->extractTo($workdir);
        $zip->close();

        try {
            $marked = json_decode(file_get_contents($workdir . '/src/marked_data.json'), true);
        } catch (Exception $e) {
            throw new Exception("El ZIP no contiene una sesión válida");
        }
        $_SESSION['jatsWizardState']['marked_data'] = $marked;
    }

    public function generateFromXml($return = false)
    {
        $workdir = $_SESSION['jatsWizardState']['workdir'];

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
        $workdir = $_SESSION['jatsWizardState']['workdir'];
        if (!file_exists($workdir . '/article.xml')) {
            $_GLOBALS['JATS_CITATIONS'] = $citations;
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
        $workdir = $_SESSION['jatsWizardState']['workdir'];
        $marked = $this->getMarkedData();
        $opts = $marked['opts'];

        $secs = (array) $marked['secs'];

        $cmd = array();
        $cmd[] = escapeshellcmd($this->converter);
        $cmd[] = 'doc:tojats';

        if (!empty($textCitations)) {
            $citationsFile = $workdir . '/src/citations.ref';

            file_put_contents($citationsFile, $textCitations);
            $cmd[] = '--bibliography-file=' . escapeshellarg($citationsFile);
            unset($marked['csl']);
        } else if (!empty($marked['csl'])) {
            $cslPath = $workdir . '/src/csl.json';
            file_put_contents($cslPath, json_encode($marked['csl'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $cmd[] = '--bibliography-file=' . escapeshellarg($cslPath);
        }

        if (!empty($opts['normalize']))
            $cmd[] = '--normalize';
        if (!empty($opts['automarkStyle']))
            $cmd[] = '--citation-style=' . $opts['automarkStyle'];
        if (!empty($opts['automarkSetMixedCitations']))
            $cmd[] = '--set-bibliography-mixed-citations';
        if (!empty($opts['automarkSetFiguresTitles']))
            $cmd[] = '--set-figures-titles';
        if (!empty($opts['automarkSetTablesTitles']))
            $cmd[] = '--set-tables-titles';
        if (!empty($opts['automarkSetTitlesReferences']))
            $cmd[] = '--replace-titles-with-references';

        $cmd[] = '--front-file ' . $workdir . '/src/front.xml';
        $cmd[] = '-o';
        if (!empty($secs)) {
            foreach (array_keys($secs) as $s) {
                $cmd[] = '--remove-sections=' . $s;
            }
        }

        $cmd[] = escapeshellarg($this->getDocxPath());
        $cmd[] = escapeshellarg($workdir);

        $cmdline = implode(' ', $cmd) . " 2>&1; echo $?";
        if ($debug) {
            echo "<pre>Executing command:\n" . htmlspecialchars($cmdline) . "</pre>";
            echo "<pre>";
            print_r($_SESSION['jatsWizardState']);
            exit;
        }
        shell_exec($cmdline);

        if (!empty($textCitations)) {
            $csl = json_decode(file_get_contents($workdir . '/article.json'), true);
            unlink($workdir . '/article.json');
            $this->updateMarkedData(['csl' => $csl]);
        }
        if (file_exists($workdir . '/article.xml')) {
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
    }
    public function updateMarkedData($data)
    {
        foreach ($data as $k => $v) {
            $_SESSION['jatsWizardState']['marked_data'][$k] = $v;
        }
        file_put_contents(
            $_SESSION['jatsWizardState']['workdir'] . '/src/marked_data.json',
            json_encode($_SESSION['jatsWizardState']['marked_data'], JSON_PRETTY_PRINT)
        );

    }

    public function uploadDoc($file)
    {
        $workdir = $_SESSION['jatsWizardState']['workdir'];
        $name = basename($file['name']);
        move_uploaded_file($file['tmp_name'], $workdir . '/src/article.docx');
        $data = $this->getMarkedData();
        $data['version'] += 1;
        $this->updateMarkedData($data);
    }

    public function zipWorkdir()
    {
        // Zip all content of $_SESSION['jatsWizardState']['workdir'];
        $work = $_SESSION['jatsWizardState']['workdir'];

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
        return $_SESSION['jatsWizardState']['workdir'];
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
        unset($_SESSION['jatsWizardState']);
        //echo "<pre>";print_r($_SESSION['jatsWizardState']);echo "</pre>";exit;
    }
}
