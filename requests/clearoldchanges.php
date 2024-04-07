<?php
	use ScheduleControl\Core\{Logs, DBTableRegistryCollections as Collections};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(null, function($data) {
		AJAXRequest::CheckUserAccess("Super");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		foreach (Collections::GetCollections() as $name => $collection)
			$collection->ClearAllOldChanges();

		Logs::Write("Очищены старые данные из регистров (пользователь: ".Session::CurrentUser()->GetLogin().")");
	});
?>