<?php
	require_once("php/session.php");
	require_once("php/pagerequests.php");

	Session::Begin();

	PageRequest::LoadActions("authorization", "logout");

	if (Session::CurrentUser() !== null)
		include("php/mainpage.php");
	else
		include("php/authorization.php");
?>