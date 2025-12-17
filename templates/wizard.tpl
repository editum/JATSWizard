{**
 * Main WIZARD interface
 * This is the full interactive UI after the initial conversion
 * or when loading a marked ZIP session.
 *}

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistente XML-JATS</title>

    {* External CSS *}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer" />

    {* Wizard CSS *}
    <link rel="stylesheet"
          href="{$wizardBaseUrl}/assets/css/wizard.css">

    <link rel="stylesheet"
          href="{$wizardBaseUrl}/assets/preview.css">

    {* Texture lens CSS (si aplica) *}
    <link rel="stylesheet"
          href="{$wizardBaseUrl}/assets/lens/lens.css">

</head>

<body class="preview-body">

<!-- ========== TOP NAVBAR ========== -->
<nav class="navbar navbar-expand-lg navbar-light bg-light px-3 wizard-navbar">
    <a class="navbar-brand" href="#">
        <i class="fa-solid fa-scroll"></i> XML-JATS Wizard
    </a>

    <div class="ms-auto">
        <button class="btn btn-success me-2" id="save-ojs-btn">
            <i class="fa-solid fa-check"></i> Finalizar
        </button>

        <button class="btn btn-secondary me-2" id="save-mark-btn">
            <i class="fa-solid fa-file-zipper"></i> Guardar sesión
        </button>

        <button class="btn btn-danger" id="clean-btn">
            <i class="fa-solid fa-xmark"></i> Cancelar
        </button>
    </div>
</nav>

<!-- ========================= -->
<!-- MAIN LAYOUT (3 PANELS)  -->
<!-- ========================= -->

<div class="wizard-container">

    <!-- ===== Left column: CSL editor ===== -->
    <div class="wizard-left-column">
        <h5 class="mt-2">Citas y referencias</h5>
        <textarea id="csl-editor" class="form-control csl-editor"></textarea>

        <button id="btn-reconvert"
                class="btn btn-primary btn-sm mt-2 w-100">
            <i class="fa-solid fa-rotate"></i> Reconstruir XML
        </button>

        <div id="secs-container" class="mt-3">
            <h6>Secciones eliminadas</h6>
            <div id="secs-list"></div>
        </div>

        <div id="upload-doc-section" class="mt-3">
            <label class="form-label">Subir nueva versión DOCX:</label>
            <input type="file" id="upload-doc-input" class="form-control">
            <button class="btn btn-secondary btn-sm mt-2 w-100"
                    id="upload-doc-btn">
                <i class="fa-solid fa-upload"></i> Subir DOCX
            </button>
        </div>
    </div>


    <!-- ===== Center column: XML ===== -->
    <div class="wizard-center-column">
        <h5 class="mt-2">XML JATS generado</h5>
        <div id="xml-content" class="xml-viewer"></div>
    </div>


    <!-- ===== Right column: Preview ===== -->
    <div class="wizard-right-column">
        <h5 class="mt-2">Vista previa</h5>

        <iframe id="preview-frame"
                class="preview-frame"
                src="{$wizardBaseUrl}&opName=preview-html">
        </iframe>
    </div>

</div>

<!-- ========================= -->
<!-- MODAL: Loading Mask      -->
<!-- ========================= -->
<div id="loading-mask" class="loading-mask" style="display:none;">
    <div class="loading-spinner">
        <i class="fa-solid fa-circle-notch fa-spin fa-3x"></i>
        <p id="loading-message">Cargando...</p>
    </div>
</div>

<!-- ========================= -->
<!-- JS Libraries             -->
<!-- ========================= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="{$wizardBaseUrl}/assets/js/config.js"></script>
<script src="{$wizardBaseUrl}/assets/js/api.js"></script>
<script src="{$wizardBaseUrl}/assets/js/wizard.js"></script>

<script src="{$wizardBaseUrl}/assets/lens/lens.js"></script>

<!-- ========================= -->
<!-- WIZARD INITIALIZATION     -->
<!-- ========================= -->
<script>
    window.WIZARD = {
        baseUrl: "{$wizardBaseUrl}",
        submissionFileId: "{$submissionFileId}",
        workdir: "{$workdir}",
        opts: {$opts|@json_encode},
        csl: {$csl|@json_encode},
        secs: {$secs|@json_encode},
        mode: "{$mode}",
        documentName: "{$documentName|escape}"
    };

    $(document).ready(function() {
        Wizard.init(WIZARD);
    });
</script>

</body>
</html>
