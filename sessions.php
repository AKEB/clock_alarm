<?php

function session_check() {
	global $USERS;

	if (isset($_POST['sign_in']) && intval($_POST['sign_in'])) {
		session_login($_POST['login'], $_POST['password']);
	}

	do {
		$error = true;
		$session = $_COOKIE['session'] ?? null;
		if (!$session) break;
		$session = @base64_decode($session);
		if (!$session) break;
		$session = @json_decode($session, true);
		if (!$session || !is_array($session)) break;
		if (!isset($session['login']) || !isset($session['token'])) break;
		if (!isset($USERS[$session['login']])) break;
		$token = md5($session['login'] . $USERS[$session['login']]);
		if ($token !== $session['token']) break;
		$error = false;
	} while(0);

	if ($error) {
		setcookie('session', '', time() + 1, '/');
		$_COOKIE['session'] = '';
		return false;
	} else {
		return true;
	}
}

function session_login($login, $password) {
	global $USERS;

	setcookie('session', '', time() + 1, '/');
	$_COOKIE['session'] = '';

	if (!isset($login) || !$login) return false;
	if (!isset($password) || !$password) return false;
	if (!isset($USERS[$login])) return false;
	if ($password != $USERS[$login]) return false;

	$token = md5($login . $USERS[$login]);
	$session = [
		'login' => $login,
		'token' => $token,
	];

	$session = base64_encode(json_encode($session));
	setcookie('session', $session, time() + 60 * 60 * 24 * 90, '/');
	$_COOKIE['session'] = $session;
	return true;
}
