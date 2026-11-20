<script type="text/javascript">
	$(function() {ldelim}
		$('#jatsWizardEpubValidationForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="jatsWizardEpubValidationForm" method="post" action="{url op="revalidateEpub" submissionId=$submissionId publicationId=$publicationId galleyId=$galleyId stageId=$stageId}">
	{csrf}
	{fbvFormArea id="jatsWizardEpubValidationFormArea"}
		{fbvFormSection title="plugins.generic.jatsWizard.epub.validationSource"}
			<p>{$galleyLabel|escape}: {$fileName|escape}</p>
		{/fbvFormSection}
		{fbvFormSection}
			<p>{translate key="plugins.generic.jatsWizard.epub.validationDescription"}</p>
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormButtons submitText="plugins.generic.jatsWizard.epub.revalidate"}
</form>