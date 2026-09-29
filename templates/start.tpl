<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{translate key="plugins.generic.jatsWizard.start.title"}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="{$assetsUrl}/css/wizard.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-3">
        {if $errorMsg}
            <div class="alert alert-danger" role="alert">
                {$errorMsg}
            </div>
        {/if}
        <h3 class="ojs-file">{$markedDataName}</h3>
        <form id="intial-upload-form" method="post" action="{$engineBaseUrl}&op=start">
            <input type="hidden" name="opts" value="1">
            <fieldset id="new-session">
                <legend>{translate key="plugins.generic.jatsWizard.start.newSession"}</legend>
                <div class="mb-2">
                    <div class="form-check" style="display:none">
                        <input class="form-check-input" type="checkbox" id="normalize" name="normalize" checked>
                        <label class="form-check-label" for="normalize">{translate key="plugins.generic.jatsWizard.start.normalize"}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="do-automark" name="do-automark" checked>
                        <label class="form-check-label" for="do-automark"> {translate key="plugins.generic.jatsWizard.start.automark"}</label> <a href="{$assetsUrl}/doc/#automark" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                    </div>
                    <fieldset id="automark-options">
                        <legend>{translate key="plugins.generic.jatsWizard.start.automarkOptions"}</legend>
                        <div class="form-check">
                            <label class="form-check-label" for="automark-citation-style">{translate key="plugins.generic.jatsWizard.start.citationStyle"} </label>
                            <select class="form-select-input" id="automark-citation-style" name="automark-citation-style">
                                <option value="apa">APA</option>
                                <option value="ama">AMA</option>
                                <option value="vancouver">Vancouver</option>
                            </select> <a href="{$assetsUrl}/doc/#automark-style" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-figures-titles" name="automark-set-figures-titles" checked>
                            <label class="form-check-label" for="automark-set-figures-titles">{translate key="plugins.generic.jatsWizard.start.detectFigures"}</label> <a href="{$assetsUrl}/doc/#figure-titles" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-tables-titles" name="automark-set-tables-titles" checked>
                            <label class="form-check-label" for="automark-set-tables-titles">{translate key="plugins.generic.jatsWizard.start.detectTables"}</label> <a href="{$assetsUrl}/doc/#table-titles" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-title-references" name="automark-set-title-references" checked>
                            <label class="form-check-label" for="automark-set-title-references">{translate key="plugins.generic.jatsWizard.start.replaceTitle"}</label> <a href="{$assetsUrl}/doc/#add-reference-title" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>{translate key="plugins.generic.jatsWizard.start.ruleSets"}</legend>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-bibliography-mixed-citations" name="scielo-specific-use" checked>
                            <label class="form-check-label" for="scielo-specific-use">{translate key="plugins.generic.jatsWizard.start.scielo"}</label> <i class="fa-solid fa-circle-info gray" data-info="scielo"></i>
                        </div>
                    </fieldset>
                </div>
            </fieldset>
            <div class="d-flex justify-content-center my-4">
                <button class="btn btn-primary btn-lg finish" id="start" {if !$hasCitations}disabled{/if}>{translate key="plugins.generic.jatsWizard.start.startBtn"}</button>
                <a style="margin-left: 10px" class="btn" href="{$engineBaseUrl}&op=clean">{translate key="plugins.generic.jatsWizard.start.backBtn"}</a>
            </div>
            <div class="alert alert-danger" role="alert" id="error-message" style="display:{if !$hasCitations}block{else}none{/if};">

                <p><span class="fa fa-exclamation-triangle"></span> <span id="error-text"> {translate key="plugins.generic.jatsWizard.start.noReferences"} </p>
                <p>{translate key="plugins.generic.jatsWizard.start.fillMetadata"}</p>
                <p>
                    </span>
            </div>
        </form>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const doAutomark = document.getElementById('do-automark');
        const automarkOptions = document.getElementById('automark-options');
        doAutomark.addEventListener('change', function() {
            if (this.checked) {
                automarkOptions.style.display = '';
            } else {
                automarkOptions.style.display = 'none';
            }
        });
        document.getElementById('start').addEventListener('click', function(event) {
            event.preventDefault();
            var startButton = document.getElementById('start');
            startButton.innerHTML = '{translate|escape:"javascript" key="plugins.generic.jatsWizard.start.processing"} <span class="fa fa-circle-notch fa-spin"></span>';
            startButton.disabled = true;
            document.getElementById('intial-upload-form').submit();
        });
    </script>
</body>
</html>
