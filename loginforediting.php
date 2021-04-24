<?php

if (isset($_REQUEST['editing_password'])) {
	setcookie('editing', $_REQUEST['editing_password'], time() + 24*60*60);
	header('Location: /');
	exit;
}

if (isset($_GET['enable_editing'])) {
	echo '<form method="post">Password: <input type="password" name="editing_password"><button type="submit">Set and return</button></form>';
	exit;
}
