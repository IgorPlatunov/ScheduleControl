<?php
	use ScheduleControl\Core\{Logs, DBTableRegistryCollections as Collections};
	use ScheduleControl\Logic\{SubjectRoomCategory, DBSubjectRoomCategory};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("ScheduleRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$id = AJAXRequest::GetIntParameter("id");

		$cid = new DBSubjectRoomCategory($id);
		if (!$cid->IsValid()) AJAXRequest::ClientError(0, "Некорректная категория");

		$category = SubjectRoomCategory::GetFromDatabase($cid);
		if (!isset($category)) AJAXRequest::ClientError(1, "Категория не найдена");

		AJAXRequest::Response($category->ToJSON(true));
	},
	function($data) {
		AJAXRequest::CheckUserAccess("ScheduleWrite");

		$type = AJAXRequest::GetParameter("type");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		if ($type == "save")
		{
			$category = SubjectRoomCategory::FromJSON($data);
			$category->SaveToDatabase();

			Logs::Write("Сохранена категория предметов и кабинетов ".$category->GetName()." (пользователь: ".Session::CurrentUser()->GetLogin().")");

			AJAXRequest::Response($category->GetID()->ToJSON(true));
		}
		else if ($type == "delete")
		{
			$cid = new DBSubjectRoomCategory(AJAXRequest::GetIntParameter("id"));
			if (!$cid->IsValid()) AJAXRequest::ClientError(1, "Некорректная категория");

			$category = SubjectRoomCategory::GetFromDatabase($cid);
			if (!isset($category)) AJAXRequest::ClientError(2, "Категория не найдена");

			$category->DeleteFromDatabase();

			Logs::Write("Удалена категория предметов и кабинетов ".$category->GetName()." (пользователь: ".Session::CurrentUser()->GetLogin().")");
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>