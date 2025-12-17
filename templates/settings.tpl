<form class="pkp_form" id="jatsWizardSettingsForm" method="post"
      action="{url router=$smarty.const.ROUTE_COMPONENT
                category="generic" plugin=$pluginName
                   op="manage"
                   verb="saveSettings"}">
    {csrf}

    <div class="formSection">
        <label for="pipelinePath">
            {translate key="plugins.generic.jatsWizard.settings.pipelinePath"}
        </label>
        <input type="text"
               name="pipelinePath"
               id="pipelinePath"
               value="{$pipelinePath|escape}"
               class="pkp_input_text"
               size="60"/>
        <p class="description">
            {translate key="plugins.generic.jatsWizard.settings.pipelinePath.description"}
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
