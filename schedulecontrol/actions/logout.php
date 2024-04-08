<?php
	use ScheduleControl\Core\Logs;

	if (PageRequest::GetParameter("type") === "logout" && Session::CurrentUser() !== null)
	{
		Logs::Write("Произведен выход из учетной записи ".Session::CurrentUser()->GetLogin()." (клиент ".$_SERVER["REMOTE_ADDR"].")");

		Session::Logout();
	}
?>