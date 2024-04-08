<?php
	use ScheduleControl\Core\{Logs, DataBase, DBTableRegistryCollections as Collections};
	use ScheduleControl\Logic\{DBLoadSubject, YearGroupLoadSubjectLecturer, DBLoadSubjectLecturer, YearLoad};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("LoadSubjectLecturerRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$year = AJAXRequest::GetIntParameter("year");

		$load = YearLoad::GetFromDatabase($year);
		if (!isset($load)) AJAXRequest::ClientError(0, "Нагрузка на год не найдена");

		$response = array();

		foreach ($load->GetLoads() as $gload)
		{
			$data = array(
				"load" => $gload->GetID()->ToJSON(true),
				"subjects" => array(),
			);

			foreach ($gload->GetSubjects() as $subject)
			{
				$info = array(
					"id" => $subject->GetID()->ToJSON(true),
					"semester" => $subject->GetSemester(),
					"lecturers" => array(),
				);

				foreach ($subject->GetLecturers() as $lecturer)
					$info["lecturers"][] = $lecturer->ToJSON(true);

				$data["subjects"][] = $info;
			}

			$response[] = $data;
		}
		
		AJAXRequest::Response($response);
	},
	function($data) {
		AJAXRequest::CheckUserAccess("LoadSubjectLecturerWrite");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		DataBase::Transaction(function() use($data) {
			$tocheck = array();

			if (isset($data["delete"]))
				foreach ($data["delete"] as $lect)
				{
					$lectid = DBLoadSubjectLecturer::FromJSON($lect);
					$lecturer = YearGroupLoadSubjectLecturer::GetFromDatabase($lectid);
					if (!isset($lecturer))
						AJAXRequest::ClientError(2, "Обнаружена некорректная связь преподавателя и предмета нагрузки для удаления");

					$tocheck[$lecturer->GetSubject()->GetID()] = true;
					$lecturer->DeleteFromDatabase();
				}

			if (isset($data["save"]))
				foreach ($data["save"] as $lect)
				{
					$lecturer = YearGroupLoadSubjectLecturer::FromJSON($lect);

					if (!$lecturer->GetSubject()->IsValid()) AJAXRequest::ClientError(0, "Обнаружен некорректный предмет нагрузки для сохранения");
					if (!$lecturer->GetLecturer()->IsValid()) AJAXRequest::ClientError(1, "Обнаружен некорректный преподаватель для сохранения");

					$tocheck[$lecturer->GetSubject()->GetID()] = true;
					$lecturer->SaveToDatabase();
				}

			foreach ($tocheck as $sid => $_)
			{
				$subject = new DBLoadSubject($sid);

				if (count($subject->GetLecturers(true)) == 0)
					AJAXRequest::ClientError(3, "Предмет ".$subject->GetName()." нагрузки ".$subject->GetLoad()->GetName()." остается без основного преподавателя");
			}

			Logs::Write("Изменены закрепления преподавателей за предметами нагрузки (пользователь: ".Session::CurrentUser()->GetLogin().")");
		});
	});
?>