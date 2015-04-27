<!DOCTYPE html>
<html>
    <head>

        <?php echo $this->combine ('sections/head.phpt'); ?>

    </head>

    <body>

        <div id="authentication">
	        <?php echo $this->help ('CatLab.OpenIDClient.LoginForm'); ?>
        </div>

        <?php echo $content; ?>

    </body>
</html>