<form class="pkp_form" id="jatsWizardSettingsForm" method="post"
      action="{url router=$smarty.const.ROUTE_COMPONENT
                category="generic" plugin=$pluginName
                   op="manage"
                   verb="saveSettings"}">
    {csrf}

    <div class="formSection">
        <label for="pipelineUrl">
            {translate key="plugins.generic.jatsWizard.settings.pipelineUrl"}
        </label>
        <input type="text"
               name="pipelineUrl"
               id="pipelineUrl"
               value="{$pipelineUrl|escape}"
               class="pkp_input_text"
               size="60"/>
        <p class="description">
            {translate key="plugins.generic.jatsWizard.settings.pipelineUrl.description"}
        </p>
    </div>

    <div class="formButtons">
        <button class="pkp_button submitFormButton" type="submit">
            {translate key="common.save"}
        </button>
    </div>
</form>

<script>
$(function () {
    $('#jatsWizardSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
});
</script>
