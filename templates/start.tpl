
{**
 * START SCREEN for DOCX submissions
 * This is shown only when the submission file is a DOCX.
 * The user selects initial conversion options.
 * When the form is submitted, it calls:
 *    jatsWizard/engine?submissionFileId=...&opName=uploadInitial
 *}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Asistente conversión XML-JATS</title>

    {* Bootstrap & FontAwesome *}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer" />

    {* Wizard CSS *}
    <link rel="stylesheet" href="{$wizardBaseUrl}/assets/css/wizard.css">
</head>

<body>
<div class="container mt-3">

    {if $errorMessage}
        <div class="alert alert-danger" role="alert">
            {$errorMessage}
        </div>
    {/if}

    <h3>{$submissionFileName|escape}</h3>

    {* FORM: options for initial conversion *}
    <form id="initial-upload-form"
          method="post"
          enctype="multipart/form-data"
          action="{url page="jatsWizard" op="engine"
                   submissionFileId=$submissionFileId
                   opName="uploadInitial"}">

        <fieldset id="new-session">
            <legend>Nueva sesión de marcado</legend>

            <div class="mb-2">

                <div class="form-check" style="display:none">
                    <input class="form-check-input"
                           type="checkbox"
                           id="normalize"
                           name="normalize"
                           checked>
                    <label class="form-check-label" for="normalize">
                        Normalizar documento
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           id="do-automark"
                           name="do-automark"
                           checked>
                    <label class="form-check-label" for="do-automark">
                        Aplicar automarcado de citas bibliográficas
                    </label>
                    <a href="doc/#automark" target="_blank">
                        <i class="fa-solid fa-circle-info gray"></i>
                    </a>
                </div>

                <fieldset id="automark-options">
                    <legend>Opciones de automarcado</legend>

                    <div class="form-check">
                        <label class="form-check-label" for="automark-citation-style">
                            Formato de marcado utilizado:
                        </label>

                        <select class="form-select-input"
                                id="automark-citation-style"
                                name="automark-citation-style">
                            <option value="apa">APA</option>
                            <option value="ama">AMA</option>
                            <option value="vancouver">Vancouver</option>
                        </select>

                        <a href="doc/#automark-style" target="_blank">
                            <i class="fa-solid fa-circle-info gray"></i>
                        </a>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               id="automark-set-figures-titles"
                               name="automark-set-figures-titles"
                               checked>
                        <label class="form-check-label" for="automark-set-figures-titles">
                            Detección de títulos en imágenes
                        </label>
                        <a href="doc/#figure-titles" target="_blank">
                            <i class="fa-solid fa-circle-info gray"></i>
                        </a>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               id="automark-set-tables-titles"
                               name="automark-set-tables-titles"
                               checked>
                        <label class="form-check-label" for="automark-set-tables-titles">
                            Detección de títulos en tablas
                        </label>
                        <a href="doc/#table-titles" target="_blank">
                            <i class="fa-solid fa-circle-info gray"></i>
                        </a>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               id="automark-set-title-references"
                               name="automark-set-title-references"
                               checked>
                        <label class="form-check-label" for="automark-set-title-references">
                            Sustituir el párrafo de título por una referencia
                        </label>
                        <a href="doc/#add-reference-title" target="_blank">
                            <i class="fa-solid fa-circle-info gray"></i>
                        </a>
                    </div>

                </fieldset>

                <fieldset>
                    <legend>Conjuntos de reglas</legend>
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               id="automark-set-bibliography-mixed-citations"
                               name="automark-set-bibliography-mixed-citations"
                               checked>
                        <label class="form-check-label"
                               for="automark-set-bibliography-mixed-citations">
                            Aplicar reglas SciELO
                        </label>
                        <i class="fa-solid fa-circle-info gray"
                           data-info="scielo"></i>
                    </div>
                </fieldset>

            </div>

            <div class="alert alert-danger" id="error-message" style="display:none"></div>

        </fieldset>

        <div class="d-flex justify-content-center my-4">
            <button class="btn btn-primary btn-lg" id="start">Empezar</button>
        </div>

    </form>
</div>

{* JS *}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="{$wizardBaseUrl}/assets/js/config.js"></script>
<script src="{$wizardBaseUrl}/assets/js/api.js"></script>
<script src="{$wizardBaseUrl}/assets/js/wizard.js"></script>

<script>
$(function() {

    $('#start').on('click', function() {
        showLoadingMask('Subiendo fichero...');
    });

    $('#do-automark').on('change', function() {
        if ($(this).is(':checked')) {
            $('#automark-options').show();
        } else {
            $('#automark-options').hide();
        }
    });

});
</script>

</body>
</html>
