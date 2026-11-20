<?php

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.file.PrivateFileManager');

class JatsEpubValidationForm extends Form
{
    private $galley;
    private $file;

    public function __construct($plugin, $galley, $file)
    {
        $this->galley = $galley;
        $this->file = $file;
        parent::__construct($plugin->getTemplateResource('epubValidationForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function fetch($request, $template = null, $display = false)
    {
        TemplateManager::getManager($request)->assign([
            'galleyLabel' => $this->galley->getLabel(),
            'fileName' => $this->file->getLocalizedData('name'),
            'submissionId' => $request->getUserVar('submissionId'),
            'publicationId' => $this->galley->getData('publicationId'),
            'galleyId' => $this->galley->getId(),
            'stageId' => $request->getUserVar('stageId'),
        ]);
        return parent::fetch($request, $template, $display);
    }

    public function readInputData()
    {
    }

    public function execute(...$functionArgs)
    {
        $path = (new PrivateFileManager())->getBasePath() . DIRECTORY_SEPARATOR . $this->file->getData('path');
        if (!is_file($path) || !is_readable($path)) {
            throw new Exception('The selected ePub file is unavailable.');
        }

        import('plugins.generic.jatsWizard.classes.JatsEpubValidator');
        @set_time_limit(600);
        $this->galley->setData('jvValidation', json_encode(JatsEpubValidator::validate($path), JSON_UNESCAPED_SLASHES));
        DAORegistry::getDAO('ArticleGalleyDAO')->updateObject($this->galley);
    }
}