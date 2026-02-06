# Docx to JATS Assistant Plugin for OJS

Este repositorio contiene un **plugin para Open Journal Systems (OJS)** que actúa como **asistente guiado de conversión de documentos DOCX a XML-JATS**.  

El plugin ofrece un flujo de trabajo en **4 pasos**, pensado para acompañar a editores y personal técnico durante el proceso de marcado y validación, reduciendo significativamente el esfuerzo manual necesario para generar XML-JATS de calidad a partir de documentos Word.

El objetivo principal es facilitar la adopción del estándar XML-JATS en flujos editoriales reales, manteniendo un equilibrio entre **automarcado automático** y **revisión asistida**.

---

## Instalación

La instalación del plugin se realiza **igual que cualquier otro plugin de OJS**.

1. Descarga el paquete comprimido (`.tar.gz` o `.zip`) desde la sección **Releases** del repositorio:
   - https://github.com/editum/JATSWizard/releases

2. Descomprime el fichero en el directorio de plugins de OJS: `plugins/generic/`

3. Accede al panel de administración de OJS y habilita el plugin desde: **Ajustes → Sitio web → Plugins → Plugins genéricos**

Las nuevas versiones del plugin se publicarán exclusivamente a través de la sección **Releases**, que debe considerarse el canal oficial de distribución.

## Dependencia externa: pipeline de conversión Docx → JATS

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

#Sponsors
<p style="background:white"> <img src="assets/doc/partners/logo-ministerio.svg" width="200"> <img src="assets/doc/partners/logo-fecyt.svg" width="200"> <img src="assets/doc/partners/convocatoria-mdg.svg" width="200"> </p>