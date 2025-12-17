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

	$pipelinePath = $this->getSetting($context->getId(), 'pipelinePath');
	if ($pipelinePath === null) {
		$pipelinePath = '/opt/docxtojats-pipeline/bin/console';
	}
    $templateMgr->assign([
        'pipelinePath' => $pipelinePath
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

    $pipelinePath = $request->getUserVar('pipelinePath');

    $this->updateSetting(
        $context->getId(),
        'pipelinePath',
        $pipelinePath,
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
			if ($this->getEnabled()) {
				// Register callbacks.
				HookRegistry::register('TemplateManager::fetch', array($this, 'templateFetchCallback'));
				HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
				$this->_registerTemplateResource();
			}
			return true;
		}
		return false;
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


		if ($page == "jatsWizard" && ($op == 'wizard' || $op == 'finish' || $op == 'unpackhtml' || $op == 'unpackxml' || $op == 'engine' || $op == 'saveMark' || $op == 'createGalley' || $op == 'createGalleyForm')) {
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
				syslog(LOG_INFO, 'EXTENSION: ' . $fileExtension);
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
					in_array(strtolower($fileExtension), static::getSupportedMimetypes()) && // show only for files with docx extension
					$accessAllowed && // only for those that have access according to the DOCXConverterHandler rules
					in_array($stageId, $this->getAllowedWorkflowStages()) && // only for stage ids copyediting or higher
					in_array($submissionStageId, $this->getAllowedWorkflowStages()) // only if submission has correspondent stage id
				) {
					// Add the link to the wizard
					if ($fileExtension == 'application/zip') {

						$urlUnpack = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'unpack', null, [
							'submissionFileId' => $submissionFile->getId(),
							'submissionId' => $submissionId,
							'stageId' => $stageId
						]);

						$urlUnpackHtml = $dispatcher->url($request, ROUTE_PAGE, null, 'jatsWizard', 'unpackhtml', null, [
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
			}
		}
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
	 * @return string[] MIME type supported by the plugin for conversion
	 */
	public static function getSupportedMimetypes()
	{
		return [
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/zip',
			// OJS identifies Google Docs files exported in DOCX format as having this MIME type
			'application/vnd.openxmlformats-officedocument.wordprocessingml.documentapplication/vnd.openxmlformats-officedocument.wordprocessingml.document'
		];
	}
}
