<!DOCTYPE html>
<html xmlns:mml="https://www.w3.org/1998/Math/MathML">
  <head>
    <title>eLife Lens</title>
    <link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,400italic,600italic' rel='stylesheet' type='text/css'>
    
    <link rel="stylesheet" type="text/css" media="all" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" />
    <link rel="stylesheet" type="text/css" media="all" href="<?= JATSWIZARD_ASSETS_URL ?>/lens/lens.css" />


    <script src="<?= JATSWIZARD_ASSETS_URL ?>/js/jquery.min.js"></script>
    <script src="<?= JATSWIZARD_ASSETS_URL ?>/lens/lens.js"></script>

    <!-- MathJax Configuration -->

  </head>
  <body>
    <script>
      // Información de la conversión y ficheros
      

      $(function() {

      // Create a new Lens app instance
      // --------
      //
      // Injects itself into body

      var app = new window.Lens({
        document_url: '<?= $_SESSION['jatsWizardState']['engineBaseUrl'] ?>&op=xml',
        converterOptions:{
          baseURL: '<?= $_SESSION['jatsWizardState']['engineBaseUrl'] ?>&op=img&img=',
        }
      });

      app.start();

      window.app = app;
      createHeader();

      });
      function createHeader(){
        let header = document.createElement('div');

        header.className="um-header";
        header.innerHTML = 'EDIT.UM - Revistas científicas - Universidad de Murcia';
        document.body.prepend(header);
      }

    </script>
  </body>
</html>
