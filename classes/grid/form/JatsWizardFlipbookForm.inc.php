<?php

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.file.PrivateFileManager');
import('lib.pkp.classes.submission.SubmissionFile');

class JatsWizardFlipbookForm extends Form
{
    private $submission;
    private $publication;
    private $sourceFile;
    private $plugin;

    public function __construct($request, $plugin, $submission, $publication, $sourceFile)
    {
        $this->submission = $submission;
        $this->publication = $publication;
        $this->sourceFile = $sourceFile;
        $this->plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('flipbookForm.tpl'));
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_SUBMISSION);
        $this->addCheck(new FormValidator($this, 'label', 'required', 'editor.issues.galleyLabelRequired'));
        $this->addCheck(new FormValidator($this, 'galleyLocale', 'required', 'editor.issues.galleyLocaleRequired'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function fetch($request, $template = null, $display = false)
    {
        $context = $request->getJournal();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'supportedLocales' => $context->getSupportedSubmissionLocaleNames(),
            'submissionId' => $this->submission->getId(),
            'publicationId' => $this->publication->getId(),
            'sourceName' => $this->sourceFile->getLocalizedData('name'),
            'sourceType' => $request->getUserVar('sourceType'),
            'sourceFileId' => $request->getUserVar('sourceFileId'),
            'sourceGalleyId' => $request->getUserVar('sourceGalleyId'),
            'stageId' => $request->getUserVar('stageId'),
        ]);

        return parent::fetch($request, $template, $display);
    }

    public function readInputData()
    {
        $this->readUserVars(['label', 'galleyLocale']);
    }

    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $sourceBasePath = (new PrivateFileManager())->getBasePath();
        $sourcePath = $sourceBasePath . DIRECTORY_SEPARATOR . $this->sourceFile->getData('path');
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new Exception('The selected PDF is not available.');
        }

        $submissionDir = Services::get('submissionFile')->getSubmissionDir(
            $this->submission->getData('contextId'),
            $this->submission->getId()
        );
        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
        $galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
        $htmlTmp = tempnam(sys_get_temp_dir(), 'jv-flipbook-');
        if ($htmlTmp === false) {
            throw new Exception('Could not create the FlipBook HTML file.');
        }

        $pdfRelativePath = $submissionDir . DIRECTORY_SEPARATOR . uniqid('flipbook-', true) . '.pdf';
        $pdfFileId = null;
        $htmlFileId = null;
        $galley = null;
        $mainSubmissionFile = null;
        $pdfSubmissionFile = null;

        try {
            $pdfFileId = Services::get('file')->add($sourcePath, $pdfRelativePath);
            $assetBase = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/assets/';
            $pdfPlugin = PluginRegistry::getPlugin('generic', 'pdfjsviewerplugin');
            if (!$pdfPlugin) {
                throw new Exception('The OJS PDF.js viewer plugin is unavailable.');
            }
            $flipbookScriptPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'flipbook' . DIRECTORY_SEPARATOR . 'flipbook.js';
            $flipbookAssetVersion = is_file($flipbookScriptPath) ? (string) filemtime($flipbookScriptPath) : '1';
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'lang' => substr($this->submission->getLocale(), 0, 2),
                'pdfJsViewerUrl' => $request->getBaseUrl() . '/' . $pdfPlugin->getPluginPath() . '/pdf.js/web/viewer.html',
                'flipbookAssetsUrl' => $assetBase . 'flipbook/',
                'flipbookAssetVersion' => $flipbookAssetVersion,
            ]);
            $html = $templateMgr->fetch($this->plugin->getTemplateResource('flipbookArticle.tpl'));
            if (file_put_contents($htmlTmp, $html) === false) {
                throw new Exception('Could not write the FlipBook HTML file.');
            }

            $galley = $galleyDao->newDataObject();
            $galley->setData('publicationId', $this->publication->getId());
            $galley->setLabel($this->getData('label'));
            $galley->setLocale($this->getData('galleyLocale'));
            $galley = Services::get('galley')->add($galley, $request);

            $htmlFileId = Services::get('file')->add(
                $htmlTmp,
                $submissionDir . DIRECTORY_SEPARATOR . uniqid('flipbook-', true) . '.html'
            );
            $locale = $this->getData('galleyLocale');
            $htmlName = [$locale => 'flipbook.html'];
            $mainSubmissionFile = $submissionFileDao->newDataObject();
            $mainSubmissionFile->setAllData([
                'fileId' => $htmlFileId,
                'assocType' => ASSOC_TYPE_REPRESENTATION,
                'assocId' => $galley->getId(),
                'fileStage' => SUBMISSION_FILE_PROOF,
                'uploaderUserId' => $request->getUser()->getId(),
                'mimetype' => 'text/html',
                'locale' => $locale,
                'genreId' => $this->sourceFile->getData('genreId'),
                'name' => $htmlName,
                'submissionId' => $this->submission->getId(),
            ]);
            $mainSubmissionFile = Services::get('submissionFile')->add($mainSubmissionFile, $request);
            $galley->setFileId($mainSubmissionFile->getId());
            $galleyDao->updateObject($galley);

            $pdfSubmissionFile = $submissionFileDao->newDataObject();
            $pdfSubmissionFile->setAllData([
                'fileId' => $pdfFileId,
                'assocType' => ASSOC_TYPE_SUBMISSION_FILE,
                'assocId' => $mainSubmissionFile->getId(),
                'fileStage' => SUBMISSION_FILE_DEPENDENT,
                'uploaderUserId' => $request->getUser()->getId(),
                'mimetype' => 'application/pdf',
                'locale' => $locale,
                'genreId' => $this->sourceFile->getData('genreId'),
                'name' => [$locale => 'flipbook-source.pdf'],
                'submissionId' => $this->submission->getId(),
            ]);
            $pdfSubmissionFile = Services::get('submissionFile')->add($pdfSubmissionFile, $request);

            return $galley;
        } catch (Throwable $e) {
            $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
            if ($pdfSubmissionFile && $pdfSubmissionFile->getId()) {
                $submissionFileDao->deleteById($pdfSubmissionFile->getId());
            }
            if ($mainSubmissionFile && $mainSubmissionFile->getId()) {
                $submissionFileDao->deleteById($mainSubmissionFile->getId());
            }
            if ($galley && $galley->getId()) {
                Services::get('galley')->delete($galley);
            }
            if ($htmlFileId) {
                Services::get('file')->delete($htmlFileId);
            }
            if ($pdfFileId) {
                Services::get('file')->delete($pdfFileId);
            }
            throw $e;
        } finally {
            if (is_file($htmlTmp)) {
                unlink($htmlTmp);
            }
        }
    }
}
