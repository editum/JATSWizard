<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{translate key="plugins.generic.jatsWizard.wizard.title"}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="{$assetsUrl}/css/wizard.css" rel="stylesheet">
</head>

<body>

    <div id="wizard" class="container">
        <div class="doc-title">
            <form id="update-doc-form" method="post" action="{$engineBaseUrl}&op=upload_doc" enctype="multipart/form-data">
                <span style="position:relative;top: 4px;min-width: 30px;display: inline-block;text-align: center;cursor:pointer"><input name="file" type="file" style="width:40px;position:absolute;height:25px;opacity:0" /><i class="fa-solid fa-arrow-up-from-bracket"></i></span>
            </form>
            <div class="name"><a href="{$engineBaseUrl}&op=download_doc" style="color:white">{$markedDataName} v{$markedDataVersion}</a><span id="dirty-indicator" style="color:red">*</span></div>
            <div class="dropdown" id="menu-options">
                <button class="btn bg-transparent border-0 dropdown-toggle no-caret" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    &#9776;
                </button>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <li><button class="menu-option dropdown-item" id="show-preview"><i class="fa-solid fa-magnifying-glass"></i>{translate key="plugins.generic.jatsWizard.wizard.preview"}</button></li>
                    <li><button class="menu-option dropdown-item" id="show-xml"><i class="fa-solid fa-code"></i>{translate key="plugins.generic.jatsWizard.wizard.viewXml"}</button></li>
                    <!-- Separador con línea + texto -->
                    <li class="dropdown-divider"></li>
                    <li><button class="menu-option dropdown-item" id="show-html"><i class="fa-solid fa-magnifying-glass"></i>{translate key="plugins.generic.jatsWizard.wizard.genHtml"}</button></li>
                    <li><button class="menu-option dropdown-item" id="show-pdf"><i class="fa-solid fa-magnifying-glass"></i>{translate key="plugins.generic.jatsWizard.wizard.genPdf"}</button></li>
                    <li class="dropdown-divider"></li>
                    <li><button class="menu-option dropdown-item" id="save-marked"><i class="fa-solid fa-cloud-arrow-up"></i>{translate key="plugins.generic.jatsWizard.wizard.saveMarked"}</button></li>
                    <li><button class="menu-option dropdown-item" id="ojs-zip"><i class="fa-solid fa-cloud-arrow-up"></i>{translate key="plugins.generic.jatsWizard.wizard.saveOjs"}</button></li>
                    <li><a href="?op=clean" class="menu-option dropdown-item" id="cancel-wizard"><i class="fa-solid fa-cancel"></i>{translate key="plugins.generic.jatsWizard.wizard.cancel"}</a></li>
                </ul>
            </div>
        </div>
        <div class="header-steps">
            <div class="header-step" data-index="0">
                <div class="circle">1</div>
                {translate key="plugins.generic.jatsWizard.wizard.step1"}
            </div>
            <div class="header-step" data-index="1">
                <div class="circle">2</div>
                {translate key="plugins.generic.jatsWizard.wizard.step2"}
            </div>
            <div class="header-step" data-index="2">
                <div class="circle">3</div>
                {translate key="plugins.generic.jatsWizard.wizard.step3"}
            </div>
            <div class="header-step" data-index="3">
                <div class="circle">4</div>
                {translate key="plugins.generic.jatsWizard.wizard.step4"}
            </div>
        </div>

        <div class="navigation-buttons mb-2">
            <button type="button" class="btn btn-sm btn-secondary prev-step" disabled>{translate key="plugins.generic.jatsWizard.wizard.back"}</button>
            <button type="button" class="btn btn-sm btn-primary next-step">{translate key="plugins.generic.jatsWizard.wizard.next"}</button>
            <button type="button" class="btn btn-sm btn-primary finish" id="finish-button" style="display: none;"><i class="fa-solid fa-magnifying-glass"></i> {translate key="plugins.generic.jatsWizard.wizard.preview"}</button>
            <button type="button" class="btn btn-sm btn-primary disabled finish" id="save-button" style="float:right;display: block;opacity:0"><i class="fa-solid fa-save"></i> {translate key="plugins.generic.jatsWizard.wizard.save"}</button>
            <button type="button" class="float-right btn btn-sm btn-primary finish" id="save-ojs" style="float:right;display: none;"><i class="fa-solid fa-cloud-arrow-up"></i> {translate key="plugins.generic.jatsWizard.wizard.exportOjs"}</button>
        </div>
        <div id="wizard-inner">
            <!-- Paso 1: Inicio -->

            <!-- Paso 2: Revisar tabla de contenidos -->
            <div class="step" id="step0">
                <h5>{translate key="plugins.generic.jatsWizard.wizard.reviewToc"}</h5>
                <p>{translate key="plugins.generic.jatsWizard.wizard.tocDesc"} <a href="{$assetsUrl}/doc/#table-contents-review" target="_blank">{translate key="plugins.generic.jatsWizard.wizard.tocLink"}</a></p>
                <div id="tableOfContents">
                </div>
                <div class="auto-select-disclaimer" style="display:none">
                    <p> {translate key="plugins.generic.jatsWizard.wizard.tocWarning1"}</p>
                    <p> {translate key="plugins.generic.jatsWizard.wizard.tocWarning2"}</p>
                </div>
                <div id="warning-toc" style="float: right;display:none">
                    <div id="warning-toc-text" class="text-danger">2 secciones ocultas</div>
                    <div>
                        <button style="float:right" class="btn btn-sm btn-secondary" id="recover-index">{translate key="plugins.generic.jatsWizard.wizard.restoreIndex"}</button>
                    </div>
                </div>
            </div>

            <!-- Paso 3: Validación de imágenes y tablas -->
            <div class="step" id="step1">
                <h5>{translate key="plugins.generic.jatsWizard.wizard.reviewFigures"}</h5>
                <h6 class="mb-2">Se han detectado 4 imágenes</h6>
                <p>
                    {translate key="plugins.generic.jatsWizard.wizard.figuresDesc"} <a href="{$assetsUrl}/doc/#figures-review" target="_blank">{translate key="plugins.generic.jatsWizard.wizard.figuresLink"}</a>
                </p>
                <div id="imageCarousel">
                </div>

            </div>

            <!-- Paso 4: Revisión de referencias -->
            <div class="step" id="step2">
                <h5 style="margin-bottom:15px">{translate key="plugins.generic.jatsWizard.wizard.reviewRefs"}
                    <a href="#" style="float:right" class="btn btn-sm btn-secondary" id="regenerate-references">{translate key="plugins.generic.jatsWizard.wizard.regenOjs"}</a>
                </h5>
                <!-- enlace tipo button right para regenerar referencias -->
                
                <div id="referenceCards">
                    <!-- Las tarjetas se generarán dinámicamente con JavaScript -->
                </div>
            </div>

            <!-- Paso 5: Revisión de citaciones -->
            <div class="step" id="step3">
                <h5>{translate key="plugins.generic.jatsWizard.wizard.reviewCitations"}</h5>
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
                    <h5 class="modal-title" id="citationModalLabel">{translate key="plugins.generic.jatsWizard.wizard.selectRef"}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="citationBlocks">
                        <!-- Los bloques de citación se generarán dinámicamente con JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{translate key="plugins.generic.jatsWizard.wizard.cancelModal"}</button>
                    <button type="button" class="btn btn-sm btn-primary" id="acceptCitation" data-bs-dismiss="modal">{translate key="plugins.generic.jatsWizard.wizard.accept"}</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{$assetsUrl}/js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        window.WIZARD_I18N = {
            confirmUnvalidated: '{translate key="plugins.generic.jatsWizard.wizard.confirmUnvalidated" escape="javascript"}',
            confirmCancel: '{translate key="plugins.generic.jatsWizard.wizard.confirmCancel" escape="javascript"}',
            noFieldsToAdd: '{translate key="plugins.generic.jatsWizard.wizard.noFieldsToAdd" escape="javascript"}',
            figureNoTitle: '{translate key="plugins.generic.jatsWizard.wizard.figureNoTitle" escape="javascript"}',
            noTitleSuffix: '{translate key="plugins.generic.jatsWizard.wizard.noTitleSuffix" escape="javascript"}',
            untitled: '{translate key="plugins.generic.jatsWizard.wizard.untitled" escape="javascript"}',
            deleteRef: '{translate key="plugins.generic.jatsWizard.wizard.deleteRef" escape="javascript"}',
            newAuthorAdded: '{translate key="plugins.generic.jatsWizard.wizard.newAuthorAdded" escape="javascript"}',
            insertAfter: '{translate key="plugins.generic.jatsWizard.wizard.insertAfter" escape="javascript"}',
            insertBefore: '{translate key="plugins.generic.jatsWizard.wizard.insertBefore" escape="javascript"}',
            deleteReference: '{translate key="plugins.generic.jatsWizard.wizard.deleteReference" escape="javascript"}',
            addAuthor: '{translate key="plugins.generic.jatsWizard.wizard.addAuthor" escape="javascript"}',
            addField: '{translate key="plugins.generic.jatsWizard.wizard.addField" escape="javascript"}'
        };
    </script>
    <script src="{$assetsUrl}/js/wizard.js"></script>
    <script>
        $(document).ready(async function() {
            const JATSWIZARD_ENGINE_URL = '{$engineBaseUrl}';
            const wizard = new Wizard('#wizard', JATSWIZARD_ENGINE_URL);
            try {
                await wizard.loadDocuments();
            } catch (error) {
                console.error("Error loading documents:", error);
                alert('{translate key="plugins.generic.jatsWizard.wizard.errorLoading" escape="javascript"}');
                return;
            }            
            window.wizard = wizard;
        });
    </script>

</body>

</html>
