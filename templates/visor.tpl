<!DOCTYPE html>
<html xmlns:mml="https://www.w3.org/1998/Math/MathML">
  <head>
    <title>eLife Lens</title>
    <link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,400italic,600italic' rel='stylesheet' type='text/css'>
    
    <link rel="stylesheet" type="text/css" media="all" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" />
    <link rel="stylesheet" type="text/css" media="all" href="{$assetsUrl}/lens/lens.css" />


    <script src="{$assetsUrl}/js/jquery.min.js"></script>
    <script src="{$assetsUrl}/lens/lens.js"></script>

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
        document_url: '{$engineBaseUrl}&op=xml',
        converterOptions:{
          baseURL: '{$engineBaseUrl}&op=img&img=',
        }
      });

      app.start();
      window.app = app;
      });

    </script>
  </body>
</html>
