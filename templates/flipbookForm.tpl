{** JATS Wizard FlipBook galley creation form. *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#jatsWizardFlipbookForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="jatsWizardFlipbookForm" method="post" action="{url op="createFlipbook" submissionId=$submissionId publicationId=$publicationId stageId=$stageId sourceType=$sourceType sourceFileId=$sourceFileId sourceGalleyId=$sourceGalleyId}">
	{csrf}
	{fbvFormArea id="jatsWizardFlipbookFormArea"}
		{fbvFormSection title="plugins.generic.jatsWizard.flipbook.source"}
			<p>{$sourceName|escape}</p>
		{/fbvFormSection}
		{fbvFormSection title="submission.layout.galleyLabel" required=true}
			{fbvElement type="text" label="submission.layout.galleyLabelInstructions" value=$label|default:"FlipBook" id="label" size=$fbvStyles.size.MEDIUM inline=true required=true}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="select" id="galleyLocale" label="common.language" from=$supportedLocales selected=$galleyLocale|default:$formLocale size=$fbvStyles.size.MEDIUM translate=false inline=true required=true}
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormButtons submitText="plugins.generic.jatsWizard.flipbook.create"}
</form>
