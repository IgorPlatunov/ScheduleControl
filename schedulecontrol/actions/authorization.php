<?php
	use ScheduleControl\Core\Logs;

	if (PageRequest::GetParameter("type") !== "login") return;

	if (PageRequest::IsParameterSet("login") && PageRequest::IsParameterSet("password"))
		PageRequest::SetActionVar("wrongpassword", true);

	if (Session::Login(PageRequest::GetParameter("login"), PageRequest::GetParameter("password")))
		Logs::Write("Произведен вход в учетную запись ".Session::CurrentUser()->GetLogin()." (клиент ".$_SERVER["REMOTE_ADDR"].")");
	else
		PageRequest::SetActionVar("wrongpassword", true);
?>