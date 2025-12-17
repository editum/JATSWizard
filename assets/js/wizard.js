
function showLoadingMask(text) {
    // Verifica que no exista ya la máscara
    if (document.getElementById('loading-mask')) {
        return; // Ya está mostrando
    }

    const mask = document.createElement('div');
    mask.id = 'loading-mask';
    mask.className = 'loading-mask';

    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';

    const title = document.createElement('div');
    title.className = 'loading-title';
    title.textContent = text || 'Cargando...';

    mask.appendChild(spinner);
    mask.appendChild(title);

    document.body.appendChild(mask);
}

function hideLoadingMask() {
    const mask = document.getElementById('loading-mask');
    if (mask) {
        mask.remove();
    }
}
class Wizard {
    constructor(selector, JATSWIZARD_ENGINE_URL) {
        this.wizard = document.querySelector(selector);
        this.steps = this.wizard.querySelectorAll('.header-step');
        // init currentStep from hash #Number
        const hash = window.location.hash;
        let stepNumber = hash ? parseInt(hash.replace('#', '')) : 0;

        if (isNaN(stepNumber) || stepNumber < 0 || stepNumber >= this.steps.length) {
            stepNumber = 0;
        }
        this.currentStep = stepNumber;
        this.imagePath = JATSWIZARD_ENGINE_URL + '&op=img&img=';
        this.engineUrl = JATSWIZARD_ENGINE_URL;
        this._updateSteps();
        this.selectedSections = '';
        this.setDirty(false);
        this.init();
    }
    error(msg) {
        console.log(msg);
        alert(msg);
    }
    async close() {
        window.location.href = this.engineUrl + "&op=clean";
    }
    _getSelectedSections() {
        let map = {};
        this.wizard.querySelectorAll('#tableOfContents .selected').forEach(section => {
            const id = section.id.replace('h-', '');
            map[id] = section.textContent.trim();
        });
        return map
    }
    async reconvertDocument(sections) {

        showLoadingMask('Reconvertiendo documento...');
        this.selectedSections = sections || this._getSelectedSections();
        try {
            this.xml = await $.ajax({
                url: this.engineUrl + "&op=reconvert",
                method: 'POST',
                data: {
                    secs: JSON.stringify(this.selectedSections),
                    csl: JSON.stringify(this.csl),
                }
            });
            hideLoadingMask();
            await this.refreshDocument();
            this.setDirty(false);
        } catch (error) {
            hideLoadingMask();
            this.error('Error al reconvertir el documento: ' + error.message);
        }
    }
    async refreshDocument() {
        
        this.figures = this._extractFiguresAndTablesFromJATS();
        let figuresWithoutTitle = this.figures.filter(f => !f.title).length;

        this.wizard.querySelector('#step1 > h6').innerHTML = 'Se han detectado ' + this.figures.length + ' elementos en el documento';
        if (figuresWithoutTitle > 0) {
            this.wizard.querySelector('#step1 > h6').innerHTML += `<span class="no-titles-tag">${figuresWithoutTitle} sin título.</span>`;
        } else {
            this.wizard.querySelector('#step1 > h6').innerHTML += ' <span class="titles-tag">Todos los elementos tienen título.</span>';
        }

        // Count figures without title
        this.wizard.querySelector('#imageCarousel').innerHTML = this._createCarouselHTML(this.figures);
        $('#referenceCards').html(this._generateReferenceForms(this.csl));
        $('#articleText').html(this._xmlToHTML(this.xml));
        this._setTooltipsListeners();
        this._ensureCslShortcuts();
        this.loadCitations(this.csl);
    }
    _ensureCslShortcuts() {
        if (this.csl.length > 0 && this.csl[0]._shortcuts) {
            return; // Ya están generados
        }
        this.csl.forEach((item, index) => {
            item._shortcuts = this._generateShortcuts(item);
            // Concat with _citations if exists
            item._citations = item._shortcuts.concat(item.citations || []);
        });
        console.log('Shortcuts generados para CSL');
        this.setDirty(true);
    }
    _generateShortcuts(cslItem) {
        let shortcuts = [];
        if (cslItem.issued) {
            if (cslItem.author) {
                if (cslItem.author.length == 1) {
                    const author = cslItem.author[0].family;
                    shortcuts.push(`${author} (${cslItem.issued})`);
                } else if (cslItem.author.length == 2) {
                    const author1 = cslItem.author[0].family;
                    const author2 = cslItem.author[1].family;
                    shortcuts.push(`${author1} and ${author2} (${cslItem.issued})`);
                    shortcuts.push(`${author1} y ${author2} (${cslItem.issued})`);
                    shortcuts.push(`${author1} & ${author2} (${cslItem.issued})`);
                } else if (cslItem.author.length > 2) {
                    const author = cslItem.author[0].family;
                    shortcuts.push(`${author} et al. (${cslItem.issued})`);
                }
            } else if (cslItem.title) {
                shortcuts.push(`${cslItem.title} (${cslItem.issued})`);
                shortcuts.push(`${cslItem.title}, ${cslItem.issued}`);
            }
        }
        return shortcuts;
    }
    async loadDocuments() {
        this.xml = await $.ajax({ url: this.engineUrl + "&op=xml", method: 'GET' });

        const markedData = await $.ajax({ url: this.engineUrl + "&op=markedData", method: 'GET' });
        this.csl = markedData.csl || [];
        this.secs = markedData.secs || [];
        this.index = this._extractIndexFromJATS();
        this.wizard.querySelector('#tableOfContents').innerHTML = '';
        this.wizard.querySelector('#tableOfContents').appendChild(this._convertIndexToHTML(this.index));
        await this.refreshDocument();
        if (!this.xml.querySelector('publisher')) {
            await this.reconvertDocument();
        }
    }
    _setTooltipsListeners() {

        function showTooltip(xrefElement, referenceContent) {

            if (xrefElement.tooltip) return; // Si ya hay un tooltip, no hacemos nada
            const tooltip = document.createElement('div');
            tooltip.style.position = 'absolute';
            tooltip.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            tooltip.style.color = 'white';
            tooltip.style.padding = '5px';
            tooltip.style.borderRadius = '3px';
            tooltip.innerHTML = referenceContent;

            // Obtener posición del <xref>
            const rect = xrefElement.getBoundingClientRect();
            tooltip.style.top = `${rect.top + 30 + window.scrollY}px`; // 30px arriba del elemento
            tooltip.style.left = `${rect.left + window.scrollX}px`;

            // Agregar tooltip al body
            document.body.appendChild(tooltip);
            tooltip.addEventListener('mouseenter', (event) => {
                xrefElement.retain = true;
            });
            tooltip.addEventListener('mouseout', (event) => {
                xrefElement.retain = false;
                hideTooltip(xrefElement);
            });
            // Almacenamos el tooltip para poder eliminarlo después
            xrefElement.tooltip = tooltip;
        }

        // Función para ocultar el tooltip
        function hideTooltip(xrefElement) {

            if (xrefElement.tooltip) {
                document.body.removeChild(xrefElement.tooltip);
                xrefElement.tooltip = null;
            }
        }
        const xrefElements = this.wizard.querySelectorAll('xref');

        xrefElements.forEach(xrefElement => {
            xrefElement.addEventListener('click', () => {
                if (xrefElement.deletable) {
                    xrefElement.replaceWith(xrefElement.textContent);
                    this.csl[xrefElement.cslIndex]._citations = this.csl[xrefElement.cslIndex]._citations.filter(citation => citation !== xrefElement.textContent);
                    this.setDirty(true);
                }
            });
            xrefElement.addEventListener('mouseover', () => {
                const rid = xrefElement.getAttribute('rid'); // Por ejemplo: "bib1"
                if (!rid) return; // Si no hay atributo rid, no hacemos nada
                const index = parseInt(rid.replace('bib', ''), 10) - 1; // Extraemos el número y restamos 1

                // Aseguramos que el índice sea válido
                if (index >= 0 && index < this.csl.length) {
                    let referenceContent = this.csl[index].note;
                    // find if xrefElement.textContent is in this.csl[index]._citations

                    if (this.csl[index]._citations.includes(xrefElement.textContent)) {
                        console.log(this.csl[index]._citations);
                        xrefElement.deletable = true;
                        xrefElement.cslIndex = index;
                        referenceContent += ('<div style="font-size:0.8em;margin-top:5px;color:#ccc;">(<span class="fa-solid fa-trash"></span>Click para eliminar)</div>');
                    } else {
                        xrefElement.deletable = false;
                    }
                    //Add remove button to referenceContent
                    
                    showTooltip(xrefElement, referenceContent);
                }
            });

            xrefElement.addEventListener('mouseout', () => {
                setTimeout(() => {
                    if (!xrefElement.retain) hideTooltip(xrefElement);
                }, 1000);
            });
        });
    }
    _updateSteps() {
        this.steps.forEach((step, index) => {
            const circle = step.querySelector(".circle");
            if (index < this.currentStep) {
                step.classList.add("completed");
                step.classList.remove("active");
                circle.innerHTML = '<i class="fa-solid fa-check"></i>';
            } else if (index === this.currentStep) {
                step.classList.add("active");
                step.classList.remove("completed");
                circle.textContent = index + 1;
            } else {
                step.classList.remove("completed", "active");
                circle.textContent = index + 1;
            }
        });
    }
    _showSearchNavigationPanel(container, searchString, spans) {
        if (spans.length === 0) return;

        let currentIndex = 0;

        // Función para actualizar la clase 'active'
        function updateActiveSpan() {
            spans.forEach(span => span.classList.remove('active'));
            spans[currentIndex].classList.add('active');
        }

        // Scroll al primer span y marcarlo como activo
        spans[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
        updateActiveSpan();

        // Crear el panel si no existe
        let existingPanel = document.getElementById('search-nav-panel');
        if (existingPanel) {
            existingPanel.remove();
        }

        const panel = document.createElement('div');
        panel.id = 'search-nav-panel';
        panel.style.position = 'fixed';
        panel.style.bottom = '0';
        panel.style.left = '0';
        panel.style.right = '0';
        panel.style.backgroundColor = '#007bff'; // Azul
        panel.style.color = 'white';
        panel.style.boxShadow = '0 -2px 10px rgba(0,0,0,0.5)';
        panel.style.display = 'flex';
        panel.style.alignItems = 'center';
        panel.style.justifyContent = 'space-between';
        panel.style.padding = '10px 20px';
        panel.style.fontFamily = 'Arial, sans-serif';
        panel.style.transition = 'transform 0.3s ease';
        panel.style.transform = 'translateY(100%)';

        requestAnimationFrame(() => {
            panel.style.transform = 'translateY(0)';
        });

        const info = document.createElement('div');
        info.textContent = `Encontradas ${spans.length} coincidencias de "${searchString}"`;

        const controls = document.createElement('div');
        controls.style.display = 'flex';
        controls.style.alignItems = 'center';
        controls.style.gap = '10px';

        const upButton = document.createElement('button');
        upButton.classList.add('panel-button');
        upButton.innerHTML = '▲';


        const downButton = document.createElement('button');
        downButton.innerHTML = '▼';
        downButton.classList.add('panel-button');


        const indicator = document.createElement('div');
        indicator.classList.add('panel-indicator');
        indicator.textContent = `${currentIndex + 1} / ${spans.length}`;

        const assignButton = document.createElement('button');
        assignButton.textContent = 'Asignar referencia';
        assignButton.style.cursor = 'pointer';
        assignButton.style.width = '160px';
        assignButton.style.padding = '5px 10px';
        assignButton.style.background = 'transparent';
        assignButton.style.color = 'white';
        assignButton.style.textDecoration = 'underline';
        assignButton.style.border = 'none';
        assignButton.style.borderRadius = '5px';

        const closeButton = document.createElement('button');
        closeButton.innerHTML = '❌';
        closeButton.classList.add('panel-button');


        controls.appendChild(upButton);
        controls.appendChild(downButton);
        controls.appendChild(indicator);
        controls.appendChild(assignButton);
        controls.appendChild(closeButton);

        panel.appendChild(info);
        panel.appendChild(controls);
        document.body.appendChild(panel);

        // Funciones auxiliares
        function updateIndicator() {
            indicator.textContent = `${currentIndex + 1} / ${spans.length}`;
        }

        function scrollToCurrentSpan() {
            spans[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
            updateActiveSpan();
        }

        // Eventos
        upButton.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                scrollToCurrentSpan();
                updateIndicator();
            }
        });

