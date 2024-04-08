<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use ScheduleControl\UserConfig;

	final class YearLoadConstructor
	{
		static function BuildFromCurriculums(int $year): YearLoad
		{
			$colgroups = Collections::GetCollection("Groups");
			$yload = new YearLoad($year);

			foreach ($colgroups->GetAllLastChanges() as $group)
			{
				$cid = new DBCurriculum($group->GetData()->GetDBValue("Curriculum"));
				if (!$cid->IsValid()) continue;

				$grp = new DBGroup($group->GetData()->GetValue("ID"));

				$course = Utilities::GetGroupCourse($grp, $year);
				if (!isset($course)) continue;

				$curriculum = Curriculum::GetFromDatabase($cid);
				if (!isset($curriculum)) continue;

				$gload = new YearGroupLoad(null, $group->GetData()->GetFullName());
				$gload->AddGroup(new YearGroupLoadGroup(null, $grp));

				$students = $grp->GetValue()->GetValue("BudgetStudents") + $grp->GetValue()->GetValue("PaidStudents");

				foreach ($curriculum->GetSubjects() as $sid => $subject)
					if ($subject->GetSubject()->IsValid() && $subject->GetCourse($course) !== null)
						foreach ($subject->GetCourse($course)->GetSemesters() as $semester => $info)
							$gload->AddSubject(new YearGroupLoadSubject(
								null,
								$subject->GetSubject(),
								$subject->GetSubject()->GetValue()->GetValue("Name"),
								$subject->GetSubject()->GetValue()->GetValue("Abbreviation"),
								$semester, $info->GetHours(), $info->GetWHours(),
								$info->GetLPHours(),
								$info->GetCDHours(),
								$info->GetCDHours() > 0 ? ceil($students * UserConfig::GetParameter("HoursCDPerStudent")) : 0,
								$info->HasExam() ? ceil($students * UserConfig::GetParameter("HoursEPerStudent")) : 0,
								$info->GetWHours() <= 1 || $subject->GetSubject()->GetValue()->GetValue("Optional") == 1,
							));

				if (count($gload->GetSubjects()) > 0) $yload->AddLoad($gload);
			}

			return $yload;
		}

		static function BuildLecturerLoad(YearLoad $yload, DBLecturer $lecturer): LecturerYearLoad
		{
			$lload = new LecturerYearLoad($lecturer);
			$subjects = array();

			foreach ($yload->GetLoads() as $gload)
				foreach ($gload->GetSubjects() as $subject)
					foreach ($subject->GetLecturers() as $lect)
					{
						if ($lecturer->GetID() != $lect->GetLecturer()->GetID()) continue;

						$lsubject = null;

						foreach ($subjects as $data)
							if (
								$data->GetSubject()->GetSubject()->GetID() == $subject->GetSubject()->GetID() &&
								$data->GetSubject()->GetValue()->GetValue("Name") == $subject->GetName() &&
								$data->GetSubject()->GetValue()->GetValue("Abbreviation") == $subject->GetAbbreviation()
							)
							{ $lsubject = $data; break; }

						if (!isset($lsubject))
						{
							$lsubject = new LecturerYearLoadSubject($subject->GetID());
							$subjects[] = $lsubject;

							$lload->AddSubject($lsubject);
						}

						$shours = $subject->GetHours();
						$hours = $lect->GetID()->GetHours();
						$whours = $lect->IsMain() ? $lect->GetID()->GetWHours() : 0;
						$lphours = $subject->GetLPHours();
						$cdhours = $subject->GetCDHours();
						$cdphours = $lect->GetID()->GetCDPHours();
						$ehours = $lect->GetID()->GetEHours();

						foreach ($gload->GetGroups() as $group)
						{
							$cursemester = $lsubject->GetGroup($group->GetGroup())?->GetSemester($subject->GetSemester());
							if (isset($cursemester))
							{
								$hours += $cursemester->GetHours();
								$whours += $cursemester->GetWHours();
								$lphours += $cursemester->GetLPHours();
								$cdhours += $cursemester->GetCDHours();
								$cdphours += $cursemester->GetCDPHours();
								$ehours += $cursemester->GetEHours();
							}

							$lsubject->GetGroup($group->GetGroup(), true)->AddSemester($subject->GetSemester(), new LecturerYearLoadSubjectGroupSemester(
								$shours, $hours, $whours, $lphours, $cdhours, $cdphours, $ehours,
							));
						}
					}
			
			return $lload;
		}
	}
?>