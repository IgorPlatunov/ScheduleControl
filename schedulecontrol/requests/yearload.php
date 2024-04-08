<?php
	use ScheduleControl\Logic\{YearLoad, YearLoadConstructor};
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use ScheduleControl\Core\Logs;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("YearLoadRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$year = AJAXRequest::GetIntParameter("year", false) ?? false;

		if ($year)
		{
			$build = AJAXRequest::GetBoolParameter("build", false) ?? false;

			$load = null;

			if ($build)
			{
				AJAXRequest::CheckUserAccess("YearLoadWrite");

				$load = YearLoadConstructor::BuildFromCurriculums($year);
			}
			else
			{
				$load = YearLoad::GetFromDatabase($year);
				if (!isset($load)) AJAXRequest::ClientError(0, "Нагрузка на год не найдена");
			}

			AJAXRequest::Response($load->ToJSON(true));
		}
		else
		{
			$years = array();
			$cache = array();

			foreach (Collections::GetCollection("YearGroupLoads")->GetAllLastChanges() as $load)
			 if (!isset($cache[$year = $load->GetData()->GetValue("Year")]))
				$years[] = $cache[$year] = $year;

			sort($years);

			AJAXRequest::Response($years);
		}
	},
	function($data) {
		AJAXRequest::CheckUserAccess("YearLoadWrite");

		$type = AJAXRequest::GetParameter("type");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		if ($type == "delete")
		{
			$year = AJAXRequest::GetIntParameter("year");
			
			$yload = YearLoad::GetFromDatabase($year);
			if (!isset($yload)) AJAXRequest::ClientError(1, "Нагрузка на год не найдена");

			$yload->DeleteFromDatabase();

			Logs::Write("Удалена нагрузка групп на ".$yload->GetYear()." год (пользователь: ".Session::CurrentUser()->GetLogin().")");
		}
		else if ($type == "save")
		{
			$yload = YearLoad::FromJSON($data);

			if (count($yload->GetLoads()) == 0)
				AJAXRequest::ClientError(2, "Нагрузка на год пустая");

			foreach ($yload->GetLoads() as $id => $load)
			{
				if (strlen($load->GetName()) == 0)
					AJAXRequest::ClientError(3, "Нагрузка группы #".($id + 1)." не имеет названия");

				if (count($load->GetGroups()) == 0)
					AJAXRequest::ClientError(4, "Нагрузка группы ".$load->GetName()." не имеет учебных групп");

				foreach ($load->GetGroups() as $gid => $group)
					if (!$group->GetGroup()->IsValid())
						AJAXRequest::ClientError(5, "Нагрузка группы ".$load->GetName()." имеет некорректную учебную группу #".($gid + 1));

				if (count($load->GetSubjects()) == 0)
					AJAXRequest::ClientError(6, "Нагрузка группы ".$load->GetName()." не имеет предметов");

				foreach ($load->GetSubjects() as $sid => $subject)
				{
					if (!$subject->GetSubject()->IsValid())
						AJAXRequest::ClientError(7, "Нагрузка группы ".$load->GetName()." имеет некорректный предмет #".($sid + 1));

					if (strlen($subject->GetAbbreviation()) == 0)
						AJAXRequest::ClientError(8, "Предмет #".($sid + 1)." нагрузки ".$load->GetName()." имеет пустое сокращение");

					if (strlen($subject->GetName()) == 0)
						AJAXRequest::ClientError(9, "Предмет ".$subject->GetAbbreviation()." нагрузки ".$load->GetName()." имеет пустое название");

					if ($subject->GetHours() == 0)
						AJAXRequest::ClientError(10, "Предмет ".$subject->GetAbbreviation()." (семестр ".$subject->GetSemester().") нагрузки ".$load->GetName()." не имеет общего количества часов");
				}
			}

			$yload->SaveToDatabase();

			Logs::Write("Сохранена нагрузка групп на ".$yload->GetYear()." год (пользователь: ".Session::CurrentUser()->GetLogin().")");
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>