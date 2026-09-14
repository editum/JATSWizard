[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.18958457.svg)](https://doi.org/10.5281/zenodo.18958457)

# Docx to JATS Assistant Plugin for OJS

[English](#english) | [Español](#español)

---

<a name="english"></a>
## English

This repository contains an **Open Journal Systems (OJS) plugin** that serves as a **guided assistant for converting DOCX documents to XML-JATS**.  

The plugin provides a **4-step workflow** designed to guide editors and technical staff throughout the tagging and validation process, significantly reducing the manual effort required to generate high-quality XML-JATS from Word documents.

The primary objective is to facilitate the adoption of the XML-JATS standard in real editorial workflows, maintaining a balance between **automated tagging** and **assisted review**.

**Supported version:** 3.3.x

### Installation

Plugin installation is performed **the same way as any other OJS plugin**:

1. Download the archive (`.tar.gz` or `.zip`) from the repository's **Releases** section:
   - https://github.com/editum/JATSWizard/releases

2. Extract the file into the OJS plugins directory: `plugins/generic/`

3. Log in to the OJS administration panel and enable the plugin under: **Settings → Website → Plugins → Generic Plugins**

New versions of the plugin will be published exclusively through the **Releases** section, which should be considered the official distribution channel.

### External Dependency: Docx → JATS Conversion Pipeline

⚠️ **This plugin does not perform the conversion directly.**

The OJS assistant relies on an **external conversion pipeline**, responsible for transforming the DOCX document into XML-JATS and applying the auto-tagging rules.

This pipeline is located in the following repository and **must be installed on the same server as OJS**:

👉 https://github.com/editum/docxtojats-pipeline

During the plugin configuration in OJS, you will be explicitly prompted for:

- **The path to the `docxtojats-pipeline` binary**

For example:

```text
/opt/docxtojats-pipeline/bin/console
```

---

<a name="español"></a>
## Español

Este repositorio contiene un **plugin para Open Journal Systems (OJS)** que actúa como **asistente guiado de conversión de documentos DOCX a XML-JATS**.  

El plugin ofrece un flujo de trabajo en **4 pasos**, pensado para acompañar a editores y personal técnico durante el proceso de marcado y validación, reduciendo significativamente el esfuerzo manual necesario para generar XML-JATS de calidad a partir de documentos Word.

El objetivo principal es facilitar la adopción del estándar XML-JATS en flujos editoriales reales, manteniendo un equilibrio entre **automarcado automático** y **revisión asistida**.

**Versión soportada:** 3.3.x

### Instalación

La instalación del plugin se realiza **igual que cualquier otro plugin de OJS**.

1. Descarga el paquete comprimido (`.tar.gz` o `.zip`) desde la sección **Releases** del repositorio:
   - https://github.com/editum/JATSWizard/releases

2. Descomprime el fichero en el directorio de plugins de OJS: `plugins/generic/`

3. Accede al panel de administración de OJS y habilita el plugin desde: **Ajustes → Sitio web → Plugins → Plugins genéricos**

Las nuevas versiones del plugin se publicarán exclusivamente a través de la sección **Releases**, que debe considerarse el canal oficial de distribución.

### Dependencia externa: pipeline de conversión Docx → JATS

⚠️ **Este plugin no realiza la conversión directamente.**

El asistente de OJS se apoya en un **pipeline de conversión externo**, responsable de transformar el documento DOCX en XML-JATS y de aplicar las reglas de automarcado.

Este pipeline se encuentra en el siguiente repositorio y **debe estar instalado en el mismo servidor que OJS**:

👉 https://github.com/editum/docxtojats-pipeline

Durante la configuración del plugin en OJS se solicitará explícitamente:

- **La ruta al binario `docxtojats-pipeline`**

Por ejemplo:

```text
/opt/docxtojats-pipeline/bin/console
```

---

## Sponsors

<p style="background:white"> <img src="assets/doc/partners/logo-um.png" width="200"> <img src="assets/doc/partners/logo-ministerio.svg" width="200"> <img src="assets/doc/partners/logo-fecyt.svg" width="200"> <img src="assets/doc/partners/convocatoria-mdg.svg" width="200"> </p>
