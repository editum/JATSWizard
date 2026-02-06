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

    <div id="wizard" class="container">
        <div class="doc-title">
            <form id="update-doc-form" method="post" action="<?= $_SESSION['jatsWizardState']['engineBaseUrl'] ?>&op=upload_doc" enctype="multipart/form-data">
                <span style="position:relative;top: 4px;min-width: 30px;display: inline-block;text-align: center;cursor:pointer"><input name="file" type="file" style="width:40px;position:absolute;height:25px;opacity:0" /><i class="fa-solid fa-arrow-up-from-bracket"></i></span>
            </form>
            <div class="name"><a href="<?= $_SESSION['jatsWizardState']['engineBaseUrl'] ?>&op=download_doc" style="color:white"><?php echo $_SESSION['jatsWizardState']['marked_data']['name'] . ' v' . $_SESSION['jatsWizardState']['marked_data']['version']; ?></a><span id="dirty-indicator" style="color:red">*</span></div>
            <div class="dropdown" id="menu-options">
                <button class="btn bg-transparent border-0 dropdown-toggle no-caret" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    &#9776;
                </button>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <li><button class="menu-option dropdown-item" id="show-preview"><i class="fa-solid fa-magnifying-glass"></i>Vista previa</button></li>
                    <li><button class="menu-option dropdown-item" id="show-xml"><i class="fa-solid fa-code"></i>Ver fuente XML</a></li>
                    <!-- Separador con línea + texto -->
                    <li class="dropdown-divider"></li>
                    <li><button class="menu-option dropdown-item" id="show-html"><i class="fa-solid fa-magnifying-glass"></i>Generar HTML (BETA)</button></li>
                    <li><button class="menu-option dropdown-item" id="show-pdf"><i class="fa-solid fa-magnifying-glass"></i>Generar PDF (BETA)</button></li>
                    <li class="dropdown-divider"></li>
                    <li><button class="menu-option dropdown-item" id="save-marked"><i class="fa-solid fa-cloud-arrow-up"></i>Guardar marcado</button></li>
                    <li><button class="menu-option dropdown-item" id="ojs-zip"><i class="fa-solid fa-cloud-arrow-up"></i>Guardar marcado y volver OJS</button></li>
                    <li><a href="?op=clean" class="menu-option dropdown-item" id="cancel-wizard"><i class="fa-solid fa-cancel"></i>Cancelar y cerrar</a></li>
                </ul>
            </div>
        </div>
        <div class="header-steps">
            <div class="header-step" data-index="0">
                <div class="circle">1</div>
                Tabla de contenidos
            </div>
            <div class="header-step" data-index="1">
                <div class="circle">2</div>
                Figuras y tablas
            </div>
            <div class="header-step" data-index="2">
                <div class="circle">3</div>
                Referencias
            </div>
            <div class="header-step" data-index="3">
                <div class="circle">4</div>
                Citaciones
            </div>
        </div>

        <div class="navigation-buttons mb-2">
            <button type="button" class="btn btn-sm btn-secondary prev-step" disabled>Atrás</button>
            <button type="button" class="btn btn-sm btn-primary next-step">Siguiente</button>
            <button type="button" class="btn btn-sm btn-primary finish" id="finish-button" style="display: none;"><i class="fa-solid fa-magnifying-glass"></i> Vista previa</button>
            <button type="button" class="btn btn-sm btn-primary disabled finish" id="save-button" style="float:right;display: block;opacity:0"><i class="fa-solid fa-save"></i> Guardar</button>
            <button type="button" class="float-right btn btn-sm btn-primary finish" id="save-ojs" style="float:right;display: none;"><i class="fa-solid fa-cloud-arrow-up"></i> Exportar XML a OJS</button>
        </div>
        <div id="wizard-inner">
            <!-- Paso 1: Inicio -->

            <!-- Paso 2: Revisar tabla de contenidos -->
            <div class="step" id="step0">
                <h5>Revisar tabla de contenidos</h5>
                <p>Este es el índice de contenidos del artículo detectado automáticamente. Si ves que hay fallos asegúrate de seguir las recomendaciones sobre<a href="<?php echo JATSWIZARD_ASSETS_URL; ?>/doc/#table-contents-review" target="_blank"> cómo etiquetar correctamente tablas de contenido</a></p>
                <div id="tableOfContents">
                </div>
                <div class="auto-select-disclaimer" style="display:none">
                    <p> ⚠️ Se han detectado secciones que no deberían ir en el documento ya que forman parte de los metadatos que se introducirán en fases posteriores y han sido seleccionadas para ser eliminadas</p>
                    <p> Si desea eliminar alguna sección adicional, selecciónela</p>
                </div>
                <div id="warning-toc" style="float: right;display:none">
                    <div id="warning-toc-text" class="text-danger">2 secciones ocultas</div>
                    <div>
                        <button style="float:right" class="btn btn-sm btn-secondary" id="recover-index">Restaurar indice</button>
                    </div>
                </div>
            </div>

            <!-- Paso 3: Validación de imágenes y tablas -->
            <div class="step" id="step1">
                <h5>Validación de imágenes y tablas</h5>
                <h6 class="mb-2">Se han detectado 4 imágenes</h6>
                <p>
                    Comprueba que están todos los elementos del documento. Si alguno no ha sido correctamente detectado, asegúrate de seguir las recomendaciones sobre <a href="<?php echo JATSWIZARD_ASSETS_URL; ?>/doc/#figures-review" target="_blank">tratamiento de imágenes y tablas</a>
                </p>
                <div id="imageCarousel">
                </div>

            </div>

            <!-- Paso 4: Revisión de referencias -->
            <div class="step" id="step2">
                <h5 style="margin-bottom:15px">Revisión de referencias
                    <a href="#" style="float:right" class="btn btn-sm btn-secondary" id="regenerate-references">Regenerar desde OJS</a>
                </h5>
                <!-- enlace tipo button right para regenerar referencias -->
                
                <div id="referenceCards">
                    <!-- Las tarjetas se generarán dinámicamente con JavaScript -->
                </div>
            </div>

            <!-- Paso 5: Revisión de citaciones -->
            <div class="step" id="step3">
                <h5>Revisión de citaciones</h5>
                <div id="articleText">
                    <!-- El texto del artículo se generará dinámicamente con JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para selección de citas -->
    <div class="modal fade" id="citationModal" tabindex="-1" aria-labelledby="citationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-custom-height">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="citationModalLabel">Seleccionar referencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="citationBlocks">
                        <!-- Los bloques de citación se generarán dinámicamente con JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" id="acceptCitation" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="<?= JATSWIZARD_ASSETS_URL ?>/js/wizard.js"></script>
    <script>
        $(document).ready(async function() {
            const JATSWIZARD_ENGINE_URL = '<?= $_SESSION['jatsWizardState']['engineBaseUrl'] ?>';
            const wizard = new Wizard('#wizard', JATSWIZARD_ENGINE_URL);
            try {
                await wizard.loadDocuments();
            } catch (error) {
                console.error("Error loading documents:", error);
                alert("No se pudo cargar el documento. Por favor, inténtelo de nuevo.");
                return;
                wizard.close();
            }            
            window.wizard = wizard;
        });
    </script>

</body>

</html>
