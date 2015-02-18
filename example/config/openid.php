<?php

return array (

	'client' => array (

		'scope' => 'openid email profile',

		'client_id' => '360569433491-veemdd3of9kg2978i2c29bfib21p22eg.apps.googleusercontent.com',
		'redirect_uri' => 'http://openidclient.catlab.local.com/account/login/next',

		'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/auth',
		'token_endpoint' => 'https://accounts.google.com/o/oauth2/token',
		'user_info_endpoint' => 'https://www.googleapis.com/oauth2/v1/userinfo',

		'authentication_info' => array(
			'method' => 'client_secret_post',
			'params' => array(
				'client_secret' => 'aMsHysHzUiqL-Dd8rLH9Ofgy'
			)
		)

	)

);