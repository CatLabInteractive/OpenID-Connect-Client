<?php
    $this->layout ($layout);
?>

<h2>Welcome!</h2>
<p>
    Please <a href="<?php echo $redirectUrl; ?>">click here</a> to continue.
</p>

<?php if ($tryJavascript) { ?>
    <script>window.location = '<?php echo $redirectUrl; ?>';</script>
<?php } ?>