        downButton.addEventListener('click', () => {
            if (currentIndex < spans.length - 1) {
                currentIndex++;
                scrollToCurrentSpan();
                updateIndicator();
            }
        });
        assignButton.addEventListener('click', () => {
            $('#citationModalLabel').html(`Asignar referencia para: <span class="badge bg-secondary">${this.selectedText}</span>`);
            var modal = new bootstrap.Modal(document.getElementById('citationModal'));
            modal.show();
        });
        closeButton.addEventListener('click', () => this.hideSearchNavigationPanel(container));
        this._panel = panel;
    }
    hideSearchNavigationPanel(container) {
        this._clearSearchMatches(container);
        this._panel.style.transform = 'translateY(100%)';
        setTimeout(() => {
            this._panel.remove();
            this._panel = null;
        }, 300);
    }
    _replaceCoincidencesWithXref(parentElement, N) {
        const spans = parentElement.querySelectorAll('span.coincidence');
        spans.forEach(span => {
            const contenido = span.textContent;
            const xref = document.createElement('xref');
            xref.setAttribute('ref-type', 'bibr');
            xref.setAttribute('rid', `bib${N}`);
            xref.textContent = contenido;
            span.replaceWith(xref);
        });
    }

    _highlightSearchMatches(container, searchString) {
        searchString = searchString.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(searchString, 'gi'); // Crear una expresión regular para la búsqueda

        const spans = [];

        // Función recursiva para recorrer los elementos
        function searchAndHighlight(element) {
            // Si el elemento tiene texto, procesarlo
            if (element.nodeType === Node.TEXT_NODE) {
                const textContent = element.textContent;
                const match = textContent.match(regex);

                if (match) {
                    let newText = textContent;
                    // Crear un contenedor temporal para el nuevo contenido con el texto marcado
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = newText.replace(regex, match => `<span class="coincidence">${match}</span>`);

                    // Reemplazar el texto original por el nuevo
                    Array.from(tempDiv.childNodes).forEach(child => {
                        if (child.nodeType === Node.ELEMENT_NODE && child.tagName.toLowerCase() === 'span') {
                            spans.push(child); // Almacenar los nuevos <span> creados
                        }
                        element.parentNode.insertBefore(child, element);
                    });
                    element.parentNode.removeChild(element);
                }
            }

            // Recorrer los elementos hijos
            Array.from(element.childNodes).forEach(child => {
                searchAndHighlight(child);
            });
        }

        // Comenzar la búsqueda desde el contenedor (por ejemplo, el div que contiene el contenido convertido)
        searchAndHighlight(container);

        return spans;
    }
    _clearSearchMatches(container) {
        // Buscar todos los <span class="coincidence"> dentro del contenedor
        const highlightedSpans = container.querySelectorAll('span.coincidence');

        // Reemplazar cada <span> por su texto

        // Reemplazar cada <span> por su texto (creando un nodo de texto)
        highlightedSpans.forEach(span => {
            const textNode = document.createTextNode(span.textContent);
            span.parentNode.replaceChild(textNode, span);
        });

        // Fusionar nodos de texto adyacentes en todo el subtree del contenedor
        container.normalize();
    }
    isDirty() {
        return this.dirty;
    }
    
    async init() {
        this.setDirty(false);
        $('.next-step').click(async () => {
            if (this.currentStep < this.steps.length + 1) {
                if (this.isDirty()) {
                    await this.reconvertDocument();
                }
                this.showStep(this.currentStep + 1);
            }
        });
        $('.prev-step').click(async() => {
            if (this.currentStep > 0) {
                if (this.isDirty()) {
                    await this.reconvertDocument();
                }
                this.showStep(this.currentStep - 1);
                
                if (this._panel) {
                    this.hideSearchNavigationPanel($('#articleText')[0]);
                }
            }
        });
        $('#update-doc-form input').change(() => {
            $('#update-doc-form').submit();
        });
        $('#do-automark').click(() => {
            $('#automark-options').toggle();
        });
        $('#save-button').click(async () => {
             await this.reconvertDocument();
        });
        $('#finish-button').click(async () => {
            this.showOption('preview', '_preview');
            //window.location.href = '?op=finish';
            // Aquí puedes agregar la lógica para finalizar el proceso
        });
        $('#recover-index').click(async () => {
            await this.reconvertDocument('');
            window.location.reload();
        });
        $('#save-ojs').click(async () => {
            if (this.isDirty()) {
                if (confirm('Hay cambios que no ha validado con vista previa. ¿Está seguro querer continuar?')) {
                    await this.reconvertDocument();
                    location.href = '?op=save_ojs';
                }
            } else {
                location.href = '?op=save_ojs';
            }

            //window.location.href = '?op=finish';
            // Aquí puedes agregar la lógica para finalizar el proceso
        });

        // Manejar clics en bloques de citación
        $('.citation-block').click(() => {
            $('.citation-block').removeClass('selected');
            $(this).addClass('selected');
        });
        // Manejar la selección de texto y abrir la modal
        $('#articleText').on('mouseup', () => {
            this.selectedText = window.getSelection().toString();
            if (this.selectedText.length > 0) {
                this._clearSearchMatches($('#articleText')[0]);
                let spans = this._highlightSearchMatches($('#articleText')[0], this.selectedText);
                this._showSearchNavigationPanel($('#articleText')[0], this.selectedText, spans);
                console.log('Encontradas ' + spans.length + ' coincidencias');
            }
        });
        $('.menu-option').click(async (event) => {
            const target = $(event.currentTarget);
            if (target.is('#show-preview')) {
                this.showOption('preview', '_preview');
            } else if (target.is('#show-xml')) {
                this.showOption('xml', '_xml');
            } else if (target.is('#show-html')) {
                debugger;
                this.showOption('html', '_html');
            } else if (target.is('#show-pdf')) {
                this.showOption('pdf', '_pdf');
            } else if (target.is('#save-zip')) {
                this.showOption('download_zip');
            } else if (target.is('#ojs-zip')) {
                this.showOption('ojs_zip', '_self');
            } else if (target.is('#cancel-wizard')) {
                event.preventDefault();
                if (confirm('¿Está seguro de que desea cancelar el asistente?')) {
                    this.close();
                }
            }
        });

        const container = document.getElementById('citationBlocks'); // o el selector que uses

        container.addEventListener('click', (event) => {

            const citationBlock = event.target.closest('.citation-block');
            if (citationBlock && container.contains(citationBlock)) {
                const blocks = Array.from(container.querySelectorAll('.citation-block'));
                blocks.forEach(block => block.classList.remove('selected'));
                const index = blocks.indexOf(citationBlock);
                citationBlock.classList.add('selected');
                this.selectedCitation = index;
                console.log('Bloque pulsado:', index); // index empieza en 0
            }
        });
        // Manejar la aceptación de la cita
        $('#acceptCitation').click(() => {
            if (!this.csl[this.selectedCitation]._citations) {
                this.csl[this.selectedCitation]._citations = [];
            }
            this.csl[this.selectedCitation]._citations.push(this.selectedText);
            this._replaceCoincidencesWithXref($('#articleText')[0], this.selectedCitation + 1);
            this._clearSearchMatches($('#articleText')[0]);
            this._panel.remove();
            this._panel = null;
            this._setTooltipsListeners();
            this.setDirty(true);
        });
        //this.updateNavigationButtons();
        this.showStep(this.currentStep);
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty()) {
                // Establecer returnValue muestra un diálogo de confirmación en navegadores compatibles
                e.preventDefault(); // Necesario para algunos navegadores
                e.returnValue = ''; // El valor real ya no se usa, pero debe establecerse
                // El navegador mostrará un mensaje por defecto, no el tuyo
            }
        });
    }
    setDirty(dirty) {
        this.dirty = dirty;
        $(document.body).toggleClass('dirty', dirty);
        $('#save-button').prop('disabled', !dirty);
        $('#save-button').toggleClass('disabled', !dirty);
        //$('#save-ojs').prop('disabled', dirty);
    }
    async showOption(option, target) {
        if (this.isDirty()) {
            await this.reconvertDocument();
        }
        window.open(this.engineUrl+'&op=' + option, target);
    }
    showStep(stepNumber) {
        $('.step').removeClass('active');
        $('#step' + stepNumber).addClass('active');
        this.currentStep = stepNumber;
        window.location.hash = stepNumber == 0 ? '' : '#' + stepNumber;
        this._updateSteps();
        this.updateNavigationButtons();
    }

    updateNavigationButtons() {
        if (this.currentStep === 0) {
            $('.navigation-buttons .prev-step').prop('disabled', true);
            $('.navigation-buttons #finish-button').hide();
        } else if (this.currentStep === this.steps.length - 1) {
            $('.navigation-buttons .prev-step').prop('disabled', false);
            $('.navigation-buttons .next-step').hide();
            $('.navigation-buttons #finish-button').show();
            if (this._mode == 'popup') {
                //$('.navigation-buttons #save-ojs').show();
            }
        } else {
            $('.navigation-buttons .prev-step').prop('disabled', false);
            $('.navigation-buttons .next-step').show();
            $('.navigation-buttons #finish-button').hide();
        }
    }


    loadCitations(citationReferences) {
        var citationBlocks = '';
        citationReferences.forEach(function (ref, index) {
            citationBlocks += `
                <div class="citation-block small" data-ref="${index}">
                    ${ref.note}
                </div>
            `;
        });
        $('#citationBlocks').html(citationBlocks);

    }
    _extractIndexFromJATS() {

        const index = [];

        function parseSection(sectionElement, level = 1) {
            const titleElement = sectionElement.querySelector("title");
            if (titleElement) {
                index.push({
                    level: level,
                    title: titleElement.textContent.trim(),
                    id: sectionElement.getAttribute("id") || null
                });
            }

            const subsections = sectionElement.querySelectorAll(":scope > sec");
            subsections.forEach(subsection => parseSection(subsection, level + 1));
        }

        const sections = this.xml.querySelectorAll("body > sec");
        sections.forEach(section => parseSection(section));

        return index;
    }
    _mergeIndexWithActualSecs(index) {
        if (!Array.isArray(index)) {
            throw new Error("El índice debe ser un array.");
        }
        const actualSecs = Object.keys(this.secs) || {};
        while (actualSecs.length > 0) {
            const sec = actualSecs.shift();
            // Introducir sec en posición index segun orden alfabético de item.id
            const item = {
                id: sec,
                title: this.secs[sec],
                checked: true,
                level: 1 // Asumimos que es un nivel 1, se puede ajustar según sea necesario
            };
            // Buscar la posición correcta para insertar el nuevo item
            let pos = index.findIndex(i => i.id > item.id);
            if (pos === -1) {
                pos = index.length; // Si no se encuentra, añadir al final
            }
            index.splice(pos, 0, item);
        }
        return index;
    }
    _convertIndexToHTML(index) {
        if (this.secs) {
            index = this._mergeIndexWithActualSecs(index);
        }
        const PRE_SELECTED_TITLES = {
            "Abstract": true,
            "Resumen": true,
            "Referencias": true,
            "Bibliografía": true,
            "References": true,
            "Bibliography": true,
        }
        if (!Array.isArray(index)) {
            throw new Error("El índice debe ser un array.");
        }

        const container = document.createElement("div");

        index.forEach(item => {
            const heading = document.createElement(`h${Math.min(item.level, 6)}`); // Máximo h6
            heading.id = 'h-' + item.id;
            if (item.level === 1) {
                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";

                checkbox.addEventListener("change", () => {
                    heading.classList.toggle("selected", checkbox.checked);
                    if (this._getSelectedSections() != this.selectedSections) {
                        this.setDirty(true);
                    }
                });

                if (PRE_SELECTED_TITLES[item.title] || item.checked) {
                    heading.classList.add("selected");
                    checkbox.checked = true;
                    if (PRE_SELECTED_TITLES[item.title]) {
                        this.setDirty(true);
                        this.wizard.querySelector('.auto-select-disclaimer').style.display = 'block';
                    }
                }

                heading.appendChild(checkbox);
                heading.appendChild(document.createTextNode(item.title));
            } else {
                heading.textContent = item.title;
            }

            container.appendChild(heading);
        });

        return container;
    }


    _extractFiguresAndTablesFromJATS() {

        const items = [];

        // Espacio de nombres para xlink
        const xlinkNamespace = "http://www.w3.org/1999/xlink";

        // Extraer figuras
        const figures = this.xml.querySelectorAll("fig");
        figures.forEach(figure => {
            const title = figure.querySelector("title")?.textContent.trim() || null;

            // Intentar extraer xlink:href del elemento <fig> o su <graphic> interno
            const graphic = figure.querySelector("graphic");
            let href = graphic
                ? graphic.getAttributeNS(xlinkNamespace, "href")
                : figure.getAttributeNS(xlinkNamespace, "href");
            href = href;
            items.push({
                type: "figure",
                title: title,
                href: href ? href.trim() : null
            });
        });

        // Extraer tablas
        const tables = this.xml.querySelectorAll("table-wrap");
        tables.forEach(table => {
            const title = table.querySelector("title")?.textContent.trim() || null;
            const tableContent = table.querySelector("table");

            // Convertir la tabla en HTML string si existe
            const html = tableContent ? tableContent.outerHTML.trim() : null;

            items.push({
                type: "table",
                title: title,
                html: html
            });
        });

        return items;
    }
    _createCarouselHTML(items) {
        if (!Array.isArray(items)) {
            throw new Error("El parámetro debe ser un array.");
        }

        const total = items.length; // Total de elementos en el carrusel
        const container = document.createElement("div"); // Contenedor principal

        items.forEach((item, index) => {
            const carouselItem = document.createElement("div");
            //carouselItem.classList.add("carousel-item");
            if (index == 0) {
                carouselItem.classList.add("active");
            }

            const card = document.createElement("div");
            card.classList.add("card");

            const cardHeader = document.createElement("div");
            cardHeader.classList.add("card-header", "py-1", "px-2");
            cardHeader.textContent = `(${index + 1}/${total}) ${item.title || "Sin título"}`;
            if (!item.title) {
                cardHeader.classList.add("unknown-title");
            }
            card.appendChild(cardHeader);

            if (item.type === "figure") {
                const img = document.createElement("img");
                img.classList.add("card-img-top");
                img.src = this.imagePath + (item.href || "#"); // Usar "#" si no hay `href`
                img.alt = item.title || "Sin título";
                card.appendChild(img);
            } else if (item.type === "table") {
                const table = document.createElement("table");
                table.classList.add("table");
                table.innerHTML = item.html || "<tr><td>Tabla vacía</td></tr>"; // Usar `item.html` si existe
                card.appendChild(table);
            }

            carouselItem.appendChild(card);
            container.appendChild(carouselItem);
        });

        return container.innerHTML; // Devolver el contenido HTML como string
    }
    _generateReferenceForms(data) {

        let validFields = [
            'accessed', 'collection-title', 'container-title', 'DOI', 'edition',
            'genre', 'ISBN', 'issued', 'issue', 'language', 'note', 'page', 'publisher',
            'publisher-place', 'title', 'type', 'URL', 'volume',
        ];
        const container = document.createElement("div"); // Contenedor para todas las tarjetas

        data.forEach((item, index) => {
            // Crear el contenedor de la tarjeta
            const card = document.createElement("div");
            card.className = "card mb-3 reference-card";
            card.id = `reference-card-${index}`; // Asignar un ID único a cada tarjeta
            card.setAttribute('data-index', index);

            // Crear el cuerpo de la tarjeta
            const cardBody = document.createElement("div");
            cardBody.className = "card-body";

            // --- Header (title + dropdown) ---
            const headerDiv = document.createElement("div");
            headerDiv.className = "d-flex justify-content-between align-items-start";

            // Título de la tarjeta
            const cardTitle = document.createElement("h6");
            cardTitle.className = "card-title mb-0";
            cardTitle.textContent = item.note || `Referencia ${index + 1}`;

            // Dropdown (Bootstrap) a la derecha del título
            const dropdownWrapper = document.createElement("div");
            dropdownWrapper.className = "btn-group";

            const dropdownBtn = document.createElement("button");
            dropdownBtn.className = "btn btn-sm btn-outline-secondary dropdown-toggle";
            dropdownBtn.type = "button";
            // atributo para Bootstrap 5
            dropdownBtn.setAttribute("data-bs-toggle", "dropdown");
            dropdownBtn.setAttribute("aria-expanded", "false");
            dropdownBtn.innerHTML = "⋮";

            const dropdownMenu = document.createElement("ul");
            dropdownMenu.className = "dropdown-menu dropdown-menu-end";

            const liAddAbove = document.createElement("li");
            const addAboveLink = document.createElement("button");
            addAboveLink.className = "dropdown-item";
            addAboveLink.type = "button";
            addAboveLink.textContent = "⊕ Añadir referencia arriba";
            liAddAbove.appendChild(addAboveLink);

            const liAddBelow = document.createElement("li");
            const addBelowLink = document.createElement("button");
            addBelowLink.className = "dropdown-item";
            addBelowLink.type = "button";
            addBelowLink.textContent = "⊕ Añadir referencia abajo";
            liAddBelow.appendChild(addBelowLink);

            const liDelete = document.createElement("li");
            const deleteLink = document.createElement("button");
            deleteLink.className = "dropdown-item text-danger";
            deleteLink.type = "button";
            deleteLink.textContent = "🗑️ Eliminar referencia";
            liDelete.appendChild(deleteLink);

            dropdownMenu.appendChild(liAddAbove);
            dropdownMenu.appendChild(liAddBelow);
            dropdownMenu.appendChild(document.createElement("li").appendChild(document.createElement("hr"))); // separador visual (no obligatorio)
            // Nota: algunos navegadores/BS requieren <li><hr class="dropdown-divider"></li>, pero lo dejamos simple.
            // Mejor usar la clase si lo prefieres:
            // const divider = document.createElement("li"); const hr = document.createElement("hr"); hr.className = "dropdown-divider"; divider.appendChild(hr); dropdownMenu.appendChild(divider);

            dropdownMenu.appendChild(liDelete);

            dropdownWrapper.appendChild(dropdownBtn);
            dropdownWrapper.appendChild(dropdownMenu);

            // Añadir título y dropdown al header
            headerDiv.appendChild(cardTitle);
            headerDiv.appendChild(dropdownWrapper);

            // --- Handlers del dropdown ---
            // Añadir referencia arriba
            addAboveLink.addEventListener("click", (e) => {
                e.preventDefault();
                const cardEl = card; // card actual
                const idx = parseInt(cardEl.getAttribute('data-index'), 10);
                if (!this.csl) this.csl = [];
                // Crear referencia vacía por defecto (puedes personalizar la estructura)
                const newRef = { author: [{}], _modified: true };
                this.csl.splice(idx, 0, newRef); // insertar antes
                this.setDirty(true);
                // Re-renderizar el contenedor por simplicidad (mantener índices coherentes)
                const parent = container.parentNode;
                if (parent) {
                    const newContainer = this._generateReferenceForms(this.csl);
                    parent.replaceChild(newContainer, container);
                }
            });

            // Añadir referencia abajo
            addBelowLink.addEventListener("click", (e) => {
                e.preventDefault();
                const cardEl = card;
                const idx = parseInt(cardEl.getAttribute('data-index'), 10);
                if (!this.csl) this.csl = [];
                const newRef = { author: [{}], _modified: true };
                this.csl.splice(idx + 1, 0, newRef); // insertar después
                this.setDirty(true);
                const parent = container.parentNode;
                if (parent) {
                    const newContainer = this._generateReferenceForms(this.csl);
                    parent.replaceChild(newContainer, container);
                }
            });

            // Eliminar referencia
            deleteLink.addEventListener("click", (e) => {
                e.preventDefault();
                const cardEl = card;
                const idx = parseInt(cardEl.getAttribute('data-index'), 10);
                if (!this.csl) this.csl = [];
                // Confirmación simple (opcional)
                //if (!confirm(`¿Eliminar la referencia ${idx + 1}? Esta acción no se puede deshacer.`)) return;
                this.csl.splice(idx, 1);
                this.setDirty(true);
                const parent = container.parentNode;
                if (parent) {
                    const newContainer = this._generateReferenceForms(this.csl);
                    parent.replaceChild(newContainer, container);
                }
            });

            // Crear el formulario
            const form = document.createElement("form");
            form.id = `reference${index}`;
            if (!item.author || !Array.isArray(item.author) || item.author.length === 0) {
                // Si no hay autores, añadir un autor vacío por defecto
                item.author = [{}];
            }
            // Manejar autores (multivaluado)
            if (item.author && Array.isArray(item.author)) {
                item.author.forEach((author, i) => {
                    const authorRow = document.createElement("div");
                    authorRow.className = "author-row";
                    authorRow.setAttribute('data-index', i); // Añadir data-index para referencia

                    const label = document.createElement("label");
                    label.textContent = `Autor ${i + 1}`;

                    const familyInput = document.createElement("input");
                    familyInput.type = "text";
                    familyInput.name = `family${i + 1}`;
                    familyInput.value = author.family || "";
                    familyInput.onkeyup = () => {
                        const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                        if (!this.csl[index]) {
                            this.csl[index] = {}; // Asegurarse de que el objeto exista
                        }
                        if (!this.csl[index].author) {
                            this.csl[index].author = []; // Asegurarse de que el array exista
                        }
                        if (!this.csl[index].author[i]) {
                            this.csl[index].author[i] = {}; // Asegurarse de que el objeto autor exista
                        }
                        this.csl[index].author[i].family = familyInput.value; // Actualizar el atributo family
                        this.csl[index]._modified = true;
                        this.setDirty(true);
                        console.log(index, i, 'family', familyInput.value);
                    }

                    const givenInput = document.createElement("input");
                    givenInput.type = "text";
                    givenInput.name = `given${i + 1}`;
                    givenInput.value = author.given || "";
                    givenInput.onkeyup = () => {
                        const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                        if (!this.csl[index]) {
                            this.csl[index] = {}; // Asegurarse de que el objeto exista
                        }
                        if (!this.csl[index].author) {
                            this.csl[index].author = []; // Asegurarse de que el array exista
                        }
                        if (!this.csl[index].author[i]) {
                            this.csl[index].author[i] = {}; // Asegurarse de que el objeto autor exista
                        }
                        this.csl[index].author[i].given = givenInput.value; // Actualizar el atributo given
                        this.csl[index]._modified = true;
                        this.setDirty(true);
                        console.log(index, i, 'given', givenInput.value);
                    }

                    // Botón de eliminar (basura)
                    const deleteBtn = document.createElement("button");
                    deleteBtn.type = "button";
                    deleteBtn.className = "btn delete ms-2";
                    deleteBtn.innerHTML = '🆇';
                    deleteBtn.onclick = () => {
                        const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                        const authorIndex = parseInt(authorRow.getAttribute('data-index'));
                        if (this.csl[index] && this.csl[index].author && this.csl[index].author[authorIndex]) {
                            this.csl[index].author.splice(authorIndex, 1); // Eliminar el autor del objeto csl
                            this.csl[index]._modified = true;
                            this.setDirty(true);
                        }
                        authorRow.remove(); // Eliminar el autor
                    }

                    authorRow.appendChild(label);
                    authorRow.appendChild(familyInput);
                    authorRow.appendChild(givenInput);
                    authorRow.appendChild(deleteBtn);
                    form.appendChild(authorRow);
                });
            }

            // Botón "Añadir autor"
            const addAuthorBtn = document.createElement("button");
            addAuthorBtn.type = "button";
            addAuthorBtn.className = "btn add-button mt-2";
            addAuthorBtn.textContent = "⊕ Añadir autor";
            addAuthorBtn.onclick = () => {
                const newAuthorRow = document.createElement("div");
                newAuthorRow.className = "author-row";
                newAuthorRow.setAttribute("data-index", form.querySelectorAll('.author-row').length);
                let nextId = form.querySelectorAll('.author-row').length + 1
                const label = document.createElement("label");
                label.textContent = `Autor ${nextId}`;

                const familyInput = document.createElement("input");
                familyInput.type = "text";
                familyInput.name = `family${nextId}`;
                familyInput.onkeyup = () => {
                    const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                    if (!this.csl[index]) {
                        this.csl[index] = {}; // Asegurarse de que el objeto exista
                    }
                    if (!this.csl[index].author) {
                        this.csl[index].author = []; // Asegurarse de que el array exista
                    }
                    if (!this.csl[index].author[nextId - 1]) {
                        this.csl[index].author[nextId - 1] = {}; // Asegurarse de que el objeto autor exista
                    }
                    this.csl[index].author[nextId - 1].family = familyInput.value; // Actualizar el atributo family
                    this.csl[index]._modified = true;
                    this.setDirty(true);
                    console.log(index, nextId - 1, 'family', familyInput.value);
                }

                const givenInput = document.createElement("input");
                givenInput.type = "text";
                givenInput.name = `given${nextId}`;
                givenInput.onkeyup = () => {
                    const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                    if (!this.csl[index]) {
                        this.csl[index] = {}; // Asegurarse de que el objeto exista
                    }
                    if (!this.csl[index].author) {
                        this.csl[index].author = []; // Asegurarse de que el array exista
                    }
                    if (!this.csl[index].author[nextId - 1]) {
                        this.csl[index].author[nextId - 1] = {}; // Asegurarse de que el objeto autor exista
                    }
                    this.csl[index].author[nextId - 1].given = givenInput.value; // Actualizar el atributo given
                    this.csl[index]._modified = true;
                    this.setDirty(true);
                    console.log(index, nextId - 1, 'given', givenInput.value);
                }

                // Botón de eliminar (basura)
                const deleteBtn = document.createElement("button");
                deleteBtn.type = "button";
                deleteBtn.className = "btn delete ms-2";
                deleteBtn.innerHTML = '🆇';
                deleteBtn.onclick = () => {
                    const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                    const authorIndex = parseInt(newAuthorRow.getAttribute('data-index'));
                    if (this.csl[index] && this.csl[index].author && this.csl[index].author[authorIndex]) {

                        this.csl[index].author.splice(authorIndex, 1); // Eliminar el autor del objeto csl
                        this.csl[index]._modified = true;
                        this.setDirty(true);

                    }
                    newAuthorRow.remove(); // Eliminar el autor
                }

                newAuthorRow.appendChild(label);
                newAuthorRow.appendChild(familyInput);
                newAuthorRow.appendChild(givenInput);
                newAuthorRow.appendChild(deleteBtn);

                // Insertar la nueva fila justo antes del botón "Añadir autor"
                form.insertBefore(newAuthorRow, addAuthorBtn);
                const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                if (!this.csl[index]) {
                    this.csl[index] = {}; // Asegurarse de que el objeto exista
                }
                if (!this.csl[index].author) {
                    this.csl[index].author = []; // Asegurarse de que el array exista
                }
                this.csl[index].author.push({ family: "", given: "" }); // Añadir un nuevo autor vacío
                this.csl[index]._modified = true;
                this.setDirty(true);
                console.log(index, 'Nuevo autor añadido');
            };

            // Añadir el botón para agregar autores
            if (item.author && Array.isArray(item.author)) {
                form.appendChild(addAuthorBtn);
            }

            // Manejar el resto de atributos
            Object.keys(item).forEach(attr => {
                if (attr !== "author" && attr !== "citation-number" & !attr.match(/^_/)) { // Omitir citation-number
                    const row = document.createElement("div");
                    row.className = "form-row"; // Clase para el estilo

                    const label = document.createElement("label");
                    label.htmlFor = `${attr}-input`;
                    label.textContent = attr;

                    const input = document.createElement("input");
                    input.type = "text";
                    input.name = attr;
                    input.value = item[attr] || "";
                    input.onkeyup = () => {
                        const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                        if (!this.csl[index]) {
                            this.csl[index] = {}; // Asegurarse de que el objeto exista
                        }
                        this.csl[index][attr] = input.value; // Actualizar el atributo en el
                        this.csl[index]._modified = true;
                        this.setDirty(true);
                        console.log(index, attr, input.value);
                    }

                    // Botón de eliminar (basura)
                    const deleteBtn = document.createElement("button");
                    deleteBtn.type = "button";
                    deleteBtn.className = "btn delete ms-2";
                    deleteBtn.innerHTML = '🆇';
                    deleteBtn.onclick = () => {
                        // Busca el data-index del div padre que contenga clase reference-card
                        const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                        delete (this.csl[index][attr]); // Eliminar el atributo del objeto csl
                        this.csl[index]._modified = true;
                        this.setDirty(true);
                        row.remove(); // Eliminar el campo
                    }

                    row.appendChild(label);
                    row.appendChild(input);
                    row.appendChild(deleteBtn);
                    form.appendChild(row);
                }
            });

            // Botón "Añadir campo"
            const addFieldBtn = document.createElement("button");
            addFieldBtn.type = "button";
            addFieldBtn.className = "btn add-button mt-2";
            addFieldBtn.textContent = "⊕ Añadir campo";
            addFieldBtn.onclick = () => {
                const newFieldRow = document.createElement("div");
                newFieldRow.className = "form-row"; // Clase para el estilo    
                const select = document.createElement("select");
                select.name = "new-field-selector";

                // Agregar todas las opciones del objeto al selector
                let valuesCount = 0;
                validFields.forEach(key => {
                    if (item.hasOwnProperty(key)) return; // Omitir si ya existe
                    const option = document.createElement("option");
                    option.value = key;
                    option.textContent = key;
                    select.appendChild(option);
                    valuesCount++;
                });
                if (valuesCount === 0) {
                    alert('No hay campos disponibles para añadir. Todos los campos ya están presentes.');
                    return;
                }
                const index = parseInt(addFieldBtn.closest('.reference-card').getAttribute('data-index'));
                select.setAttribute('data-old-value', select.value); // Guardar el valor actual como antiguo
                this.csl[index][select.value] = ""; // Asegurarse de que el campo exista en el objeto csl

                const input = document.createElement("input");
                input.type = "text";
                input.name = "new-field";
                input.value = "";
                input.onkeyup = () => {
                    const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                    this.csl[index][select.value] = input.value; // Actualizar el atributo en el objeto csl
                    this.csl[index]._modified = true;
                    this.setDirty(true);
                    console.log(index, select.value, input.value);
                }

                // Botón de eliminar (basura)
                const deleteBtn = document.createElement("button");
                deleteBtn.type = "button";
                deleteBtn.className = "btn delete ms-2";
                deleteBtn.innerHTML = '🆇';
                deleteBtn.onclick = () => newFieldRow.remove(); // Eliminar el campo

                select.onchange = (e) => {
                    //check selector old value
                    const oldValue = select.getAttribute('data-old-value');
                    if (oldValue) {
                        // Si hay un valor anterior, lo borramos del objeto csl
                        const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                        delete (this.csl[index][oldValue]);
                    }
                    select.setAttribute('data-old-value', select.value); // Guardar el nuevo valor como antiguo
                    const index = parseInt(deleteBtn.closest('.reference-card').getAttribute('data-index'));
                    this.csl[index][select.value] = input.value; // Actualizar el atributo en el objeto csl
                    this.csl[index]._modified = true;
                    this.setDirty(true);
                    console.log(index, select.value, input.value);

                }
                newFieldRow.appendChild(select);
                newFieldRow.appendChild(input);
                newFieldRow.appendChild(deleteBtn);

                // Insertar el nuevo campo justo antes del botón "Añadir campo"
                form.insertBefore(newFieldRow, addFieldBtn);
            };

            // Añadir el botón para agregar campos
            form.appendChild(addFieldBtn);
            const cardNumber = document.createElement("p");
            cardNumber.className = "card-number";
            cardNumber.textContent = index + 1;

            // Agregar el header (título + dropdown) y formulario al cuerpo de la tarjeta
            cardBody.appendChild(headerDiv);
            cardBody.appendChild(cardNumber);
            cardBody.appendChild(form);

            // Agregar la tarjeta al contenedor principal
            card.appendChild(cardBody);

            // Agregar la tarjeta al contenedor principal
            container.appendChild(card);
        });
        return container;
    }

    _xmlToHTML(xmlDoc) {
        const me = this;
        const serializer = new XMLSerializer();

        // Helper: copia atributos de un elemento a otro, fusionando 'class' si existe
        function copyAttributes(src, dest) {
            Array.from(src.attributes || []).forEach(attr => {
                if (attr.name === 'class') {
                    const classes = attr.value.split(/\s+/).filter(Boolean);
                    classes.forEach(c => dest.classList.add(c));
                } else {
                    dest.setAttribute(attr.name, attr.value);
                }
            });
        }

        // Helper: detecta si un nodo contiene hijos de tipo "block"
        function hasBlockChildren(node) {
            // lista razonable de elementos de bloque o estructuras de tabla/figura que queremos considerar "block"
            return !!node.querySelector && !!node.querySelector('p,div,table,thead,tbody,tfoot,tr,ul,ol,section,aside,figure,figcaption,blockquote');
        }

        // Convierte un fragmento XML en fragmento HTML (reparseando como 'text/html')
        // y además post-procesa <fn> dentro del fragmento (reemplazándolos por notas aptas para HTML).
        function parseXmlFragmentAsHtml(xmlNode) {
            const xmlString = serializer.serializeToString(xmlNode);
            const parsed = new DOMParser().parseFromString(xmlString, 'text/html');
            const frag = document.createDocumentFragment();
            Array.from(parsed.body.childNodes).forEach(n => frag.appendChild(n));

            // Post-proceso: reemplazar cualquier <fn> dentro del fragment por <span/div class="note"> adecuado.
            // Usamos un contenedor temporal para poder querySelector sobre el fragmento.
            const temp = document.createElement('div');
            temp.appendChild(frag.cloneNode(true));

            const fns = Array.from(temp.querySelectorAll('fn'));
            fns.forEach(fnEl => {
                // decide wrapper block/inline
                const useBlock = hasBlockChildren(fnEl);
                const wrapper = document.createElement(useBlock ? 'div' : 'span');
                wrapper.classList.add('note');

                // copiar atributos (incluido id)
                copyAttributes(fnEl, wrapper);

                // mover hijos (ya parseados en HTML)
                while (fnEl.firstChild) wrapper.appendChild(fnEl.firstChild);

                fnEl.replaceWith(wrapper);
            });

            // devolver un DocumentFragment con el resultado
            const outFrag = document.createDocumentFragment();
            Array.from(temp.childNodes).forEach(n => outFrag.appendChild(n));
            return outFrag;
        }

        // Crea un elemento note a partir de un <fn> que viene del XML (si no ha sido parseado aún)
        function createNoteFromFnNode(fnNode) {
            const useBlock = hasBlockChildren(fnNode);
            const wrapper = document.createElement(useBlock ? 'div' : 'span');
            wrapper.classList.add('note');

            copyAttributes(fnNode, wrapper);

            // procesar hijos recursivamente
            Array.from(fnNode.childNodes).forEach(child => {
                const conv = convertNode(child);
                wrapper.appendChild(conv instanceof DocumentFragment ? conv : conv);
            });

            return wrapper;
        }

        // Convierte un nodo XML (o HTML) en nodos HTML adecuados (puede devolver DocumentFragment o Node)
        function convertNode(node) {
            if (!node) return document.createDocumentFragment();

            // Text node
            if (node.nodeType === Node.TEXT_NODE) {
                return document.createTextNode(node.nodeValue);
            }

            // Comment node (opcional)
            if (node.nodeType === Node.COMMENT_NODE) {
                return document.createComment(node.nodeValue);
            }

            // Si no es elemento, importar como fallback
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return document.importNode(node, true);
            }

            const tag = node.tagName.toLowerCase();

            // <sec>
            if (tag === 'sec') {
                return processSec(node, 1);
            }

            // <fn> -> <span|div class="note">
            if (tag === 'fn') {
                return createNoteFromFnNode(node);
            }

            // <fig> -> <img> (conservar caption como alt si existe)
            if (tag === 'fig') {
                const frag = document.createDocumentFragment();
                const img = document.createElement('img');
                const graphic = node.querySelector('graphic');
                if (graphic) {
                    const href = me.imagePath + (graphic.getAttribute('xlink:href') || graphic.getAttribute('href') || '');
                    img.src = href.trim();
                }
                img.alt = node.querySelector('caption') ? node.querySelector('caption').textContent.trim() : 'Figura sin título';
                img.className = 'figure-img';
                frag.appendChild(img);
                return frag;
            }

            // Tablas: si es 'table', 'table-wrap' (JATS) o contiene un <table>, reparseamos como HTML
            const maybeTable = (tag === 'table' || tag === 'table-wrap' || (node.querySelector && node.querySelector('table')));
            if (maybeTable) {
                return parseXmlFragmentAsHtml(node);
            }

            // Por defecto: reparsear como HTML para forzar namespace HTML y evitar elementos "extraños"
            try {
                return parseXmlFragmentAsHtml(node);
            } catch (e) {
                // fallback: importar directamente
                return document.importNode(node, true);
            }
        }

        // Procesa un <sec> recursivamente (transforma title en hN y procesa hijos)
        function processSec(secElement, level) {
            const container = document.createElement('div');

            // Título directo
            const title = secElement.querySelector(':scope > title');
            if (title) {
                const heading = document.createElement(`h${Math.min(level, 6)}`);
                heading.textContent = title.textContent;
                container.appendChild(heading);
            }

            // Procesar todos los hijos (incluso nodos de texto) excepto el title ya procesado
            Array.from(secElement.childNodes).forEach(child => {
                if (child.nodeType === Node.ELEMENT_NODE && child.tagName.toLowerCase() === 'title') return;
                const converted = convertNode(child);
                container.appendChild(converted instanceof DocumentFragment ? converted : converted);
            });

            // Normalizar para unir text nodes adyacentes
            container.normalize();
            return container;
        }

        // --- Main ---
        const htmlContainer = document.createElement('div');
        const body = xmlDoc.querySelector('body');
        if (!body) return htmlContainer;

        Array.from(body.childNodes).forEach(child => {
            if (child.nodeType === Node.ELEMENT_NODE && child.tagName.toLowerCase() === 'sec') {
                htmlContainer.appendChild(processSec(child, 1));
            } else {
                const converted = convertNode(child);
                htmlContainer.appendChild(converted instanceof DocumentFragment ? converted : converted);
            }
        });

        // Normalizar el resultado final
        htmlContainer.normalize();
        return htmlContainer;
    }


}
