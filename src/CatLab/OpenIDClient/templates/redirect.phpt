<?php
$this->layout ($layout);
$this->textdomain('catlab.openidclient');
?>

<p>
    <?php echo sprintf($this->gettext('Please wait while we redirect you or %s to continue.'), '<a href="<?php echo $redirectUrl; ?>">' . $this->gettext('click here') . '</a>'); ?>
</p>

<?php if ($tryJavascript) { ?>
    <script type="text/javascript">
        window.onload = function() {
            setTimeout(function() {
                window.location = '<?php echo $redirectUrl; ?>';
            }, 10);
        };
    </script>
<?php } ?>
