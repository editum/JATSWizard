<?php

/**
 * @file plugins/generic/jatsWizard/JatsWizardPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University Library
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief main class of the DOCX to JATS XML Converter Plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class JatsWizardPlugin extends GenericPlugin
{
	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName()
	{
		return __('plugins.generic.jatsWizard.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription()
	{
		return __('plugins.generic.jatsWizard.description');
	}
public function getActions($request, $verb)
{
	
    $actions = parent::getActions($request, $verb);

    if (!$this->getEnabled()) {
        return $actions;
    }

    $router = $request->getRouter();

    $actions[] = 				new LinkAction(
					'settings',
					new AjaxModal($router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')), $this->getDisplayName()),
					__('manager.plugins.settings'),
					null
	);

    return $actions;
}
public function manage($args, $request)
{
    switch ($request->getUserVar('verb')) {
        case 'settings':
            return $this->settings($args, $request);
        case 'saveSettings':
			
            return $this->saveSettings($args, $request);
    }
    return parent::manage($args, $request);
}
public function settings($args, $request)
{
    $templateMgr = TemplateManager::getManager($request);

    $context = $request->getContext();

    $pipelineUrl = $this->getSetting($context->getId(), 'pipelineUrl');
    $templateMgr->assign([
        'pipelineUrl' => $pipelineUrl ?: ''
    ]);

	$templateMgr->assign('pluginName', $this->getName());

    return new JSONMessage(
        true,
        $templateMgr->fetch(
            $this->getTemplateResource('settings.tpl')
        )
    );
}
public function saveSettings($args, $request)
{
    $context = $request->getContext();

    $pipelineUrl = trim((string) $request->getUserVar('pipelineUrl'));
    if ($pipelineUrl !== '') {
        import('plugins.generic.jatsWizard.classes.PipelineApiClient');
        if (!PipelineApiClient::isValidBaseUrl($pipelineUrl)) {
            return new JSONMessage(false, __('plugins.generic.jatsWizard.settings.pipelineUrl.invalid'));
        }
    }

    $this->updateSetting(
        $context->getId(),
        'pipelineUrl',
        $pipelineUrl,
        'string'
    );

    return new JSONMessage(true);
}

	/**
	 * Register the plugin
	 *
	 * @param $category string Plugin category
	 * @param $path string Plugin path
	 * @param $mainContextId ?integer
	 * @return bool True on successful registration false otherwise
	 */
	function register($category, $path, $mainContextId = null)
	{
		
		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled($mainContextId)) {
				$this->addLocaleData();
				// Register callbacks.
				HookRegistry::register('TemplateManager::fetch', array($this, 'templateFetchCallback'));
				HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
				HookRegistry::register('ArticleHandler::view::galley', array($this, 'epubGalleyViewCallback'));
				HookRegistry::register('Schema::get::galley', function ($hookName, $args) {
					$args[0]->properties->jvValidation = (object) ['type' => 'string', 'apiSummary' => false, 'validation' => ['nullable']];
					return false;
				});
				$this->_registerTemplateResource();
			}
			return true;
		}
		return false;
	}

	/**
	 * Shows ePub galleys in the embedded epub.js reader instead of forcing a download.
	 */
	function epubGalleyViewCallback($hookName, $args)
	{
		$request = $args[0];
		$issue = $args[1];
		$galley = $args[2];
		$article = $args[3];
		if (!$galley || !$galley->getFile() || $galley->getFile()->getData('mimetype') !== 'application/epub+zip') {
			return false;
		}
		$galleyPublication = null;
		foreach ($article->getData('publications') as $publication) {
			if ($publication->getId() === $galley->getData('publicationId')) {
				$galleyPublication = $publication;
				break;
			}
		}
		if (!$galleyPublication) {
			return false;
		}
		$isLatest = $article->getData('currentPublicationId') === $galley->getData('publicationId');
		$path = $isLatest ? [$article->getBestId(), $galley->getBestGalleyId()] : [$article->getBestId(), 'version', $galleyPublication->getId(), $galley->getBestGalleyId()];
		$validation = json_decode((string) $galley->getData('jvValidation'), true);
		$validation = is_array($validation) ? $validation : [];
		$epubcheck = is_array($validation['epubcheck'] ?? null) ? $validation['epubcheck'] : [];
		$ace = is_array($validation['ace'] ?? null) ? $validation['ace'] : [];
		$templateMgr = TemplateManager::getManager($request);
		$assetsPath = Core::getBaseDir() . '/' . $this->getPluginPath() . '/assets';
		$templateMgr->assign([
			'issue' => $issue,
			'article' => $article,
			'galley' => $galley,
			'galleyPublication' => $galleyPublication,
			'epubUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'article', 'download', $path),
			'epubAssetsUrl' => $request->getBaseUrl() . '/' . $this->getPluginPath() . '/assets/vendor/',
			'epubLogoUrl' => $request->getBaseUrl() . '/' . $this->getPluginPath() . '/assets/selloUMU.png?v=' . filemtime($assetsPath . '/selloUMU.png'),
			'epubReaderCssUrl' => $request->getBaseUrl() . '/' . $this->getPluginPath() . '/assets/epubReader.css?v=' . filemtime($assetsPath . '/epubReader.css'),
			'epubReaderJsUrl' => $request->getBaseUrl() . '/' . $this->getPluginPath() . '/assets/epubReader.js?v=' . filemtime($assetsPath . '/epubReader.js'),
			'epubcheckStatus' => $epubcheck['status'] ?? 'unavailable',
			'epubcheckVersion' => $epubcheck['version'] ?? '',
			'epubcheckErrors' => $epubcheck['errors'] ?? 0,
			'epubcheckWarnings' => $epubcheck['warnings'] ?? 0,
			'daisyAceStatus' => $ace['status'] ?? 'unavailable',
			'daisyAceVersion' => $ace['version'] ?? '',
			'daisyAceIssues' => $ace['issues'] ?? 0,
			'epubValidationDate' => !empty($validation['date']) ? date('Y-m-d', strtotime($validation['date'])) : '',
		]);
		$templateMgr->display($this->getTemplateResource('epubReader.tpl'));
		return true;
	}

	/**
	 * Get plugin URL
	 * @param $request PKPRequest
	 * @return string
	 */
	function getPluginUrl($request)
	{
		return $request->getBaseUrl() . '/' . $this->getPluginPath();
	}

	public function callbackLoadHandler($hookName, $args)
	{
		$page = $args[0];
		$op = $args[1];


		if ($page == "jatsWizard" && ($op == 'wizard' || $op == 'finish' || $op == 'unpackhtml' || $op == 'downloadhtmlzip' || $op == 'unpackxml' || $op == 'engine' || $op == 'saveMark' || $op == 'createGalley' || $op == 'createGalleyForm' || $op == 'flipbookForm' || $op == 'createFlipbook' || $op == 'revalidateEpubForm' || $op == 'revalidateEpub')) {
			define('HANDLER_CLASS', 'WizardHandler');
			define('JATS_WIZARD_PLUGIN_NAME', $this->getName());
			$args[2] = $this->getPluginPath() . '/' . 'WizardHandler.inc.php';
			return false;
		}
		return false;
	}

	/**
	 * Adds additional links to submission files grid row
	 * @param $hookName string The name of the invoked hook
	 * @param $args array Hook parameters
	 */
	public function templateFetchCallback($hookName, $params)
	{
		$request = $this->getRequest();
		$dispatcher = $request->getDispatcher();

		$templateMgr = $params[0];
		$resourceName = $params[1];
		
		if ($resourceName == 'controllers/grid/gridRow.tpl') {
			/* @var $row GridRow */
			$row = $templateMgr->getTemplateVars('row');
			$data = $row->getData();
			if (is_array($data) && (isset($data['submissionFile']))) {
				
				$submissionFile = $data['submissionFile'];
				$fileExtension = strtolower($submissionFile->getData('mimetype'));
				self::log('INFO', 'Checking file extension for wizard action', ['extension' => $fileExtension]);
				// Ensure that the conversion is run on the appropriate workflow stage
				$stageId = (int) $request->getUserVar('stageId');
				$submissionId = $submissionFile->getData('submissionId');
				$submission = Services::get('submission')->get($submissionId);
				/** @var $submission Submission */
				$submissionStageId = $submission->getData('stageId');
				$roles = $request->getUser()->getRoles($request->getContext()->getId());

				$accessAllowed = false;
				foreach ($roles as $role) {
					if (in_array($role->getId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT])) {
						$accessAllowed = true;
						break;
					}
				}
				if (
					$fileExtension === 'application/pdf' &&
					$accessAllowed &&
					in_array($stageId, $this->getAllowedWorkflowStages()) &&
					in_array($submissionStageId, $this->getAllowedWorkflowStages())
				) {
					$this->_addFlipbookAction($row, $dispatcher, $request, [
						'submissionId' => $submissionId,
						'publicationId' => $submission->getData('currentPublicationId'),
						'stageId' => $stageId,
						'sourceType' => 'file',
						'sourceFileId' => $submissionFile->getId(),
					], 'createFlipbookFromSubmissionPdf');
				}
				if (
					in_array(strtolower($fileExtension), static::getSupportedMimetypes()) && // show only for files with docx extension
					$accessAllowed && // only for those that have access according to the DOCXConverterHandler rules
					in_array($stageId, $this->getAllowedWorkflowStages()) && // only for stage ids copyediting or higher
					in_array($submissionStageId, $this->getAllowedWorkflowStages()) // only if submission has correspondent stage id
				) {
					// Add the link to the wizard
					if ($fileExtension == 'application/zip') {

						$urlUnpack = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'unpackxml', null, [
							'submissionFileId' => $submissionFile->getId(),
							'submissionId' => $submissionId,
							'stageId' => $stageId
						]);

						$urlUnpackHtml = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'unpackhtml', null, [
							'submissionFileId' => $submissionFile->getId(),
							'submissionId' => $submissionId,
							'stageId' => $stageId
						]);


						$urlDownloadZip = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'downloadhtmlzip', null, [
							'submissionFileId' => $submissionFile->getId(),
							'submissionId' => $submissionId,
							'stageId' => $stageId
						]);

						$unpackAction = new LinkAction(
							'unpackxml',
							new RedirectAction($urlUnpack),
							__('plugins.generic.jatsWizard.button.jatsExport')
						);

						$unpackHtmlAction = new LinkAction(
							'unpackhtml',
							new RedirectAction($urlUnpackHtml),
							__('plugins.generic.jatsWizard.button.htmlExport')
						);

						$row->addAction($unpackAction);
						$row->addAction($unpackHtmlAction);
						$row->addAction(new LinkAction(
							'downloadhtmlzip',
							new RedirectAction($urlDownloadZip),
							__('plugins.generic.jatsWizard.button.htmlZip')
						));
					}
					$urlWizard = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'wizard', null, [
						'submissionFileId' => $submissionFile->getId(),
						'submissionId' => $submissionId,
						'stageId' => $stageId
					]);

					$wizardAction = new LinkAction(
						'wizard',
						new RedirectAction($urlWizard),
						__('plugins.generic.jatsWizard.button.launchWizard')
					);

					$row->addAction($wizardAction);
				} else 	if (strtolower($fileExtension) == 'text/xml') {
					$fileStage = SUBMISSION_FILE_PRODUCTION_READY;
					import('lib.pkp.classes.linkAction.request.OpenWindowAction');
					//$this->_editWithTextureAction($row, $dispatcher, $request, $submissionFile, $stageId);
					$this->_createGalleyAction($row, $dispatcher, $request, $submissionFile, $stageId, $fileStage);
				}
			} elseif (is_object($data) && method_exists($data, 'getFile') && $data->getFile() &&
				$data->getFile()->getData('mimetype') === 'application/pdf') {
				$roles = $request->getUser()->getRoles($request->getContext()->getId());
				$accessAllowed = false;
				foreach ($roles as $role) {
					if (in_array($role->getId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT])) {
						$accessAllowed = true;
						break;
					}
				}
				$submissionId = (int) $request->getUserVar('submissionId');
				$publicationId = (int) $request->getUserVar('publicationId');
				if ($accessAllowed && $submissionId && $publicationId) {
					$this->_addFlipbookAction($row, $dispatcher, $request, [
						'submissionId' => $submissionId,
						'publicationId' => $publicationId,
						'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
						'sourceType' => 'galley',
						'sourceGalleyId' => $data->getId(),
					], 'createFlipbookFromPdfGalley');
				}
			} elseif (is_object($data) && method_exists($data, 'getFile') && $data->getFile() &&
				$data->getFile()->getData('mimetype') === 'application/epub+zip') {
				$roles = $request->getUser()->getRoles($request->getContext()->getId());
				$accessAllowed = false;
				foreach ($roles as $role) {
					if (in_array($role->getId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT])) {
						$accessAllowed = true;
						break;
					}
				}
				$submissionId = (int) $request->getUserVar('submissionId');
				$publicationId = (int) $request->getUserVar('publicationId');
				if ($accessAllowed && $submissionId && $publicationId) {
					$url = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'revalidateEpubForm', null, [
						'submissionId' => $submissionId,
						'publicationId' => $publicationId,
						'galleyId' => $data->getId(),
						'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
					]);
					$row->addAction(new LinkAction(
						'revalidateEpub',
						new AjaxModal($url, __('plugins.generic.jatsWizard.epub.validationTitle')),
						__('plugins.generic.jatsWizard.epub.revalidate'),
						null
					));
				}
			}
		}
	}

	private function _addFlipbookAction($row, Dispatcher $dispatcher, PKPRequest $request, array $actionArgs, $actionName): void
	{
		$url = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'flipbookForm', null, $actionArgs);
		$row->addAction(new LinkAction(
			$actionName,
			new AjaxModal($url, __('plugins.generic.jatsWizard.flipbook.confirmTitle')),
			__('plugins.generic.jatsWizard.flipbook.create'),
			null
		));
	}

	private function _createGalleyAction($row, Dispatcher $dispatcher, PKPRequest $request, $submissionFile, int $stageId, int $fileStage): void {

		$actionArgs = array(
			'submissionId' => $submissionFile->getData('submissionId'),
			'stageId' => $stageId,
			'fileStage' => $fileStage,
			'submissionFileId' => $submissionFile->getData('id')
		);
		$row->addAction(new LinkAction(
			'createGalleyForm',
			new AjaxModal(
				$dispatcher->url(
					$request, ROUTE_PAGE, null,
					'jatsWizard',
					'createGalleyForm', null,
					$actionArgs
				),
				__('submission.layout.newGalley')
			),
			__('plugins.generic.jatsWizard.links.createGalley'),
			null
		));
	}

	public function getAllowedWorkflowStages()
	{
		return [
			WORKFLOW_STAGE_ID_EDITING,
			WORKFLOW_STAGE_ID_PRODUCTION
		];
	}

	/**
	 * Write a structured log message to the PHP error log.
	 * 
	 * @param string $level Log level (e.g., 'INFO', 'DEBUG', 'ERROR')
	 * @param string $message The log message
	 * @param array $context Additional context data to be JSON encoded
	 */
	public static function log($level, $message, $context = [])
	{
		$logLevel = Config::getVar('jats_wizard', 'log_level', 'INFO');
		if (strtoupper($level) === 'DEBUG' && strtoupper($logLevel) !== 'DEBUG') {
			return;
		}

		$logMessage = sprintf('[JATSWizard] [%s] %s', strtoupper($level), $message);
		if (!empty($context)) {
			$logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
		}
		error_log($logMessage);
	}

	/**
	 * @return string[] MIME type supported by the plugin for conversion
	 */
	public static function getSupportedMimetypes()
	{
		return [
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/zip'
		];
	}
}
