<!DOCTYPE html>
<html xmlns:mml="https://www.w3.org/1998/Math/MathML">
  <head>
    <title>eLife Lens</title>
    <link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,400italic,600italic' rel='stylesheet' type='text/css'>
    
    <link rel="stylesheet" type="text/css" media="all" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" />

    <!-- A combined lens.css will be generated in the bundling process --> 
    <!-- While in development, separate links for each CSS file are added, so we don't need a source map -->
    <link href='assets/preview.css' rel='stylesheet' type='text/css'/>

    <script src="assets/js/jquery.min.js"></script>

    <!-- MathJax Configuration -->
  <style>
    body{
      font-family: 'Open Sans', sans-serif;
      height: 100%;
    }
    html{
      height: 100%;
    }
    h1{
      background: lightblue;
      padding: 10px;
      margin: 40px auto;
      display: inline-block;
      text-align: center;
      border-radius: 4px;
      border: 1px solid blue;
    }
    .version{
      position: absolute;
      bottom: 5px;
      right: 5px;
      font-size: 0.8em;
    }
  </style>
  </head>
  <body>
    <div class="version">v1.0</div>
    <div style="text-align:center">
      <h1> Subir archivo DocX o XML</h1>
      <form action="index.php?op=upload" method="post" enctype="multipart/form-data">
        <?php if (!empty($ERROR)){ ?>
          <div class="error">
            <?php echo $ERROR;?>
          </div>
        <?php } ?>
        <div style="padding-top: 1em">
          <input id="file-input" name="file" type="file" />
        </div>
        <div style="padding-top: 1em">
          <label for="normalize">Optimizar documento</label>
          <input name="normalize" type="checkbox" value="true">
        </div>
        <div style="padding-top: 1em">
          <label for="automark-citation-style">Automarcar estilo citación:</label>
          <select name="automark-citation-style">
            <option selected="selected"></option>
            <option value="ama">AMA</option>
            <option value="apa">APA</option>
            <option value="vancouver">Vancouver</option>
          </select>
        </div>
        <div style="padding-top: 1em">
          <label for="automark-set-bibliography-mixed-citations">Aplicar compatibilidad Scielo</label>
          <input type="checkbox" name="automark-set-bibliography-mixed-citations" value="true">
        </div>
        <div style="padding-top: 1em">
          <label for="automark-set-figures-titles">Establecer títulos de imágenes</label>
          <input type="checkbox" name="automark-set-figures-titles" value="true">
        </div>
        <div style="padding-top: 1em">
          <label for="automark-set-tables-titles">Establecer títulos de tablas</label>
          <input type="checkbox" name="automark-set-tables-titles" value="true">
        </div>
        <div style="padding-top: 1em">
          <label for="automark-set-title-references">Sustituir el párrafo de título por una referencia</label>
          <input type="checkbox" name="automark-set-title-references" value="true">
        </div>
      </form>
    </div>
  </body>
  <script>
    $(function(){
      $('#file-input').on('change', function(){
        $('form').submit();
      });
    });
  </script>
</html>

