<?php
	use ScheduleControl\Core\{Logs, DBTableRegistryCollections as Collections};
	use ScheduleControl\Logic\{Curriculum, CurriculumSubject, DBCurriculum};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("CurriculumRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$id = AJAXRequest::GetIntParameter("id");

		$cid = new DBCurriculum($id);
		if (!$cid->IsValid()) AJAXRequest::ClientError(0, "Некорректный учебный план");

		$curriculum = Curriculum::GetFromDatabase($cid);
		if (!isset($curriculum)) AJAXRequest::ClientError(1, "Учебный план не найден");

		AJAXRequest::Response($curriculum->ToJSON(true));
	},
	function($data) {
		AJAXRequest::CheckUserAccess("CurriculumWrite");

		$type = AJAXRequest::GetParameter("type");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		if ($type == "save")
		{
			$curriculum = Curriculum::FromJSON($data);
			$curriculum->SaveToDatabase();

			Logs::Write("Сохранён учебный план ".$curriculum->GetID()->GetName()." (пользователь: ".Session::CurrentUser()->GetLogin().")");

			AJAXRequest::Response($curriculum->GetID()->ToJSON(true));
		}
		else if ($type == "delete" || $type == "subject")
		{
			$cid = new DBCurriculum(AJAXRequest::GetIntParameter("id"));
			if (!$cid->IsValid()) AJAXRequest::ClientError(1, "Некорректный учебный план");

			$curriculum = Curriculum::GetFromDatabase($cid);
			if (!isset($curriculum)) AJAXRequest::ClientError(2, "Учебный план не найден");

			if ($type == "delete")
			{
				$name = $cid->GetName();
				$curriculum->DeleteFromDatabase();

				Logs::Write("Удалён учебный план $name (пользователь: ".Session::CurrentUser()->GetLogin().")");
			}
			else
			{
				$subject = CurriculumSubject::FromJSON($data);

				if (count($subject->GetCourses()) > 0)
				{
					$curriculum->SetSubject($subject);

					Logs::Write("Сохранён предмет учебного плана ".$subject->GetSubject()->GetName()." (пользователь: ".Session::CurrentUser()->GetLogin().")");
				}
				else
				{
					$curriculum->RemoveSubject($subject->GetSubject());
					
					Logs::Write("Удалён предмет учебного плана ".$subject->GetSubject()->GetName()." (пользователь: ".Session::CurrentUser()->GetLogin().")");
				}
			}
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>	