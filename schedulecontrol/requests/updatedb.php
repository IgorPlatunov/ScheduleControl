<?php
	use ScheduleControl\Core\Logs;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(null, function($data) {
		AJAXRequest::CheckUserAccess("Super");

		DataBaseUpdater::Update();

		Logs::Write("Обновлена структура базы данных (пользователь: ".Session::CurrentUser()->GetLogin().")");
	});
?>