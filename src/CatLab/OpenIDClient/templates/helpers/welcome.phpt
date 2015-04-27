<?php $this->textdomain ('catlab.openidclient'); ?>

<?php if ($user) { ?>
	<p>
		<?php echo sprintf ($this->gettext ('Welcome, %s.'), $user->getUsername ()); ?>
		<a href="<?php echo $logout; ?>"><?php echo $this->getText ('logout'); ?></a>
	</p>
<?php } else { ?>

	<p>
		<?php echo sprintf ($this->gettext ('Welcome, %s.'), 'stranger'); ?>
		<a href="<?php echo $login; ?>"><?php echo $this->getText ('login'); ?></a>
	</p>

<?php } ?>