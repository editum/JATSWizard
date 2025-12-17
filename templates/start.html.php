<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente conversión XML-JATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="<?= JATSWIZARD_ASSETS_URL ?>/css/wizard.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-3">
        <?php if (!empty($ERROR)): ?>
            <div class="alert alert-danger" role="alert">
                <?= $ERROR ?>
            </div>
        <?php endif; ?>
        <h3 class="ojs-file"><?= $_SESSION['jatsWizardState']['marked_data']['name'] ?></h3>
        <form id="intial-upload-form" method="post" action="<?= $_SESSION['jatsWizardState']['engineBaseUrl'] ?>&op=start">
            <input type="hidden" name="opts" value="1">
            <fieldset id="new-session">
                <legend>Nueva sesión de marcado</legend>
                <div class="mb-2">
                    <div class="form-check" style="display:none">
                        <input class="form-check-input" type="checkbox" id="normalize" name="normalize" checked>
                        <label class="form-check-label" for="normalize">Normalizar documento</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="do-automark" name="do-automark" checked>
                        <label class="form-check-label" for="do-automark"> Aplicar automarcado de citas bibliográficas</label> <a href="<?= JATSWIZARD_ASSETS_URL ?>/doc/#automark" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                    </div>
                    <fieldset id="automark-options">
                        <legend>Opciones de automarcado</legend>
                        <div class="form-check">
                            <label class="form-check-label" for="automark-citation-style">Formato de marcado utilizado: </label>
                            <select class="form-select-input" id="automark-citation-style" name="automark-citation-style">
                                <option value="apa">APA</option>
                                <option value="ama">AMA</option>
                                <option value="vancouver">Vancouver</option>
                            </select> <a href="<?= JATSWIZARD_ASSETS_URL ?>/doc/#automark-style" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-figures-titles" name="automark-set-figures-titles" checked>
                            <label class="form-check-label" for="automark-set-figures-titles">Detección de títulos en imágenes</label> <a href="<?= JATSWIZARD_ASSETS_URL ?>/doc/#figure-titles" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-tables-titles" name="automark-set-tables-titles" checked>
                            <label class="form-check-label" for="automark-set-tables-titles">Detección de títulos en tablas</label> <a href="<?= JATSWIZARD_ASSETS_URL ?>/doc/#table-titles" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-title-references" name="automark-set-title-references" checked>
                            <label class="form-check-label" for="automark-set-title-references">Sustituir el párrafo de título por una referencia</label> <a href="<?= JATSWIZARD_ASSETS_URL ?>/doc/#add-reference-title" target="_blank"><i class="fa-solid fa-circle-info gray"></i></a>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Conjuntos de reglas</legend>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="automark-set-bibliography-mixed-citations" name="scielo-specific-use" checked>
                            <label class="form-check-label" for="scielo-specific-use">Aplicar reglas SciELO</label> <i class="fa-solid fa-circle-info gray" data-info="scielo"></i>
                        </div>
                    </fieldset>
                </div>
            </fieldset>
            <div class="d-flex justify-content-center my-4">
                <button class="btn btn-primary btn-lg finish" id="start" <?php if (empty($_GLOBALS['JATS_CITATIONS'])) echo 'disabled'; ?>>Empezar</button>
                <a style="margin-left: 10px" class="btn" href="<?= $_SESSION['jatsWizardState']['engineBaseUrl'] ?>&op=clean">Volver</a>
            </div>
            <div class="alert alert-danger" role="alert" id="error-message" style="display:<?php if (empty($_GLOBALS['JATS_CITATIONS'])) echo 'block';
                                                                                            else echo 'none'; ?>;">

                <p><span class="fa fa-exclamation-triangle"></span> <span id="error-text"> No se han encontrado referencias bibliográficas. </p>
                <p>Asegúrese de rellenar los metadatos básicos en la sección de <strong>Publicación</strong></p>
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
            startButton.innerHTML = 'Procesando... <span class="fa fa-circle-notch fa-spin"></span>';
            startButton.disabled = true;
            document.getElementById('intial-upload-form').submit();
        });
    </script>
</body>